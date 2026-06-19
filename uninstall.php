<?php
/**
 * UpsellBay uninstall handler.
 *
 * Data is preserved by default. Destructive cleanup only runs when the merchant
 * explicitly enabled the cleanup option in UpsellBay settings.
 *
 * @package UpsellBay
 */

declare(strict_types=1);
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Installer;
use WPAnchorBay\UpsellBay\Core\Scheduler;
use WPAnchorBay\UpsellBay\Core\Settings;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$upsellbay_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $upsellbay_autoload ) ) {
	require_once $upsellbay_autoload;
} else {
	require_once __DIR__ . '/app/Core/Constants.php';
	require_once __DIR__ . '/app/Core/Settings.php';
	require_once __DIR__ . '/app/Core/Scheduler.php';
	require_once __DIR__ . '/app/Core/Installer.php';
}

Constants::init( __DIR__ . '/upsellbay.php' );

( new Installer( new Settings(), new Scheduler() ) )->uninstall();
