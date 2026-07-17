/**
 * UpsellBay
 * Block Checkout integration.
 *
 * @package UpsellBay\Frontend
 */

import { registerCartCrossSells } from './cart-cross-sells';
import { registerCheckoutBumps } from './checkout-bump';

function registerUpsellBayBlocks() {
    registerCartCrossSells();
    registerCheckoutBumps();
}

// This script is enqueued in the footer, so DOMContentLoaded has often
// already fired by the time it executes — attaching a listener for it
// unconditionally means the callback never runs. Register immediately if
// the DOM is already ready, and only wait for the event otherwise.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', registerUpsellBayBlocks);
} else {
    registerUpsellBayBlocks();
}
