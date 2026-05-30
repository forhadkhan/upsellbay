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
					'url'   => 'admin.php?page=upsellbay-tools#import',
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
		echo '<div class="wrap woocommerce"><h1>' . esc_html__( 'UpsellBay Tools', 'upsellbay' ) . '</h1></div>';
	}
}
