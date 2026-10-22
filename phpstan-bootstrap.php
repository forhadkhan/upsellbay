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

if (! function_exists('is_cart')) {
	function is_cart(): bool { return false; }
}

if (! function_exists('is_checkout')) {
	function is_checkout(): bool { return false; }
}

if (! function_exists('is_order_received_page')) {
	function is_order_received_page(): bool { return false; }
}

if (! class_exists('WC_Product')) {
	class WC_Product {}
}

if (! class_exists('WC_Coupon')) {
	class WC_Coupon {}
}

if (! class_exists('WC_Stripe_API')) {
	class WC_Stripe_API {
		/**
		 * @param array<string, mixed> $request Request.
		 * @param string               $api     API resource.
		 * @param string               $method  HTTP method.
		 * @return object
		 */
		public static function request($request, $api = 'charges', $method = 'POST') {
			return (object) array();
		}
	}
}

if (! class_exists('WC_Stripe_Helper')) {
	class WC_Stripe_Helper {
		/**
		 * @param float|int|string $total    Amount.
		 * @param string           $currency Currency.
		 * @return int
		 */
		public static function get_stripe_amount($total, $currency = '') {
			return 0;
		}
	}
}

if (! class_exists('WC_Payment_Tokens')) {
	class WC_Payment_Tokens {
		/**
		 * @param int    $customer_id Customer.
		 * @param string $gateway     Gateway id.
		 * @return array<int, object>
		 */
		public static function get_customer_tokens($customer_id, $gateway = '') {
			return array();
		}
	}
}

if (! function_exists('get_user_option')) {
	/**
	 * @param string $option  Option.
	 * @param int    $user_id User.
	 * @return mixed
	 */
	function get_user_option($option, $user_id = 0) {
		return false;
	}
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

// The free host plugin's admin tab value object, which Pro builds through the
// host's `upsellbay_admin_tabs` filter. Signature mirrors the host class.
namespace WPAnchorBay\UpsellBay\Admin\Navigation {
	if (! class_exists(AdminTab::class)) {
		final class AdminTab {
			public function __construct(string $id, string $label, callable $render_callback, ?callable $prepare_callback = null, bool $show_in_nav = true) {}

			public function id(): string {
				return '';
			}
		}
	}
}
