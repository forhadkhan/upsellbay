<?php
/**
 * Offer visibility inspector.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Data\OfferRepository;

/**
 * Produces merchant-facing visibility diagnostics for storefront offers.
 *
 * @since 1.0.0
 */
final class OfferVisibilityInspector {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Offer validator.
	 *
	 * @var OfferValidator
	 */
	private OfferValidator $validator;

	/**
	 * Offer prioritizer.
	 *
	 * @var OfferPrioritizer
	 */
	private OfferPrioritizer $prioritizer;

	/**
	 * Offer repository.
	 *
	 * @var OfferRepository|null
	 */
	private ?OfferRepository $offers;

	/**
	 * Cart session.
	 *
	 * @var CartSession|null
	 */
	private ?CartSession $session;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings             $settings    Settings service.
	 * @param OfferValidator       $validator   Offer validator.
	 * @param OfferPrioritizer     $prioritizer Offer prioritizer.
	 * @param OfferRepository|null $offers      Offer repository.
	 * @param CartSession|null     $session     Cart session.
	 */
	public function __construct( Settings $settings, OfferValidator $validator, OfferPrioritizer $prioritizer, ?OfferRepository $offers = null, ?CartSession $session = null ) {
		$this->settings    = $settings;
		$this->validator   = $validator;
		$this->prioritizer = $prioritizer;
		$this->offers      = $offers;
		$this->session     = $session;
	}

	/**
	 * Inspect an offer against current settings and storefront context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>      $offer   Offer payload.
	 * @param array<string, mixed>|null $context Optional storefront context.
	 * @return array{summary: string, status: string, checks: array<int, array<string, string>>, preview: array{available: bool, message: string}}
	 */
	public function inspect( array $offer, ?array $context = null ): array {
		$meta     = $this->validator->normalize( is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array() );
		$settings = $this->settings->all();
		$context  = is_array( $context ) ? $context : $this->context();
		$checks   = array();

		$checks[] = $this->make_check(
			'plugin_enabled',
			__( 'UpsellBay enabled', 'upsellbay' ),
			true === ( $settings['enabled'] ?? true ) ? 'pass' : 'fail',
			true === ( $settings['enabled'] ?? true ) ? __( 'Storefront rendering is enabled.', 'upsellbay' ) : __( 'UpsellBay is disabled globally.', 'upsellbay' )
		);

		$placement = (string) $meta['_ub_offer_type'];
		$checks[]  = $this->make_check(
			'placement_enabled',
			__( 'Placement enabled', 'upsellbay' ),
			false === ( $settings['placements'][ $placement ] ?? true ) ? 'fail' : 'pass',
			false === ( $settings['placements'][ $placement ] ?? true ) ? __( 'This placement is disabled in settings.', 'upsellbay' ) : __( 'This placement is enabled in settings.', 'upsellbay' )
		);

		$checks[] = $this->make_check(
			'test_mode',
			__( 'Test mode visibility', 'upsellbay' ),
			true === ( $settings['test_mode'] ?? false ) ? 'warn' : 'pass',
			true === ( $settings['test_mode'] ?? false ) ? __( 'Test mode is enabled. Only admins and preview-mode viewers can see this offer.', 'upsellbay' ) : __( 'Eligible shoppers can see this offer.', 'upsellbay' )
		);

		$validation = $this->validator->validate( $meta );
		if ( $validation->is_valid() ) {
			$checks[] = $this->make_check(
				'configuration',
				__( 'Offer configuration', 'upsellbay' ),
				'pass',
				__( 'The offer passes required configuration validation.', 'upsellbay' )
			);
		} else {
			foreach ( $validation->errors() as $error ) {
				$checks[] = $this->make_check(
					'configuration',
					__( 'Offer configuration', 'upsellbay' ),
					'fail',
					$error
				);
			}
		}

		$evaluation = $this->prioritizer->evaluate( $offer, $placement, $context );
		if ( $evaluation['eligible'] ) {
			$checks[] = $this->make_check(
				'eligibility',
				__( 'Current storefront eligibility', 'upsellbay' ),
				'pass',
				__( 'The offer is eligible in the current storefront context.', 'upsellbay' )
			);
		} else {
			foreach ( $evaluation['reasons'] as $reason ) {
				$checks[] = $this->make_check(
					'eligibility',
					__( 'Current storefront eligibility', 'upsellbay' ),
					'warn',
					$this->reason_message( $reason )
				);
			}
		}

		if ( null !== $this->offers && (int) ( $offer['id'] ?? 0 ) > 0 ) {
			$winner = $this->winning_offer( $placement, $context );
			if ( null !== $winner && (int) ( $winner['id'] ?? 0 ) !== (int) ( $offer['id'] ?? 0 ) ) {
				$checks[] = $this->make_check(
					'competition',
					__( 'Placement competition', 'upsellbay' ),
					'warn',
					sprintf(
						/* translators: %s: offer title */
						__( 'Another eligible offer currently wins this placement: %s.', 'upsellbay' ),
						(string) ( $winner['title'] ?? __( 'Untitled offer', 'upsellbay' ) )
					)
				);
			} elseif ( $evaluation['eligible'] ) {
				$checks[] = $this->make_check(
					'competition',
					__( 'Placement competition', 'upsellbay' ),
					'pass',
					__( 'This offer currently wins its placement.', 'upsellbay' )
				);
			}
		}

		$preview = $this->preview_status( $offer, $context, $settings );
		$status  = $this->overall_status( $checks );

		return array(
			'summary' => $this->summary_message( $status ),
			'status'  => $status,
			'checks'  => $checks,
			'preview' => $preview,
		);
	}

	/**
	 * Build a storefront-like context for diagnostics.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function context(): array {
		$context = array(
			'cart_product_ids'    => array(),
			'dismissed_offer_ids' => null !== $this->session ? array_keys( $this->session->state()['dismissed'] ?? array() ) : array(),
			'cart_subtotal'       => '0',
			'is_preview'          => isset( $_GET['upsellbay_preview'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$context['cart_subtotal'] = (string) WC()->cart->get_subtotal();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = (int) ( $cart_item['product_id'] ?? 0 );
				if ( $product_id > 0 ) {
					$context['cart_product_ids'][] = $product_id;
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
	 * Determine the currently winning offer for a placement.
	 *
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Context payload.
	 * @return array<string, mixed>|null
	 */
	private function winning_offer( string $placement, array $context ): ?array {
		if ( null === $this->offers ) {
			return null;
		}

		$all    = $this->offers->query( array( 'limit' => 50 ) );
		$limit  = $this->settings->placement_max_display( $placement );
		$winner = $this->prioritizer->select( $all, $placement, $context, $limit );

		return $winner[0] ?? null;
	}

	/**
	 * Build a preview availability message.
	 *
	 * @param array<string, mixed> $offer    Offer payload.
	 * @param array<string, mixed> $context  Context payload.
	 * @param array<string, mixed> $settings Settings payload.
	 * @return array{available: bool, message: string}
	 */
	private function preview_status( array $offer, array $context, array $settings ): array {
		$meta       = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$product_id = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$cart_ids   = is_array( $context['cart_product_ids'] ?? null ) ? array_map( 'intval', $context['cart_product_ids'] ) : array();

		if ( true === ( $settings['test_mode'] ?? false ) ) {
			return array(
				'available' => true,
				'message'   => __( 'Preview from an admin session while test mode is enabled.', 'upsellbay' ),
			);
		}

		if ( 'checkout_bump' === (string) ( $meta['_ub_offer_type'] ?? '' ) ) {
			if ( array() === $cart_ids ) {
				return array(
					'available' => false,
					'message'   => __( 'Checkout previews need at least one item in the cart before the bump area can render.', 'upsellbay' ),
				);
			}

			if ( $product_id > 0 && in_array( $product_id, $cart_ids, true ) ) {
				$hide_if_in_cart = (bool) ( $meta['_ub_hide_if_in_cart'] ?? true );
				if ( $hide_if_in_cart ) {
					return array(
						'available' => false,
						'message'   => __( 'The offered product is already in the cart, so the checkout bump is intentionally suppressed.', 'upsellbay' ),
					);
				}
			}
		}

		return array(
			'available' => true,
			'message'   => __( 'The preview context is ready for this offer type.', 'upsellbay' ),
		);
	}

	/**
	 * Create a merchant-facing check row.
	 *
	 * @param string $code    Check code.
	 * @param string $label   Check label.
	 * @param string $status  pass|warn|fail.
	 * @param string $message Check message.
	 * @return array<string, string>
	 */
	private function make_check( string $code, string $label, string $status, string $message ): array {
		return array(
			'code'    => $code,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * Map runtime reason codes to merchant guidance.
	 *
	 * @param string $reason Reason code.
	 */
	private function reason_message( string $reason ): string {
		return match ( $reason ) {
			'placement_mismatch' => __( 'This offer is assigned to a different placement.', 'upsellbay' ),
			'inactive_offer' => __( 'The offer is not active yet.', 'upsellbay' ),
			'dismissed_in_session' => __( 'This offer was dismissed in the current session.', 'upsellbay' ),
			'product_already_in_cart' => __( 'The offered product is already in the cart, so the bump is hidden.', 'upsellbay' ),
			'not_started' => __( 'The offer start date is in the future.', 'upsellbay' ),
			'expired' => __( 'The offer end date has passed.', 'upsellbay' ),
			'missing_product' => __( 'The offered product is missing.', 'upsellbay' ),
			'product_unavailable' => __( 'The offered product is not currently purchasable or in stock.', 'upsellbay' ),
			'trigger_mismatch' => __( 'The current cart does not match the trigger settings.', 'upsellbay' ),
			'rules_failed' => __( 'The current shopper or cart context does not satisfy the offer rules.', 'upsellbay' ),
			default => __( 'The offer is currently suppressed by storefront eligibility checks.', 'upsellbay' ),
		};
	}

	/**
	 * Determine the overall status from check rows.
	 *
	 * @param array<int, array<string, string>> $checks Check rows.
	 */
	private function overall_status( array $checks ): string {
		foreach ( $checks as $check ) {
			if ( 'fail' === ( $check['status'] ?? '' ) ) {
				return 'blocked';
			}
		}

		foreach ( $checks as $check ) {
			if ( 'warn' === ( $check['status'] ?? '' ) ) {
				return 'warning';
			}
		}

		return 'eligible';
	}

	/**
	 * Return a short summary for the overall result.
	 *
	 * @param string $status Overall status.
	 */
	private function summary_message( string $status ): string {
		return match ( $status ) {
			'blocked' => __( 'This offer is blocked by configuration or environment checks.', 'upsellbay' ),
			'warning' => __( 'This offer is configured, but one or more conditions may prevent it from showing.', 'upsellbay' ),
			default => __( 'This offer is configured correctly and is eligible in the current context.', 'upsellbay' ),
		};
	}
}
