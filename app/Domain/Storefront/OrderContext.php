<?php
/**
 * Rule context built from a verified source order.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\Storefront;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the rule context for thank-you offers from the order that was just placed.
 *
 * The thank-you page shows an offer and a later REST call claims it. Both must
 * evaluate the same rules against the same facts, so they share this builder:
 * order items drive product, category, tag and subtotal rules, and the order's
 * customer drives order count, lifetime spend and role rules. The cart is empty
 * by then, and the claim request has no logged-in guarantee, so neither can be
 * the source of truth.
 *
 * @since 1.0.0
 */
final class OrderContext {
	/**
	 * Build the item and customer context for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order Verified WooCommerce order (or compatible object).
	 * @return array<string, mixed>
	 */
	public static function build( object $order ): array {
		return array_merge( self::item_context( $order ), self::customer_context( $order ) );
	}

	/**
	 * Product, category, tag and subtotal facts from the order items.
	 *
	 * @param object $order Order.
	 * @return array<string, mixed>
	 */
	private static function item_context( object $order ): array {
		$product_ids  = array();
		$category_ids = array();
		$tag_ids      = array();

		if ( method_exists( $order, 'get_items' ) ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = self::item_product_id( $item );
				if ( $product_id <= 0 ) {
					continue;
				}

				$product_ids[] = $product_id;
				$category_ids  = array_merge( $category_ids, self::term_ids( $product_id, 'product_cat' ) );
				$tag_ids       = array_merge( $tag_ids, self::term_ids( $product_id, 'product_tag' ) );
			}
		}

		return array(
			'cart_product_ids'  => array_values( array_unique( $product_ids ) ),
			'cart_category_ids' => array_values( array_unique( $category_ids ) ),
			'cart_tag_ids'      => array_values( array_unique( $tag_ids ) ),
			'cart_subtotal'     => method_exists( $order, 'get_subtotal' ) ? (string) $order->get_subtotal() : '0',
		);
	}

	/**
	 * Customer facts from the order's customer, not from whoever is browsing.
	 *
	 * A guest order has no customer: roles are empty and the count and spend
	 * fields are left unset, as the live-cart context does for a guest.
	 *
	 * @param object $order Order.
	 * @return array<string, mixed>
	 */
	private static function customer_context( object $order ): array {
		$customer_id = 0;
		if ( method_exists( $order, 'get_customer_id' ) ) {
			$customer_id = (int) $order->get_customer_id();
		} elseif ( method_exists( $order, 'get_user_id' ) ) {
			$customer_id = (int) $order->get_user_id();
		}

		if ( $customer_id <= 0 ) {
			return array( 'user_roles' => array() );
		}

		$roles = array();
		if ( function_exists( 'get_userdata' ) ) {
			$user = get_userdata( $customer_id );
			if ( is_object( $user ) ) {
				$roles = array_values( (array) $user->roles );
			}
		}

		return array(
			'user_roles'              => $roles,
			'customer_order_count'    => function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $customer_id ) : 0,
			'customer_lifetime_spend' => function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $customer_id ) : '0',
		);
	}

	/**
	 * Return the product ID represented by an order item.
	 *
	 * @param mixed $item Order item.
	 */
	private static function item_product_id( $item ): int {
		if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
			return (int) $item->get_product_id();
		}

		if ( is_array( $item ) ) {
			return (int) ( $item['product_id'] ?? 0 );
		}

		return 0;
	}

	/**
	 * Return product term IDs when taxonomy helpers are available.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy name.
	 * @return array<int, int>
	 */
	private static function term_ids( int $product_id, string $taxonomy ): array {
		if ( $product_id <= 0 || ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'intval', $terms ) ) );
	}
}
