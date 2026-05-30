<?php
/**
 * Admin asset scoping.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Enqueues admin assets only for UpsellBay screens.
 *
 * @since 1.0.0
 */
final class AdminAssets {
	/**
	 * Register admin enqueue hook.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}
	}

	/**
	 * Return assets for a screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $screen_id Screen ID.
	 * @return array<string, array<string, string>>
	 */
	public function assets_for_screen( string $screen_id ): array {
		if ( ! str_starts_with( $screen_id, 'woocommerce_page_upsellbay' ) ) {
			return array();
		}

		$assets = array(
			'upsellbay-admin' => array(
				'type' => 'style-script',
				'css'  => 'assets/admin/css/upsellbay-admin.css',
				'js'   => 'assets/admin/js/upsellbay-admin.js',
			),
		);

		if ( in_array( $screen_id, array( 'woocommerce_page_upsellbay-add-offer', 'woocommerce_page_upsellbay-wizard' ), true ) ) {
			$assets['upsellbay-offer-editor'] = array(
				'type' => 'script',
				'js'   => 'assets/admin/js/upsellbay-offer-editor.js',
			);
		}

		if ( 'woocommerce_page_upsellbay-analytics' === $screen_id ) {
			$assets['upsellbay-analytics'] = array(
				'type' => 'script',
				'js'   => 'assets/admin/js/upsellbay-analytics.js',
			);
		}

		return $assets;
	}

	/**
	 * Enqueue scoped assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 */
	public function enqueue( string $hook_suffix ): void {
		$assets = $this->assets_for_screen( $hook_suffix );
		if ( array() === $assets || ! function_exists( 'plugins_url' ) ) {
			return;
		}

		foreach ( $assets as $handle_suffix => $asset ) {
			$handle = Constants::asset_handle( str_replace( 'upsellbay-', '', $handle_suffix ) );
			if ( isset( $asset['css'] ) && function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( $handle, plugins_url( $asset['css'], Constants::plugin_file() ), array(), Constants::VERSION );
			}
			if ( isset( $asset['js'] ) && function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( $handle, plugins_url( $asset['js'], Constants::plugin_file() ), array( 'jquery' ), Constants::VERSION, true );
			}
		}
	}
}
