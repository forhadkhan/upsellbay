<?php
/**
 * Admin bar test mode indicator.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Core\Settings;

/**
 * Shows an admin-only test mode indicator.
 *
 * @since 1.0.0
 */
final class AdminBar {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Capability callback.
	 *
	 * @var callable(): bool
	 */
	private $can_manage;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings      $settings   Settings service.
	 * @param callable|null $can_manage Capability callback.
	 */
	public function __construct( Settings $settings, ?callable $can_manage = null ) {
		$this->settings   = $settings;
		$this->can_manage = $can_manage ?? static fn (): bool => function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}

	/**
	 * Register admin bar hook.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_bar_menu', array( $this, 'add_node' ), 90 );
		}
	}

	/**
	 * Determine whether the indicator should render.
	 *
	 * @since 1.0.0
	 */
	public function should_show_indicator(): bool {
		$settings = $this->settings->all();
		return (bool) $settings['test_mode'] && ( $this->can_manage )();
	}

	/**
	 * Add admin bar node.
	 *
	 * @since 1.0.0
	 *
	 * @param object $admin_bar WP admin bar.
	 */
	public function add_node( object $admin_bar ): void {
		if ( ! $this->should_show_indicator() || ! method_exists( $admin_bar, 'add_node' ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'upsellbay-test-mode',
				'title' => __( 'UpsellBay test mode', 'upsellbay' ),
				'href'  => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=upsellbay-settings' ) : '',
			)
		);
	}
}
