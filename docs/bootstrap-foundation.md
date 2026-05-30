# Bootstrap Foundation

UpsellBay Phase 1 provides the standalone plugin entrypoint, centralized identifiers, lifecycle shell, settings normalization, scheduler shell, licensing foundation, updater identity, and base utilities.

Runtime code preserves merchant data by default. `uninstall.php` performs destructive cleanup only when `upsellbay_settings.cleanup_on_delete` is enabled.

Block Checkout compatibility is not declared in Phase 1. HPOS compatibility is declared through WooCommerce's `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` hook when WooCommerce is available.
