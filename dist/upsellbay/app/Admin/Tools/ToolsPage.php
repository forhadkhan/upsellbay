<?php
/**
 * Tools and diagnostics admin page.
 *
 * @package UpsellBay\Admin\Tools
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Tools;

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;

/**
 * Provides import/export and safe diagnostic helpers.
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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param ImportExporter $import_exporter Import/export helper.
	 * @param Settings       $settings        Settings service.
	 */
	public function __construct( ImportExporter $import_exporter, Settings $settings ) {
		$this->import_exporter = $import_exporter;
		$this->settings        = $settings;
	}

	/**
	 * Return masked system diagnostics.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function diagnostics(): array {
		$settings = $this->settings->all();
		$license  = is_array( $settings['license'] ?? null ) ? $settings['license'] : array();

		return array(
			'plugin=' . Constants::PLUGIN_SLUG,
			'version=' . Constants::VERSION,
			'license_status=' . (string) ( $license['status'] ?? 'unknown' ),
			'license_masked=' . (string) ( $license['masked_key'] ?? '' ),
			'test_mode=' . ( true === ( $settings['test_mode'] ?? false ) ? 'yes' : 'no' ),
		);
	}

	/**
	 * Validate import JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json Import JSON.
	 */
	public function validate_import( string $json ): bool {
		return $this->import_exporter->validate_json( $json )->is_valid();
	}

	/**
	 * Return import empty-state guidance.
	 *
	 * @since 1.0.0
	 *
	 * @return array{title: string, message: string, actions: array<int, array{label: string, url: string}>}
	 */
	public function import_empty_state(): array {
		return array(
			'title'   => __( 'Import offers when you have a JSON export', 'upsellbay' ),
			'message' => __( 'Agencies can move reviewed offer definitions between stores without copying site-specific product IDs blindly.', 'upsellbay' ),
			'actions' => array(
				array(
					'label' => __( 'Import offers', 'upsellbay' ),
					'url'   => 'admin.php?page=upsellbay&tab=tools#import',
				),
			),
		);
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
	 */
	public function render_content(): void {
		echo '<h2>' . esc_html__( 'System diagnostics', 'upsellbay' ) . '</h2>';
		echo '<table class="widefat striped upsellbay-diagnostics-table"><tbody>';
		foreach ( $this->diagnostics() as $line ) {
			$parts = explode( '=', $line, 2 );
			echo '<tr><th scope="row">' . esc_html( $parts[0] ) . '</th><td><code>' . esc_html( $parts[1] ?? '' ) . '</code></td></tr>';
		}
		echo '</tbody></table>';

		$empty = $this->import_empty_state();
		echo '<h2 id="import">' . esc_html__( 'Import offers', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html( $empty['message'] ) . '</p>';
		echo '<form method="post"><textarea name="upsellbay_import_json" class="large-text code" rows="8" aria-label="' . esc_attr( __( 'Offer import JSON', 'upsellbay' ) ) . '"></textarea>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Validate import', 'upsellbay' ) . '</button></p></form>';
	}
}
