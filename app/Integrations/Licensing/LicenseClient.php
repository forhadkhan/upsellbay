<?php
/**
 * License client for remote activation and validation.
 *
 * @package UpsellBay\Integrations\Licensing
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\Licensing;

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Hooks;
use WP_Error;

/**
 * Communicates with the WP Anchor Bay license server.
 *
 * @since 1.0.0
 */
final class LicenseClient {

	/**
	 * State reader.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(): array<string, mixed>
	 */
	private $reader;

	/**
	 * State writer.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(array<string, mixed>): bool
	 */
	private $writer;

	/**
	 * Domain resolver.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(): string
	 */
	private $domain_resolver;

	/**
	 * Remote POST callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(string, array<string, mixed>): mixed
	 */
	private $remote_post;

	/**
	 * Remote GET callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(string, array<string, mixed>): mixed
	 */
	private $remote_get;

	/**
	 * Validation transient key.
	 *
	 * @since 1.0.0
	 */
	private const TRANSIENT_KEY = 'upsellbay_license_valid';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $reader          Optional license state reader.
	 * @param callable|null $writer          Optional license state writer.
	 * @param callable|null $domain_resolver Optional domain resolver.
	 * @param callable|null $remote_post     Optional remote POST callback.
	 * @param callable|null $remote_get      Optional remote GET callback.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null, ?callable $domain_resolver = null, ?callable $remote_post = null, ?callable $remote_get = null ) {
		$this->reader = $reader ?? static function (): array {
			$status = get_option( Constants::LICENSE_DATA_OPTION, array() );
			return is_array( $status ) ? $status : array();
		};

		$this->writer = $writer ?? static function ( array $state ): bool {
			return update_option( Constants::LICENSE_DATA_OPTION, $state, false );
		};

		$this->domain_resolver = $domain_resolver ?? static function (): string {
			$domain = wp_parse_url( home_url(), PHP_URL_HOST );

			if ( ! is_string( $domain ) ) {
				return '';
			}

			return sanitize_text_field( $domain );
		};

		$this->remote_post = $remote_post ?? static fn ( string $url, array $args ) => wp_remote_post( $url, $args );
		$this->remote_get  = $remote_get ?? static fn ( string $url, array $args ) => wp_remote_get( $url, $args );
	}

	/**
	 * Activate the supplied license key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key Raw license key input.
	 *
	 * @return true|WP_Error
	 */
	public function activate( string $license_key ): true|WP_Error {
		$sanitized_key = sanitize_text_field( $license_key );

		if ( '' === $sanitized_key ) {
			return new WP_Error(
				'upsellbay_license_missing',
				__( 'Please enter a license key.', 'upsellbay' )
			);
		}

		delete_transient( self::TRANSIENT_KEY );

		$domain = $this->get_domain();

		$response = ( $this->remote_post )(
			Constants::LICENSE_SERVER_URL . '/activate',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 15,
				'body'    => wp_json_encode(
					array(
						'license_key' => $sanitized_key,
						'slug'        => Constants::LICENSE_PRODUCT_SLUG,
						'domain'      => $domain,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->update_status_flag( 'server_error' );
			Hooks::action( 'license_activation_failed', $sanitized_key, 'server_error', $response );

			return new WP_Error(
				'upsellbay_license_server_error',
				__( 'The license server could not be reached. Please try again shortly.', 'upsellbay' )
			);
		}

		return $this->handle_license_response( $response, $sanitized_key );
	}

	/**
	 * Determine whether the current license is valid.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		if ( $this->should_bypass_license_server_for_dev_domain() ) {
			return true;
		}

		$status = $this->get_status();

		if ( ! isset( $status['key'] ) || '' === (string) $status['key'] ) {
			return false;
		}

		$cached_status = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached_status ) {
			return (bool) $cached_status;
		}

		$response = ( $this->remote_get )(
			add_query_arg(
				array(
					'license_key' => sanitize_text_field( (string) $status['key'] ),
					'slug'        => Constants::LICENSE_PRODUCT_SLUG,
					'domain'      => $this->get_domain(),
				),
				Constants::LICENSE_SERVER_URL . '/check'
			),
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->update_status_flag( 'server_error' );
			Hooks::action( 'license_check_failed', 'server_error', $response );
			return true;
		}

		$result = $this->parse_license_response( $response, sanitize_text_field( (string) $status['key'] ) );

		if ( is_wp_error( $result ) ) {
			$this->update_status_flag( 'inactive' );
			set_transient( self::TRANSIENT_KEY, false, 12 * HOUR_IN_SECONDS );
			Hooks::action( 'license_check_failed', $result->get_error_code(), $result );
			return false;
		}

		set_transient( self::TRANSIENT_KEY, true, 12 * HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Return the locally cached license status.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		return ( $this->reader )();
	}

	/**
	 * Mask a license key for safe display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Raw license key.
	 *
	 * @return string Masked key.
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
	 *
	 * @return bool
	 */
	public function can_show_live_offers( array $latest_check = array() ): bool {
		$state = $this->get_status();

		if ( in_array( $latest_check['status'] ?? null, array( 'valid', 'active' ), true ) ) {
			return true;
		}

		if ( true === ( $latest_check['transport_error'] ?? false ) && in_array( $state['status'] ?? null, array( 'valid', 'active' ), true ) ) {
			return true;
		}

		return in_array( $state['status'] ?? 'unknown', array( 'valid', 'active', 'unknown' ), true );
	}

	/**
	 * Get the currently saved masked license key.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_masked_key(): string {
		$status = $this->get_status();
		$key    = isset( $status['key'] ) ? sanitize_text_field( (string) $status['key'] ) : '';

		if ( '' === $key ) {
			return '';
		}

		return str_repeat( 'X', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
	}

	/**
	 * Remove locally stored license data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function remove_local(): void {
		( $this->writer )( array() );
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Get the current site domain for license checks.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_domain(): string {
		return sanitize_text_field( ( $this->domain_resolver )() );
	}

	/**
	 * Classify local and staging domains that should not consume production activations.
	 *
	 * @since 1.0.0
	 *
	 * @param string $domain Hostname.
	 *
	 * @return bool
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
	 * Determine whether the current domain is a development environment.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_dev_domain(): bool {
		return $this->is_non_production_domain( $this->get_domain() );
	}

	/**
	 * Run the scheduled background license check.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function background_check(): void {
		$this->is_valid();
	}

	/**
	 * Persist a normalized license response from activation.
	 *
	 * @since 1.0.0
	 *
	 * @param array|WP_Error|mixed $response    Remote response.
	 * @param string               $license_key Sanitized license key.
	 *
	 * @return true|WP_Error
	 */
	private function handle_license_response( mixed $response, string $license_key ): true|WP_Error {
		$result = $this->parse_license_response( $response, $license_key );

		if ( is_wp_error( $result ) ) {
			$this->maybe_mark_matching_key_invalid( $license_key );
			Hooks::action( 'license_activation_failed', $license_key, $result->get_error_code(), $result );
			return $result;
		}

		set_transient( self::TRANSIENT_KEY, true, 12 * HOUR_IN_SECONDS );
		Hooks::action( 'license_activated', $license_key );
		return true;
	}

	/**
	 * Mark a rejected key invalid when it is already the stored key.
	 *
	 * This corrects stale local state from older bypass behavior without
	 * clobbering a different previously valid license if a replacement key
	 * fails activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key Sanitized rejected license key.
	 *
	 * @return void
	 */
	private function maybe_mark_matching_key_invalid( string $license_key ): void {
		$license_data = $this->get_status();

		if ( ! isset( $license_data['key'] ) || $license_key !== (string) $license_data['key'] ) {
			return;
		}

		$license_data['status'] = 'invalid';
		$license_data['domain'] = $this->get_domain();
		$this->persist_license_data( $license_data );
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Parse a remote activate or check response.
	 *
	 * @since 1.0.0
	 *
	 * @param array|WP_Error|mixed $response    Remote response.
	 * @param string               $license_key Sanitized license key.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function parse_license_response( mixed $response, string $license_key ): array|WP_Error {
		if ( ! is_array( $response ) ) {
			return new WP_Error(
				'upsellbay_license_invalid_response',
				__( 'The license server returned an unexpected response.', 'upsellbay' )
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$is_valid = $this->response_indicates_valid_license( $response_code, $data );

		if ( ! $is_valid ) {
			$message = $this->license_error_message( $data );

			return new WP_Error( 'upsellbay_license_inactive', $message );
		}

		$normalized = array(
			'key'          => $license_key,
			'status'       => 'active',
			'expires_at'   => isset( $data['expires_at'] ) ? sanitize_text_field( (string) $data['expires_at'] ) : '',
			'plan'         => isset( $data['plan'] ) ? sanitize_text_field( (string) $data['plan'] ) : '',
			'domain'       => $this->get_domain(),
			'activated_at' => time(),
		);

		$this->persist_license_data( $normalized );

		return $normalized;
	}

	/**
	 * Determine whether the decoded response marks the license as valid.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $response_code HTTP status code.
	 * @param array<string, mixed> $data          Decoded response body.
	 *
	 * @return bool
	 */
	private function response_indicates_valid_license( int $response_code, array $data ): bool {
		if ( $response_code < 200 || $response_code >= 300 ) {
			return false;
		}

		$status  = isset( $data['status'] ) ? sanitize_text_field( (string) $data['status'] ) : '';
		$license = isset( $data['license'] ) ? sanitize_text_field( (string) $data['license'] ) : '';

		if ( '' !== $license && in_array( strtolower( $license ), array( 'valid', 'active' ), true ) ) {
			return true;
		}

		return in_array( $status, array( 'active', 'valid', 'success' ), true );
	}

	/**
	 * Extract a safe license error message from the server response.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Decoded response body.
	 *
	 * @return string
	 */
	private function license_error_message( array $data ): string {
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			return sanitize_text_field( $data['message'] );
		}

		if ( isset( $data['error'] ) && is_array( $data['error'] ) && isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
			return sanitize_text_field( $data['error']['message'] );
		}

		return __( 'The license key could not be activated.', 'upsellbay' );
	}

	/**
	 * Whether to bypass license server calls for development domains.
	 *
	 * This remains available as an explicit test/development override only.
	 * Activations validate against the server by default, including on local
	 * and staging domains, so invalid keys cannot be marked active locally.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function should_bypass_license_server_for_dev_domain(): bool {
		if ( ! $this->is_dev_domain() ) {
			return false;
		}

		/**
		 * Filter whether UpsellBay should bypass the license server for dev domains.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $bypass Whether to bypass the server.
		 * @param string $domain Current site domain.
		 */
		return apply_filters( 'upsellbay_license_dev_domain_bypass', false, $this->get_domain() ); // phpcs:ignore Squiz.PHP.DisallowBooleanArgumentFound.Found
	}

	/**
	 * Persist normalized license data locally.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $license_data Normalized license state.
	 *
	 * @return void
	 */
	private function persist_license_data( array $license_data ): void {
		( $this->writer )( $license_data );
	}

	/**
	 * Update only the local status flag while keeping the saved key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status New status value.
	 *
	 * @return void
	 */
	private function update_status_flag( string $status ): void {
		$license_data           = $this->get_status();
		$license_data['status'] = sanitize_text_field( $status );
		$license_data['domain'] = $this->get_domain();
		$this->persist_license_data( $license_data );
	}
}
