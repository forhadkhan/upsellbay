# Core Business Logic

Status: implemented foundation in Phase 4.

Tasks: `UB-P4-001` through `UB-P4-018`.

## Offer Engine

`Domain\Offers\OfferService` is the lifecycle boundary for create, update, duplicate, pause, activate, delete, and preview payloads. Admin save and list-table mutations now go through this service rather than directly mutating repository state.

`Domain\Offers\OfferPrioritizer` selects eligible offers by placement, active status, schedule, dismissal state, product availability, rules, and priority. Core checkout rendering remains limited to one checkout bump; cart rendering can request up to three offers.

## Rules, Cart, and Discounts

`Domain\Rules\RuleParser` and `RuleEvaluator` support the P0 rule families from PRD v4: cart product, category, tag, subtotal, viewed product, user role, customer order count, lifetime spend, stock status, and exclude-if-product-in-cart. Empty rules are eligible; malformed rules fail closed.

`Domain\Discounts\DiscountCalculator` calculates offer prices server-side for no discount, percent, fixed amount, and fixed price. Public APIs do not accept client-sent prices. `Domain\Cart\CartValidator`, `CartMutator`, and `DiscountApplier` validate product state, prevent unsupported subscription discount leakage, store `_ub_` cart item data, prevent duplicate accepted items, and apply session-scoped cart item prices rather than public coupons.

## Storefront and REST

`Domain\Storefront\PlacementRenderer` delegates escaped offer-card markup to placement renderers for classic checkout, product page, cart, and thank-you offers. `StorefrontController` connects those renderers to WooCommerce hooks and scopes frontend assets to rendered placements.

`Api\Routes\PublicOfferRoutes` provides shopper-safe `/bump-toggle`, `/cart-offer-add`, and `/dismiss` handlers with session token validation, rate limiting, server-side offer loading, and analytics recording. `Api\Routes\OfferPreviewRoute` provides the admin preview payload through `OfferService`.

Block Checkout support is wired through `Integrations\WooCommerce\CheckoutFields`, `BlockCheckoutIntegration`, and the compiled `src/block-checkout` entry. The runtime does not declare Cart/Checkout Blocks compatibility here; the launch claim remains gated by Phase 7 E2E proof.

## Attribution, Analytics, and Compatibility

`Domain\Attribution\AttributionWriter` and `AttributionReader` use WooCommerce CRUD object methods for order item attribution and follow-on order linkage. There are no direct order postmeta writes.

`Domain\Analytics\AnalyticsService` routes views, accepts, dismissals, and orders to the aggregate non-PII stats repository through `AnalyticsRecorder`.

`Domain\Compatibility\CompatibilityScanner` detects known checkout/funnel plugins as warnings and CartBay as informational coexistence guidance. It never hard-fails checkout and does not read CartBay data.

## Validation

Phase 4 behavior is covered by `tests/test-core-business-logic.php` and the existing test runner. Static validation passed with `composer phpcs` and `composer phpstan`. Asset build and POT generation passed. `composer plugin-check` could not run locally because the WP-CLI Plugin Check command is not installed in this WordPress environment.
