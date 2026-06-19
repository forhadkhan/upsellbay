<?php
/**
 * Cart item discount applier.
 *
 * @package UpsellBay\Domain\Discounts
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Discounts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Applies session-scoped offer prices to WooCommerce cart items.
 *
 * @since 1.0.0
 */
final class DiscountApplier {
	/**
	 * Apply an offer price to a cart item.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	public function apply_to_cart_item( array $cart_item ): void {
		if ( ! isset( $cart_item['_ub_offer_price'], $cart_item['data'] ) || '' === (string) $cart_item['_ub_offer_price'] ) {
			return;
		}

		$product = $cart_item['data'];
		if ( ! is_object( $product ) || ! method_exists( $product, 'set_price' ) ) {
			return;
		}

		if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $product ) ) {
			return;
		}

		$product->set_price( (string) $cart_item['_ub_offer_price'] );
	}
}
