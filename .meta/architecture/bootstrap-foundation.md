# Bootstrap Foundation

Status: implemented in Phase 1.

Tasks: `UB-P1-001` through `UB-P1-014`.

## Runtime Shape

The plugin now has a thin `upsellbay.php` entrypoint that loads Composer autoloading when available, falls back to explicit Phase 1 class loading, registers lifecycle hooks, and starts `WPAnchorBay\UpsellBay\Core\Plugin` on `plugins_loaded`.

`Core\Plugin` owns service registration and hook topology. It registers:

- HPOS compatibility on `before_woocommerce_init`.
- Offer CPT registration on `init`.
- Runtime upgrade/scheduler self-healing on `init`.
- Dependency admin notices on `admin_notices`.
- `upsellbay_loaded` after bootstrap initialization.

Block Checkout compatibility is intentionally not declared in Phase 1. That claim remains gated by the Phase 7 E2E task.

## Foundation Services

- `Core\Constants` centralizes all PRD identifier contract values.
- `Core\Container` resolves request-scoped singleton services without adding a framework.
- `Core\Settings` normalizes the single `upsellbay_settings` option.
- `Core\Platform` checks PHP, WordPress, WooCommerce, and required WooCommerce functions without fatal errors.
- `Core\Installer` handles activation, deactivation, CPT registration, stats table migration shell, default settings, scheduler setup, and opt-in uninstall cleanup.
- `Core\Scheduler` registers only UpsellBay Action Scheduler jobs under the `upsellbay` group.
- `Integrations\Licensing\LicenseClient` provides local license state, masked keys, staging-domain classification, and fail-open live-offer policy.
- `Core\Updater` exposes product identity for future private update checks.
- `Utils\Logger`, `Utils\RateLimiter`, and `Utils\TokenHelper` provide the Phase 1 base utilities.

## Data Retention

`uninstall.php` preserves data by default. Destructive cleanup runs only when `upsellbay_settings.cleanup_on_delete` is enabled.

Phase 2 must expand cleanup coverage when offer repositories, attribution contracts, and stats repositories are implemented.

## Tooling

Composer now declares PSR-4 autoloading and scripts for `phpcs`, `phpcbf`, `phpstan`, `test`, and `plugin-check`.

JavaScript build tooling uses `@wordpress/scripts` with explicit entry points for admin, classic checkout, Block Checkout, and storefront bundles. Runtime bundles and asset metadata are committed under `assets/`.
