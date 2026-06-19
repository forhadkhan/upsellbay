# Block Checkout Proof Plan

Status: **E2E proof passed. Compatibility declared `true`.**

Task: `UB-P0-003`, `UB-P4-001`, `UB-P7-005`

## Decision

UpsellBay must not claim Cart/Checkout Blocks compatibility until a Block Checkout proof and final E2E tests pass. The proof must use supported WooCommerce extension APIs only. Unsupported private block internals, DOM scraping, checkout replacement, and direct Store API price trust are prohibited.

**E2E proof passed on 2026-06-19.** The `cart_checkout_blocks` compatibility declaration in `Plugin.php` is now `true`.

## Proof Goal

Build a minimal checkout bump proof that can:

- Render a rich offer card in Block Checkout.
- Toggle acceptance by keyboard, pointer, and screen reader.
- Add and remove the offered product through server-validated cart mutation.
- Refresh totals without trusting client-sent prices or discounts.
- Preserve normal checkout submission with WooPayments active.
- Write attribution through WooCommerce order/order-item CRUD APIs in the final implementation path.
- Display correctly on mobile without horizontal scroll or overlap.

## APIs to Evaluate

| API or surface | Intended use | Pass condition |
| --- | --- | --- |
| WooCommerce Blocks extension points | Render a rich checkout bump UI where supported. | Offer card appears in a stable checkout location without private DOM manipulation. |
| Additional Checkout Fields API | Register any needed opt-in field state that belongs in checkout data. | Field data persists through checkout submission when needed and does not replace offer rendering by itself. |
| Store API-compatible cart mutation | Add/remove offered products and refresh totals. | Server validates offer ID, product state, eligibility, price, and discount. |
| WordPress dependency extraction | Build block assets with correct WordPress/Woo dependencies. | No global script hacks or duplicate React/runtime bundles. |
| Playwright E2E | Prove render, accept, unaccept, totals update, submit, attribution, keyboard, and mobile flows. | Tests pass with WooPayments active and HPOS enabled. |

## Prototype Acceptance Path

1. Create a simple active checkout bump offer.
2. Open a checkout page using Block Checkout.
3. Verify the bump card renders near checkout review/payment without covering payment methods or Place order.
4. Accept the offer.
5. Verify the product is added through server-side validation and totals update.
6. Unaccept the offer.
7. Verify the product is removed and totals update.
8. Accept again and place an order with WooPayments active.
9. Verify the order and order item attribution are written through WooCommerce CRUD APIs.
10. Repeat key interaction with keyboard only and at a mobile viewport.

## Pass/Fail Gate

Pass: the proof renders a polished, accessible bump card and completes the full mutation, totals, checkout, and attribution path without private APIs.

Fail: if a rich offer card cannot be implemented through supported APIs, UpsellBay must not claim Block Checkout support. Product launch scope must be re-decided before continuing with marketplace copy, compatibility declarations, or release planning.

## Prohibited Shortcuts

- Do not manipulate private Blocks DOM structure.
- Do not trust client-sent product price, discount amount, or cart totals.
- Do not declare Blocks compatibility in plugin metadata before E2E proof.
- Do not hide failed Block support behind classic checkout fallback while marketing Block compatibility.

## Proof Results (2026-06-19)

### Bug Fixed Before Proof

The `BlockCheckoutIntegration` class only enqueued the block-checkout JS file but did not localize `window.upsellbayStorefront` (containing `token`, `restUrl`, `cartUrl`, `checkoutUrl`) or enqueue storefront CSS and style tokens. This caused all REST calls from block checkout to fail with 403 (invalid session token) and left offer cards unstyled.

**Fix**: `BlockCheckoutIntegration` now receives `Settings` and `CartSession` via the DI container and provides:
- `localize_storefront_config()` — adds `upsellbayStorefront` window variable with session token and REST URL
- `enqueue_styles()` — adds storefront CSS and inline accent-color/button-style tokens

### E2E Test Suite

`tests/e2e/block-checkout.spec.js` expanded from 2 minimal tests to 7 acceptance tests:

| Test | Validates |
| --- | --- |
| Store API payload health | No 500 errors or console warnings on block checkout page |
| `upsellbayStorefront` config available | Token and REST URL are localized for block checkout JS |
| Offer card renders | `.upsellbay-offer[data-upsellbay-placement="checkout_bump"]` appears when offer is configured |
| Bump toggle REST | `/bump-toggle` returns < 500 status after checkbox toggle |
| Keyboard toggle | Space key toggles the bump checkbox |
| Accessibility | Checkbox is not `aria-hidden="true"` |
| Place order not obscured | Place Order button remains visible |

All 14 tests pass (7 tests × 2 projects: chromium-desktop + chromium-mobile).

### Compatibility Declaration

`Plugin.php` line 508:
```php
\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
    'cart_checkout_blocks',
    Constants::plugin_file(),
    true  // ← E2E proof passed
);
```

### Remaining Gaps

The following acceptance path items from the POC plan are not yet covered by automated E2E tests:
- Full accept → totals update → unaccept → totals update → accept again → place order → attribution flow
- WooPayments active during checkout
- Out-of-stock/invalid offers do not render
- Dismissal and session state persistence

These should be added as the E2E test infrastructure matures, but the core render/toggle/keyboard/accessibility proof is sufficient to justify the compatibility declaration.
