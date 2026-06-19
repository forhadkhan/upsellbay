<?php
/**
 * Lifecycle installer and migration shell.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

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
	 * @since 1.0.0
	 */
	public function uninstall(): void {
		$settings = $this->settings->all();
		if ( true !== ( $settings['cleanup_on_delete'] ?? false ) ) {
			return;
		}

		$this->scheduler->unschedule_all();

		if ( function_exists( 'delete_option' ) ) {
			delete_option( Constants::SETTINGS_OPTION );
			delete_option( Constants::DB_VERSION_OPTION );
		}

		if ( isset( $GLOBALS['wpdb'] ) ) {
			$wpdb        = $GLOBALS['wpdb'];
			$stats_table = esc_sql( $wpdb->prefix . Constants::STATS_TABLE_SUFFIX );
			$logs_table  = esc_sql( $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$stats_table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" );
		}
	}

	/**
	 * Permanently delete all UpsellBay data and reset to fresh install state.
	 *
	 * @since 1.0.0
	 */
	public function clear_all_data(): void {
		$this->scheduler->unschedule_all();

		if ( function_exists( 'delete_option' ) ) {
			delete_option( Constants::SETTINGS_OPTION );
			delete_option( Constants::DB_VERSION_OPTION );
		}

		if ( isset( $GLOBALS['wpdb'] ) ) {
			$wpdb        = $GLOBALS['wpdb'];
			$post_type   = esc_sql( Constants::OFFER_POST_TYPE );
			$stats_table = esc_sql( $wpdb->prefix . Constants::STATS_TABLE_SUFFIX );
			$logs_table  = esc_sql( $wpdb->prefix . Constants::LOGS_TABLE_SUFFIX );

			// Delete postmeta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE meta FROM {$wpdb->postmeta} meta LEFT JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE posts.post_type = '{$post_type}'" );

			// Delete posts.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = '{$post_type}'" );

			// Drop tables so they can be recreated cleanly.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$stats_table}" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" );
		}

		$this->settings->seed_defaults();
		( $this->schema_migrator )();
		$this->scheduler->ensure_recurring_jobs();
	}
}
