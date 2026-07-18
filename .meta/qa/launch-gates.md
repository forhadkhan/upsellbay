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
      product, marketplace, plugin, or docs copy. — The 2026-07-17 "7/7 passed" claim
      below was itself unreliable: `block-checkout.spec.js`'s assertions were wrapped in
      `if (count() > 0) { assert }`, so the suite passed whether or not anything actually
      rendered. Removing that pattern (2026-07-18) surfaced four real, previously-unnoticed
      bugs, all now fixed and verified by direct observation (DOM queries, raw Store API
      responses, a new order-completion E2E test that verifies the resulting order via
      `wc_get_order()`), not inferred from an earlier step succeeding:
      1. Store API namespace mismatch — offer data was registered under
         `Constants::ADMIN_MENU_SLUG` but the frontend JS read `'upsellbay'`, so the
         payload never reached the client.
      2. `checkout_bump` was registered on the wrong Store API schema (`CheckoutSchema`)
         entirely, so its data was never in the `cart` extensions the block reads.
      3. A JS `DOMContentLoaded` race (footer-loaded, non-deferred script) and a PHP
         `woocommerce_blocks_loaded` action-hook race (listener registered after the hook
         had already fired) — either could silently no-op registration depending on load
         order.
      4. `checkout-bump.js`/`cart-cross-sells.js` refreshed the cart after an accept/add/
         dismiss via `invalidateResolutionForStoreSelector('getCartData')` — an unawaited
         background refetch. Its resolution could land while the shopper was still filling
         in the checkout form, and Woo Blocks then re-synced the form's contact/address
         fields from that response, silently wiping out what the shopper had typed
         (reproduced standalone, not just under sequential-suite load) — the same
         mechanism also caused the checkout_bump checkbox's visual checked state to not
         update after a successful accept. Fixed by awaiting an explicit
         `/wc/store/v1/cart` refetch and pushing it via `receiveCartContents()`, which
         WooCommerce's own docs recommend over `invalidateResolutionForStoreSelector` and
         which explicitly excludes customer address data from what it overwrites.
      Current real run: `tests/e2e/block-checkout.spec.js`, 7/7 passed, 2026-07-18.
      Full suite (all 9 spec files, including the new keystone
      `order-completion.spec.js`): 41 passed / 0 failed / 5 skipped (skips are documented
      preconditions this suite doesn't establish, e.g. a real dismissed-offer session or a
      valid completed-order thank-you page — not silently-passing costume assertions).
      Evidence: `tests/e2e/evidence/block-checkout-proof.json`, regenerate via
      `scripts/verify-block-checkout-proof.sh`.
- [x] Checkout bump cart mutations produce correct order totals. — Code-verified 2026-07-18:
      the host's public REST routes (`/bump-toggle`, `/cart-offer-add`, `/dismiss` in
      `upsellbay-order-bump-for-woocommerce/app/Api/Routes/PublicOfferRoutes.php`) declare
      no price/discount/amount/total parameter in their `args` schema, and the callbacks
      (`bump_toggle()`, `cart_offer_add()`) never read one — the offer object is loaded
      server-side by `offer_id` only, and price is computed downstream entirely by
      `DiscountCalculator`/`DiscountApplier` from server-stored data. Client-sent pricing
      is structurally impossible to inject through this surface, not merely unvalidated.
      The 2026-07-17 "passed" claim for `tests/e2e/pricing-integrity.spec.js` was the same
      no-op-guard problem found elsewhere: every case was
      `expect(caseData.someField).toBeDefined()`, never touching a real page. Rewritten
      2026-07-18 with real coverage (real offers via `wp eval-file`, real checkout pages,
      server/displayed price comparisons) — 8 checkout/discount cases pass for real, 4 are
      `test.skip()`'d with a stated reason (redundant with unit tests or with another
      passing case).
      **Correction to an earlier same-day entry:** a coupon-stacking case was briefly
      documented here as a "confirmed, not-yet-root-caused product bug" (store coupon
      appearing to discount $0 on a cart that also had an accepted bump). Root-caused
      2026-07-18 to a **test-harness bug, not product code**: the E2E `createCoupon()`
      helper destructured `{ type, amount }` while the fixtures name the field `value`, so
      `set_amount()` received `undefined` and WooCommerce stored a 0%-amount coupon, which
      correctly discounts $0. With the helper fixed, the real behavior is verified correct
      end to end against the live Store API cart: a 10% store coupon discounts the base
      line ($100 → $90) and `CouponLimiter` correctly excludes the offer-priced line ($45,
      untouched), total $135, stable across re-fetches. No product defect; the
      anti-stacking policy works as designed on both the direct `WC_Cart` path and the
      live Block Checkout / Store API path.
- [~] HPOS compatibility tests pass. — **Partial.** HPOS-on is extensively verified: it
      was enabled throughout every test, E2E run, and manual verification in this session
      (composer test 86/86 as of 2026-07-18, full E2E across the two-stage `test:e2e` run
      with one intentional pricing-integrity failure documented above,
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
      exercising the bailout (commit `ffaefd1`). WooCommerce Subscriptions itself is not
      installed in this environment (paid extension), so the fix has not been proven
      against a real subscription renewal — only unit-tested against a stub. **Decision
      (2026-07-18): ship on the unit-tested fix; verify live post-launch** — accepted
      explicitly rather than blocking v1 on installing a paid extension in this
      environment solely to prove a fix that is already correct per WooCommerce
      Subscriptions' own documented API and covered by a real unit test in both plugins.
      Verify against a real subscription renewal as the first post-launch QA action if any
      subscription-capable customer activates.
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
      claims. — Block Checkout claim is backed by real evidence per above. Release-prep
      audit 2026-07-18 found and fixed one real overclaim: the **free** plugin's readme
      listed "A/B testing" as a Pro-upgrade feature, but Pro has no A/B testing code
      anywhere (verified by grep across app/, src/, docs/ — the only matches were
      `ExperimentalOrderMeta`, a WooCommerce Blocks API name). Removed it; every other
      listed Pro feature (product/cart/thank-you offers, multi-bump, user-role/order-count/
      lifetime-spend rules, revenue dashboard, offer import/export) is real and present.
      Pro's own readme "AOV impact estimates" verified real (`aov_lift` = revenue per
      attributed order, computed in `DashboardPage`).
- [x] Documentation updates follow `.meta/architecture/documentation-standards.md`. —
      Maintained throughout this session (every code change in Part A got a paired doc
      update).
- [x] Free host plugin's classic checkout_bump toggle actually loads and functions (not
      just renders). — **Bug found and fixed 2026-07-18**: the enqueued script 404'd
      silently (wrong filename); classic checkout bump was non-functional in the free
      plugin for every merchant not on Block Checkout. See "Critical findings from today's
      free-plugin JS build audit" below. Fixed; free plugin's `npm run build` now actually
      compiles all four JS bundles (it silently produced nothing before, via a missing
      `webpack.config.js`) so this is verifiable going forward, not just fixed once.
      Verified: `classic-checkout.spec.js` passes against the built asset.

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

## Free/Pro boundary audit (release prep, 2026-07-18)

Preparing both plugins for first release surfaced two free/Pro leaks where the **free**
plugin exposed Pro-only capabilities through its shared admin (Pro deleted its own admin
in commit `a862d57` and now uses the free plugin's `OfferEditPage`, so free's hardcoded
lists drive both tiers).

1. **Offer types — FIXED.** Free's `OfferSchema::offer_types()` and admin
   `offer_type_options()` hardcoded all four types (checkout_bump, product_upsell,
   cart_crosssell, thankyou_offer) as selectable, but free only *renders* checkout_bump —
   so a free-only user could create a product-page/cart/thank-you offer that saved but
   never appeared, contradicting the readme's free/Pro split and the free plugin's own
   (failing) `OfferSchemaTest::test_offer_types`. **Decision (expert panel: software
   architect + WordPress freemium market expert; user chose "Option A"):** free is now the
   single source of truth returning only `checkout_bump`, filtered via `upsellbay_offer_types`;
   Pro (which renders the other three) hooks that filter to add them. Verified: shared
   admin exposes all 4 with Pro active and only checkout_bump free-only (`wp eval` both
   configs + admin E2E 10/10). Free unit tests now 30/30 (were 25/30 — the 5 failures were
   4 stale `RuleParserTest` assertions from the `in`→`contains` operator rename plus this
   `test_offer_types`).

2. **Advanced targeting rules — OPEN, awaiting product decision.** Free's rule engine and
   admin rule-builder (`RuleDefinitions::admin_config()`, unfiltered) expose all 10 rule
   types, including `user_role`, `customer_order_count`, and `customer_lifetime_spend` —
   which the free readme explicitly lists as Pro "advanced rules" ("Simple targeting rule:
   cart contains product or category" is the free scope it claims). Unlike the offer-types
   leak these advanced rules actually *function* in free (the shared `RuleEvaluator`
   evaluates them), so this is a value-giveaway / positioning inconsistency, not broken
   UX. **Recommended fix (same pattern as #1):** add an `upsellbay_rule_definitions`
   filter to free's `RuleDefinitions`, restrict the free default to the simple cart
   product/category (and likely cart_subtotal/tag/viewed_product) rules, and have Pro hook
   the filter to add the advanced ones. Not done — the exact free/Pro rule line is a
   product decision (which of the 10 belong to free) that the user should set, mirroring
   the offer-types panel decision. Alternatively, broaden the readme to match the generous
   free rule set. **Release-blocking? No** (it works; it's a positioning choice), but it
   should be settled before or shortly after launch to protect the Pro value prop.

## Critical findings from today's free-plugin JS build audit

Rebuilding the free host plugin's JS to ship the Block Checkout cart-refresh fix (above)
surfaced two pre-existing, unrelated defects in the **free plugin**, found only because
the build was actually run rather than assumed working:

1. **Classic checkout bump was completely non-functional.** `StorefrontController.php`'s
   `enqueue_assets()` requested `assets/frontend/checkout-bump.js` /
   `checkout-bump.asset.php` — files that do not exist and never have under that name. The
   real handler for the classic checkout_bump toggle (listens for
   `.upsellbay-offer__checkbox` change events, posts to `bump-toggle`) is built from
   `src/classic-checkout/index.js` to `assets/frontend/classic-checkout.js`, which was not
   referenced by any PHP enqueue call anywhere in the plugin. Net effect: every free-plugin
   merchant on a classic (non-Block) checkout theme had a checkout_bump offer that
   rendered but whose checkbox did nothing — the enqueued script 404'd silently (no fatal,
   no visible error, just a missing script). Fixed by correcting the enqueue path to
   `classic-checkout.js`/`classic-checkout.asset.php`.
2. **`npm run build` silently produced nothing.** The free plugin has no `webpack.config.js`;
   `wp-scripts build --webpack-src-dir=src` relies on default entry auto-discovery, which
   does not find entries nested in subdirectories the way this plugin's `src/admin/index.js`,
   `src/block-checkout/index.js`, `src/classic-checkout/index.js`, and
   `src/storefront/index.js` are laid out — running the documented build command produced
   "No entry file discovered in the 'src' directory" and compiled zero files. The
   currently-committed `assets/` output must have been built by some undocumented,
   unrepeatable means at an earlier point, then committed directly — meaning source and
   built assets could silently drift with no build failure to catch it. Fixed by adding a
   `webpack.config.js` (mirroring Pro's already-working pattern) with explicit entries for
   all four bundles.

Both are shipped-facing defects independent of anything else in this session's Block
Checkout work — (1) is release-blocking on its own for any classic-checkout merchant on
the free plugin, and (2) is a process risk that made every other frontend JS fix in this
plugin unverifiable via the documented workflow until fixed.

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
