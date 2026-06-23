<?php
/**
 * Help admin page.
 *
 * @package UpsellBay\Admin\Help
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Help;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Routes merchants to UpsellBay AOV offer docs and support.
 *
 * @since 1.0.0
 */
final class HelpPage {
	/**
	 * Return help links.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{label: string, url: string}>
	 */
	public function links(): array {
		return array(
			array(
				'label' => __( 'AOV offer setup guide', 'upsellbay' ),
				'url'   => Constants::DOCS_URL . 'setup/',
			),
			array(
				'label' => __( 'First offer tutorial', 'upsellbay' ),
				'url'   => Constants::DOCS_URL . 'first-offer/',
			),
			array(
				'label' => __( 'Compatibility matrix', 'upsellbay' ),
				'url'   => Constants::DOCS_URL . 'compatibility/',
			),
			array(
				'label' => __( 'Data retention guide', 'upsellbay' ),
				'url'   => Constants::DOCS_URL . 'data-retention/',
			),
			array(
				'label' => __( 'Developer docs', 'upsellbay' ),
				'url'   => Constants::DOCS_URL . 'developer/',
			),
			array(
				'label' => __( 'UpsellBay AOV offer support', 'upsellbay' ),
				'url'   => Constants::SUPPORT_URL,
			),
		);
	}

	/**
	 * Return Help page empty-state action for new stores.
	 *
	 * @since 1.0.0
	 *
	 * @return array{title: string, message: string, actions: array<int, array{label: string, url: string}>}
	 */
	public function empty_state(): array {
		return array(
			'title'   => __( 'Need a starting point?', 'upsellbay' ),
			'message' => __( 'Use the first offer tutorial to create a draft offer, preview it in test mode, and publish only when the checkout looks right.', 'upsellbay' ),
			'actions' => array(
				array(
					'label' => __( 'First offer tutorial', 'upsellbay' ),
					'url'   => Constants::DOCS_URL . 'first-offer/',
				),
			),
		);
	}

	/**
	 * Render help shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		echo '<div class="wrap woocommerce upsellbay-admin">';
		
		$logo_path = dirname( Constants::plugin_file() ) . '/assets/admin/upsellbay-logo.svg';
		echo '<div style="margin-bottom: 20px; margin-top: 10px;">';
		echo '<a href="' . esc_url( Constants::PLUGIN_URL ) . '" target="_blank" style="display:inline-block; max-width: 200px;">';
		if ( file_exists( $logo_path ) ) {
			$svg = file_get_contents( $logo_path );
			// Remove XML declaration if it exists so it embeds cleanly.
			$svg = preg_replace( '/^<\?xml[^>]+>\s*/i', '', $svg );
			// Enforce max width directly on the SVG element for safety
			$svg = str_replace( '<svg ', '<svg style="max-width: 100%; height: auto; display: block;" ', $svg );
			echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<strong>UpsellBay</strong>';
		}
		echo '</a>';
		echo '</div>';

		echo '<h2 style="display:none;">' . esc_html__( 'Help', 'upsellbay' ) . '</h2>'; // Kept for screen readers/admin notice positioning
		$this->render_content();
		echo '</div>';
	}

	/**
	 * Render help tab content.
	 *
	 * @since 1.0.0
	 */
	public function render_content(): void {
		$empty = $this->empty_state();

		echo '<div class="upsellbay-tools-container" style="display: flex; gap: 20px; flex-wrap: wrap;">';

		// Documentation Card
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Documentation', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html( $empty['message'] ) . '</p>';
		echo '<p><a href="' . esc_url( Constants::DOCS_URL ) . '" target="_blank" class="button button-primary">' . esc_html__( 'View Full Documentation', 'upsellbay' ) . '</a></p>';
		echo '</div>';

		// Support Card
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Contact Support', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Need help with UpsellBay? Our support team is here to assist you.', 'upsellbay' ) . '</p>';
		echo '<p><a href="' . esc_url( Constants::SUPPORT_URL ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Visit Support Portal', 'upsellbay' ) . '</a></p>';
		echo '<p style="margin-top: 15px;"><strong>' . esc_html__( 'Or email us directly:', 'upsellbay' ) . '</strong> <a href="mailto:support@wpanchorbay.com">support@wpanchorbay.com</a></p>';
		echo '</div>';

		// Logs & Diagnostics Card
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Logs & Diagnostics', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Access system logs to troubleshoot issues or review plugin activity.', 'upsellbay' ) . '</p>';
		echo '<table class="widefat striped upsellbay-help-table" style="margin-top: 15px;"><tbody>';
		echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=upsellbay&tab=settings&section=logs' ) ) . '">' . esc_html__( 'UpsellBay Logs', 'upsellbay' ) . '</a></td></tr>';
		echo '<tr><td><a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . esc_html__( 'WooCommerce Logs', 'upsellbay' ) . '</a></td></tr>';
		echo '<tr><td><a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'WordPress Site Health & Logs', 'upsellbay' ) . '</a></td></tr>';
		echo '</tbody></table>';
		echo '</div>';

		echo '</div>';
	}
}
