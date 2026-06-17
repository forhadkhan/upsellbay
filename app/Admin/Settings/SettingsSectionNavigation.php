<?php
/**
 * Settings section navigation.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Renders WooCommerce-style section links inside the Settings tab.
 *
 * @since 1.0.0
 */
final class SettingsSectionNavigation {
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

		echo '<ul class="subsubsub upsellbay-settings-section-menu" aria-label="' . esc_attr__( 'Settings sections', 'upsellbay' ) . '">';
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
	 * Return Settings tab section links.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	private function sections(): array {
		return array(
			'general' => array(
				'label' => __( 'General', 'upsellbay' ),
				'url'   => 'admin.php?page=upsellbay&tab=settings',
			),
			'data'    => array(
				'label' => __( 'Data', 'upsellbay' ),
				'url'   => 'admin.php?page=upsellbay&tab=settings&section=data',
			),
			'license' => array(
				'label' => __( 'License', 'upsellbay' ),
				'url'   => 'admin.php?page=upsellbay&tab=settings&section=license',
			),
			'logs'    => array(
				'label' => __( 'Logs', 'upsellbay' ),
				'url'   => 'admin.php?page=upsellbay&tab=settings&section=logs',
			),
		);
	}
}
