<?php
/**
 * Local product recommendation assistant.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

/**
 * Ranks explainable local product suggestions for optional merchant use.
 *
 * @since 1.0.0
 */
final class ProductRecommendationAssistant {
	/**
	 * Source callbacks.
	 *
	 * @var array<string, callable>
	 */
	private array $sources;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $upsells            Woo upsell source.
	 * @param callable|null $cross_sells        Woo cross-sell source.
	 * @param callable|null $category_products  Same-category source.
	 * @param callable|null $accepted_products  Accepted-offer source.
	 */
	public function __construct( ?callable $upsells = null, ?callable $cross_sells = null, ?callable $category_products = null, ?callable $accepted_products = null ) {
		$this->sources = array(
			'upsell'    => $upsells ?? static fn (): array => array(),
			'crosssell' => $cross_sells ?? static fn (): array => array(),
			'category'  => $category_products ?? static fn (): array => array(),
			'accepted'  => $accepted_products ?? static fn (): array => array(),
		);
	}

	/**
	 * Return ranked suggestions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<int, array{product_id: int, source: string, reason: string}>
	 */
	public function suggest( array $context ): array {
		$base_product_id = (int) ( $context['base_product_id'] ?? 0 );
		if ( $base_product_id <= 0 ) {
			return array();
		}

		$limit       = max( 1, (int) ( $context['limit'] ?? 5 ) );
		$category_id = (int) ( is_array( $context['category_ids'] ?? null ) ? ( $context['category_ids'][0] ?? 0 ) : 0 );
		$candidates  = array(
			array(
				'source' => 'upsell',
				'ids'    => ( $this->sources['upsell'] )( $base_product_id ),
				'reason' => __( 'WooCommerce upsell configured for this product.', 'upsellbay' ),
			),
			array(
				'source' => 'crosssell',
				'ids'    => ( $this->sources['crosssell'] )( $base_product_id ),
				'reason' => __( 'WooCommerce cross-sell configured for this product.', 'upsellbay' ),
			),
			array(
				'source' => 'category',
				'ids'    => $category_id > 0 ? ( $this->sources['category'] )( $category_id ) : array(),
				'reason' => __( 'Same-category product that can work as an add-on.', 'upsellbay' ),
			),
			array(
				'source' => 'accepted',
				'ids'    => ( $this->sources['accepted'] )(),
				'reason' => __( 'Previously accepted UpsellBay offer product.', 'upsellbay' ),
			),
		);

		$suggestions = array();
		$seen        = array( $base_product_id => true );

		foreach ( $candidates as $candidate ) {
			foreach ( is_array( $candidate['ids'] ) ? $candidate['ids'] : array() as $product_id ) {
				$product_id = (int) $product_id;
				if ( $product_id <= 0 || isset( $seen[ $product_id ] ) ) {
					continue;
				}

				$seen[ $product_id ] = true;
				$suggestions[]       = array(
					'product_id' => $product_id,
					'source'     => $candidate['source'],
					'reason'     => $candidate['reason'],
				);

				if ( count( $suggestions ) >= $limit ) {
					return $suggestions;
				}
			}
		}

		return $suggestions;
	}
}
