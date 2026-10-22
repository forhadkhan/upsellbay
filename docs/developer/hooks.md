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

## Pro Public Route Filters

| Hook | Purpose | Return |
| --- | --- | --- |
| `upsellbay_pro_rate_limit_client_ip` | The client IP public routes rate-limit on and the log table records, truncated (default `REMOTE_ADDR`). Return a trusted visitor IP only from a header your own proxy sets, never one the browser can send. A value that is not a valid IP address is ignored. IPv6 clients are rate-limited per /64 network. | `string` |

## Pro Actions

| Hook | Fires when | Arguments |
| --- | --- | --- |
| `upsellbay_pro_api_request_failed` | A Pro public route refuses a request. | `string $endpoint`, `string $reason`, `array $request` with only `offer_id` and `placement` (never the token, order ID or order key). The Pro log table keeps at most one row per endpoint and reason (and offer, except for `rate_limited` / `invalid_token`) each hour. |
| `upsellbay_pro_license_activation_failed` | Activating a license key failed. | `string $masked_key` (the key masked, for example `AB****78`; the raw key is never passed), `string $error_code`, `WP_Error $error`. The error's messages and data have the key masked too. |
| `upsellbay_pro_license_activated` | A license key was activated. | `string $masked_key` (masked as above; the raw key is never passed). |
| `upsellbay_pro_license_check_failed` | The background license check failed. | `string $error_code`, `WP_Error $error`, key masked as above. |

## Reserved: Pro One-Click (not supported in 1.0.0)

Pro 1.0.0 ships thank-you offers as a separate follow-on checkout only. The one-click post-purchase charge is built but switched off, and these hooks are reserved for a later release. Do not rely on them: they may change without notice, and turning the switch on is unsupported.

| Hook | Purpose | Return |
| --- | --- | --- |
| `upsellbay_pro_one_click_enabled` | Reserved switch for the one-click charge (default `false`). Must be added before Pro boots. While it is off, `/one-click-accept` is not registered (and refuses with 404 if called), no payment charge adapter is built, and thank-you offer markup carries no one-click marker. | `bool` |
| `upsellbay_pro_one_click_max_order_age` | Reserved. Maximum age in seconds of a source order whose thank-you offer may be charged in one click (default 86400). Has no effect while one-click is off. | `int` |
| `upsellbay_pro_register_one_click_adapters` (action) | Reserved. Lets a payment gateway add a charge adapter to the registry. Never fires while one-click is off. | `PaymentChargeAdapterRegistry $registry` |

## Import/Export Filters

| Hook | Purpose |
| --- | --- |
| `upsellbay_export_all_payload` | Adjust the full settings-and-offers export envelope before it is JSON-encoded. Offer post IDs are already stripped by this point. |
