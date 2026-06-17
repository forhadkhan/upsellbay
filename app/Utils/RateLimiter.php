<?php
/**
 * Public endpoint rate limiter foundation.
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
 * Rate limits public write endpoints by endpoint and client key.
 *
 * @since 1.0.0
 */
final class RateLimiter {
	/**
	 * Transient reader.
	 *
	 * @var callable(string): ?array<string, int>
	 */
	private $reader;

	/**
	 * Transient writer.
	 *
	 * @var callable(string, array<string, int>, int): void
	 */
	private $writer;

	/**
	 * Time provider.
	 *
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $reader Optional reader.
	 * @param callable|null $writer Optional writer.
	 * @param callable|null $clock  Optional clock.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null, ?callable $clock = null ) {
		$this->reader = $reader ?? static function ( string $key ): ?array {
			if ( ! function_exists( 'get_transient' ) ) {
				return null;
			}

			$value = get_transient( $key );
			return is_array( $value ) ? $value : null;
		};
		$this->writer = $writer ?? static function ( string $key, array $value, int $ttl ): void {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( $key, $value, $ttl );
			}
		};
		$this->clock  = $clock ?? static fn (): int => time();
	}

	/**
	 * Record a hit and return whether it is allowed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Endpoint key.
	 * @param string $client_key IP/session/client key.
	 * @param int    $limit Max hits.
	 * @param int    $ttl Window length in seconds.
	 */
	public function hit( string $endpoint, string $client_key, int $limit = 30, int $ttl = 60 ): bool {
		$key    = $this->key( $endpoint, $client_key );
		$now    = ( $this->clock )();
		$record = ( $this->reader )( $key );

		if ( ! is_array( $record ) || ( $record['expires'] ?? 0 ) <= $now ) {
			$record = array(
				'count'   => 0,
				'expires' => $now + $ttl,
			);
		}

		++$record['count'];
		( $this->writer )( $key, $record, $ttl );

		return $record['count'] <= $limit;
	}

	/**
	 * Build a storage key without exposing the raw client value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Endpoint key.
	 * @param string $client_key Client key.
	 */
	private function key( string $endpoint, string $client_key ): string {
		return Constants::OPTION_PREFIX . 'rate_' . md5( $endpoint . '|' . $client_key );
	}
}
