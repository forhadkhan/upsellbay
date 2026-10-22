<?php
/**
 * Follow-on order linkage writer contract.
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
 * Writes the source-order + offer linkage onto a follow-on order.
 *
 * The one-click charger depends on this linkage for idempotency (a retry finds the
 * first follow-on order through it), so it must never charge when the write fails.
 *
 * @since 1.0.0
 */
interface FollowOnLinkageWriter {
	/**
	 * Write follow-on order linkage.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order           WC order-like object.
	 * @param int    $source_order_id Source order ID.
	 * @param int    $source_offer_id Source offer ID.
	 * @return bool Whether the linkage was written.
	 */
	public function write_follow_on_order( object $order, int $source_order_id, int $source_offer_id ): bool;
}
