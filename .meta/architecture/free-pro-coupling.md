# Free ↔ Pro Coupling

Status: documents the actual, current coupling mechanism between
`upsellbay-order-bump-for-woocommerce` (free, the host) and `upsellbay` (Pro), verified
against on-disk code, not readme marketing copy. Supersedes any earlier assumption that
Pro shares live PHP objects with free — it does not. See
[free-plugin-architecture.md](./free-plugin-architecture.md) for free's own architecture
and [file-ownership-map.md](./file-ownership-map.md) for the current Pro file layout.

## The core fact: two independent implementations, not a shared runtime

Pro does not call into free's live PHP objects. Pro ships its own complete,
independently namespaced implementation of almost the entire domain/data layer:
`Domain\Offers\{OfferService,OfferRepository,OfferValidator,OfferSchema}`,
`Domain\Rules\{RuleEvaluator,RuleParser}`, `Domain\Discounts\DiscountCalculator`,
`Domain\Cart\{CartMutator,CartValidator}`, `Domain\Attribution\{AttributionWriter,AttributionReader}`,
`Domain\Storefront\{PlacementRenderer,StorefrontController}`,
`Api\Routes\PublicOfferRoutes`, and its own `Core\{Container,Plugin,Hooks,Constants}`.
No cross-namespace PHP references exist between the two codebases (confirmed by grep —
zero hits for `WPAnchorBay\UpsellBay\` outside Pro's own `UpsellBayPro` namespace).
Pro's `Plugin::register_services()` (`app/Core/Plugin.php:174-284`) builds its own
container from scratch and never touches free's `Plugin::instance()->container()`.

The two plugins are coupled at exactly three surfaces:

1. **Shared WordPress primitives** — same CPT `upsellbay_offer`, same postmeta prefix
   `_ub_`, same REST namespace `upsellbay/v1`, same options (`upsellbay_settings`,
   `upsellbay_db_version`). Both plugins' repositories independently read/write this
   same underlying data.
2. **Native WordPress/WooCommerce hooks** that both independently attach to (e.g. both
   register `woocommerce_review_order_before_submit`, `woocommerce_before_calculate_totals`,
   `woocommerce_checkout_create_order_line_item`, `init`, `admin_notices`). See "Double
   registration risk" below.
3. **Free's own public `upsellbay_*` hooks** — Pro attaches listener classes to these.
   This is the "documented hooks" contract the free readme promises ("The Pro add-on
   attaches to this free plugin through documented hooks; deactivating Pro keeps your
   bumps running normally.").

## Identifier divergence (Pro intentionally does NOT reuse all of free's identifiers)

Free's `Constants.php` docblock states: "The Pro add-on hooks into the same hooks,
options, CPT, and REST namespace — it never redefines them." In practice this is only
true for a subset of identifiers. Verified from both `Constants.php` files directly:

| Identifier | Free value | Pro value | Shared? |
| --- | --- | --- | --- |
| Namespace root | `WPAnchorBay\UpsellBay` | `WPAnchorBay\UpsellBayPro` | No |
| Plugin slug | `upsellbay-order-bump-for-woocommerce` | `upsellbay` | No |
| Admin menu slug | `upsellbay` | `upsellbay-pro` | No |
| Option prefix | `upsellbay_` | `upsellbay_` | **Yes** |
| Settings option | `upsellbay_settings` | `upsellbay_settings` | **Yes** |
| Meta prefix | `_ub_` | `_ub_` | **Yes** |
| Offer CPT | `upsellbay_offer` | `upsellbay_offer` | **Yes** |
| REST namespace | `upsellbay/v1` | `upsellbay/v1` | **Yes** |
| **Hook prefix** | `upsellbay_` | `upsellbay_pro_` | **No** |
| **Nonce prefix** | `upsellbay_` | `upsellbay_pro_` | No |
| Asset handle prefix | `upsellbay-` | `upsellbay-pro-` | No |
| Stats table suffix | (none — free has no stats table) | `upsellbay_pro_offer_stats_daily` | No |
| Action Scheduler group | (none in free) | `upsellbay-pro` | No |

**This matters most for hooks**: Pro's own `Hooks::action('offer_created', ...)` (fired by
Pro's own parallel `OfferService`) fires `upsellbay_pro_offer_created` — a *different*
hook from free's `upsellbay_offer_created`. Pro's hooks are for third parties extending
*Pro's* independent implementation; they are not the seam Pro itself listens on. The seam
Pro listens on is free's non-`_pro_`-prefixed hooks, enumerated below.

## Full inventory of free's public extension hooks

All fired via free's `Hooks::action()`/`Hooks::filter()` (prefix `upsellbay_`) unless
marked "raw" (a bare `do_action()`/`apply_filters()` call not going through the facade —
this distinction matters, see "Known issue" below).

| Hook (suffix shown; prefix `upsellbay_`) | Type | Fired in | Consumed by Pro? |
| --- | --- | --- | --- |
| `loaded` | action | `Plugin.php:144` | No |
| `register_services` | action, passes `$container` | `Plugin.php:271` | No |
| `rest_routes` | action | `Plugin.php:265` | No |
| `dashboard_after` | action | `Admin/Dashboard/DashboardPage.php:92` | **Yes** |
| `admin_tabs` | filter | `Admin/Navigation/TabFactory.php:218` | No |
| `offer_stats_summary_after` | action | `Admin/Offers/OfferEditPage.php:1287` | **Yes** |
| `before_save_bump` | filter | `Admin/Offers/OfferEditPage.php:185` | No |
| `after_save_bump` | action | `Admin/Offers/OfferEditPage.php:233` | No |
| `offer_edit_extra_sections` | action | `Admin/Offers/OfferEditPage.php:506` | **Yes** |
| `bump_list_columns` | filter | `Admin/Offers/OffersPage.php:147` | **Yes** |
| `bump_list_column_content` | action | `Admin/Offers/OffersPage.php:457` | **Yes** |
| `settings_sections` | filter | `Admin/Settings/SettingsSectionNavigation.php:82` | **Yes** |
| `settings_section_save` | action | `Admin/Settings/SettingsPage.php:176` | **Yes** |
| `settings_section_content` | action | `Admin/Settings/SettingsPage.php:236` | **Yes** |
| `tools_tab_content` | action | `Admin/Tools/ToolsPage.php:48` | **Yes** |
| `enqueue_admin_assets` | action | `Admin/AdminAssets.php:118` | **Yes** |
| `admin_script_data` | filter | `Admin/AdminAssets.php:177` | No |
| `admin_page_license_banner` (raw) | action | `Admin/AdminPage.php:100` | Dead — see known issue |
| `admin_page_heading_before` (raw) | action | `Admin/AdminPage.php:118` | Dead — see known issue |
| `offers_header_after` (raw) | action | `Admin/Offers/OffersPage.php:121` | No |
| `compatibility_findings` (raw filter) | filter | `Admin/CompatibilityNotice.php:56` | No — real, unused seam |
| `offer_created` / `offer_updated` / `offer_deleted` | action | `Domain/Offers/OfferService.php:74/104/158` | No |
| `offer_schema` | filter | `Domain/Offers/OfferSchema.php:72` | No |
| `available_placements` | filter | `Domain/Offers/OfferSchema.php:108` | No |
| `placement_config_positions` | filter | `Domain/Offers/OfferSchema.php:133` | No |
| `eligible_offers` | filter | `Domain/Offers/OfferPrioritizer.php:108` | No — real, unused seam |
| `register_placements` | filter | `Domain/Storefront/PlacementRenderer.php:82` | No — real, unused seam |
| `render_offer_html` | filter | `AbstractOfferRenderer.php:105`, `ClassicCheckoutBump.php:87`, `PlacementRenderer.php:148` | No |
| `offer_skipped` / `offer_rendered` | action | `Domain/Storefront/PlacementRenderer.php:125/150` | No |
| `rule_context` / `rule_result` | filter | `Domain/Rules/RuleEvaluator.php:63/108` | No |
| `offer_price` / `discount_amount` | filter | `Domain/Discounts/DiscountCalculator.php:66/78` | No |
| `attribution_meta` | filter | `Domain/Attribution/AttributionWriter.php:52` | No |
| `attribution_written` | action | `Domain/Attribution/AttributionWriter.php:58` | No |
| `follow_on_order_created` | action | `Domain/Attribution/AttributionWriter.php:83` | No |
| `offer_query_args` | filter | `Data/OfferRepository.php:308` | No — real, unused seam |
| `offer_accepted` / `offer_dismissed` | action | `Api/Routes/PublicOfferRoutes.php:274,335/538` | No |
| `api_request_failed` | action | `Api/Routes/PublicOfferRoutes.php:568,579` | No |

Seams free exposes that Pro currently does **not** consume: `register_services`,
`admin_tabs`, `compatibility_findings`, `before_save_bump`, `after_save_bump`,
`render_offer_html`, `eligible_offers`, `register_placements`, `offer_query_args`,
`offers_header_after`. These are real, live extension points — the natural attachment
points for any future feature (including a hypothetical bundle feature; see
[bundle-feature-feasibility-note.md](./bundle-feature-feasibility-note.md) if present).

## Confirmed Pro listeners (the live, working half of the contract)

| Free hook fired | Pro listener | File:line |
| --- | --- | --- |
| `upsellbay_dashboard_after` | `DashboardPage::render_analytics` | `app/Admin/Dashboard/DashboardPage.php:99` |
| `upsellbay_offer_edit_extra_sections` | `OfferEditDiagnosticsIntegration::render` | `app/Integrations/OfferEditDiagnosticsIntegration.php:67` |
| `upsellbay_bump_list_columns` (filter) | `OffersListColumnsIntegration::add_columns` | `app/Integrations/OffersListColumnsIntegration.php:79` |
| `upsellbay_bump_list_column_content` | `OffersListColumnsIntegration::render_column_content` | same file:80 |
| `upsellbay_offer_stats_summary_after` | `OffersListColumnsIntegration::render_stats_summary_after` | same file:81 |
| `upsellbay_settings_sections` (filter) | `SettingsPage::add_host_sections` | `app/Admin/Settings/SettingsPage.php:175` |
| `upsellbay_settings_section_content` | `SettingsPage::render_host_section_content` | same:176 |
| `upsellbay_settings_section_save` | `SettingsPage::handle_host_section_save` | same:177 |
| `upsellbay_enqueue_admin_assets` | `AdminAssets::enqueue` | `app/Admin/AdminAssets.php:36` |
| `upsellbay_tools_tab_content` | `ToolsPage::render_content` | `app/Admin/Tools/ToolsPage.php:82` |
| `upsellbay_rest_routes` | `ProductsRoute::register`, `Plugin::register_license_routes` | `app/Core/Plugin.php:343-344` |

## The admin-integration pattern (current, post-refactor)

Pro previously duplicated free's entire admin shell: its own `AdminPage.php`,
`Admin/Navigation/{TabFactory,TabRegistry,TabRouter,TabNavigation,AdminTab}.php`, and its
own `Admin/Offers/{OffersPage,OfferListTable,OfferEditPage}.php`. **These files are
deleted in the current working tree** (uncommitted). Pro now owns **zero** admin page
shell — it is a pure hook listener into free's single admin page. The two files that
replaced the deleted offer-editor/list-table duplication are the canonical pattern going
forward:

- `app/Integrations/OfferEditDiagnosticsIntegration.php` — attaches to
  `upsellbay_offer_edit_extra_sections`; renders Pro's `OfferVisibilityPanel` and
  `OfferConflictDetector` findings inside free's own offer edit page. No admin page of
  its own.
- `app/Integrations/OffersListColumnsIntegration.php` — attaches to
  `upsellbay_bump_list_columns` (filter, adds Health/Revenue columns),
  `upsellbay_bump_list_column_content` (renders those columns' cell content), and
  `upsellbay_offer_stats_summary_after` (appends a revenue/orders/discount table to
  free's per-offer stats block). No list table of its own.

Any future Pro feature that needs an admin presence should follow this pattern: a small
`Integrations\*` class with `register_hooks()` attaching to an existing free seam,
rather than a new competing admin page. See
[file-ownership-map.md](./file-ownership-map.md) for the full current file list.

## Double-registration risk

Both `StorefrontController::register_hooks()` (free: `app/Domain/Storefront/StorefrontController.php:90`
in the free plugin; Pro: `app/Domain/Storefront/StorefrontController.php:97` in Pro)
independently call `add_action('woocommerce_review_order_before_submit', [...,'render_checkout_bump'])`.
When both plugins are active, checkout-bump rendering is driven by **two independent
`StorefrontController` instances**, each querying and rendering offers with no
cross-plugin de-dup guard found in either file. This has not been confirmed to cause a
visible double-render in practice, but the code path allows it and it should be treated
as an open risk when reasoning about checkout-bump behavior with both plugins active.

## Known issue: a raw hook name mismatch (confirmed dead wiring)

Two of free's `do_action()` calls bypass the `Hooks::` facade and use bare, unprefixed
strings:

```
Admin/AdminPage.php:100:  do_action( 'upsellbay_admin_page_license_banner' );
Admin/AdminPage.php:118:  do_action( 'upsellbay_admin_page_heading_before' );
```

But Pro listens on the `_pro_`-infixed variants (following the `Constants::hook_name()`
convention, which is correct for Pro's *own* hooks but wrong for attaching to free's):

```
app/Core/Plugin.php:300:                add_action( 'upsellbay_pro_admin_page_license_banner', ... );
app/Admin/CompatibilityNotice.php:57:   add_action( 'upsellbay_pro_admin_page_heading_before', ... );
app/Admin/Settings/SettingsPage.php:558: add_action( 'upsellbay_pro_admin_page_heading_before', ... );
```

These never fire from free's `AdminPage::render()`. Pro's license banner and
compatibility notice inside the free admin shell are non-functional via this path in the
current codebase. **Lesson for any future integration work**: attach to the *exact*
literal string free fires, not a `Constants::hook_name()`-derived guess — free's raw
`do_action()` calls do not carry the `upsellbay_pro_` prefix just because Pro's own
convention would predict it.

## Dependency enforcement (how Pro requires free)

- Pro's plugin header declares WordPress's native plugin-dependency mechanism:
  `Requires Plugins: woocommerce, upsellbay-order-bump-for-woocommerce`
  (`upsellbay.php:13`) — WordPress itself refuses to activate Pro without free active.
- Pro additionally does its own runtime version gate on `plugins_loaded` (priority 20,
  i.e. after free's default-priority-10 `plugins_loaded` init): if
  `!defined('UPSELLBAY_VERSION') || version_compare(UPSELLBAY_VERSION, Constants::MIN_HOST_VERSION, '<')`,
  it prints an admin notice and returns without calling `Plugin::instance()->init()`
  (`upsellbay.php:88-123`). `MIN_HOST_VERSION = '1.0.0'`.
- `Core\Platform::check()` (same shape in both plugins) validates PHP/WP/WooCommerce
  minimums independently per-plugin, both at activation and on every `init`.
- `Core\Installer` is plugin-scoped: free's only creates the shared `upsellbay_offer` CPT
  (no-op schema migration — "The Pro add-on manages its own tables via its own
  installer"). Pro's installer additionally `dbDelta()`s two Pro-owned tables:
  `{$wpdb->prefix}upsellbay_pro_offer_stats_daily` and `{$wpdb->prefix}upsellbay_pro_logs`.
  Both plugins register the same CPT idempotently.
- Both `uninstall.php` files are opt-in/conservative — data is only deleted if the
  relevant settings flag is explicitly true.

## Extension pattern — worked example (Analytics/Dashboard)

1. Free's `DashboardPage::render()` renders native lifetime metrics, then fires
   `Hooks::action('dashboard_after', $request)` as the last step
   (`app/Admin/Dashboard/DashboardPage.php:83-92`).
2. Pro's own `DashboardPage` (a different object, built in Pro's own container) listens
   on the literal `upsellbay_dashboard_after` hook (`app/Admin/Dashboard/DashboardPage.php:94-100`).
   Because free's page is the only Dashboard tab registered, Pro's output is appended
   in-place inside free's page.
3. `$request` (raw `$_GET`) is passed straight through free → Pro. Pro's
   `render_analytics()` reads `$request['range']` and queries **its own** database table
   via `Data\StatsRepository`, independent of anything in free's runtime.
4. That table is populated on the write side by Pro's own `PlacementRenderer` calling
   `AnalyticsRecorder::record_view()` whenever **Pro's own** `StorefrontController`
   renders a placement (product/cart/thank-you — not free's checkout-bump path), and by
   `Plugin::record_order_offer_analytics()` hooked to native
   `woocommerce_order_status_processing`/`completed`, reading the one genuinely shared
   piece of cross-plugin data: `_ub_offer_id` and sibling `_ub_*` attribution meta that
   **both** plugins write onto order line items using byte-identical `Constants::ATTRIBUTION_*` keys.

**Pattern summary**: free fires a narrow, well-named hook at a specific rendering point
with whatever local context it already has; Pro registers a listener that is a fully
self-contained feature object backed by Pro's own services/tables, built through Pro's
own container. The two plugins never share object references — only (a) native WP/WC
hooks, (b) free's public `upsellbay_*` hooks, and (c) common WordPress-level state (the
CPT/postmeta and the `_ub_*` order-item attribution meta) couple them.
