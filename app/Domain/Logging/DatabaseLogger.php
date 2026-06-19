<?php
/**
 * Database Logger.
 *
 * @package UpsellBay\Domain\Logging
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Logging;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Data\LogRepository;

/**
 * PSR-3 inspired logger that stores entries in the custom database table.
 *
 * @since 1.0.0
 */
final class DatabaseLogger implements LoggerInterface {
	/**
	 * Log repository.
	 *
	 * @since 1.0.0
	 *
	 * @var LogRepository
	 */
	private LogRepository $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LogRepository $repository Log repository.
	 */
	public function __construct( LogRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * System is unusable.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function emergency( string $title, array $context = array() ): void {
		$this->log( 'emergency', $title, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function alert( string $title, array $context = array() ): void {
		$this->log( 'alert', $title, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function critical( string $title, array $context = array() ): void {
		$this->log( 'critical', $title, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function error( string $title, array $context = array() ): void {
		$this->log( 'error', $title, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function warning( string $title, array $context = array() ): void {
		$this->log( 'warning', $title, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function notice( string $title, array $context = array() ): void {
		$this->log( 'notice', $title, $context );
	}

	/**
	 * Interesting events.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function info( string $title, array $context = array() ): void {
		$this->log( 'info', $title, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function debug( string $title, array $context = array() ): void {
		$this->log( 'debug', $title, $context );
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed                $level   Log level/status.
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function log( $level, string $title, array $context = array() ): void {
		$log_type    = isset( $context['log_type'] ) && is_string( $context['log_type'] ) ? $context['log_type'] : 'system_event';
		$description = isset( $context['description'] ) && is_string( $context['description'] ) ? $context['description'] : '';

		// Extract context variables and avoid duplicating them in metadata if they are mapped to columns.
		$source        = isset( $context['source'] ) && '' !== $context['source'] ? (string) $context['source'] : null;
		$user_id       = isset( $context['user_id'] ) ? (int) $context['user_id'] : get_current_user_id();
		$object_type   = isset( $context['object_type'] ) && is_string( $context['object_type'] ) ? $context['object_type'] : null;
		$object_id     = isset( $context['object_id'] ) ? (int) $context['object_id'] : null;
		$request_data  = ( ! isset( $context['request_data'] ) || '' === $context['request_data'] || ( is_array( $context['request_data'] ) && 0 === count( $context['request_data'] ) ) ) ? null : wp_json_encode( $context['request_data'] );
		$response_data = ( ! isset( $context['response_data'] ) || '' === $context['response_data'] || ( is_array( $context['response_data'] ) && 0 === count( $context['response_data'] ) ) ) ? null : wp_json_encode( $context['response_data'] );

		// Clean mapped columns from context before saving as metadata.
		$metadata = $context;
		unset( $metadata['log_type'], $metadata['description'], $metadata['source'], $metadata['user_id'], $metadata['object_type'], $metadata['object_id'], $metadata['request_data'], $metadata['response_data'] );

		$ip_address = $this->get_ip_address();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		$this->repository->insert(
			array(
				'log_type'      => sanitize_text_field( $log_type ),
				'title'         => sanitize_text_field( $title ),
				'description'   => sanitize_textarea_field( $description ),
				'status'        => sanitize_text_field( (string) $level ),
				'source'        => null !== $source ? sanitize_text_field( $source ) : null,
				'user_id'       => $user_id,
				'object_type'   => null !== $object_type ? sanitize_text_field( $object_type ) : null,
				'object_id'     => $object_id,
				'request_data'  => $request_data,
				'response_data' => $response_data,
				'metadata'      => ! isset( $context['metadata'] ) || '' === $context['metadata'] || ( is_array( $context['metadata'] ) && 0 === count( $context['metadata'] ) ) ? null : wp_json_encode( $metadata ),
				'ip_address'    => $ip_address,
				'user_agent'    => $user_agent,
			)
		);
	}

	/**
	 * Retrieve current user IP address.
	 *
	 * @return string|null
	 */
	private function get_ip_address(): ?string {
		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) && '' !== $_SERVER['HTTP_CLIENT_IP'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		}
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && '' !== $_SERVER['HTTP_X_FORWARDED_FOR'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		}
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && '' !== $_SERVER['REMOTE_ADDR'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return null;
	}
}
