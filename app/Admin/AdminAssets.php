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
	 * @param string               $screen_id Screen ID.
	 * @param array<string, mixed> $request Request context.
	 * @return array<string, array<string, string>>
	 */
	public function assets_for_screen( string $screen_id, array $request = array() ): array {
		if ( ! str_starts_with( $screen_id, 'woocommerce_page_upsellbay' ) ) {
			return array();
		}

		if ( 'woocommerce_page_upsellbay' !== $screen_id ) {
			return array();
		}

		$assets = array(
			'upsellbay-admin' => array(
				'type' => 'style-script',
				'css'  => 'assets/admin/css/upsellbay-admin.css',
				'js'   => 'assets/admin/js/upsellbay-admin.js',
			),
		);

		$tab    = $this->request_key( $request['tab'] ?? '' );
		$action = $this->request_key( $request['action'] ?? '' );

		if ( 'setup' === $tab || ( 'offers' === $tab && 'edit' === $action ) ) {
			$assets['upsellbay-offer-editor'] = array(
				'type' => 'script',
				'js'   => 'assets/admin/js/upsellbay-offer-editor.js',
			);
		}

		if ( '' === $tab || 'dashboard' === $tab ) {
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
		$request = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$assets  = $this->assets_for_screen( $hook_suffix, $request );
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

	/**
	 * Sanitize a request key for asset scoping.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 */
	private function request_key( $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( (string) $value );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ?? '' );
	}
}
