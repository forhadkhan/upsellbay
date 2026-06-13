<?php
/**
 * WooCommerce checkout field integration.
 *
 * @package UpsellBay\Integrations\WooCommerce
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\WooCommerce;

/**
 * Registers supported checkout field state for Blocks when APIs are present.
 *
 * @since 1.0.0
 */
final class CheckoutFields {
	/**
	 * Register checkout fields if the Blocks API is available.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'upsellbay/accepted_offer',
				'label'    => __( 'UpsellBay accepted offer', 'upsellbay' ),
				'location' => 'order',
				'type'     => 'text',
				'required' => false,
			)
		);
	}
}
