# Block Checkout Proof Plan

Status: implemented in Phase 4/Integrations.

Task: `UB-P0-003`, `UB-P4-001`

## Decision

UpsellBay must not claim Cart/Checkout Blocks compatibility until a Block Checkout proof and final E2E tests pass. The proof must use supported WooCommerce extension APIs only. Unsupported private block internals, DOM scraping, checkout replacement, and direct Store API price trust are prohibited.

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
