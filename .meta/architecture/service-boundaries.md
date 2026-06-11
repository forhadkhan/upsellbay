# Service Boundaries

Phase 6 records the public and internal service boundaries that should remain stable through v1.

| Service | Owns | Allowed callers | Must not own |
| --- | --- | --- | --- |
| `OfferService` | Validated offer lifecycle and preview payloads. | Admin pages, REST preview/import surfaces, tests. | Direct post/meta writes outside `OfferRepository`. |
| `OfferPrioritizer` | Eligibility ordering after status, schedule, product, dismissal, and rule checks. | Storefront renderers and offer selection flows. | HTML, cart mutation, analytics persistence. |
| `RuleEvaluator` | Server-side rule context evaluation. | Prioritizer, cart validation. | Client pricing or product loading. |
| `CartMutator` | Accept/remove cart items and session accepted state. | Public routes and checkout integrations. | Trusting client prices or rendering notices. |
| `DiscountApplier` | Apply stored offer price to Woo cart item objects. | Woo cart-total hook only. | Eligibility decisions or coupon creation. |
| `AttributionWriter` | WooCommerce CRUD attribution writes. | Checkout/order lifecycle integrations. | Direct order tables or postmeta queries. |
| `AnalyticsRecorder` and `AnalyticsService` | Aggregate non-PII event counters. | Storefront, routes, reconciliation. | Live order scans during dashboard load. |
| `LicenseClient` | License status checks and masked key handling. | Updater, settings diagnostics. | Frontend output or direct browser calls. |
| `CompatibilityScanner` | Known plugin risk findings. | Admin notices, diagnostics. | Hard blocking safe CartBay coexistence. |
| `ImportExporter` | Portable JSON schema validation and export payloads. | Tools page, future REST/CLI import surfaces. | Capability, nonce, or file upload authorization. |
| `Scheduler` | Action Scheduler job registration and cleanup. | Installer and upgrade routines. | Business logic inside scheduled callbacks. |
| `Logger` | Masked operational logging. | Services that need diagnostics. | Raw emails, full license keys, tokens, or payment identifiers. |

Controllers, routes, renderers, and admin pages should delegate to these services instead of duplicating validation, mutation, or persistence logic.
