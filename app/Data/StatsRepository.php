<?php
/**
 * Aggregate stats repository.
 *
 * @package UpsellBay\Data
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Core\Constants;

/**
 * Encapsulates non-PII daily aggregate stats reads and writes.
 *
 * @since 1.0.0
 */
final class StatsRepository {
	/**
	 * Atomic upsert callback.
	 *
	 * @var callable(string, array<string, int|string>): void
	 */
	private $upsert;

	/**
	 * Bounded stats reader callback.
	 *
	 * @var callable(array<string, mixed>): array<string, array<string, int|string>>
	 */
	private $reader;

	/**
	 * Retention delete callback.
	 *
	 * @var callable(string): int
	 */
	private $pruner;

	/**
	 * Most rows one prune run deletes, so a first run on a large table does not
	 * hold a long lock. Later daily runs work through any remainder.
	 *
	 * @var int
	 */
	private const PRUNE_BATCH_LIMIT = 10000;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $upsert Atomic upsert callback.
	 * @param callable|null $reader Bounded stats reader.
	 * @param callable|null $pruner Retention delete callback (receives the Y-m-d cutoff).
	 */
	public function __construct( ?callable $upsert = null, ?callable $reader = null, ?callable $pruner = null ) {
		$this->upsert = $upsert ?? array( $this, 'wpdb_upsert' );
		$this->reader = $reader ?? array( $this, 'wpdb_read' );
		$this->pruner = $pruner ?? array( $this, 'wpdb_prune' );
	}

	/**
	 * Delete aggregate rows dated before a cutoff day.
	 *
	 * Touches only Pro's own daily stats table. Offers, orders and order
	 * attribution meta are never read or deleted here.
	 *
	 * @since 1.0.0
	 *
	 * @param string $before_date Cutoff store date (Y-m-d); rows strictly older are deleted.
	 * @return int Rows deleted.
	 */
	public function prune_before( string $before_date ): int {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before_date ) ) {
			return 0;
		}

		return max( 0, (int) ( $this->pruner )( $before_date ) );
	}

	/**
	 * Atomically increment aggregate counters.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $date      Store date.
	 * @param int                       $offer_id  Offer ID.
	 * @param string                    $placement Placement key.
	 * @param array<string, int|string> $delta     Counter deltas.
	 */
	public function increment( string $date, int $offer_id, string $placement, array $delta ): void {
		( $this->upsert )( $this->row_key( $date, $offer_id, $placement ), $this->normalize_delta( $delta ) );
	}

	/**
	 * Return a bounded aggregate summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $start_date Start date.
	 * @param string               $end_date   End date.
	 * @param array<string, mixed> $filters    Filters.
	 * @return array<string, int|string>
	 */
	public function summary( string $start_date, string $end_date, array $filters = array() ): array {
		$rows    = ( $this->reader )(
			array_merge(
				$filters,
				array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			)
		);
		$summary = array(
			'views'          => 0,
			'accepts'        => 0,
			'dismissals'     => 0,
			'orders'         => 0,
			'revenue'        => '0.000000',
			'discount_total' => '0.000000',
		);

		foreach ( $rows as $row ) {
			foreach ( array( 'views', 'accepts', 'dismissals', 'orders' ) as $field ) {
				$summary[ $field ] += (int) ( $row[ $field ] ?? 0 );
			}
			foreach ( array( 'revenue', 'discount_total' ) as $field ) {
				$summary[ $field ] = number_format( (float) $summary[ $field ] + (float) ( $row[ $field ] ?? 0 ), 6, '.', '' );
			}
		}

		return $summary;
	}

	/**
	 * Build a stable row key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date      Store date.
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 */
	public function row_key( string $date, int $offer_id, string $placement ): string {
		return $date . '|' . $offer_id . '|' . $placement;
	}

	/**
	 * Normalize a delta payload.
	 *
	 * @param array<string, int|string> $delta Raw delta.
	 * @return array<string, int|string>
	 */
	private function normalize_delta( array $delta ): array {
		$allowed    = array( 'views', 'accepts', 'dismissals', 'orders', 'revenue', 'discount_total' );
		$normalized = array();

		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $delta ) ) {
				continue;
			}

			$normalized[ $field ] = in_array( $field, array( 'revenue', 'discount_total' ), true )
				? number_format( (float) $delta[ $field ], 6, '.', '' )
				: max( 0, (int) $delta[ $field ] );
		}

		return $normalized;
	}

	/**
	 * WordPress database upsert adapter.
	 *
	 * @param string                    $key   Row key.
	 * @param array<string, int|string> $delta Delta.
	 */
	private function wpdb_upsert( string $key, array $delta ): void {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return;
		}

		list( $date, $offer_id, $placement ) = explode( '|', $key );
		$wpdb                                = $GLOBALS['wpdb'];
		$table_name                          = $wpdb->prefix . Constants::STATS_TABLE_SUFFIX;
		$now                                 = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$defaults                            = array(
			'views'          => 0,
			'accepts'        => 0,
			'dismissals'     => 0,
			'orders'         => 0,
			'revenue'        => '0.000000',
			'discount_total' => '0.000000',
		);
		$delta                               = array_replace( $defaults, $delta );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (stat_date, offer_id, placement, views, accepts, dismissals, orders, revenue, discount_total, updated_at)
				VALUES (%s, %d, %s, %d, %d, %d, %d, %f, %f, %s)
				ON DUPLICATE KEY UPDATE
					views = views + VALUES(views),
					accepts = accepts + VALUES(accepts),
					dismissals = dismissals + VALUES(dismissals),
					orders = orders + VALUES(orders),
					revenue = revenue + VALUES(revenue),
					discount_total = discount_total + VALUES(discount_total),
					updated_at = VALUES(updated_at)",
				$date,
				(int) $offer_id,
				$placement,
				(int) $delta['views'],
				(int) $delta['accepts'],
				(int) $delta['dismissals'],
				(int) $delta['orders'],
				(float) $delta['revenue'],
				(float) $delta['discount_total'],
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * WordPress database retention delete adapter.
	 *
	 * @param string $before_date Cutoff store date (Y-m-d).
	 */
	private function wpdb_prune( string $before_date ): int {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return 0;
		}

		$wpdb       = $GLOBALS['wpdb'];
		$table_name = $wpdb->prefix . Constants::STATS_TABLE_SUFFIX;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE stat_date < %s LIMIT %d",
				$before_date,
				self::PRUNE_BATCH_LIMIT
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * WordPress database bounded read adapter.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<string, array<string, int|string>>
	 */
	private function wpdb_read( array $filters ): array {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return array();
		}

		$wpdb       = $GLOBALS['wpdb'];
		$table_name = $wpdb->prefix . Constants::STATS_TABLE_SUFFIX;

		$where  = 'stat_date >= %s AND stat_date <= %s';
		$params = array( (string) $filters['start_date'], (string) $filters['end_date'] );

		if ( isset( $filters['offer_id'] ) ) {
			$where   .= ' AND offer_id = %d';
			$params[] = (int) $filters['offer_id'];
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT views, accepts, dismissals, orders, revenue, discount_total FROM {$table_name} WHERE {$where}",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return is_array( $rows ) ? $rows : array();
	}
}
