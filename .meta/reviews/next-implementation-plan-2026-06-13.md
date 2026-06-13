# UpsellBay Storefront Value & Governance Implementation Plan

Date: 2026-06-13  
Source: `.meta/reviews/product-gap-analysis-2026-06-13.md`

## Summary

Build the next implementation wave from the product gap analysis as a full roadmap, but execute it in strict value order: first make the existing checkout/product/cart/thank-you storefront flows useful and truthful, then add offer governance, mini-cart/account expansion, recommendation workflows, and QA/docs. The implementation should preserve UpsellBay's core wedge: native WooCommerce AOV offers without checkout replacement, funnel-builder scope, popup clutter, CartBay coupling, or unproven Block Checkout claims.

## Key Implementation Changes

### 1. Claim Gating and Storefront Baseline

- Remove the active `cart_checkout_blocks` compatibility declaration until real Block Checkout E2E proof exists; downgrade compatibility docs/tests from "Supported" to "implemented path, claim gated."
- Redesign shared storefront offer rendering so every placement has a distinct shopper purpose:
  - Checkout: one concise checkbox bump.
  - Product page: "Complete this product" add-on module.
  - Cart: "Still missing?" add-on list with reason labels.
  - Thank-you: explicit follow-on checkout offer, never original-order mutation.
- Add visible shopper controls and states: dismiss button, disabled/loading button state, success/error Woo notices, "already in cart," and safer REST failure messages.
- Add server-side suppression for invalid offers before render: offered product unavailable, already in cart, unsupported product type, subscription discount blocked, schedule/rules failed, placement disabled, dismissed in session.

### 2. Offer Governance and Merchant Trust

- Add an offer conflict/health service that evaluates active and draft offers before save and for list-table display.
- Health states must include: eligible, draft, paused, placement disabled, product unavailable, already overlapping, discount conflict, schedule inactive, unsupported product type, and Block claim gated.
- Add save-time warnings in the offer editor for:
  - Same offered product + same placement + overlapping schedule.
  - Same trigger context + same offer product.
  - Offer product also used as trigger product.
  - Sale product or active coupon/dynamic-pricing risk.
  - Bundle/composite/subscription discount risk.
- Add deterministic resolution options: pause older overlapping offer, lower existing priority, change placement, restrict schedule, or keep overlap with explicit override reason.
- Keep checkout default max display count at `1`; cart max display count at `3`; product and thank-you at `1`.

### 3. New Interfaces and Data Shape

- Add normalized offer meta:
  - `_ub_conflict_override` boolean, default `false`.
  - `_ub_conflict_override_reason` string, default empty.
  - `_ub_offer_goal` string, allowed values: `add_on`, `upgrade`, `protection`, `threshold_helper`, `follow_on`, default `add_on`.
  - `_ub_reason_label` string, optional shopper-facing reason such as "Pairs with your cart."
- Add settings shape under `upsellbay_settings.placements` or adjacent placement config:
  - `checkout_bump.max_display = 1`
  - `cart_crosssell.max_display = 3`
  - `product_upsell.max_display = 1`
  - `thankyou_offer.max_display = 1`
- Add one new future placement after the core storefront pass: `account_order_offer`, rendered on WooCommerce order details using order CRUD only.
- Do not add new public REST routes unless needed; extend existing `/cart-offer-add` and `/dismiss` responses with safe status/message fields only.

### 4. Roadmap Execution Order

1. **Truth and safety pass**
   - Gate Block Checkout claim.
   - Fix compatibility docs/tests.
   - Add render suppression for already-in-cart and unsupported offer products.
   - Add visible dismiss and success/error states.

2. **Checkout bump value pass**
   - Redesign classic checkout bump copy/markup.
   - Keep one displayed bump by default.
   - Add placement position setting for supported classic checkout locations.
   - Verify accept/unaccept updates totals and attribution.

3. **Offer governance pass**
   - Implement conflict detector and health model.
   - Surface health in Offers list and editor.
   - Add conflict warnings and override fields.
   - Add tests for overlapping active/draft/paused offers.

4. **Product and cart value pass**
   - Replace generic product card with "Complete this product."
   - Add cart reason labels and threshold-helper goal.
   - Add already-in-cart behavior and visible dismiss.
   - Keep mini-cart out of this pass unless classic cart/product flows are stable.

5. **Thank-you follow-on clarity pass**
   - Rewrite thank-you UI to explain separate follow-on checkout.
   - Preserve `_ub_source_order_id` attribution.
   - Add "No thanks" dismissal.
   - Suppress for invalid source orders.

6. **Recommendation workflow pass**
   - Integrate existing product recommendation assistant into the editor.
   - Show explainable suggestions from Woo upsells/cross-sells, same category, low-priced add-ons, and accepted-history signals.
   - Let merchant apply a suggestion into offer product, goal, reason label, and default copy.

7. **Mini-cart and account expansion**
   - Add classic mini-cart add-on tray using Woo mini-cart hooks and fragment refresh.
   - Add `account_order_offer` on order details for replenishment/accessory follow-on offers.
   - Treat block mini-cart and side-cart plugins as compatibility backlog unless tested.

8. **QA, docs, and release readiness**
   - Add focused tests for each new behavior.
   - Run PHP, JS, i18n, static, and storefront E2E validation.
   - Update merchant docs, compatibility matrix, architecture notes, and release gates.

## Test Plan

- Unit tests:
  - Offer conflict detector for overlapping active offers, draft/paused behavior, schedule windows, same product/placement, self-offer, and override reason.
  - Offer prioritizer suppression for already-in-cart, dismissed, unsupported product, and placement max display.
  - Settings normalization for placement display counts and new offer meta fields.
- Integration tests:
  - Offer editor save warnings do not block drafts but warn active conflicting offers.
  - Cart mutation still uses server-calculated prices and does not trust client price.
  - Thank-you follow-on preserves source order attribution and never mutates original order.
  - HPOS-safe attribution uses Woo order/order-item CRUD only.
- Frontend/E2E scenarios:
  - Product page add-on renders, dismisses, adds to cart, and suppresses once in cart.
  - Cart offer renders up to three relevant offers, adds one, refreshes notices/fragments, and suppresses duplicate.
  - Classic checkout bump accepts/unaccepts, updates totals, and writes attribution.
  - Thank-you offer clearly starts follow-on checkout and links source order.
  - Mobile and keyboard pass for all P0 placements.
  - Block Checkout compatibility is not claimed unless the dedicated Block E2E suite passes.

## Assumptions

- The next implementation should be broad-roadmap planned but executed sequentially, with checkout/storefront truth first.
- Block Checkout remains claim-gated until real E2E evidence exists, regardless of current partial implementation.
- UpsellBay should not add popups, funnel pages, abandoned-cart recovery, tokenized one-click post-purchase charges, or CartBay-dependent behavior in this wave.
- Runtime changes go on the application branch; `.meta` architecture/task/docs updates stay aligned with the repo's overlay workflow.

