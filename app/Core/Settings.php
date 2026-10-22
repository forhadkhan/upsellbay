<?php
/**
 * Normalized plugin settings.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Core;

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
	 * Depth of writes to the shared option that Pro itself started.
	 *
	 * The settings row is shared with the free host, which writes it too. This
	 * marker lets a `pre_update_option_*` callback tell Pro's own writes apart
	 * from the host's (see HostSettingsIntegration).
	 *
	 * @var int
	 */
	private static int $own_write_depth = 0;

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
	 * Placement keys Pro owns inside the shared `placements` groups.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function pro_placement_keys(): array {
		return array( 'product_upsell', 'cart_crosssell', 'thankyou_offer' );
	}

	/**
	 * Top-level keys Pro owns in the shared option. The free host defines only
	 * enabled, test_mode, placements, placement_max_display, style_tokens,
	 * cleanup_on_delete and wizard_completed; every other default is Pro's.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function pro_only_keys(): array {
		return array( 'license', 'retention_days', 'data_retention', 'notice_dismissals', 'debug_logging', 'wizard_completed_at', 'first_offer_id' );
	}

	/**
	 * Add Pro's missing keys to the shared option.
	 *
	 * Never overwrites a stored value and never writes a host key. Writes only
	 * when something is missing, so repeated activations are no-ops.
	 *
	 * @since 1.0.0
	 */
	public function seed_defaults(): bool {
		$stored   = ( $this->reader )();
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = $this->defaults();
		$changed  = false;

		foreach ( $this->pro_only_keys() as $key ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				$stored[ $key ] = $defaults[ $key ];
				$changed        = true;
			}
		}

		foreach ( array( 'placements', 'placement_max_display' ) as $group ) {
			$current = is_array( $stored[ $group ] ?? null ) ? $stored[ $group ] : array();
			foreach ( $this->pro_placement_keys() as $key ) {
				if ( ! array_key_exists( $key, $current ) ) {
					$current[ $key ] = $defaults[ $group ][ $key ];
					$changed         = true;
				}
			}
			$stored[ $group ] = $current;
		}

		return $changed ? $this->write( $stored ) : true;
	}

	/**
	 * Remove only Pro's keys from the shared option, keeping every host value.
	 *
	 * Does nothing when the option holds no array.
	 *
	 * @since 1.0.0
	 */
	public function remove_pro_keys(): bool {
		$stored = ( $this->reader )();
		if ( ! is_array( $stored ) || array() === $stored ) {
			return true;
		}

		$next = $stored;
		foreach ( $this->pro_only_keys() as $key ) {
			unset( $next[ $key ] );
		}
		foreach ( array( 'placements', 'placement_max_display' ) as $group ) {
			if ( ! is_array( $next[ $group ] ?? null ) ) {
				continue;
			}
			foreach ( $this->pro_placement_keys() as $key ) {
				unset( $next[ $group ][ $key ] );
			}
		}

		return $next === $stored ? true : $this->write( $next );
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
		return $this->write( $this->normalize( $value ) );
	}

	/**
	 * Whether the settings write in progress was started by Pro.
	 *
	 * @since 1.0.0
	 */
	public static function is_own_write(): bool {
		return self::$own_write_depth > 0;
	}

	/**
	 * Write the option, marking the write as Pro's own while it runs.
	 *
	 * @param array<string, mixed> $value Normalized settings.
	 */
	private function write( array $value ): bool {
		++self::$own_write_depth;

		try {
			return (bool) ( $this->writer )( $value );
		} finally {
			--self::$own_write_depth;
		}
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

		// The tokens end up in inline storefront CSS, so only a hex colour and a
		// known button style survive, whatever wrote the option (settings page,
		// import, another plugin).
		$tokens       = is_array( $settings['style_tokens'] ) ? $settings['style_tokens'] : array();
		$accent_color = is_string( $tokens['accent_color'] ?? null ) ? trim( $tokens['accent_color'] ) : '';
		$button_style = is_string( $tokens['button_style'] ?? null ) ? $tokens['button_style'] : '';

		$settings['style_tokens'] = array(
			'accent_color' => 1 === preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $accent_color ) ? $accent_color : self::DEFAULT_ACCENT_COLOR,
			'button_style' => in_array( $button_style, array( 'theme', 'outline' ), true ) ? $button_style : 'theme',
		);

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
