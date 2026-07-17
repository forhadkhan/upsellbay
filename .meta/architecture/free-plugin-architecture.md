# Free Plugin Architecture (UpsellBay — Order Bump for WooCommerce)

Status: documents the free plugin as implemented on disk at
`/var/www/html/wp-content/plugins/upsellbay-order-bump-for-woocommerce`. This directory
(`.meta/architecture`) lives inside the **Pro** plugin repo; this file exists here because
Pro's architecture cannot be understood without the host it extends. See
[free-pro-coupling.md](./free-pro-coupling.md) for how the two plugins actually connect.

## What the free plugin is

A single-purpose WooCommerce order-bump plugin. It renders **one** offer at classic and
Block checkout, above the Place Order button (`readme.txt:15-33`), and owns the entire
admin surface (offer CRUD, dashboard, settings, tools) that Pro later attaches to.

Namespace root: `WPAnchorBay\UpsellBay`. Plugin slug: `upsellbay-order-bump-for-woocommerce`.
Text domain: `upsellbay-order-bump-for-woocommerce`. Admin menu slug: `upsellbay`.

## Bootstrap

`upsellbay-order-bump-for-woocommerce.php:1-149` — registers `Constants::init()`, wires
activation/deactivation hooks that run `Platform::check()` then `Installer::activate()`,
and on `plugins_loaded` calls `Plugin::instance()->init()`.

`Core\Plugin` is a singleton bootstrap coordinator:
- `register_services()` (`app/Core/Plugin.php:152-231`) builds a flat dependency graph in
  `Container`: offer schema/validator/repository/service, rule engine, discount
  calculator, cart session/validator/mutator, attribution reader/writer, placement
  renderer, storefront controller, REST routes, and the full admin tab stack.
- `register_hooks()` (`app/Core/Plugin.php:238-272`) attaches native WP/WC hooks (`init`,
  `admin_notices`, `admin_menu`, `woocommerce_before_calculate_totals`,
  `woocommerce_checkout_create_order_line_item`, `rest_api_init`) and fires the free
  plugin's own public extension hooks (full inventory in
  [free-pro-coupling.md](./free-pro-coupling.md)).

`Core\Container` (`app/Core/Container.php`) is a ~90-line lazy singleton service locator
(`set`/`has`/`get`). `Core\Hooks` is a thin facade: `Hooks::action('suffix', ...$args)` →
`do_action(Constants::hook_name('suffix'), ...$args)`, and similarly for `filter()`.
`Constants::hook_name()` prepends `HOOK_PREFIX` (`upsellbay_`).

## Domain model: the Offer

An Offer is a WordPress **custom post** of type `upsellbay_offer`
(`Constants::OFFER_POST_TYPE`, registered in `app/Core/Installer.php:99-122`;
`public=false, show_ui=false, capability_type=shop_order`). All domain fields are `_ub_*`
postmeta, defined in `Domain\Offers\OfferSchema::defaults()` (`app/Domain/Offers/OfferSchema.php:38-63`):

```
_ub_offer_type          one of 4 placement types (below)
_ub_status               active | paused | draft
_ub_offer_product_id     single int — the ONE WooCommerce product being offered
_ub_trigger_product_ids / _ub_trigger_category_ids
_ub_discount_type        none | percent | fixed_amount | fixed_price
_ub_discount_value
_ub_offer_goal, _ub_reason_label, _ub_conflict_override(+reason)
_ub_section_heading, _ub_headline, _ub_body, _ub_button_text
_ub_rules, _ub_rules_match  (all|any)
_ub_placement_config
_ub_show_image, _ub_hide_if_in_cart
_ub_start_at, _ub_end_at, _ub_priority
```

**Offer types / placements** (`OfferSchema::TYPE_*`, identical constants defined here and
mirrored in Pro): `checkout_bump`, `product_upsell`, `cart_crosssell`, `thankyou_offer`.
All four constants exist in the free schema, but free only *implements* a renderer for
`checkout_bump` — `Plugin.php:168-177` constructs `PlacementRenderer` with only
`'checkout_bump' => new ClassicCheckoutBump(...)`. The other three placement types are
schema-only in free; Pro supplies their renderers (see coupling doc).

**Discount types**: `none`, `percent`, `fixed_amount`, `fixed_price`
(`Domain\Discounts\DiscountCalculator` against the single offer product's regular price).

**Single-product constraint (foundational, and directly relevant to any future
multi-product/bundle feature)**: `_ub_offer_product_id` is a single scalar integer
everywhere — in the schema default, the validator (`OfferValidator.php`, which requires
the product to be `simple` or `variation` type only, explicitly rejecting
grouped/composite/bundle/variable-parent products — as of 2026-07-18 this guard applies
to all four placements, not just `checkout_bump`/`thankyou_offer` as it originally did;
`product_upsell`/`cart_crosssell` previously had no such guard, which was a latent
correctness bug fixed alongside a related third-party-bundle-interop investigation — see
`.meta/reviews/bundle-feature-and-release-readiness-2026-07-17.md`), the cart mutator
(`CartMutator::accept()`, quantity hardcoded to `1`), and every renderer. There is no
array-of-products, no per-item quantity, and no cross-product bundle price anywhere in
this schema.

**Targeting rules** (`Domain\Rules\RuleDefinitions`): `cart_product`, `cart_category`,
`cart_tag`, `cart_subtotal`, `viewed_product`, `user_role`, `customer_order_count`,
`customer_lifetime_spend`, `stock_status`, `exclude_if_product_in_cart`. Note: the free
readme markets user-role/order-count/lifetime-spend rules as Pro-only, but the rule
definitions and evaluator for all of these already exist and fully function in free's
`RuleEvaluator::evaluate_rule()` (`app/Domain/Rules/RuleEvaluator.php:85-95`) — any
restriction is presentational/admin-UI only, not server-side gated.

## Admin surface (free owns all of it)

`Admin/AdminPage.php` + `Admin/Navigation/{TabFactory,TabRegistry,TabRouter,TabNavigation,AdminTab}.php`
implement the single WooCommerce → UpsellBay submenu page and its tab routing.
`Admin/Offers/{OffersPage,OfferListTable,OfferEditPage}.php` implement the offer list and
the offer create/edit form. `Admin/Dashboard/DashboardPage.php`, `Admin/Settings/SettingsPage.php`,
`Admin/Tools/ToolsPage.php` implement the remaining tabs. **These are the free-owned
files that Pro used to duplicate and, as of the current working tree, no longer does** —
see [file-ownership-map.md](./file-ownership-map.md) and
[free-pro-coupling.md](./free-pro-coupling.md) for the admin-integration refactor.

`Admin/Tools/ToolsPage.php:47,64-74` and `Admin/Settings/SettingsPage.php:242,255` check
`is_plugin_active('upsellbay/upsellbay.php')` to show an "Upgrade to Pro" CTA in place of
firing the corresponding extension hook — the only server-side Pro-awareness in free, and
it is UI-only (nothing is enforced beyond hiding a CTA).

`Admin/CompatibilityNotice.php:46-53,86-105` hardcodes a coexistence check for a
competing product ("CartBay") via `is_plugin_active()` — the established precedent for
how free surfaces warnings about unrelated conflicting plugins.

## Dependency direction

Free has **no knowledge of Pro's implementation** — it does not check for Pro, require
Pro, or branch on Pro's presence except for the two upsell-CTA checks above. Free is
fully functional standalone. All coupling is Pro depending on free, never the reverse.
