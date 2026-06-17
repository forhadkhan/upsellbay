<?php
/**
 * Token generation and hashing helpers.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Creates random tokens and stores only deterministic hashes.
 *
 * @since 1.0.0
 */
final class TokenHelper {
	/**
	 * Generate a random token.
	 *
	 * @since 1.0.0
	 *
	 * @param int $length Desired token length.
	 */
	public function generate( int $length = 32 ): string {
		$length = max( 16, $length );
		$bytes  = random_bytes( (int) ceil( $length / 2 ) );

		return substr( bin2hex( $bytes ), 0, $length );
	}

	/**
	 * Hash a token for storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Raw token.
	 */
	public function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Verify a token against a stored hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Raw token.
	 * @param string $hash  Stored hash.
	 */
	public function verify( string $token, string $hash ): bool {
		return hash_equals( $hash, $this->hash( $token ) );
	}
}
