<?php
/**
 * Dashboard overview admin tab.
 *
 * @package UpsellBay\Admin\Dashboard
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Dashboard;

use WPAnchorBay\UpsellBay\Admin\OverviewSummary;

/**
 * Renders the default UpsellBay overview tab.
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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OverviewSummary $summary Overview summary service.
	 */
	public function __construct( OverviewSummary $summary ) {
		$this->summary = $summary;
	}

	/**
	 * Render dashboard content.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		$data = $this->summary->data();

		echo '<h2>' . esc_html__( 'Dashboard / Overview', 'upsellbay' ) . '</h2>';
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
