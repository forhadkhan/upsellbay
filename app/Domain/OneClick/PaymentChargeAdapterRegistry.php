<?php
/**
 * One-click payment adapter registry.
 *
 * @package UpsellBay\Domain\OneClick
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\OneClick;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Resolves the one-click charge adapter for a given order's payment gateway.
 *
 * @since 1.1.0
 */
final class PaymentChargeAdapterRegistry {
	/**
	 * Registered adapters keyed by gateway id.
	 *
	 * @var array<string, PaymentChargeAdapter>
	 */
	private array $adapters = array();

	/**
	 * Register an adapter for its gateway.
	 *
	 * @since 1.1.0
	 *
	 * @param PaymentChargeAdapter $adapter Adapter.
	 */
	public function register( PaymentChargeAdapter $adapter ): void {
		$this->adapters[ $adapter->gateway_id() ] = $adapter;
	}

	/**
	 * Return the adapter that can one-click-charge this order, or null.
	 *
	 * @since 1.1.0
	 *
	 * @param object $source_order Source WC_Order.
	 */
	public function for_order( object $source_order ): ?PaymentChargeAdapter {
		$gateway_id = method_exists( $source_order, 'get_payment_method' ) ? (string) $source_order->get_payment_method() : '';
		if ( '' === $gateway_id || ! isset( $this->adapters[ $gateway_id ] ) ) {
			return null;
		}

		$adapter = $this->adapters[ $gateway_id ];

		return $adapter->supports_one_click( $source_order ) ? $adapter : null;
	}
}
