<?php
/**
 * Central UpsellBay runtime identifiers.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

/**
 * Defines product identifiers and shared runtime values.
 *
 * @since 1.0.0
 */
final class Constants {
	public const VERSION                     = '0.1.0';
	public const DB_VERSION                  = '1';
	public const NAMESPACE_ROOT              = 'WPAnchorBay\\UpsellBay';
	public const TEXT_DOMAIN                 = 'upsellbay';
	public const PLUGIN_SLUG                 = 'upsellbay';
	public const PLUGIN_ENTRY_FILE           = 'upsellbay.php';
	public const REST_NAMESPACE              = 'upsellbay/v1';
	public const OPTION_PREFIX               = 'upsellbay_';
	public const SETTINGS_OPTION             = 'upsellbay_settings';
	public const DB_VERSION_OPTION           = 'upsellbay_db_version';
	public const META_PREFIX                 = '_ub_';
	public const HOOK_PREFIX                 = 'upsellbay_';
	public const NONCE_PREFIX                = 'upsellbay_';
	public const OFFER_POST_TYPE             = 'upsellbay_offer';
	public const STATS_TABLE_SUFFIX          = 'upsellbay_offer_stats_daily';
	public const ACTION_SCHEDULER_GROUP      = 'upsellbay';
	public const ASSET_HANDLE_PREFIX         = 'upsellbay-';
	public const LICENSE_PRODUCT_SLUG        = 'upsellbay';
	public const DOCS_URL                    = 'https://wpanchorbay.com/docs/upsellbay/';
	public const SUPPORT_URL                 = 'https://wpanchorbay.com/support/';
	public const MIN_PHP_VERSION             = '8.1';
	public const MIN_WP_VERSION              = '6.9';
	public const MIN_WC_VERSION              = '10.8';
	public const ATTRIBUTION_OFFER_ID        = '_ub_offer_id';
	public const ATTRIBUTION_OFFER_TYPE      = '_ub_offer_type';
	public const ATTRIBUTION_OFFER_PLACEMENT = '_ub_offer_placement';
	public const ATTRIBUTION_DISCOUNT_TYPE   = '_ub_discount_type';
	public const ATTRIBUTION_DISCOUNT_AMOUNT = '_ub_discount_amount';
	public const ATTRIBUTION_SOURCE_CONTEXT  = '_ub_source_context';
	public const ATTRIBUTION_SOURCE_ORDER_ID = '_ub_source_order_id';
	public const ATTRIBUTION_SOURCE_OFFER_ID = '_ub_source_offer_id';
	public const ATTRIBUTION_FOLLOW_ON_ORDER = '_ub_follow_on_order';

	/**
	 * Main plugin file path.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private static string $plugin_file = '';

	/**
	 * Initialize path-dependent constants.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public static function init( string $plugin_file ): void {
		self::$plugin_file = $plugin_file;
	}

	/**
	 * Return the main plugin file path.
	 *
	 * @since 1.0.0
	 */
	public static function plugin_file(): string {
		return self::$plugin_file;
	}

	/**
	 * Return the plugin basename when WordPress helpers are available.
	 *
	 * @since 1.0.0
	 */
	public static function plugin_basename(): string {
		if ( '' === self::$plugin_file ) {
			return self::PLUGIN_ENTRY_FILE;
		}

		if ( function_exists( 'plugin_basename' ) ) {
			return plugin_basename( self::$plugin_file );
		}

		return basename( dirname( self::$plugin_file ) ) . '/' . basename( self::$plugin_file );
	}

	/**
	 * Build an option name from the shared prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $suffix Option suffix.
	 */
	public static function option_name( string $suffix ): string {
		return self::OPTION_PREFIX . ltrim( $suffix, '_' );
	}

	/**
	 * Build an asset handle from the shared prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $suffix Asset handle suffix.
	 */
	public static function asset_handle( string $suffix ): string {
		return self::ASSET_HANDLE_PREFIX . ltrim( $suffix, '-' );
	}

	/**
	 * Build a hook name from the shared prefix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $suffix Hook suffix.
	 */
	public static function hook_name( string $suffix ): string {
		return self::HOOK_PREFIX . ltrim( $suffix, '_' );
	}
}
