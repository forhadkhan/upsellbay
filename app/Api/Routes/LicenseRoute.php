<?php
/**
 * License REST routes.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api\Routes;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for license activation and status.
 *
 * @since 1.0.0
 */
final class LicenseRoute {

	/**
	 * License client instance.
	 *
	 * @since 1.0.0
	 *
	 * @var LicenseClient
	 */
	private LicenseClient $client;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LicenseClient $client License client instance.
	 */
	public function __construct( LicenseClient $client ) {
		$this->client = $client;
	}

	/**
	 * Register the REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$capability_check = array( $this, 'check_permission' );

		register_rest_route(
			'upsellbay/v1',
			'/license/activate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => $capability_check,
			)
		);

		register_rest_route(
			'upsellbay/v1',
			'/license/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $capability_check,
			)
		);

		register_rest_route(
			'upsellbay/v1',
			'/license/deactivate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deactivate' ),
				'permission_callback' => $capability_check,
			)
		);
	}

	/**
	 * Check that the current user can manage license settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage license settings.', 'upsellbay' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Activate a license key.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$key = sanitize_text_field( $request->get_param( 'license_key' ) );

		if ( '' === $key ) {
			return new WP_Error(
				'missing_key',
				__( 'License key is required.', 'upsellbay' ),
				array( 'status' => 422 )
			);
		}

		$result = $this->client->activate( $key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $this->client->get_status(),
			),
			200
		);
	}

	/**
	 * Get the current license status.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->client->get_status(), 200 );
	}

	/**
	 * Deactivate the license locally.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function deactivate( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$this->client->remove_local();
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
