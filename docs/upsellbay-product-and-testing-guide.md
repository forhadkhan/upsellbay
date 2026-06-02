# UpsellBay Product And Testing Guide

This guide explains UpsellBay from the point of view of someone who has never seen the product before. It is based on the v1 PRD, the final v4 PRD, the architecture notes, and the current runtime implementation.

## Short Version

UpsellBay is a WooCommerce plugin for increasing average order value. It lets a merchant define relevant add-on offers and show them inside the normal WooCommerce buying journey:

- Product page offers, such as a matching accessory or frequently-bought-together item.
- Cart offers, such as complementary products or threshold helper items.
- Checkout bumps, such as a low-friction checkbox add-on before the order is placed.
- Thank-you follow-on offers, where the original order stays complete and the shopper starts a separate follow-on checkout.

The product is intentionally WooCommerce-native. It should preserve the store's existing checkout, use WooCommerce product/cart/order APIs, look like part of WooCommerce, and report offer performance without storing customer PII in analytics.

## What It Is Not

UpsellBay is not a funnel builder, checkout replacement, abandoned cart recovery tool, popup lead-capture tool, CRM automation suite, or CartBay module.

The v1 PRD introduced the core AOV idea: checkout bumps, product upsells, cart cross-sells, thank-you offers, rules, and a revenue dashboard. The v4 PRD keeps those ideas but tightens the product boundary:

- No checkout replacement.
- No gateway-tokenized one-click post-purchase charge in v1.
- No recovery emails, recovery sessions, restore links, unsubscribe flows, SMS, WhatsApp, or abandoned-cart automation.
- No dependency on CartBay and no shared CartBay state.
- No Block Checkout compatibility claim until E2E proof exists.

## Who Uses It

The main admin user is a WooCommerce merchant or agency operator who wants to make each order more valuable without rebuilding checkout. A typical first use case is:

1. Pick a product to offer, such as a warranty, accessory, sample, gift wrap, or replenishment item.
2. Choose where the offer appears.
3. Add simple targeting, such as cart subtotal, cart product, viewed product, category, tag, user role, order count, lifetime spend, stock status, or exclude-if-product-in-cart.
4. Optionally apply a server-calculated discount.
5. Preview in test mode.
6. Publish and watch views, accepts, dismissals, attributed revenue, discount total, orders, accept rate, and revenue per attributed order.

## How It Works

### Runtime Shape

UpsellBay has a thin entry file, `upsellbay.php`, which loads the plugin, checks platform requirements, registers activation/deactivation hooks, declares HPOS compatibility, and starts `Core\Plugin`.

`Core\Plugin` wires the services:

- `Core\Constants` centralizes identifiers such as `upsellbay`, `upsellbay/v1`, `upsellbay_settings`, `_ub_`, and `upsellbay_offer`.
- `Core\Installer` registers the private offer CPT, seeds settings, creates the aggregate stats table, and preserves data by default on uninstall.
- `Data\OfferRepository` stores offer definitions in the private `upsellbay_offer` CPT with `_ub_` meta.
- `Domain\Offers\OfferService`, `OfferValidator`, `OfferSchema`, and `OfferDefaults` own offer lifecycle and validation.
- `Domain\Rules\RuleEvaluator` decides whether an offer is eligible in the current cart/product/customer context.
- `Domain\Storefront\StorefrontController` connects offers to WooCommerce product, cart, checkout, and thank-you hooks.
- `Domain\Cart\CartMutator`, `CartValidator`, `DiscountCalculator`, and `DiscountApplier` add/remove accepted offer products and apply trusted server-side prices.
- `Domain\Attribution\AttributionWriter` writes `_ub_` attribution using WooCommerce order and order-item object methods.
- `Data\StatsRepository` stores aggregate non-PII daily stats in `{$wpdb->prefix}upsellbay_offer_stats_daily`.
- `Api\Routes\PublicOfferRoutes` handles shopper interactions through `/bump-toggle`, `/cart-offer-add`, and `/dismiss`.
- `Admin\*` classes render the WooCommerce admin surface.

### Admin Experience

The current admin entry is `WooCommerce -> UpsellBay`. It uses one WooCommerce submenu and internal tabs:

- Dashboard: default landing tab with operational status and aggregate analytics.
- Offers: list table and empty state for offer management.
- Add/Edit Offer: internal Offers action for offer fields.
- Settings: enable offers, test mode, placement toggles, style tokens, data retention, cleanup, and license.
- Tools: diagnostics and import JSON validation UI.
- Setup/Get started: first-run wizard UI.

Dashboard analytics are part of the Dashboard tab. There is no separate Analytics tab in the current v1 admin IA.

### Shopper Experience

When an active eligible offer exists, the current storefront hooks can render:

- One checkout bump at `woocommerce_review_order_before_submit`.
- One product-page offer after the add-to-cart form.
- Up to three cart cross-sell offers in cart collaterals.
- One thank-you follow-on offer on the order-received page.

The current offer card markup is simple and Woo-native: a headline, optional body, and either a checkout checkbox or an add-offer button. Checkout bump changes call `/wp-json/upsellbay/v1/bump-toggle`; cart/product/thank-you buttons call `/wp-json/upsellbay/v1/cart-offer-add`.

### Analytics And Attribution

Views are recorded when the placement renderer renders an eligible offer. Accepts and dismissals are recorded through public REST interactions. Dashboard reads aggregate stats; it should not scan live orders during normal dashboard load.

Accepted cart items carry `_ub_` attribution data. Order attribution is designed to use WooCommerce CRUD object methods so the implementation stays HPOS-compatible.

## Current Implementation Snapshot

The current codebase implements the foundation, admin IA, offer schema/repository, rules, storefront rendering, public offer routes, analytics aggregation, attribution helpers, licensing UI, compatibility warnings, import/export validation helpers, and compiled frontend entry points.

Some areas are intentionally still launch-gated or incomplete:

- Block Checkout code exists, but Blocks compatibility is not claimed until E2E proof passes.
- The architecture index still contains old Phase 0 wording saying no runtime architecture exists; the current implementation is ahead of that index.
- Settings save is wired, but the current storefront controller does not visibly consume the global enabled flag, placement toggles, or test mode before rendering active eligible offers.
- The offer editor and setup wizard expose forms and service methods, but code review did not find a posted-form handler wired into those render paths. In this snapshot, no-code admin offer creation may not persist through the rendered form until that wiring is completed.
- The storefront JavaScript sends REST requests and updates checkout totals, but it does not currently show a shopper-facing error notice if a REST call fails.
- Tools shows import JSON validation UI, but full admin import execution is still a planned route/surface.

Use those points as testing expectations, not product promises.

## Admin Test Plan

### Prerequisites

Use a local or staging WooCommerce store with:

- WordPress and WooCommerce versions compatible with the plugin header and PRD baseline.
- WooCommerce active.
- At least two simple, purchasable, in-stock products.
- Classic checkout available for the first checkout bump test.
- A manager/admin account with `manage_woocommerce`.

Do not use a production store for first-pass testing.

### Activation

1. Install and activate UpsellBay.
2. Open `WooCommerce -> UpsellBay`.
3. Confirm the admin page loads under WooCommerce, not as a top-level WordPress menu.
4. Confirm the default tab is Dashboard.

Expected result:

- No activation fatal.
- The Dashboard shows Offers enabled, Test mode, Active offers, Recent revenue, and Performance metrics.
- The WordPress admin menu has only `WooCommerce -> UpsellBay` for this plugin.
- HPOS compatibility is declared by architecture, but final HPOS QA evidence still has to be run before release claims.

### Settings

1. Open `WooCommerce -> UpsellBay -> Settings`.
2. Toggle Enable offers and Test mode.
3. Change placement checkboxes.
4. Change the accent color or button style.
5. Change retention day values.
6. Save.

Expected result:

- A Woo-style success or error notice appears.
- Settings persist in `upsellbay_settings`.
- License key fields mask existing keys and validate the expected `WPAB-...-...` format.
- Data cleanup stays off unless deliberately enabled.

Current implementation watch item:

- After saving global enablement or placement toggles, verify whether storefront rendering actually changes. In this snapshot, those settings are normalized and shown in admin, but the storefront renderer does not appear to enforce them yet.

### Setup And Offers

1. Open the Setup/Get started tab.
2. Confirm the wizard asks for offer product, placement, headline, and preview/test-mode preference.
3. Open the Offers tab.
4. Confirm the empty state offers Create offer and Open setup wizard actions.
5. Open Add offer.
6. Confirm sections exist for basics, targeting, discount, placement, schedule, and advanced metadata.

Expected result:

- The admin surfaces render in a Woo-native style.
- The offer editor exposes placement options for checkout bump, product page offer, cart offer, and thank-you follow-on offer.
- Required fields include offer name, offer product ID, headline, rule matching, discount type/value, display options, schedule, and priority.

Current implementation watch item:

- The service layer can create offers, and tests cover wizard/offer service behavior. However, the rendered wizard and offer editor forms may not persist submissions in this snapshot because posted-form handling is not visibly wired into those render paths.

### Dashboard Analytics

1. With no stats, open Dashboard.
2. Trigger a rendered offer in a seeded test environment.
3. Return to Dashboard.

Expected result:

- With no activity, metrics show zero-like values.
- After offer views/accepts are recorded, Dashboard reflects aggregate stats from the last 30 days.
- Analytics remain aggregate and non-PII.

### Tools

1. Open Tools.
2. Confirm diagnostics show plugin, version, license status, masked license, and test mode.
3. Inspect the Import offers JSON textarea.

Expected result:

- Diagnostics should never expose full license keys or PII.
- Import validation helpers should reject invalid JSON when called through the service layer.
- Full import execution should not be treated as complete unless the admin route/import workflow is wired and tested.

Current implementation watch item:

- The Tools page renders an import textarea, but code review did not find a posted-form handler for that textarea in this snapshot.

## End-User Test Plan

These steps describe what a shopper should experience once a valid active offer is available in the store. If the admin UI cannot yet create a persisted active offer in your current build, seed one through the service layer or test fixture before running the shopper flow.

### Product Page Offer

1. Create or seed an active `product_upsell` offer for an in-stock product.
2. Visit the target product page as a shopper.
3. Look below the add-to-cart form.
4. Click the offer button.

Expected result:

- A native-looking UpsellBay offer appears only when the offer is eligible.
- Clicking the button calls `/wp-json/upsellbay/v1/cart-offer-add`.
- The offered product is added to the cart with server-controlled offer metadata.
- If the product is out of stock or not purchasable, the offer should not render.

Current implementation watch item:

- The JavaScript does not currently render a visible error notice on REST failure, so use browser dev tools or WooCommerce cart state to confirm the outcome.

### Cart Offer

1. Add the trigger product to cart.
2. Open the cart page.
3. Look in the cart collaterals area.
4. Accept one cart offer.

Expected result:

- Up to three eligible cart offers can render.
- Accepted offer products are added to the cart.
- Duplicate accepts should not add duplicate accepted items for the same offer.
- Views and accepts should increment aggregate stats.

### Classic Checkout Bump

1. Use classic shortcode checkout.
2. Add a trigger product to cart.
3. Proceed to checkout.
4. Look near the final order review area before submit.
5. Check the UpsellBay bump checkbox.
6. Uncheck it again.

Expected result:

- One highest-priority eligible checkout bump appears.
- Checking the box calls `/wp-json/upsellbay/v1/bump-toggle`.
- WooCommerce checkout totals recalculate after the request.
- Unchecking removes the accepted offer item.
- Shopper-sent prices are ignored; the server uses product price and configured discount.
- Payment fields and Place order remain visible and usable.

### Thank-You Follow-On Offer

1. Complete a normal checkout.
2. On the order-received page, look for the follow-on offer.
3. Accept the offer.

Expected result:

- The original order is not mutated as a one-click charge.
- The shopper should be sent into a separate follow-on cart/checkout path linked to the source order.
- Follow-on attribution should use `_ub_source_order_id`, `_ub_source_offer_id`, and `_ub_follow_on_order`.

Current implementation watch item:

- The renderer and cart-add route exist, but the complete follow-on checkout journey should be manually verified before treating this as production-ready.

### Block Checkout

1. Enable a Block Checkout test page.
2. Enable WooPayments or another required test gateway.
3. Run render, accept, unaccept, totals, submit, attribution, keyboard, and mobile checks.

Expected result:

- Do not claim support unless E2E passes.
- Current code can enqueue a Block Checkout bundle and register an additional checkout field when the API exists, but public compatibility remains blocked until proof exists.

## What Good Looks Like

A successful release-quality test should show:

- The merchant can create and preview a first offer in under 15 minutes.
- Offers appear only in the intended placement and only when eligible.
- Checkout totals are correct before and after accepting/removing a bump.
- Out-of-stock or invalid offer products do not render.
- Accepted offer items receive `_ub_` attribution.
- Dashboard analytics show views, accepts, dismissals, orders, revenue, discount total, accept rate, and revenue per attributed order from aggregate stats.
- License outages do not disable live offers.
- CartBay can be active or inactive without changing UpsellBay state, routes, jobs, settings, or recovery behavior.
- No Block Checkout, QIT, HPOS, gateway, accessibility, or marketplace claim is made without current evidence.

## Release Readiness Notes

Before treating UpsellBay as ready for merchants or marketplace submission, verify:

- `composer test`
- `composer phpcs`
- `composer phpstan`
- `bun run build`
- `bun run i18n:make-pot`
- `composer plugin-check`, when the local WordPress/WP-CLI Plugin Check environment supports it
- Classic checkout E2E
- Block Checkout E2E
- HPOS enabled and disabled tests
- WooPayments, Stripe, PayPal Payments, and Subscriptions compatibility checks
- Mobile and keyboard/screen-reader checks for each storefront placement
- Product isolation scan confirming no CartBay runtime coupling

The safest mental model is: UpsellBay is the AOV offer layer for the active WooCommerce buying journey. It should help shoppers add relevant value before or just after purchase, while leaving checkout ownership, recovery workflows, customer data, and CartBay state alone.
