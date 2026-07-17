# File Ownership Map

Status: accepted for Phase 0.

Task: `UB-P0-006`.

## Decision

The implementation layout follows PRD v4, the folder structure plan, and the phase task files. Directories should be created only when a task needs files in them. Empty placeholder directories are not required.

## Phase 6.5 Admin Integration Refactor

Pro originally duplicated the host's entire admin shell: its own `app/Admin/AdminPage.php`,
`app/Admin/AdminPageRegistrar.php`, `app/Admin/Navigation/{AdminTab,TabFactory,TabNavigation,TabRegistry,TabRouter}.php`,
and `app/Admin/Offers/{OfferEditPage.php,OfferListTable.php,OffersPage.php}`. These files
are **deleted** in the current working tree. Pro now owns no admin page shell at all — it
attaches to the host's single admin page purely through the host's public hooks. See
[free-pro-coupling.md](./free-pro-coupling.md) for the hook contract and
`app/Integrations/OfferEditDiagnosticsIntegration.php` /
`app/Integrations/OffersListColumnsIntegration.php` below for the pattern that replaced
the deleted files.

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
| `app/Core/Platform.php` | Minimum runtime dependency checks (PHP, WP, Woo). | Phase 1 | - |
| `app/Core/Hooks.php` | Wrapper for WordPress hooks. | Phase 1 | - |
| `app/Core/Scheduler.php` | Action Scheduler wrapper. | Phase 2 | Action Scheduler. |
| `app/Admin/Offers/OfferSectionNavigation.php` | WooCommerce-style section links rendered inside the host's (free's) offer list/edit pages. | Phase 3 | Host `upsellbay_offers_header_after` seam. |
| `app/Admin/Offers/OfferVisibilityPanel.php` | Merchant-facing offer visibility diagnostics, rendered by `OfferEditDiagnosticsIntegration` inside the host's offer editor. | Phase 6 | `OfferVisibilityInspector`. |
| `app/Admin/Settings/*` | Woo-native settings sections, section navigation (`SettingsSectionNavigation`), and tools, all rendered inside the host's Settings tab via `upsellbay_settings_*` hooks. `BasicSection` replaces the former `GeneralSection`. | Phase 3 | Settings service, capability and nonce checks, [free-pro-coupling.md](./free-pro-coupling.md). |
| `app/Admin/Wizard/*` | First-run wizard. | Phase 5 | Offer service and test mode. |
| `app/Admin/Dashboard/*` | Analytics section appended to the host's Dashboard tab via `upsellbay_dashboard_after`. Pro owns no independent Dashboard page. | Phase 3 and Phase 4 | Overview summary, analytics service, and stats repository. |
| `app/Admin/Coexistence.php` | Optional CartBay coexistence guidance without data access. | Phase 3 | Identifier isolation rules. |
| `app/Admin/CompatibilityNotice.php` | Known conflict notices. | Phase 3 and Phase 4 | Compatibility matrix. |
| `app/Admin/AdminBar.php` | Admin-only test mode indicator. | Phase 3 and Phase 5 | Settings service. |
| `app/Admin/PreviewLinks.php` | Shared preview link generator for frontend placements. | Phase 4 | WooCommerce products. |
| `app/Integrations/OfferEditDiagnosticsIntegration.php` | Attaches to the host's `upsellbay_offer_edit_extra_sections` hook; renders `OfferVisibilityPanel` and `OfferConflictDetector` findings inside the host's own offer editor. Replaces the former standalone `OfferEditPage.php`. | Phase 6.5 refactor | Host hook contract, [free-pro-coupling.md](./free-pro-coupling.md). |
| `app/Integrations/OffersListColumnsIntegration.php` | Attaches to the host's `upsellbay_bump_list_columns`, `upsellbay_bump_list_column_content`, and `upsellbay_offer_stats_summary_after` hooks; adds Health/Revenue columns and a stats table to the host's own offers list. Replaces the former standalone `OffersPage.php`/`OfferListTable.php`. | Phase 6.5 refactor | Host hook contract, [free-pro-coupling.md](./free-pro-coupling.md). |
| `app/Api/Routes/*` | REST route registration, permissions, validation, safe responses. | Phase 4 | Domain services, rate limiter, nonces. |
| `app/Api/ProductsController.php` | AJAX product/category search for the admin offer editor. | Phase 3 | WooCommerce product/term APIs. |
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
| `app/Integrations/WooCommerce/*` | Block Checkout and Woo-specific compatibility. | Phase 4 and Phase 7 | Block Checkout POC and E2E proof (passed 2026-06-19). `BlockCheckoutIntegration` receives `Settings` and `CartSession` from DI container. |
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
