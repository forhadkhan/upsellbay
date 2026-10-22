<?php
/**
 * Binds every recurring Action Scheduler hook to its handler.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Data\StatsRepository;

/**
 * Action Scheduler runs jobs from WP-Cron, its async request and WP-CLI, none
 * of which is an admin page, and fails a job whose hook has no callback. So the
 * handlers are bound from Plugin::register_hooks(), which runs on every request
 * once the host is present, and each scheduled hook in Scheduler::jobs() must
 * appear in handlers().
 *
 * @since 1.0.0
 */
final class ScheduledJobs {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Stats repository.
	 *
	 * @var StatsRepository
	 */
	private StatsRepository $stats;

	/**
	 * License background check handler.
	 *
	 * @var callable(): void
	 */
	private $check_license;

	/**
	 * Log retention handler.
	 *
	 * @var callable(): void
	 */
	private $prune_logs;

	/**
	 * Store-date provider (Y-m-d).
	 *
	 * @var callable(): string
	 */
	private $today;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings        $settings      Settings service.
	 * @param StatsRepository $stats         Stats repository.
	 * @param callable        $check_license License background check handler.
	 * @param callable        $prune_logs    Log retention handler.
	 * @param callable|null   $today         Store-date provider.
	 */
	public function __construct( Settings $settings, StatsRepository $stats, callable $check_license, callable $prune_logs, ?callable $today = null ) {
		$this->settings      = $settings;
		$this->stats         = $stats;
		$this->check_license = $check_license;
		$this->prune_logs    = $prune_logs;
		$this->today         = $today ?? static function (): string {
			return function_exists( 'wp_date' ) ? (string) wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		};
	}

	/**
	 * Map each scheduled hook to its handler.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, callable>
	 */
	public function handlers(): array {
		return array(
			Constants::hook_name( 'prune_stats' )   => array( $this, 'prune_stats' ),
			Constants::hook_name( 'check_license' ) => $this->check_license,
			Constants::hook_name( 'prune_logs' )    => $this->prune_logs,
		);
	}

	/**
	 * Register the handlers.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		foreach ( $this->handlers() as $hook => $handler ) {
			add_action( $hook, $handler );
		}
	}

	/**
	 * Delete aggregate stats rows older than the merchant's stats retention.
	 *
	 * Retention is `data_retention.stats_days` (default 365, normalized to at
	 * least 1). Only Pro's own daily stats table is touched; offers, orders and
	 * order attribution are never pruned. Running it twice deletes nothing more.
	 *
	 * @since 1.0.0
	 *
	 * @return int Rows deleted.
	 */
	public function prune_stats(): int {
		$settings = $this->settings->all();
		$days     = (int) ( $settings['data_retention']['stats_days'] ?? 365 );
		if ( $days < 1 ) {
			return 0;
		}

		$today = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) ( $this->today )() );
		if ( false === $today ) {
			return 0;
		}

		return $this->stats->prune_before( $today->modify( '-' . $days . ' days' )->format( 'Y-m-d' ) );
	}
}
