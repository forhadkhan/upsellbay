# Offer Schema

Offer configuration is stored as private `upsellbay_offer` CPT meta using `_ub_` keys. Developers should construct offers through the normalized schema rather than writing post meta directly.

| Key | Type | Default | Allowed values |
| --- | --- | --- | --- |
| `_ub_offer_type` | string | `checkout_bump` | `checkout_bump`, `product_upsell`, `cart_crosssell`, `thankyou_offer` |
| `_ub_status` | string | `draft` | `active`, `paused`, `draft` |
| `_ub_offer_product_id` | int | `0` | Existing WooCommerce product ID |
| `_ub_trigger_product_ids` | int list | `[]` | Product IDs |
| `_ub_trigger_category_ids` | int list | `[]` | Category IDs |
| `_ub_discount_type` | string | `none` | `none`, `percent`, `fixed_amount`, `fixed_price` |
| `_ub_discount_value` | decimal string | `0.000000` | Non-negative decimal |
| `_ub_headline` | string | empty | Max 80 characters |
| `_ub_body` | limited HTML string | empty | `a`, `br`, `em`, `strong` |
| `_ub_button_text` | string | empty | Max 40 characters |
| `_ub_rules` | array | `[]` | Normalized rule objects |
| `_ub_rules_match` | string | `all` | `all`, `any` |
| `_ub_placement_config` | array | `[]` | Placement-specific options. The built-in editor exposes predefined `position` choices: `before_submit`, `after_add_to_cart`, `after_cart_table`, and `order_received_actions`. The saved `position` currently affects display metadata/classes and editor defaults; it does not dynamically move WooCommerce hook registration. Advanced JSON keys are preserved for developer integrations. |
| `_ub_show_image` | bool | `true` | Boolean |
| `_ub_start_at` | datetime or null | `null` | `Y-m-d H:i:s` |
| `_ub_end_at` | datetime or null | `null` | `Y-m-d H:i:s` |
| `_ub_priority` | int | `0` | Lower numbers render first |

Validation errors are keyed by schema field, for example `_ub_offer_product_id` or `_ub_discount_type`. Imports must include the `upsellbay_offer_export` envelope from `docs/developer/import-export-schema.md`.
