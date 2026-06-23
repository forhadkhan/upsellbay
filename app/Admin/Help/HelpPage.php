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
		echo '<h2>' . esc_html__( 'Help', 'upsellbay' ) . '</h2>';
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
		echo '<table class="widefat striped upsellbay-help-table" style="margin-top: 15px;"><tbody>';
		foreach ( $this->links() as $link ) {
			echo '<tr><td><a href="' . esc_url( $link['url'] ) . '" target="_blank">' . esc_html( $link['label'] ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';

		// Support Card
		echo '<div class="upsellbay-card" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Contact Support', 'upsellbay' ) . '</h2>';
		echo '<p>' . esc_html__( 'Need help with UpsellBay? Our support team is here to assist you.', 'upsellbay' ) . '</p>';
		echo '<p><a href="' . esc_url( Constants::SUPPORT_URL ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Visit Support Portal', 'upsellbay' ) . '</a></p>';
		echo '<p style="margin-top: 15px;"><strong>' . esc_html__( 'Or email us directly:', 'upsellbay' ) . '</strong> <a href="mailto:support@wpanchorbay.com">support@wpanchorbay.com</a></p>';
		echo '</div>';

		echo '</div>';
	}
}
