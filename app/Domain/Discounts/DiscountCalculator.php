<?php
/**
 * Offer discount calculator.
 *
 * @package UpsellBay\Domain\Discounts
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Discounts;

/**
 * Calculates server-side offer prices from current product prices.
 *
 * @since 1.0.0
 */
final class DiscountCalculator {
	/**
	 * Calculate an offer price.
	 *
	 * @since 1.0.0
	 *
	 * @param string|float|int     $product_price Current product price.
	 * @param array<string, mixed> $meta          Offer meta.
	 * @return array<string, string>|null
	 */
	public function calculate( $product_price, array $meta ): ?array {
		$original = (float) $product_price;
		if ( $original < 0 ) {
			return null;
		}

		$type  = (string) ( $meta['_ub_discount_type'] ?? 'none' );
		$value = max( 0.0, (float) ( $meta['_ub_discount_value'] ?? 0 ) );

		$offer_price = match ( $type ) {
			'none'         => $original,
			'percent'      => $original - ( $original * min( 100.0, $value ) / 100 ),
			'fixed_amount' => $original - $value,
			'fixed_price'  => $value,
			default        => null,
		};

		if ( null === $offer_price ) {
			return null;
		}

		$offer_price     = max( 0.0, $offer_price );
		$discount_amount = max( 0.0, $original - $offer_price );

		return array(
			'original_price'  => $this->format_decimal( $original ),
			'offer_price'     => $this->format_decimal( $offer_price ),
			'discount_amount' => $this->format_decimal( $discount_amount ),
			'discount_type'   => $type,
		);
	}

	/**
	 * Format a decimal with WooCommerce helpers when available.
	 *
	 * @param float $value Value.
	 */
	private function format_decimal( float $value ): string {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return (string) wc_format_decimal( $value, 6 );
		}

		return number_format( $value, 6, '.', '' );
	}
}
