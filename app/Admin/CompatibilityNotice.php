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

/**
 * Builds dismissible compatibility and coexistence notices.
 *
 * @since 1.0.0
 */
final class CompatibilityNotice {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings    $settings    Settings service, retained for constructor compatibility.
	 * @param Coexistence $coexistence Coexistence detector, retained for constructor compatibility.
	 */
	public function __construct( Settings $settings, Coexistence $coexistence ) {
		unset( $settings, $coexistence );
	}

	/**
	 * Register notice hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( $this, 'render' ) );
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
		return array();
	}

	/**
	 * Render notices.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		foreach ( $this->notices() as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] ),
				esc_url( $notice['url'] ),
				esc_html__( 'Compatibility guide', 'upsellbay' )
			);
		}
	}
}
