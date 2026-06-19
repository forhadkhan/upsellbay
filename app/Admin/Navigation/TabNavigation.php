<?php
/**
 * Admin tab navigation renderer.
 *
 * @package UpsellBay\Admin\Navigation
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Navigation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Renders WooCommerce-style internal admin tabs.
 *
 * @since 1.0.0
 */
final class TabNavigation {
	/**
	 * Render navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param TabRegistry $registry   Tab registry.
	 * @param AdminTab    $active_tab Active tab.
	 */
	public function render( TabRegistry $registry, AdminTab $active_tab ): void {
		echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper upsellbay-tabs" aria-label="' . esc_attr( __( 'UpsellBay sections', 'upsellbay' ) ) . '">';

		foreach ( $registry->tabs() as $tab ) {
			if ( ! $tab->show_in_nav() ) {
				continue;
			}

			$classes = 'nav-tab';
			if ( $tab->id() === $active_tab->id() ) {
				$classes .= ' nav-tab-active';
			}

			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( 'admin.php?page=upsellbay&tab=' . $tab->id() ) . '">' . esc_html( $tab->label() ) . '</a>';
		}

		echo '</nav>';
	}
}
