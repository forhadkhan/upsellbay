<?php
/**
 * Platform dependency checks.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Checks minimum runtime dependencies without fatal errors.
 *
 * @since 1.0.0
 */
final class Platform {
	/**
	 * Check runtime requirements.
	 *
	 * @since 1.0.0
	 *
	 * @param string                   $php_version PHP version to test.
	 * @param string                   $wp_version WordPress version to test.
	 * @param bool                     $woocommerce_active Whether WooCommerce is active.
	 * @param array<string, bool>|null $required_functions Required Woo functions map.
	 * @return array{ok: bool, errors: array<int, string>}
	 */
	public static function check(
		string $php_version = PHP_VERSION,
		string $wp_version = '',
		bool $woocommerce_active = false,
		?array $required_functions = null
	): array {
		$errors = array();

		if ( version_compare( $php_version, Constants::MIN_PHP_VERSION, '<' ) ) {
			$errors[] = 'PHP ' . Constants::MIN_PHP_VERSION . ' or newer is required.';
		}

		if ( '' !== $wp_version && version_compare( $wp_version, Constants::MIN_WP_VERSION, '<' ) ) {
			$errors[] = 'WordPress ' . Constants::MIN_WP_VERSION . ' or newer is required.';
		}

		if ( ! $woocommerce_active ) {
			$errors[] = 'WooCommerce ' . Constants::MIN_WC_VERSION . ' or newer must be active.';
		}

		if ( $woocommerce_active ) {
			$required_functions ??= array(
				'wc_get_order'   => function_exists( 'wc_get_order' ),
				'wc_get_product' => function_exists( 'wc_get_product' ),
			);

			foreach ( $required_functions as $function => $available ) {
				if ( ! $available ) {
					$errors[] = "Required WooCommerce function {$function}() is unavailable.";
					break;
				}
			}
		}

		return array(
			'ok'     => array() === $errors,
			'errors' => $errors,
		);
	}

	/**
	 * Determine whether WooCommerce appears active.
	 *
	 * @since 1.0.0
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}
}
