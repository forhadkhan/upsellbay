<?php
/**
 * Lifecycle installer and migration shell.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Core;

/**
 * Handles activation, deactivation, upgrades, and opt-in cleanup.
 *
 * @since 1.0.0
 */
final class Installer {
	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Scheduler service.
	 *
	 * @since 1.0.0
	 *
	 * @var Scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Schema migration callback.
	 *
	 * @var callable(): void
	 */
	private $schema_migrator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings      $settings        Settings service.
	 * @param Scheduler     $scheduler       Scheduler service.
	 * @param callable|null $schema_migrator Optional schema migration callback.
	 */
	public function __construct( Settings $settings, Scheduler $scheduler, ?callable $schema_migrator = null ) {
		$this->settings        = $settings;
		$this->scheduler       = $scheduler;
		$this->schema_migrator = $schema_migrator ?? array( $this, 'migrate_schema' );
	}

	/**
	 * Activation lifecycle.
	 *
	 * @since 1.0.0
	 */
	public function activate(): void {
		$this->register_offer_post_type();
		$this->settings->seed_defaults();
		( $this->schema_migrator )();
		$this->scheduler->ensure_recurring_jobs();

		if ( function_exists( 'update_option' ) ) {
			update_option( Constants::DB_VERSION_OPTION, Constants::DB_VERSION, false );
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Deactivation lifecycle.
	 *
	 * @since 1.0.0
	 */
	public function deactivate(): void {
		$this->scheduler->unschedule_all();

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Runtime self-healing upgrade checks.
	 *
	 * @since 1.0.0
	 */
	public function maybe_upgrade(): void {
		( $this->schema_migrator )();
		$this->scheduler->ensure_recurring_jobs();
		$this->migrate_version_stamp();
	}

	/**
	 * Run one-time upgrade steps and stamp Pro's own DB version.
	 *
	 * Pro used to stamp `upsellbay_db_version`, the same row the free host
	 * stamps, so either plugin's write could hide the other's pending migration.
	 * Pro now keeps `upsellbay_pro_db_version`. The shared row is deliberately
	 * not read as Pro's starting version: the host writes it too, so trusting it
	 * would bring the masking back. A missing Pro stamp runs every one-time step,
	 * and each step is idempotent. The shared row stays in place for the host.
	 *
	 * @since 1.0.0
	 */
	public function migrate_version_stamp(): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		$from = (string) get_option( Constants::DB_VERSION_OPTION, '0' );
		if ( Constants::DB_VERSION === $from ) {
			return;
		}

		// Version 2: the daily refresh_analytics job is retired; drop queued runs.
		if ( version_compare( $from, '2', '<' ) ) {
			$this->scheduler->unschedule_retired_jobs();
		}

		update_option( Constants::DB_VERSION_OPTION, Constants::DB_VERSION, false );
	}

	/**
	 * Register the offer CPT before rewrite flushes.
	 *
	 * @since 1.0.0
	 */
	public function register_offer_post_type(): void {
		if ( ! function_exists( 'register_post_type' ) ) {
			return;
		}

		register_post_type(
			Constants::OFFER_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'UpsellBay Offers', 'upsellbay' ),
					'singular_name' => __( 'UpsellBay Offer', 'upsellbay' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'supports'            => array( 'title', 'revisions', 'page-attributes' ),
				'capability_type'     => 'shop_order',
				'map_meta_cap'        => true,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/**
	 * Create or migrate the aggregate stats table.
	 *
	 * @since 1.0.0
	 */
	public function migrate_schema(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade_file = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
			if ( '' !== $upgrade_file && file_exists( $upgrade_file ) ) {
				require_once $upgrade_file;
			}
		}

		if ( ! function_exists( 'dbDelta' ) || ! isset( $GLOBALS['wpdb'] ) ) {
			return;
		}

		$wpdb    = $GLOBALS['wpdb'];
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

		$stats_table     = $wpdb->prefix . Constants::STATS_TABLE_SUFFIX;
		$stats_table_sql = esc_sql( $stats_table );
		$stats_sql       = self::stats_table_schema_sql( $stats_table_sql, $charset );
		dbDelta( $stats_sql );

		$logs_table     = $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX;
		$logs_table_sql = esc_sql( $logs_table );
		$logs_sql       = self::logs_table_schema_sql( $logs_table_sql, $charset );
		dbDelta( $logs_sql );

		$fbt_table     = $wpdb->prefix . Constants::FBT_TABLE_SUFFIX;
		$fbt_table_sql = esc_sql( $fbt_table );
		$fbt_sql       = self::fbt_table_schema_sql( $fbt_table_sql, $charset );
		dbDelta( $fbt_sql );
	}

	/**
	 * Build the frequently-bought-together index table schema.
	 *
	 * Precomputed product-pair recommendations (see Domain\Recommendations\FbtMiner).
	 * Stores only aggregate product-to-product counts and a score — no customer or
	 * order PII — and is fully re-derivable from order history.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name_sql Escaped table name.
	 * @param string $charset        Charset clause.
	 */
	public static function fbt_table_schema_sql( string $table_name_sql, string $charset ): string {
		return "CREATE TABLE {$table_name_sql} (
			anchor_product_id bigint(20) unsigned NOT NULL,
			recommended_product_id bigint(20) unsigned NOT NULL,
			co_occurrence_count bigint(20) unsigned NOT NULL DEFAULT 0,
			anchor_order_count bigint(20) unsigned NOT NULL DEFAULT 0,
			score decimal(20,6) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (anchor_product_id, recommended_product_id),
			KEY anchor_score (anchor_product_id, score)
		) {$charset};";
	}

	/**
	 * Build the aggregate stats table schema.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name_sql Escaped table name.
	 * @param string $charset        Charset clause.
	 */
	public static function stats_table_schema_sql( string $table_name_sql, string $charset ): string {
		return "CREATE TABLE {$table_name_sql} (
			stat_date date NOT NULL,
			offer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			placement varchar(32) NOT NULL DEFAULT '',
			views bigint(20) unsigned NOT NULL DEFAULT 0,
			accepts bigint(20) unsigned NOT NULL DEFAULT 0,
			dismissals bigint(20) unsigned NOT NULL DEFAULT 0,
			orders bigint(20) unsigned NOT NULL DEFAULT 0,
			revenue decimal(20,6) NOT NULL DEFAULT 0,
			discount_total decimal(20,6) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			UNIQUE KEY stat_offer_placement (stat_date, offer_id, placement)
		) {$charset};";
	}

	/**
	 * Build the logs table schema.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name_sql Escaped table name.
	 * @param string $charset        Charset clause.
	 */
	public static function logs_table_schema_sql( string $table_name_sql, string $charset ): string {
		return "CREATE TABLE {$table_name_sql} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			log_type varchar(64) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			description text,
			status varchar(32) NOT NULL DEFAULT 'info',
			source varchar(128) DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(64) DEFAULT NULL,
			object_id bigint(20) unsigned DEFAULT NULL,
			request_data longtext,
			response_data longtext,
			metadata longtext,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY log_type (log_type),
			KEY status (status),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};";
	}

	/**
	 * Opt-in uninstall cleanup. Data is preserved by default.
	 *
	 * Removes only Pro-owned data; the shared settings row and offer posts stay.
	 *
	 * @since 1.0.0
	 */
	public function uninstall(): void {
		$settings = $this->settings->all();
		if ( true !== ( $settings['cleanup_on_delete'] ?? false ) ) {
			return;
		}

		$this->remove_pro_data();

		if ( function_exists( 'delete_option' ) ) {
			delete_option( Constants::LICENSE_DATA_OPTION );
		}
	}

	/**
	 * Permanently delete all Pro data and reset Pro to a fresh install state.
	 *
	 * Offers, the shared settings option and the host's own settings are left
	 * untouched; only Pro's keys, tables and scheduled actions are reset.
	 *
	 * @since 1.0.0
	 */
	public function clear_all_data(): void {
		$this->remove_pro_data();

		$this->settings->seed_defaults();
		( $this->schema_migrator )();
		$this->scheduler->ensure_recurring_jobs();
	}

	/**
	 * Remove Pro-owned data only: scheduled actions, Pro keys inside the shared
	 * settings option, Pro's DB version stamp and Pro's tables. The shared option
	 * row and the shared offer posts belong to the free host and are never deleted.
	 */
	private function remove_pro_data(): void {
		$this->scheduler->unschedule_all();
		$this->settings->remove_pro_keys();

		if ( function_exists( 'delete_option' ) ) {
			delete_option( Constants::DB_VERSION_OPTION );
		}

		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return;
		}

		$wpdb = $GLOBALS['wpdb'];
		foreach ( array( Constants::STATS_TABLE_SUFFIX, Constants::LOGS_TABLE_SUFFIX, Constants::FBT_TABLE_SUFFIX ) as $suffix ) {
			$table = esc_sql( $wpdb->prefix . $suffix );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
