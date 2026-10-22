<?php
/**
 * Public offer interaction routes.
 *
 * @package UpsellBay\Api\Routes
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Api\Routes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Hooks;
use WPAnchorBay\UpsellBayPro\Data\CartSession;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\OfferLineRepricer;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\OneClickCharger;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\OneClickResult;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\OrderContext;
use WPAnchorBay\UpsellBayPro\Utils\ClientIp;
use WPAnchorBay\UpsellBayPro\Utils\TokenHelper;

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
	 * Token helper.
	 *
	 * @var TokenHelper
	 */
	private TokenHelper $tokens;

	/**
	 * One-click post-purchase charger (Pro flagship). Null disables the endpoint.
	 *
	 * @var OneClickCharger|null
	 */
	private ?OneClickCharger $one_click;

	/**
	 * Storefront eligibility evaluator (status, schedule, product, triggers, rules).
	 * Null makes one-click fail closed: no offer is charged without it.
	 *
	 * @var OfferPrioritizer|null
	 */
	private ?OfferPrioritizer $eligibility;

	/**
	 * Default maximum age of a source order that may still be charged in one click.
	 *
	 * @var int
	 */
	private const ONE_CLICK_MAX_ORDER_AGE = 86400;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable              $offer_loader  Offer loader.
	 * @param CartMutator           $cart          Cart mutator.
	 * @param CartSession           $session       Cart session.
	 * @param callable              $rate_limit    Rate limiter.
	 * @param callable|null         $date_provider Date callback.
	 * @param AnalyticsService      $analytics     Analytics service.
	 * @param TokenHelper|null      $tokens        Token helper.
	 * @param OneClickCharger|null  $one_click     One-click charger.
	 * @param OfferPrioritizer|null $eligibility   Offer eligibility evaluator (required for one-click).
	 */
	public function __construct( callable $offer_loader, CartMutator $cart, CartSession $session, callable $rate_limit, ?callable $date_provider, AnalyticsService $analytics, ?TokenHelper $tokens = null, ?OneClickCharger $one_click = null, ?OfferPrioritizer $eligibility = null ) {
		$this->offer_loader  = $offer_loader;
		$this->cart          = $cart;
		$this->session       = $session;
		$this->rate_limit    = $rate_limit;
		$this->date_provider = $date_provider ?? static fn (): string => gmdate( 'Y-m-d' );
		$this->analytics     = $analytics;
		$this->tokens        = $tokens ?? new TokenHelper();
		$this->one_click     = $one_click;
		$this->eligibility   = $eligibility;
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
					'offer_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement'         => array(
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
					'accepted'          => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'token'             => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'viewed_product_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
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
					'offer_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement'         => array(
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
					'source_order_id'   => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'source_order_key'  => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'             => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'viewed_product_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
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
					'offer_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement'         => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'source_order_id'   => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'source_order_key'  => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'             => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'viewed_product_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Register the cart/product/thank-you offer route on the shared REST namespace.
	 *
	 * The free host registers only the checkout-bump and dismiss routes; Pro owns
	 * the cart-offer-add endpoint for its non-bump placements (cart cross-sell,
	 * product upsell, thank-you follow-on), attaching via `upsellbay_rest_routes`.
	 *
	 * @since 1.0.0
	 */
	public function register_cart_offer_add_route(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			Constants::REST_NAMESPACE,
			'/cart-offer-add',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cart_offer_add' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'offer_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement'         => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'cart_crosssell',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $value ) => in_array(
							sanitize_key( $value ),
							OfferLineRepricer::PRO_PLACEMENTS,
							true
						),
					),
					'source_order_id'   => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'source_order_key'  => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'             => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'viewed_product_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Register the Pro one-click accept route on the host's `upsellbay_rest_routes` hook.
	 *
	 * The free host owns `register_routes()` for the shared endpoints; Pro attaches its
	 * extra routes (like this one) via the host's route-registration action, mirroring how
	 * ProductsRoute and the license routes register. No-op unless one-click is switched
	 * on (Constants::one_click_enabled(), off in 1.0.0) and the charger is present.
	 *
	 * @since 1.0.0
	 */
	public function register_one_click_route(): void {
		if ( null === $this->one_click || ! Constants::one_click_enabled() || ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			Constants::REST_NAMESPACE,
			'/one-click-accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'one_click_accept' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'offer_id'         => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ) => absint( $value ) > 0,
					),
					'placement'        => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'thankyou_offer',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static fn( $value ) => 'thankyou_offer' === sanitize_key( $value ),
					),
					'source_order_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'source_order_key' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'            => array(
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

		$placement = (string) ( $params['placement'] ?? 'checkout_bump' );
		$context   = $this->build_request_context( $params, $placement );

		$result = true === ( $params['accepted'] ?? false )
			? $this->accept_result( $this->cart->accept( $offer, $placement, $context ) )
			: $this->cart->remove( (int) $params['offer_id'] );

		if ( true === ( $params['accepted'] ?? false ) && true === ( $result['success'] ?? false ) ) {
			$this->analytics->record_event( 'accept', (int) $params['offer_id'], (string) ( $params['placement'] ?? 'checkout_bump' ), ( $this->date_provider )() );
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

		// Checkout bumps are the free host's: its bump-toggle route owns them.
		if ( ! in_array( $placement, OfferLineRepricer::PRO_PLACEMENTS, true ) ) {
			Hooks::action( 'api_request_failed', 'cart_offer_add', 'invalid_placement', $this->failure_context( $params ) );
			return $this->response(
				400,
				array(
					'success' => false,
					'message' => __( 'This offer is not valid for this placement.', 'upsellbay' ),
					'notices' => array(),
				)
			);
		}

		if ( 'thankyou_offer' === $placement ) {
			$context = $this->source_order_context( $params );
			if ( null === $context ) {
				return $this->response(
					400,
					array(
						'success' => false,
						'message' => __( 'This order could not be verified for the follow-on offer.', 'upsellbay' ),
						'notices' => array(),
					)
				);
			}
		} else {
			$context = $this->build_request_context( $params, $placement );
		}
		$context['dismissed_offer_ids'] = $this->dismissed_offer_ids();

		// Only an offer the storefront would show right now may be claimed: a
		// draft, paused, scheduled-out, dismissed or trigger-mismatched offer is
		// refused even with a valid token and a guessed offer ID.
		if ( array() !== $this->claim_reasons( $offer, $placement, $context ) ) {
			Hooks::action( 'api_request_failed', 'cart_offer_add', 'ineligible_offer', $this->failure_context( $params ) );
			return $this->response(
				400,
				array(
					'success' => false,
					'message' => __( 'This offer is no longer available.', 'upsellbay' ),
					'notices' => array(),
				)
			);
		}

		$accepted = $this->cart->accept(
			$offer,
			$placement,
			$context
		);
		if ( is_wp_error( $accepted ) ) {
			Hooks::action( 'api_request_failed', 'cart_offer_add', 'add_to_cart_failed', $this->failure_context( $params ) );
		}
		$result = $this->accept_result( $accepted );

		if ( true === ( $result['success'] ?? false ) ) {
			$this->analytics->record_event( 'accept', (int) $params['offer_id'], $placement, ( $this->date_provider )() );
			Hooks::action( 'offer_accepted', (int) $params['offer_id'], $placement, $result );
			$result['message'] = __( 'Offer added to your cart.', 'upsellbay' );
		} elseif ( isset( $result['errors'] ) ) {
			$result['message'] = $this->error_message( (array) $result['errors'] );
		}

		return $this->response( true === ( $result['success'] ?? false ) ? 200 : 400, $result + array( 'notices' => array() ) );
	}

	/**
	 * Accept a thank-you offer and charge the saved payment method in one click.
	 *
	 * Falls back (via the `should_fall_back` flag the client honours) to the standard
	 * separate-checkout flow when the order's gateway can't be charged off-session, the
	 * charge is definitely declined, it needs authentication (3DS), the source order is
	 * not paid (pending, on-hold, ...), or the source order is older than the one-click
	 * age limit, so this never strands a shopper. An
	 * unknown outcome (exception, timeout, gateway still processing) is in_progress:
	 * no success and no fallback. An offer that is not eligible for this order is
	 * refused before any charge. Success is reported only for a charged or
	 * verified-paid order.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request.
	 * @return array<string, mixed>|\WP_REST_Response
	 */
	public function one_click_accept( $request ) {
		if ( null === $this->one_click || ! Constants::one_click_enabled() ) {
			return $this->response( 404, array( 'success' => false ) );
		}

		$params = $this->params( $request );
		$guard  = $this->guard( 'one_click_accept', $params );
		if ( null !== $guard ) {
			return $guard;
		}

		$order_id = (int) ( $params['source_order_id'] ?? 0 );
		$order    = ( $order_id > 0 && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;
		if ( ! is_object( $order ) || ! $this->can_use_source_order( $order, (string) ( $params['source_order_key'] ?? '' ) ) ) {
			return $this->response(
				400,
				array(
					'success' => false,
					'message' => __( 'This order could not be verified for the follow-on offer.', 'upsellbay' ),
				)
			);
		}

		// Second bucket per verified source order, so many IPs cannot hammer one order.
		if ( ! ( $this->rate_limit )( 'one_click_accept_order', 'order:' . $order_id ) ) {
			Hooks::action( 'api_request_failed', 'one_click_accept', 'rate_limited', $this->failure_context( $params ) );
			return $this->response(
				429,
				array(
					'success' => false,
					'message' => __( 'Too many requests.', 'upsellbay' ),
				)
			);
		}

		// The order key never expires, so the off-session charge it authorises must:
		// past the age limit the shopper goes through the normal checkout and pays there.
		if ( ! $this->source_order_within_one_click_age( $order ) ) {
			return $this->response(
				200,
				array(
					'success'          => false,
					'status'           => 'expired',
					'should_fall_back' => true,
					'message'          => __( 'This offer can no longer be added in one click. Continue to checkout instead.', 'upsellbay' ),
				)
			);
		}

		// An off-session charge needs a paid source order (is_paid(): processing or
		// completed by default). A pending or on-hold order, for example an authorise-only
		// or not-yet-settled payment, is never charged against; the shopper takes the
		// separate checkout, where they pay explicitly. Fails closed without is_paid().
		if ( ! method_exists( $order, 'is_paid' ) || true !== $order->is_paid() ) {
			Hooks::action( 'api_request_failed', 'one_click_accept', 'source_order_unpaid', $this->failure_context( $params ) );
			return $this->response(
				200,
				array(
					'success'          => false,
					'status'           => OneClickResult::STATUS_UNSUPPORTED,
					'should_fall_back' => true,
					'message'          => __( 'We couldn\'t complete a one-click purchase. Continue to checkout instead.', 'upsellbay' ),
				)
			);
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

		// Fail closed: only an offer the thank-you page would show for this order, and
		// that the cart claim path would accept, may be charged. Price and discount are
		// then computed server-side by the charger from the stored offer and product.
		if ( ! $this->one_click_offer_eligible( $offer, $order ) ) {
			Hooks::action( 'api_request_failed', 'one_click_accept', 'ineligible_offer', $this->failure_context( $params ) );
			return $this->response(
				400,
				array(
					'success' => false,
					'status'  => 'ineligible',
					'message' => __( 'This offer is no longer available.', 'upsellbay' ),
				)
			);
		}

		$result = $this->one_click->charge( $order, $offer, 'thankyou_offer' );

		if ( in_array( $result->status(), array( OneClickResult::STATUS_CHARGED, OneClickResult::STATUS_DUPLICATE ), true ) ) {
			if ( OneClickResult::STATUS_CHARGED === $result->status() ) {
				$this->analytics->record_event( 'accept', (int) $params['offer_id'], 'thankyou_offer', ( $this->date_provider )() );
				Hooks::action( 'offer_accepted', (int) $params['offer_id'], 'thankyou_offer', array( 'one_click_order_id' => $result->order_id() ) );
			}

			return $this->response(
				200,
				array(
					'success'  => true,
					'status'   => $result->status(),
					'order_id' => $result->order_id(),
					'message'  => __( 'Added to your order — no need to check out again.', 'upsellbay' ),
				)
			);
		}

		if ( OneClickResult::STATUS_IN_PROGRESS === $result->status() ) {
			// The outcome is unknown (this attempt or an earlier one): neither success
			// nor a fallback, so the shopper is not invited to pay a second time.
			return $this->response(
				200,
				array(
					'success' => false,
					'status'  => $result->status(),
					'message' => __( 'A payment for this offer is still being processed. Please check your order emails before trying again.', 'upsellbay' ),
				)
			);
		}

		if ( OneClickResult::STATUS_REQUIRES_ACTION === $result->status() ) {
			// No 3DS challenge UI ships for one-click, so the follow-on order was
			// cancelled uncharged and the client secret stays server-side. The shopper
			// completes the purchase through the normal checkout, which can authenticate.
			return $this->response(
				200,
				array(
					'success'          => false,
					'status'           => $result->status(),
					'should_fall_back' => true,
					'message'          => __( 'Your bank needs to verify this payment. Continue to checkout to complete it securely.', 'upsellbay' ),
				)
			);
		}

		// unsupported / declined -> tell the client to fall back to separate checkout.
		return $this->response(
			200,
			array(
				'success'          => false,
				'status'           => $result->status(),
				'should_fall_back' => true,
				'message'          => __( 'We couldn\'t complete a one-click purchase. Continue to checkout instead.', 'upsellbay' ),
			)
		);
	}

	/**
	 * Turn a refused add (WP_Error from CartMutator::accept()) into a failed result.
	 *
	 * @param array<string, mixed>|\WP_Error $result Accept result.
	 * @return array<string, mixed>
	 */
	private function accept_result( $result ): array {
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'errors'  => array( $result->get_error_code() ),
			);
		}

		return $result;
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
	 * Build a cart-like rule context from a verified source order.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return array<string, mixed>|null Null when the order cannot be verified.
	 */
	private function source_order_context( array $params ): ?array {
		$order_id = (int) ( $params['source_order_id'] ?? 0 );
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! $this->can_use_source_order( $order, (string) ( $params['source_order_key'] ?? '' ) ) ) {
			return null;
		}

		return $this->thankyou_context( $order );
	}

	/**
	 * Rule context for a verified source order, shared by the cart claim and the
	 * one-click charge so both evaluate the facts the thank-you page showed against.
	 *
	 * @param object $order Verified source order.
	 * @return array<string, mixed>
	 */
	private function thankyou_context( object $order ): array {
		return array_replace(
			OrderContext::build( $order ),
			array(
				'source_context'      => 'thankyou',
				'source_order_id'     => method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0,
				'source_order_status' => method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '',
			)
		);
	}

	/**
	 * Whether an offer may be charged in one click for this source order.
	 *
	 * Reuses the storefront evaluator (placement, active status, schedule, product
	 * availability, triggers, rules: what decided the offer was shown) and the cart
	 * claim validator (product visibility, subscription block, server-side discount).
	 * Fails closed when the evaluator is missing or the offer post is not published.
	 *
	 * @param array<string, mixed> $offer Offer loaded server-side.
	 * @param object               $order Verified source order.
	 */
	private function one_click_offer_eligible( array $offer, object $order ): bool {
		$context                        = $this->thankyou_context( $order );
		$context['dismissed_offer_ids'] = $this->dismissed_offer_ids();
		if ( array() !== $this->claim_reasons( $offer, 'thankyou_offer', $context ) ) {
			return false;
		}

		return true === ( $this->cart->validate( $offer, 'thankyou_offer', $context )['valid'] ?? false );
	}

	/**
	 * Why an offer may not be claimed now; empty when it may.
	 *
	 * Runs the storefront evaluator (placement, active status, dismissal,
	 * product already in cart, schedule, product availability, triggers, rules:
	 * what decides whether the offer is shown) on a published offer. Fails closed
	 * when the evaluator is missing. A retry of an offer this session already
	 * accepted is not refused for its own product being in the cart, so the
	 * accept stays idempotent (CartMutator::accept() reports the duplicate).
	 *
	 * @param array<string, mixed> $offer     Offer loaded server-side.
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Rule context.
	 * @return array<int, string>
	 */
	private function claim_reasons( array $offer, string $placement, array $context ): array {
		if ( null === $this->eligibility ) {
			return array( 'no_evaluator' );
		}

		if ( 'publish' !== (string) ( $offer['status'] ?? '' ) ) {
			return array( 'not_published' );
		}

		$reasons = (array) ( $this->eligibility->evaluate( $offer, $placement, $context )['reasons'] ?? array( 'unknown' ) );

		$accepted = $this->session->state()['accepted'];
		if ( isset( $accepted[ (int) ( $offer['id'] ?? 0 ) ] ) ) {
			$reasons = array_diff( $reasons, array( 'product_already_in_cart' ) );
		}

		return array_values( $reasons );
	}

	/**
	 * Offer IDs the shopper dismissed in this session.
	 *
	 * @return array<int, int>
	 */
	private function dismissed_offer_ids(): array {
		return array_map( 'intval', array_keys( $this->session->state()['dismissed'] ) );
	}

	/**
	 * The request facts a failed-request hook may see.
	 *
	 * Only the offer and placement: tokens, order IDs and order keys stay out
	 * of the action, so no listener (the Pro log table included) can store them.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return array<string, mixed>
	 */
	private function failure_context( array $params ): array {
		return array(
			'offer_id'  => (int) ( $params['offer_id'] ?? 0 ),
			'placement' => sanitize_key( (string) ( $params['placement'] ?? '' ) ),
		);
	}

	/**
	 * Whether the source order is still young enough for an off-session charge.
	 *
	 * Age runs from the order's creation date. A missing date fails closed. The limit
	 * is filterable through `upsellbay_pro_one_click_max_order_age` (seconds); zero or
	 * less turns one-click off, so every accept falls back to the normal checkout.
	 *
	 * @param object $order Verified source order.
	 */
	private function source_order_within_one_click_age( object $order ): bool {
		$created = method_exists( $order, 'get_date_created' ) ? $order->get_date_created() : null;
		if ( ! is_object( $created ) || ! method_exists( $created, 'getTimestamp' ) ) {
			return false;
		}

		/**
		 * Filter the maximum age, in seconds, of a source order whose thank-you offer
		 * may still be charged in one click. Older orders fall back to the normal checkout.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $max_age Maximum age in seconds. Default 86400 (24 hours).
		 * @param object $order   Source WC_Order.
		 */
		$max_age = (int) Hooks::filter( 'one_click_max_order_age', self::ONE_CLICK_MAX_ORDER_AGE, $order );
		if ( $max_age <= 0 ) {
			return false;
		}

		$age = time() - (int) $created->getTimestamp();

		return $age >= 0 && $age <= $max_age;
	}

	/**
	 * Build a cart-like rule context from the current session.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @param string               $placement Placement key.
	 * @return array<string, mixed>
	 */
	private function build_request_context( array $params, string $placement ): array {
		$context = array(
			'source_context'      => $placement,
			'viewed_product_id'   => (int) ( $params['viewed_product_id'] ?? 0 ),
			'cart_product_ids'    => array(),
			'cart_category_ids'   => array(),
			'cart_tag_ids'        => array(),
			'viewed_category_ids' => array(),
			'viewed_tag_ids'      => array(),
			'cart_subtotal'       => '0',
		);

		if ( $context['viewed_product_id'] > 0 ) {
			$context['viewed_category_ids'] = $this->product_term_ids( $context['viewed_product_id'], 'product_cat' );
			$context['viewed_tag_ids']      = $this->product_term_ids( $context['viewed_product_id'], 'product_tag' );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			// Same figure the totals hook re-checks the accepted line against.
			$context['cart_subtotal'] = OfferLineRepricer::rule_subtotal( WC()->cart->get_cart() );
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = (int) ( $cart_item['product_id'] ?? 0 );
				if ( $product_id > 0 ) {
					$context['cart_product_ids'][] = $product_id;
					$context['cart_category_ids']  = array_merge( $context['cart_category_ids'], $this->product_term_ids( $product_id, 'product_cat' ) );
					$context['cart_tag_ids']       = array_merge( $context['cart_tag_ids'], $this->product_term_ids( $product_id, 'product_tag' ) );
				}
			}
		}

		if ( function_exists( 'wp_get_current_user' ) ) {
			$user                  = wp_get_current_user();
			$context['user_roles'] = $user->roles;
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$customer_id = get_current_user_id();
			if ( $customer_id > 0 ) {
				$context['customer_order_count']    = function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $customer_id ) : 0;
				$context['customer_lifetime_spend'] = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $customer_id ) : '0';
			}
		}

		return $context;
	}

	/**
	 * Confirm the shopper can use a source order for follow-on eligibility.
	 *
	 * @param object $order Source order.
	 * @param string $source_order_key Submitted source order key.
	 */
	private function can_use_source_order( object $order, string $source_order_key ): bool {
		$status = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		if ( in_array( $status, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
			return false;
		}

		if ( '' !== $source_order_key && method_exists( $order, 'get_order_key' ) && hash_equals( (string) $order->get_order_key(), $source_order_key ) ) {
			return true;
		}

		$order_user_id = method_exists( $order, 'get_user_id' ) ? (int) $order->get_user_id() : 0;
		$current_user  = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		return $order_user_id > 0 && $order_user_id === $current_user;
	}

	/**
	 * Return product term IDs when taxonomy helpers are available.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int, int>
	 */
	private function product_term_ids( int $product_id, string $taxonomy ): array {
		if ( $product_id <= 0 || ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'intval', $terms ) ) );
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
		// Keyed on the connecting IP, never on a client-supplied value: varying the
		// token or nonce must not buy a fresh bucket.
		$client_key = 'ip:' . $this->client_ip();

		// WooCommerce only calls wc_load_cart() for frontend requests.
		// REST API requests skip it, leaving WC()->session null.
		// Our endpoints need the session to validate the shopper token.
		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || null === WC()->session ) ) {
			wc_load_cart();
		}

		// Rate-limit before authorization so rejected requests (invalid tokens
		// included) cannot run unthrottled token-validation work.
		if ( ! ( $this->rate_limit )( $endpoint, $client_key ) ) {
			Hooks::action( 'api_request_failed', $endpoint, 'rate_limited', $this->failure_context( $params ) );
			return $this->response(
				429,
				array(
					'success' => false,
					'message' => __( 'Too many requests.', 'upsellbay' ),
				)
			);
		}

		$token = (string) ( $params['token'] ?? '' );
		if ( '' === $token || ( ! $this->session->validate_token( $token ) && ! $this->validate_thankyou_action_token( $endpoint, $params, $token ) ) ) {
			Hooks::action( 'api_request_failed', $endpoint, 'invalid_token', $this->failure_context( $params ) );
			return $this->response(
				403,
				array(
					'success' => false,
					'message' => __( 'Invalid session token.', 'upsellbay' ),
				)
			);
		}

		return null;
	}

	/**
	 * The rate-limit bucket of the connecting client.
	 *
	 * REMOTE_ADDR only (forwarded headers are client-controlled), adjustable
	 * through `upsellbay_pro_rate_limit_client_ip` for a site behind a trusted
	 * proxy; IPv6 clients share one bucket per /64 (ClientIp::bucket()).
	 */
	private function client_ip(): string {
		return ClientIp::bucket( ClientIp::current() );
	}

	/**
	 * Authorize a host offer action carrying Pro's order-bound thank-you token.
	 *
	 * Hooked onto the free host's `upsellbay_offer_action_token_valid` filter so
	 * host-owned endpoints (e.g. `/dismiss`) accept the post-purchase follow-on
	 * token when the session token is unavailable. Preserves any prior true
	 * result and never lowers it.
	 *
	 * @since 1.0.0
	 *
	 * @param bool                 $valid    Whether a prior check already authorized the action.
	 * @param string               $endpoint Endpoint key.
	 * @param array<string, mixed> $params   Request params.
	 * @param string               $token    Submitted token.
	 */
	public function filter_action_token_valid( bool $valid, string $endpoint, array $params, string $token ): bool {
		if ( $valid ) {
			return true;
		}

		return $this->validate_thankyou_action_token( $endpoint, $params, $token );
	}

	/**
	 * Validate a stateless thank-you action token.
	 *
	 * WooCommerce can rotate the cart session after checkout. Thank-you offers
	 * therefore use an order-bound signed token and still verify the source
	 * order before accepting the offer.
	 *
	 * Scope: the token authorizes follow-on actions for the verified ORDER (bound
	 * to source_order_id + order_key), not a specific offer_id — one token covers
	 * every thank-you offer shown for that order, and the one-click accept route
	 * shares it. Per-offer checks happen downstream: cart_offer_add runs
	 * claim_reasons() (published post plus the storefront OfferPrioritizer) and
	 * then CartMutator::accept() (CartValidator: placement, active status,
	 * product, rules); one_click_accept runs one_click_offer_eligible() (the
	 * storefront OfferPrioritizer plus the same CartValidator) and the order-age
	 * limit before any charge. dismiss has no per-offer check: it only hides an
	 * offer in the current session.
	 *
	 * @param string               $endpoint Endpoint key.
	 * @param array<string, mixed> $params   Request params.
	 * @param string               $token    Submitted token.
	 */
	private function validate_thankyou_action_token( string $endpoint, array $params, string $token ): bool {
		if ( ! in_array( $endpoint, array( 'cart_offer_add', 'dismiss', 'one_click_accept' ), true ) ) {
			return false;
		}

		if ( 'thankyou_offer' !== (string) ( $params['placement'] ?? '' ) ) {
			return false;
		}

		$order_id  = (int) ( $params['source_order_id'] ?? 0 );
		$order_key = (string) ( $params['source_order_key'] ?? '' );
		if ( $order_id <= 0 || '' === $order_key ) {
			return false;
		}

		return $this->tokens->verify_action(
			$token,
			'thankyou_offer',
			array(
				'source_order_id'  => $order_id,
				'source_order_key' => $order_key,
			)
		);
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
