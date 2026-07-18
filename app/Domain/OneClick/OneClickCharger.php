<?php
/**
 * One-click post-purchase charge orchestrator.
 *
 * @package UpsellBay\Domain\OneClick
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\OneClick;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Hooks;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;

/**
 * Turns an accepted thank-you offer into a real, charged, linked follow-on order using
 * the shopper's already-saved payment method — no re-entry.
 *
 * Gateway-agnostic: it builds the order and delegates the actual charge to a
 * PaymentChargeAdapter. It is idempotent (never double-charges the same offer for the
 * same source order) and, when the gateway can't do off-session one-click, returns an
 * `unsupported` result so the caller falls back to the standard separate-checkout flow.
 *
 * @since 1.1.0
 */
final class OneClickCharger {
	/**
	 * Adapter registry.
	 *
	 * @var PaymentChargeAdapterRegistry
	 */
	private PaymentChargeAdapterRegistry $registry;

	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discounts;

	/**
	 * Linkage-meta writer (object with write_follow_on_order()).
	 *
	 * @var object
	 */
	private object $attribution;

	/**
	 * Builds an empty linked order from the source order.
	 *
	 * @var callable(object): ?object
	 */
	private $order_factory;

	/**
	 * Finds an existing follow-on order id for (source_order_id, offer_id), or 0.
	 *
	 * @var callable(int, int): int
	 */
	private $existing_finder;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param PaymentChargeAdapterRegistry $registry        Adapter registry.
	 * @param DiscountCalculator           $discounts       Discount calculator.
	 * @param object                       $attribution     Linkage-meta writer.
	 * @param callable|null                $order_factory   Linked-order factory.
	 * @param callable|null                $existing_finder Existing follow-on order finder.
	 */
	public function __construct(
		PaymentChargeAdapterRegistry $registry,
		DiscountCalculator $discounts,
		object $attribution,
		?callable $order_factory = null,
		?callable $existing_finder = null
	) {
		$this->registry        = $registry;
		$this->discounts       = $discounts;
		$this->attribution     = $attribution;
		$this->order_factory   = $order_factory ?? array( $this, 'wc_order_factory' );
		$this->existing_finder = $existing_finder ?? array( $this, 'wc_existing_finder' );
	}

	/**
	 * Accept an offer for a completed order and charge it in one click.
	 *
	 * @since 1.1.0
	 *
	 * @param object               $source_order Completed source WC_Order.
	 * @param array<string, mixed> $offer        Offer payload (id + meta).
	 * @param string               $placement    Placement key (e.g. thankyou_offer).
	 * @return OneClickResult
	 */
	public function charge( object $source_order, array $offer, string $placement ): OneClickResult {
		$offer_id   = (int) ( $offer['id'] ?? 0 );
		$meta       = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$product_id = (int) ( $meta['_ub_offer_product_id'] ?? 0 );

		if ( $offer_id <= 0 || $product_id <= 0 ) {
			return OneClickResult::unsupported( 'Invalid offer.' );
		}

		// Idempotency: never charge the same offer twice for the same source order.
		$existing = (int) ( $this->existing_finder )( $source_order->get_id(), $offer_id );
		if ( $existing > 0 ) {
			return OneClickResult::duplicate( $existing );
		}

		// Capability detection — unsupported gateways fall back to separate checkout.
		$adapter = $this->registry->for_order( $source_order );
		if ( null === $adapter ) {
			return OneClickResult::unsupported( 'This order\'s payment method cannot be charged in one click.' );
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return OneClickResult::unsupported( 'WooCommerce is unavailable.' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return OneClickResult::unsupported( 'Offered product is unavailable.' );
		}

		$priced = $this->discounts->calculate( (string) $product->get_price(), $meta );
		if ( null === $priced ) {
			return OneClickResult::unsupported( 'Offer price could not be computed.' );
		}
		$offer_price = (float) $priced['offer_price'];

		// Build the unpaid linked order.
		$new_order = ( $this->order_factory )( $source_order );
		if ( ! is_object( $new_order ) || ! method_exists( $new_order, 'add_product' ) ) {
			return OneClickResult::unsupported( 'Could not create the follow-on order.' );
		}

		$item_id = $new_order->add_product(
			$product,
			1,
			array(
				'subtotal' => $offer_price,
				'total'    => $offer_price,
			)
		);

		$item = ( is_numeric( $item_id ) && method_exists( $new_order, 'get_item' ) ) ? $new_order->get_item( (int) $item_id ) : null;
		if ( is_object( $item ) && method_exists( $item, 'add_meta_data' ) && method_exists( $item, 'save' ) ) {
			$item->add_meta_data( Constants::ATTRIBUTION_OFFER_ID, $offer_id, true );
			$item->add_meta_data( Constants::ATTRIBUTION_OFFER_PLACEMENT, $placement, true );
			$item->add_meta_data( Constants::ATTRIBUTION_DISCOUNT_AMOUNT, (string) ( $priced['discount_amount'] ?? '0' ), true );
			$item->save();
		}

		if ( method_exists( $new_order, 'set_payment_method' ) ) {
			$new_order->set_payment_method( (string) $source_order->get_payment_method() );
		}
		if ( method_exists( $new_order, 'set_payment_method_title' ) && method_exists( $source_order, 'get_payment_method_title' ) ) {
			$new_order->set_payment_method_title( (string) $source_order->get_payment_method_title() );
		}

		if ( method_exists( $new_order, 'calculate_totals' ) ) {
			$new_order->calculate_totals( false );
		}
		if ( method_exists( $new_order, 'save' ) ) {
			$new_order->save();
		}

		// Link it to the source order + offer (reuses the existing attribution meta).
		if ( method_exists( $this->attribution, 'write_follow_on_order' ) ) {
			$this->attribution->write_follow_on_order( $new_order, $source_order->get_id(), $offer_id );
		}

		// Delegate the actual off-session charge to the gateway adapter.
		$result = $adapter->charge( $new_order, $source_order );

		if ( OneClickResult::STATUS_CHARGED === $result->status() ) {
			Hooks::action( 'one_click_charged', $new_order, $source_order, $offer, $placement );
		} elseif ( OneClickResult::STATUS_DECLINED === $result->status() && method_exists( $new_order, 'update_status' ) ) {
			$new_order->update_status( 'cancelled', 'UpsellBay: one-click charge declined.' );
		}

		return $result;
	}

	/**
	 * Default linked-order factory: a new order cloning the source's customer, addresses
	 * and currency. HPOS-safe (pure WooCommerce CRUD).
	 *
	 * @param object $source_order Source WC_Order.
	 * @return object|null
	 */
	private function wc_order_factory( object $source_order ): ?object {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return null;
		}

		$new_order = wc_create_order( array( 'customer_id' => (int) $source_order->get_customer_id() ) );
		if ( ! is_object( $new_order ) || ! method_exists( $new_order, 'set_address' ) ) {
			return null;
		}

		$new_order->set_address( $source_order->get_address( 'billing' ), 'billing' );
		$new_order->set_address( $source_order->get_address( 'shipping' ), 'shipping' );
		if ( method_exists( $new_order, 'set_currency' ) && method_exists( $source_order, 'get_currency' ) ) {
			$new_order->set_currency( (string) $source_order->get_currency() );
		}
		if ( method_exists( $new_order, 'set_created_via' ) ) {
			$new_order->set_created_via( 'upsellbay_one_click' );
		}

		return $new_order;
	}

	/**
	 * Default existing-follow-on finder — HPOS-safe via wc_get_orders on linkage meta.
	 *
	 * @param int $source_order_id Source order.
	 * @param int $offer_id        Offer.
	 * @return int Existing follow-on order id, or 0.
	 */
	private function wc_existing_finder( int $source_order_id, int $offer_id ): int {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded idempotency lookup (limit 1); wc_get_orders meta_query is the HPOS-safe way to find a follow-on order by its linkage meta.
				'meta_query' => array(
					array(
						'key'   => Constants::ATTRIBUTION_SOURCE_ORDER_ID,
						'value' => $source_order_id,
					),
					array(
						'key'   => Constants::ATTRIBUTION_SOURCE_OFFER_ID,
						'value' => $offer_id,
					),
				),
			)
		);

		return is_array( $orders ) && array() !== $orders ? (int) $orders[0] : 0;
	}
}
