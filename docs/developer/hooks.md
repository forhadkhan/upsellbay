# Developer Hook Reference

UpsellBay exposes hooks only at stable service boundaries. Hook callbacks must not trust client prices, bypass capability checks, bypass nonce or session-token validation, or write CartBay state.

## Filters

| Hook | Purpose | Return |
| --- | --- | --- |
| `upsellbay_offer_schema` | Extend normalized offer meta defaults before validation. | `array<string,mixed>` |
| `upsellbay_available_placements` | Add documented placement labels for integration UIs. | `array<string,string>` |
| `upsellbay_offer_query_args` | Adjust private offer CPT query arguments. | `array<string,mixed>` |
| `upsellbay_rule_context` | Add server-derived values to rule evaluation context. | `array<string,mixed>` |
| `upsellbay_rule_result` | Override a single normalized rule result. | `bool` |
| `upsellbay_eligible_offers` | Adjust eligible offers after status, schedule, product, and rule checks. | `array<int,array<string,mixed>>` |
| `upsellbay_render_offer_html` | Adjust escaped storefront offer HTML. | `string` |
| `upsellbay_offer_price` | Adjust the server-calculated offer price. | formatted decimal string |
| `upsellbay_discount_amount` | Adjust the server-calculated discount amount. | formatted decimal string |
| `upsellbay_attribution_meta` | Add non-PII attribution meta before WooCommerce CRUD writes. | `array<string,mixed>` |
| `upsellbay_analytics_event` | Adjust non-PII aggregate analytics event payloads. | `array<string,mixed>` |

## Actions

| Hook | Fires when |
| --- | --- |
| `upsellbay_offer_created` | `OfferService` creates a validated offer. |
| `upsellbay_offer_updated` | `OfferService` updates a validated offer. |
| `upsellbay_offer_rendered` | A storefront placement renders an offer. |
| `upsellbay_offer_accepted` | A public offer endpoint accepts and adds an offer. |
| `upsellbay_offer_dismissed` | A shopper dismisses an offer for the current session. |
| `upsellbay_attribution_written` | Attribution meta is written through WooCommerce CRUD methods. |
| `upsellbay_follow_on_order_created` | A thank-you follow-on order is linked to its source order and offer. |
| `upsellbay_daily_stats_reconciled` | A bounded stats reconciliation operation repairs an aggregate row. |

## Import/Export Filters

These filters support portable agency templates. They do not bypass JSON shape validation, capability checks, nonce checks, or product validation.

| Hook | Purpose |
| --- | --- |
| `upsellbay_export_payload` | Adjust a generated export envelope after site-specific product IDs are stripped. |
| `upsellbay_import_mapping` | Normalize product mapping data before SKU/name matching. |
| `upsellbay_import_sku_match` | Resolve a product ID from a portable SKU mapping. |
| `upsellbay_import_validation_errors` | Add integration-specific validation messages while keeping the import invalid. |
| `upsellbay_import_post_status` | Choose the post status used for imported draft offers. |
