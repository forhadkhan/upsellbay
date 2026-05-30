<?php
/**
 * Admin overview summary.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Data\StatsRepository;

/**
 * Provides compact operational summary data for admin screens.
 *
 * @since 1.0.0
 */
final class OverviewSummary {
	/**
	 * Offer repository.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $offers;

	/**
	 * Stats repository.
	 *
	 * @since 1.0.0
	 *
	 * @var StatsRepository
	 */
	private StatsRepository $stats;

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository $offers   Offer repository.
	 * @param StatsRepository $stats    Stats repository.
	 * @param Settings        $settings Settings service.
	 */
	public function __construct( OfferRepository $offers, StatsRepository $stats, Settings $settings ) {
		$this->offers   = $offers;
		$this->stats    = $stats;
		$this->settings = $settings;
	}

	/**
	 * Return summary data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		$settings      = $this->settings->all();
		$offers        = $this->offers->query( array( 'limit' => 200 ) );
		$active_offers = 0;

		foreach ( $offers as $offer ) {
			$meta = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
			if ( 'active' === ( $meta['_ub_status'] ?? '' ) ) {
				++$active_offers;
			}
		}

			$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$recent          = $this->stats->summary( gmdate( 'Y-m-d', time() - $day_seconds * 30 ), gmdate( 'Y-m-d' ) );

		return array(
			'enabled'        => (bool) $settings['enabled'],
			'test_mode'      => (bool) $settings['test_mode'],
			'active_offers'  => $active_offers,
			'recent_revenue' => $recent['revenue'],
			'warnings'       => array(),
		);
	}
}
