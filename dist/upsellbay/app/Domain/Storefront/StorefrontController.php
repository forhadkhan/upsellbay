<?php
/**
 * Storefront placement hook controller.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

use WPAnchorBay\UpsellBay\Core\Constants;
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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository   $offers   Offer repository.
	 * @param PlacementRenderer $renderer Placement renderer.
	 * @param CartSession       $session  Cart session.
	 */
	public function __construct( OfferRepository $offers, PlacementRenderer $renderer, CartSession $session ) {
		$this->offers   = $offers;
		$this->renderer = $renderer;
		$this->session  = $session;
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

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_checkout_bump' ) );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_offer' ) );
		add_action( 'woocommerce_cart_collaterals', array( $this, 'render_cart_offers' ), 5 );
		add_action( 'woocommerce_thankyou', array( $this, 'render_thankyou_offer' ) );
	}

	/**
	 * Render the classic checkout bump placement.
	 *
	 * @since 1.0.0
	 */
	public function render_checkout_bump(): void {
		$this->echo_placement( 'checkout_bump', $this->context(), 1 );
	}

	/**
	 * Render the product-page offer placement.
	 *
	 * @since 1.0.0
	 */
	public function render_product_offer(): void {
		$this->echo_placement( 'product_upsell', $this->context(), 1 );
	}

	/**
	 * Render cart cross-sell offers.
	 *
	 * @since 1.0.0
	 */
	public function render_cart_offers(): void {
		$this->echo_placement( 'cart_crosssell', $this->context(), 3 );
	}

	/**
	 * Render thank-you follow-on offers.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $order_id Source order ID.
	 */
	public function render_thankyou_offer( $order_id = 0 ): void {
		$context                    = $this->context();
		$context['source_order_id'] = (int) $order_id;
		$this->echo_placement( 'thankyou_offer', $context, 1 );
	}

	/**
	 * Render and echo a placement.
	 *
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Context.
	 * @param int                  $limit     Limit.
	 */
	private function echo_placement( string $placement, array $context, int $limit ): void {
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
			'cart_product_ids'  => array(),
			'cart_category_ids' => array(),
			'cart_tag_ids'      => array(),
			'cart_subtotal'     => '0',
		);

		if ( function_exists( 'get_the_ID' ) ) {
			$context['viewed_product_id'] = (int) get_the_ID();
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$context['cart_subtotal'] = (string) WC()->cart->get_subtotal();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = (int) ( $cart_item['product_id'] ?? 0 );
				if ( $product_id > 0 ) {
					$context['cart_product_ids'][] = $product_id;
				}
			}
		}

		if ( function_exists( 'wp_get_current_user' ) ) {
			$user                  = wp_get_current_user();
			$context['user_roles'] = $user->roles;
		}

		return $context;
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

		if ( function_exists( 'wp_localize_script' ) && function_exists( 'rest_url' ) ) {
			wp_localize_script(
				$handle,
				'upsellbayStorefront',
				array(
					'restUrl' => rest_url( Constants::REST_NAMESPACE ),
					'token'   => $this->session->ensure_token(),
				)
			);
		}
	}
}
