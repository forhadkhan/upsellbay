<?php
/**
 * Client IP resolution for rate limiting and logs.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Core\Hooks;

/**
 * Resolves the connecting IP and derives rate-limit buckets and log-safe forms.
 *
 * Only REMOTE_ADDR is read: forwarded headers (Client-IP, X-Forwarded-For) are
 * sent by the browser and would let a client pick its own bucket or forge the
 * logged address. A site behind a proxy returns the trusted visitor IP through
 * `upsellbay_pro_rate_limit_client_ip`.
 *
 * @since 1.0.0
 */
final class ClientIp {
	/**
	 * The connecting IP, or "unknown" when none is valid.
	 *
	 * @since 1.0.0
	 */
	public static function current(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- isset() is checked inline; the value is validated as an IP below.
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip  = false !== filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : 'unknown';

		/**
		 * Filter the client IP that public UpsellBay routes rate-limit on and logs record.
		 *
		 * Return a value only from a source you trust (e.g. a proxy header your own
		 * reverse proxy sets); never from a header the browser can send. A value
		 * that is not a valid IP address is ignored.
		 *
		 * @since 1.0.0
		 *
		 * @param string $ip REMOTE_ADDR, or "unknown" when it is missing or invalid.
		 */
		$filtered = Hooks::filter( 'rate_limit_client_ip', $ip );

		return is_string( $filtered ) && false !== filter_var( $filtered, FILTER_VALIDATE_IP ) ? $filtered : $ip;
	}

	/**
	 * Rate-limit bucket for an IP.
	 *
	 * IPv4 addresses are keyed one by one. IPv6 addresses are keyed by their /64
	 * network: one subscriber line usually holds a whole /64, so per-address
	 * buckets would let a single client rotate through billions of fresh ones.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address.
	 */
	public static function bucket( string $ip ): string {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return $ip;
		}

		$network = self::ipv6_prefix( $ip, 64 );

		return null === $network ? 'unknown' : $network . '/64';
	}

	/**
	 * Truncated IP for logs: IPv4 /24, IPv6 /48 (the same widths WordPress core
	 * uses for privacy anonymization). Empty string when the IP is not valid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address.
	 */
	public static function anonymize( string $ip ): string {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';

			return implode( '.', $parts );
		}

		return self::ipv6_prefix( $ip, 48 ) ?? '';
	}

	/**
	 * Zero every bit of an IPv6 address after the given prefix length.
	 *
	 * @param string $ip     IPv6 address.
	 * @param int    $prefix Prefix length in bits (a multiple of 8).
	 */
	private static function ipv6_prefix( string $ip, int $prefix ): ?string {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return null;
		}

		$packed = inet_pton( $ip );
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return null;
		}

		$bytes  = intdiv( $prefix, 8 );
		$masked = inet_ntop( substr( $packed, 0, $bytes ) . str_repeat( "\0", 16 - $bytes ) );

		return false === $masked ? null : $masked;
	}
}
