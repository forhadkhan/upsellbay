<?php
/**
 * Admin settings section contract.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

/**
 * Defines one Woo-style settings section.
 *
 * @since 1.0.0
 */
interface SettingsSectionInterface {
	/**
	 * Section identifier.
	 *
	 * @since 1.0.0
	 */
	public function id(): string;

	/**
	 * Section label.
	 *
	 * @since 1.0.0
	 */
	public function label(): string;

	/**
	 * Normalize submitted values into the settings shape.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request  Request data.
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<string, mixed>
	 */
	public function apply( array $request, array $settings ): array;
}
