<?php
/**
 * Adds Pro-only columns to the host plugin's offers list table.
 *
 * @package UpsellBay\Integrations
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Data\OfferRepository;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferConflictDetector;

/**
 * Attaches to the host's upsellbay_bump_list_columns / upsellbay_bump_list_column_content hooks.
 *
 * @since 1.0.0
 */
final class OffersListColumnsIntegration {
	/**
	 * Offer repository.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $repository;

	/**
	 * Offer conflict detector.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferConflictDetector
	 */
	private OfferConflictDetector $conflict_detector;

	/**
	 * Stats repository.
	 *
	 * @since 1.0.0
	 *
	 * @var StatsRepository
	 */
	private StatsRepository $stats;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository       $repository        Offer repository.
	 * @param OfferConflictDetector $conflict_detector Offer conflict detector.
	 * @param StatsRepository       $stats             Stats repository.
	 */
	public function __construct( OfferRepository $repository, OfferConflictDetector $conflict_detector, StatsRepository $stats ) {
		$this->repository        = $repository;
		$this->conflict_detector = $conflict_detector;
		$this->stats             = $stats;
	}

	/**
	 * Attach to the host's offers list-table extension seams.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter( 'upsellbay_bump_list_columns', array( $this, 'add_columns' ) );
		add_action( 'upsellbay_bump_list_column_content', array( $this, 'render_column_content' ), 10, 3 );
		add_action( 'upsellbay_offer_stats_summary_after', array( $this, 'render_stats_summary_after' ) );
	}

	/**
	 * Render revenue/orders/discount rows after the host's native per-offer stats table.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function render_stats_summary_after( int $offer_id ): void {
		$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$summary     = $this->stats->summary(
			gmdate( 'Y-m-d', time() - $day_seconds * 365 ),
			gmdate( 'Y-m-d' ),
			array( 'offer_id' => $offer_id )
		);

		echo '<table class="widefat striped" style="max-width: 480px; margin-top: 12px;">';
		echo '<tbody>';
		echo '<tr><td><strong>' . esc_html__( 'Orders', 'upsellbay' ) . '</strong></td><td>' . esc_html( number_format_i18n( (int) $summary['orders'] ) ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Attributed revenue', 'upsellbay' ) . '</strong></td><td>' . esc_html( $this->format_currency( $summary['revenue'] ) ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Total discounts', 'upsellbay' ) . '</strong></td><td>' . esc_html( $this->format_currency( $summary['discount_total'] ) ) . '</td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Add Health and Revenue columns to the host's offers list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $columns Host column map.
	 * @return array<string, string>
	 */
	public function add_columns( array $columns ): array {
		$columns['health']             = __( 'Health', 'upsellbay' );
		$columns['attributed_revenue'] = __( 'Revenue', 'upsellbay' );

		return $columns;
	}

	/**
	 * Render Health/Revenue cell content for a host offers list-table row.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $column   Column key.
	 * @param int                  $offer_id Offer ID.
	 * @param array<string, mixed> $row      Row data.
	 */
	public function render_column_content( string $column, int $offer_id, array $row ): void {
		unset( $row );

		if ( 'health' === $column ) {
			$offer    = $this->repository->get( $offer_id );
			$meta     = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
			$findings = $this->conflict_detector->detect( $offer_id, $meta );

			if ( array() === $findings ) {
				echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'No conflicts', 'upsellbay' ) . '"></span>';
			} else {
				echo '<span class="dashicons dashicons-warning" style="color: #dba617;" title="' . esc_attr__( 'Placement crowding or funnel overlap detected', 'upsellbay' ) . '"></span>';
			}

			return;
		}

		if ( 'attributed_revenue' === $column ) {
			$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
			$summary     = $this->stats->summary(
				gmdate( 'Y-m-d', time() - $day_seconds * 90 ),
				gmdate( 'Y-m-d' ),
				array( 'offer_id' => $offer_id )
			);

			echo esc_html( $this->format_currency( $summary['revenue'] ?? '0.000000' ) );
		}
	}

	/**
	 * Format a currency value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Value to format.
	 */
	private function format_currency( $value ): string {
		$float_val = (float) $value;
		$symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

		return $symbol . number_format_i18n( $float_val, 2 );
	}
}
