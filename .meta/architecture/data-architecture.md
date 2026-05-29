# Data Architecture

Status: implemented foundation in Phase 2.

Tasks: `UB-P2-001` through `UB-P2-012`.

## Offer Storage

Offers use the private `upsellbay_offer` CPT registered by `Core\Installer`. The CPT remains hidden from public archives, REST exposure, and top-level admin menus.

`Domain\Offers\OfferSchema` and `Domain\Offers\OfferValidator` define and normalize the `_ub_` offer meta contract. `Data\OfferRepository` is the only runtime layer that directly adapts WordPress post and post-meta functions for offer configuration.

## Stats Storage

`Core\Installer::stats_table_schema_sql()` defines the aggregate non-PII stats table:

- `stat_date`
- `offer_id`
- `placement`
- `views`
- `accepts`
- `dismissals`
- `orders`
- `revenue`
- `discount_total`
- `updated_at`

The unique key is `(stat_date, offer_id, placement)`.

`Data\StatsRepository` owns aggregate increments and bounded reads. Normal dashboard flows must read this repository rather than scanning orders.

## Session State

`Data\CartSession` stores accepted offers, dismissed offers, cart item keys, placement, and hashed REST validation tokens in the WooCommerce session. It strips obvious PII keys before persistence.

## Attribution Contract

Attribution key constants live in `Core\Constants`. Phase 4 attribution writers must use WooCommerce order and order-item CRUD APIs only.

## Import/Export

`Utils\ImportExporter` defines versioned JSON with `type=upsellbay_offer_export` and `version=1`. Exported payloads remove site-specific product/category IDs and include a product mapping envelope for SKU/name based portability.

## Retention

`Core\Settings` now normalizes `data_retention.stats_days`, `data_retention.session_days`, `data_retention.log_days`, and keeps `data_retention.prune_order_attribution` forced false. Routine pruning must not delete order attribution metadata.
