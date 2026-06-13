<?php
/**
 * Offer conflict detector.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

use WPAnchorBay\UpsellBay\Data\OfferRepository;

/**
 * Detects conflicts between offers based on placement crowding and funnel overlap.
 *
 * @since 1.0.0
 */
final class OfferConflictDetector {
	/**
	 * Offer repository.
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository $repository Offer repository.
	 */
	public function __construct( OfferRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Detect conflicts for a specific offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id The ID of the offer being checked.
	 * @param array<string, mixed> $meta     The offer meta data.
	 * @return array<int, string> Array of conflict warning messages.
	 */
	public function detect( int $offer_id, array $meta ): array {
		if ( true === ( $meta['_ub_conflict_override'] ?? false ) ) {
			return array(); // Merchant explicitly bypassed conflicts.
		}

		if ( 'active' !== ( $meta['_ub_status'] ?? 'draft' ) ) {
			return array(); // Only active offers cause live conflicts.
		}

		$warnings     = array();
		$placement    = $meta['_ub_offer_type'] ?? '';
		$goal         = $meta['_ub_offer_goal'] ?? '';
		$product_ids  = $meta['_ub_trigger_product_ids'] ?? array();
		$category_ids = $meta['_ub_trigger_category_ids'] ?? array();

		// Fetch all other active offers for comparison
		$active_offers = $this->repository->query(
			array(
				'status' => 'active',
				'limit'  => 100,
			)
		);

		$placement_count = 1; // Count self
		$funnel_overlaps = array();

		foreach ( $active_offers as $other_offer ) {
			$other_id = (int) $other_offer['id'];
			if ( $other_id === $offer_id ) {
				continue;
			}

			$other_meta      = $other_offer['meta'] ?? array();
			$other_placement = $other_meta['_ub_offer_type'] ?? '';

			if ( $placement === $other_placement ) {
				++$placement_count;

				$other_goal         = $other_meta['_ub_offer_goal'] ?? '';
				$other_product_ids  = $other_meta['_ub_trigger_product_ids'] ?? array();
				$other_category_ids = $other_meta['_ub_trigger_category_ids'] ?? array();

				// Funnel overlap: Same placement AND same goal AND overlapping triggers
				if ( $goal === $other_goal ) {
					$has_overlap = false;

					// Global triggers (empty trigger means all products)
					if ( empty( $product_ids ) && empty( $category_ids ) && empty( $other_product_ids ) && empty( $other_category_ids ) ) {
						$has_overlap = true;
					}

					// Product overlaps
					if ( ! empty( $product_ids ) && ! empty( $other_product_ids ) && count( array_intersect( $product_ids, $other_product_ids ) ) > 0 ) {
						$has_overlap = true;
					}

					// Category overlaps
					if ( ! empty( $category_ids ) && ! empty( $other_category_ids ) && count( array_intersect( $category_ids, $other_category_ids ) ) > 0 ) {
						$has_overlap = true;
					}

					if ( $has_overlap ) {
						$funnel_overlaps[] = $other_offer['title'] ?? sprintf( __( 'Offer #%d', 'upsellbay' ), $other_id );
					}
				}
			}
		}

		// Placement crowding
		if ( $placement_count > 3 ) {
			/* translators: %d: count */
			$warnings[] = sprintf( __( 'Placement crowding: There are %d active offers assigned to this placement. High numbers of offers may reduce conversion.', 'upsellbay' ), $placement_count );
		}

		// Funnel overlap
		if ( count( $funnel_overlaps ) > 0 ) {
			$warnings[] = sprintf(
				/* translators: %s: Comma-separated offer titles */
				__( 'Funnel overlap: This offer competes directly with %s. They share the same placement, goal, and trigger conditions. Only one may show to the shopper.', 'upsellbay' ),
				implode( ', ', $funnel_overlaps )
			);
		}

		return $warnings;
	}
}
