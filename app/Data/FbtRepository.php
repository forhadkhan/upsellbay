<?php
/**
 * Frequently-bought-together index repository.
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
 * Reads and writes the precomputed product-pair recommendation index.
 *
 * The index is aggregate product-to-product counts only (no PII). Reads are a single
 * indexed lookup — never a live order aggregation — so serving a recommendation on a
 * page request stays cheap on shared hosting. Writes happen only from the batch miner.
 *
 * @since 1.1.0
 */
final class FbtRepository {
	/**
	 * Recommendation reader callback.
	 *
	 * @var callable(int, int): array<int, array<string, mixed>>
	 */
	private $reader;

	/**
	 * Bulk index writer callback.
	 *
	 * @var callable(array<int, array<string, mixed>>, bool): int
	 */
	private $writer;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param callable|null $reader Recommendation reader.
	 * @param callable|null $writer Bulk index writer.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null ) {
		$this->reader = $reader ?? array( $this, 'wpdb_top_for' );
		$this->writer = $writer ?? array( $this, 'wpdb_bulk_upsert' );
	}

	/**
	 * Return the top recommended product IDs for an anchor product, highest score first.
	 *
	 * @since 1.1.0
	 *
	 * @param int $anchor_product_id Anchor product.
	 * @param int $limit             Max recommendations.
	 * @return array<int, int> Recommended product IDs.
	 */
	public function top_for( int $anchor_product_id, int $limit = 3 ): array {
		if ( $anchor_product_id <= 0 || $limit <= 0 ) {
			return array();
		}

		$rows = ( $this->reader )( $anchor_product_id, $limit );

		return array_values(
			array_filter(
				array_map(
					static fn ( array $row ): int => (int) ( $row['recommended_product_id'] ?? 0 ),
					is_array( $rows ) ? $rows : array()
				),
				static fn ( int $id ): bool => $id > 0
			)
		);
	}

	/**
	 * Replace the entire index with a freshly-mined set of rows.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $rows Mined index rows.
	 * @return int Number of rows written.
	 */
	public function replace_index( array $rows ): int {
		return ( $this->writer )( $rows, true );
	}

	/**
	 * Upsert a batch of index rows without clearing the table.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $rows Mined index rows.
	 * @return int Number of rows written.
	 */
	public function upsert_batch( array $rows ): int {
		return ( $this->writer )( $rows, false );
	}

	/**
	 * Read the top recommendations for an anchor from the index table.
	 *
	 * @param int $anchor_product_id Anchor product.
	 * @param int $limit             Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function wpdb_top_for( int $anchor_product_id, int $limit ): array {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return array();
		}

		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::FBT_TABLE_SUFFIX;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT recommended_product_id, score FROM {$table} WHERE anchor_product_id = %d ORDER BY score DESC LIMIT %d",
			$anchor_product_id,
			$limit
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Bulk-write index rows, optionally truncating first (full rebuild).
	 *
	 * @param array<int, array<string, mixed>> $rows     Rows to write.
	 * @param bool                             $truncate Whether to clear the table first.
	 * @return int Rows written.
	 */
	private function wpdb_bulk_upsert( array $rows, bool $truncate ): int {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return 0;
		}

		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::FBT_TABLE_SUFFIX;
		$now   = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

		if ( $truncate ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "TRUNCATE TABLE {$table}" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$written = 0;
		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$values       = array();
			$placeholders = array();
			foreach ( $chunk as $row ) {
				$anchor      = (int) ( $row['anchor_product_id'] ?? 0 );
				$recommended = (int) ( $row['recommended_product_id'] ?? 0 );
				if ( $anchor <= 0 || $recommended <= 0 || $anchor === $recommended ) {
					continue;
				}

				$placeholders[] = '(%d, %d, %d, %d, %f, %s)';
				array_push(
					$values,
					$anchor,
					$recommended,
					(int) ( $row['co_occurrence_count'] ?? 0 ),
					(int) ( $row['anchor_order_count'] ?? 0 ),
					(float) ( $row['score'] ?? 0 ),
					$now
				);
				++$written;
			}

			if ( array() === $placeholders ) {
				continue;
			}

			$sql = "INSERT INTO {$table}
				(anchor_product_id, recommended_product_id, co_occurrence_count, anchor_order_count, score, updated_at)
				VALUES " . implode( ', ', $placeholders ) . '
				ON DUPLICATE KEY UPDATE
					co_occurrence_count = VALUES(co_occurrence_count),
					anchor_order_count = VALUES(anchor_order_count),
					score = VALUES(score),
					updated_at = VALUES(updated_at)';

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( $sql, $values ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $written;
	}
}
