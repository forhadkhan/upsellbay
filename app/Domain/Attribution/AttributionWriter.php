<?php
/**
 * Offer attribution writer.
 *
 * @package UpsellBay\Domain\Attribution
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Attribution;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Writes attribution metadata through WooCommerce CRUD object methods.
 *
 * @since 1.0.0
 */
final class AttributionWriter {
	/**
	 * Write attribution to an order item.
	 *
	 * @since 1.0.0
	 *
	 * @param object               $item            WC order item-like object.
	 * @param array<string, mixed> $offer           Offer.
	 * @param string               $placement       Placement.
	 * @param string               $discount_amount Discount amount.
	 * @param string               $source_context  Source context.
	 */
	public function write_order_item( object $item, array $offer, string $placement, string $discount_amount, string $source_context ): void {
		if ( ! method_exists( $item, 'add_meta_data' ) ) {
			return;
		}

		$meta = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$item->add_meta_data( Constants::ATTRIBUTION_OFFER_ID, (int) ( $offer['id'] ?? 0 ) );
		$item->add_meta_data( Constants::ATTRIBUTION_OFFER_TYPE, (string) ( $meta['_ub_offer_type'] ?? '' ) );
		$item->add_meta_data( Constants::ATTRIBUTION_OFFER_PLACEMENT, $placement );
		$item->add_meta_data( Constants::ATTRIBUTION_DISCOUNT_TYPE, (string) ( $meta['_ub_discount_type'] ?? 'none' ) );
		$item->add_meta_data( Constants::ATTRIBUTION_DISCOUNT_AMOUNT, $discount_amount );
		$item->add_meta_data( Constants::ATTRIBUTION_SOURCE_CONTEXT, $source_context );
	}

	/**
	 * Write follow-on order linkage.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order           WC order-like object.
	 * @param int    $source_order_id Source order ID.
	 * @param int    $source_offer_id Source offer ID.
	 */
	public function write_follow_on_order( object $order, int $source_order_id, int $source_offer_id ): void {
		if ( ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( Constants::ATTRIBUTION_SOURCE_ORDER_ID, $source_order_id );
		$order->update_meta_data( Constants::ATTRIBUTION_SOURCE_OFFER_ID, $source_offer_id );
		$order->update_meta_data( Constants::ATTRIBUTION_FOLLOW_ON_ORDER, true );

		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}
}
