<?php
/**
 * Public offer interaction routes.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api\Routes;

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBay\Domain\Cart\CartMutator;

/**
 * Shopper-safe public REST route handlers.
 *
 * @since 1.0.0
 */
final class PublicOfferRoutes {
	/**
	 * Offer loader callback.
	 *
	 * @var callable(int): ?array<string, mixed>
	 */
	private $offer_loader;

	/**
	 * Cart mutator.
	 *
	 * @var CartMutator
	 */
	private CartMutator $cart;

	/**
	 * Cart session.
	 *
	 * @var CartSession
	 */
	private CartSession $session;

	/**
	 * Rate-limit callback.
	 *
	 * @var callable(string, string): bool
	 */
	private $rate_limit;

	/**
	 * Date callback.
	 *
	 * @var callable(): string
	 */
	private $date_provider;

	/**
	 * Analytics service.
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable         $offer_loader  Offer loader.
	 * @param CartMutator      $cart          Cart mutator.
	 * @param CartSession      $session       Cart session.
	 * @param callable         $rate_limit    Rate limiter.
	 * @param callable|null    $date_provider Date callback.
	 * @param AnalyticsService $analytics     Analytics service.
	 */
	public function __construct( callable $offer_loader, CartMutator $cart, CartSession $session, callable $rate_limit, ?callable $date_provider, AnalyticsService $analytics ) {
		$this->offer_loader  = $offer_loader;
		$this->cart          = $cart;
		$this->session       = $session;
		$this->rate_limit    = $rate_limit;
		$this->date_provider = $date_provider ?? static fn (): string => gmdate( 'Y-m-d' );
		$this->analytics     = $analytics;
	}

	/**
	 * Register WordPress REST routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			Constants::REST_NAMESPACE,
			'/bump-toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bump_toggle' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Constants::REST_NAMESPACE,
			'/cart-offer-add',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cart_offer_add' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Constants::REST_NAMESPACE,
			'/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Toggle a checkout bump.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request or array.
	 * @return array<string, mixed>
	 */
	public function bump_toggle( $request ): array {
		$params = $this->params( $request );
		$guard  = $this->guard( 'bump_toggle', $params );
		if ( null !== $guard ) {
			return $guard;
		}

		$offer = ( $this->offer_loader )( (int) $params['offer_id'] );
		if ( null === $offer ) {
			return $this->response(
				404,
				array(
					'success' => false,
					'message' => 'Offer not found.',
				)
			);
		}

		if ( true !== ( $params['accepted'] ?? false ) ) {
			$result = $this->cart->remove( (int) $params['offer_id'] );
		} else {
			$result = $this->cart->accept( $offer, (string) ( $params['placement'] ?? 'checkout_bump' ), array( 'source_context' => 'checkout' ) );
		}

		if ( true === ( $result['success'] ?? false ) ) {
			$this->analytics->record_event( 'accept', (int) $params['offer_id'], (string) ( $params['placement'] ?? 'checkout_bump' ), ( $this->date_provider )(), (string) ( $result['offer_price'] ?? '0.000000' ) );
		}

		return $this->response( true === ( $result['success'] ?? false ) ? 200 : 400, $result + array( 'notices' => array() ) );
	}

	/**
	 * Add a product-page or cart offer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request or array.
	 * @return array<string, mixed>
	 */
	public function cart_offer_add( $request ): array {
		$params = $this->params( $request );
		$guard  = $this->guard( 'cart_offer_add', $params );
		if ( null !== $guard ) {
			return $guard;
		}

		$offer = ( $this->offer_loader )( (int) $params['offer_id'] );
		if ( null === $offer ) {
			return $this->response(
				404,
				array(
					'success' => false,
					'message' => 'Offer not found.',
				)
			);
		}

		$placement = (string) ( $params['placement'] ?? 'cart_crosssell' );
		$result    = $this->cart->accept( $offer, $placement, array( 'source_context' => $placement ) );

		if ( true === ( $result['success'] ?? false ) ) {
			$this->analytics->record_event( 'accept', (int) $params['offer_id'], $placement, ( $this->date_provider )(), (string) ( $result['offer_price'] ?? '0.000000' ) );
		}

		return $this->response( true === ( $result['success'] ?? false ) ? 200 : 400, $result + array( 'notices' => array() ) );
	}

	/**
	 * Dismiss an offer for the current session.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request or array.
	 * @return array<string, mixed>
	 */
	public function dismiss( $request ): array {
		$params = $this->params( $request );
		$guard  = $this->guard( 'dismiss', $params );
		if ( null !== $guard ) {
			return $guard;
		}

		$offer_id  = (int) $params['offer_id'];
		$placement = (string) ( $params['placement'] ?? '' );
		$this->session->dismiss_offer( $offer_id, $placement );
		$this->analytics->record_event( 'dismiss', $offer_id, $placement, ( $this->date_provider )() );

		return $this->response(
			200,
			array(
				'success' => true,
				'notices' => array(),
			)
		);
	}

	/**
	 * Run shared public endpoint guards.
	 *
	 * @param string               $endpoint Endpoint key.
	 * @param array<string, mixed> $params   Params.
	 * @return array<string, mixed>|null
	 */
	private function guard( string $endpoint, array $params ): ?array {
		$client_key = (string) ( $params['token'] ?? $params['_wpnonce'] ?? 'guest' );
		if ( ! isset( $params['token'] ) || '' === (string) $params['token'] || ! $this->session->validate_token( (string) $params['token'] ) ) {
			return $this->response(
				403,
				array(
					'success' => false,
					'message' => 'Invalid session token.',
				)
			);
		}

		if ( ! ( $this->rate_limit )( $endpoint, $client_key ) ) {
			return $this->response(
				429,
				array(
					'success' => false,
					'message' => 'Too many requests.',
				)
			);
		}

		if ( (int) ( $params['offer_id'] ?? 0 ) <= 0 ) {
			return $this->response(
				400,
				array(
					'success' => false,
					'message' => 'Invalid offer.',
				)
			);
		}

		return null;
	}

	/**
	 * Normalize request params.
	 *
	 * @param mixed $request Request.
	 * @return array<string, mixed>
	 */
	private function params( $request ): array {
		if ( is_array( $request ) ) {
			return $request;
		}

		if ( is_object( $request ) && method_exists( $request, 'get_params' ) ) {
			$params = $request->get_params();
			return is_array( $params ) ? $params : array();
		}

		return array();
	}

	/**
	 * Build a response array.
	 *
	 * @param int                  $status Status.
	 * @param array<string, mixed> $data   Data.
	 * @return array<string, mixed>
	 */
	private function response( int $status, array $data ): array {
		return array(
			'status' => $status,
			'data'   => $data,
		);
	}
}
