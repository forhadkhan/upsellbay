<?php
/**
 * Normalized plugin settings.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Reads, normalizes, and writes the single UpsellBay settings option.
 *
 * @since 1.0.0
 */
final class Settings {
	public const DEFAULT_ACCENT_COLOR = '#3858e9';

	/**
	 * Option reader callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(): mixed
	 */
	private $reader;

	/**
	 * Option writer callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(array<string, mixed>): bool
	 */
	private $writer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $reader Optional reader.
	 * @param callable|null $writer Optional writer.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null ) {
		$this->reader = $reader ?? static function (): array {
			if ( function_exists( 'get_option' ) ) {
				$value = get_option( Constants::SETTINGS_OPTION, array() );
				return is_array( $value ) ? $value : array();
			}

			return array();
		};
		$this->writer = $writer ?? static function ( array $value ): bool {
			if ( function_exists( 'update_option' ) ) {
				return update_option( Constants::SETTINGS_OPTION, $value, false );
			}

			return true;
		};
	}

	/**
	 * Get normalized settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$value = ( $this->reader )();
			return $this->normalize( is_array( $value ) ? $value : array() );
	}

	/**
	 * Seed default settings when the option does not exist.
	 *
	 * @since 1.0.0
	 */
	public function seed_defaults(): bool {
		return ( $this->writer )( $this->defaults() );
	}

	/**
	 * Get max display count for a placement.
	 *
	 * @since 1.0.0
	 *
	 * @param string $placement Placement key.
	 * @return int
	 */
	public function placement_max_display( string $placement ): int {
		$all     = $this->all();
		$default = $this->defaults()['placement_max_display'][ $placement ] ?? 1;

		return isset( $all['placement_max_display'][ $placement ] ) ? max( 1, (int) $all['placement_max_display'][ $placement ] ) : $default;
	}

	/**
	 * Update settings after normalization.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $value Raw settings.
	 */
	public function update( array $value ): bool {
		return ( $this->writer )( $this->normalize( $value ) );
	}

	/**
	 * Return defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'enabled'               => true,
			'test_mode'             => false,
			'placements'            => array(
				'product_upsell' => true,
				'cart_crosssell' => true,
				'checkout_bump'  => true,
				'thankyou_offer' => true,
			),
			'placement_max_display' => array(
				'product_upsell' => 1,
				'cart_crosssell' => 3,
				'checkout_bump'  => 1,
				'thankyou_offer' => 1,
			),
			'style_tokens'          => array(
				'accent_color' => self::DEFAULT_ACCENT_COLOR,
				'button_style' => 'theme',
			),
			'license'               => array(
				'status'     => 'unknown',
				'masked_key' => '',
				'checked_at' => 0,
			),
			'retention_days'        => 365,
			'data_retention'        => array(
				'stats_days'              => 365,
				'session_days'            => 30,
				'log_days'                => 30,
				'prune_order_attribution' => false,
			),
			'cleanup_on_delete'     => false,
			'notice_dismissals'     => array(),
			'debug_logging'         => false,
			'wizard_completed'      => false,
			'wizard_completed_at'   => 0,
			'first_offer_id'        => 0,
		);
	}

	/**
	 * Normalize settings into a complete shape.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $value Raw settings.
	 * @return array<string, mixed>
	 */
	public function normalize( array $value ): array {
		$defaults = $this->defaults();
		$settings = array_replace_recursive( $defaults, $value );

		$settings['enabled']             = $this->to_bool( $settings['enabled'] );
		$settings['test_mode']           = $this->to_bool( $settings['test_mode'] );
		$settings['cleanup_on_delete']   = $this->to_bool( $settings['cleanup_on_delete'] );
		$settings['debug_logging']       = $this->to_bool( $settings['debug_logging'] );
		$settings['wizard_completed']    = $this->to_bool( $settings['wizard_completed'] );
		$settings['wizard_completed_at'] = max( 0, (int) $settings['wizard_completed_at'] );
		$settings['first_offer_id']      = max( 0, (int) $settings['first_offer_id'] );

		$placements = array();
		foreach ( $defaults['placements'] as $key => $default ) {
			$placements[ $key ] = $this->to_bool( $settings['placements'][ $key ] ?? $default );
		}
		$settings['placements'] = $placements;

		if ( ! is_array( $settings['placement_max_display'] ) ) {
			$settings['placement_max_display'] = array();
		}

		$max_display = array();
		foreach ( $defaults['placement_max_display'] as $key => $default ) {
			$val                 = isset( $settings['placement_max_display'][ $key ] ) ? (int) $settings['placement_max_display'][ $key ] : $default;
			$max_display[ $key ] = max( 1, $val );
		}
		$settings['placement_max_display'] = $max_display;

		$retention = (int) $settings['retention_days'];
		if ( $retention < 1 ) {
			$retention = (int) $defaults['retention_days'];
		}
		$settings['retention_days'] = $retention;

		if ( ! is_array( $settings['data_retention'] ) ) {
			$settings['data_retention'] = $defaults['data_retention'];
		}
		$settings['data_retention'] = array_replace( $defaults['data_retention'], $settings['data_retention'] );
		foreach ( array( 'stats_days', 'session_days', 'log_days' ) as $retention_key ) {
			$retention_value = (int) $settings['data_retention'][ $retention_key ];
			if ( $retention_value < 1 ) {
				$retention_value = (int) $defaults['data_retention'][ $retention_key ];
			}
			$settings['data_retention'][ $retention_key ] = $retention_value;
		}
		$settings['data_retention']['prune_order_attribution'] = false;

		if ( ! is_array( $settings['notice_dismissals'] ) ) {
			$settings['notice_dismissals'] = array();
		}

		if ( ! is_array( $settings['license'] ) ) {
			$settings['license'] = $defaults['license'];
		}
		$settings['license'] = array_replace( $defaults['license'], $settings['license'] );

		return $settings;
	}

	/**
	 * Cast common checkbox values to booleans.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 */
	private function to_bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true );
	}
}
