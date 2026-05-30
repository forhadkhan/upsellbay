<!-- STATUS: COMPLETED -->

# Phase 1 - Core Plugin Bootstrap

Goal: build the minimal, stable plugin foundation that every later subsystem can depend on.

## UB-P1-001 - Main Plugin Entrypoint

- Objective: Create the independent UpsellBay plugin entry file.
- Scope definition: Add `upsellbay.php` with plugin headers, `ABSPATH` guard, constant loading, Composer autoload loading, dependency checks, activation/deactivation hooks, and bootstrap startup.
- Dependencies: UB-P0-002, UB-P0-005.
- Implementation notes: Keep the file thin. It must not contain business logic, admin rendering, REST handlers, or CartBay references except a neutral product-separation comment if needed.
- Acceptance criteria: WordPress can discover the plugin, dependency checks do not fatal when WooCommerce is missing, and startup delegates to `WPAnchorBay\UpsellBay\Core\Plugin`.
- Validation and testing requirements: Run `php -l upsellbay.php`; activate on a local WordPress site with WooCommerce active and inactive.
- Estimated complexity: Medium.
- Suggested execution order: 10.

## UB-P1-002 - Core Constants

- Objective: Centralize every identifier from the PRD contract.
- Scope definition: Create `app/Core/Constants.php`.
- Dependencies: UB-P1-001.
- Implementation notes: Define constants or a constants class for plugin slug, version, file, basename, namespace, text domain, option names, meta prefix, hook prefix, REST namespace, CPT, stats table suffix, scheduler group, asset prefix, license slug, and docs URLs.
- Acceptance criteria: No implementation file hardcodes a new prefix that belongs in constants.
- Validation and testing requirements: Run static scan for `upsellbay_`, `_ub_`, `upsellbay/v1`, and `upsellbay-` usages outside `Constants.php` and confirm each is imported from constants or intentionally generated from constants.
- Estimated complexity: Low.
- Suggested execution order: 11.

## UB-P1-003 - Composer Autoload and PHP Tooling

- Objective: Establish PHP autoloading and local code quality scripts.
- Scope definition: Add `composer.json`, `phpcs.xml`, `phpstan.neon`, `phpstan-bootstrap.php`, and `tests/bootstrap.php`.
- Dependencies: UB-P0-005, UB-P1-002.
- Implementation notes: Use PSR-4 root `WPAnchorBay\\UpsellBay\\` mapped to `app/`. Configure WPCS for WordPress/WooCommerce style and PHPStan with WordPress/Woo stubs as dependencies.
- Acceptance criteria: Composer can autoload `app/Core/Plugin.php` and scripts exist for PHPCS, PHPStan, and PHPUnit.
- Validation and testing requirements: Run `composer validate`, `composer phpcs`, and `composer phpstan` after dependencies are installed.
- Estimated complexity: Medium.
- Suggested execution order: 12.

## UB-P1-004 - Minimal Service Container

- Objective: Provide explicit dependency construction without adding a framework.
- Scope definition: Create `app/Core/Container.php`.
- Dependencies: UB-P1-003.
- Implementation notes: Support singleton factories, `set()`, `has()`, and `get()` or `make()`. Throw a clear exception or return `WP_Error` for unknown services depending on call site needs.
- Acceptance criteria: Services can be registered by class name and resolved once per request.
- Validation and testing requirements: Add unit tests for singleton resolution, missing service behavior, and dependency factory invocation count.
- Estimated complexity: Medium.
- Suggested execution order: 13.

## UB-P1-005 - Bootstrap Coordinator

- Objective: Own plugin initialization order and hook topology.
- Scope definition: Create `app/Core/Plugin.php`.
- Dependencies: UB-P1-002, UB-P1-004.
- Implementation notes: Implement `instance()`, `init()`, `register_services()`, `register_hooks()`, `declare_wc_feature_compatibility()`, admin setup, REST route registration, frontend renderer setup, scheduler setup, and `do_action( 'upsellbay_loaded' )`.
- Acceptance criteria: The bootstrap wires services and hooks but contains no offer eligibility, discount, rendering, or analytics business logic.
- Validation and testing requirements: Unit test singleton behavior where practical; runtime test confirms `upsellbay_loaded` fires once.
- Estimated complexity: High.
- Suggested execution order: 14.

## UB-P1-006 - Platform Dependency Guards

- Objective: Fail safely when platform requirements are not met.
- Scope definition: Add dependency checks for PHP, WordPress, WooCommerce, database baseline assumptions, and required WooCommerce functions.
- Dependencies: UB-P1-005.
- Implementation notes: Use admin notices for unsupported environments. Do not fatal on normal admin page loads. Prevent feature initialization when WooCommerce is inactive.
- Acceptance criteria: Unsupported environment shows actionable admin guidance and does not run offer/cart logic.
- Validation and testing requirements: Test with WooCommerce deactivated; test PHP version guard through unit-level version comparison helper.
- Estimated complexity: Medium.
- Suggested execution order: 15.

## UB-P1-007 - Installer, Deactivation, and Upgrade Shell

- Objective: Create lifecycle entry points for activation, deactivation, migrations, and self-healing.
- Scope definition: Create `app/Core/Installer.php`.
- Dependencies: UB-P1-002, UB-P1-005.
- Implementation notes: Activation must register CPT before rewrite flush, seed defaults, create stats table through a migration method, and schedule only UpsellBay jobs. Deactivation must unschedule only UpsellBay jobs and preserve data.
- Acceptance criteria: Activation and deactivation are idempotent and never touch CartBay data.
- Validation and testing requirements: Run activate/deactivate twice locally; verify scheduled actions use group `upsellbay` only.
- Estimated complexity: High.
- Suggested execution order: 16.

## UB-P1-008 - Uninstall and Data Retention Foundation

- Objective: Define deletion behavior without risking merchant data loss.
- Scope definition: Add `uninstall.php` and cleanup helpers in `Installer` or a dedicated cleanup service.
- Dependencies: UB-P1-007, UB-P2-008.
- Implementation notes: Preserve data by default. Delete only when `upsellbay_settings` has explicit cleanup enabled. Cleanup covers UpsellBay options, offers, `_ub_` meta where safely discoverable, stats table, transients, and scheduled jobs.
- Acceptance criteria: Uninstall does nothing destructive unless cleanup is enabled.
- Validation and testing requirements: Manual uninstall test on seed data with cleanup disabled and enabled; verify CartBay data remains untouched.
- Estimated complexity: High.
- Suggested execution order: 35.

## UB-P1-009 - WooCommerce Feature Compatibility Declarations

- Objective: Declare HPOS and Block Checkout compatibility only when valid.
- Scope definition: Add compatibility declaration in the correct WooCommerce lifecycle timing.
- Dependencies: UB-P1-005, UB-P0-003, UB-P0-004.
- Implementation notes: Declare HPOS compatibility early using `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )`. Declare Cart/Checkout Blocks compatibility only after UB-P7-005 passes.
- Acceptance criteria: HPOS compatibility is active; Block compatibility remains gated until E2E proof exists.
- Validation and testing requirements: Run HPOS compatibility test and confirm no premature Block support claim in plugin metadata.
- Estimated complexity: Medium.
- Suggested execution order: 17 for HPOS, final Block declaration after UB-P7-005.

## UB-P1-010 - Settings Foundation

- Objective: Provide normalized plugin settings storage.
- Scope definition: Create `app/Core/Settings.php`.
- Dependencies: UB-P1-002, UB-P1-007.
- Implementation notes: Store a single `upsellbay_settings` option with defaults for enabled state, test mode, placement toggles, style tokens, license display state, retention, notice dismissals, and debug logging.
- Acceptance criteria: Settings reads always return a complete normalized array, even before the option exists.
- Validation and testing requirements: Unit tests cover default settings, missing keys, checkbox normalization, invalid retention, and boolean casting.
- Estimated complexity: Medium.
- Suggested execution order: 18.

## UB-P1-011 - Scheduler Foundation

- Objective: Register and deduplicate UpsellBay background jobs.
- Scope definition: Add scheduler service or methods for `upsellbay_refresh_analytics`, `upsellbay_prune_stats`, and `upsellbay_check_license`.
- Dependencies: UB-P1-007.
- Implementation notes: Use Action Scheduler functions only. Jobs must be grouped under `upsellbay`, checked with `as_has_scheduled_action()`, and idempotent.
- Acceptance criteria: Activation schedules the three recurring jobs once and runtime self-healing restores missing jobs.
- Validation and testing requirements: Inspect Action Scheduler table or UI after activation; run duplicate activation and confirm no duplicate jobs.
- Estimated complexity: Medium.
- Suggested execution order: 19.

## UB-P1-012 - License and Updater Foundation

- Objective: Add WP Anchor Bay licensing and private update architecture.
- Scope definition: Create `app/Integrations/Licensing/LicenseClient.php` and `app/Core/Updater.php`.
- Dependencies: UB-P1-002, UB-P1-010.
- Implementation notes: Use license product slug `upsellbay`. Mask keys in UI/logs. Local/staging domains must not consume production activations. License server outages must not disable live offers.
- Acceptance criteria: License status can be activated, cached, checked, deactivated, and failed open for runtime offer display.
- Validation and testing requirements: Unit tests for domain classification, masked key display, outage handling, and cached valid state.
- Estimated complexity: High.
- Suggested execution order: 20.

## UB-P1-013 - Asset Build Foundation

- Objective: Establish JavaScript and CSS source/build conventions.
- Scope definition: Add `package.json`, build config, `src/` entry directories, and `assets/` output paths.
- Dependencies: UB-P0-005, UB-P1-002.
- Implementation notes: Planned entries are admin, classic checkout, block checkout, product upsell, cart offer, and thank-you. Frontend assets must load only on relevant contexts.
- Acceptance criteria: `bun run build` or the selected package script creates committed assets without global sitewide bundles.
- Validation and testing requirements: Run build; inspect generated asset filenames and dependency metadata.
- Estimated complexity: Medium.
- Suggested execution order: 21.

## UB-P1-014 - Base Utilities

- Objective: Add reusable helpers needed by several phases.
- Scope definition: Create `app/Utils/Logger.php`, `app/Utils/RateLimiter.php`, `app/Utils/TokenHelper.php`, and base formatting helpers only if used by immediate tasks.
- Dependencies: UB-P1-004, UB-P1-012.
- Implementation notes: Logger must use WooCommerce logging and mask license keys, emails, tokens, and payment identifiers. Rate limiter must support public REST endpoints with endpoint/IP keys.
- Acceptance criteria: Utilities are small, tested, and do not pull in unrelated feature behavior.
- Validation and testing requirements: Unit tests for masking, token length/hash behavior, and rate-limit threshold/TTL.
- Estimated complexity: Medium.
- Suggested execution order: 22.
