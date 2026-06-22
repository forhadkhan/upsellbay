<?php
/**
 * Storefront placement hook controller.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Data\OfferRepository;

/**
 * Connects WooCommerce storefront hooks to the placement renderer.
 *
 * @since 1.0.0
 */
final class StorefrontController {
	/**
	 * Offer repository.
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $offers;

	/**
	 * Placement renderer.
	 *
	 * @var PlacementRenderer
	 */
	private PlacementRenderer $renderer;

	/**
	 * Cart session.
	 *
	 * @var CartSession
	 */
	private CartSession $session;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository   $offers   Offer repository.
	 * @param PlacementRenderer $renderer Placement renderer.
	 * @param CartSession       $session  Cart session.
	 * @param Settings|null     $settings Plugin settings.
	 */
	public function __construct( OfferRepository $offers, PlacementRenderer $renderer, CartSession $session, ?Settings $settings = null ) {
		$this->offers   = $offers;
		$this->renderer = $renderer;
		$this->session  = $session;
		$this->settings = $settings ?? new Settings();
	}

	/**
	 * Register storefront hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		// Start WC session early so the cookie is set before output begins.
		add_action( 'template_redirect', array( $this, 'maybe_start_session' ) );

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_checkout_bump' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_offer' ) );
		add_action( 'woocommerce_cart_collaterals', array( $this, 'render_cart_offers' ), 5 );
		add_action( 'woocommerce_thankyou', array( $this, 'render_thankyou_offer' ) );

		add_action( 'wp_footer', array( $this, 'output_token_fragment' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_token_fragment' ) );
	}

	/**
	 * Start the WooCommerce session and generate the offer token before output.
	 *
	 * WC session cookies must be sent before headers are flushed.
	 * Offer render hooks fire mid-page (after headers are sent),
	 * so we initialise the session here on `template_redirect`.
	 *
	 * @since 1.0.0
	 */
	public function maybe_start_session(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		if ( ! function_exists( 'is_product' ) ) {
			return;
		}

		$needs_session = is_product() || is_cart() || is_checkout() || is_order_received_page();

		if ( ! $needs_session ) {
			return;
		}

		// Force-start the session so the cookie is sent with this response.
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// Pre-generate the token so it is persisted in the now-active session.
		$this->session->ensure_token();
	}

	/**
	 * Render the classic checkout bump placement.
	 *
	 * @since 1.0.0
	 */
	public function render_checkout_bump(): void {
		$limit = $this->settings->placement_max_display( 'checkout_bump' );
		$this->echo_placement( 'checkout_bump', $this->context(), $limit );
	}

	/**
	 * Render the product-page offer placement.
	 *
	 * @since 1.0.0
	 */
	public function render_product_offer(): void {
		$limit = $this->settings->placement_max_display( 'product_upsell' );
		$this->echo_placement( 'product_upsell', $this->context(), $limit );
	}

	/**
	 * Render cart cross-sell offers.
	 *
	 * @since 1.0.0
	 */
	public function render_cart_offers(): void {
		$limit = $this->settings->placement_max_display( 'cart_crosssell' );
		$this->echo_placement( 'cart_crosssell', $this->context(), $limit );
	}

	/**
	 * Render thank-you follow-on offers.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $order_id Source order ID.
	 */
	public function render_thankyou_offer( $order_id = 0 ): void {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || in_array( $order->get_status(), array( 'failed', 'cancelled', 'refunded' ), true ) ) {
			return;
		}

		$user_id       = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$order_user_id = (int) $order->get_user_id();

		if ( $order_user_id > 0 ) {
			if ( $order_user_id !== $user_id ) {
				return;
			}
		} else {
			$request_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' === $request_key || $order->get_order_key() !== $request_key ) {
				return;
			}
		}

		$context                     = $this->context();
		$context['source_order_id']  = $order_id;
		$context['source_order_key'] = method_exists( $order, 'get_order_key' ) ? (string) $order->get_order_key() : '';
		$context['token']            = $this->session->ensure_token();
		$context['rest_url']         = function_exists( 'rest_url' ) ? rest_url( Constants::REST_NAMESPACE ) : '';
		$context['cart_url']         = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
		$context['checkout_url']     = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
		$limit                       = $this->settings->placement_max_display( 'thankyou_offer' );
		$this->echo_placement( 'thankyou_offer', $context, $limit );
	}

	/**
	 * Render and echo a placement.
	 *
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Context.
	 * @param int                  $limit     Limit.
	 */
	private function echo_placement( string $placement, array $context, int $limit ): void {
		if ( ! $this->should_render_placement( $placement, $context ) ) {
			return;
		}

		$html = $this->renderer->render( $placement, $this->offers->query( array( 'limit' => 50 ) ), $context, $limit );
		if ( '' === $html ) {
			return;
		}

		$this->enqueue_assets( 'checkout_bump' === $placement ? 'classic-checkout' : 'storefront' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderers escape offer content.
	}

	/**
	 * Build a lightweight context from the current WooCommerce request.
	 *
	 * @return array<string, mixed>
	 */
	private function context(): array {
		$context = array(
			'cart_product_ids'    => array(),
			'cart_category_ids'   => array(),
			'cart_tag_ids'        => array(),
			'viewed_category_ids' => array(),
			'viewed_tag_ids'      => array(),
			'dismissed_offer_ids' => array_keys( $this->session->state()['dismissed'] ?? array() ),
			'cart_subtotal'       => '0',
			'source_context'      => '',
			'is_preview'          => isset( $_GET['upsellbay_preview'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if ( function_exists( 'get_the_ID' ) ) {
			$viewed_product_id              = (int) get_the_ID();
			$context['viewed_product_id']   = $viewed_product_id;
			$context['viewed_category_ids'] = $this->product_term_ids( $viewed_product_id, 'product_cat' );
			$context['viewed_tag_ids']      = $this->product_term_ids( $viewed_product_id, 'product_tag' );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$context['cart_subtotal'] = (string) WC()->cart->get_subtotal();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = (int) ( $cart_item['product_id'] ?? 0 );
				if ( $product_id > 0 ) {
					$context['cart_product_ids'][] = $product_id;
					$context['cart_category_ids']  = array_merge( $context['cart_category_ids'], $this->product_term_ids( $product_id, 'product_cat' ) );
					$context['cart_tag_ids']       = array_merge( $context['cart_tag_ids'], $this->product_term_ids( $product_id, 'product_tag' ) );
				}
			}
		}

		if ( function_exists( 'wp_get_current_user' ) ) {
			$user                  = wp_get_current_user();
			$context['user_roles'] = $user->roles;
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$customer_id = get_current_user_id();
			if ( $customer_id > 0 ) {
				$context['customer_order_count']    = function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $customer_id ) : 0;
				$context['customer_lifetime_spend'] = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $customer_id ) : '0';
			}
		}

		// Merchant previews should remain inspectable even after a local dismiss action.
		if ( true === ( $context['is_preview'] ?? false ) || ( function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ) ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			$context['dismissed_offer_ids'] = array();
		}

		return $context;
	}

	/**
	 * Determine whether a storefront placement can render for this request.
	 *
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Render context.
	 */
	private function should_render_placement( string $placement, array $context ): bool {
		$settings = $this->settings->all();

		if ( true !== ( $settings['enabled'] ?? true ) ) {
			return false;
		}

		if ( false === ( $settings['placements'][ $placement ] ?? true ) ) {
			return false;
		}

		if ( true === ( $settings['test_mode'] ?? false ) && ! $this->can_view_test_mode( $context ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Return whether the current viewer can see test-mode offers.
	 *
	 * @param array<string, mixed> $context Render context.
	 */
	private function can_view_test_mode( array $context ): bool {
		if ( true === ( $context['is_preview'] ?? false ) ) {
			return true;
		}

		return function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}

	/**
	 * Return product term IDs when WordPress taxonomy helpers are available.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy.
	 * @return array<int, int>
	 */
	private function product_term_ids( int $product_id, string $taxonomy ): array {
		if ( $product_id <= 0 || ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'intval', $terms ) ) );
	}

	/**
	 * Enqueue placement-specific frontend assets.
	 *
	 * @param string $entry Entry key.
	 */
	private function enqueue_assets( string $entry ): void {
		if ( ! function_exists( 'wp_enqueue_script' ) || '' === Constants::plugin_file() ) {
			return;
		}

		$handle     = Constants::asset_handle( $entry );
		$asset_file = dirname( Constants::plugin_file() ) . '/assets/frontend/' . $entry . '.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => Constants::VERSION,
		);

		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/frontend/' . $entry . '.js', Constants::plugin_file() ),
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? Constants::VERSION,
			true
		);

		$this->enqueue_styles();

		if ( function_exists( 'wp_localize_script' ) && function_exists( 'rest_url' ) ) {
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
	}

	/**
	 * Enqueue scoped storefront styles and merchant style tokens.
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
			$css .= '.upsellbay-offer .upsellbay-offer__button{background:transparent;color:var(--upsellbay-accent);border-color:var(--upsellbay-accent);}';
		}

		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Output the initial token fragment container.
	 *
	 * @since 1.0.0
	 */
	public function output_token_fragment(): void {
		$token = $this->session->ensure_token();
		echo '<div id="upsellbay-token-fragment" data-token="' . esc_attr( $token ) . '" style="display:none;"></div>';
	}

	/**
	 * Refresh the token fragment via WooCommerce AJAX.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $fragments Cart fragments.
	 * @return array<string, string>
	 */
	public function add_token_fragment( array $fragments ): array {
		$token                                     = $this->session->ensure_token();
		$fragments['div#upsellbay-token-fragment'] = '<div id="upsellbay-token-fragment" data-token="' . esc_attr( $token ) . '" style="display:none;"></div>';
		return $fragments;
	}
}
