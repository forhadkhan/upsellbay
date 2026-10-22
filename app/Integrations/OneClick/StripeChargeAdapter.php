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


use WPAnchorBay\UpsellBayPro\Core\Constants;
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
 * @since 1.0.0
 */
final class StripeChargeAdapter implements PaymentChargeAdapter {
	/**
	 * The WooCommerce payment gateway id.
	 *
	 * @since 1.0.0
	 */
	public function gateway_id(): string {
		return 'stripe';
	}

	/**
	 * Whether the order has a Stripe customer + saved payment method to charge again.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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

		$source_id    = method_exists( $source_order, 'get_id' ) ? (int) $source_order->get_id() : 0;
		$new_order_id = method_exists( $new_order, 'get_id' ) ? (int) $new_order->get_id() : 0;
		$offer_id     = method_exists( $new_order, 'get_meta' ) ? (int) $new_order->get_meta( Constants::ATTRIBUTION_SOURCE_OFFER_ID ) : 0;
		$key          = $this->idempotency_key( $source_id, $offer_id, $new_order_id );

		$request = array(
			'amount'         => $amount,
			'currency'       => strtolower( '' !== $currency ? $currency : 'usd' ),
			'customer'       => $customer,
			'payment_method' => $payment_method,
			'off_session'    => 'true',
			'confirm'        => 'true',
			'description'    => 'UpsellBay one-click post-purchase upsell',
			'metadata'       => array(
				'upsellbay_one_click'       => '1',
				'upsellbay_source_order'    => (string) $source_id,
				'upsellbay_offer'           => (string) $offer_id,
				// A second follow-on order for the same source + offer carries a different
				// id, so Stripe rejects the reused idempotency key (idempotency_error)
				// instead of replaying the first order's payment onto it.
				'upsellbay_follow_on_order' => (string) $new_order_id,
				'upsellbay_idempotency_key' => $key,
			),
		);

		// WC_Stripe_API::request() sends a random UUID as the Idempotency-Key for
		// payment_intents; its `wc_stripe_idempotency_key` filter is the supported way
		// to replace it. Scoped to this one request (matched on our metadata) and
		// removed straight after.
		$key_filter = static function ( $default_key, $stripe_request = array() ) use ( $key ) {
			$meta = is_array( $stripe_request ) && is_array( $stripe_request['metadata'] ?? null ) ? $stripe_request['metadata'] : array();

			return ( $meta['upsellbay_idempotency_key'] ?? '' ) === $key ? $key : $default_key;
		};
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wc_stripe_idempotency_key', $key_filter, 10, 2 );
		}

		try {
			$intent = \WC_Stripe_API::request( $request, 'payment_intents' );
		} catch ( \Throwable ) {
			// WC_Stripe_API::request() throws only for an outage or an empty response:
			// the request may or may not have reached Stripe. Unknown, not declined.
			return OneClickResult::in_progress( $new_order_id, 'Stripe request failed before a result was received' );
		} finally {
			if ( function_exists( 'remove_filter' ) ) {
				remove_filter( 'wc_stripe_idempotency_key', $key_filter, 10 );
			}
		}

		if ( ! is_object( $intent ) ) {
			return OneClickResult::in_progress( $new_order_id, 'Stripe returned no readable result' );
		}

		if ( isset( $intent->error ) ) {
			return $this->error_result( $intent->error, $new_order_id );
		}

		$status    = isset( $intent->status ) ? (string) $intent->status : '';
		$intent_id = isset( $intent->id ) ? (string) $intent->id : '';

		if ( 'succeeded' === $status ) {
			return $this->complete_succeeded( $new_order, $customer, $payment_method, $intent_id );
		}

		if ( 'processing' === $status ) {
			// Not paid yet and may still succeed. Record the intent so the gateway's
			// webhook (or the merchant) can settle the order; never cancel, never fall back.
			$this->record_intent( $new_order, $customer, $payment_method, $intent_id );
			return OneClickResult::in_progress( $new_order_id, 'Stripe is still processing payment ' . $this->mask( $intent_id ) );
		}

		if ( 'requires_action' === $status || 'requires_confirmation' === $status ) {
			// No 3DS challenge UI ships for one-click and the orchestrator cancels the
			// follow-on order, so void the intent too: it can never be confirmed later
			// against a cancelled order. Best effort; an unconfirmed intent cannot
			// capture funds on its own either way.
			$this->cancel_intent( $intent_id );
			return OneClickResult::requires_action( $new_order_id, '', 'Additional authentication is required to complete this purchase.' );
		}

		if ( 'requires_payment_method' === $status || 'canceled' === $status ) {
			// Stripe's terminal "not charged" states for a confirmed intent.
			return OneClickResult::declined( 'The card was declined.' );
		}

		// requires_capture or anything unexpected: money may be held. Unknown.
		return OneClickResult::in_progress( $new_order_id, 'Stripe returned status "' . sanitize_key( $status ) . '" for payment ' . $this->mask( $intent_id ) );
	}

	/**
	 * Mark the order paid after Stripe reports the intent succeeded.
	 *
	 * The intent id is saved on the order first, so a failure inside
	 * payment_complete() (a hook throwing, a save error) never loses the link to
	 * money that was taken; that case is reported as in_progress, not declined, so
	 * the order is kept on hold and never cancelled or charged again.
	 *
	 * @param object $order          Follow-on order.
	 * @param string $customer       Stripe customer id.
	 * @param string $payment_method Stripe payment method id.
	 * @param string $intent_id      Stripe PaymentIntent id.
	 */
	private function complete_succeeded( object $order, string $customer, string $payment_method, string $intent_id ): OneClickResult {
		$order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
		$this->record_intent( $order, $customer, $payment_method, $intent_id );

		$completed = false;
		try {
			$completed = method_exists( $order, 'payment_complete' ) && false !== $order->payment_complete( $intent_id );
		} catch ( \Throwable ) {
			$completed = false;
		}

		$paid = method_exists( $order, 'is_paid' ) ? true === $order->is_paid() : $completed;
		if ( ! $completed || ! $paid ) {
			return OneClickResult::in_progress( $order_id, 'Stripe charged payment ' . $this->mask( $intent_id ) . ' but the order could not be marked paid' );
		}

		return OneClickResult::charged( $order_id, 'Charged the saved card.' );
	}

	/**
	 * Save the Stripe references on the follow-on order (same meta keys the Stripe
	 * gateway uses, so its webhook handler can find the order by intent id).
	 *
	 * @param object $order          Follow-on order.
	 * @param string $customer       Stripe customer id.
	 * @param string $payment_method Stripe payment method id.
	 * @param string $intent_id      Stripe PaymentIntent id.
	 */
	private function record_intent( object $order, string $customer, string $payment_method, string $intent_id ): void {
		if ( ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( '_stripe_customer_id', $customer );
		$order->update_meta_data( '_stripe_source_id', $payment_method );
		if ( '' !== $intent_id ) {
			$order->update_meta_data( '_stripe_intent_id', $intent_id );
			if ( method_exists( $order, 'set_transaction_id' ) ) {
				$order->set_transaction_id( $intent_id );
			}
		}
		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * Map a Stripe error object to a result.
	 *
	 * Only an error that proves nothing was charged is a decline: a `card_error`
	 * (card_declined, insufficient_funds, expired_card, ...) or an error whose
	 * PaymentIntent is in a terminal unpaid state. `authentication_required` means the
	 * bank wants 3DS, which one-click cannot run: requires_action, so the shopper is
	 * sent to checkout. Everything else (api_error, idempotency_error, rate limits,
	 * unknown types) is indeterminate.
	 *
	 * @param mixed $error        Stripe error object.
	 * @param int   $new_order_id Follow-on order id.
	 */
	private function error_result( $error, int $new_order_id ): OneClickResult {
		$type          = is_object( $error ) && isset( $error->type ) ? (string) $error->type : '';
		$code          = is_object( $error ) && isset( $error->code ) ? (string) $error->code : '';
		$intent_status = is_object( $error ) && isset( $error->payment_intent ) && is_object( $error->payment_intent ) && isset( $error->payment_intent->status )
			? (string) $error->payment_intent->status
			: '';

		if ( 'card_error' === $type && 'authentication_required' === $code ) {
			return OneClickResult::requires_action( $new_order_id, '', 'Additional authentication is required to complete this purchase.' );
		}

		if ( 'card_error' === $type || in_array( $intent_status, array( 'requires_payment_method', 'canceled' ), true ) ) {
			return OneClickResult::declined( 'The card was declined.' );
		}

		return OneClickResult::in_progress( $new_order_id, 'Stripe returned error type "' . sanitize_key( '' !== $type ? $type : 'unknown' ) . '"' );
	}

	/**
	 * Deterministic Stripe idempotency key for one source order + offer.
	 *
	 * A site fragment keeps two stores that share a Stripe account from colliding on
	 * the same order ids. Stripe keeps keys for at least 24 hours, the same window as
	 * the default one-click order-age limit.
	 *
	 * @param int $source_order_id Source order id.
	 * @param int $offer_id        Offer id (0 when the linkage meta is missing).
	 * @param int $new_order_id    Follow-on order id, used only when the offer id is missing.
	 */
	private function idempotency_key( int $source_order_id, int $offer_id, int $new_order_id ): string {
		$site = function_exists( 'home_url' ) ? substr( hash( 'sha256', (string) home_url() ), 0, 10 ) : 'site';

		return $offer_id > 0
			? 'ub-' . $site . '-' . $source_order_id . '-' . $offer_id
			: 'ub-' . $site . '-' . $source_order_id . '-order' . $new_order_id;
	}

	/**
	 * Mask a Stripe object id for order notes: keep the prefix and last four characters.
	 *
	 * @param string $id Stripe object id.
	 */
	private function mask( string $id ): string {
		if ( '' === $id ) {
			return '(no id)';
		}

		$prefix = (string) strstr( $id, '_', true );

		return ( '' !== $prefix ? $prefix . '_' : '' ) . '...' . substr( $id, -4 );
	}

	/**
	 * Cancel a PaymentIntent that one-click will not complete.
	 *
	 * @param string $intent_id Stripe PaymentIntent id.
	 */
	private function cancel_intent( string $intent_id ): void {
		if ( '' === $intent_id || ! class_exists( '\WC_Stripe_API' ) ) {
			return;
		}

		try {
			\WC_Stripe_API::request( array(), 'payment_intents/' . rawurlencode( $intent_id ) . '/cancel' );
		} catch ( \Throwable ) {
			// The intent stays unconfirmed, which charges nothing; the order is cancelled regardless.
			return;
		}
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
