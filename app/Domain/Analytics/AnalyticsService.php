<?php
/**
 * Analytics service.
 *
 * @package UpsellBay\Domain\Analytics
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Analytics;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Hooks;

/**
 * Routes offer lifecycle analytics events to aggregate counters.
 *
 * @since 1.0.0
 */
final class AnalyticsService {
	/**
	 * Analytics recorder.
	 *
	 * @var AnalyticsRecorder
	 */
	private AnalyticsRecorder $recorder;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param AnalyticsRecorder $recorder Analytics recorder.
	 */
	public function __construct( AnalyticsRecorder $recorder ) {
		$this->recorder = $recorder;
	}

	/**
	 * Record a named analytics event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event          Event name.
	 * @param int    $offer_id       Offer ID.
	 * @param string $placement      Placement.
	 * @param string $date           Store date.
	 * @param string $revenue        Revenue delta.
	 * @param string $discount_total Discount delta.
	 */
	public function record_event( string $event, int $offer_id, string $placement, string $date, string $revenue = '0.000000', string $discount_total = '0.000000' ): void {
		$payload = array(
			'event'          => $event,
			'offer_id'       => $offer_id,
			'placement'      => $placement,
			'date'           => $date,
			'revenue'        => $revenue,
			'discount_total' => $discount_total,
		);
		$payload = Hooks::filter( 'analytics_event', $payload );

		match ( (string) $payload['event'] ) {
			'view'    => $this->recorder->record_view( (int) $payload['offer_id'], (string) $payload['placement'], (string) $payload['date'] ),
			'accept'  => $this->recorder->record_accept( (int) $payload['offer_id'], (string) $payload['placement'], (string) $payload['date'], (string) $payload['revenue'], (string) $payload['discount_total'] ),
			'dismiss' => $this->recorder->record_dismissal( (int) $payload['offer_id'], (string) $payload['placement'], (string) $payload['date'] ),
			'order'   => $this->recorder->record_order( (int) $payload['offer_id'], (string) $payload['placement'], (string) $payload['date'], (string) $payload['revenue'], (string) $payload['discount_total'] ),
			default   => null,
		};
	}
}
