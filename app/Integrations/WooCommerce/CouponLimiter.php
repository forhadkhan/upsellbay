<?php
/**
 * WooCommerce Coupon Limiter.
 *
 * @package UpsellBay\Integrations\WooCommerce
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\WooCommerce;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WC_Product;
use WC_Coupon;

/**
 * Prevents store-wide coupons from applying to items that have an UpsellBay offer price.
 *
 * @since 1.0.0
 */
final class CouponLimiter {
	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'is_valid_for_product' ), 10, 4 );
	}

	/**
	 * Disallow coupon application if the product has an active UpsellBay offer price in the cart.
	 *
	 * @since 1.0.0
	 *
	 * @param bool       $valid   Whether the coupon is valid for the product.
	 * @param WC_Product $product The product being checked.
	 * @param WC_Coupon  $coupon  The coupon being applied.
	 * @param array      $values  The cart item data.
	 * @return bool
	 */
	public function is_valid_for_product( bool $valid, WC_Product $product, WC_Coupon $coupon, $values ): bool {
		if ( ! $valid ) {
			return $valid;
		}

		// If this specific cart item has an UpsellBay offer applied, prevent coupon.
		if ( isset( $values['_ub_offer_price'] ) && '' !== (string) $values['_ub_offer_price'] ) {
			return false;
		}

		return $valid;
	}
}
