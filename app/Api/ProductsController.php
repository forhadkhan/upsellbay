<?php
/**
 * Products controller for REST API.
 *
 * @package UpsellBay\Api
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles product and category searching for the admin UI.
 *
 * @since 1.0.0
 */
final class ProductsController {

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
	 * @return WP_REST_Response
	 */
	public function categories(): WP_REST_Response {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( array() );
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

		return new WP_REST_Response( $data );
	}

	/**
	 * Format product for response.
	 *
	 * @param mixed $product WooCommerce product.
	 * @return array<string, mixed>
	 */
	private function format_product( $product ): array {
		$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
		return array(
			'id'    => $product->get_id(),
			'name'  => $product->get_name(),
			'sku'   => $product->get_sku(),
			'price' => $product->get_price_html(),
			'image' => false !== $image_url ? $image_url : '',
		);
	}
}
