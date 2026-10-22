# One-click charge idempotency model

Status: accepted, as built after the UB-6 fix (2026-10-22) and the PRO-5 review fixes
(SQ-1..SQ-10, 2026-10-22). Backlog: UB-9. Switched off in Pro 1.0.0 behind
`upsellbay_pro_one_click_enabled`; see [pro-1.0-release-hardening.md](./pro-1.0-release-hardening.md).

## Decision

One source order + one offer gets at most one follow-on order, and so at most one charge.
`OneClickCharger` writes the linkage meta (`_ub_source_order_id` / `_ub_source_offer_id`) on the
new order *before* calling the gateway adapter, so every later attempt finds that order and
never reaches the gateway again. Success is never inferred from the linkage alone: a retry
reports what the first attempt actually did, read from the order's payment state.

| Linked order state | Retry result | Route response |
| --- | --- | --- |
| `is_paid()` (processing / completed) | `duplicate` | success, no charge |
| cancelled / failed / refunded | `declined` | `should_fall_back`: separate checkout |
| pending / on-hold / anything else / missing | `in_progress` | no success, no fallback, "check your emails" |

## First attempt: definite versus unknown outcomes

Only an adapter `charged` result is success. The rest splits by whether the gateway proved that
nothing was charged:

| Adapter outcome | Follow-on order | Result | Route |
| --- | --- | --- | --- |
| `charged` | paid by the adapter | `charged` | success |
| `declined`, `requires_action`, `unsupported` (order not paid) | cancelled | same | `should_fall_back` |
| `in_progress`, an exception, any other status, or a "not charged" claim on a paid order | on-hold (a paid order is never downgraded; it only gets a note) | `in_progress` | no success, no fallback |

An unknown outcome is never cancelled and never falls back: the gateway may have taken the
money (timeout, transport error, a fault after the charge), and a fallback would charge again
through checkout. The order note names only the exception class or a masked reason, never the
exception message (Stripe's carries the raw API response). A retry finds the on-hold order and
`resolve_existing()` keeps answering `in_progress` until the merchant or a gateway webhook settles it.

## Stripe adapter mapping

- `succeeded`: the intent id is saved on the order (`_stripe_intent_id`, transaction id) and saved
  *before* `payment_complete()`. If `payment_complete()` returns false, throws, or the order is
  still unpaid afterwards, the result is `in_progress` (money taken, order not marked paid), never
  `declined`.
- `processing`: `in_progress`; intent id recorded so the Stripe gateway's webhook can find the order.
- `requires_action` / `requires_confirmation`: intent cancelled (best effort), `requires_action`.
- `requires_payment_method` / `canceled`: `declined`.
- Error object: `card_error` is `declined` (`authentication_required` is `requires_action`); an
  error whose `payment_intent.status` is `requires_payment_method` / `canceled` is `declined`;
  every other type (`api_error`, `idempotency_error`, rate limit, unknown) is `in_progress`.
- `WC_Stripe_API::request()` throwing (outage or empty body) or a non-object response: `in_progress`.

**Idempotency key.** `WC_Stripe_API::request()` sends a random UUID as `Idempotency-Key` for
`payment_intents` (Stripe gateway 10.8.4, `get_idempotency_key()`), and exposes the
`wc_stripe_idempotency_key` filter. The adapter adds a filter for the duration of its one request
(matched on its own metadata, removed in `finally`) that returns
`ub-{site hash}-{source_order_id}-{offer_id}`. The request metadata also carries the follow-on
order id, so a second follow-on order for the same source + offer (the lookup race, or a trashed
first order) sends the same key with different parameters: Stripe answers `idempotency_error`
instead of charging, which maps to `in_progress`.

## Eligibility and authorisation before any charge

`PublicOfferRoutes::one_click_accept` refuses (400 `ineligible`, no fallback, adapter never
called) unless the offer post is `publish`, `OfferPrioritizer::evaluate()` (the storefront
evaluator: placement, active status, schedule, product availability, triggers, rules) passes
against `OrderContext::build( $order )` plus the thank-you context, and
`CartMutator::validate()` (the cart claim validator) passes. No evaluator wired = refused.
Price and discount come from the stored offer and product via `DiscountCalculator`, never from
the client.

The order key never expires, so the off-session charge it authorises does: the source order must
be at most 24 hours old by creation date (filter `upsellbay_pro_one_click_max_order_age`,
seconds; 0 or less turns one-click off; a missing date fails closed). Older orders get
`status: expired` with `should_fall_back`, so the shopper pays through the normal checkout.

**The source order must be paid** (SQ-7, 2026-10-22). Not being failed / cancelled / refunded
is not enough: a pending or on-hold source order (an authorise-only or not-yet-settled payment)
can carry a saved Stripe method, and charging the upsell off-session against it would bill the
shopper before the first order is settled. So `one_click_accept` requires
`WC_Order::is_paid()` (processing / completed by default, filterable by WooCommerce through
`woocommerce_order_is_paid_statuses`) right after the age check and before the offer load and
eligibility; otherwise it answers `status: unsupported` with `should_fall_back`, and the charger
is never entered. `OneClickCharger::charge()` repeats the check as defence in depth, failing
closed (`unsupported`) when the source order is not paid or has no `is_paid()`. It runs *after*
the idempotency lookup, so a retry still reports what an earlier attempt did, and before any
order is created or any gateway call. Edge: a source order moved from paid back to on-hold after
a one-click attempt now gets the route's fallback, not the `in_progress` answer.

**No charge without the linkage** (SQ-8). The charger takes a typed `FollowOnLinkageWriter`
(`AttributionWriter` implements it); `write_follow_on_order()` returns whether the linkage was
written. On `false` the unlinked follow-on order is cancelled with a note and the result is
`unsupported`; the adapter is never called, since an unlinked order would let a retry charge again.

Rate limiting keys on `REMOTE_ADDR` (filter `upsellbay_pro_rate_limit_client_ip`), never on the
client-sent token, plus a per-source-order bucket once the order is verified.

## Tax

The line total is stored excluding tax (`wc_get_price_excluding_tax()` strips tax first on
tax-inclusive stores) and `calculate_totals( true )` computes tax from the order's billing /
shipping address, which the order factory copies from the source order. The adapter charges
`get_total()`, so tax is included. No shipping line is added; when the product needs shipping
the order gets a note saying none was charged.

## 3DS (`requires_action`)

No client-side challenge UI ships. The Stripe adapter cancels the PaymentIntent
(`payment_intents/{id}/cancel`, best effort), the order is cancelled, the client secret never
leaves the server, and the shopper is sent to the normal checkout, where the gateway runs its
own authentication. An in-page challenge would need a pending order, the secret returned, a
client confirm step and a webhook-driven `payment_complete`.

## Known limits

Closed by PRO-5: exceptions and unknown statuses no longer cancel or fall back; `processing` is no
longer treated as paid; a deterministic Stripe idempotency key is sent; eligibility is checked
before charging; tax is charged; the authorising order has an age limit; the rate limiter no
longer keys on the client token.

Still open:

- Nothing here has run against real Stripe. The error-object shapes, the `idempotency_error`
  answer for a reused key with different metadata, and the Stripe webhook settling a `processing`
  or on-hold follow-on order by `_stripe_intent_id` are read from the gateway source and Stripe's
  docs, not observed.
- The lookup is still a non-atomic `wc_get_orders()` meta query: two concurrent requests can both
  create a follow-on order. The idempotency key stops the second charge (Stripe returns
  `idempotency_error`, so the second order sits on hold), but a second on-hold order is left for
  the merchant to cancel.
- Stripe keeps idempotency keys for at least 24 hours. Raising the order-age filter above that
  re-opens the trashed-order double charge after the key expires.
- A follow-on order moved to trash is invisible to the lookup; within the key window the charge is
  refused by Stripe, after it the offer can be charged again.
- Tax on tax-inclusive stores and the shipping note depend on WooCommerce functions the unit
  harness does not load (`wc_get_price_excluding_tax`, real tax tables); the harness covers only
  that `calculate_totals( true )` runs and that the adapter charges the tax-inclusive total.
- Per-IP rate limiting on a site behind a proxy that does not restore the visitor IP puts every
  shopper in one bucket until `upsellbay_pro_rate_limit_client_ip` is used.
