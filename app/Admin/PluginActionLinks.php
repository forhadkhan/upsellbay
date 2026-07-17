<?php
/**
 * Plugin row action links on the Installed Plugins screen.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Core\Constants;

/**
 * Adds a Docs quick link to the Pro plugin's row on the plugins list.
 *
 * The Manage link lives in the free host plugin (upsellbay-order-bump-for-woocommerce).
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
	 * Add Docs link beside the default plugin actions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $actions Existing action links.
	 * @return array<string, string>
	 */
	public function add_action_links( array $actions ): array {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			return $actions;
		}

		$links = array(
			'docs' => sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( Constants::DOCS_URL ),
				esc_html__( 'Docs', 'upsellbay' )
			),
		);

		return array_merge( $links, $actions );
	}
}
