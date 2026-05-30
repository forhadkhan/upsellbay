<?php
/**
 * Offer prioritizer.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

use WPAnchorBay\UpsellBay\Domain\Rules\RuleEvaluator;

/**
 * Selects eligible offers for a storefront placement.
 *
 * @since 1.0.0
 */
final class OfferPrioritizer {
	/**
	 * Rule evaluator.
	 *
	 * @var RuleEvaluator
	 */
	private RuleEvaluator $rules;

	/**
	 * Product availability callback.
	 *
	 * @var callable(int): bool
	 */
	private $product_available;

	/**
	 * Clock callback.
	 *
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param RuleEvaluator $rules             Rule evaluator.
	 * @param callable|null $product_available Product availability callback.
	 * @param callable|null $clock             Clock callback.
	 */
	public function __construct( RuleEvaluator $rules, ?callable $product_available = null, ?callable $clock = null ) {
		$this->rules             = $rules;
		$this->product_available = $product_available ?? array( $this, 'is_product_available' );
		$this->clock             = $clock ?? static fn (): int => time();
	}

	/**
	 * Select eligible offers for a placement.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $offers    Offers.
	 * @param string                           $placement Placement key.
	 * @param array<string, mixed>             $context   Evaluation context.
	 * @param int                              $limit     Max offers.
	 * @return array<int, array<string, mixed>>
	 */
	public function select( array $offers, string $placement, array $context = array(), int $limit = 1 ): array {
		$eligible = array_values(
			array_filter(
				$offers,
				fn ( array $offer ): bool => $this->is_eligible( $offer, $placement, $context )
			)
		);

		usort(
			$eligible,
			static function ( array $a, array $b ): int {
				$a_meta = is_array( $a['meta'] ?? null ) ? $a['meta'] : array();
				$b_meta = is_array( $b['meta'] ?? null ) ? $b['meta'] : array();

				$priority_compare = (int) ( $a_meta['_ub_priority'] ?? 0 ) <=> (int) ( $b_meta['_ub_priority'] ?? 0 );
				if ( 0 !== $priority_compare ) {
					return $priority_compare;
				}

				return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
			}
		);

		return array_slice( $eligible, 0, max( 0, $limit ) );
	}

	/**
	 * Determine whether an offer is currently eligible.
	 *
	 * @param array<string, mixed> $offer     Offer.
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Evaluation context.
	 */
	private function is_eligible( array $offer, string $placement, array $context ): bool {
		$meta = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();

		if ( ( $meta['_ub_offer_type'] ?? '' ) !== $placement || 'active' !== ( $meta['_ub_status'] ?? '' ) ) {
			return false;
		}

		$offer_id    = (int) ( $offer['id'] ?? 0 );
		$dismissed   = is_array( $context['dismissed_offer_ids'] ?? null ) ? array_map( 'intval', $context['dismissed_offer_ids'] ) : array();
		$product_id  = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$start_at    = $this->datetime_to_timestamp( $meta['_ub_start_at'] ?? null );
		$end_at      = $this->datetime_to_timestamp( $meta['_ub_end_at'] ?? null );
		$current     = ( $this->clock )();
		$rules       = is_array( $meta['_ub_rules'] ?? null ) ? $meta['_ub_rules'] : array();
		$rules_match = (string) ( $meta['_ub_rules_match'] ?? 'all' );

		return ! in_array( $offer_id, $dismissed, true )
			&& ( null === $start_at || $start_at <= $current )
			&& ( null === $end_at || $end_at >= $current )
			&& $product_id > 0
			&& ( $this->product_available )( $product_id )
			&& $this->rules->matches( $rules, $rules_match, $context );
	}

	/**
	 * Convert a date string to a timestamp.
	 *
	 * @param mixed $value Date value.
	 */
	private function datetime_to_timestamp( $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? null : $timestamp;
	}

	/**
	 * Default product availability adapter.
	 *
	 * @param int $product_id Product ID.
	 */
	private function is_product_available( int $product_id ): bool {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return true;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		return ( ! method_exists( $product, 'is_purchasable' ) || $product->is_purchasable() )
			&& ( ! method_exists( $product, 'is_in_stock' ) || $product->is_in_stock() );
	}
}
