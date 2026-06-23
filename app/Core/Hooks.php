<?php
/**
 * Public hook helpers.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Wraps WordPress hooks while keeping non-WordPress tests deterministic.
 *
 * @since 1.0.0
 */
final class Hooks {
	/**
	 * Apply a public UpsellBay filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $suffix Hook suffix without the shared prefix.
	 * @param mixed  $value  Filter value.
	 * @param mixed  ...$args Additional filter arguments.
	 * @return mixed
	 */
	public static function filter( string $suffix, $value, ...$args ) {
		if ( ! function_exists( 'apply_filters' ) ) {
			return $value;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		return apply_filters( Constants::hook_name( $suffix ), $value, ...$args );
	}

	/**
	 * Fire a public UpsellBay action.
	 *
	 * @since 1.0.0
	 *
	 * @param string $suffix Hook suffix without the shared prefix.
	 * @param mixed  ...$args Action arguments.
	 */
	public static function action( string $suffix, ...$args ): void {
		if ( function_exists( 'do_action' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			do_action( Constants::hook_name( $suffix ), ...$args );
		}
	}
}
