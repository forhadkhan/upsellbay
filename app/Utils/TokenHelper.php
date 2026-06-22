<?php
/**
 * Token generation and hashing helpers.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
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

	/**
	 * Create a signed stateless action token.
	 *
	 * This is used for thank-you follow-on offers because WooCommerce can rotate
	 * or clear the cart session after the original order is complete.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $scope  Token scope.
	 * @param array<string, mixed> $claims Non-sensitive token claims.
	 */
	public function sign_action( string $scope, array $claims ): string {
		return hash_hmac( 'sha256', $this->action_payload( $scope, $claims ), $this->signing_secret() );
	}

	/**
	 * Verify a signed stateless action token.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $token  Submitted token.
	 * @param string               $scope  Expected token scope.
	 * @param array<string, mixed> $claims Expected token claims.
	 */
	public function verify_action( string $token, string $scope, array $claims ): bool {
		if ( '' === $token ) {
			return false;
		}

		return hash_equals( $this->sign_action( $scope, $claims ), $token );
	}

	/**
	 * Build a deterministic action-token payload.
	 *
	 * @param string               $scope  Token scope.
	 * @param array<string, mixed> $claims Token claims.
	 */
	private function action_payload( string $scope, array $claims ): string {
		ksort( $claims );

		return $scope . '|' . wp_json_encode( $claims );
	}

	/**
	 * Return the WordPress signing secret.
	 */
	private function signing_secret(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( 'auth' );
		}

		if ( defined( 'AUTH_SALT' ) && '' !== (string) AUTH_SALT ) {
			return (string) AUTH_SALT;
		}

		if ( defined( 'AUTH_KEY' ) && '' !== (string) AUTH_KEY ) {
			return (string) AUTH_KEY;
		}

		return 'upsellbay-action-token';
	}
}
