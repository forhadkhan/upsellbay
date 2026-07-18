<?php
/**
 * Stripe one-click charge adapter.
 *
 * @package UpsellBay\Integrations\OneClick
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Integrations\OneClick;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Domain\OneClick\OneClickResult;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\PaymentChargeAdapter;

/**
 * Charges the WooCommerce Stripe gateway's saved payment method off-session for a
 * one-click post-purchase upsell.
 *
 * It reuses the official WooCommerce Stripe gateway's own API client
 * (`WC_Stripe_API::request`), so the secret key is never handled here — the gateway's
 * configured credentials are used. It reads the Stripe customer + saved payment method
 * the gateway already stored on the completed order, then creates a confirmed
 * off-session PaymentIntent. Everything is guarded by `class_exists`, so the adapter is
 * only ever registered/active when the Stripe gateway is present.
 *
 * @since 1.1.0
 */
final class StripeChargeAdapter implements PaymentChargeAdapter {
	/**
	 * The WooCommerce payment gateway id.
	 *
	 * @since 1.1.0
	 */
	public function gateway_id(): string {
		return 'stripe';
	}

	/**
	 * Whether the order has a Stripe customer + saved payment method to charge again.
	 *
	 * @since 1.1.0
	 *
	 * @param object $source_order Source WC_Order.
	 */
	public function supports_one_click( object $source_order ): bool {
		if ( ! class_exists( '\WC_Stripe_API' ) ) {
			return false;
		}

		return '' !== $this->stripe_customer_id( $source_order ) && '' !== $this->stripe_payment_method_id( $source_order );
	}

	/**
	 * Create a confirmed off-session PaymentIntent for the new order's total.
	 *
	 * @since 1.1.0
	 *
	 * @param object $new_order    Linked follow-on WC_Order to charge.
	 * @param object $source_order Original WC_Order supplying the saved method.
	 */
	public function charge( object $new_order, object $source_order ): OneClickResult {
		if ( ! class_exists( '\WC_Stripe_API' ) ) {
			return OneClickResult::unsupported( 'Stripe gateway unavailable.' );
		}

		$customer       = $this->stripe_customer_id( $source_order );
		$payment_method = $this->stripe_payment_method_id( $source_order );
		if ( '' === $customer || '' === $payment_method ) {
			return OneClickResult::unsupported( 'No saved Stripe payment method for this order.' );
		}

		$currency = method_exists( $new_order, 'get_currency' ) ? (string) $new_order->get_currency() : '';
		$total    = method_exists( $new_order, 'get_total' ) ? (float) $new_order->get_total() : 0.0;
		$amount   = class_exists( '\WC_Stripe_Helper' )
			? \WC_Stripe_Helper::get_stripe_amount( $total, $currency )
			: (int) round( $total * 100 );

		$request = array(
			'amount'         => $amount,
			'currency'       => strtolower( '' !== $currency ? $currency : 'usd' ),
			'customer'       => $customer,
			'payment_method' => $payment_method,
			'off_session'    => 'true',
			'confirm'        => 'true',
			'description'    => 'UpsellBay one-click post-purchase upsell',
			'metadata'       => array(
				'upsellbay_one_click'    => '1',
				'upsellbay_source_order' => (string) ( method_exists( $source_order, 'get_id' ) ? $source_order->get_id() : '' ),
			),
		);

		try {
			$intent = \WC_Stripe_API::request( $request, 'payment_intents' );
		} catch ( \Throwable $e ) {
			// A declined off-session charge surfaces as an exception carrying the intent.
			return OneClickResult::declined( 'Charge failed: ' . $e->getMessage() );
		}

		if ( is_object( $intent ) && isset( $intent->error ) ) {
			$message = isset( $intent->error->message ) ? (string) $intent->error->message : 'The card was declined.';
			return OneClickResult::declined( $message );
		}

		$status    = is_object( $intent ) && isset( $intent->status ) ? (string) $intent->status : '';
		$intent_id = is_object( $intent ) && isset( $intent->id ) ? (string) $intent->id : '';

		if ( 'succeeded' === $status || 'processing' === $status ) {
			if ( method_exists( $new_order, 'update_meta_data' ) ) {
				$new_order->update_meta_data( '_stripe_customer_id', $customer );
				$new_order->update_meta_data( '_stripe_source_id', $payment_method );
				$new_order->update_meta_data( '_stripe_intent_id', $intent_id );
			}
			if ( method_exists( $new_order, 'payment_complete' ) ) {
				$new_order->payment_complete( $intent_id );
			}
			return OneClickResult::charged( (int) $new_order->get_id(), 'Charged the saved card.' );
		}

		if ( 'requires_action' === $status || 'requires_confirmation' === $status ) {
			$secret = is_object( $intent ) && isset( $intent->client_secret ) ? (string) $intent->client_secret : '';
			return OneClickResult::requires_action( (int) $new_order->get_id(), $secret, 'Additional authentication is required to complete this purchase.' );
		}

		return OneClickResult::declined( 'The charge could not be completed.' );
	}

	/**
	 * Read the Stripe customer id the gateway stored for this order.
	 *
	 * @param object $order Source order.
	 */
	private function stripe_customer_id( object $order ): string {
		if ( method_exists( $order, 'get_meta' ) ) {
			$customer_id = (string) $order->get_meta( '_stripe_customer_id' );
			if ( '' !== $customer_id ) {
				return $customer_id;
			}
		}

		$user_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		if ( $user_id > 0 && function_exists( 'get_user_option' ) ) {
			$customer_id = get_user_option( '_stripe_customer_id', $user_id );
			if ( is_string( $customer_id ) && '' !== $customer_id ) {
				return $customer_id;
			}
		}

		return '';
	}

	/**
	 * Read the saved Stripe payment method / source id to charge again.
	 *
	 * @param object $order Source order.
	 */
	private function stripe_payment_method_id( object $order ): string {
		if ( method_exists( $order, 'get_meta' ) ) {
			$source_id = (string) $order->get_meta( '_stripe_source_id' );
			if ( '' !== $source_id ) {
				return $source_id;
			}
		}

		$user_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		if ( $user_id > 0 && class_exists( '\WC_Payment_Tokens' ) ) {
			$tokens = \WC_Payment_Tokens::get_customer_tokens( $user_id, 'stripe' );
			foreach ( (array) $tokens as $token ) {
				if ( is_object( $token ) && method_exists( $token, 'get_token' ) ) {
					$value = (string) $token->get_token();
					if ( '' !== $value ) {
						return $value;
					}
				}
			}
		}

		return '';
	}
}
