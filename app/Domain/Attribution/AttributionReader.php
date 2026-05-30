<?php
/**
 * Offer attribution reader.
 *
 * @package UpsellBay\Domain\Attribution
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Attribution;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Reads attribution metadata through WooCommerce CRUD object methods.
 *
 * @since 1.0.0
 */
final class AttributionReader {
	/**
	 * Read order item attribution.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item WC order item-like object.
	 * @return array<string, mixed>
	 */
	public function read_order_item( object $item ): array {
		return $this->read_meta(
			$item,
			array(
				Constants::ATTRIBUTION_OFFER_ID,
				Constants::ATTRIBUTION_OFFER_TYPE,
				Constants::ATTRIBUTION_OFFER_PLACEMENT,
				Constants::ATTRIBUTION_DISCOUNT_TYPE,
				Constants::ATTRIBUTION_DISCOUNT_AMOUNT,
				Constants::ATTRIBUTION_SOURCE_CONTEXT,
			)
		);
	}

	/**
	 * Read order-level follow-on attribution.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order WC order-like object.
	 * @return array<string, mixed>
	 */
	public function read_order( object $order ): array {
		return $this->read_meta(
			$order,
			array(
				Constants::ATTRIBUTION_SOURCE_ORDER_ID,
				Constants::ATTRIBUTION_SOURCE_OFFER_ID,
				Constants::ATTRIBUTION_FOLLOW_ON_ORDER,
			)
		);
	}

	/**
	 * Read a set of meta keys.
	 *
	 * @param object             $meta_object Object.
	 * @param array<int, string> $keys   Keys.
	 * @return array<string, mixed>
	 */
	private function read_meta( object $meta_object, array $keys ): array {
		if ( ! method_exists( $meta_object, 'get_meta' ) ) {
			return array();
		}

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = $meta_object->get_meta( $key );
		}

		return $data;
	}
}
