<?php
/**
 * Keeps Pro's placement settings alive in the option shared with the free host.
 *
 * @package UpsellBayPro\Integrations
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Settings;

/**
 * Both plugins read and write `upsellbay_settings`, and the host rebuilds the
 * `placements` and `placement_max_display` sub-arrays from its own defaults on
 * every save. Pro registers its three placement keys through the host's
 * `upsellbay_settings_defaults` filter so the host stops pruning them.
 *
 * Registering the keys is not enough on its own: the host's Basic settings
 * form renders a checkbox for the checkout bump only, and its save sets every
 * known placement that was not posted to `false`. So a host-started write may
 * never change Pro's placement values; they are carried over from the stored
 * option. Writes Pro starts itself (import, reset, its own settings service)
 * pass through untouched.
 *
 * @since 1.0.0
 */
final class HostSettingsIntegration {
	/**
	 * Placement keys Pro owns in the shared settings option.
	 *
	 * @var array<int, string>
	 */
	private const PRO_PLACEMENTS = array( 'product_upsell', 'cart_crosssell', 'thankyou_offer' );

	/**
	 * Pro settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings|null $settings Pro settings service.
	 */
	public function __construct( ?Settings $settings = null ) {
		$this->settings = $settings ?? new Settings();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter( 'upsellbay_settings_defaults', array( $this, 'add_settings_defaults' ) );
		add_filter( 'pre_update_option_' . Constants::SETTINGS_OPTION, array( $this, 'preserve_pro_placements' ), 10, 2 );
	}

	/**
	 * Add Pro's placement keys to the host's settings defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $defaults Host settings defaults.
	 * @return mixed
	 */
	public function add_settings_defaults( $defaults ) {
		if ( ! is_array( $defaults ) ) {
			return $defaults;
		}

		$pro = $this->settings->defaults();

		foreach ( array( 'placements', 'placement_max_display' ) as $group ) {
			$host_group = is_array( $defaults[ $group ] ?? null ) ? $defaults[ $group ] : array();

			foreach ( self::PRO_PLACEMENTS as $key ) {
				if ( ! array_key_exists( $key, $host_group ) && array_key_exists( $key, $pro[ $group ] ) ) {
					$host_group[ $key ] = $pro[ $group ][ $key ];
				}
			}

			$defaults[ $group ] = $host_group;
		}

		return $defaults;
	}

	/**
	 * Carry Pro's stored placement values over a host-started settings write.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     New option value.
	 * @param mixed $old_value Stored option value.
	 * @return mixed
	 */
	public function preserve_pro_placements( $value, $old_value ) {
		if ( Settings::is_own_write() || ! is_array( $value ) || ! is_array( $old_value ) ) {
			return $value;
		}

		foreach ( array( 'placements', 'placement_max_display' ) as $group ) {
			$stored = is_array( $old_value[ $group ] ?? null ) ? $old_value[ $group ] : array();

			foreach ( self::PRO_PLACEMENTS as $key ) {
				if ( ! array_key_exists( $key, $stored ) ) {
					continue;
				}

				if ( ! is_array( $value[ $group ] ?? null ) ) {
					$value[ $group ] = array();
				}

				$value[ $group ][ $key ] = $stored[ $key ];
			}
		}

		return $value;
	}
}
