<?php
/**
 * Unified UpsellBay admin page.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Admin\Navigation\AdminTab;
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

		add_filter( 'admin_title', array( $this, 'filter_admin_title' ), 10, 2 );
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
		echo '<h1>' . esc_html( $this->page_heading( $active_tab, $request ) ) . '</h1>';
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

	/**
	 * Generate the page heading for the current tab.
	 *
	 * @since 1.0.0
	 *
	 * @param AdminTab             $active_tab Active tab.
	 * @param array<string, mixed> $request    Request data.
	 * @return string
	 */
	private function page_heading( AdminTab $active_tab, array $request ): string {
		$base   = __( 'UpsellBay', 'upsellbay' );
		$tab_id = $active_tab->id();

		if ( 'dashboard' === $tab_id ) {
			return $base;
		}

		if ( 'offers' === $tab_id && 'edit' === sanitize_key( (string) ( $request['action'] ?? '' ) ) ) {
			return $base . ' › ' . __( 'Add Offer', 'upsellbay' );
		}

		return $base . ' › ' . $active_tab->label();
	}

	/**
	 * Filter the admin page title to include the current tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string $admin_title Full admin title.
	 * @param string $title       Page-specific title portion.
	 * @return string
	 */
	public function filter_admin_title( string $admin_title, string $title ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen ) {
			return $admin_title;
		}

		if ( ! str_starts_with( $screen->id, 'woocommerce_page_upsellbay' ) ) {
			return $admin_title;
		}

		$request    = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = $this->router->current_tab( $request );
		$new_title  = $this->page_heading( $active_tab, $request );

		if ( str_starts_with( $admin_title, $title ) ) {
			return $new_title . substr( $admin_title, strlen( $title ) );
		}

		return $admin_title;
	}
}
