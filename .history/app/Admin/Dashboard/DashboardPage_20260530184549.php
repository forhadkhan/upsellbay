<?php
/**
 * Dashboard overview admin tab.
 *
 * @package UpsellBay\Admin\Dashboard
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Dashboard;

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
	 */
	public function render(): void {
		$data = $this->summary->data();

		echo '<h2>' . esc_html__( 'Dashboard', 'upsellbay' ) . '</h2>';
		echo '<div class="upsellbay-summary upsellbay-summary--dashboard">';
		$this->summary_item( __( 'Offers enabled', 'upsellbay' ), true === $data['enabled'] ? __( 'Yes', 'upsellbay' ) : __( 'No', 'upsellbay' ) );
		$this->summary_item( __( 'Test mode', 'upsellbay' ), true === $data['test_mode'] ? __( 'On', 'upsellbay' ) : __( 'Off', 'upsellbay' ) );
		$this->summary_item( __( 'Active offers', 'upsellbay' ), (string) $data['active_offers'] );
		$this->summary_item( __( 'Recent revenue', 'upsellbay' ), (string) $data['recent_revenue'] );
		echo '</div>';
		echo '<table class="widefat striped upsellbay-dashboard-actions"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Next action', 'upsellbay' ) . '</th><td><a class="button button-primary" href="' . esc_url( 'admin.php?page=upsellbay&tab=offers&action=edit' ) . '">' . esc_html__( 'Add offer', 'upsellbay' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( 'admin.php?page=upsellbay&tab=settings' ) . '">' . esc_html__( 'Review settings', 'upsellbay' ) . '</a></td></tr>';
		echo '</tbody></table>';

		$this->render_analytics();
	}

	/**
	 * Render the analytics performance section.
	 *
	 * @since 1.0.0
	 */
	private function render_analytics(): void {
		$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$summary     = $this->analytics_summary( gmdate( 'Y-m-d', time() - $day_seconds * 30 ), gmdate( 'Y-m-d' ) );

		echo '<h3>' . esc_html__( 'Performance (Last 30 days)', 'upsellbay' ) . '</h3>';
		echo '<div class="upsellbay-summary upsellbay-summary--analytics">';
		$this->summary_item( __( 'Views', 'upsellbay' ), (string) $summary['views'] );
		$this->summary_item( __( 'Accepts', 'upsellbay' ), (string) $summary['accepts'] );
		$this->summary_item( __( 'Accept rate', 'upsellbay' ), (string) $summary['accept_rate'] . '%' );
		$this->summary_item( __( 'Attributed revenue', 'upsellbay' ), (string) $summary['revenue'] );
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
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $summary[ $key ] ) . '</td></tr>';
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
		$summary['aov_lift']    = (int) $summary['orders'] > 0 ? number_format( (float) $summary['revenue'] / (int) $summary['orders'], 6, '.', '' ) : '0.000000';

		return $summary;
	}

	/**
	 * Render one dashboard metric.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label Metric label.
	 * @param string $value Metric value.
	 */
	private function summary_item( string $label, string $value ): void {
		echo '<div class="upsellbay-summary__item"><span class="upsellbay-summary__label">' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}
}
