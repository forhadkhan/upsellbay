<?php
/**
 * Renders Pro-only offer editor diagnostics inside the host plugin's edit page.
 *
 * @package UpsellBay\Integrations
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Admin\Offers\OfferVisibilityPanel;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferConflictDetector;

/**
 * Attaches to the host's upsellbay_offer_edit_extra_sections hook.
 *
 * @since 1.0.0
 */
final class OfferEditDiagnosticsIntegration {
	/**
	 * Offer conflict detector.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferConflictDetector
	 */
	private OfferConflictDetector $conflict_detector;

	/**
	 * Offer visibility panel.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferVisibilityPanel
	 */
	private OfferVisibilityPanel $visibility_panel;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferConflictDetector $conflict_detector Offer conflict detector.
	 * @param OfferVisibilityPanel  $visibility_panel  Offer visibility panel.
	 */
	public function __construct( OfferConflictDetector $conflict_detector, OfferVisibilityPanel $visibility_panel ) {
		$this->conflict_detector = $conflict_detector;
		$this->visibility_panel  = $visibility_panel;
	}

	/**
	 * Attach to the host's offer editor extension seam.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'upsellbay_offer_edit_extra_sections', array( $this, 'render' ), 10, 2 );
	}

	/**
	 * Render conflict warnings and the visibility inspector panel.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id Offer ID, or 0 for a new offer.
	 * @param array<string, mixed> $meta     Current offer meta.
	 */
	public function render( int $offer_id, array $meta ): void {
		if ( $offer_id <= 0 ) {
			return;
		}

		$this->visibility_panel->render(
			array(
				'id'   => $offer_id,
				'meta' => $meta,
			)
		);

		$findings = $this->conflict_detector->detect( $offer_id, $meta );
		foreach ( $findings as $finding ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( (string) $finding ) . '</p></div>';
		}
	}
}
