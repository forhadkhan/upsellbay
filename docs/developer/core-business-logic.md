# Core Business Logic

UpsellBay Phase 4 implements the offer engine as small services under `app/Domain/`.

- Offers: `OfferService` owns lifecycle actions and preview payloads.
- Rules: `RuleDefinitions`, `RuleParser`, and `RuleEvaluator` define, normalize, and evaluate P0 targeting rules server-side. Entity-based rules are validated on save, and stock-status rules are evaluated from the offered WooCommerce product state.
- Discounts: `DiscountCalculator` computes trusted offer prices from current product prices.
- Cart: `CartValidator`, `CartMutator`, and `DiscountApplier` validate and apply accepted offer items without public coupon codes.
- Storefront: `PlacementRenderer` and placement renderers output native offer cards for checkout, product, cart, and thank-you contexts.
- API: `PublicOfferRoutes` handles shopper interactions with session token validation and rate limiting.
- Attribution: `AttributionWriter` and `AttributionReader` use WooCommerce CRUD methods only.
- Analytics: `AnalyticsService` records aggregate non-PII event counters.
- Compatibility: `CompatibilityScanner` detects risky checkout/funnel plugins without blocking checkout.
- Merchant diagnostics: `OfferVisibilityInspector` and the editor visibility panel explain why a checkout bump is eligible, suppressed, or risky in the current context.
- Observability: `PlacementRenderer` emits skip reasons for suppressed offers, and debug render/skip logging is gated behind the `debug_logging` setting.

Block Checkout code is present as an implementation path, but product and marketplace compatibility claims remain blocked until the Phase 7 Block Checkout E2E suite passes.
