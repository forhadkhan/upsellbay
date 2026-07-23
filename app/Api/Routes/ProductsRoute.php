<?php
/**
 * Products route registration.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Api\Routes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Api\ProductsController;
use WPAnchorBay\UpsellBayPro\Core\Constants;

/**
 * Registers products and categories endpoints.
 *
 * @since 1.0.0
 */
final class ProductsRoute {

	/**
	 * Products controller.
	 *
	 * @var ProductsController
	 */
	private ProductsController $controller;

	/**
	 * Constructor.
	 *
	 * @param ProductsController $controller Controller instance.
	 */
	public function __construct( ProductsController $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		// The free host owns the shared /products, /categories, /tags, and /roles
		// search endpoints used by the shared offer editor. Pro registers only its
		// own recommendations endpoint here to avoid duplicate route registration.
		register_rest_route(
			Constants::REST_NAMESPACE,
			'/recommendations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->controller, 'recommendations' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 */
	public function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}
}
