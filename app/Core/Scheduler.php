<?php
/**
 * Action Scheduler integration shell.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

/**
 * Registers UpsellBay recurring background jobs idempotently.
 *
 * @since 1.0.0
 */
final class Scheduler {
	/**
	 * Action existence callback.
	 *
	 * @var callable(string, array<int, mixed>, string): bool
	 */
	private $has_action;

	/**
	 * Schedule callback.
	 *
	 * @var callable(int, string, string, array<int, mixed>, string): void
	 */
	private $schedule_action;

	/**
	 * Unschedule callback.
	 *
	 * @var callable(string, array<int, mixed>, string): void
	 */
	private $unschedule_action;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $has_action        Optional existence callback.
	 * @param callable|null $schedule_action   Optional schedule callback.
	 * @param callable|null $unschedule_action Optional unschedule callback.
	 */
	public function __construct( ?callable $has_action = null, ?callable $schedule_action = null, ?callable $unschedule_action = null ) {
		$this->has_action        = $has_action ?? static function ( string $hook, array $args, string $group ): bool {
			return function_exists( 'as_has_scheduled_action' ) && (bool) as_has_scheduled_action( $hook, $args, $group );
		};
		$this->schedule_action   = $schedule_action ?? static function ( int $timestamp, string $recurrence, string $hook, array $args, string $group ): void {
			if ( function_exists( 'as_schedule_recurring_action' ) ) {
				as_schedule_recurring_action( $timestamp, self::recurrence_interval( $recurrence ), $hook, $args, $group );
			}
		};
		$this->unschedule_action = $unschedule_action ?? static function ( string $hook, array $args, string $group ): void {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, $args, $group );
			}
		};
	}

	/**
	 * Return recurring job definitions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{hook: string, recurrence: string}>
	 */
	public function jobs(): array {
		return array(
			array(
				'hook'       => Constants::hook_name( 'refresh_analytics' ),
				'recurrence' => 'daily',
			),
			array(
				'hook'       => Constants::hook_name( 'prune_stats' ),
				'recurrence' => 'daily',
			),
			array(
				'hook'       => Constants::hook_name( 'check_license' ),
				'recurrence' => 'twicedaily',
			),
			array(
				'hook'       => Constants::hook_name( 'prune_logs' ),
				'recurrence' => 'daily',
			),
		);
	}

	/**
	 * Convert a recurrence string to interval in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @param string $recurrence Named recurrence (hourly, twicedaily, daily).
	 *
	 * @return int Interval in seconds.
	 */
	private static function recurrence_interval( string $recurrence ): int {
		return match ( $recurrence ) {
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			default      => DAY_IN_SECONDS,
		};
	}

	/**
	 * Ensure each recurring job exists once.
	 *
	 * @since 1.0.0
	 */
	public function ensure_recurring_jobs(): void {
		foreach ( $this->jobs() as $index => $job ) {
			$hook  = $job['hook'];
			$group = Constants::ACTION_SCHEDULER_GROUP;
			$args  = array();

			if ( ( $this->has_action )( $hook, $args, $group ) ) {
				continue;
			}

			( $this->schedule_action )( time() + ( 60 * ( $index + 1 ) ), $job['recurrence'], $hook, $args, $group );
		}
	}

	/**
	 * Unschedule all UpsellBay jobs.
	 *
	 * @since 1.0.0
	 */
	public function unschedule_all(): void {
		foreach ( $this->jobs() as $job ) {
			( $this->unschedule_action )( $job['hook'], array(), Constants::ACTION_SCHEDULER_GROUP );
		}
	}
}
