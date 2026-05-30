<?php
/**
 * Analytics admin page.
 *
 * @package UpsellBay\Admin\Analytics
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Analytics;

use WPAnchorBay\UpsellBay\Data\StatsRepository;

/**
 * Displays aggregate, non-PII offer performance.
 *
 * @since 1.0.0
 */
final class AnalyticsPage {
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
	 * @param StatsRepository $stats Stats repository.
	 */
	public function __construct( StatsRepository $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Return dashboard summary from aggregate stats only.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $start_date Start date.
	 * @param string               $end_date   End date.
	 * @param array<string, mixed> $filters    Filters.
	 * @return array<string, int|string>
	 */
	public function summary( string $start_date, string $end_date, array $filters = array() ): array {
		$summary                = $this->stats->summary( $start_date, $end_date, $filters );
		$summary['accept_rate'] = $summary['views'] > 0 ? number_format( ( (int) $summary['accepts'] / (int) $summary['views'] ) * 100, 2, '.', '' ) : '0.00';
		$summary['aov_lift']    = (int) $summary['orders'] > 0 ? number_format( (float) $summary['revenue'] / (int) $summary['orders'], 6, '.', '' ) : '0.000000';

		return $summary;
	}

	/**
	 * Return the zero-data analytics empty state.
	 *
	 * @since 1.0.0
	 *
	 * @return array{title: string, message: string, actions: array<int, array{label: string, url: string}>}
	 */
	public function empty_state(): array {
		return array(
			'title'   => __( 'No offer activity yet', 'upsellbay' ),
			'message' => __( 'Create your first offer and use test mode preview before shoppers see it.', 'upsellbay' ),
			'actions' => array(
				array(
					'label' => __( 'Create offer', 'upsellbay' ),
					'url'   => 'admin.php?page=upsellbay-add-offer',
				),
				array(
					'label' => __( 'Review test mode', 'upsellbay' ),
					'url'   => 'admin.php?page=upsellbay-settings',
				),
			),
		);
	}

	/**
	 * Render dashboard shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$summary     = $this->summary( gmdate( 'Y-m-d', time() - $day_seconds * 30 ), gmdate( 'Y-m-d' ) );

		echo '<div class="wrap woocommerce upsellbay-admin">';
		echo '<h1>' . esc_html__( 'UpsellBay Analytics', 'upsellbay' ) . '</h1>';
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
		echo '</tbody></table></div>';
	}

	/**
	 * Render one summary metric.
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
