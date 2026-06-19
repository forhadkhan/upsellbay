<?php
/**
 * Offer conflict detector.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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
		$priority     = (int) ( $meta['_ub_priority'] ?? 0 );

		// Fetch all other active offers for comparison.
		$active_offers = $this->repository->query(
			array(
				'status' => 'active',
				'limit'  => 100,
			)
		);

		$placement_count = 1; // Count self.
		$funnel_overlaps = array();

		foreach ( $active_offers as $other_offer ) {
			$other_id = (int) $other_offer['id'];
			if ( $other_id === $offer_id ) {
				continue;
			}

			$other_meta = $other_offer['meta'] ?? array();
			if ( 'active' !== ( $other_meta['_ub_status'] ?? 'draft' ) ) {
				continue;
			}

			$other_placement = $other_meta['_ub_offer_type'] ?? '';

			if ( $placement === $other_placement ) {
				++$placement_count;
				if ( array_key_exists( '_ub_priority', $meta ) && array_key_exists( '_ub_priority', $other_meta ) && $priority === (int) $other_meta['_ub_priority'] ) {
					$warnings[] = sprintf(
						/* translators: %s: offer title */
						__( 'Priority tie: This offer has the same priority as %s. Lower priority numbers win, and matching numbers fall back to offer ID.', 'upsellbay' ),
						(string) ( $other_offer['title'] ?? $this->fallback_title( $other_id ) )
					);
				}

				$other_goal         = $other_meta['_ub_offer_goal'] ?? '';
				$other_product_ids  = $other_meta['_ub_trigger_product_ids'] ?? array();
				$other_category_ids = $other_meta['_ub_trigger_category_ids'] ?? array();

				// Funnel overlap: Same placement, same goal, and overlapping triggers.
				if ( $goal === $other_goal && $this->schedules_overlap( $meta, $other_meta ) && $this->targeting_overlaps( $product_ids, $category_ids, $meta['_ub_rules'] ?? array(), $other_product_ids, $other_category_ids, $other_meta['_ub_rules'] ?? array() ) ) {
					$funnel_overlaps[] = $other_offer['title'] ?? $this->fallback_title( $other_id );
				}
			}
		}

		// Placement crowding.
		if ( $placement_count > 3 ) {
			/* translators: %d: count */
			$warnings[] = sprintf( __( 'Placement crowding: There are %d active offers assigned to this placement. High numbers of offers may reduce conversion.', 'upsellbay' ), $placement_count );
		}

		// Funnel overlap.
		if ( count( $funnel_overlaps ) > 0 ) {
			$warnings[] = sprintf(
				/* translators: %s: Comma-separated offer titles */
				__( 'Funnel overlap: This offer competes directly with %s. They share the same placement, goal, and trigger conditions. Only one may show to the shopper.', 'upsellbay' ),
				implode( ', ', $funnel_overlaps )
			);
		}

		return $warnings;
	}

	/**
	 * Determine whether two offer schedules can be active at the same time.
	 *
	 * @param array<string, mixed> $current Current offer meta.
	 * @param array<string, mixed> $other Other offer meta.
	 */
	private function schedules_overlap( array $current, array $other ): bool {
		$current_start = $this->timestamp( $current['_ub_start_at'] ?? null ) ?? PHP_INT_MIN;
		$current_end   = $this->timestamp( $current['_ub_end_at'] ?? null ) ?? PHP_INT_MAX;
		$other_start   = $this->timestamp( $other['_ub_start_at'] ?? null ) ?? PHP_INT_MIN;
		$other_end     = $this->timestamp( $other['_ub_end_at'] ?? null ) ?? PHP_INT_MAX;

		return $current_start <= $other_end && $other_start <= $current_end;
	}

	/**
	 * Build a fallback offer title.
	 *
	 * @param int $offer_id Offer ID.
	 */
	private function fallback_title( int $offer_id ): string {
		return sprintf(
			/* translators: %d: offer ID */
			__( 'Offer #%d', 'upsellbay' ),
			$offer_id
		);
	}

	/**
	 * Convert a date value into a timestamp.
	 *
	 * @param mixed $value Date value.
	 */
	private function timestamp( $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? null : $timestamp;
	}

	/**
	 * Determine whether two offer target definitions overlap.
	 *
	 * @param mixed $product_ids Current product IDs.
	 * @param mixed $category_ids Current category IDs.
	 * @param mixed $rules Current rules.
	 * @param mixed $other_product_ids Other product IDs.
	 * @param mixed $other_category_ids Other category IDs.
	 * @param mixed $other_rules Other rules.
	 */
	private function targeting_overlaps( $product_ids, $category_ids, $rules, $other_product_ids, $other_category_ids, $other_rules ): bool {
		$product_ids        = $this->int_list( $product_ids );
		$category_ids       = $this->int_list( $category_ids );
		$other_product_ids  = $this->int_list( $other_product_ids );
		$other_category_ids = $this->int_list( $other_category_ids );
		$rules              = is_array( $rules ) ? $rules : array();
		$other_rules        = is_array( $other_rules ) ? $other_rules : array();
		$current_global     = array() === $product_ids && array() === $category_ids && array() === $rules;
		$other_global       = array() === $other_product_ids && array() === $other_category_ids && array() === $other_rules;

		if ( $current_global || $other_global ) {
			return true;
		}

		if ( array() !== array_intersect( $product_ids, $other_product_ids ) || array() !== array_intersect( $category_ids, $other_category_ids ) ) {
			return true;
		}

		return array() !== $rules && $this->normalized_rules_key( $rules ) === $this->normalized_rules_key( $other_rules );
	}

	/**
	 * Normalize integer list values.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, int>
	 */
	private function int_list( $value ): array {
		$values = is_array( $value ) ? $value : array( $value );

		return array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $values ),
					static fn ( int $item ): bool => $item > 0
				)
			)
		);
	}

	/**
	 * Build a stable rule comparison key.
	 *
	 * @param array<int, mixed> $rules Rules.
	 */
	private function normalized_rules_key( array $rules ): string {
		array_walk(
			$rules,
			static function ( &$rule ): void {
				if ( is_array( $rule ) ) {
					ksort( $rule );
				}
			}
		);
		usort( $rules, static fn ( $a, $b ): int => wp_json_encode( $a ) <=> wp_json_encode( $b ) );

		return (string) wp_json_encode( $rules );
	}
}
