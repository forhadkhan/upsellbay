<?php
/**
 * Logs Section Renderer.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Data\LogRepository;

/**
 * Handles the view logic and processing for the Logs section.
 *
 * @since 1.0.0
 */
final class LogsSectionRenderer {
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
	}

	/**
	 * Process potential actions before rendering.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string}|null
	 */
	public function process_actions(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) ) : '';

		$current_action = ( '' !== $action && '-1' !== $action ) ? $action : ( ( '' !== $action2 && '-1' !== $action2 ) ? $action2 : '' );

		if ( 'delete' === $current_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$log_id = isset( $_REQUEST['log_id'] ) ? (int) $_REQUEST['log_id'] : 0;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( $log_id > 0 && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'upsellbay_delete_log_' . $log_id ) ) {
				$this->repository->delete( $log_id );
				return array(
					'success' => true,
					'message' => __( 'Log entry deleted.', 'upsellbay' ),
				);
			}
		}

		if ( 'bulk-delete' === $current_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$log_ids = isset( $_REQUEST['log_ids'] ) ? array_map( 'absint', (array) $_REQUEST['log_ids'] ) : array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( count( $log_ids ) > 0 && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-log_entries' ) ) {
				$this->repository->bulk_delete( $log_ids );
				return array(
					'success' => true,
					/* translators: %d: number of log entries deleted */
					'message' => sprintf( __( '%d log entries deleted.', 'upsellbay' ), count( $log_ids ) ),
				);
			}
		}

		return null;
	}

	/**
	 * Render the section content.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log_id = isset( $_REQUEST['log_id'] ) ? (int) $_REQUEST['log_id'] : 0;

		if ( 'view' === $action && $log_id > 0 ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'upsellbay_view_log_' . $log_id ) ) {
				$this->render_details( $log_id );
				return;
			}
		}

		$this->render_table();
	}

	/**
	 * Render the WP_List_Table.
	 *
	 * @since 1.0.0
	 */
	private function render_table(): void {
		$table = new LogsListTable( $this->repository );
		$table->prepare_items();

		echo '<h2>' . esc_html__( 'Logs', 'upsellbay' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'System events, API activity, and errors for diagnostics.', 'upsellbay' ) . '</p>';

		echo '<form id="upsellbay-logs-filter" method="get">';
		echo '<input type="hidden" name="page" value="upsellbay" />';
		echo '<input type="hidden" name="tab" value="settings" />';
		echo '<input type="hidden" name="section" value="logs" />';

		$table->search_box( __( 'Search Logs', 'upsellbay' ), 'upsellbay-logs' );
		$table->display();
		echo '</form>';
	}

	/**
	 * Render the log details view.
	 *
	 * @since 1.0.0
	 *
	 * @param int $log_id Log ID.
	 */
	private function render_details( int $log_id ): void {
		$log = $this->repository->get_log( $log_id );

		if ( ! $log ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Log entry not found.', 'upsellbay' ) . '</p></div>';
			return;
		}

		$back_url = admin_url( 'admin.php?page=upsellbay&tab=settings&section=logs' );

		echo '<h2>' . esc_html__( 'Log Details', 'upsellbay' ) . '</h2>';
		echo '<p><a href="' . esc_url( $back_url ) . '" class="button button-secondary"><span class="dashicons dashicons-arrow-left-alt2"></span> ' . esc_html__( 'Back to Logs', 'upsellbay' ) . '</a></p>';

		echo '<div class="upsellbay-log-details postbox" style="padding: 20px;">';

		$report  = "UpsellBay Log Report\n\n";
		$report .= 'Log ID: ' . $log->id . "\n";
		$report .= 'Date: ' . $log->created_at . "\n";
		$report .= 'Plugin Version: ' . Constants::VERSION . "\n";
		$report .= "Event:\nType: " . $log->log_type . "\nStatus: " . $log->status . "\n";
		$report .= 'Title: ' . $log->title . "\n\n";

		if ( isset( $log->description ) && '' !== $log->description ) {
			$report .= "Description:\n" . $log->description . "\n\n";
		}

		echo '<h3>' . esc_html__( 'Overview', 'upsellbay' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Title', 'upsellbay' ) . '</th><td>' . esc_html( $log->title ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Date', 'upsellbay' ) . '</th><td>' . esc_html( $log->created_at ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Type', 'upsellbay' ) . '</th><td>' . esc_html( $log->log_type ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Status', 'upsellbay' ) . '</th><td>' . esc_html( $log->status ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'IP Address', 'upsellbay' ) . '</th><td>' . esc_html( (string) $log->ip_address ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'User ID', 'upsellbay' ) . '</th><td>' . esc_html( (string) $log->user_id ) . '</td></tr>';
		if ( isset( $log->object_type ) && '' !== $log->object_type ) {
			$object_id = isset( $log->object_id ) ? (string) $log->object_id : '';
			echo '<tr><th scope="row">' . esc_html__( 'Related Object', 'upsellbay' ) . '</th><td>' . esc_html( $log->object_type ) . ' (ID: ' . esc_html( $object_id ) . ')</td></tr>';
		}
		echo '</tbody></table>';

		if ( isset( $log->description ) && '' !== $log->description ) {
			echo '<h3>' . esc_html__( 'Description', 'upsellbay' ) . '</h3>';
			echo '<pre style="background: #f0f0f1; padding: 10px; overflow-x: auto;">' . esc_html( $log->description ) . '</pre>';
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

				echo '<h3>' . esc_html( $label ) . '</h3>';
				echo '<pre style="background: #f0f0f1; padding: 10px; overflow-x: auto;">' . esc_html( $formatted ) . '</pre>';
			}
		}

		echo '<h3>' . esc_html__( 'Export', 'upsellbay' ) . '</h3>';
		echo '<p>' . esc_html__( 'Copy this text to provide to support:', 'upsellbay' ) . '</p>';
		echo '<p>';
		echo sprintf(
			'<button type="button" class="button upsellbay-copy-log" style="min-width: 148px; text-align: center; margin-bottom: 10px;" data-clipboard-text="%s" title="%s">%s</button>',
			esc_attr( $report ),
			esc_attr__( 'Copy details to clipboard', 'upsellbay' ),
			esc_html__( 'Copy to clipboard', 'upsellbay' )
		);
		echo '</p>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<textarea readonly class="large-text" rows="15" onclick="this.select();">' . htmlspecialchars( $report, ENT_QUOTES, 'UTF-8' ) . '</textarea>';

		echo '</div>';
	}
}
