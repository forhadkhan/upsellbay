<?php
/**
 * Public offer interaction routes.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Api\Routes;

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Hooks;
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
				'args'                => array(
					'offer_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'checkout_bump',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $value ) => in_array(
							sanitize_key( $value ),
							array( 'checkout_bump', 'cart_crosssell', 'product_upsell', 'thankyou_offer' ),
							true
						),
					),
					'accepted'  => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'token'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			Constants::REST_NAMESPACE,
			'/cart-offer-add',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cart_offer_add' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'offer_id'        => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement'       => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'cart_crosssell',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $value ) => in_array(
							sanitize_key( $value ),
							array( 'checkout_bump', 'cart_crosssell', 'product_upsell', 'thankyou_offer' ),
							true
						),
					),
					'source_order_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'token'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			Constants::REST_NAMESPACE,
			'/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'offer_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'token'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Toggle a checkout bump.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request or array.
	 * @return array<string, mixed>|\WP_REST_Response
	 */
	public function bump_toggle( $request ) {
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
					'message' => __( 'Offer not found.', 'upsellbay' ),
				)
			);
		}

		$result = true === ( $params['accepted'] ?? false )
			? $this->cart->accept( $offer, (string) ( $params['placement'] ?? 'checkout_bump' ), array( 'source_context' => 'checkout' ) )
			: $this->cart->remove( (int) $params['offer_id'] );

		if ( true === ( $params['accepted'] ?? false ) && true === ( $result['success'] ?? false ) ) {
			$this->analytics->record_event( 'accept', (int) $params['offer_id'], (string) ( $params['placement'] ?? 'checkout_bump' ), ( $this->date_provider )(), (string) ( $result['offer_price'] ?? '0.000000' ), (string) ( $result['discount_amount'] ?? '0.000000' ) );
			Hooks::action( 'offer_accepted', (int) $params['offer_id'], (string) ( $params['placement'] ?? 'checkout_bump' ), $result );
		}

		if ( true !== ( $result['success'] ?? false ) && isset( $result['errors'] ) ) {
			$result['message'] = $this->error_message( (array) $result['errors'] );
		}

		return $this->response( true === ( $result['success'] ?? false ) ? 200 : 400, $result + array( 'notices' => array() ) );
	}

	/**
	 * Add a product-page or cart offer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request or array.
	 * @return array<string, mixed>|\WP_REST_Response
	 */
	public function cart_offer_add( $request ) {
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
					'message' => __( 'Offer not found.', 'upsellbay' ),
				)
			);
		}

		$placement = (string) ( $params['placement'] ?? 'cart_crosssell' );
		$result    = $this->cart->accept(
			$offer,
			$placement,
			array(
				'source_context'  => $placement,
				'source_order_id' => (int) ( $params['source_order_id'] ?? 0 ),
			)
		);

		if ( true === ( $result['success'] ?? false ) ) {
			$this->analytics->record_event( 'accept', (int) $params['offer_id'], $placement, ( $this->date_provider )(), (string) ( $result['offer_price'] ?? '0.000000' ), (string) ( $result['discount_amount'] ?? '0.000000' ) );
			Hooks::action( 'offer_accepted', (int) $params['offer_id'], $placement, $result );
			$result['message'] = __( 'Offer added to your cart.', 'upsellbay' );
		} elseif ( isset( $result['errors'] ) ) {
			$result['message'] = $this->error_message( (array) $result['errors'] );
		}

		return $this->response( true === ( $result['success'] ?? false ) ? 200 : 400, $result + array( 'notices' => array() ) );
	}

	/**
	 * Map internal validator errors to safe shopper-facing messages.
	 *
	 * @param array<int, string> $errors Validator error keys.
	 */
	private function error_message( array $errors ): string {
		$first = $errors[0] ?? 'unknown';

		return match ( $first ) {
			'product_in_stock'     => __( 'This product is currently out of stock.', 'upsellbay' ),
			'product_purchasable'  => __( 'This product is not currently available for purchase.', 'upsellbay' ),
			'missing_product'      => __( 'This product could not be found.', 'upsellbay' ),
			'rules_failed'         => __( 'You are no longer eligible for this offer.', 'upsellbay' ),
			'placement_mismatch'   => __( 'This offer is not valid for this placement.', 'upsellbay' ),
			'subscription_discount_blocked' => __( 'Discounts cannot be applied to subscription products.', 'upsellbay' ),
			default                => __( 'Unable to add this offer. Please try again.', 'upsellbay' ),
		};
	}

	/**
	 * Dismiss an offer for the current session.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request or array.
	 * @return array<string, mixed>|\WP_REST_Response
	 */
	public function dismiss( $request ) {
		$params = $this->params( $request );
		$guard  = $this->guard( 'dismiss', $params );
		if ( null !== $guard ) {
			return $guard;
		}

		$offer_id  = (int) $params['offer_id'];
		$placement = (string) ( $params['placement'] ?? '' );
		$this->session->dismiss_offer( $offer_id, $placement );
		$this->analytics->record_event( 'dismiss', $offer_id, $placement, ( $this->date_provider )() );
		Hooks::action( 'offer_dismissed', $offer_id, $placement );

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
	 * @return array<string, mixed>|\WP_REST_Response|null
	 */
	private function guard( string $endpoint, array $params ) {
		$client_key = (string) ( $params['token'] ?? $params['_wpnonce'] ?? 'guest' );
		if ( ! isset( $params['token'] ) || '' === (string) $params['token'] || ! $this->session->validate_token( (string) $params['token'] ) ) {
			Hooks::action( 'api_request_failed', $endpoint, 'invalid_token', $params );
			return $this->response(
				403,
				array(
					'success' => false,
					'message' => __( 'Invalid session token.', 'upsellbay' ),
				)
			);
		}

		if ( ! ( $this->rate_limit )( $endpoint, $client_key ) ) {
			Hooks::action( 'api_request_failed', $endpoint, 'rate_limited', $params );
			return $this->response(
				429,
				array(
					'success' => false,
					'message' => __( 'Too many requests.', 'upsellbay' ),
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
	 * @return array<string, mixed>|\WP_REST_Response
	 */
	private function response( int $status, array $data ) {
		if ( class_exists( '\WP_REST_Response' ) ) {
			return new \WP_REST_Response( $data, $status );
		}

		return array(
			'status' => $status,
			'data'   => $data,
		);
	}
}
