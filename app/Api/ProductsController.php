<?php
/**
 * Products controller for REST API.
 *
 * @package UpsellBay\Api
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WP_REST_Request;
use WP_REST_Response;
use WPAnchorBay\UpsellBay\Domain\Offers\ProductRecommendationAssistant;

/**
 * Handles product and category searching for the admin UI.
 *
 * @since 1.0.0
 */
final class ProductsController {

	/**
	 * Recommendation assistant.
	 *
	 * @var ProductRecommendationAssistant|null
	 */
	private ?ProductRecommendationAssistant $assistant;

	/**
	 * Constructor.
	 *
	 * @param ProductRecommendationAssistant|null $assistant Recommendation assistant.
	 */
	public function __construct( ?ProductRecommendationAssistant $assistant = null ) {
		$this->assistant = $assistant;
	}

	/**
	 * Search products.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$search  = (string) $request->get_param( 'search' );
		$include = (string) $request->get_param( 'include' );
		$sku     = (string) $request->get_param( 'sku' );
		$limit   = (int) $request->get_param( 'limit' );
		$limit   = $limit > 0 ? $limit : 10;
		$page    = (int) $request->get_param( 'page' );
		$page    = $page > 0 ? $page : 1;

		$args = array(
			'limit'   => $limit,
			'page'    => $page,
			'status'  => 'publish',
			'orderby' => ( '' === $search && '' === $include && '' === $sku ) ? 'date' : 'title',
			'order'   => ( '' === $search && '' === $include && '' === $sku ) ? 'DESC' : 'ASC',
		);

		if ( '' !== $include ) {
			$args['include'] = array( (int) $include );
			$args['limit']   = 1;
		} elseif ( '' !== $sku ) {
			// SKU prefix matching as described in reference.
			unset( $args['page'] );
			$args['limit'] = 200;
			$all_products  = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();

			$data      = array();
			$sku_lower = strtolower( sanitize_text_field( $sku ) );

			foreach ( $all_products as $product ) {
				$product_sku = strtolower( $product->get_sku() );
				if ( $product_sku === $sku_lower || str_starts_with( $product_sku, $sku_lower ) ) {
					$data[] = $this->format_product( $product );
				}
			}

			$offset = ( $page - 1 ) * $limit;
			return new WP_REST_Response( array_slice( $data, $offset, $limit ) );
		} elseif ( '' !== $search ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$products = function_exists( 'wc_get_products' ) ? wc_get_products( $args ) : array();
		$data     = array();

		foreach ( $products as $product ) {
			$data[] = $this->format_product( $product );
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * List categories.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function categories( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->terms( $request, 'product_cat' ) );
	}

	/**
	 * List product tags.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function tags( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->terms( $request, 'product_tag' ) );
	}

	/**
	 * List editable user roles.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function roles( WP_REST_Request $request ): WP_REST_Response {
		$search = strtolower( sanitize_text_field( (string) $request->get_param( 'search' ) ) );
		$roles  = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();

		if ( array() === $roles && function_exists( 'wp_roles' ) ) {
			$wp_roles = wp_roles();
			$roles    = $wp_roles->roles;
		}

		$data = array();
		foreach ( $roles as $role_key => $role ) {
			$name = (string) ( $role['name'] ?? $role_key );
			if ( '' !== $search && ! str_contains( strtolower( $role_key . ' ' . $name ), $search ) ) {
				continue;
			}

			$data[] = array(
				'id'   => sanitize_key( (string) $role_key ),
				'name' => $name,
			);
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Search product terms.
	 *
	 * @param WP_REST_Request $request  Request object.
	 * @param string          $taxonomy Product taxonomy.
	 * @return array<int, array<string, mixed>>
	 */
	private function terms( WP_REST_Request $request, string $taxonomy ): array {
		$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$include = (int) $request->get_param( 'include' );
		$limit   = (int) $request->get_param( 'limit' );
		$limit   = $limit > 0 ? $limit : 20;

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $limit,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( $include > 0 ) {
			$args['include'] = array( $include );
		} elseif ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = function_exists( 'get_terms' ) ? get_terms( $args ) : array();

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
			return array();
		}

		$data = array();
		foreach ( $terms as $term ) {
			$data[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'count' => $term->count,
				'slug'  => $term->slug,
			);
		}

		return $data;
	}

	/**
	 * Format product for response.
	 *
	 * @param mixed $product WooCommerce product.
	 * @return array<string, mixed>
	 */
	private function format_product( $product ): array {
		$image_url         = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
		$price_min         = null;
		$price_max         = null;
		$regular_min       = null;
		$regular_max       = null;
		$regular_price_max = null;

		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_prices' ) ) {
			$variation_prices = $product->get_variation_prices( false );
			if ( isset( $variation_prices['price'] ) && array() !== $variation_prices['price'] && is_array( $variation_prices['price'] ) ) {
				$variation_min = min( $variation_prices['price'] );
				$variation_max = max( $variation_prices['price'] );
				if ( is_numeric( $variation_min ) ) {
					$price_min = (string) $variation_min;
				}
				if ( is_numeric( $variation_max ) ) {
					$price_max = (string) $variation_max;
				}
			}
			if ( isset( $variation_prices['regular_price'] ) && array() !== $variation_prices['regular_price'] && is_array( $variation_prices['regular_price'] ) ) {
				$regular_variation_min = min( $variation_prices['regular_price'] );
				$regular_variation_max = max( $variation_prices['regular_price'] );
				if ( is_numeric( $regular_variation_min ) ) {
					$regular_min = (string) $regular_variation_min;
				}
				if ( is_numeric( $regular_variation_max ) ) {
					$regular_max = (string) $regular_variation_max;
				}
			}
		}

		if ( null === $price_min || null === $price_max ) {
			$price_min = (string) $product->get_price();
			$price_max = $price_min;
		}

		if ( null === $regular_min || null === $regular_max ) {
			$regular_price = method_exists( $product, 'get_regular_price' ) ? (string) $product->get_regular_price() : '';
			if ( '' === $regular_price ) {
				$regular_price     = $price_min;
				$regular_price_max = $price_max;
			} else {
				$regular_price_max = $regular_price;
			}
			$regular_min = $regular_price;
			$regular_max = null !== $regular_price_max ? $regular_price_max : $regular_price;
		}

		return array(
			'id'                 => $product->get_id(),
			'name'               => $product->get_name(),
			'sku'                => $product->get_sku(),
			'price'              => $product->get_price_html(),
			'price_raw'          => (string) $product->get_price(),
			'regular_price_raw'  => (string) $regular_min,
			'base_price_raw'     => (string) $regular_min,
			'price_min_raw'      => $price_min,
			'price_max_raw'      => $price_max,
			'base_price_min_raw' => $regular_min,
			'base_price_max_raw' => $regular_max,
			'image'              => false !== $image_url ? $image_url : '',
		);
	}

	/**
	 * Get product recommendations.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function recommendations( WP_REST_Request $request ): WP_REST_Response {
		if ( null === $this->assistant ) {
			return new WP_REST_Response( array() );
		}

		$base_product_id = (int) $request->get_param( 'base_product_id' );
		if ( $base_product_id <= 0 ) {
			return new WP_REST_Response( array() );
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $base_product_id ) : null;
		if ( ! $product ) {
			return new WP_REST_Response( array() );
		}

		$category_ids = $product->get_category_ids();

		$suggestions = $this->assistant->suggest(
			array(
				'base_product_id' => $base_product_id,
				'category_ids'    => $category_ids,
				'limit'           => 5,
			)
		);

		$data = array();
		foreach ( $suggestions as $suggestion ) {
			$sugg_product = function_exists( 'wc_get_product' ) ? wc_get_product( $suggestion['product_id'] ) : null;
			if ( $sugg_product ) {
				$formatted           = $this->format_product( $sugg_product );
				$formatted['source'] = $suggestion['source'];
				$formatted['reason'] = $suggestion['reason'];
				$data[]              = $formatted;
			}
		}

		return new WP_REST_Response( $data );
	}
}
