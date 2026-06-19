<?php
/**
 * Dashboard overview admin tab.
 *
 * @package UpsellBay\Admin\Dashboard
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Dashboard;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Admin\OverviewSummary;
use WPAnchorBay\UpsellBay\Data\StatsRepository;

/**
 * Renders the default UpsellBay overview tab with analytics.
 *
 * @since 1.0.0
 */
final class DashboardPage {
	/**
	 * Overview summary service.
	 *
	 * @since 1.0.0
	 *
	 * @var OverviewSummary
	 */
	private OverviewSummary $summary;

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
	 * @param OverviewSummary $summary Overview summary service.
	 * @param StatsRepository $stats   Stats repository.
	 */
	public function __construct( OverviewSummary $summary, StatsRepository $stats ) {
		$this->summary = $summary;
		$this->stats   = $stats;
	}

	/**
	 * Render dashboard content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function render( array $request = array() ): void {
		$data = $this->summary->data();

		if ( 0 === (int) $data['active_offers'] ) {
			echo '<div class="upsellbay-onboarding-panel" style="background: #fff; border: 1px solid #c3c4c7; padding: 30px; text-align: center; margin-bottom: 20px; border-radius: 4px;">';
			echo '<h2 style="margin-top: 0;">' . esc_html__( 'Welcome to UpsellBay!', 'upsellbay' ) . '</h2>';
			echo '<p style="font-size: 16px; color: #50575e; max-width: 600px; margin: 0 auto 20px;">' . esc_html__( 'Increase your average order value (AOV) instantly by creating your first highly-targeted upsell offer.', 'upsellbay' ) . '</p>';
			echo '<a href="' . esc_url( 'admin.php?page=upsellbay&tab=offers&action=add' ) . '" class="button button-primary button-hero">' . esc_html__( 'Create your first offer', 'upsellbay' ) . '</a>';
			echo '</div>';
		}

		echo '<div class="upsellbay-overview-header">';
		echo '<h3 class="upsellbay-overview-title">' . esc_html__( 'Store offer status', 'upsellbay' ) . '</h3>';
		echo '</div>';
		echo '<div class="upsellbay-card-grid upsellbay-card-grid--metrics">';
		$this->summary_item( __( 'Offers enabled', 'upsellbay' ), true === $data['enabled'] ? __( 'Yes', 'upsellbay' ) : __( 'No', 'upsellbay' ), __( 'Whether live eligible offers are allowed to render on enabled placements.', 'upsellbay' ) );
		$this->summary_item( __( 'Test mode', 'upsellbay' ), true === $data['test_mode'] ? __( 'On', 'upsellbay' ) : __( 'Off', 'upsellbay' ), __( 'Admin-only preview mode for checking offers before shoppers see them.', 'upsellbay' ) );
		$this->summary_item( __( 'Active offers', 'upsellbay' ), (string) $data['active_offers'], __( 'Published offers currently marked active in UpsellBay.', 'upsellbay' ) );
		$this->summary_item( __( 'Recent revenue', 'upsellbay' ), $this->format_currency( $data['recent_revenue'] ), __( 'Attributed offer revenue from the recent aggregate stats window.', 'upsellbay' ) );
		echo '</div>';

		$this->render_analytics( $request );
	}

	/**
	 * Render the analytics performance section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	private function render_analytics( array $request = array() ): void {
		$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$range       = $this->selected_range( $request );
		$summary     = $this->analytics_summary( gmdate( 'Y-m-d', time() - $day_seconds * $range ), gmdate( 'Y-m-d' ) );

		echo '<div class="upsellbay-overview-header">';
		/* translators: %d: number of days in the selected analytics range. */
		echo '<h3 class="upsellbay-overview-title">' . esc_html( sprintf( __( 'Performance (Last %d days)', 'upsellbay' ), $range ) ) . '</h3>';
		echo '<div class="upsellbay-button-group" role="group" aria-label="' . esc_attr__( 'Performance date range', 'upsellbay' ) . '">';
		foreach ( $this->range_options() as $days => $label ) {
			$is_selected = $range === $days;
			$url         = 'admin.php?page=upsellbay&tab=dashboard&range=' . $days;
			echo '<a href="' . esc_url( $url ) . '" class="button' . ( $is_selected ? ' button-primary' : '' ) . '"' . ( $is_selected ? ' aria-current="true"' : '' ) . '>';
			echo esc_html( $label );
			echo '</a>';
		}
		echo '</div>';
		echo '</div>';
		echo '<div class="upsellbay-card-grid upsellbay-card-grid--metrics">';
		$this->summary_item( __( 'Views', 'upsellbay' ), (string) $summary['views'], __( 'Offer render events recorded in aggregate stats.', 'upsellbay' ) );
		$this->summary_item( __( 'Accepts', 'upsellbay' ), (string) $summary['accepts'], __( 'Accepted offers recorded in aggregate stats.', 'upsellbay' ) );
		$this->summary_item( __( 'Accept rate', 'upsellbay' ), (string) $summary['accept_rate'] . '%', __( 'Accepted offers divided by offer views for this period.', 'upsellbay' ) );
		$this->summary_item( __( 'Attributed revenue', 'upsellbay' ), $this->format_currency( $summary['revenue'] ), __( 'Revenue attributed to accepted offers in aggregate stats.', 'upsellbay' ) );
		echo '</div>';
		echo '<table class="widefat striped upsellbay-analytics-table"><tbody>';
		foreach (
			array(
				'dismissals'     => __( 'Dismissals', 'upsellbay' ),
				'orders'         => __( 'Orders', 'upsellbay' ),
				'discount_total' => __( 'Discount total', 'upsellbay' ),
				'aov_lift'       => __( 'Revenue per attributed order', 'upsellbay' ),
			) as $key => $label
		) {
			$val = in_array( $key, array( 'discount_total', 'aov_lift' ), true ) ? $this->format_currency( $summary[ $key ] ) : (string) $summary[ $key ];
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Return analytics summary with computed fields.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $start_date Start date.
	 * @param string               $end_date   End date.
	 * @param array<string, mixed> $filters    Filters.
	 * @return array<string, int|string>
	 */
	private function analytics_summary( string $start_date, string $end_date, array $filters = array() ): array {
		$summary                = $this->stats->summary( $start_date, $end_date, $filters );
		$summary['accept_rate'] = $summary['views'] > 0 ? number_format( ( (int) $summary['accepts'] / (int) $summary['views'] ) * 100, 2, '.', '' ) : '0.00';
		$summary['aov_lift']    = (int) $summary['orders'] > 0 ? (float) $summary['revenue'] / (int) $summary['orders'] : 0.00;

		return $summary;
	}

	/**
	 * Return supported dashboard date ranges.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	private function range_options(): array {
		return array(
			7  => __( 'Last 7 days', 'upsellbay' ),
			30 => __( 'Last 30 days', 'upsellbay' ),
			90 => __( 'Last 90 days', 'upsellbay' ),
		);
	}

	/**
	 * Return the selected analytics range.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	private function selected_range( array $request ): int {
		$range = (int) ( $request['range'] ?? 30 );

		return array_key_exists( $range, $this->range_options() ) ? $range : 30;
	}

	/**
	 * Render one dashboard metric.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label Metric label.
	 * @param string $value Metric value.
	 * @param string $help  Optional help tip text.
	 */
	private function summary_item( string $label, string $value, string $help = '' ): void {
		echo '<div class="upsellbay-metric-card">';
		if ( '' !== $help ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help tip markup.
			echo '<span class="upsellbay-metric-card__help">' . $this->help_tip( $help ) . '</span>';
		}
		echo '<span class="upsellbay-metric-card__value">' . esc_html( $value ) . '</span>';
		echo '<span class="upsellbay-metric-card__label">' . esc_html( $label ) . '</span>';
		echo '</div>';
	}

	/**
	 * Render a WooCommerce help tip when available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Tip text.
	 * @return string Help tip markup.
	 */
	private function help_tip( string $text ): string {
		if ( function_exists( 'wc_help_tip' ) ) {
			return wc_help_tip( $text, false );
		}

		return '<span class="description">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Format currency value.
	 *
	 * @param mixed $value Value to format.
	 * @return string
	 */
	private function format_currency( $value ): string {
		$float_val = (float) $value;
		$symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		return $symbol . number_format_i18n( $float_val, 2 );
	}
}
