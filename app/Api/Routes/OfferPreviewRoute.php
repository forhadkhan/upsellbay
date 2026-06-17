<?php
/**
 * Admin offer preview route.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api\Routes;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;

/**
 * Returns safe preview payloads for offer editor requests.
 *
 * @since 1.0.0
 */
final class OfferPreviewRoute {
	/**
	 * Offer service.
	 *
	 * @var OfferService
	 */
	private OfferService $offers;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferService $offers Offer service.
	 */
	public function __construct( OfferService $offers ) {
		$this->offers = $offers;
	}

	/**
	 * Register route.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			Constants::REST_NAMESPACE,
			'/offer-preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_preview' ),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @since 1.0.0
	 */
	public function can_preview(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}

	/**
	 * Return a preview payload.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request.
	 * @return array<string, mixed>
	 */
	public function preview( $request ): array {
		$params   = is_object( $request ) && method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		$offer_id = (int) ( is_array( $params ) ? ( $params['offer_id'] ?? 0 ) : 0 );
		$offer    = $this->offers->get( $offer_id );

		if ( null === $offer ) {
			return array(
				'status' => 404,
				'data'   => array( 'message' => 'Offer not found.' ),
			);
		}

		return array(
			'status' => 200,
			'data'   => $this->offers->preview_payload( $offer ),
		);
	}
}
