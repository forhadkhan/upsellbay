<?php
/**
 * Safe WooCommerce logger wrapper.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Writes optional debug logs with sensitive values masked.
 *
 * @since 1.0.0
 */
final class Logger {
	/**
	 * Woo logger compatible object.
	 *
	 * @var object|null
	 */
	private ?object $logger;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param object|null $logger  Optional logger.
	 * @param bool        $enabled Whether logging is enabled.
	 */
	public function __construct( ?object $logger = null, bool $enabled = false ) {
		$this->logger  = $logger;
		$this->enabled = $enabled;
	}

	/**
	 * Log a debug message when enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Raw message.
	 * @param array<string, mixed> $context Context values.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$logger = $this->logger;
		if ( null === $logger && function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
		}

		if ( ! is_object( $logger ) || ! method_exists( $logger, 'debug' ) ) {
			return;
		}

		$logger->debug(
			$this->mask( $message ),
			array(
				'source'  => Constants::PLUGIN_SLUG,
				'context' => $this->mask( wp_json_encode( $context ) ? wp_json_encode( $context ) : '' ),
			)
		);
	}

	/**
	 * Mask secrets, tokens, emails, and long identifiers.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw value.
	 */
	public function mask( string $value ): string {
		$value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[masked-email]', $value ) ?? $value;
		$value = preg_replace( '/(token|license|key|payment)[=: ]+[A-Za-z0-9_\-]{8,}/i', '$1=[masked]', $value ) ?? $value;
		$value = preg_replace( '/\b[A-Za-z0-9_\-]{16,}\b/', '[masked-secret]', $value ) ?? $value;

		return $value;
	}
}
