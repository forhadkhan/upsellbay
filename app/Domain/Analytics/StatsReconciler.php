<?php
/**
 * Aggregate stats reconciliation contract.
 *
 * @package UpsellBay\Domain\Analytics
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Analytics;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Data\StatsRepository;
use WPAnchorBay\UpsellBay\Core\Hooks;

/**
 * Repairs missing aggregate rows through bounded, idempotent operations.
 *
 * @since 1.0.0
 */
final class StatsReconciler {
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
	 * Ensure an aggregate row exists without incrementing counters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date      Store date.
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 */
	public function repair_missing_row( string $date, int $offer_id, string $placement ): void {
		$this->stats->increment( $date, $offer_id, $placement, array() );
		Hooks::action( 'daily_stats_reconciled', $date, $offer_id, $placement );
	}
}
