# Phase 7 E2E Test Plan

Status: in progress.

Use a disposable WooCommerce store with HPOS enabled, debug logging enabled, at least one simple product, one variable product, and one non-cash payment method. Capture screenshots or traces for every failure and record final results in `.meta/qa/release-validation.md`.

## Classic Checkout

1. Create a checkout bump offer for a simple product.
2. Add a primary product to the cart and open a `[woocommerce_checkout]` page.
3. Confirm the offer renders before the Place order area without obscuring payment controls.
4. Accept the bump and confirm totals refresh with server-calculated pricing.
5. Unaccept the bump and confirm the cart line and discount are removed.
6. Place an order with the bump accepted and confirm order-item attribution via WooCommerce CRUD-visible meta.
7. Repeat with invalid, paused, scheduled-out, and out-of-stock offers; no invalid offer should render.

## Block Checkout

Block Checkout compatibility is not claimed until Phase 7 Block Checkout E2E passes.

1. Create a checkout page using Checkout Blocks.
2. Activate WooPayments or the configured block-compatible gateway.
3. Confirm the checkout bump renders through supported Blocks extension points.
4. Test keyboard focus, screen reader labels, accept, unaccept, Store API totals refresh, and place order.
5. Confirm attribution is written through WooCommerce order and order-item CRUD APIs.
6. If any step requires private Blocks internals or DOM scraping, record failure and keep Blocks compatibility unclaimed.

## Storefront Placements

1. Product page: render and accept a product add-on offer; confirm no horizontal scroll on mobile.
2. Cart: render, accept, dismiss, and re-open a cross-sell offer; confirm Woo notices on failure.
3. Thank-you: render a follow-on checkout offer after order completion; confirm the primary order total is not mutated.
4. Repeat key paths with desktop and mobile viewport screenshots.

## Compatibility Paths

Run the applicable checkout flow with:

- WooPayments.
- Stripe for WooCommerce.
- PayPal Payments.
- WooCommerce Subscriptions with subscription products excluded from recurring discount leakage.
- CartBay active and inactive.
- Known checkout replacement plugins detected as warning-only where available.
