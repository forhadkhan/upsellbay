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
 * Only a verified-paid follow-on order is ever reported as success; see
 * `.meta/architecture/one-click-charge-idempotency.md` for the full model.
 *
 * @since 1.0.0
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
	 * Linkage-meta writer; its linkage is the idempotency key, so it is required.
	 *
	 * @var FollowOnLinkageWriter
	 */
	private FollowOnLinkageWriter $attribution;

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
	 * Loads an order by id (null when missing).
	 *
	 * @var callable(int): ?object
	 */
	private $order_loader;

	/**
	 * Follow-on order statuses that are closed with nothing charged or held, so a
	 * retry may safely fall back to the separate-checkout flow.
	 *
	 * @var array<int, string>
	 */
	private const CLOSED_UNPAID_STATUSES = array( 'cancelled', 'failed', 'refunded' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param PaymentChargeAdapterRegistry $registry        Adapter registry.
	 * @param DiscountCalculator           $discounts       Discount calculator.
	 * @param FollowOnLinkageWriter        $attribution     Linkage-meta writer.
	 * @param callable|null                $order_factory   Linked-order factory.
	 * @param callable|null                $existing_finder Existing follow-on order finder.
	 * @param callable|null                $order_loader    Order loader by id.
	 */
	public function __construct(
		PaymentChargeAdapterRegistry $registry,
		DiscountCalculator $discounts,
		FollowOnLinkageWriter $attribution,
		?callable $order_factory = null,
		?callable $existing_finder = null,
		?callable $order_loader = null
	) {
		$this->registry        = $registry;
		$this->discounts       = $discounts;
		$this->attribution     = $attribution;
		$this->order_factory   = $order_factory ?? array( $this, 'wc_order_factory' );
		$this->existing_finder = $existing_finder ?? array( $this, 'wc_existing_finder' );
		$this->order_loader    = $order_loader ?? array( $this, 'wc_order_loader' );
	}

	/**
	 * Accept an offer for a paid source order and charge it in one click.
	 *
	 * The source order must report itself paid (`WC_Order::is_paid()`: processing or
	 * completed by default). A pending, on-hold or otherwise unpaid source order is
	 * never charged against; it returns `unsupported` so the caller falls back to the
	 * separate checkout, where the shopper pays explicitly.
	 *
	 * @since 1.0.0
	 *
	 * @param object               $source_order Paid source WC_Order.
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

		// Idempotency: at most one follow-on order (so at most one charge) per source
		// order + offer. A retry never charges again; it reports what the first attempt
		// actually did, and only a verified-paid order counts as success.
		$existing = (int) ( $this->existing_finder )( $source_order->get_id(), $offer_id );
		if ( $existing > 0 ) {
			return $this->resolve_existing( $existing );
		}

		// Defence in depth (the REST route checks this first): an off-session charge for
		// the upsell needs a paid source order. Checked after the idempotency lookup so a
		// retry still reports what an earlier attempt did, and before any order is
		// created or charged. A source without is_paid() fails closed.
		if ( ! $this->is_paid( $source_order ) ) {
			return OneClickResult::unsupported( 'The source order is not paid.' );
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

		// Order line totals are stored excluding tax. On a store that enters prices
		// including tax, strip the tax first so calculate_totals() adds it back once
		// rather than charging tax on top of a tax-inclusive price (the cart does the same).
		$line_total = $offer_price;
		if ( function_exists( 'wc_get_price_excluding_tax' ) ) {
			$line_total = (float) wc_get_price_excluding_tax(
				$product,
				array(
					'qty'   => 1,
					'price' => $offer_price,
				)
			);
		}

		// Build the unpaid linked order.
		$new_order = ( $this->order_factory )( $source_order );
		if ( ! is_object( $new_order ) || ! method_exists( $new_order, 'add_product' ) ) {
			return OneClickResult::unsupported( 'Could not create the follow-on order.' );
		}

		$item_id = $new_order->add_product(
			$product,
			1,
			array(
				'subtotal' => $line_total,
				'total'    => $line_total,
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

		// Tax is computed by WooCommerce from the order's own billing/shipping address,
		// which the order factory copies from the source order, honouring the store's
		// "calculate tax based on" setting. calculate_totals( true ) runs
		// calculate_taxes() itself, so the amount the adapter charges (get_total()) is
		// the tax-inclusive order total.
		if ( method_exists( $new_order, 'calculate_totals' ) ) {
			$new_order->calculate_totals( true );
		}
		if ( method_exists( $new_order, 'save' ) ) {
			$new_order->save();
		}

		// No shipping line is added: one-click charges the item (and its tax) only.
		if ( method_exists( $product, 'needs_shipping' ) && true === $product->needs_shipping() ) {
			$this->add_note( $new_order, 'UpsellBay: one-click follow-on order for source order #' . (int) $source_order->get_id() . '. This item needs shipping, but one-click charges no shipping.' );
		}

		// Link it to the source order + offer (reuses the existing attribution meta). The
		// linkage is the idempotency key: without it a retry could not find this order
		// and would charge again, so never charge when it was not written.
		if ( ! $this->attribution->write_follow_on_order( $new_order, (int) $source_order->get_id(), $offer_id ) ) {
			if ( method_exists( $new_order, 'update_status' ) ) {
				$new_order->update_status( 'cancelled', 'UpsellBay: one-click follow-on order could not be linked to its source order. Nothing was charged.' );
			}
			return OneClickResult::unsupported( 'The follow-on order could not be linked.' );
		}

		// Delegate the actual off-session charge to the gateway adapter. An exception
		// is an UNKNOWN outcome, never a decline: the gateway may have taken the money
		// before the failure (timeout, transport error, fault after the charge).
		try {
			$result = $adapter->charge( $new_order, $source_order );
		} catch ( \Throwable $e ) {
			// Only the exception class goes into the note: gateway messages can carry raw
			// API responses.
			return $this->hold_indeterminate( $new_order, 'the payment request failed with ' . basename( str_replace( '\\', '/', get_class( $e ) ) ) );
		}

		if ( OneClickResult::STATUS_CHARGED === $result->status() ) {
			Hooks::action( 'one_click_charged', $new_order, $source_order, $offer, $placement );
			return $result;
		}

		// Only a definite "nothing was charged" outcome closes the follow-on order, so a
		// retry finds a closed, unpaid order and falls back to checkout. requires_action
		// is closed too: no challenge UI ships, so the shopper completes via checkout.
		$closed = array(
			OneClickResult::STATUS_DECLINED        => 'UpsellBay: one-click charge declined. Nothing was charged.',
			OneClickResult::STATUS_REQUIRES_ACTION => 'UpsellBay: one-click charge needs customer authentication, which one-click cannot run. Nothing was charged.',
			OneClickResult::STATUS_UNSUPPORTED     => 'UpsellBay: one-click charge is not possible for this payment method. Nothing was charged.',
		);
		if ( isset( $closed[ $result->status() ] ) && ! $this->is_paid( $new_order ) ) {
			if ( method_exists( $new_order, 'update_status' ) ) {
				$new_order->update_status( 'cancelled', $closed[ $result->status() ] );
			}
			return $result;
		}

		// Everything else is indeterminate: an adapter-reported in_progress (e.g. the
		// gateway is still processing, or money was taken but the order could not be
		// marked paid), an unknown or bogus status, or a "no charge" claim on an order
		// that is in fact paid. Never cancel, never fall back to a second charge.
		$reason = OneClickResult::STATUS_IN_PROGRESS === $result->status() && '' !== $result->message()
			? $result->message()
			: 'the payment adapter reported "' . sanitize_key( $result->status() ) . '"';

		return $this->hold_indeterminate( $new_order, $reason );
	}

	/**
	 * Keep a follow-on order whose charge outcome is unknown on hold, and report
	 * in_progress so the caller neither claims success nor offers a second payment.
	 *
	 * A retry finds this order through the linkage meta and resolve_existing() keeps
	 * answering in_progress until the merchant (or a gateway webhook) settles it.
	 *
	 * @param object $order  Follow-on order.
	 * @param string $reason Masked, PII-free reason for the merchant note.
	 * @return OneClickResult
	 */
	private function hold_indeterminate( object $order, string $reason ): OneClickResult {
		$note = 'UpsellBay: one-click charge outcome is unknown (' . $reason . '). The order was kept on hold, not cancelled, so the shopper is not charged twice. Check the payment in your gateway dashboard before completing or cancelling this order.';

		if ( $this->is_paid( $order ) || ! method_exists( $order, 'update_status' ) ) {
			// Never downgrade an order that is already paid.
			$this->add_note( $order, $note );
		} else {
			$order->update_status( 'on-hold', $note );
		}

		$order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;

		return OneClickResult::in_progress( $order_id, 'The payment result is not confirmed yet.' );
	}

	/**
	 * Whether an order reports itself as paid.
	 *
	 * @param object $order Order.
	 */
	private function is_paid( object $order ): bool {
		return method_exists( $order, 'is_paid' ) && true === $order->is_paid();
	}

	/**
	 * Add a private order note when the order supports it.
	 *
	 * @param object $order Order.
	 * @param string $note  Note text (no secrets, no PII).
	 */
	private function add_note( object $order, string $note ): void {
		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( $note );
		}
	}

	/**
	 * Report what an earlier attempt for the same source order + offer did, without
	 * charging again.
	 *
	 * Paid -> duplicate (true success). Closed and unpaid -> declined, so the caller
	 * falls back to the separate checkout. Anything else (pending, on-hold, missing)
	 * may still be charging -> in_progress: neither success nor a fallback, so the
	 * shopper is not invited to pay a second time.
	 *
	 * @param int $order_id Existing follow-on order id.
	 * @return OneClickResult
	 */
	private function resolve_existing( int $order_id ): OneClickResult {
		$order = ( $this->order_loader )( $order_id );

		if ( is_object( $order ) && method_exists( $order, 'is_paid' ) && true === $order->is_paid() ) {
			return OneClickResult::duplicate( $order_id );
		}

		$status = ( is_object( $order ) && method_exists( $order, 'get_status' ) ) ? (string) $order->get_status() : '';
		if ( in_array( $status, self::CLOSED_UNPAID_STATUSES, true ) ) {
			return OneClickResult::declined( 'A previous one-click payment for this offer was not completed.' );
		}

		return OneClickResult::in_progress( $order_id, 'A payment for this offer is still being processed.' );
	}

	/**
	 * Default order loader (WooCommerce CRUD, HPOS-safe).
	 *
	 * @param int $order_id Order id.
	 * @return object|null
	 */
	private function wc_order_loader( int $order_id ): ?object {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return is_object( $order ) ? $order : null;
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
		if ( method_exists( $new_order, 'set_prices_include_tax' ) && function_exists( 'get_option' ) ) {
			$new_order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
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
