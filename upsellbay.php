<?php
/**
 * Plugin Name: UpsellBay Pro
 * Plugin URI: https://wpanchorbay.com/upsellbay/
 * Description: WooCommerce-native order bumps and AOV offers for product, cart, checkout, and thank-you journeys.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: WPAnchorBay
 * Author URI: https://wpanchorbay.com/
 * Text Domain: upsellbay
 * Domain Path: /languages
 * Requires Plugins: woocommerce, upsellbay-order-bump-for-woocommerce
 * WC requires at least: 10.8
 * WC tested up to: 11.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package UpsellBay
 */

declare(strict_types=1);

use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Installer;
use WPAnchorBay\UpsellBayPro\Core\Platform;
use WPAnchorBay\UpsellBayPro\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$upsellbay_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $upsellbay_autoload ) ) {
	require_once $upsellbay_autoload;
} else {
	require_once __DIR__ . '/app/Core/Constants.php';
	require_once __DIR__ . '/app/Core/Container.php';
	require_once __DIR__ . '/app/Core/Settings.php';
	require_once __DIR__ . '/app/Core/Platform.php';
	require_once __DIR__ . '/app/Core/Scheduler.php';
	require_once __DIR__ . '/app/Core/Installer.php';
	require_once __DIR__ . '/app/Core/Updater.php';
	require_once __DIR__ . '/app/Domain/Offers/ValidationResult.php';
	require_once __DIR__ . '/app/Domain/Offers/OfferSchema.php';
	require_once __DIR__ . '/app/Domain/Offers/OfferValidator.php';
	require_once __DIR__ . '/app/Data/OfferRepository.php';
	require_once __DIR__ . '/app/Data/StatsRepository.php';
	require_once __DIR__ . '/app/Data/CartSession.php';
	require_once __DIR__ . '/app/Domain/Analytics/AnalyticsRecorder.php';
	require_once __DIR__ . '/app/Domain/Analytics/StatsReconciler.php';
	require_once __DIR__ . '/app/Core/Plugin.php';
	require_once __DIR__ . '/app/Integrations/Licensing/LicenseClient.php';
	require_once __DIR__ . '/app/Api/Routes/LicenseRoute.php';
	require_once __DIR__ . '/app/Utils/ImportExporter.php';
	require_once __DIR__ . '/app/Utils/Logger.php';
	require_once __DIR__ . '/app/Utils/RateLimiter.php';
	require_once __DIR__ . '/app/Utils/TokenHelper.php';
}

Constants::init( __FILE__ );

add_action( 'before_woocommerce_init', array( Plugin::instance(), 'declare_wc_feature_compatibility' ) );

register_activation_hook(
	__FILE__,
	static function (): void {
		$result = Platform::check(
			PHP_VERSION,
			isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '',
			Platform::is_woocommerce_active()
		);

		if ( ! $result['ok'] && function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html( implode( ' ', $result['errors'] ) ) );
		}

		Plugin::instance()->container()->get( Installer::class )->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Plugin::instance()->container()->get( Installer::class )->deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( function_exists( 'load_plugin_textdomain' ) ) {
			// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
			load_plugin_textdomain( 'upsellbay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		if ( ! defined( 'UPSELLBAY_VERSION' ) || version_compare( UPSELLBAY_VERSION, Constants::MIN_HOST_VERSION, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! function_exists( 'esc_html' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error upsellbay-pro-notice"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: minimum required UpsellBay version. */
								__( 'UpsellBay Pro requires UpsellBay (Order Bump for WooCommerce) version %s or newer to be installed and active.', 'upsellbay' ),
								Constants::MIN_HOST_VERSION
							)
						)
					);
				}
			);

			return;
		}

		Plugin::instance()->init();
	},
	20
);
