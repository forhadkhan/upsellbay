<?php
/**
 * WP Anchor Bay license client foundation.
 *
 * @package UpsellBay\Integrations\Licensing
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\Licensing;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Handles local license state rules without exposing full license keys.
 *
 * @since 1.0.0
 */
final class LicenseClient {
	/**
	 * State reader.
	 *
	 * @var callable(): array<string, mixed>
	 */
	private $reader;

	/**
	 * State writer.
	 *
	 * @var callable(array<string, mixed>): bool
	 */
	private $writer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $reader Optional state reader.
	 * @param callable|null $writer Optional state writer.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null ) {
		$this->reader = $reader ?? static function (): array {
			if ( function_exists( 'get_option' ) ) {
				$value = get_option( Constants::SETTINGS_OPTION, array() );
				if ( is_array( $value ) && isset( $value['license'] ) && is_array( $value['license'] ) ) {
					return $value['license'];
				}
			}

			return array();
		};
		$this->writer = $writer ?? static function ( array $state ): bool {
			if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
				return true;
			}

			$settings            = get_option( Constants::SETTINGS_OPTION, array() );
			$settings            = is_array( $settings ) ? $settings : array();
			$settings['license'] = $state;

			return update_option( Constants::SETTINGS_OPTION, $settings, false );
		};
	}

	/**
	 * Mask a license key for storage or display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Raw license key.
	 */
	public function mask_key( string $key ): string {
		$key = trim( $key );
		if ( '' === $key ) {
			return '';
		}

		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}

		return substr( $key, 0, 2 ) . '****' . substr( $key, -2 );
	}

	/**
	 * Determine whether live offers may render under the current license state.
	 *
	 * License transport errors fail open when the last-known state was valid.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $latest_check Latest license check result.
	 */
	public function can_show_live_offers( array $latest_check = array() ): bool {
		$state = ( $this->reader )();

		if ( 'valid' === ( $latest_check['status'] ?? null ) ) {
			return true;
		}

		if ( true === ( $latest_check['transport_error'] ?? false ) && 'valid' === ( $state['status'] ?? null ) ) {
			return true;
		}

		return 'valid' === ( $state['status'] ?? null ) || 'unknown' === ( $state['status'] ?? 'unknown' );
	}

	/**
	 * Classify local and staging domains that should not consume production activations.
	 *
	 * @since 1.0.0
	 *
	 * @param string $domain Hostname.
	 */
	public function is_non_production_domain( string $domain ): bool {
		$domain = strtolower( trim( $domain ) );

		if ( '' === $domain ) {
			return false;
		}

		return (bool) preg_match( '/(\.test|\.local|\.localhost|\.invalid|\.staging|\.dev)$/', $domain )
			|| str_starts_with( $domain, 'localhost' )
			|| str_contains( $domain, '.wpenginepowered.com' )
			|| str_contains( $domain, '.kinsta.cloud' )
			|| str_contains( $domain, '.cloudwaysapps.com' );
	}

	/**
	 * Cache local license state.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $state License state.
	 */
	public function cache_state( array $state ): bool {
		$state['product']    = Constants::LICENSE_PRODUCT_SLUG;
		$state['checked_at'] = time();

		if ( isset( $state['key'] ) ) {
			$state['masked_key'] = $this->mask_key( (string) $state['key'] );
			unset( $state['key'] );
		}

		return ( $this->writer )( $state );
	}
}
