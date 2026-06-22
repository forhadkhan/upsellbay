<?php
/**
 * Offer visibility panel.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBay\Domain\Offers\OfferVisibilityInspector;

/**
 * Renders merchant-facing offer visibility diagnostics.
 *
 * @since 1.0.0
 */
final class OfferVisibilityPanel {
	/**
	 * Visibility inspector.
	 *
	 * @var OfferVisibilityInspector
	 */
	private OfferVisibilityInspector $inspector;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferVisibilityInspector $inspector Visibility inspector.
	 */
	public function __construct( OfferVisibilityInspector $inspector ) {
		$this->inspector = $inspector;
	}

	/**
	 * Render the panel for an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer Offer payload.
	 */
	public function render( array $offer ): void {
		$report       = $this->inspector->inspect( $offer );
		$notice_class = match ( $report['status'] ) {
			'blocked' => 'notice-error',
			'warning' => 'notice-warning',
			default => 'notice-success',
		};

		echo '<div id="upsellbay-offer-visibility-panel" class="postbox upsellbay-offer-visibility-panel">';
		echo '<h2 style="padding-left: 12px;"><span>' . esc_html__( 'Visibility Inspector', 'upsellbay' ) . '</span></h2>';
		echo '<div class="inside">';
		echo '<div class="notice inline ' . esc_attr( $notice_class ) . '"><p>' . esc_html( $report['summary'] ) . '</p></div>';
		echo '<p><strong>' . esc_html__( 'Preview readiness:', 'upsellbay' ) . '</strong> ' . esc_html( $report['preview']['message'] ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'upsellbay' ) . '</th><th>' . esc_html__( 'Status', 'upsellbay' ) . '</th><th>' . esc_html__( 'Details', 'upsellbay' ) . '</th></tr></thead><tbody>';
		foreach ( $report['checks'] as $check ) {
			echo '<tr>';
			echo '<td>' . esc_html( $check['label'] ) . '</td>';
			echo '<td><strong>' . esc_html( ucfirst( $check['status'] ) ) . '</strong></td>';
			echo '<td>' . esc_html( $check['message'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</div>';
	}
}
