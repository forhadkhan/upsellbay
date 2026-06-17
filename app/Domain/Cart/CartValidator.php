<?php
/**
 * Cart mutation validator.
 *
 * @package UpsellBay\Domain\Cart
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Cart;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleEvaluator;

/**
 * Validates offer products and rules before cart mutation.
 *
 * @since 1.0.0
 */
final class CartValidator {
	/**
	 * Rule evaluator.
	 *
	 * @var RuleEvaluator
	 */
	private RuleEvaluator $rules;

	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discounts;

	/**
	 * Product loader callback.
	 *
	 * @var callable(int): array<string, mixed>|null
	 */
	private $product_loader;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param RuleEvaluator      $rules          Rule evaluator.
	 * @param DiscountCalculator $discounts      Discount calculator.
	 * @param callable|null      $product_loader Product loader.
	 */
	public function __construct( RuleEvaluator $rules, DiscountCalculator $discounts, ?callable $product_loader = null ) {
		$this->rules          = $rules;
		$this->discounts      = $discounts;
		$this->product_loader = $product_loader ?? array( $this, 'load_product' );
	}

	/**
	 * Validate an offer for cart mutation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer     Offer.
	 * @param string               $placement Placement.
	 * @param array<string, mixed> $context   Context.
	 * @return array<string, mixed>
	 */
	public function validate( array $offer, string $placement, array $context ): array {
		$meta       = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$product_id = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$product    = $product_id > 0 ? ( $this->product_loader )( $product_id ) : null;
		$errors     = array();

		if ( ( $meta['_ub_offer_type'] ?? '' ) !== $placement ) {
			$errors[] = 'placement_mismatch';
		}
		if ( 'active' !== ( $meta['_ub_status'] ?? '' ) ) {
			$errors[] = 'inactive_offer';
		}
		if ( ! is_array( $product ) ) {
			$errors[] = 'missing_product';
		} else {
			foreach ( array( 'purchasable', 'visible', 'in_stock' ) as $flag ) {
				if ( true !== ( $product[ $flag ] ?? false ) ) {
					$errors[] = 'product_' . $flag;
				}
			}
			if ( ! in_array( (string) ( $product['type'] ?? 'simple' ), array( 'simple', 'variation', 'variable' ), true ) ) {
				$errors[] = 'unsupported_product_type';
			}
			if ( true === ( $product['subscription'] ?? false ) ) {
				$errors[] = 'subscription_discount_blocked';
			}
		}

		$rules = is_array( $meta['_ub_rules'] ?? null ) ? $meta['_ub_rules'] : array();
		if ( ! $this->rules->matches( $rules, (string) ( $meta['_ub_rules_match'] ?? 'all' ), $context ) ) {
			$errors[] = 'rules_failed';
		}

		$price    = is_array( $product ) ? ( $product['price'] ?? null ) : null;
		$discount = null === $price ? null : $this->discounts->calculate( $price, $meta );
		if ( null === $discount ) {
			$errors[] = 'discount_failed';
		}

		return array(
			'valid'    => array() === $errors,
			'errors'   => $errors,
			'product'  => $product,
			'discount' => $discount,
		);
	}

	/**
	 * Default WooCommerce product loader.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	private function load_product( int $product_id ): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		return array(
			'id'           => $product_id,
			'price'        => method_exists( $product, 'get_price' ) ? $product->get_price() : '0',
			'purchasable'  => ! method_exists( $product, 'is_purchasable' ) || $product->is_purchasable(),
			'visible'      => ! method_exists( $product, 'is_visible' ) || $product->is_visible(),
			'in_stock'     => ! method_exists( $product, 'is_in_stock' ) || $product->is_in_stock(),
			'type'         => method_exists( $product, 'get_type' ) ? $product->get_type() : 'simple',
			'subscription' => function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $product ),
		);
	}
}
