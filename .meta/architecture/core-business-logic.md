# Core Business Logic

Status: implemented foundation in Phase 4.

Tasks: `UB-P4-001` through `UB-P4-018`.

## Offer Engine

`Domain\Offers\OfferService` is the lifecycle boundary for create, update, duplicate, pause, activate, delete, and preview payloads. Admin save and list-table mutations now go through this service rather than directly mutating repository state. Uses `ValidationResult` to safely pass back input validation errors.

`Domain\Offers\OfferPrioritizer` selects eligible offers by placement, active status, schedule, dismissal state, product availability, rules, and priority. Core checkout rendering remains limited to one checkout bump; cart rendering can request up to three offers.

`Domain\Offers\OfferConflictDetector` evaluates new or existing offers against active offers to identify warnings, such as placement crowding, duplicate triggers, and self-offers. Merchants can choose to override these warnings manually.

## Rules, Cart, and Discounts

`Domain\Rules\RuleParser` and `RuleEvaluator` support the P0 rule families from PRD v4: cart product, category, tag, subtotal, viewed product, user role, customer order count, lifetime spend, stock status, and exclude-if-product-in-cart. Empty rules are eligible; malformed rules fail closed.

`Domain\Discounts\DiscountCalculator` calculates offer prices server-side for no discount, percent, fixed amount, and fixed price. Public APIs do not accept client-sent prices. `Domain\Cart\CartValidator`, `CartMutator`, and `DiscountApplier` validate product state, prevent unsupported subscription discount leakage, store `_ub_` cart item data, prevent duplicate accepted items, and apply session-scoped cart item prices rather than public coupons. `Integrations\WooCommerce\CouponLimiter` prevents unintended usage of coupons with UpsellBay offers.

## Storefront and REST

`Domain\Storefront\PlacementRenderer` delegates escaped offer-card markup to placement renderers for classic checkout, product page, cart, and thank-you offers. `StorefrontController` connects those renderers to WooCommerce hooks and scopes frontend assets to rendered placements.

`Api\Routes\PublicOfferRoutes` provides shopper-safe `/bump-toggle`, `/cart-offer-add`, and `/dismiss` handlers with session token validation, schema validation, rate limiting, server-side offer loading, and analytics recording. `Api\Routes\OfferPreviewRoute` provides the admin preview payload through `OfferService`. `Api\Routes\ProductsRoute` and `Api\ProductsController` provide AJAX product search and recommendations endpoints for the admin offer editor.

Block Checkout support is wired through `Integrations\WooCommerce\CheckoutFields`, `Integrations\WooCommerce\StoreApiExtender`, `BlockCheckoutIntegration`, and the compiled `src/block-checkout` entry. The Store API extender hooks into cart mutations to ensure seamless functionality within WooCommerce Blocks.

## Attribution, Analytics, and Compatibility

`Domain\Attribution\AttributionWriter` and `AttributionReader` use WooCommerce CRUD object methods for order item attribution and follow-on order linkage. There are no direct order postmeta writes.

`Domain\Analytics\AnalyticsService` routes views, accepts, dismissals, and orders to the aggregate non-PII stats repository through `AnalyticsRecorder`.

`Domain\Compatibility\CompatibilityScanner` detects known checkout/funnel plugins as warnings and CartBay as informational coexistence guidance. It never hard-fails checkout and does not read CartBay data.

## Validation

Phase 4 behavior is covered by `tests/test-core-business-logic.php` and the existing test runner. Static validation passed with `composer phpcs` and `composer phpstan`. Asset build and POT generation passed. `composer plugin-check` could not run locally because the WP-CLI Plugin Check command is not installed in this WordPress environment.
