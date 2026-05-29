<!-- STATUS: PENDING -->

# Phase 4 - Core Business Logic

Goal: implement the offer engine that evaluates rules, renders eligible placements, mutates carts safely, applies discounts, writes attribution, records analytics, and keeps checkout stable.

## UB-P4-001 - Offer Service

- Objective: Centralize offer lifecycle business behavior.
- Scope definition: Create `app/Domain/Offers/OfferService.php`.
- Dependencies: UB-P2-003, UB-P2-002.
- Implementation notes: Service coordinates create, update, duplicate, pause, activate, delete, preview payload, and status transitions. It must delegate persistence to `OfferRepository` and validation to `OfferValidator`.
- Acceptance criteria: Admin controllers and REST preview routes use `OfferService` instead of directly mutating offers.
- Validation and testing requirements: Unit tests for create/update/duplicate/pause/delete and invalid transition handling.
- Estimated complexity: High.
- Suggested execution order: 43.

## UB-P4-002 - Offer Prioritizer

- Objective: Select the highest-priority eligible offer for each context.
- Scope definition: Create `app/Domain/Offers/OfferPrioritizer.php`.
- Dependencies: UB-P2-003, UB-P4-003.
- Implementation notes: Respect offer status, schedules, placement, priority/menu order, product availability, dismissed offers, cart state, and Core/Growth/Agency display limits.
- Acceptance criteria: Checkout Core tier shows one highest-priority bump; later tiers can allow up to three only when licensing policy permits.
- Validation and testing requirements: Unit tests for priority order, paused offers, expired offers, dismissed offers, display limits, and empty result.
- Estimated complexity: High.
- Suggested execution order: 49.

## UB-P4-003 - Rule Parser and Evaluator

- Objective: Evaluate P0 targeting rules server-side.
- Scope definition: Create `app/Domain/Rules/RuleParser.php` and `app/Domain/Rules/RuleEvaluator.php`.
- Dependencies: UB-P2-002.
- Implementation notes: Support AND/OR matching for cart product, cart category/tag, cart subtotal, viewed product, user role, customer order count, customer lifetime spend, stock status, and exclude-if-product-in-cart.
- Acceptance criteria: Empty rules mean eligible for the applicable placement; malformed rules fail closed and are logged for admins when debug logging is enabled.
- Validation and testing requirements: Unit tests for every rule type, all/any matching, malformed rules, guest customers, and HPOS-safe customer order queries.
- Estimated complexity: High.
- Suggested execution order: 44.

## UB-P4-004 - Discount Calculator

- Objective: Calculate offer prices without trusting clients.
- Scope definition: Create `app/Domain/Discounts/DiscountCalculator.php`.
- Dependencies: UB-P2-002.
- Implementation notes: Support `none`, `percent`, `fixed_amount`, and `fixed_price`. Use WooCommerce price/decimal helpers. Protect against negative prices and invalid decimals.
- Acceptance criteria: All discounts are calculated server-side from stored offer config and current product price.
- Validation and testing requirements: Unit tests for each discount type, rounding, zero/negative protection, variable product price input, and invalid config fail-closed behavior.
- Estimated complexity: Medium.
- Suggested execution order: 45.

## UB-P4-005 - Cart Validator

- Objective: Guard cart mutations before products or discounts are applied.
- Scope definition: Create `app/Domain/Cart/CartValidator.php`.
- Dependencies: UB-P4-003, UB-P4-004.
- Implementation notes: Validate product exists, purchasable, visible where required, in stock, allowed by offer rules, not already blocked by cart state, and not a subscription product receiving recurring discounts in v1.
- Acceptance criteria: Ineligible offers do not render or do not mutate cart; user receives a Woo notice when an accepted offer becomes invalid.
- Validation and testing requirements: Unit/integration tests for out-of-stock, non-purchasable, variable product unsupported state, duplicate cart items, subscription product safeguards, and rule failure.
- Estimated complexity: High.
- Suggested execution order: 46.

## UB-P4-006 - Cart Mutator

- Objective: Add/remove accepted offer products and preserve cart state.
- Scope definition: Create `app/Domain/Cart/CartMutator.php`.
- Dependencies: UB-P2-006, UB-P4-005, UB-P4-004.
- Implementation notes: Add cart item data for offer ID, placement, discount type, original price, offer price, and source context. Remove by tracked cart item key where possible.
- Acceptance criteria: Accept/unaccept updates Woo cart totals without duplicate offer items or stale discounts.
- Validation and testing requirements: Integration tests for add, remove, duplicate accept, cart totals refresh, and invalid item recovery.
- Estimated complexity: High.
- Suggested execution order: 47.

## UB-P4-007 - Discount Applier

- Objective: Apply session-scoped offer discounts safely.
- Scope definition: Create `app/Domain/Discounts/DiscountApplier.php`.
- Dependencies: UB-P4-004, UB-P4-006.
- Implementation notes: Prefer cart item price adjustment over persistent coupons. Store original price and restore behavior. Do not apply recurring discounts to subscriptions in v1.
- Acceptance criteria: Cart totals reflect accepted offer discounts and checkout order line items preserve attribution metadata.
- Validation and testing requirements: Integration tests for cart totals, removal restoring price, subscription guard, fixed price discount, and tax-inclusive store settings.
- Estimated complexity: High.
- Suggested execution order: 48.

## UB-P4-008 - Placement Renderer Coordinator

- Objective: Route eligible offers to placement-specific renderers.
- Scope definition: Create `app/Domain/Storefront/PlacementRenderer.php` or `app/Storefront/Renderers/PlacementRenderer.php` following the final file map.
- Dependencies: UB-P4-002, UB-P4-005.
- Implementation notes: Coordinator determines context and delegates to checkout, product, cart, or thank-you renderers. It must not calculate discounts or mutate cart directly.
- Acceptance criteria: Rendering code is placement-specific and eligibility stays in domain services.
- Validation and testing requirements: Unit tests confirm coordinator selects correct renderer and skips disabled placements.
- Estimated complexity: Medium.
- Suggested execution order: 50.

## UB-P4-009 - Classic Checkout Bump

- Objective: Implement order bumps for classic checkout.
- Scope definition: Create classic checkout renderer and hook integration using `woocommerce_review_order_before_submit`, `woocommerce_cart_calculate_fees` or price adjustment hooks, `woocommerce_checkout_create_order_line_item`, and `woocommerce_checkout_order_processed`.
- Dependencies: UB-P4-006, UB-P4-007, UB-P4-008, UB-P4-014.
- Implementation notes: Render a native, accessible checkbox/toggle. Accept/unaccept via REST/AJAX, refresh checkout totals, and avoid obstructing payment methods or Place order.
- Acceptance criteria: Works with `[woocommerce_checkout]`, updates totals, writes line-item attribution, and does not break payment submission.
- Validation and testing requirements: Playwright E2E for render, accept, unaccept, place order, and attribution meta with HPOS enabled.
- Estimated complexity: High.
- Suggested execution order: 54.

## UB-P4-010 - Block Checkout Bump

- Objective: Implement order bumps for Block Checkout only through supported APIs.
- Scope definition: Create `app/Integrations/WooCommerce/CheckoutFields.php`, `app/Integrations/WooCommerce/BlockCheckoutIntegration.php`, and `src/block-checkout/index.js`.
- Dependencies: UB-P0-003, UB-P4-006, UB-P4-007, UB-P4-014.
- Implementation notes: Use Additional Checkout Fields API where appropriate and Blocks extension points or Slot/Fill APIs for rich UI. Use Store API-compatible mutation paths. Do not use private Woo internals.
- Acceptance criteria: Block checkout card renders accessibly, toggles offer, updates totals, allows checkout completion, and writes attribution.
- Validation and testing requirements: E2E test on Block Checkout with WooPayments active; launch claim remains blocked until this passes.
- Estimated complexity: High.
- Suggested execution order: 55.

## UB-P4-011 - Product Page Offer

- Objective: Render product-page add-on or frequently-bought-together offers.
- Scope definition: Create product page renderer, template `templates/storefront/product-upsell.php`, and `src/storefront/product-upsell/index.js`.
- Dependencies: UB-P4-002, UB-P4-006, UB-P4-014.
- Implementation notes: Target by current product, category, tag, or manual selected product. Support simple products and variable products where the chosen implementation can validate variation selection.
- Acceptance criteria: Merchant-configured product offers render natively and add accepted products to cart with attribution state.
- Validation and testing requirements: E2E tests for simple product, variable product supported path, out-of-stock hidden path, mobile layout, and add-to-cart notice.
- Estimated complexity: High.
- Suggested execution order: 56.

## UB-P4-012 - Cart Cross-Sell Offer

- Objective: Render eligible add-on offers in the cart.
- Scope definition: Create cart renderer, template `templates/storefront/cart-crosssell.php`, and `src/storefront/cart-offer/index.js`.
- Dependencies: UB-P4-002, UB-P4-006, UB-P4-014.
- Implementation notes: Render up to three eligible offers, respect rules and display limits, avoid layout shift on mobile, and allow dismissal.
- Acceptance criteria: Cart offers can be accepted/dismissed, update cart totals, and record analytics.
- Validation and testing requirements: E2E tests for render, accept, dismiss, mobile layout, and no horizontal scroll.
- Estimated complexity: High.
- Suggested execution order: 57.

## UB-P4-013 - Thank-You Follow-On Offer

- Objective: Offer a safe post-purchase follow-on checkout without mutating the original order.
- Scope definition: Create thank-you renderer, template `templates/storefront/thankyou-offer.php`, and `src/storefront/thankyou/index.js`.
- Dependencies: UB-P4-002, UB-P4-006, UB-P4-015.
- Implementation notes: Clicking the offer starts a new cart/checkout flow linked to the source order through follow-on order meta after completion. Primary order totals and line items must never be changed.
- Acceptance criteria: Follow-on flow creates a separate order and records `_ub_source_order_id`, `_ub_source_offer_id`, and `_ub_follow_on_order`.
- Validation and testing requirements: E2E test confirms primary order unchanged and follow-on order linked correctly.
- Estimated complexity: High.
- Suggested execution order: 59.

## UB-P4-014 - Public REST Routes

- Objective: Add shopper-safe REST boundaries for offer interactions.
- Scope definition: Create route classes for `/bump-toggle`, `/cart-offer-add`, and `/dismiss`.
- Dependencies: UB-P1-014, UB-P4-006, UB-P4-007.
- Implementation notes: Validate offer ID, product ID, placement, nonce or session token, stock, purchase permissions, and server-side rules. Never accept client-sent price or discount.
- Acceptance criteria: REST responses include Woo notices and updated cart state/fragments where relevant.
- Validation and testing requirements: REST tests for nonce/session validation, rate limiting, invalid offer, invalid product, client-sent price ignored, and HTTP 429 behavior.
- Estimated complexity: High.
- Suggested execution order: 51.

## UB-P4-015 - Attribution Writer and Reader

- Objective: Write and read offer attribution through WooCommerce CRUD.
- Scope definition: Create `app/Domain/Attribution/AttributionWriter.php` and `app/Domain/Attribution/AttributionReader.php`.
- Dependencies: UB-P2-007, UB-P4-006.
- Implementation notes: Write order item meta during checkout and follow-on order meta after linked checkout completes. Reader supports analytics and developer use.
- Acceptance criteria: Accepted offers produce accurate attribution for offer ID, type, placement, discount, source context, and follow-on linkage.
- Validation and testing requirements: Integration tests with HPOS enabled and disabled; static scan confirms no direct order postmeta writes.
- Estimated complexity: High.
- Suggested execution order: 53.

## UB-P4-016 - Analytics Recorder and Reconciler

- Objective: Record offer views, accepts, dismissals, orders, revenue, and discounts.
- Scope definition: Create `app/Domain/Analytics/AnalyticsRecorder.php`, `AnalyticsService.php`, and `StatsReconciler.php`.
- Dependencies: UB-P2-005, UB-P2-011, UB-P4-015.
- Implementation notes: Increment on render/accept/dismiss/order lifecycle. Reconcile daily via Action Scheduler. Keep raw PII out of analytics.
- Acceptance criteria: Accepted test offers appear in aggregate stats and dashboard after runtime write or reconciliation.
- Validation and testing requirements: Unit/integration tests for each event type, idempotent reconciliation, and date range aggregation.
- Estimated complexity: High.
- Suggested execution order: 60.

## UB-P4-017 - Conflict Scanner

- Objective: Detect known checkout/funnel/recovery plugins and risky checkout replacements.
- Scope definition: Create `app/Domain/Compatibility/CompatibilityScanner.php` or equivalent admin service.
- Dependencies: UB-P0-008, UB-P3-008.
- Implementation notes: Return structured compatibility findings; do not hard-fail checkout. CartBay finding is informational only.
- Acceptance criteria: Scanner feeds admin notices, tools diagnostics, and compatibility docs.
- Validation and testing requirements: Unit tests for plugin detection inputs and severity classification.
- Estimated complexity: Medium.
- Suggested execution order: 61.

## UB-P4-018 - Core Business Logic Tests

- Objective: Provide focused test coverage for the offer engine.
- Scope definition: Add tests under `tests/TestCases/Offers/`, `Rules/`, `Cart/`, `Discounts/`, `Attribution/`, `Analytics/`, `Storefront/`, and `Api/`.
- Dependencies: UB-P4-001 through UB-P4-017.
- Implementation notes: Keep tests close to service behavior. Include failure paths and launch blockers.
- Acceptance criteria: Test suite fails on broken discount math, client price trust, HPOS-unsafe attribution, duplicate cart mutations, and CartBay coupling.
- Validation and testing requirements: Run `composer test` and targeted E2E suites for checkout flows.
- Estimated complexity: High.
- Suggested execution order: 62.

