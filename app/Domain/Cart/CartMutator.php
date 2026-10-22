<?php
/**
 * Cart mutator.
 *
 * @package UpsellBay\Domain\Cart
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\Cart;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Data\CartSession;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WP_Error;

/**
 * Adds and removes accepted offer items while preserving session state.
 *
 * @since 1.0.0
 */
final class CartMutator {
	/**
	 * Cart session.
	 *
	 * @var CartSession
	 */
	private CartSession $session;

	/**
	 * Cart validator.
	 *
	 * @var CartValidator
	 */
	private CartValidator $validator;

	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discounts;

	/**
	 * Add to cart callback.
	 *
	 * @var callable(int, int, array<string, mixed>): string
	 */
	private $add_to_cart;

	/**
	 * Remove cart item callback.
	 *
	 * @var callable(string): bool
	 */
	private $remove_cart_item;

	/**
	 * Cart item existence callback.
	 *
	 * @var callable(string): bool
	 */
	private $cart_item_exists;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param CartSession        $session          Cart session.
	 * @param CartValidator      $validator        Cart validator.
	 * @param DiscountCalculator $discounts        Discount calculator.
	 * @param callable|null      $add_to_cart      Add callback.
	 * @param callable|null      $remove_cart_item Remove callback.
	 * @param callable|null      $cart_item_exists Exists callback.
	 */
	public function __construct(
		CartSession $session,
		CartValidator $validator,
		DiscountCalculator $discounts,
		?callable $add_to_cart = null,
		?callable $remove_cart_item = null,
		?callable $cart_item_exists = null
	) {
		$this->session          = $session;
		$this->validator        = $validator;
		$this->discounts        = $discounts;
		$this->add_to_cart      = $add_to_cart ?? array( $this, 'wc_add_to_cart' );
		$this->remove_cart_item = $remove_cart_item ?? array( $this, 'wc_remove_cart_item' );
		$this->cart_item_exists = $cart_item_exists ?? array( $this, 'wc_cart_item_exists' );
	}

	/**
	 * Accept an offer by adding its product to the cart.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer     Offer.
	 * @param string               $placement Placement.
	 * @param array<string, mixed> $context   Context.
	 * @return array<string, mixed>|WP_Error WP_Error when WooCommerce refused to add the product.
	 */
	public function accept( array $offer, string $placement, array $context ) {
		$offer_id = (int) ( $offer['id'] ?? 0 );
		$state    = $this->session->state();
		$accepted = is_array( $state['accepted'][ $offer_id ] ?? null ) ? $state['accepted'][ $offer_id ] : array();

		if ( isset( $accepted['cart_item_key'] ) && ( $this->cart_item_exists )( (string) $accepted['cart_item_key'] ) ) {
			return array(
				'success'       => true,
				'cart_item_key' => (string) $accepted['cart_item_key'],
				'duplicate'     => true,
			);
		}

		$validation = $this->validator->validate( $offer, $placement, $context );
		if ( true !== ( $validation['valid'] ?? false ) ) {
			return array(
				'success' => false,
				'errors'  => $validation['errors'],
			);
		}

		$meta       = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$product_id = (int) $meta['_ub_offer_product_id'];
		$discount   = is_array( $validation['discount'] ?? null ) ? $validation['discount'] : $this->discounts->calculate( '0', $meta );
		$item_data  = array(
			Constants::ATTRIBUTION_OFFER_ID        => $offer_id,
			Constants::ATTRIBUTION_OFFER_TYPE      => $meta['_ub_offer_type'],
			Constants::ATTRIBUTION_OFFER_PLACEMENT => $placement,
			Constants::ATTRIBUTION_DISCOUNT_TYPE   => $discount['discount_type'],
			Constants::ATTRIBUTION_DISCOUNT_AMOUNT => $discount['discount_amount'],
			'_ub_original_price'                   => $discount['original_price'],
			'_ub_offer_price'                      => $discount['offer_price'],
			'_ub_source_context'                   => (string) ( $context['source_context'] ?? $placement ),
		);
		// Only a thank-you offer has a source order, and the route sets it only after
		// verifying the shopper may use that order. Any other placement drops it, so
		// a client-sent ID can never become follow-on attribution on someone's order.
		$source_order_id = 'thankyou_offer' === $placement ? (int) ( $context['source_order_id'] ?? 0 ) : 0;
		if ( $source_order_id > 0 ) {
			$item_data[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] = $source_order_id;
		}
		// The repricer re-checks a product page offer's triggers against the product
		// it was accepted on, on every totals run (OfferLineRepricer::cart_context()).
		if ( 'product_upsell' === $placement && (int) ( $context['viewed_product_id'] ?? 0 ) > 0 ) {
			$item_data['_ub_viewed_product_id'] = (int) $context['viewed_product_id'];
		}
		$cart_item_key = (string) ( $this->add_to_cart )( $product_id, 1, $item_data );

		// WC_Cart::add_to_cart() returns '' (or false) when WooCommerce refuses the
		// product, for example a variable parent. Nothing was added, so nothing
		// may be recorded as accepted.
		if ( '' === $cart_item_key ) {
			return new WP_Error( 'add_to_cart_failed', __( 'Unable to add this offer. Please try again.', 'upsellbay' ) );
		}

		$this->session->accept_offer( $offer_id, $placement, $cart_item_key, array( 'source_order_id' => $source_order_id ) );

		return array(
			'success'         => true,
			'cart_item_key'   => $cart_item_key,
			'offer_price'     => $discount['offer_price'],
			'discount_amount' => $discount['discount_amount'],
		);
	}

	/**
	 * Run the claim-path validation (placement, active status, product, rules,
	 * server-side discount) without mutating the cart.
	 *
	 * Lets paths that do not add to the cart, such as the one-click thank-you charge,
	 * apply exactly the checks accept() applies.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer     Offer.
	 * @param string               $placement Placement.
	 * @param array<string, mixed> $context   Context.
	 * @return array<string, mixed>
	 */
	public function validate( array $offer, string $placement, array $context ): array {
		return $this->validator->validate( $offer, $placement, $context );
	}

	/**
	 * Remove an accepted offer item.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 * @return array<string, mixed>
	 */
	public function remove( int $offer_id ): array {
		$state    = $this->session->state();
		$accepted = is_array( $state['accepted'][ $offer_id ] ?? null ) ? $state['accepted'][ $offer_id ] : array();
		$key      = (string) ( $accepted['cart_item_key'] ?? '' );

		if ( '' === $key ) {
			return array( 'success' => true );
		}

		$removed = ( $this->remove_cart_item )( $key );
		if ( $removed ) {
			$this->session->forget_accepted_offer( $offer_id );
		}

		return array(
			'success'       => $removed,
			'cart_item_key' => $key,
		);
	}

	/**
	 * Default WooCommerce add adapter.
	 *
	 * @param int                  $product_id Product ID.
	 * @param int                  $quantity   Quantity.
	 * @param array<string, mixed> $item_data  Cart item data.
	 */
	private function wc_add_to_cart( int $product_id, int $quantity, array $item_data ): string {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $item_data );
			return is_string( $key ) ? $key : '';
		}

		return '';
	}

	/**
	 * Default WooCommerce remove adapter.
	 *
	 * @param string $cart_item_key Cart item key.
	 */
	private function wc_remove_cart_item( string $cart_item_key ): bool {
		return function_exists( 'WC' ) && WC()->cart && WC()->cart->remove_cart_item( $cart_item_key );
	}

	/**
	 * Default WooCommerce cart item exists adapter.
	 *
	 * @param string $cart_item_key Cart item key.
	 */
	private function wc_cart_item_exists( string $cart_item_key ): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$cart = WC()->cart->get_cart();
		return isset( $cart[ $cart_item_key ] );
	}
}
