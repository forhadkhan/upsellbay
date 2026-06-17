<?php
/**
 * Data settings section.
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
 * Normalizes retention and cleanup settings.
 *
 * @since 1.0.0
 */
final class DataSection extends AbstractSettingsSection {
	/**
	 * Section identifier.
	 *
	 * @since 1.0.0
	 */
	public function id(): string {
		return 'data';
	}

	/**
	 * Section label.
	 *
	 * @since 1.0.0
	 */
	public function label(): string {
		return __( 'Data', 'upsellbay' );
	}

	/**
	 * Apply submitted data settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request  Request data.
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<string, mixed>
	 */
	public function apply( array $request, array $settings ): array {
		$retention = is_array( $settings['data_retention'] ?? null ) ? $settings['data_retention'] : array();

		$retention['stats_days']              = $this->days_value( $request['stats_days'] ?? $retention['stats_days'] ?? 365, 365 );
		$retention['session_days']            = $this->days_value( $request['session_days'] ?? $retention['session_days'] ?? 30, 30 );
		$retention['log_days']                = $this->days_value( $request['log_days'] ?? $retention['log_days'] ?? 30, 30 );
		$retention['prune_order_attribution'] = false;

		$settings['data_retention']    = $retention;
		$settings['cleanup_on_delete'] = $this->bool_value( $request['cleanup_on_delete'] ?? false );

		return $settings;
	}
}
