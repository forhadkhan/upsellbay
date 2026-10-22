# Pro 1.0.0 release hardening: one-click gating, price integrity, public route safety

Status: accepted 2026-10-22. Applies to Pro (`upsellbay`) 1.0.0. Covers Forhad's decision to ship
without the one-click charge and the fixes for security review findings SH-2 to SH-12.

## 1. One-click post-purchase charge is off in 1.0.0

**Decision (Forhad):** Pro 1.0.0 ships without the tokenized one-click charge, as PRD v4 and
AGENTS.md require ("no tokenized one-click post-purchase charge flow in v1"). Thank-you offers
always start a separate follow-on checkout. The finished, reviewed one-click code stays in the
repository for 1.1 (see [one-click-charge-idempotency.md](./one-click-charge-idempotency.md)).

**Mechanism:** one switch, `Constants::one_click_enabled()`, which returns
`true === apply_filters( 'upsellbay_pro_one_click_enabled', false )`. The filter is documented as
reserved and unsupported in `docs/developer/hooks.md`. While it is off:

| Surface | Behaviour |
| --- | --- |
| `Plugin` container | `PublicOfferRoutes` gets no `OneClickCharger`, so the adapter registry and `StripeChargeAdapter` are never built and `upsellbay_pro_register_one_click_adapters` never fires. |
| `PublicOfferRoutes::register_one_click_route()` | Registers nothing: `/one-click-accept` does not exist. |
| `PublicOfferRoutes::one_click_accept()` | Returns 404 before any guard or charge (defence in depth if it is ever wired by hand). |
| `StorefrontController` / `ThankYouOfferRenderer` | No `data-upsellbay-one-click` marker; the card shows "Add to a new checkout" and the separate-checkout explainer. |
| `src/storefront/index.js` | The one-click branch runs only for a card with the marker, so it is unreachable. |

The one-click unit tests switch it on explicitly (`upsellbay_with_one_click()` in
`tests/test-release-hardening.php`) so they keep guarding the 1.1 code. Turning it on for 1.1 needs
its own release decision, docs and E2E proof.

## 2. Price integrity for Pro offer lines

**Rule:** the price stored on a cart line at accept time is never charged. On every
`woocommerce_before_calculate_totals` run (priority 20, after the free host's handler at 10),
`OfferLineRepricer` re-decides the price of each Pro offer line (`product_upsell`,
`cart_crosssell`, `thankyou_offer`):

1. Reload the offer. It must be `publish`, `_ub_status` `active`, in schedule, still offering the
   line's product, on a placement the settings leave on, with its triggers and rules matching
   (`OfferPrioritizer::evaluate()`; the display-only reasons `dismissed_in_session`,
   `product_already_in_cart` and `viewing_offered_product` are ignored for an accepted line).
   Thank-you lines also need their source order to exist and not be failed, cancelled or refunded.
2. Recompute the offer price with `DiscountCalculator` from the product's current price, read
   from a fresh product object.
3. Discount exactly one unit: unit price = (offer + (qty - 1) x regular) / qty, computed in minor
   units (`DiscountApplier::unit_price()`), the same arithmetic as the host's applier.
4. Fail closed: any failed check means no discount. The line's `_ub_offer_price` is cleared so
   the host's handler (which applies the stored price) has nothing to apply on the next run. No
   current regular price means the line is removed from the cart (SQ-4), since it would otherwise
   keep whatever price its product object already carries.

**Why Pro keeps its own handler instead of relying on the host's:** the host's committed handler
(1.0.2) applies the stored price with no re-check, and its in-flight revision re-checks only some
standing reasons and the cart rules and still applies the stored price. The host exposes no
service Pro could call to get the re-checked price. Pro therefore owns its own lines and runs
last; checkout-bump lines stay the host's.

**Cart subtotal rules (SQ-1):** `WC_Cart::calculate_totals()` calls `reset_totals()` before
`woocommerce_before_calculate_totals`, so `$cart->get_subtotal()` is always 0 inside this hook.
`OfferLineRepricer::rule_subtotal()` builds the figure from the lines instead (unit price x
quantity of every line except Pro offer lines), and the `cart-offer-add` route uses the same
helper, so accept and reprice judge `cart_subtotal` rules on the same number. Pro offer lines are
left out so an accepted offer never counts toward its own (or another offer's) threshold. The
storefront display context still uses `get_subtotal()` (outside the totals run it holds the last
run's value, offer lines included), so an offer can rarely be shown that the accept route then
refuses when only other accepted offers lifted the cart over the threshold.

**Known limits:** `test_mode` is not part of the placement check (it gates display,
not pricing of an already accepted line); the viewed product of a product-page offer is still the
one the client sent at accept time.

## 3. Public route safety

- **Claims (SH-6):** `cart-offer-add` accepts only `product_upsell`, `cart_crosssell` and
  `thankyou_offer`, only a published offer, and only when `OfferPrioritizer::evaluate()` (with the
  session's dismissed offers) finds no reason to hide it. No evaluator wired means refuse.
- **Failure logging (SH-7):** the `upsellbay_pro_api_request_failed` action carries only
  `offer_id` and `placement`. `ApiFailureLog` writes at most one row per endpoint and reason (and
  offer, for offer-specific reasons) per hour, so a client cannot grow the log table. Log rows hold
  a truncated IP (IPv4 /24, IPv6 /48) and no user agent.
- **Client IP (SH-7, SH-11):** `ClientIp` reads `REMOTE_ADDR` only; forwarded headers are ignored
  unless a site returns a trusted value through `upsellbay_pro_rate_limit_client_ip` (invalid IPs
  are ignored). IPv6 clients are rate-limited per /64.
- **Licence key (SH-8):** REST licence responses return status, plan, expiry and the masked key.
  Licence failure hooks and log rows have the key masked in messages, request and response data.
  The key still travels in the licence server URLs (`Updater` update-check path, `/check` query
  string); moving it needs a licence server contract change and is out of scope for 1.0.0.
- **Import (SH-12):** uploads must be `UPLOAD_ERR_OK`, pass `is_uploaded_file()` and be at most
  2 MB (`ImportExporter::read_upload()`).

## 4. Session token limits (SH-2 / SH-10)

Pro's `CartSession::validate_token()` no longer trusts the first token a session sees: a session
without an issued token rejects every token, and a token older than two days is rejected (same
lifetime as the host). A cached page without a token gets one through the cart fragment and on
its next render.

**Not done, and why:** the raw token is still stored in the WooCommerce session
(`upsellbay_offer_state.token_raw`), and the token is random rather than an HMAC of the session ID.
Pro and the free host share that session key and the host issues and reads the same fields, so
changing the derivation or dropping `token_raw` in Pro alone would break the host's token flow.
It needs a coordinated host change (derive `hash_hmac( 'sha256', session_id, wp_salt() )` on both
sides and stop storing the raw value). Risk while it stands: anyone who can read the WooCommerce
session row can read the token, which only authorises offer actions on that same session.
