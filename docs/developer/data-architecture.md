# Data Architecture

UpsellBay stores offer configuration in the private `upsellbay_offer` CPT and stores offer configuration meta with `_ub_` keys only.

Order attribution keys are centralized in `WPAnchorBay\UpsellBayPro\Core\Constants`:

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

### Two stat stores

Offer interactions are kept in two places:

| Store | Written by |
| --- | --- |
| Host offer counters: post meta `_ub_counter_<type>` on the offer, shown on the host Offers list | The free host, from its `upsellbay_offer_*` actions. `HostStatsBridge` re-fires Pro placement events (product page, cart, thank-you) on those actions. |
| Pro daily table `{$wpdb->prefix}upsellbay_offer_stats_daily` | Pro's `AnalyticsRecorder`. Pro placements record directly; for `checkout_bump`, `HostStatsBridge` records the host's events. |

Each placement flows one way, so an event is not counted twice in the same store. The two stores are separate and are not reconciled with each other.

In the Pro table, `AnalyticsRecorder::record_accept( $offer_id, $placement, $date )` increments accepts only. Orders, revenue and discount total are added once, by `record_order()`, when the order exists.

Cart session offer state stores accepted and dismissed offer IDs, placement, cart item keys, and hashed validation tokens only. It must not store email, phone, customer identifiers, license keys, or payment identifiers.
