# Data Architecture

UpsellBay stores offer configuration in the private `upsellbay_offer` CPT and stores offer configuration meta with `_ub_` keys only.

Order attribution keys are centralized in `WPAnchorBay\UpsellBay\Core\Constants`:

- `_ub_offer_id`
- `_ub_offer_type`
- `_ub_offer_placement`
- `_ub_discount_type`
- `_ub_discount_amount`
- `_ub_source_context`
- `_ub_source_order_id`
- `_ub_source_offer_id`
- `_ub_follow_on_order`

Order and order-item attribution must be written through WooCommerce CRUD APIs in later business-logic phases. Routine retention pruning must not delete order attribution metadata.

Aggregate analytics live in `{$wpdb->prefix}upsellbay_offer_stats_daily`. Dashboard reads must use aggregate stats, not live order scans.

Cart session offer state stores accepted and dismissed offer IDs, placement, cart item keys, and hashed validation tokens only. It must not store email, phone, customer identifiers, license keys, or payment identifiers.
