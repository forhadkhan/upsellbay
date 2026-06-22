<?php
/**
 * Block Checkout integration.
 *
 * @package UpsellBay\Integrations\WooCommerce
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\CartSession;

/**
 * Loads the Block Checkout asset with storefront config and styles.
 *
 * @since 1.0.0
 */
final class BlockCheckoutIntegration {
	/**
	 * Plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Cart session.
	 *
	 * @since 1.0.0
	 *
	 * @var CartSession
	 */
	private CartSession $session;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings|null    $settings Plugin settings.
	 * @param CartSession|null $session  Cart session.
	 */
	public function __construct( ?Settings $settings = null, ?CartSession $session = null ) {
		$this->settings = $settings ?? new Settings();
		$this->session  = $session ?? new CartSession();
	}

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
	 * Enqueue the block checkout entry with storefront config and styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_asset(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		$asset_file   = dirname( Constants::plugin_file() ) . '/assets/frontend/block-checkout.asset.php';
		$asset        = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => Constants::VERSION,
		);
		$dependencies = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : array();

		if ( ! in_array( 'wc-blocks-checkout', $dependencies, true ) ) {
			$dependencies[] = 'wc-blocks-checkout';
		}

		$handle = Constants::asset_handle( 'block-checkout' );

		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/frontend/block-checkout.js', Constants::plugin_file() ),
			$dependencies,
			$asset['version'] ?? Constants::VERSION,
			true
		);

		$this->localize_storefront_config( $handle );
		$this->enqueue_styles();
	}

	/**
	 * Localize the storefront configuration object for block checkout JS.
	 *
	 * This provides the same `upsellbayStorefront` window variable that
	 * StorefrontController provides for classic checkout, so the block
	 * checkout React components can authenticate REST calls.
	 *
	 * @since 1.0.0
	 *
	 * @param string $handle Script handle.
	 */
	private function localize_storefront_config( string $handle ): void {
		if ( ! function_exists( 'wp_localize_script' ) || ! function_exists( 'rest_url' ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			'upsellbayStorefront',
			array(
				'restUrl'     => rest_url( Constants::REST_NAMESPACE ),
				'token'       => $this->session->ensure_token(),
				'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			)
		);
	}

	/**
	 * Enqueue storefront styles and merchant style tokens on block checkout.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_styles(): void {
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$handle   = Constants::asset_handle( 'storefront' );
		$css_file = dirname( Constants::plugin_file() ) . '/assets/frontend/storefront.css';
		$version  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : Constants::VERSION;

		wp_enqueue_style(
			$handle,
			plugins_url( 'assets/frontend/storefront.css', Constants::plugin_file() ),
			array(),
			$version
		);

		if ( ! function_exists( 'wp_add_inline_style' ) ) {
			return;
		}

		$tokens       = $this->settings->all()['style_tokens'] ?? array();
		$accent_color = (string) ( $tokens['accent_color'] ?? Settings::DEFAULT_ACCENT_COLOR );
		$button_style = (string) ( $tokens['button_style'] ?? 'theme' );
		$css          = '.upsellbay-offer{--upsellbay-accent:' . esc_attr( $accent_color ) . ';}';
		if ( 'outline' === $button_style ) {
			$css .= '.upsellbay-offer .upsellbay-offer__button{background:transparent;color:var(--upsellbay-accent);border:1px solid var(--upsellbay-accent);}';
			$css .= '.upsellbay-offer .upsellbay-offer__button:hover,.upsellbay-offer .upsellbay-offer__button:focus{background:var(--upsellbay-accent);color:#fff;}';
		}

		wp_add_inline_style( $handle, $css );
	}
}
