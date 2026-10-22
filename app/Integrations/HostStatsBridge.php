<?php
/**
 * Keeps the free host's offer counters and Pro's aggregate stats in step.
 *
 * @package UpsellBayPro\Integrations
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsService;

/**
 * The host counts views, accepts and dismissals from its own `upsellbay_*`
 * interaction hooks and shows them on the offers list and its dashboard. Pro
 * fires the same events under its own `upsellbay_pro_*` prefix and keeps a
 * separate aggregate table. This bridge forwards each interaction both ways so
 * neither side misses one:
 *
 * - Pro placements (product page, cart, thank-you) are re-announced on the
 *   host hooks so the host counters include them.
 * - Checkout bump events, which only the host fires, are recorded in Pro's
 *   aggregate table.
 *
 * Each placement flows in one direction only, so nothing is counted twice.
 *
 * @since 1.0.0
 */
final class HostStatsBridge {
	/**
	 * Placement the host owns; its events are recorded into Pro's stats.
	 */
	private const HOST_PLACEMENT = 'checkout_bump';

	/**
	 * Analytics service.
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param AnalyticsService $analytics Analytics service.
	 */
	public function __construct( AnalyticsService $analytics ) {
		$this->analytics = $analytics;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_action( Constants::hook_name( 'offer_accepted' ), array( $this, 'forward_accept' ), 10, 3 );
		add_action( Constants::hook_name( 'offer_dismissed' ), array( $this, 'forward_dismiss' ), 10, 2 );
		add_action( Constants::hook_name( 'offer_viewed' ), array( $this, 'forward_view' ), 10, 2 );

		add_action( 'upsellbay_offer_accepted', array( $this, 'record_host_accept' ), 10, 2 );
		add_action( 'upsellbay_offer_dismissed', array( $this, 'record_host_dismiss' ), 10, 2 );
		add_action( 'upsellbay_offer_viewed', array( $this, 'record_host_view' ), 10, 2 );
	}

	/**
	 * Re-announce a Pro placement accept on the host's hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id  Offer ID.
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $result    Accept result.
	 */
	public function forward_accept( $offer_id, $placement = '', $result = array() ): void {
		if ( self::HOST_PLACEMENT !== $placement ) {
			do_action( 'upsellbay_offer_accepted', (int) $offer_id, (string) $placement, is_array( $result ) ? $result : array() );
		}
	}

	/**
	 * Re-announce a Pro placement dismissal on the host's hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 */
	public function forward_dismiss( $offer_id, $placement = '' ): void {
		if ( self::HOST_PLACEMENT !== $placement ) {
			do_action( 'upsellbay_offer_dismissed', (int) $offer_id, (string) $placement );
		}
	}

	/**
	 * Re-announce a Pro placement view on the host's hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 */
	public function forward_view( $offer_id, $placement = '' ): void {
		if ( self::HOST_PLACEMENT !== $placement ) {
			do_action( 'upsellbay_offer_viewed', (int) $offer_id, (string) $placement );
		}
	}

	/**
	 * Record a host-fired accept in Pro's aggregate stats.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $offer_id  Offer ID.
	 * @param mixed $placement Placement key.
	 */
	public function record_host_accept( $offer_id, $placement = '' ): void {
		$this->record( 'accept', $offer_id, $placement );
	}

	/**
	 * Record a host-fired dismissal in Pro's aggregate stats.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $offer_id  Offer ID.
	 * @param mixed $placement Placement key.
	 */
	public function record_host_dismiss( $offer_id, $placement = '' ): void {
		$this->record( 'dismiss', $offer_id, $placement );
	}

	/**
	 * Record a host-fired view in Pro's aggregate stats.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $offer_id  Offer ID.
	 * @param mixed $placement Placement key.
	 */
	public function record_host_view( $offer_id, $placement = '' ): void {
		$this->record( 'view', $offer_id, $placement );
	}

	/**
	 * Record one event for the host-owned placement.
	 *
	 * @param string $event     Analytics event name.
	 * @param mixed  $offer_id  Offer ID.
	 * @param mixed  $placement Placement key.
	 */
	private function record( string $event, $offer_id, $placement ): void {
		if ( self::HOST_PLACEMENT !== $placement || (int) $offer_id <= 0 ) {
			return;
		}

		$date = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$this->analytics->record_event( $event, (int) $offer_id, self::HOST_PLACEMENT, $date );
	}
}
