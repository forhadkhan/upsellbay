<?php
/**
 * General settings section.
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
final class GeneralSection extends AbstractSettingsSection {
	/**
	 * Section identifier.
	 *
	 * @since 1.0.0
	 */
	public function id(): string {
		return 'general';
	}

	/**
	 * Section label.
	 *
	 * @since 1.0.0
	 */
	public function label(): string {
		return __( 'General', 'upsellbay' );
	}

	/**
	 * Apply submitted general settings.
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

		return $settings;
	}
}
