<?php
/**
 * Products route registration.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api\Routes;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Api\ProductsController;
use WPAnchorBay\UpsellBay\Core\Constants;

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

		register_rest_route(
			Constants::REST_NAMESPACE,
			'/products',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->controller, 'index' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			Constants::REST_NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->controller, 'categories' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

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
