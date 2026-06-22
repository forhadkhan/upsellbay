<?php
/**
 * Admin asset scoping.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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
	 * @return array<string, array<string, mixed>>
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
				'deps' => array( 'jquery', 'wp-util', 'wc-backbone-modal' ),
			),
		);

		$tab    = $this->request_key( $request['tab'] ?? '' );
		$action = $this->request_key( $request['action'] ?? '' );

		if ( 'setup' === $tab || ( 'offers' === $tab && 'edit' === $action ) ) {
			$assets['upsellbay-offer-editor'] = array(
				'type' => 'script',
				'js'   => 'assets/admin/js/upsellbay-offer-editor.js',
				'deps' => array( 'jquery', Constants::asset_handle( 'admin' ), 'wc-admin-meta-boxes', 'wc-enhanced-select' ),
			);
		}

		if ( '' === $tab || 'dashboard' === $tab ) {
			$assets['upsellbay-analytics'] = array(
				'type' => 'script',
				'js'   => 'assets/admin/js/upsellbay-analytics.js',
			);
		}

		if ( 'settings' === $tab ) {
			$assets['upsellbay-color-picker'] = array(
				'type' => 'native-color-picker',
			);

			$assets['upsellbay-admin']['deps'] = array( 'jquery', 'wp-color-picker', 'wp-util', 'wc-backbone-modal' );
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

		$this->enqueue_help_tip_assets();
		$this->enqueue_color_picker_assets( $assets );

		foreach ( $assets as $handle_suffix => $asset ) {
			if ( 'native-color-picker' === ( $asset['type'] ?? '' ) ) {
				continue;
			}

			$handle = Constants::asset_handle( str_replace( 'upsellbay-', '', $handle_suffix ) );
			if ( isset( $asset['css'] ) && function_exists( 'wp_enqueue_style' ) ) {
				$css_version = Constants::VERSION;
				$css_file    = dirname( Constants::plugin_file() ) . '/' . $asset['css'];
				if ( file_exists( $css_file ) ) {
					$css_version = (string) filemtime( $css_file );
				}
				wp_enqueue_style( $handle, plugins_url( $asset['css'], Constants::plugin_file() ), array(), $css_version );
			}
			if ( isset( $asset['js'] ) && function_exists( 'wp_enqueue_script' ) ) {
				$dependencies = isset( $asset['deps'] ) && is_array( $asset['deps'] ) ? $asset['deps'] : array( 'jquery' );
				if ( isset( $assets['upsellbay-color-picker'] ) && 'upsellbay-admin' === $handle_suffix ) {
					$dependencies[] = 'wp-color-picker';
					$dependencies   = array_values( array_unique( $dependencies ) );
				}

				$version    = Constants::VERSION;
				$asset_file = dirname( Constants::plugin_file() ) . '/' . str_replace( '.js', '.asset.php', $asset['js'] );
				if ( file_exists( $asset_file ) ) {
					$asset_data = require $asset_file;
					$version    = $asset_data['version'] ?? $version;
				}

				wp_enqueue_script( $handle, plugins_url( $asset['js'], Constants::plugin_file() ), $dependencies, $version, true );

				if ( 'upsellbay-admin' === $handle_suffix ) {
					wp_localize_script(
						$handle,
						'upsellbay_data',
						array(
							'rest_url'            => get_rest_url( null, '/' ),
							'ajax_url'            => admin_url( 'admin-ajax.php' ),
							'nonce'               => wp_create_nonce( 'wp_rest' ),
							'ajax_nonce'          => wp_create_nonce( 'upsellbay_admin_ajax' ),
							'currency_symbol'     => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
							'currency_position'   => function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_currency_pos', 'left' ) : 'left',
							'decimal_separator'   => function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_price_decimal_sep', '.' ) : '.',
							'thousand_separator'  => function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_price_thousand_sep', ',' ) : ',',
							'price_decimals'      => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
						)
					);
				}
			}
		}
	}

	/**
	 * Enqueue WordPress's native color picker assets when the Settings tab needs them.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, string>> $assets Assets selected for the current screen.
	 */
	private function enqueue_color_picker_assets( array $assets ): void {
		if ( ! isset( $assets['upsellbay-color-picker'] ) ) {
			return;
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
		}
	}

	/**
	 * Enqueue WooCommerce help-tip dependencies for UpsellBay's custom admin page.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_help_tip_assets(): void {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}

		if ( ! function_exists( 'wp_enqueue_script' ) || ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-tiptip' );
		wp_add_inline_script(
			'jquery-tiptip',
			"jQuery( function( $ ) { if ( $.fn.tipTip ) { $( '.upsellbay-admin .woocommerce-help-tip' ).tipTip( { attribute: 'data-tip', fadeIn: 50, fadeOut: 50, delay: 200 } ); } } );"
		);
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
