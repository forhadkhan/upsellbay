# Bootstrap Foundation

UpsellBay Phase 1 provides the standalone plugin entrypoint, centralized identifiers, lifecycle shell, settings normalization, scheduler shell, licensing foundation, updater identity, and base utilities.

Runtime code preserves merchant data by default. `uninstall.php` performs destructive cleanup only when `upsellbay_settings.cleanup_on_delete` is enabled.

HPOS and Block Checkout compatibility are declared through WooCommerce's `FeaturesUtil::declare_compatibility()` hook when WooCommerce is available. Block Checkout support was enabled after the documented E2E proof passed on 2026-06-19.
