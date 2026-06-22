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
		$badge_class  = match ( $report['status'] ) {
			'blocked' => 'upsellbay-offer-visibility-panel__badge--blocked',
			'warning' => 'upsellbay-offer-visibility-panel__badge--warning',
			default => 'upsellbay-offer-visibility-panel__badge--success',
		};
		$badge_label  = match ( $report['status'] ) {
			'blocked' => __( 'Blocked', 'upsellbay' ),
			'warning' => __( 'Issues found', 'upsellbay' ),
			default => __( 'All clear', 'upsellbay' ),
		};

		echo '<details id="upsellbay-offer-visibility-panel" class="postbox upsellbay-offer-visibility-panel">';
		echo '<summary class="upsellbay-offer-visibility-panel__summary">';
		echo '<span>' . esc_html__( 'Visibility Inspector', 'upsellbay' ) . '</span>';
		echo '<span class="upsellbay-offer-visibility-panel__badge ' . esc_attr( $badge_class ) . '">' . esc_html( $badge_label ) . '</span>';
		echo '</summary>';
		echo '<div class="inside">';
		echo '<div class="notice inline ' . esc_attr( $notice_class ) . '"><p>' . esc_html( $report['summary'] ) . '</p></div>';
		echo '<p><strong>' . esc_html__( 'Preview readiness:', 'upsellbay' ) . '</strong> ' . esc_html( $report['preview']['message'] ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'upsellbay' ) . '</th><th>' . esc_html__( 'Status', 'upsellbay' ) . '</th><th>' . esc_html__( 'Details', 'upsellbay' ) . '</th></tr></thead><tbody>';
		foreach ( $report['checks'] as $check ) {
			$status_class = $this->status_badge_class( (string) ( $check['status'] ?? '' ) );
			$status_label = $this->status_badge_label( (string) ( $check['status'] ?? '' ) );

			echo '<tr>';
			echo '<td>' . esc_html( $check['label'] ) . '</td>';
			echo '<td><span class="upsellbay-offer-visibility-panel__row-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
			echo '<td>' . esc_html( $check['message'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</details>';
	}

	/**
	 * Return the row badge class for a check status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Check status.
	 * @return string
	 */
	private function status_badge_class( string $status ): string {
		return match ( $status ) {
			'fail' => 'upsellbay-offer-visibility-panel__row-badge--blocked',
			'warn' => 'upsellbay-offer-visibility-panel__row-badge--warning',
			default => 'upsellbay-offer-visibility-panel__row-badge--success',
		};
	}

	/**
	 * Return the row badge label for a check status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Check status.
	 * @return string
	 */
	private function status_badge_label( string $status ): string {
		return match ( $status ) {
			'fail' => __( 'Fail', 'upsellbay' ),
			'warn' => __( 'Warn', 'upsellbay' ),
			default => __( 'Pass', 'upsellbay' ),
		};
	}
}
