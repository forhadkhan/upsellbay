<?php
/**
 * Products AJAX handler.
 *
 * @package UpsellBay\Api
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api;

/**
 * Handles product searching via admin-ajax.php.
 *
 * @since 1.0.0
 */
final class ProductsAjax {

	/**
	 * Register AJAX hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_ajax_upsellbay_search_products', array( $this, 'search_products' ) );
		}
	}

	/**
	 * Search products AJAX callback.
	 *
	 * @since 1.0.0
	 */
	public function search_products(): void {
		if ( ! function_exists( 'check_ajax_referer' ) || ! function_exists( 'current_user_can' ) ) {
			wp_send_json_error( 'Missing dependencies.' );
		}

		check_ajax_referer( 'upsellbay_admin_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_send_json_error( 'Unauthorized.' );
		}

		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page   = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$limit  = 10;
		$offset = ( $page - 1 ) * $limit;

		$product_ids = array();

		if ( '' !== $search ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$query = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
				 WHERE p.post_type = 'product'
				   AND p.post_status = 'publish'
				   AND ( p.post_title LIKE %s OR pm.meta_value LIKE %s )
				 ORDER BY p.post_title ASC
				 LIMIT %d OFFSET %d",
				$like,
				$like,
				$limit,
				$offset
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_col( $query );
			if ( is_array( $results ) ) {
				$product_ids = array_map( 'intval', $results );
			}
		} else {
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'product' AND post_status = 'publish'
				 ORDER BY post_date DESC
				 LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_col( $query );
			if ( is_array( $results ) ) {
				$product_ids = array_map( 'intval', $results );
			}
		}

		$data = array();

		if ( array() !== $product_ids && function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'include' => $product_ids,
					'limit'   => -1,
					'orderby' => 'post__in',
				)
			);

			foreach ( $products as $product ) {
				$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
				$data[]    = array(
					'id'    => $product->get_id(),
					'name'  => $product->get_name(),
					'sku'   => $product->get_sku(),
					'price' => $product->get_price_html(),
					'image' => false !== $image_url ? $image_url : '',
				);
			}
		}

		wp_send_json_success(
			array(
				'products' => $data,
				'has_more' => count( $data ) === $limit,
			)
		);
	}
}
