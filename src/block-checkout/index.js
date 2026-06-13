/**
 * UpsellBay
 * Block Checkout integration.
 *
 * @package UpsellBay\Frontend
 */

import { registerCartCrossSells } from './cart-cross-sells';
import { registerCheckoutBumps } from './checkout-bump';

document.addEventListener('DOMContentLoaded', () => {
    registerCartCrossSells();
    registerCheckoutBumps();
});
