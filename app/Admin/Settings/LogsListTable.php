<?php
/**
 * Logs List Table.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WP_List_Table;
use WPAnchorBay\UpsellBay\Data\LogRepository;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the log entries table.
 *
 * @since 1.0.0
 */
final class LogsListTable extends WP_List_Table {
	/**
	 * Log repository.
	 *
	 * @since 1.0.0
	 *
	 * @var LogRepository
	 */
	private LogRepository $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LogRepository $repository Log repository.
	 */
	public function __construct( LogRepository $repository ) {
		$this->repository = $repository;

		parent::__construct(
			array(
				'singular' => __( 'Log Entry', 'upsellbay' ),
				'plural'   => __( 'Log Entries', 'upsellbay' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Prepare items for the table.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'upsellbay_logs_per_page', 20 );
		$paged    = $this->get_pagenum();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log_type = isset( $_REQUEST['log_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['log_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		$args = array(
			'per_page' => $per_page,
			'paged'    => $paged,
			'orderby'  => $orderby,
			'order'    => $order,
			'search'   => $search,
			'log_type' => $log_type,
			'status'   => $status,
		);

		$result = $this->repository->get_logs( $args );

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);

		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable, 'title' );
	}

	/**
	 * Define columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Title', 'upsellbay' ),
			'log_type'   => __( 'Type', 'upsellbay' ),
			'status'     => __( 'Status', 'upsellbay' ),
			'user'       => __( 'User', 'upsellbay' ),
			'created_at' => __( 'Date', 'upsellbay' ),
			'actions'    => __( 'Actions', 'upsellbay' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'title'      => array( 'title', false ),
			'log_type'   => array( 'log_type', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Render default column.
	 *
	 * @since 1.0.0
	 *
	 * @param object|array<mixed> $item        Item.
	 * @param mixed               $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		$item = (object) $item;
		switch ( $column_name ) {
			case 'title':
				return esc_html( $item->title );
			case 'log_type':
				return esc_html( $item->log_type );
			case 'status':
				$status_class = match ( $item->status ) {
					'error', 'critical', 'emergency', 'alert' => 'upsellbay-badge--error',
					'warning' => 'upsellbay-badge--warning',
					'success', 'notice' => 'upsellbay-badge--active',
					default => 'upsellbay-badge--inactive',
				};
				return '<span class="upsellbay-badge ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
			case 'user':
				if ( '' !== $item->user_id && null !== $item->user_id ) {
					$user = get_userdata( (int) $item->user_id );
					if ( $user ) {
						return esc_html( $user->display_name );
					}
					return esc_html( 'ID: ' . $item->user_id );
				}
				return '&mdash;';
			case 'created_at':
				$timestamp = strtotime( $item->created_at );
				return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : esc_html( $item->created_at );
			default:
				return '';
		}
	}

	/**
	 * Render checkbox column.
	 *
	 * @since 1.0.0
	 *
	 * @param object|array<mixed> $item Item.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		$item = (object) $item;
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'log_ids',
			$item->id
		);
	}

	/**
	 * Actions column.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item Log item.
	 * @return string
	 */
	protected function column_actions( $item ): string {
		$view_url   = wp_nonce_url( admin_url( 'admin.php?page=upsellbay&tab=settings&section=logs&action=view&log_id=' . $item->id ), 'upsellbay_view_log_' . $item->id );
		$delete_url = wp_nonce_url( admin_url( 'admin.php?page=upsellbay&tab=settings&section=logs&action=delete&log_id=' . $item->id ), 'upsellbay_delete_log_' . $item->id );

		$actions = array(
			'copy'   => sprintf(
				'<button type="button" class="button button-small upsellbay-copy-log" style="min-width: 60px; text-align: center;" data-clipboard-text="%s" title="%s">%s</button>',
				esc_attr( $this->generate_log_report( $item ) ),
				esc_attr__( 'Copy details to clipboard', 'upsellbay' ),
				esc_html__( 'Copy', 'upsellbay' )
			),
			'view'   => sprintf( '<a href="%s" class="button button-small">%s</a>', esc_url( $view_url ), esc_html__( 'Details', 'upsellbay' ) ),
			'delete' => sprintf(
				'<a href="%s" class="button button-small button-link-delete upsellbay-modal-trigger" data-modal-title="%s" data-modal-message="%s" data-modal-confirm="%s" data-modal-cancel="%s">%s</a>',
				esc_url( $delete_url ),
				esc_attr__( 'Delete Log Entry', 'upsellbay' ),
				esc_attr__( 'Are you sure you want to permanently delete this log entry? This cannot be undone.', 'upsellbay' ),
				esc_attr__( 'Delete', 'upsellbay' ),
				esc_attr__( 'Cancel', 'upsellbay' ),
				esc_html__( 'Delete', 'upsellbay' )
			),
		);

		return implode( ' ', $actions );
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		$statuses = array(
			'info'      => __( 'Info', 'upsellbay' ),
			'notice'    => __( 'Notice', 'upsellbay' ),
			'warning'   => __( 'Warning', 'upsellbay' ),
			'error'     => __( 'Error', 'upsellbay' ),
			'critical'  => __( 'Critical', 'upsellbay' ),
			'alert'     => __( 'Alert', 'upsellbay' ),
			'emergency' => __( 'Emergency', 'upsellbay' ),
		);

		echo '<div class="alignleft actions">';
		echo '<select name="status" id="filter-by-status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'upsellbay' ) . '</option>';
		foreach ( $statuses as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current_status, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'upsellbay' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		echo '</div>';
	}

	/**
	 * Return bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'bulk-delete' => __( 'Delete', 'upsellbay' ),
		);
	}
	/**
	 * Generate a raw text report of the log for copying.
	 *
	 * @since 1.0.0
	 *
	 * @param object $log Log object.
	 * @return string
	 */
	private function generate_log_report( object $log ): string {
		$report  = "UpsellBay Log Report\n\n";
		$report .= 'Log ID: ' . $log->id . "\n";
		$report .= 'Date: ' . $log->created_at . "\n";
		$report .= 'Plugin Version: ' . \WPAnchorBay\UpsellBay\Core\Constants::VERSION . "\n";
		$report .= "Event:\nType: " . $log->log_type . "\nStatus: " . $log->status . "\n";
		$report .= 'Title: ' . $log->title . "\n\n";

		if ( isset( $log->description ) && '' !== $log->description ) {
			$report .= "Description:\n" . $log->description . "\n\n";
		}

		$log_arr = get_object_vars( $log );
		foreach ( array(
			'request_data'  => __( 'Request Data', 'upsellbay' ),
			'response_data' => __( 'Response Data', 'upsellbay' ),
			'metadata'      => __( 'Metadata', 'upsellbay' ),
		) as $field => $label ) {
			if ( isset( $log_arr[ $field ] ) && '' !== $log_arr[ $field ] && '[]' !== $log_arr[ $field ] ) {
				$decoded   = json_decode( (string) $log_arr[ $field ], true );
				$formatted = null !== $decoded ? wp_json_encode( $decoded, JSON_PRETTY_PRINT ) : (string) $log_arr[ $field ];
				$report   .= "{$label}:\n" . $formatted . "\n\n";
			}
		}

		return $report;
	}
}
