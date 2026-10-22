<?php
/**
 * Re-prices accepted Pro offer lines on every cart total calculation.
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
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\OrderContext;

/**
 * Decides the price of every Pro offer line from current server state.
 *
 * The price stored on the cart line at accept time is never trusted. On each
 * totals run the offer is reloaded and must still be published, active, in
 * schedule, on an enabled placement, for the same product, with its triggers
 * and rules still matching; the offer price is then recomputed from the
 * product's current price. A line that fails any check goes back to the
 * regular price. Exactly one unit is discounted, whatever the line quantity.
 *
 * Pro owns the lines its own route adds (product page, cart and thank-you
 * placements) and runs after the free host on the same hook, so its decision is
 * final for those lines. Checkout-bump lines belong to the host and are left
 * alone.
 *
 * @since 1.0.0
 */
final class OfferLineRepricer {
	/**
	 * Placements whose cart lines Pro prices.
	 *
	 * @var array<int, string>
	 */
	public const PRO_PLACEMENTS = array( 'product_upsell', 'cart_crosssell', 'thankyou_offer' );

	/**
	 * Evaluator reasons that decide whether an offer is shown, not whether an
	 * accepted one keeps its price. The offered product sits in the cart once
	 * accepted, the shopper may dismiss other cards, and the viewed product is
	 * gone at totals time.
	 *
	 * @var array<int, string>
	 */
	private const DISPLAY_ONLY_REASONS = array( 'dismissed_in_session', 'product_already_in_cart', 'viewing_offered_product' );

	/**
	 * Source order statuses that void a thank-you offer.
	 *
	 * @var array<int, string>
	 */
	private const VOID_ORDER_STATUSES = array( 'failed', 'cancelled', 'refunded' );

	/**
	 * Offer loader.
	 *
	 * @var callable(int): ?array<string, mixed>
	 */
	private $offer_loader;

	/**
	 * Storefront eligibility evaluator.
	 *
	 * @var OfferPrioritizer
	 */
	private OfferPrioritizer $eligibility;

	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discounts;

	/**
	 * Discount applier.
	 *
	 * @var DiscountApplier
	 */
	private DiscountApplier $applier;

	/**
	 * Placement switch reader.
	 *
	 * @var callable(string): bool
	 */
	private $placement_available;

	/**
	 * Current regular unit price of a product, or null when unknown.
	 *
	 * @var callable(int): ?string
	 */
	private $regular_price;

	/**
	 * Order loader.
	 *
	 * @var callable(int): ?object
	 */
	private $order_loader;

	/**
	 * Term ID cache for this totals run.
	 *
	 * @var array<string, array<int, int>>
	 */
	private array $terms = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable           $offer_loader        Offer loader (any post status).
	 * @param OfferPrioritizer   $eligibility         Storefront eligibility evaluator.
	 * @param DiscountCalculator $discounts           Discount calculator.
	 * @param DiscountApplier    $applier             Discount applier.
	 * @param callable           $placement_available Whether a placement is switched on.
	 * @param callable|null      $regular_price       Current regular unit price for a product ID.
	 * @param callable|null      $order_loader        Order loader.
	 */
	public function __construct(
		callable $offer_loader,
		OfferPrioritizer $eligibility,
		DiscountCalculator $discounts,
		DiscountApplier $applier,
		callable $placement_available,
		?callable $regular_price = null,
		?callable $order_loader = null
	) {
		$this->offer_loader        = $offer_loader;
		$this->eligibility         = $eligibility;
		$this->discounts           = $discounts;
		$this->applier             = $applier;
		$this->placement_available = $placement_available;
		$this->regular_price       = $regular_price ?? array( $this, 'wc_regular_price' );
		$this->order_loader        = $order_loader ?? static function ( int $order_id ): ?object {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
			return is_object( $order ) ? $order : null;
		};
	}

	/**
	 * Price every Pro offer line in a cart.
	 *
	 * Also refreshes the line's stored price and discount so order attribution
	 * records what was actually charged.
	 *
	 * @since 1.0.0
	 *
	 * @param object $cart WooCommerce cart (get_cart(), cart_contents).
	 */
	public function reprice( object $cart ): void {
		if ( ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		$items = $cart->get_cart();
		if ( ! is_array( $items ) || array() === $items ) {
			return;
		}

		// Not $cart->get_subtotal(): WC_Cart::calculate_totals() resets the totals
		// before this hook fires, so it reads 0 here. Built from the lines instead,
		// the same way the accept route builds it.
		$subtotal    = self::rule_subtotal( $items );
		$this->terms = array();

		foreach ( $items as $key => $item ) {
			if ( ! is_array( $item ) || ! self::is_pro_line( $item ) ) {
				continue;
			}

			$regular = ( $this->regular_price )( $this->line_product_id( $item ) );

			// Fail closed: with no known regular price there is nothing to price the
			// offer line against, and leaving it would charge whatever price the line
			// object already carries (an earlier pass's offer price, or another
			// plugin's). The line is removed instead.
			if ( null === $regular ) {
				$this->remove_line( $cart, (string) $key );
				continue;
			}

			$discount    = $this->current_discount( (string) $key, $item, $items, $subtotal, $regular );
			$offer_price = null === $discount ? null : (string) $discount['offer_price'];

			$this->applier->apply( $item, $offer_price, $regular );

			if ( property_exists( $cart, 'cart_contents' ) && is_array( $cart->cart_contents ) && isset( $cart->cart_contents[ $key ] ) ) {
				$cart->cart_contents[ $key ]['_ub_original_price']                     = $regular;
				$cart->cart_contents[ $key ]['_ub_offer_price']                        = $offer_price ?? '';
				$cart->cart_contents[ $key ][ Constants::ATTRIBUTION_DISCOUNT_AMOUNT ] = null === $discount ? '0.000000' : (string) $discount['discount_amount'];
			}
		}
	}

	/**
	 * Remove an offer line that cannot be priced.
	 *
	 * @param object $cart WooCommerce cart.
	 * @param string $key  Cart item key.
	 */
	private function remove_line( object $cart, string $key ): void {
		if ( method_exists( $cart, 'remove_cart_item' ) ) {
			$cart->remove_cart_item( $key );
			return;
		}

		if ( property_exists( $cart, 'cart_contents' ) && is_array( $cart->cart_contents ) ) {
			unset( $cart->cart_contents[ $key ] );
		}
	}

	/**
	 * The discount this line may carry right now, or null for none.
	 *
	 * @param string                              $key      Cart item key.
	 * @param array<string, mixed>                $item     Cart item.
	 * @param array<string, array<string, mixed>> $items    All cart items.
	 * @param string                              $subtotal Rule subtotal of the cart's non-offer lines.
	 * @param string                              $regular  Current regular unit price.
	 * @return array<string, string>|null
	 */
	private function current_discount( string $key, array $item, array $items, string $subtotal, string $regular ): ?array {
		$offer = ( $this->offer_loader )( (int) $item[ Constants::ATTRIBUTION_OFFER_ID ] );
		if ( ! is_array( $offer ) || 'publish' !== (string) ( $offer['status'] ?? '' ) ) {
			return null;
		}

		$meta      = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$placement = (string) $item[ Constants::ATTRIBUTION_OFFER_PLACEMENT ];

		// The merchant may have pointed the offer at another product since.
		$offered = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		if ( $offered <= 0 || ! in_array( $offered, array( (int) ( $item['product_id'] ?? 0 ), (int) ( $item['variation_id'] ?? 0 ) ), true ) ) {
			return null;
		}

		if ( ! ( $this->placement_available )( $placement ) ) {
			return null;
		}

		$context = 'thankyou_offer' === $placement
			? $this->source_order_context( $item )
			: $this->cart_context( $key, $item, $items, $subtotal );
		if ( null === $context ) {
			return null;
		}

		$evaluation = $this->eligibility->evaluate( $offer, $placement, $context );
		$reasons    = array_diff( (array) ( $evaluation['reasons'] ?? array( 'unknown' ) ), self::DISPLAY_ONLY_REASONS );
		if ( array() !== $reasons ) {
			return null;
		}

		return $this->discounts->calculate( $regular, $meta );
	}

	/**
	 * Rule context from the rest of the cart, as the accept route saw it.
	 *
	 * The line itself is left out: it was not in the cart when the offer was
	 * accepted, so its product and price must not count toward the offer's own
	 * triggers or subtotal rules.
	 *
	 * @param string                              $key      Cart item key.
	 * @param array<string, mixed>                $item     Cart item.
	 * @param array<string, array<string, mixed>> $items    All cart items.
	 * @param string                              $subtotal Rule subtotal of the cart's non-offer lines.
	 * @return array<string, mixed>
	 */
	private function cart_context( string $key, array $item, array $items, string $subtotal ): array {
		$context = array(
			'source_context'      => (string) $item[ Constants::ATTRIBUTION_OFFER_PLACEMENT ],
			'cart_product_ids'    => array(),
			'cart_category_ids'   => array(),
			'cart_tag_ids'        => array(),
			'viewed_category_ids' => array(),
			'viewed_tag_ids'      => array(),
			'cart_subtotal'       => $subtotal,
		);

		foreach ( $items as $other_key => $other ) {
			$product_id = is_array( $other ) ? (int) ( $other['product_id'] ?? 0 ) : 0;
			if ( (string) $other_key === $key || $product_id <= 0 || self::is_pro_line( $other ) ) {
				continue;
			}

			$context['cart_product_ids'][] = $product_id;
			$context['cart_category_ids']  = array_merge( $context['cart_category_ids'], $this->term_ids( $product_id, 'product_cat' ) );
			$context['cart_tag_ids']       = array_merge( $context['cart_tag_ids'], $this->term_ids( $product_id, 'product_tag' ) );
		}

		// A product page offer was accepted from the product it is shown on; the
		// accept route checked that product against the offer's triggers and the
		// line keeps it, so a trigger change by the merchant still takes effect.
		$viewed = (int) ( $item['_ub_viewed_product_id'] ?? 0 );
		if ( $viewed > 0 ) {
			$context['viewed_product_id']   = $viewed;
			$context['viewed_category_ids'] = $this->term_ids( $viewed, 'product_cat' );
			$context['viewed_tag_ids']      = $this->term_ids( $viewed, 'product_tag' );
		}

		return array_merge( $context, $this->customer_context() );
	}

	/**
	 * Rule context from the thank-you offer's source order, as the accept route saw it.
	 *
	 * @param array<string, mixed> $item Cart item.
	 * @return array<string, mixed>|null Null when the source order is missing or void.
	 */
	private function source_order_context( array $item ): ?array {
		$order_id = (int) ( $item[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] ?? 0 );
		$order    = $order_id > 0 ? ( $this->order_loader )( $order_id ) : null;
		if ( ! is_object( $order ) ) {
			return null;
		}

		$status = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		if ( in_array( $status, self::VOID_ORDER_STATUSES, true ) ) {
			return null;
		}

		return array_replace(
			OrderContext::build( $order ),
			array(
				'source_context'      => 'thankyou',
				'source_order_id'     => $order_id,
				'source_order_status' => $status,
			)
		);
	}

	/**
	 * Shopper facts used by customer rules, as the accept route reads them.
	 *
	 * @return array<string, mixed>
	 */
	private function customer_context(): array {
		$context = array();

		if ( function_exists( 'wp_get_current_user' ) ) {
			$context['user_roles'] = (array) wp_get_current_user()->roles;
		}

		$customer_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $customer_id > 0 ) {
			$context['customer_order_count']    = function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $customer_id ) : 0;
			$context['customer_lifetime_spend'] = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $customer_id ) : '0';
		}

		return $context;
	}

	/**
	 * The cart subtotal that cart_subtotal rules are judged against.
	 *
	 * Sum of unit price x quantity over every line except Pro offer lines. Offer
	 * lines are left out so an accepted offer never counts toward its own (or
	 * another offer's) threshold, and so the figure does not depend on which
	 * offer lines a totals pass has already repriced. Read from the lines, not
	 * WC_Cart::get_subtotal(): the accept route and the totals hook must agree,
	 * and inside woocommerce_before_calculate_totals the cart totals are reset to 0.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed, mixed> $items Cart items (WC_Cart::get_cart()).
	 * @return string Decimal string.
	 */
	public static function rule_subtotal( array $items ): string {
		$total = 0.0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || self::is_pro_line( $item ) ) {
				continue;
			}

			$product = $item['data'] ?? null;
			$price   = is_object( $product ) && method_exists( $product, 'get_price' ) ? (string) $product->get_price() : '';
			if ( ! is_numeric( $price ) ) {
				continue;
			}

			$total += (float) $price * max( 0, (int) ( $item['quantity'] ?? 0 ) );
		}

		return sprintf( '%.6F', max( 0.0, $total ) );
	}

	/**
	 * Whether a cart line was added by Pro's offer route.
	 *
	 * @param array<string, mixed> $item Cart item.
	 */
	private static function is_pro_line( array $item ): bool {
		return (int) ( $item[ Constants::ATTRIBUTION_OFFER_ID ] ?? 0 ) > 0
			&& in_array( (string) ( $item[ Constants::ATTRIBUTION_OFFER_PLACEMENT ] ?? '' ), self::PRO_PLACEMENTS, true );
	}

	/**
	 * The product (or variation) a cart line holds.
	 *
	 * @param array<string, mixed> $item Cart item.
	 */
	private function line_product_id( array $item ): int {
		$variation_id = (int) ( $item['variation_id'] ?? 0 );

		return $variation_id > 0 ? $variation_id : (int) ( $item['product_id'] ?? 0 );
	}

	/**
	 * Product term IDs, cached for this totals run.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy.
	 * @return array<int, int>
	 */
	private function term_ids( int $product_id, string $taxonomy ): array {
		$cache_key = $taxonomy . ':' . $product_id;
		if ( isset( $this->terms[ $cache_key ] ) ) {
			return $this->terms[ $cache_key ];
		}

		$terms = function_exists( 'wp_get_post_terms' ) ? wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) ) : array();

		$this->terms[ $cache_key ] = is_array( $terms ) ? array_values( array_filter( array_map( 'intval', $terms ) ) ) : array();

		return $this->terms[ $cache_key ];
	}

	/**
	 * Default regular price resolver.
	 *
	 * Reads a fresh product object rather than the cart line's own `data`:
	 * WooCommerce runs totals more than once per request against the same cart
	 * item objects, so `data` may already carry a price set on an earlier pass.
	 *
	 * @param int $product_id Product or variation ID.
	 */
	private function wc_regular_price( int $product_id ): ?string {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return null;
		}

		$price = (string) $product->get_price();

		return is_numeric( $price ) && (float) $price >= 0 ? $price : null;
	}
}
