<?php
/**
 * Basic settings section.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

/**
 * Normalizes plugin enablement, placements, and test mode.
 *
 * @since 1.0.0
 */
final class BasicSection extends AbstractSettingsSection {
	/**
	 * Section identifier.
	 *
	 * @since 1.0.0
	 */
	public function id(): string {
		return 'basic';
	}

	/**
	 * Section label.
	 *
	 * @since 1.0.0
	 */
	public function label(): string {
		return __( 'Basic', 'upsellbay' );
	}

	/**
	 * Apply submitted basic settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request  Request data.
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<string, mixed>
	 */
	public function apply( array $request, array $settings ): array {
		$settings['enabled']   = $this->bool_value( $request['enabled'] ?? false );
		$settings['test_mode'] = $this->bool_value( $request['test_mode'] ?? false );
		$submitted             = is_array( $request['placements'] ?? null ) ? $request['placements'] : array();

		foreach ( array_keys( $settings['placements'] ?? array() ) as $placement ) {
			$settings['placements'][ $placement ] = $this->bool_value( $submitted[ $placement ] ?? false );
		}

		$submitted_max_display = is_array( $request['placement_max_display'] ?? null ) ? $request['placement_max_display'] : array();
		foreach ( array_keys( $settings['placement_max_display'] ?? array() ) as $placement ) {
			if ( isset( $submitted_max_display[ $placement ] ) ) {
				$settings['placement_max_display'][ $placement ] = max( 1, (int) $submitted_max_display[ $placement ] );
			}
		}

		return $settings;
	}
}
