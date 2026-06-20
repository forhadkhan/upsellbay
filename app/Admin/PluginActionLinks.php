<?php
/**
 * Plugin row action links on the Installed Plugins screen.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Adds quick links for managing UpsellBay from the plugins list.
 *
 * @since 1.0.0
 */
final class PluginActionLinks {
	/**
	 * Register plugin row action hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter(
			'plugin_action_links_' . Constants::plugin_basename(),
			array( $this, 'add_action_links' )
		);
	}

	/**
	 * Add Manage and Docs links beside the default plugin actions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $actions Existing action links.
	 * @return array<string, string>
	 */
	public function add_action_links( array $actions ): array {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}

		$links = array(
			'manage' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->dashboard_url() ),
				esc_html__( 'Manage', 'upsellbay' )
			),
			'docs'   => sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( Constants::DOCS_URL ),
				esc_html__( 'Docs', 'upsellbay' )
			),
		);

		return array_merge( $links, $actions );
	}

	/**
	 * Build the internal admin dashboard URL.
	 *
	 * @since 1.0.0
	 */
	private function dashboard_url(): string {
		if ( function_exists( 'admin_url' ) ) {
			return admin_url( 'admin.php?page=' . Constants::PLUGIN_SLUG );
		}

		return 'admin.php?page=' . Constants::PLUGIN_SLUG;
	}
}
