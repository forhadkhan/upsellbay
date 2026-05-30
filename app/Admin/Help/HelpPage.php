<?php
/**
 * Help contextual tabs.
 *
 * @package UpsellBay\Admin\Help
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Help;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Registers WordPress contextual help tabs on UpsellBay admin screens.
 *
 * @since 1.0.0
 */
final class HelpPage {

	/**
	 * Register contextual help tabs and sidebar on the UpsellBay admin page.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'woocommerce_page_upsellbay' !== $screen->id ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'upsellbay-getting-started',
				'title'   => __( 'Getting Started', 'upsellbay' ),
				'content' => $this->getting_started_content(),
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'upsellbay-documentation',
				'title'   => __( 'Documentation', 'upsellbay' ),
				'content' => $this->documentation_content(),
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'upsellbay-support',
				'title'   => __( 'Support', 'upsellbay' ),
				'content' => $this->support_content(),
			)
		);

		$screen->set_help_sidebar( $this->sidebar_content() );
	}

	/**
	 * Return the Getting Started help tab markup.
	 *
	 * @since 1.0.0
	 */
	private function getting_started_content(): string {
		$empty = $this->empty_state();
		$html  = '<p>' . esc_html( $empty['message'] ) . '</p>';
		$html .= '<p><a href="' . esc_url( $empty['actions'][0]['url'] ) . '" class="button">' . esc_html( $empty['actions'][0]['label'] ) . '</a></p>';

		return $html;
	}

	/**
	 * Return the Documentation help tab markup.
	 *
	 * @since 1.0.0
	 */
	private function documentation_content(): string {
		$html = '<ul>';
		foreach ( $this->links() as $link ) {
			if ( str_contains( $link['url'], 'support' ) ) {
				continue;
			}
			$html .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Return the Support help tab markup.
	 *
	 * @since 1.0.0
	 */
	private function support_content(): string {
		$links = $this->links();

		$link = end( $links );

		$html  = '<p>' . esc_html__( 'Need help with UpsellBay?', 'upsellbay' ) . '</p>';
		$html .= '<p><a href="' . esc_url( $link['url'] ) . '" class="button">' . esc_html( $link['label'] ) . '</a></p>';

		return $html;
	}

	/**
	 * Return the help sidebar markup.
	 *
	 * @since 1.0.0
	 */
	private function sidebar_content(): string {
		$html  = '<p><strong>' . esc_html__( 'Quick links:', 'upsellbay' ) . '</strong></p>';
		$html .= '<ul>';
		foreach ( $this->links() as $link ) {
			$html .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></li>';
		}
		$html .= '</ul>';

		return $html;
	}

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
}
