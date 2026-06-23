<?php
/**
 * Tools admin page.
 *
 * @package UpsellBay\Admin\Tools
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;

/**
 * Provides full data import and export.
 *
 * @since 1.0.0
 */
final class ToolsPage {
	/**
	 * Import/export helper.
	 *
	 * @since 1.0.0
	 *
	 * @var ImportExporter
	 */
	private ImportExporter $import_exporter;

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Compatibility scanner.
	 *
	 * @since 1.0.0
	 *
	 * @var CompatibilityScanner
	 */
	private CompatibilityScanner $scanner;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param ImportExporter            $import_exporter Import/export helper.
	 * @param Settings                  $settings        Settings service.
	 * @param CompatibilityScanner|null $scanner         Compatibility scanner.
	 */
	public function __construct( ImportExporter $import_exporter, Settings $settings, ?CompatibilityScanner $scanner = null ) {
		$this->import_exporter = $import_exporter;
		$this->settings        = $settings;
		$this->scanner         = $scanner ?? new CompatibilityScanner();
	}

	/**
	 * Render tools shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		echo '<div class="wrap woocommerce upsellbay-admin">';
		$this->render_content();
		echo '</div>';
	}

	/**
	 * Render tools tab content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Optional request payload.
	 */
	public function render_content( array $request = array() ): void {
		unset( $request );
		
		$preview_hash = isset( $_GET['preview_import'] ) ? sanitize_key( wp_unslash( $_GET['preview_import'] ) ) : '';

		if ( ! empty( $preview_hash ) ) {
			$this->render_preview( $preview_hash );
			return;
		}

		echo '<div class="upsellbay-tools-container" style="display: flex; gap: 20px; flex-wrap: wrap;">';

		// Export Data Card
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Export Data', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download all UpsellBay data including your active/paused offers and global settings. This JSON file can be used as a backup or to migrate your setup to another store.', 'upsellbay' ) . '</p>';
		
		$export_url = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $export_url ) . '">';
		wp_nonce_field( 'upsellbay_export_data' );
		echo '<input type="hidden" name="action" value="upsellbay_export_data">';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Export Whole Data', 'upsellbay' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		// Import Data Card
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 id="import" style="margin-top:0;">' . esc_html__( 'Import Data', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Upload a previously exported JSON file. This will restore all your settings and append the imported offers to your store.', 'upsellbay' ) . '</p>';
		
		$import_url = admin_url( 'admin-post.php' );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $import_url ) . '">';
		wp_nonce_field( 'upsellbay_import_data' );
		echo '<input type="hidden" name="action" value="upsellbay_import_data">';
		echo '<p><input type="file" name="import_file" accept=".json" required></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Upload and Preview', 'upsellbay' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render the import preview screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash Import hash.
	 */
	private function render_preview( string $hash ): void {
		$json = get_transient( 'upsellbay_import_' . $hash );
		if ( empty( $json ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Import session expired or invalid. Please try uploading the file again.', 'upsellbay' ) . '</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=upsellbay&tab=tools' ) ) . '" class="button">' . esc_html__( 'Go Back', 'upsellbay' ) . '</a></p>';
			return;
		}

		$payload = json_decode( (string) $json, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$has_settings = isset( $payload['settings'] ) && is_array( $payload['settings'] );
		$offers_count = isset( $payload['offers'] ) && is_array( $payload['offers'] ) ? count( $payload['offers'] ) : 0;

		echo '<div class="upsellbay-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Import Preview', 'upsellbay' ) . '</h2>';
		
		echo '<div style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #3858e9;">';
		echo '<p style="margin: 0 0 10px 0;"><strong>' . esc_html__( 'Found in file:', 'upsellbay' ) . '</strong></p>';
		echo '<ul style="margin: 0; padding-left: 20px;">';
		if ( $has_settings ) {
			echo '<li>' . esc_html__( 'Global Settings', 'upsellbay' ) . '</li>';
		}
		echo '<li>' . esc_html( sprintf( _n( '%s Offer', '%s Offers', $offers_count, 'upsellbay' ), number_format_i18n( $offers_count ) ) ) . '</li>';
		echo '</ul>';
		echo '</div>';

		$confirm_url = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $confirm_url ) . '">';
		wp_nonce_field( 'upsellbay_confirm_import' );
		echo '<input type="hidden" name="action" value="upsellbay_confirm_import">';
		echo '<input type="hidden" name="import_hash" value="' . esc_attr( $hash ) . '">';

		echo '<table class="form-table" role="presentation"><tbody>';
		
		if ( $has_settings ) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html__( 'Settings', 'upsellbay' ) . '</th>';
			echo '<td>';
			echo '<label><input type="checkbox" name="import_settings" value="1" checked> ' . esc_html__( 'Import global settings', 'upsellbay' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'This will overwrite your current settings.', 'upsellbay' ) . '</p>';
			echo '</td>';
			echo '</tr>';
		}

		if ( $offers_count > 0 ) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html__( 'Offer Titles', 'upsellbay' ) . '</th>';
			echo '<td>';
			echo '<label><input type="checkbox" name="append_text" value="1"> ' . esc_html__( 'Append "(Import)" to offer titles', 'upsellbay' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'Useful for identifying imported offers easily.', 'upsellbay' ) . '</p>';
			echo '</td>';
			echo '</tr>';

			echo '<tr>';
			echo '<th scope="row">' . esc_html__( 'Duplicates', 'upsellbay' ) . '</th>';
			echo '<td>';
			echo '<select name="duplicate_handling">';
			echo '<option value="duplicate">' . esc_html__( 'Create duplicate offers', 'upsellbay' ) . '</option>';
			echo '<option value="skip">' . esc_html__( 'Skip importing offers that already exist', 'upsellbay' ) . '</option>';
			echo '<option value="overwrite">' . esc_html__( 'Overwrite existing offers with the same title', 'upsellbay' ) . '</option>';
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'How to handle imported offers if an offer with the exact same title already exists on this store.', 'upsellbay' ) . '</p>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Confirm Import', 'upsellbay' ) . '</button>';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=upsellbay&tab=tools' ) ) . '" class="button button-secondary">' . esc_html__( 'Cancel', 'upsellbay' ) . '</a>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}
}
