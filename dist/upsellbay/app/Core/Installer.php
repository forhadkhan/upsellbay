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

		$wpdb           = $GLOBALS['wpdb'];
		$charset        = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		$table_name     = $wpdb->prefix . Constants::STATS_TABLE_SUFFIX;
		$table_name_sql = esc_sql( $table_name );

		$sql = self::stats_table_schema_sql( $table_name_sql, $charset );

		dbDelta( $sql );
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
			$wpdb       = $GLOBALS['wpdb'];
			$table_name = esc_sql( $wpdb->prefix . Constants::STATS_TABLE_SUFFIX );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
	}
}
