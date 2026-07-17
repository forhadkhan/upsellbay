# Launch Gates

Status: accepted for Phase 0. Triaged 2026-07-18 (see
`.meta/reviews/bundle-feature-and-release-readiness-2026-07-17.md` and the Opus
consultation referenced there for how this triage was reached).

Task: `UB-P0-007`.

Launch is blocked until every applicable item is checked with current evidence, **or**
explicitly re-scoped below with a stated reason. This 15-item list is the actual v1
definition of done — the 35-item Phase 7/8/9 backlog in `.meta/tasks/` is a separate,
aspirational tracking document and is not itself a ship gate; see the reconciliation note
at the bottom of this file.

## Release-Blocking Checklist

- [x] Block Checkout support is either fully proven by E2E tests or not claimed in
      product, marketplace, plugin, or docs copy. — Real run of
      `tests/e2e/block-checkout.spec.js` against a live WooCommerce Block Checkout page,
      7/7 passed, 2026-07-17 (commit `99698bd`). Evidence:
      `tests/e2e/evidence/block-checkout-proof.json`, regenerate via
      `scripts/verify-block-checkout-proof.sh`.
- [x] Checkout bump cart mutations produce correct order totals. — Code-verified 2026-07-18:
      the host's public REST routes (`/bump-toggle`, `/cart-offer-add`, `/dismiss` in
      `upsellbay-order-bump-for-woocommerce/app/Api/Routes/PublicOfferRoutes.php`) declare
      no price/discount/amount/total parameter in their `args` schema, and the callbacks
      (`bump_toggle()`, `cart_offer_add()`) never read one — the offer object is loaded
      server-side by `offer_id` only, and price is computed downstream entirely by
      `DiscountCalculator`/`DiscountApplier` from server-stored data. Client-sent pricing
      is structurally impossible to inject through this surface, not merely unvalidated.
      Also covered functionally by `tests/e2e/pricing-integrity.spec.js` (passed in the
      2026-07-17 full-suite run).
- [~] HPOS compatibility tests pass. — **Partial.** HPOS-on is extensively verified: it
      was enabled throughout every test, E2E run, and manual verification in this session
      (composer test 83/83, full E2E 41/45 with only pre-existing unrelated failures,
      live activation/CartBay-independence checks). HPOS-off could not be tested in this
      environment: WooCommerce itself refuses `woocommerce_custom_orders_table_enabled`
      toggling here ("the authoritative table for orders storage can't be changed while
      there are orders out of sync") — this environment has accumulated real order data
      out of sync between stores. Forcing a resync or toggle to test HPOS-off was judged
      too risky to real order data to do casually. **Remaining gap**: verify HPOS-off on a
      clean environment (fresh WooCommerce install with no orders) before claiming this
      gate closed.
- [x] Attribution uses WooCommerce order and order-item CRUD APIs only. — Code-verified
      2026-07-18: `AttributionWriter::write_follow_on_order()` and related methods use
      `$order->update_meta_data()` + `$order->save()` exclusively; no direct
      `$wpdb`/postmeta function calls found in `app/Domain/Attribution/`.
- [x] Thank-you follow-on offers create a separate checkout flow and never mutate the
      primary order. — Code-verified 2026-07-18: `write_follow_on_order()` writes meta
      only onto the new follow-on `$order` object; the source order is referenced only by
      its integer ID (`ATTRIBUTION_SOURCE_ORDER_ID`) and is never loaded or mutated.
- [~] Subscription product discounts cannot leak into recurring renewals. — **Bug found
      and fixed 2026-07-18, live verification still open.** `DiscountApplier`'s bailout
      guard checked `wcs_is_subscription( $product )`, which per WooCommerce Subscriptions'
      own docs only returns true for a `WC_Subscription` object (the recurring-billing
      entity that exists only after checkout) or a subscription order ID — never for a
      cart item's product, which is always a `WC_Product`. The bailout could not have
      actually triggered from this call site since it shipped; it was also completely
      untested, which is how this went unnoticed. Fixed to use
      `WC_Subscriptions_Product::is_subscription( $product )`, the extension's documented
      API for checking whether a *product* is a subscription type, and added a unit test
      exercising the bailout (commit `ffaefd1`). **Still open**: WooCommerce Subscriptions
      itself is not installed in this environment (paid extension), so the fix has not
      been proven against a real subscription renewal — only unit-tested against a stub.
      Needs either a WC Subscriptions license/install here, or explicit sign-off to ship
      on the unit-tested fix alone and verify live post-launch.
- [x] License server outage does not disable live offers. — **Checked, with the accurate
      reason recorded** (see finding below): license state does not gate offer rendering
      anywhere in the runtime path — `LicenseClient::can_show_live_offers()` has zero
      callers in `app/`. This gate therefore passes structurally, not via a working
      fail-open/grace mechanism — do not describe it as the latter in any release copy.
      This is an intentional, confirmed design choice (see below), not an accident to fix.
      Separately, real license activation was tested end-to-end against the production
      `wpanchorbay.com` license server on 2026-07-18 with a live key: activate, is_valid,
      and background_check all succeeded. This also caught and fixed a release-blocking
      bug — see "Critical finding" below.
- [x] Public REST endpoints never accept client-sent pricing, discount, totals, or
      product validity. — Same code-level verification as the cart-mutation gate above;
      also true for `dismiss` and `/cart-offer-add`.
- [~] QIT has no unresolved high-severity security, compatibility, static analysis, or
      activation failures. — **Re-scoped.** QIT (WooCommerce's paid managed test service)
      is not available in v1's distribution channel — this product ships as a direct
      commercial sale via wpanchorbay.com with its own license server, not through the Woo
      Marketplace, so QIT itself does not apply. The gate's *intent* (no unresolved
      high-severity issues) is satisfied instead by: PHPCS clean, PHPStan clean, the
      product-isolation scan passing, and today's live activation/CartBay-independence
      testing. WordPress.org's Plugin Check (not marketplace-specific, cheap, catches real
      issues) was attempted today and is genuinely blocked in this sandbox — `wp-content/`
      is `www-data`-owned at `755` and Plugin Check's CLI needs write access there for a
      temporary file; no passwordless sudo available to work around it. Running Plugin
      Check once on infrastructure with proper permissions remains a real "should," not a
      hard blocker.
- [x] Admin UI is WooCommerce-native and does not introduce a top-level WordPress menu or
      custom app shell. — Code-verified 2026-07-18: exactly one `add_submenu_page()` call
      exists across both plugins (`upsellbay-order-bump-for-woocommerce/app/Admin/AdminPageRegistrar.php`,
      parent slug `'woocommerce'`); zero `add_menu_page()` calls anywhere.
- [x] UpsellBay activates and operates without CartBay. — Live-tested 2026-07-18:
      deactivated `cartbay-abandoned-cart-recovery-for-woocommerce`, confirmed the site
      loads (HTTP 200), UpsellBay Pro loads and functions (`class_exists` check), and the
      full test suite still passes (83/83). Reactivated CartBay afterward to restore the
      environment.
- [x] UpsellBay does not read from or write to CartBay options, sessions, metadata, REST
      routes, scheduled jobs, recovery email settings, or private runtime state. — Covered
      by the existing automated product-isolation scan (`composer qa:isolation`, passing).
- [~] Static scan confirms identifiers match the contract in
      `.meta/architecture/identifier-contract.md`. — **Re-scoped.** The contract itself
      was found to be stale during this session (it claimed identical hook/nonce/asset
      identifiers between free and Pro; the implementation has since intentionally
      diverged to avoid runtime collisions) and was corrected with a documented divergence
      note rather than reverted. The automated isolation scan still passes; re-verify it
      doesn't assert the now-corrected stale claims.
- [x] Marketplace and docs copy avoid unsupported AOV lift, Block Checkout, or recovery
      claims. — Maintained throughout this session's doc work (Block Checkout claim is now
      backed by real evidence per above; no A/B-testing or other unbacked claims found in
      an earlier pass). Not exhaustively re-audited today beyond what this session touched.
- [x] Documentation updates follow `.meta/architecture/documentation-standards.md`. —
      Maintained throughout this session (every code change in Part A got a paired doc
      update).

## Critical finding from today's license testing

`Constants::LICENSE_PRODUCT_SLUG` was changed from `'upsellbay'` to `'upsellbay-pro'` as
part of the A1 namespace-collision rename, along with every other Pro-scoped identifier.
This constant is sent as the `slug` field to the external `wpanchorbay.com` license
server's activate/check/update-check endpoints and is never used as a local storage key —
there was no collision to avoid renaming it fixed, only a real external API contract to
break. Verified directly against the production server with a live key: `slug=upsellbay-pro`
is rejected ("License key does not match the provided slug"), `slug=upsellbay` activates
successfully. Every legitimately issued customer license key would have failed to
activate on the built release. Fixed and verified end-to-end (activate/is_valid/background_check
all succeed) in commit `62f5530`.

## Honor-system licensing — confirmed intentional, not a defect

`LicenseClient::can_show_live_offers()` is unused code (zero callers in `app/`) — license
validity never gates whether offers render. Per direction: this is intentional and should
stay as-is. Confirmed against general WordPress commercial-plugin practice: a GPL-licensed
plugin's license key conventionally gates automatic updates and support access, not the
code's actual functionality — disabling functionality via a license check sits in a
legally murky area under GPL (the license grants unrestricted rights to run/modify/redistribute
code you already have), which is why EDD Software Licensing, Freemius, and similar
WordPress licensing systems are typically described as gating updates/support, not
runtime behavior. Sources:
[Software Licensing for WordPress Plugins and Themes – Freemius](https://freemius.com/wordpress/software-licensing/),
[Software Licensing for WordPress Plugins – Chris Lema](https://chrislema.com/software-licensing-for-wordpress-plugins/),
[License – WordPress.org](https://wordpress.org/about/license/).

## Reconciling against the Phase 7/8/9 backlog

The 35-item backlog in `.meta/tasks/07-quality-assurance.md`, `08-documentation.md`, and
`09-release-preparation.md` should not be worked item-by-item as if each is a ship
blocker. Treat it as three buckets:

1. **Already done, checklist hasn't caught up**: product isolation scan, classic/storefront/Block
   Checkout E2E, PHPCS/PHPStan/build/POT, a deterministic test harness (a hand-rolled
   runner, not literally PHPUnit — an intentional, working architecture choice, not a
   gap), and meaningful documentation content that already exists under different file
   names than the plan anticipated (`docs/compatibility-matrix.md`, `docs/developer/*`,
   `docs/upsellbay-product-and-testing-guide.md`, the architecture docs from this session).
2. **Real v1-relevant gaps**, scoped to finite, must-pass outcomes rather than open-ended
   backlog items: HPOS-off verification (see above), the subscription-leak test (blocked,
   open question), one representative payment gateway proven through both Classic and
   Block checkout (not the full WooPayments/Stripe/PayPal matrix — no gateway plugin is
   installed in this environment yet), and a real Plugin Check run once sandbox
   permissions allow it.
3. **Explicitly deferred to v1.1**: the full gateway compatibility matrix, a
   performance harness at full PRD scale (100k orders/500 offers — a reduced-scale run is
   the v1 target instead), a full WCAG 2.1 AA sweep across every admin surface (storefront
   placements take priority for v1 since that's the customer-facing ADA exposure), QIT and
   all Woo Marketplace-specific artifacts (listing draft, reviewer guide, compliance
   review — this product isn't shipping through that channel), the general real-WP
   integration-test harness (the specific outcomes that matter are covered individually
   above), beta plan, post-launch monitoring, and demo/reviewer seed data.

## Evidence Expectations

Each release candidate must attach or link:

- PHPCS result.
- PHPStan result.
- PHPUnit or project test result.
- Build result.
- POT generation result when strings changed.
- Plugin Check result (blocked in this sandbox as of 2026-07-18; needs infrastructure with
  write access to `wp-content/`).
- QIT result — not applicable to v1's distribution channel; see above.
- Classic checkout E2E result.
- Block Checkout E2E result before any Block compatibility claim.
- HPOS enabled and disabled attribution result — enabled done, disabled still open.
- Static scans for CartBay coupling and direct order storage access.
