<?php
/**
 * Logger Interface.
 *
 * @package UpsellBay\Domain\Logging
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Logging;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * PSR-3 style logger interface for UpsellBay.
 *
 * @since 1.0.0
 */
interface LoggerInterface {
	/**
	 * System is unusable.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function emergency( string $title, array $context = array() ): void;

	/**
	 * Action must be taken immediately.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function alert( string $title, array $context = array() ): void;

	/**
	 * Critical conditions.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function critical( string $title, array $context = array() ): void;

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function error( string $title, array $context = array() ): void;

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function warning( string $title, array $context = array() ): void;

	/**
	 * Normal but significant events.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function notice( string $title, array $context = array() ): void;

	/**
	 * Interesting events.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function info( string $title, array $context = array() ): void;

	/**
	 * Detailed debug information.
	 *
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function debug( string $title, array $context = array() ): void;

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed                $level   Log level/status.
	 * @param string               $title   Short description.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public function log( $level, string $title, array $context = array() ): void;
}
