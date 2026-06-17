<?php
/**
 * Cart mutator.
 *
 * @package UpsellBay\Domain\Cart
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Cart;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;

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
	 * @return array<string, mixed>
	 */
	public function accept( array $offer, string $placement, array $context ): array {
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
		if ( isset( $context['source_order_id'] ) && (int) $context['source_order_id'] > 0 ) {
			$item_data[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] = (int) $context['source_order_id'];
		}
		$cart_item_key = ( $this->add_to_cart )( $product_id, 1, $item_data );

		$this->session->accept_offer( $offer_id, $placement, $cart_item_key, array( 'source_order_id' => (int) ( $context['source_order_id'] ?? 0 ) ) );

		return array(
			'success'         => true,
			'cart_item_key'   => $cart_item_key,
			'offer_price'     => $discount['offer_price'],
			'discount_amount' => $discount['discount_amount'],
		);
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
