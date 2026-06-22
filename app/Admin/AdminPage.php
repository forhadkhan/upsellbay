<?php
/**
 * Unified UpsellBay admin page.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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
		add_action( 'in_admin_header', array( $this, 'remove_third_party_notices' ), 1 );
	}

	/**
	 * Remove third-party admin notices on UpsellBay pages.
	 *
	 * @since 1.0.0
	 */
	public function remove_third_party_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null !== $screen && str_starts_with( $screen->id, 'woocommerce_page_upsellbay' ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
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

		echo '<div class="upsellbay-license-banner-slot">';
		do_action( 'upsellbay_admin_page_license_banner' );
		echo '</div>';

		$this->render_header( $active_tab );

		echo '<div class="wrap woocommerce upsellbay-admin">';
		echo '<div class="upsellbay-tab-content">';

		echo '<h1 class="screen-reader-text">' . esc_html( $this->page_heading( $active_tab, $request ) ) . '</h1>';
		echo '<div id="lost-connection-notice" class="notice error hidden"></div>';

		/**
		 * Fires at the top of the active tab, before the attached page header,
		 * tab navigation, and tab-local section navigation.
		 *
		 * @since 1.0.0
		 */
		echo '<div class="upsellbay-page-notices">';
		do_action( 'upsellbay_admin_page_heading_before' );
		if ( ! $this->should_defer_redirect_notices( $active_tab, $request ) ) {
			$this->render_redirect_notices();
		}
		echo '</div>';

		$active_tab->render( $request );
		echo '</div></div>';

		$this->render_confirmation_modal_template();
	}

	/**
	 * Render the WooCommerce Backbone confirmation template.
	 *
	 * @since 1.0.0
	 */
	private function render_confirmation_modal_template(): void {
		echo '<script type="text/template" id="tmpl-upsellbay-confirmation-modal">';
		echo '<div class="wc-backbone-modal upsellbay-confirmation-modal">';
		echo '<div class="wc-backbone-modal-content">';
		echo '<section class="wc-backbone-modal-main" role="main">';
		echo '<header class="wc-backbone-modal-header">';
		echo '<h1>{{ data.title }}</h1>';
		echo '<button type="button" class="modal-close modal-close-link dashicons dashicons-no-alt">';
		echo '<span class="screen-reader-text">' . esc_html__( 'Close modal panel', 'upsellbay' ) . '</span>';
		echo '</button>';
		echo '</header>';
		echo '<form>';
		echo '<article><p>{{ data.message }}</p></article>';
		echo '<footer><div class="inner">';
		echo '<button type="button" class="button button-large upsellbay-confirmation-cancel">{{ data.cancel }}</button>';
		echo '<button type="button" class="button button-large button-primary upsellbay-button-danger upsellbay-confirmation-confirm" data-url="{{ data.url }}">{{ data.confirm }}</button>';
		echo '</div></footer>';
		echo '</form>';
		echo '</section>';
		echo '</div>';
		echo '</div>';
		echo '<div class="wc-backbone-modal-backdrop modal-close"></div>';
		echo '</script>';
	}

	/**
	 * Render the WooCommerce-style page header.
	 *
	 * @since 1.0.0
	 *
	 * @param AdminTab $active_tab Active tab.
	 */
	private function render_header( AdminTab $active_tab ): void {
		echo '<div id="upsellbay-header" class="upsellbay-layout-header upsellbay-admin">';
		echo '<div class="upsellbay-layout-header__wrapper">';
		echo '<h1 class="upsellbay-layout-header__heading">' . esc_html__( 'UpsellBay', 'upsellbay' ) . '</h1>';
		echo '<div class="upsellbay-layout-header__actions">';
		echo '<a class="button button-secondary" href="' . esc_url( 'admin.php?page=upsellbay&tab=setup' ) . '">' . esc_html__( 'Get Started', 'upsellbay' ) . '</a>';
		echo '<a class="button button-primary" href="' . esc_url( 'admin.php?page=upsellbay&tab=offers&action=edit' ) . '">' . esc_html__( 'Add Offer', 'upsellbay' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="upsellbay-layout-header__tabs">';
		$this->navigation->render( $this->registry, $active_tab );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render safe redirect notices above the attached page header.
	 *
	 * @since 1.0.0
	 */
	private function render_redirect_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wc_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['wc_message'] ) );
			echo '<div class="notice notice-success upsellbay-page-notice"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( isset( $_GET['wc_error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['wc_error'] ) );
			echo '<div class="notice notice-error upsellbay-page-notice"><p>' . esc_html( $error ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Determine whether redirect notices should be rendered within the active tab content.
	 *
	 * @since 1.0.0
	 *
	 * @param AdminTab             $active_tab Active tab.
	 * @param array<string, mixed> $request    Request data.
	 * @return bool
	 */
	private function should_defer_redirect_notices( AdminTab $active_tab, array $request ): bool {
		if ( 'offers' !== $active_tab->id() ) {
			return false;
		}

		if ( 'edit' === $this->request_key( $request['action'] ?? '' ) ) {
			return true;
		}

		return isset( $request['offer_id'] ) || isset( $request['id'] );
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
