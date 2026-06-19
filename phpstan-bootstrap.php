<?php
/**
 * PHPStan bootstrap for WordPress constants and light function stubs.
 *
 * @package UpsellBay
 */

declare(strict_types=1);

namespace {

if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

if (! defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

if (! defined('WP_UNINSTALL_PLUGIN')) {
	define('WP_UNINSTALL_PLUGIN', false);
}

if (! function_exists('__')) {
	/**
	 * Translation stub.
	 *
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 */
	function __(string $text, string $domain = 'default'): string {
		unset($domain);
		return $text;
	}
}

if (! function_exists('esc_sql')) {
	/**
	 * SQL escape stub.
	 *
	 * @param string $text Text.
	 */
	function esc_sql(string $text): string {
		return $text;
	}
}

if (! class_exists('WC_Product')) {
	class WC_Product {}
}

if (! class_exists('WC_Coupon')) {
	class WC_Coupon {}
}

}

namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
	if (! class_exists(CartSchema::class)) {
		class CartSchema {
			public const IDENTIFIER = 'cart';
		}
	}

	if (! class_exists(CheckoutSchema::class)) {
		class CheckoutSchema {
			public const IDENTIFIER = 'checkout';
		}
	}
}
