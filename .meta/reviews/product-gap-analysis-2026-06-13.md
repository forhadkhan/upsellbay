# UpsellBay Product Gap Analysis

Date: 2026-06-13  
Scope: PRD v1, PRD v4, current implementation, storefront value, competitor positioning, offer governance, WooCommerce architecture.

## Executive Assessment

UpsellBay has a credible technical foundation, but it is not yet a compelling merchant or shopper product. The admin surface is relatively mature for a first implementation: one WooCommerce submenu, offer CRUD, settings sections, tools, help, onboarding, diagnostics, import/export, analytics summaries, and license handling exist. The storefront is the weak point. It renders generic offer cards in a few placements, but it does not yet guide shoppers through a useful merchandising experience or give merchants a dependable governance model for overlapping offers, discounts, and plugin conflicts.

The most important product correction is not "add more features." It is to make one narrow promise true:

> UpsellBay should be the safest WooCommerce-native way to show the single most relevant add-on, upgrade, or follow-on offer at the right buying moment without replacing checkout.

That is a stronger wedge than trying to match FunnelKit, CartFlows, or UpsellWP feature-by-feature. Competitors win on breadth. UpsellBay can win on lean, native, low-risk, measurable, context-aware offer placement.

## Source Baseline

PRD v1 framed the original product around checkout bumps, product-page upsells, cart cross-sells, thank-you offers, simple rules, and AOV analytics. Its main weakness was the assumption that post-purchase one-click charges might be part of v1 despite gateway/tokenization risk.

PRD v4 correctly tightened the product: no checkout replacement, no funnel builder, no CartBay dependency, HPOS-safe attribution, aggregate analytics, Block Checkout as a launch gate, and follow-on checkout instead of tokenized one-click post-purchase charges. It also recognized that many merchants do not know what offer to create and need native recommendation assistance.

The current implementation fulfills large parts of the architecture, but not the product value:

- Implemented: bootstrap, constants, container, settings, installer, scheduler, license client, updater, admin IA, offer CRUD, offer schema, rules, discount calculation, cart mutation, storefront renderers, REST routes, attribution, analytics, import/export, test harness, docs.
- Partially implemented: product recommendations, Block Checkout, conflict detection, diagnostics, dashboard analytics, frontend interactions.
- Missing or too shallow: storefront merchandising strategy, mini-cart/side-cart surfaces, order details/account offers, placement-specific UX, meaningful conflict resolution, offer stacking policy, duplicate-offer governance, merchant warnings, shopper-facing clarity, E2E proof, and commercially useful analytics loops.

## Competitive Review

### FunnelKit

FunnelKit positions order bumps as frictionless checkout add-ons with discounts, skins, multiple bumps, placement control, conditional rules, analytics, and A/B testing. It also sells a broader funnel/automation system. Its value to merchants is optimization depth; its value to shoppers is quick add-ons that can be accepted during checkout without redirects. Source: https://funnelkit.com/woocommerce-order-bump/

Pattern to copy: checkout placement control, relevant low-cost add-ons, analytics by offer, clear one-click accept flow.

Pattern to avoid: becoming a funnel/automation suite.

UpsellBay differentiation: one native Woo offer layer that does not require funnel pages, checkout replacement, or broader CRM automations.

### CartFlows

CartFlows bundles funnels, checkout layouts, order bumps, one-click upsells/downsells, A/B testing, cart/recovery, and coupons. Current suite pricing is materially above a focused plugin. Source: https://cartflows.com/features/order-bumps/

Pattern to copy: dynamic offers, multiple bump ordering, A/B-test mindset, in-cart recommendations.

Pattern to avoid: checkout replacement and suite lock-in.

UpsellBay differentiation: "preserve your current checkout" should be the product's strongest promise.

### WooCommerce Product Recommendations

Woo's official Product Recommendations extension is broad and recommendation-oriented: it supports upsells, cross-sells, frequently bought together, strategic locations across product/cart/checkout/thank-you/order-pay surfaces, filters, amplifiers, visibility conditions, and analytics. It also explicitly notes block-theme limitations on its marketplace page. Source: https://woocommerce.com/products/product-recommendations/

Pattern to copy: engines/recipes, context-aware recommendations, location-level analytics, "complete the look" and threshold helper strategies.

Pattern to avoid: building a complex recommendation platform in v1.

UpsellBay differentiation: editorial offer control plus lightweight recommendations, not machine-learning breadth.

### Order Bump for WooCommerce

This Woo Marketplace plugin focuses on last-minute checkout offers. It supports single or multiple order bumps, priority/display count, quantity controls, checkout or product-page acceptance, criteria by product/category/subtotal/order/user/purchase history/date, and upsell funnels. Source: https://woocommerce.com/products/order-bump-for-woocommerce/

Pattern to copy: explicit display count, priority, quantity control, and predictable multiple-bump rules.

UpsellBay differentiation: broader journey coverage plus stricter Woo-native architecture and better conflict governance.

### UpsellWP

UpsellWP is the closest broad direct competitor. It covers checkout upsells, cart upsells, product add-ons, frequently bought together, post-purchase upsells, thank-you upsells, next-order coupons, popups, double-the-order offers, smart product recommendations, scheduled campaigns, A/B testing, and analytics. Current public pricing starts at $75/year. Sources: https://upsellwp.com/ and https://upsellwp.com/pricing/

Pattern to copy: all journey surfaces, offer templates, next-order coupons, scheduling, and simple setup.

Pattern to avoid: too many campaign types for v1; popups are outside the lean/native wedge.

UpsellBay differentiation: fewer offer types, better Woo compatibility, better conflict prevention, and a calmer admin.

### YayCommerce / Dynamic Pricing Plugins

YayPricing focuses on automatic discounts, BOGO, pricing rules, smart coupons, cart notices, priority modes, discount-combination controls, and notices that guide customers toward rewards. Source: https://yaycommerce.com/yaypricing-woocommerce-dynamic-pricing-and-discounts/

Pattern to copy: explicit conflict policies such as whether discounts combine with coupons/sale prices, priority mode, and "next best action" cart notices.

Pattern to avoid: becoming a full dynamic pricing engine.

UpsellBay differentiation: offer placement and attribution, not storewide pricing rules.

### Shopify-Focused Post-Purchase Tools

ReConvert, Zipify OneClickUpsell, Bold Upsell, and ShopBrain are useful category references even when not Woo-native. They normalize the idea that post-purchase, thank-you, and account/customer-lifecycle surfaces can drive value, but they also show why UpsellBay should not promise tokenized one-click post-purchase charges in v1. WooCommerce gateway fragmentation makes follow-on checkout the safer default.

Pattern to copy: post-purchase timing, relevance, and analytics.

Pattern to avoid: platform assumptions that depend on Shopify checkout/payment architecture.

## Current Implementation Assessment

### Admin

Strengths:

- WooCommerce -> UpsellBay IA is aligned with v4.
- Offers, Settings, Tools, Help, Dashboard, and hidden Setup are in place.
- Offer schema supports placement, status, product, triggers, rules, discount, headline/body/button, image toggle, schedule, and priority.
- Settings include enable/test mode, placement toggles, style tokens, retention, license state.
- Offer CRUD goes through service/repository boundaries.
- Product search and recommendation primitives exist.

Gaps:

- Offer editor is still form-heavy and does not actively prevent bad merchant decisions.
- No conflict dashboard explains overlapping offers, duplicate target products, discount stacking, or unsafe product types.
- No per-placement preview that shows the offer in a realistic product/cart/checkout/thank-you context.
- No guided "choose the best offer for this product/cart" workflow.
- Analytics are aggregate, but not yet decision-making: merchants need "disable this", "move this to cart", "lower discount", "conflicts with X" recommendations.

### Storefront

Strengths:

- Four placements exist: product page, cart, classic checkout, thank-you.
- Rendering is escaped and theme-friendly.
- Checkout bump uses a checkbox/toggle.
- Cart/product/thank-you offers use a button and REST mutation.
- Session token, rate limit, server-side product/discount validation, and attribution are present.
- Product availability and rule checks are server-side.

Gaps:

- Offer cards are generic and placement-insensitive.
- Product page offer is rendered after add-to-cart form, but it is not a true "complete the set" selector or bundle experience.
- Cart offer is limited to cart collaterals; no mini-cart/side-cart support.
- Checkout is one classic hook plus a block asset that imports classic behavior; Block Checkout claim needs real proof.
- Thank-you offer simply adds to cart and redirects to checkout/cart. It does not explain that this starts a separate follow-on checkout.
- There is no order details page or account-area follow-on recommendation.
- No shopper dismiss controls are visible in current shared markup, despite a dismiss REST endpoint.
- No quantity choice, variation choice, "replace with upgrade", "add all selected", threshold progress, or "already in cart" explanation.
- No customer-facing conflict messaging beyond generic REST errors.
- Mobile/accessibility behavior is not proven end-to-end.

## PRD Gaps vs Implementation

### v1 Gaps

- Checkout bump exists, but not proven by E2E and shopper UX is basic.
- Product page upsell exists, but not as a useful frequently-bought-together or upgrade module.
- Cart cross-sell exists, but not mini-cart/side-cart and not threshold helper.
- Thank-you offer exists, but v1's one-click/post-purchase concept was intentionally replaced by safer follow-on checkout in v4.
- AOV dashboard exists in aggregate form, but not yet actionable enough.

### v4 Gaps

- Block Checkout is not product-ready until E2E proof exists.
- Conflict detection is broad plugin-warning level, not offer-level or discount-level governance.
- Offer recommendations exist as a service primitive, not as a strong workflow.
- The storefront does not yet satisfy "native, measurable, low-friction offers" across the journey.
- Subscription/bundle/composite handling is defensive but not merchant-explainable.
- Import/export exists, but no launch workflow for agencies to map imported products cleanly.
- Public docs and compatibility claims need to be aligned with actual proof.

## Storefront Redesign Recommendations

### 1. Product Page: "Complete This Product" Module

Why it matters: Product pages are evaluation moments. The shopper is still deciding. The right offer should clarify compatibility or upgrade value, not feel like an ad.

Competitor pattern: Woo Product Recommendations supports context-aware product/category recommendations; UpsellWP has product add-ons and frequently bought together.

Recommended behavior:

- Render a compact "Complete this product" block near the add-to-cart area, not a generic offer card.
- Support one primary add-on plus optional secondary add-ons later.
- Show compatibility text: "Works with [current product]" or "Recommended add-on."
- Offer one-click "Add with product" when possible, but preserve Woo variation/product state.
- For variable products, wait until a purchasable variation is selected before enabling the offer.
- If the offered product is already in cart, replace CTA with "Already in cart" and hide discount noise.

Woo architecture:

- Classic product pages: `woocommerce_after_add_to_cart_form` or a merchant-selectable hook near `woocommerce_before_add_to_cart_button`.
- Use `wc_get_product()`, product variation APIs, and cart APIs only.
- Store offer acceptance through cart item data with `_ub_` attribution.

### 2. Cart Page: "Still Missing?" Cross-Sell + Threshold Helper

Why it matters: Cart is a review moment. Useful offers are add-ons, refills, protection, gift wrap, or threshold helpers.

Competitor pattern: CartFlows and UpsellWP both surface in-cart recommendations. YayPricing uses cart notices to show progress toward discounts/free shipping.

Recommended behavior:

- Render up to three eligible cart offers only when they are clearly complementary.
- Add a threshold-helper offer type: "Add $X more to unlock free shipping / gift / discount" using Woo shipping/free-shipping settings where available.
- Show reason labels: "Pairs with [cart item]", "Unlocks free shipping", "Popular add-on."
- Add visible dismiss per offer and persist dismissal per session.
- Refresh cart fragments or redirect only when Woo cannot update in place.

Woo architecture:

- Cart page: `woocommerce_cart_collaterals` or `woocommerce_after_cart_table`.
- Mini-cart/side-cart: use Woo fragments for classic mini-cart and a separate compatibility plan for block mini-cart.
- Do not create public coupons for checkout bumps; if threshold helper uses coupons later, use `WC_Coupon` only in a clearly scoped next-order or promo feature.

### 3. Mini Cart / Side Cart: Add-On Tray

Why it matters: Many themes and plugins move cart review into mini-cart/side-cart. UpsellBay misses a major buying moment if it only renders on the full cart page.

Competitor pattern: FunnelKit and CartFlows emphasize mini-cart/sliding-cart upsells.

Recommended behavior:

- Add a lightweight mini-cart add-on tray with at most one or two offers.
- Keep it quiet: small product image, name, price, "Add" button, and reason.
- Avoid modals/popups in v1.
- Detect known side-cart plugins and either integrate through documented hooks or suppress with admin warning.

Woo architecture:

- Classic mini-cart: use `woocommerce_widget_shopping_cart_before_buttons` or `woocommerce_widget_shopping_cart_after_buttons` where theme-compatible.
- Fragment refresh after add.
- Block mini-cart requires separate Woo Blocks compatibility research and should not be claimed until proven.

### 4. Checkout: One Relevant Bump, Not a Catalog

Why it matters: Checkout is high-intent and high-risk. Extra choice can reduce conversion.

Competitor pattern: FunnelKit and Order Bump plugins support multiple bumps, but they also expose controls for location, count, and priority.

Recommended behavior:

- Default to one highest-priority checkout bump.
- Permit multiple checkout bumps only behind a setting with a max display count and clear warning.
- Place the bump above payment or before place-order, merchant-selectable per classic checkout.
- Copy should be short: add-on name, one sentence, price/savings, checkbox.
- Never obscure payment methods or place-order.
- If product is subscription, variable without selected variation, out of stock, already in cart, or discount-conflicted, do not render.

Woo architecture:

- Classic: `woocommerce_review_order_before_submit` as default; optional additional supported hooks.
- Blocks: real Slot/Fill or supported Checkout Block extension point, Store API-compatible cart mutation, dependency extraction, E2E proof.
- Cart mutation must remain server-side.

### 5. Thank-You Page: Follow-On Checkout, Explicitly

Why it matters: The shopper already paid. The UX must not imply the original order will be silently changed.

Competitor pattern: UpsellWP and funnel tools use post-purchase offers. Shopify tools normalize one-click post-purchase, but Woo gateway reality is different.

Recommended behavior:

- Label this as "Add a follow-on item" or "Add this to a new checkout."
- Explain one sentence: "Your original order is complete. This starts a separate checkout linked to this order."
- Offer low-friction items: refill, accessory, gift card, next-order coupon, subscription sign-up later.
- Include "No thanks" dismiss.
- Link source order ID through cart item data and final attribution.

Woo architecture:

- `woocommerce_thankyou` render.
- Add offered product to cart with `_ub_source_order_id`, redirect to checkout.
- Never mutate the original paid order in v1.

### 6. Order Details / Customer Account: Replenishment and Repeat Add-On

Why it matters: This can be useful without interrupting checkout. It is lower pressure and can drive repeat purchases.

Recommended behavior:

- Add a new P1 placement: `account_order_offer`.
- On order details, show one follow-on recommendation related to purchased products.
- Do not use discounts by default; focus on reorder/refill/accessory.
- Do not show if order is failed/cancelled/refunded.

Woo architecture:

- `woocommerce_order_details_after_order_table` for classic account order details.
- Use `wc_get_order()` and order item CRUD getters.
- Add to cart as a normal cart item with source order attribution.

## Offer Lifecycle Architecture

### Offer Statuses

- `draft`: never shopper-visible; can be saved with incomplete/conflicting targeting.
- `active`: eligible for rendering if validation, schedule, rules, product state, settings, and conflicts pass.
- `paused`: not eligible; retained for history and duplication.
- Recommended addition: `archived` for inactive historical offers that should not clutter active governance but preserve analytics.

### Evaluation Order

1. Global plugin enabled and placement enabled.
2. Test mode/admin preview visibility.
3. Offer status is active.
4. Offer schedule window is valid.
5. Placement matches.
6. Offered product exists, visible, purchasable, in stock, supported type.
7. Offered product is not already in cart unless strategy explicitly allows quantity increase.
8. Trigger product/category/tag/context matches.
9. Rules match.
10. Conflict rules pass.
11. Dismissal/session state pass.
12. Priority and max display count choose final offers.
13. Render and record view.

### Multiple Offers on Same Product

Rules:

- Multiple draft offers may target the same offered product.
- Paused offers do not block new active offers, but should be shown in conflict history.
- Multiple active offers may target the same offered product only if they have different placements or non-overlapping schedules/rules.
- Multiple active offers in the same placement for the same offered product should warn by default.
- Checkout should default to one displayed bump; cart may show up to three; product page and thank-you should default to one.

Conflict detection:

- Same offered product + same placement + overlapping schedule = conflict warning.
- Same trigger product/category + same placement + same offered product = duplicate warning.
- Same offered product with different discounts active at same time = pricing conflict warning.
- Offer product also in trigger product list = self-offer error unless explicitly allowed for quantity/double-order strategy.
- Offer product already in cart = suppress by default.

Auto-resolution:

- Pause older overlapping offer.
- Lower priority of existing offer.
- Change placement.
- Restrict schedule.
- Convert to draft.

Manual override:

- Allow merchant to keep overlap only with an explicit checkbox: "Allow this offer to compete with existing offers."
- Store an override reason in offer meta for auditability.

### Priority System

Current implementation sorts lower `_ub_priority` first. Keep that if already exposed, but make the UI explicit:

- Priority 1 is highest.
- Ties sort by most specific targeting, then newest or manual order.
- Add placement-level max display count.
- Add a "why this offer wins" diagnostic in the offer list.

## External Conflict Architecture

### Native Woo Sales

Expected behavior: Offers may discount products already on sale only if merchant allows it.

Detection: `WC_Product::is_on_sale()`.

Merchant warning: "This offer discounts a product that is already on sale."

Default: do not stack percentage/fixed discounts on sale items unless enabled.

### Woo Coupons

Expected behavior: Checkout bump cart-item discounts should not become public coupons.

Detection: `WC()->cart->get_applied_coupons()`.

Default: allow normal store coupons on non-offer items; prevent double-discounting the offered item where possible.

Merchant option: "Do not show this offer when any coupon is applied" and "Do not show when specific coupon is applied."

### Dynamic Pricing / Discount Plugins

Expected behavior: Avoid unpredictable stacked prices.

Detection: known plugin active checks plus product/cart price delta after totals calculation.

Default: if a dynamic pricing plugin is detected, warn in admin and require merchant test mode validation. Do not hard fail storefront.

Customer behavior: show final server-calculated price only.

### Bundles and Composites

Expected behavior: v1 does not offer bundle/composite products unless tested.

Detection: product types such as `bundle`, `composite`, `grouped`, plugin classes.

Default: suppress unsupported offer products and show admin warnings.

### Subscriptions

Expected behavior: no recurring discount leakage.

Detection: `wcs_is_subscription()` and product type checks.

Default: block discounted subscription offer products in v1. Allow non-discounted subscription suggestions only after explicit QA.

### Funnel / Checkout Replacement Plugins

Expected behavior: warn, suppress unsafe checkout injection where needed, keep product/cart/thank-you where safe.

Detection: known plugins such as CartFlows, FunnelKit, CheckoutWC, custom checkout templates where detectable.

Default: admin compatibility warning with placement-specific risk, not a global failure.

### Other Upsell Plugins

Expected behavior: avoid duplicate offer spam.

Detection: plugin active list and known frontend hooks where practical.

Default: warn if other upsell/order-bump plugins are active and suggest disabling overlapping placements.

## Merchant Workflow Improvements

1. Add "Offer Health" to the Offers list:
   - Active and eligible
   - Product unavailable
   - Conflicts with offer X
   - Blocked by schedule
   - Disabled placement
   - Test-mode only

2. Add a guided "Create Useful Offer" flow:
   - Choose goal: add accessory, upgrade, protect order, free-shipping helper, follow-on purchase.
   - Choose placement based on goal.
   - Suggest products from Woo upsells/cross-sells, same category, low-priced accessories, and accepted history.
   - Preview before activation.

3. Add realistic previews:
   - Product context preview.
   - Cart context preview.
   - Classic checkout preview.
   - Thank-you follow-on preview.

4. Add conflict warnings at save time:
   - Same product/placement overlap.
   - Unsupported product type.
   - Already discounted/sale product.
   - Dynamic pricing plugin active.
   - Existing checkout replacement plugin risk.

5. Add placement recommendations:
   - Product page for compatibility/add-on clarity.
   - Cart for threshold helpers and small accessories.
   - Checkout for one low-cost impulse add-on.
   - Thank-you for follow-on/replenishment.

## WooCommerce Compliance Review

Keep:

- WooCommerce CRUD for orders/order items.
- `wc_get_product()` and product methods.
- Cart APIs for add/remove.
- Session-scoped cart item data for offer attribution/discounts.
- Aggregate stats table without PII.
- HPOS declaration through `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` on `before_woocommerce_init`.

Correct:

- Do not declare `cart_checkout_blocks` until real Block Checkout E2E proof exists.
- Block Checkout should use documented Cart/Checkout Blocks extension APIs and Store API-compatible mutation, not imported classic checkout DOM assumptions.
- Add Store API-aware validation for block checkout flows.
- Add HPOS enabled/disabled integration proof before release claims.

Recommended APIs/hooks:

- Product page: `woocommerce_after_add_to_cart_form`, optional `woocommerce_before_add_to_cart_button`.
- Cart page: `woocommerce_after_cart_table`, `woocommerce_cart_collaterals`.
- Mini cart: `woocommerce_widget_shopping_cart_before_buttons` or `woocommerce_widget_shopping_cart_after_buttons` for classic mini-cart only.
- Classic checkout: `woocommerce_review_order_before_submit` default.
- Thank-you: `woocommerce_thankyou`.
- Account order details: `woocommerce_order_details_after_order_table`.
- Cart mutation: `WC()->cart->add_to_cart()`, `remove_cart_item()`, totals refresh.
- Order attribution: `woocommerce_checkout_create_order_line_item`, `$item->add_meta_data()`, `$order->update_meta_data()`, `$order->save()`.

## Edge Cases

- Offer product out of stock after render: REST add returns safe message and card should disable on failure.
- Offered product already in cart: suppress or show "Already in cart"; do not add duplicate unless quantity strategy allows it.
- Variable product without selected variation: product-page offer should wait for valid variation or link to product page.
- Cart emptied after render: REST should reject via rule/context validation.
- Coupon applied after offer render: recalculate eligibility and price server-side.
- Dynamic pricing changes offer product price: use final server price and log diagnostic.
- Subscription product with discount: block by default.
- Bundle/composite product: suppress until compatibility tested.
- Thank-you source order refunded/cancelled: suppress account/order-detail follow-on offers.
- Guest shopper session lost: show normal product/cart fallback, not broken REST.
- Dismissed offer: persist per session and placement.
- Multiple tabs: duplicate accept should return existing cart item, not add duplicates.
- Multi-currency: show/store current Woo price but do not overclaim reporting by currency until designed.
- Accessibility: checkbox labels, button disabled state, focus after add, screen-reader notices, no color-only savings indication.
- Mobile: cards must not shift checkout controls or create horizontal scroll.

## Prioritized Roadmap

### P0: Make One Storefront Flow Valuable

Merchant value: high. Shopper value: high. Conversion impact: high. Complexity: medium.

- Redesign checkout bump as one clear, relevant, low-cost offer.
- Add visible dismiss and success/error states.
- Add "already in cart" and unsupported-product suppression.
- Add per-placement preview.
- Remove or gate Block Checkout compatibility claim until E2E proof exists.

### P1: Offer Governance

Merchant value: very high. Shopper value: medium. Conversion impact: high. Complexity: medium.

- Add offer conflict detector service.
- Add save-time warnings and offer-list health statuses.
- Add priority/max-display controls per placement.
- Add duplicate/same-product overlap rules.
- Add automatic resolution actions.

### P2: Product Page and Cart Value

Merchant value: high. Shopper value: high. Conversion impact: medium-high. Complexity: medium.

- Product page "Complete this product" module.
- Cart "Still missing?" module.
- Threshold helper offer strategy.
- Placement-specific reason labels.

### P3: Mini Cart

Merchant value: high on modern stores. Shopper value: medium. Conversion impact: medium-high. Complexity: high due to theme/plugin variability.

- Classic mini-cart add-on tray.
- Side-cart compatibility detection.
- Suppress or warn for unsupported side carts.

### P4: Thank-You Follow-On Clarity

Merchant value: medium-high. Shopper value: medium. Conversion impact: medium. Complexity: medium.

- Explicit separate-checkout copy.
- Follow-on source order attribution.
- No-thanks dismissal.
- Low-friction redirect to checkout.

### P5: Recommendation Assistant as Workflow

Merchant value: high. Shopper value: high if relevance improves. Conversion impact: medium. Complexity: medium-high.

- Use existing recommendation assistant in the offer editor.
- Rank candidates by Woo upsells/cross-sells, category affinity, low price, stock, margin proxy, and accepted history.
- Explain why each candidate is suggested.

### P6: Account / Order Details Offers

Merchant value: medium. Shopper value: medium. Conversion impact: medium. Complexity: medium.

- Add `account_order_offer` placement.
- Render replenishment/accessory follow-on offer on order details.
- Use order CRUD and source order attribution.

### P7: Advanced Testing and Optimization

Merchant value: high. Shopper value: indirect. Conversion impact: high. Complexity: high.

- Real E2E for classic checkout, Block Checkout, product, cart, mini-cart, thank-you, order details.
- Accessibility and mobile screenshots.
- Offer analytics by placement and reason.
- Later: A/B testing only after baseline flows are useful.

## Recommended Product Positioning

Do not position UpsellBay as "all-in-one upsells." UpsellWP already owns that shape at a low price, and funnel suites own the high-end breadth.

Position it as:

> Native WooCommerce offer governance for merchants who want higher AOV without checkout replacement, popup clutter, or discount chaos.

The commercial differentiator should be:

- Safer than funnel builders.
- More useful than single bump plugins.
- Leaner than all-in-one upsell suites.
- More transparent than generic recommendation engines.
- Better governed than discount plugins stacked on top of each other.

## Launch Readiness Verdict

Not launch-ready as a paid premium plugin.

The architecture is promising. The admin is workable. The storefront value is not yet strong enough. The next milestone should not be another broad feature phase; it should be a storefront and governance hardening phase focused on one high-confidence shopper journey: product/cart context -> one relevant checkout bump -> clean cart mutation -> accurate attribution -> merchant can see whether it worked.

