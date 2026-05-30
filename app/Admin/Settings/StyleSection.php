<?php
/**
 * Style settings section.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

/**
 * Normalizes theme-friendly presentation settings.
 *
 * @since 1.0.0
 */
final class StyleSection extends AbstractSettingsSection {
	/**
	 * Section identifier.
	 *
	 * @since 1.0.0
	 */
	public function id(): string {
		return 'style';
	}

	/**
	 * Section label.
	 *
	 * @since 1.0.0
	 */
	public function label(): string {
		return __( 'Style', 'upsellbay' );
	}

	/**
	 * Apply submitted style settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request  Request data.
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<string, mixed>
	 */
	public function apply( array $request, array $settings ): array {
		$tokens = is_array( $settings['style_tokens'] ?? null ) ? $settings['style_tokens'] : array();

		$tokens['accent_color'] = $this->color_value( (string) ( $request['accent_color'] ?? '#2271b1' ), '#2271b1' );
		$tokens['button_style'] = in_array( $request['button_style'] ?? 'theme', array( 'theme', 'outline' ), true ) ? (string) $request['button_style'] : 'theme';

		$settings['style_tokens'] = $tokens;
		return $settings;
	}
}
