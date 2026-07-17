# Service Boundaries

Phase 6 records the public and internal service boundaries that should remain stable through v1.

| Service | Owns | Allowed callers | Must not own |
| --- | --- | --- | --- |
| `OfferService` | Validated offer lifecycle and preview payloads. | Admin pages, REST preview/import surfaces, tests. | Direct post/meta writes outside `OfferRepository`. |
| `OfferPrioritizer` | Eligibility ordering after status, schedule, product, dismissal, and rule checks. | Storefront renderers and offer selection flows. | HTML, cart mutation, analytics persistence. |
| `RuleEvaluator` | Server-side rule context evaluation. | Prioritizer, cart validation. | Client pricing or product loading. |
| `CartMutator` | Accept/remove cart items and session accepted state. | Public routes and checkout integrations. | Trusting client prices or rendering notices. |
| `BlockCheckoutIntegration` | Enqueue block checkout JS, localize storefront config (token, REST URL), and enqueue storefront CSS with style tokens. | `woocommerce_blocks_checkout_block_registration` hook. | Direct HTML rendering or trusting client prices. |
| `DiscountApplier` | Apply stored offer price to Woo cart item objects. | Woo cart-total hook only. | Eligibility decisions or coupon creation. |
| `AttributionWriter` | WooCommerce CRUD attribution writes. | Checkout/order lifecycle integrations. | Direct order tables or postmeta queries. |
| `AnalyticsRecorder` and `AnalyticsService` | Aggregate non-PII event counters. | Storefront, routes, reconciliation. | Live order scans during dashboard load. |
| `LicenseClient` | License status checks and masked key handling. | Updater, settings diagnostics. | Frontend output or direct browser calls. |
| `CompatibilityScanner` | Known plugin risk findings. | Admin notices, diagnostics. | Hard blocking safe CartBay coexistence. |
| `OfferEditDiagnosticsIntegration` | Renders Pro diagnostics (`OfferVisibilityPanel`, `OfferConflictDetector`) inside the host's offer editor. | Host's `upsellbay_offer_edit_extra_sections` hook only. | Its own admin page, offer CRUD, or persistence. |
| `OffersListColumnsIntegration` | Adds Health/Revenue columns and a stats summary to the host's offers list table. | Host's `upsellbay_bump_list_columns` / `upsellbay_bump_list_column_content` / `upsellbay_offer_stats_summary_after` hooks only. | Its own list table or offer queries outside `OfferRepository`. |
| `ImportExporter` | Portable JSON schema validation and export payloads. | Tools page, future REST/CLI import surfaces. | Capability, nonce, or file upload authorization. |
| `Scheduler` | Action Scheduler job registration and cleanup. | Installer and upgrade routines. | Business logic inside scheduled callbacks. |
| `Logger` | Masked operational logging. | Services that need diagnostics. | Raw emails, full license keys, tokens, or payment identifiers. |

Controllers, routes, renderers, and admin pages should delegate to these services instead of duplicating validation, mutation, or persistence logic.
