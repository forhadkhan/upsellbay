<?php
/**
 * Block Checkout integration.
 *
 * @package UpsellBay\Integrations\WooCommerce
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\WooCommerce;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Loads the Block Checkout asset without declaring compatibility claims.
 *
 * @since 1.0.0
 */
final class BlockCheckoutIntegration {
	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'enqueue_asset' ) );
		}
	}

	/**
	 * Enqueue the block checkout entry when assets are available.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_asset(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		$asset_file = dirname( Constants::plugin_file() ) . '/assets/frontend/block-checkout.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => Constants::VERSION,
		);

		wp_enqueue_script(
			Constants::asset_handle( 'block-checkout' ),
			plugins_url( 'assets/frontend/block-checkout.js', Constants::plugin_file() ),
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? Constants::VERSION,
			true
		);
	}
}
