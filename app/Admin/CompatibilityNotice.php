<?php
/**
 * Compatibility notices.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Domain\Compatibility\CompatibilityScanner;

/**
 * Builds dismissible compatibility and coexistence notices.
 *
 * @since 1.0.0
 */
final class CompatibilityNotice {
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
	 * @param Settings                         $settings               Settings service, retained for constructor compatibility.
	 * @param Coexistence|CompatibilityScanner $coexistence_or_scanner Coexistence detector or compatibility scanner.
	 */
	public function __construct( Settings $settings, Coexistence|CompatibilityScanner $coexistence_or_scanner ) {
		unset( $settings );
		$this->scanner = $coexistence_or_scanner instanceof CompatibilityScanner ? $coexistence_or_scanner : new CompatibilityScanner();
	}

	/**
	 * Register notice hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( $this, 'render' ) );
			add_action( 'upsellbay_admin_page_heading_before', array( $this, 'render' ) );
		}
	}

	/**
	 * Return active notices.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>>
	 */
	public function notices(): array {
		$notices = array();

		foreach ( $this->scanner->findings() as $finding ) {
			$notices[] = array(
				'type'    => $finding['severity'] ?? 'warning',
				'message' => $finding['message'] ?? '',
				'url'     => admin_url( 'admin.php?page=upsellbay&tab=help' ),
			);
		}

		return $notices;
	}

	/**
	 * Render notices.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		foreach ( $this->notices() as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible upsellbay-notice"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] ),
				esc_url( $notice['url'] ),
				esc_html__( 'Compatibility guide', 'upsellbay' )
			);
		}
	}
}
