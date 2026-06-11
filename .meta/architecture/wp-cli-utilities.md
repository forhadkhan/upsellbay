# WP-CLI Utility Plan

WP-CLI commands are a P1 extension point and are intentionally not registered in v1 unless release scope changes. This avoids dead code while preserving a concrete implementation path.

Planned commands:

| Command | Purpose | Service boundary |
| --- | --- | --- |
| `wp upsellbay stats rollup` | Reconcile aggregate stats rows for a bounded date range. | `StatsReconciler`, `StatsRepository` |
| `wp upsellbay offers export` | Export portable offer templates. | `OfferRepository`, `ImportExporter` |
| `wp upsellbay offers import` | Validate and import portable offers. | `ImportExporter`, `OfferService` |
| `wp upsellbay compatibility scan` | Print compatibility findings. | `CompatibilityScanner` |

Commands must use `manage_woocommerce` or an equivalent trusted CLI execution context, share the same services as admin tools, and avoid exposing license keys, raw tokens, or PII.
