# HPOS and CRUD Compliance Proof Plan

Status: accepted for Phase 0.

Task: `UB-P0-004`.

## Decision

UpsellBay order, order-item, product, and coupon access must use WooCommerce CRUD APIs. Runtime code must not directly query or write WooCommerce order storage tables, legacy order postmeta, HPOS tables, or order item meta tables.

## Approved APIs

| Data area | Approved access |
| --- | --- |
| Orders | `wc_get_order()`, `WC_Order` getters/setters, `$order->update_meta_data()`, `$order->save()`. |
| Order items | `WC_Order_Item_Product`, `$item->add_meta_data()`, `$item->update_meta_data()`, order item getters, `$order->save()`. |
| Products | `wc_get_product()`, `WC_Product` getters, stock and visibility APIs. |
| Coupons | `WC_Coupon` only for P1 next-order coupon behavior; checkout bumps must not create public coupon codes. |
| Cart | WooCommerce cart APIs and server-side validation. |
| Analytics | Aggregate non-PII stats table through a repository; no live order scans on normal dashboard load. |

## Prohibited Patterns

Runtime code must not contain direct order storage access such as:

- SQL writes or reads against `wp_posts` for orders.
- SQL writes or reads against `wp_postmeta` for order attribution.
- SQL writes or reads against `woocommerce_order_items`.
- SQL writes or reads against `woocommerce_order_itemmeta`.
- SQL writes or reads against HPOS tables including `wc_orders`, `wc_order_addresses`, `wc_order_operational_data`, and `wc_orders_meta`.
- Direct calls to `update_post_meta()` or `get_post_meta()` for order attribution.
- Direct CartBay identifier or persistence access.

Direct post meta access is allowed for the private `upsellbay_offer` CPT only through the offer repository layer when implementing offer configuration, not for WooCommerce order data.

## Static Scan Plan

Run these scans during implementation and before release:

```bash
rg -n "wp_postmeta|woocommerce_order_itemmeta|woocommerce_order_items|wc_orders|wc_order_addresses|wc_order_operational_data|wc_orders_meta" app tests
rg -n "update_post_meta|get_post_meta|add_post_meta|delete_post_meta" app tests
rg -n "INSERT INTO|UPDATE .*SET|DELETE FROM|SELECT .*FROM" app tests
rg -n "cartbay_|_cartbay_|cartbay-|WPAnchorBay\\\\CartBay|CartBay" app assets src templates tests
```

Any match must be reviewed. SQL may be acceptable only for the UpsellBay aggregate stats table repository and schema migration. Post meta functions may be acceptable only inside the offer repository for the `upsellbay_offer` CPT, never for WooCommerce orders.

## Runtime Verification Plan

- Run order attribution tests with HPOS enabled.
- Run order attribution tests with HPOS disabled.
- Confirm HPOS compatibility declaration uses `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` before WooCommerce initialization.
- Confirm no Cart/Checkout Blocks compatibility declaration is made until Block Checkout E2E passes.
