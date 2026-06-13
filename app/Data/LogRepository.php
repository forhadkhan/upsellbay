<?php
/**
 * Log Repository.
 *
 * @package UpsellBay\Data
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Data;

use WPAnchorBay\UpsellBay\Core\Constants;
use wpdb;

/**
 * Handles database operations for logs.
 *
 * @since 1.0.0
 */
final class LogRepository {
	/**
	 * Insert a new log entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Log data.
	 * @return int|false The new log ID or false on failure.
	 */
	public function insert( array $data ) {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return false;
		}

		/** @var wpdb $wpdb WordPress database object. */
		$wpdb = $GLOBALS['wpdb'];

		$table = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;

		$defaults = array(
			'log_type'      => 'info',
			'title'         => '',
			'description'   => '',
			'status'        => 'info',
			'source'        => null,
			'user_id'       => get_current_user_id(),
			'object_type'   => null,
			'object_id'     => null,
			'request_data'  => null,
			'response_data' => null,
			'metadata'      => null,
			'ip_address'    => null,
			'user_agent'    => null,
			'created_at'    => current_time( 'mysql', true ),
		);

		$data = array_merge( $defaults, $data );

		// Serialize arrays/objects if needed.
		foreach ( array( 'request_data', 'response_data', 'metadata' ) as $key ) {
			if ( isset( $data[ $key ] ) && ! is_scalar( $data[ $key ] ) ) {
				$data[ $key ] = wp_json_encode( $data[ $key ] );
			}
		}

		$result = $wpdb->insert(
			$table,
			$data,
			array(
				'%s', // log_type.
				'%s', // title.
				'%s', // description.
				'%s', // status.
				'%s', // source.
				'%d', // user_id.
				'%s', // object_type.
				'%d', // object_id.
				'%s', // request_data.
				'%s', // response_data.
				'%s', // metadata.
				'%s', // ip_address.
				'%s', // user_agent.
				'%s', // created_at.
			)
		);

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve a single log by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Log ID.
	 * @return object|null Log object or null if not found.
	 */
	public function get_log( int $id ): ?object {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return null;
		}

		/** @var wpdb $wpdb WordPress database object. */
		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return is_object( $log ) ? $log : null;
	}

	/**
	 * Retrieve logs with optional filtering, sorting, and pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items: array<object>, total: int}
	 */
	public function get_logs( array $args = array() ): array {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		/** @var wpdb $wpdb WordPress database object. */
		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;

		$defaults = array(
			'per_page' => 20,
			'paged'    => 1,
			'orderby'  => 'id',
			'order'    => 'DESC',
			'search'   => '',
			'log_type' => '',
			'status'   => '',
		);

		$args = array_merge( $defaults, $args );

		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$search          = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where_clauses[] = '(title LIKE %s OR description LIKE %s OR log_type LIKE %s)';
			$where_values[]  = $search;
			$where_values[]  = $search;
			$where_values[]  = $search;
		}

		if ( isset( $args['log_type'] ) && '' !== $args['log_type'] ) {
			$where_clauses[] = 'log_type = %s';
			$where_values[]  = (string) $args['log_type'];
		}

		if ( isset( $args['status'] ) && '' !== $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = (string) $args['status'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		if ( count( $where_values ) > 0 ) {
			$where_sql = $wpdb->prepare( $where_sql, ...$where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$orderby = in_array( strtolower( $args['orderby'] ), array( 'id', 'created_at', 'log_type', 'status', 'user_id', 'title' ), true ) ? strtolower( $args['orderby'] ) : 'id';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		// Count query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE {$where_sql}" );

		// Data query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d, %d", $offset, $per_page ) );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete a single log.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Log ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return false;
		}

		/** @var wpdb $wpdb WordPress database object. */
		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Delete multiple logs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $ids Array of log IDs.
	 * @return bool True on success, false on failure.
	 */
	public function bulk_delete( array $ids ): bool {
		if ( count( $ids ) === 0 || ! isset( $GLOBALS['wpdb'] ) ) {
			return false;
		}

		/** @var wpdb $wpdb WordPress database object. */
		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;

		$ids_sql = implode( ',', array_map( 'absint', $ids ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false !== $wpdb->query( "DELETE FROM {$table} WHERE id IN ({$ids_sql})" );
	}

	/**
	 * Clean up logs older than a specific number of days.
	 *
	 * @since 1.0.0
	 *
	 * @param int $days Number of days to retain logs.
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_logs( int $days ): int {
		if ( $days < 1 || ! isset( $GLOBALS['wpdb'] ) ) {
			return 0;
		}

		/** @var wpdb $wpdb WordPress database object. */
		$wpdb  = $GLOBALS['wpdb'];
		$table = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff_date ) );

		return is_int( $deleted ) ? $deleted : 0;
	}
}
