<?php
/**
 * Base settings section helpers.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

/**
 * Provides shared sanitization helpers for settings sections.
 *
 * @since 1.0.0
 */
abstract class AbstractSettingsSection implements SettingsSectionInterface {
	/**
	 * Cast checkbox-like input to bool.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 */
	protected function bool_value( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true );
	}

	/**
	 * Sanitize positive days value with fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $fallback Fallback days.
	 */
	protected function days_value( $value, int $fallback ): int {
		$days = (int) $value;
		return $days > 0 ? $days : $fallback;
	}

	/**
	 * Sanitize a CSS hex color.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value    Raw color.
	 * @param string $fallback Fallback color.
	 */
	protected function color_value( string $value, string $fallback ): string {
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$color = sanitize_hex_color( $value );
			return '' !== $color && null !== $color ? $color : $fallback;
		}

		return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : $fallback;
	}
}
