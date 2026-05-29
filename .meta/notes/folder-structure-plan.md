# UpsellBay Folder Structure Plan

Based on: PRD v4 (`.meta/PRDs/UpsellBay-PRD-v4.md`), CartBay implementation (/var/www/html/wp-content/plugins/cartbay), and plugin-development-blueprint (`.meta/notes/plugin-development-blueprint.md`).

## Design Principles

1. Mirror CartBay's proven patterns (bootstrap, container, settings sections, route classes, repositories, utilities) but scoped to UpsellBay's domain.
2. No email classes, no recovery/abandonment subsystems, no agent infrastructure — UpsellBay is an AOV engine, not a recovery tool.
3. Admin under `WooCommerce -> UpsellBay` (submenu page, not WC settings tab like CartBay) because UpsellBay has a dedicated list table (offers) that needs more space.
4. Custom aggregate stats table (`upsellbay_offer_stats_daily`) alongside the `upsellbay_offer` CPT.
5. All identifiers prefixed with `upsellbay_` / `_ub_` per the identifier contract in PRD §10.5.

---

## Proposed Directory Structure

```
upsellbay.php                          # Entry point: ABSPATH check, constants, autoload, lifecycle hooks
uninstall.php                          # Data cleanup (opt-in only)

app/
  Admin/
    Offers/
      OfferListTable.php               # WP_List_Table for offer management
      OfferEditPage.php                # Meta-box-based offer editor
    Settings/
      SettingsPage.php                 # Coordinates submenu settings sections
      SettingsSectionInterface.php     # Section contract
      AbstractSettingsSection.php      # Base section implementation
      GeneralSection.php               # Global enable/disable, test mode, placement toggles
      StyleSection.php                 # Style tokens (accent color, border, badge)
      DataSection.php                  # Data retention, debug logging toggle
      ToolsSection.php                 # Import/export, compatibility scan, system info
    Wizard/
      WizardController.php             # First-run setup wizard
    Analytics/
      AnalyticsPage.php                # Dashboard renderer (aggregate stats)
    Coexistence.php                    # CartBay coexistence notice/guidance
    CompatibilityNotice.php            # Known conflicting plugin warnings
    AdminBar.php                       # Test mode indicator in admin bar

  Api/
    Routes/
      BumpToggleRoute.php              # POST /bump-toggle — add/remove checkout bump
      CartOfferAddRoute.php            # POST /cart-offer-add — add product/cart offer
      DismissRoute.php                 # POST /dismiss — dismiss offer for session
      OfferPreviewRoute.php            # GET /offer-preview — admin preview payload
      AnalyticsRoute.php               # GET /analytics/summary — admin analytics
      ImportRoute.php                  # POST /import — admin offer import

  Core/
    Constants.php                      # All plugin identifiers (slug, prefix, URLs, etc.)
    Container.php                      # Minimal DI container (same pattern as CartBay)
    Plugin.php                         # Bootstrap: init order, service registration, hook registration
    Installer.php                      # Activation/deactivation/upgrade: CPT, options, jobs, migrations
    Settings.php                       # Settings normalization helpers

  Data/
    OfferRepository.php                # CRUD wrapper around upsellbay_offer CPT + meta
    StatsRepository.php                # Read/write for upsellbay_offer_stats_daily table
    CartSession.php                    # WC session helpers for offer state

  Domain/
    Offers/
      OfferService.php                 # Offer CRUD business logic, validation, duplication
      OfferValidator.php               # Schema/rule validation on save
      OfferPrioritizer.php             # Highest-priority offer selection per context
    Rules/
      RuleEvaluator.php                # Evaluates AND/OR rules against cart/product/customer context
      RuleParser.php                   # Normalizes and validates raw rule definitions
    Cart/
      CartMutator.php                  # Add/remove offer products to cart, apply discounts
      CartValidator.php                # Stock, subscription, and compatibility guards
    Discounts/
      DiscountApplier.php              # Session-scoped price adjustments (no persistent coupons)
      DiscountCalculator.php           # Fixed amount, percentage, fixed price calculations
    Attribution/
      AttributionWriter.php            # Writes _ub_* meta to order items / follow-on orders via CRUD
      AttributionReader.php            # Reads attribution data for analytics
    Analytics/
      AnalyticsRecorder.php            # Atomic increments on aggregate stats table
      AnalyticsService.php             # Query aggregation, date range filtering, AOV lift calc
      StatsReconciler.php              # Daily reconciliation job
    Storefront/
      PlacementRenderer.php            # Coordinates which renderer runs for current context
      CheckoutBumpRenderer.php         # Classic checkout bump HTML + AJAX toggle
      ProductUpsellRenderer.php        # Product page "frequently bought together" module
      CartCrossSellRenderer.php        # Cart page offer display
      ThankYouOfferRenderer.php        # Order-received page follow-on offer

  Integrations/
    WooCommerce/
      CheckoutFields.php               # Additional Checkout Fields API registration for Block Checkout
      BlockCheckoutIntegration.php     # Slot/Fill + Store API integration for Block Checkout
    Licensing/
      LicenseClient.php                # WP Anchor Bay license server communication
      Updater.php                      # PUC update checker bootstrap

  Utils/
    Logger.php                         # WooCommerce logger wrapper with subsystem context
    RateLimiter.php                    # Transient-based rate limiting for public REST endpoints
    TokenHelper.php                    # Random token generation/hashing
    ImportExporter.php                 # JSON import/export with SKU mapping

assets/
  admin/
    css/
      upsellbay-admin.css              # Admin list table, editor, analytics styles
    js/
      upsellbay-admin.js               # Admin UI enhancements (Select2, preview, etc.)
      upsellbay-offer-editor.js        # Offer editor JS (rule builder, product search)
      upsellbay-analytics.js           # Analytics chart/table interactivity
  frontend/
    css/
      upsellbay-storefront.css         # Offer widget styles (bump, upsell, cross-sell, thank-you)
    js/
      upsellbay-checkout-bump.js       # Classic checkout bump AJAX toggle
      upsellbay-block-checkout.js      # Block Checkout integration bundle
      upsellbay-product-upsell.js      # Product page add-to-cart
      upsellbay-cart-offer.js          # Cart page offer interaction
      upsellbay-thankyou.js            # Thank-you page follow-on flow

src/
  admin/
    index.js                           # Admin entry
    offer-editor/
    analytics/
  classic-checkout/
    index.js                           # Classic bump toggle, cart fragments
  block-checkout/
    index.js                           # Block checkout Slot/Fill integration
  storefront/
    product-upsell/
    cart-offer/
    thankyou/

templates/
  storefront/
    checkout-bump.php                  # Checkbox bump template
    product-upsell.php                 # Product page offer template
    cart-crosssell.php                 # Cart offer template
    thankyou-offer.php                 # Thank-you page offer template
  admin/
    wizard-step-offer-type.php
    wizard-step-product.php
    wizard-step-rules.php
    wizard-step-preview.php

languages/
  upsellbay.pot                        # Generated translation template

tests/
  TestCases/
    Offers/
    Rules/
    Cart/
    Discounts/
    Attribution/
    Analytics/
    Storefront/
    Api/
    Core/
  bootstrap.php

docs/
  README.md
  developer-hooks.md
  compatibility-matrix.md
  marketplace-reviewer-guide.md

composer.json                         # PSR-4 autoload, PHPCS, PHPStan, PHPUnit, PUC dependency
package.json                          # @wordpress/scripts build, i18n, release
webpack.config.js                     # Multiple entry points mapped to assets/
phpcs.xml                             # WPCS configuration
phpstan.neon                          # PHPStan configuration
phpstan-bootstrap.php                 # PHPStan bootstrap
phpunit.xml                           # PHPUnit configuration
```

---

## Key Structural Decisions

### 1. Admin: Submenu Page vs WC Settings Tab

CartBay uses a `woocommerce_settings_tabs_array` tab. UpsellBay should use `add_submenu_page` under `WooCommerce` instead.

**Why:** UpsellBay needs a WP_List_Table for offers (bulk actions, search, filters, sortable columns). WC settings tabs render settings API fields — they don't naturally host list tables. A submenu page with custom render gives full control over the offers list table, the offer editor (meta boxes), analytics dashboard, settings, and tools.

The submenu is registered in `Plugin::register_admin_menu()` matching how CartBay does its submenu.

### 2. No Email / Recovery / Agent Subsystems

CartBay has `app/Email/`, `app/Recovery/`, `app/Agent/`. UpsellBay omits all of these. Instead:
- "Next-order coupon" (P1) lives under `Domain/Discounts/` — it generates a WooCommerce coupon, not an email.
- Async work is minimal: analytics reconciliation and license checks via Action Scheduler.

### 3. Custom Table + CPT

- `upsellbay_offer` CPT stores offer definitions (postmeta for all `_ub_*` fields).
- `{$wpdb->prefix}upsellbay_offer_stats_daily` stores aggregate analytics (one row per date/offer/placement).
- Both are created/managed by `Installer.php` using `dbDelta` and `register_post_type()`.

### 4. Block Checkout Integration

The `Integrations/WooCommerce/` directory holds both:
- `CheckoutFields.php` — registers the Additional Checkout Fields API for Block Checkout compatibility.
- `BlockCheckoutIntegration.php` — handles Slot/Fill rendering and Store API mutations if the plain checkbox field is not sufficient.

This keeps Block-specific code isolated from classic renderers.

### 5. JavaScript Entry Points

Four frontend bundles + one admin bundle, matching CartBay's `webpack.config.js` pattern:

| Entry | Source | Output | Pages |
|-------|--------|--------|-------|
| Admin | `src/admin/index.js` | `assets/admin/js/upsellbay-admin.js` | All admin pages |
| Classic checkout | `src/classic-checkout/index.js` | `assets/frontend/js/upsellbay-checkout-bump.js` | Checkout page (classic) |
| Block checkout | `src/block-checkout/index.js` | `assets/frontend/js/upsellbay-block-checkout.js` | Checkout page (blocks) |
| Product upsell | `src/storefront/product-upsell/index.js` | `assets/frontend/js/upsellbay-product-upsell.js` | Product page |
| Cart offer | `src/storefront/cart-offer/index.js` | `assets/frontend/js/upsellbay-cart-offer.js` | Cart page |
| Thank-you | `src/storefront/thankyou/index.js` | `assets/frontend/js/upsellbay-thankyou.js` | Order-received page |

### 6. REST Endpoint Mapping (from PRD §10.10)

| Endpoint | Route class | Method | Auth |
|----------|-------------|--------|------|
| `/upsellbay/v1/offer-preview` | `OfferPreviewRoute` | GET | `manage_woocommerce` + nonce |
| `/upsellbay/v1/bump-toggle` | `BumpToggleRoute` | POST | Nonce/guest session + rate limit |
| `/upsellbay/v1/cart-offer-add` | `CartOfferAddRoute` | POST | Guest-safe + rate limit |
| `/upsellbay/v1/dismiss` | `DismissRoute` | POST | Session-bound + rate limit |
| `/upsellbay/v1/analytics/summary` | `AnalyticsRoute` | GET | `manage_woocommerce` + nonce |
| `/upsellbay/v1/import` | `ImportRoute` | POST | `manage_woocommerce` + nonce + file validation |

### 7. CartBay Coexistence

No shared code. `Admin/Coexistence.php` detects CartBay and shows optional guidance. `Installer.php` never reads/writes CartBay data. The identifier contract (PRD §10.5) enforces separate prefixes across all subsystems.

---

## Services to Register in Container (in `Plugin::register_services()`)

| Class | Dependencies |
|-------|-------------|
| `OfferRepository` | (none) |
| `StatsRepository` | (none) |
| `CartSession` | (none) |
| `OfferService` | `OfferRepository` |
| `OfferValidator` | (none) |
| `OfferPrioritizer` | `OfferRepository`, `RuleEvaluator` |
| `RuleEvaluator` | (none) |
| `RuleParser` | (none) |
| `CartMutator` | `CartValidator` |
| `CartValidator` | (none) |
| `DiscountApplier` | `DiscountCalculator` |
| `DiscountCalculator` | (none) |
| `AttributionWriter` | (none) |
| `AttributionReader` | (none) |
| `AnalyticsRecorder` | `StatsRepository` |
| `AnalyticsService` | `StatsRepository` |
| `StatsReconciler` | `StatsRepository` |
| `PlacementRenderer` | `CheckoutBumpRenderer`, `ProductUpsellRenderer`, `CartCrossSellRenderer`, `ThankYouOfferRenderer` |
| `CheckoutBumpRenderer` | `OfferPrioritizer`, `CartMutator` |
| `ProductUpsellRenderer` | `OfferPrioritizer`, `CartMutator` |
| `CartCrossSellRenderer` | `OfferPrioritizer`, `CartMutator` |
| `ThankYouOfferRenderer` | `OfferPrioritizer`, `CartMutator` |
| `CheckoutFields` | (none) |
| `BlockCheckoutIntegration` | `CheckoutFields` |
| `LicenseClient` | (none) |
| `Logger` | (none) |
| `RateLimiter` | (none) |
| `TokenHelper` | (none) |
| `ImportExporter` | `OfferRepository` |
| `SettingsPage` | Container, `SettingsUrl`, `AdminEnvironment` |
| `WizardController` | Container |

---

## Bootstrapping Flow (matches CartBay)

```
1. upsellbay.php
   → ABSPATH check
   → require Constants.php, register constants
   → Composer autoload
   → plugins_loaded: check WooCommerce exists, init Plugin singleton
   → register_activation_hook → Installer::activate()
   → register_deactivation_hook → Installer::deactivate()

2. Plugin::init()
   → declare_wc_feature_compatibility()     (before_woocommerce_init)
   → register_services()                     (container bindings)
   → register_hooks()                        (wordpress, wc, rest, admin, frontend, scheduler)
   → Updater::init()                         (PUC update checker)
   → do_action('upsellbay_loaded')
```

---

## Action Scheduler Jobs (group: `upsellbay`)

| Hook | Interval | Purpose |
|------|----------|---------|
| `upsellbay_refresh_analytics` | Hourly | Stats reconciliation / cache refresh |
| `upsellbay_prune_stats` | Daily | Cleanup old stats per retention setting |
| `upsellbay_check_license` | Daily | Background license validation |
