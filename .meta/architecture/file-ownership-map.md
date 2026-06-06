# File Ownership Map

Status: accepted for Phase 0.

Task: `UB-P0-006`.

## Decision

The implementation layout follows PRD v4, the folder structure plan, and the phase task files. Directories should be created only when a task needs files in them. Empty placeholder directories are not required.

## Runtime Ownership Map

| Path or class group | Responsibility | Owning phase | Key dependencies |
| --- | --- | --- | --- |
| `upsellbay.php` | Thin plugin entrypoint, headers, environment guards, autoload, lifecycle hooks, bootstrap startup. | Phase 1 | Identifier contract, tooling plan. |
| `uninstall.php` | Preserve data by default; opt-in cleanup only. | Phase 1 and Phase 2 | Data retention model. |
| `app/Core/Constants.php` | Central runtime identifiers from PRD v4. | Phase 1 | Identifier contract. |
| `app/Core/Container.php` | Small service container. | Phase 1 | Bootstrap coordinator. |
| `app/Core/Plugin.php` | Initialization order and hook registration. | Phase 1 | Container and services. |
| `app/Core/Installer.php` | Activation, deactivation, upgrade shell, stats table migration, scheduled action setup. | Phase 1 and Phase 2 | Constants, stats schema, Action Scheduler. |
| `app/Core/Settings.php` | Settings defaults, normalization, option access. | Phase 1 and Phase 2 | Single `upsellbay_settings` option. |
| `app/Admin/Offers/*` | WP list table and offer editor surfaces. | Phase 3 | Offer repository and validator. |
| `app/Admin/Settings/*` | Woo-native settings sections, section navigation (`SettingsSectionNavigation`), and tools. `BasicSection` replaces the former `GeneralSection`. | Phase 3 | Settings service, capability and nonce checks. |
| `app/Admin/Wizard/*` | First-run wizard. | Phase 5 | Offer service and test mode. |
| `app/Admin/Dashboard/*` | Dashboard overview and aggregate analytics section. | Phase 3 and Phase 4 | Overview summary, analytics service, and stats repository. |
| `app/Admin/Coexistence.php` | Optional CartBay coexistence guidance without data access. | Phase 3 | Identifier isolation rules. |
| `app/Admin/CompatibilityNotice.php` | Known conflict notices. | Phase 3 and Phase 4 | Compatibility matrix. |
| `app/Admin/AdminBar.php` | Admin-only test mode indicator. | Phase 3 and Phase 5 | Settings service. |
| `app/Admin/AdminPage.php` | Unified WooCommerce submenu page shell with tab routing. Renders the layout header with `Get Started` and `Add Offer` action buttons, page notices, tab navigation, and active tab content. Owns the `admin_title` filter. | Phase 1 | Tab registry, tab router, tab navigation. |
| `app/Admin/Navigation/TabFactory.php` | Registers all tabs. The setup tab is marked `is_visible = false` and labeled `Get started`. | Phase 1 | Dashboard, Offers, Settings, Tools, Wizard, Help page renderers. |
| `app/Api/Routes/*` | REST route registration, permissions, validation, safe responses. | Phase 4 | Domain services, rate limiter, nonces. |
| `app/Data/OfferRepository.php` | Private `upsellbay_offer` CPT and `_ub_` offer meta access. | Phase 2 | Constants and schema. |
| `app/Data/StatsRepository.php` | Aggregate stats table reads/writes. | Phase 2 and Phase 4 | Installer migration. |
| `app/Data/CartSession.php` | Woo session-backed offer state. | Phase 2 and Phase 4 | WooCommerce session APIs. |
| `app/Domain/Offers/*` | Offer validation, CRUD business logic, prioritization. | Phase 4 | Offer repository and rule evaluator. |
| `app/Domain/Rules/*` | Rule parsing and evaluation. | Phase 4 | Cart/product/customer context. |
| `app/Domain/Cart/*` | Server-side cart validation and mutation. | Phase 4 | WooCommerce cart and product APIs. |
| `app/Domain/Discounts/*` | Discount calculation and application. | Phase 4 | Server-side price validation. |
| `app/Domain/Attribution/*` | Order/order-item attribution through Woo CRUD only. | Phase 4 | HPOS compliance plan. |
| `app/Domain/Analytics/*` | Event recording, summaries, reconciliation. | Phase 4 | Stats repository and Action Scheduler. |
| `app/Domain/Storefront/*` | Placement coordination and templates for product, cart, checkout, thank-you. | Phase 4 | Offer prioritizer, cart mutator, templates. |
| `app/Integrations/WooCommerce/*` | Block Checkout and Woo-specific compatibility. | Phase 4 and Phase 7 | Block Checkout POC and E2E proof. |
| `app/Integrations/Licensing/*` | WP Anchor Bay license client and updater. | Phase 1 | License failure behavior. |
| `app/Utils/*` | Logger, rate limiter, token helper, import/export helpers. | Phase 1 through Phase 6 | Security and public API needs. |
| `src/*` | Authored JS entry points. | Phase 1 through Phase 5 | Build tooling and dependency extraction. |
| `assets/*` | Built runtime assets. | Phase 1 through Phase 5 | Build output. |
| `templates/*` | Escaped admin/storefront PHP templates. | Phase 3 through Phase 5 | Renderers and admin pages. |
| `languages/upsellbay.pot` | Translation template. | Phase 1 onward | i18n script. |
| `tests/*` | Unit, integration, E2E support, and test cases. | Phase 1 through Phase 7 | Tooling and implementation surfaces. |
| `docs/*` | Merchant, developer, compatibility, reviewer, migration, and release documentation. | Phase 8, updated continuously | Documentation standards. |

## Explicit Exclusions

UpsellBay must not create runtime subsystems for:

- CartBay sessions.
- Abandoned-cart recovery capture.
- Recovery sequences or notifications.
- Recovery email templates.
- Unsubscribe or restore links.
- SMS or WhatsApp recovery automation.
- Agent infrastructure.
- Funnel-builder checkout replacement.

If future work needs any adjacent integration, it must be documented as a new architecture decision and must use public hooks or documented APIs only.
