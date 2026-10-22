<?php
/**
 * Tools admin page.
 *
 * @package UpsellBay\Admin\Tools
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Admin\Tools;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBay\Admin\Navigation\AdminTab as HostAdminTab;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBayPro\Utils\ImportExporter;

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
	 * @phpstan-ignore property.onlyWritten
	 */
	private ImportExporter $import_exporter;

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 * @phpstan-ignore property.onlyWritten
	 */
	private Settings $settings;

	/**
	 * Compatibility scanner.
	 *
	 * @since 1.0.0
	 *
	 * @var CompatibilityScanner
	 * @phpstan-ignore property.onlyWritten
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
	 * Attach a Tools tab to the host plugin's admin page.
	 *
	 * The host removed its own Tools tab (and the `upsellbay_tools_tab_content`
	 * action with it), so Pro contributes a whole tab through the host's
	 * `upsellbay_admin_tabs` registry instead.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter( 'upsellbay_admin_tabs', array( $this, 'add_host_tab' ) );
	}

	/**
	 * Add the Tools tab to the host's tab list, before Help when present.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $tabs Host tab list (`WPAnchorBay\UpsellBay\Admin\Navigation\AdminTab` objects).
	 * @return mixed
	 */
	public function add_host_tab( $tabs ) {
		if ( ! is_array( $tabs ) || ! class_exists( HostAdminTab::class ) ) {
			return $tabs;
		}

		$help_index = null;
		foreach ( array_values( $tabs ) as $index => $tab ) {
			$id = is_object( $tab ) && method_exists( $tab, 'id' ) ? (string) $tab->id() : '';
			if ( 'tools' === $id ) {
				return $tabs;
			}

			if ( 'help' === $id ) {
				$help_index = $index;
			}
		}

		$tools = new HostAdminTab(
			'tools',
			__( 'Tools', 'upsellbay' ),
			function ( array $request ): void {
				$this->render_content( $request );
			}
		);

		$tabs = array_values( $tabs );
		array_splice( $tabs, $help_index ?? count( $tabs ), 0, array( $tools ) );

		return $tabs;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview_hash = isset( $_GET['preview_import'] ) ? sanitize_key( wp_unslash( $_GET['preview_import'] ) ) : '';

		if ( '' !== $preview_hash ) {
			$this->render_preview( $preview_hash );
			return;
		}

		echo '<div class="upsellbay-tools-container" style="display: flex; gap: 20px; flex-wrap: wrap;">';

		// Export Data Card.
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Export Data', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download all UpsellBay data including your active/paused offers and global settings. This JSON file can be used as a backup or to migrate your setup to another store.', 'upsellbay' ) . '</p>';

		$export_url = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $export_url ) . '">';
		wp_nonce_field( 'upsellbay_pro_export_data' );
		echo '<input type="hidden" name="action" value="upsellbay_pro_export_data">';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Export Whole Data', 'upsellbay' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		// Import Data Card.
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 id="import" style="margin-top:0;">' . esc_html__( 'Import Data', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Upload a previously exported JSON file. This will restore all your settings and append the imported offers to your store.', 'upsellbay' ) . '</p>';

		$import_url = admin_url( 'admin-post.php' );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $import_url ) . '">';
		wp_nonce_field( 'upsellbay_pro_import_data' );
		echo '<input type="hidden" name="action" value="upsellbay_pro_import_data">';
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
		if ( false === $json || '' === $json ) {
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
		/* translators: %s: number of offers */
		$offers_text = _n( '%s Offer', '%s Offers', $offers_count, 'upsellbay' );
		echo '<li>' . esc_html( sprintf( $offers_text, number_format_i18n( $offers_count ) ) ) . '</li>';
		echo '</ul>';
		echo '</div>';

		$confirm_url = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $confirm_url ) . '">';
		wp_nonce_field( 'upsellbay_pro_confirm_import' );
		echo '<input type="hidden" name="action" value="upsellbay_pro_confirm_import">';
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
			echo '<option value="skip">' . esc_html__( 'Skip importing offers that already exist', 'upsellbay' ) . '</option>';
			echo '<option value="duplicate">' . esc_html__( 'Create duplicate offers', 'upsellbay' ) . '</option>';
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
