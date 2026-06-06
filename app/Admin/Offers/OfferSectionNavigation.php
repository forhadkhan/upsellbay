<?php
/**
 * Offers section navigation.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

/**
 * Renders WooCommerce-style section links inside the Offers tab.
 *
 * @since 1.0.0
 */
final class OfferSectionNavigation {
	/**
	 * Render section links.
	 *
	 * @since 1.0.0
	 *
	 * @param string $active_section Active section key.
	 */
	public function render( string $active_section ): void {
		$sections = $this->sections();
		$last_key = array_key_last( $sections );

		echo '<ul class="subsubsub upsellbay-offers-section-menu" aria-label="' . esc_attr__( 'Offer sections', 'upsellbay' ) . '">';
		foreach ( $sections as $section_id => $section ) {
			$is_active = $section_id === $active_section;
			echo '<li><a href="' . esc_url( $section['url'] ) . '"';
			if ( $is_active ) {
				echo ' class="current" aria-current="page"';
			}
			echo '>' . esc_html( $section['label'] ) . '</a>';
			if ( $section_id !== $last_key ) {
				echo ' | ';
			}
			echo '</li>';
		}
		echo '</ul><br class="clear">';
	}

	/**
	 * Return Offers tab section links.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	private function sections(): array {
		return array(
			'general'   => array(
				'label' => __( 'General', 'upsellbay' ),
				'url'   => 'admin.php?page=upsellbay&tab=offers',
			),
			'add_offer' => array(
				'label' => __( 'Add Offer', 'upsellbay' ),
				'url'   => 'admin.php?page=upsellbay&tab=offers&action=edit',
			),
		);
	}
}
