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

		$this->render_header();
		echo '<div class="wrap woocommerce upsellbay-admin">';
		/**
		 * Fires below the UpsellBay page header and above the tab navigation.
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
	 * Render the WooCommerce-style page header.
	 *
	 * @since 1.0.0
	 */
	private function render_header(): void {
		echo '<div class="upsellbay-layout-header woocommerce-layout__header">';
		echo '<div class="upsellbay-layout-header__wrapper woocommerce-layout__header-wrapper">';
		echo '<h1 class="upsellbay-layout-header__heading woocommerce-layout__header-heading">' . esc_html__( 'UpsellBay', 'upsellbay' ) . '</h1>';
		echo '<div class="upsellbay-layout-header__actions">';
		echo '<a class="button button-primary" href="' . esc_url( 'admin.php?page=upsellbay&tab=offers&action=edit' ) . '">' . esc_html__( 'Add offer', 'upsellbay' ) . '</a>';
		echo '<a class="button" href="' . esc_url( 'admin.php?page=upsellbay&tab=settings' ) . '">' . esc_html__( 'Settings', 'upsellbay' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
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

		if ( 'offers' === $tab_id && 'edit' === $this->request_key( $request['action'] ?? '' ) ) {
			return $base . ' › ' . __( 'Add Offer', 'upsellbay' );
		}

		return $base . ' › ' . $active_tab->label();
	}

	/**
	 * Sanitize an admin request key with a fallback for isolated tests.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 */
	private function request_key( $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( (string) $value );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ?? '' );
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
