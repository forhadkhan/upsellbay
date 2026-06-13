<?php
/**
 * Unified UpsellBay admin page.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Admin\Navigation\TabNavigation;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabRegistry;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabRouter;

/**
 * Renders the single WooCommerce submenu page with internal tabs.
 *
 * @since 1.0.0
 */
final class AdminPage {
	/**
	 * Tab registry.
	 *
	 * @since 1.0.0
	 *
	 * @var TabRegistry
	 */
	private TabRegistry $registry;

	/**
	 * Tab router.
	 *
	 * @since 1.0.0
	 *
	 * @var TabRouter
	 */
	private TabRouter $router;

	/**
	 * Tab navigation renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var TabNavigation
	 */
	private TabNavigation $navigation;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param TabRegistry        $registry   Tab registry.
	 * @param TabRouter          $router     Tab router.
	 * @param TabNavigation|null $navigation Tab navigation renderer.
	 */
	public function __construct( TabRegistry $registry, TabRouter $router, ?TabNavigation $navigation = null ) {
		$this->registry   = $registry;
		$this->router     = $router;
		$this->navigation = $navigation ?? new TabNavigation();
	}

	/**
	 * Render the admin shell.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request Request context or WordPress hook argument.
	 */
	public function render( $request = null ): void {
		$request    = is_array( $request ) ? $request : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = $this->router->current_tab( $request );
		$active_tab->prepare( $request );

		echo '<div class="wrap woocommerce upsellbay-admin">';
		echo '<h1>' . esc_html__( 'UpsellBay', 'upsellbay' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		/**
		 * Fires below the UpsellBay page title and above the tab navigation.
		 *
		 * @since 1.0.0
		 */
		do_action( 'upsellbay_admin_page_heading_before' );
		$this->navigation->render( $this->registry, $active_tab );
		echo '<div class="upsellbay-tab-content">';
		$active_tab->render( $request );
		echo '</div></div>';
	}
}
