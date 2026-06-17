<?php
/**
 * Aggregate analytics recorder.
 *
 * @package UpsellBay\Domain\Analytics
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Analytics;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Data\StatsRepository;

/**
 * Records offer events into the non-PII aggregate stats table.
 *
 * @since 1.0.0
 */
final class AnalyticsRecorder {
	/**
	 * Stats repository.
	 *
	 * @since 1.0.0
	 *
	 * @var StatsRepository
	 */
	private StatsRepository $stats;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param StatsRepository $stats Stats repository.
	 */
	public function __construct( StatsRepository $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Record an offer view.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 * @param string $date      Store date.
	 */
	public function record_view( int $offer_id, string $placement, string $date ): void {
		$this->stats->increment( $date, $offer_id, $placement, array( 'views' => 1 ) );
	}

	/**
	 * Record an accepted offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id       Offer ID.
	 * @param string $placement      Placement key.
	 * @param string $date           Store date.
	 * @param string $revenue        Revenue delta.
	 * @param string $discount_total Discount delta.
	 */
	public function record_accept( int $offer_id, string $placement, string $date, string $revenue = '0.000000', string $discount_total = '0.000000' ): void {
		$this->stats->increment(
			$date,
			$offer_id,
			$placement,
			array(
				'accepts'        => 1,
				'revenue'        => $revenue,
				'discount_total' => $discount_total,
			)
		);
	}

	/**
	 * Record a dismissed offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 * @param string $date      Store date.
	 */
	public function record_dismissal( int $offer_id, string $placement, string $date ): void {
		$this->stats->increment( $date, $offer_id, $placement, array( 'dismissals' => 1 ) );
	}

	/**
	 * Record an order that contains an accepted offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id       Offer ID.
	 * @param string $placement      Placement key.
	 * @param string $date           Store date.
	 * @param string $revenue        Revenue delta.
	 * @param string $discount_total Discount delta.
	 */
	public function record_order( int $offer_id, string $placement, string $date, string $revenue, string $discount_total ): void {
		$this->stats->increment(
			$date,
			$offer_id,
			$placement,
			array(
				'orders'         => 1,
				'revenue'        => $revenue,
				'discount_total' => $discount_total,
			)
		);
	}
}
