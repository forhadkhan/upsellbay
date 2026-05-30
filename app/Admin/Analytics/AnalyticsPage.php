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
		echo '<div class="wrap woocommerce"><h1>' . esc_html__( 'UpsellBay Analytics', 'upsellbay' ) . '</h1></div>';
	}
}
