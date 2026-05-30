<?php
/**
 * Compatibility notices.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;

/**
 * Builds dismissible compatibility and coexistence notices.
 *
 * @since 1.0.0
 */
final class CompatibilityNotice {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Coexistence detector.
	 *
	 * @var Coexistence
	 */
	private Coexistence $coexistence;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings    $settings    Settings service.
	 * @param Coexistence $coexistence Coexistence detector.
	 */
	public function __construct( Settings $settings, Coexistence $coexistence ) {
		$this->settings    = $settings;
		$this->coexistence = $coexistence;
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
		$settings   = $this->settings->all();
		$dismissals = is_array( $settings['notice_dismissals'] ?? null ) ? $settings['notice_dismissals'] : array();
		$notices    = array();

		if ( $this->coexistence->is_cartbay_active() && true !== ( $dismissals['cartbay_coexistence'] ?? false ) ) {
			$notices[] = array(
				'id'      => 'cartbay_coexistence',
				'type'    => 'info',
				'message' => __( 'CartBay is active. UpsellBay remains a separate AOV offer engine and does not read recovery sessions, recovery coupons, or email sequence data.', 'upsellbay' ),
				'url'     => Constants::DOCS_URL . 'compatibility/',
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
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] ),
				esc_url( $notice['url'] ),
				esc_html__( 'Compatibility guide', 'upsellbay' )
			);
		}
	}
}
