<?php
/**
 * Cart item discount applier.
 *
 * @package UpsellBay\Domain\Discounts
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\Discounts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Sets the unit price of an accepted offer line.
 *
 * An accepted offer discounts exactly one unit. WooCommerce prices a line as
 * unit price x quantity, so when the shopper raises the line's quantity (cart
 * page, Store API update-item, or any other path) the unit price set here is
 * the blend that keeps one unit at the offer price and the rest at the
 * product's regular price. The free host's applier uses the same arithmetic,
 * so both plugins land on the same line total.
 *
 * @since 1.0.0
 */
final class DiscountApplier {
	/**
	 * Price decimals provider.
	 *
	 * @var callable(): int
	 */
	private $decimals;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $decimals Returns the store's price decimals.
	 */
	public function __construct( ?callable $decimals = null ) {
		$this->decimals = $decimals ?? static fn (): int => function_exists( 'wc_get_price_decimals' )
			? (int) wc_get_price_decimals()
			: 2;
	}

	/**
	 * Price an offer line.
	 *
	 * With an offer price, one unit is charged at it and the rest at the regular
	 * price. Without one (the offer no longer qualifies) the line goes back to
	 * the regular price, overriding any earlier writer on the same totals run.
	 * Subscription products are never touched: a discounted first price would
	 * carry into every renewal.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $cart_item     Cart item.
	 * @param string|null          $offer_price   Offer price for one unit, or null for no discount.
	 * @param string               $regular_price Current regular price for one unit.
	 */
	public function apply( array $cart_item, ?string $offer_price, string $regular_price ): void {
		$product = $cart_item['data'] ?? null;
		if ( ! is_object( $product ) || ! method_exists( $product, 'set_price' ) ) {
			return;
		}

		if ( class_exists( '\WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
			return;
		}

		if ( null === $offer_price ) {
			$product->set_price( $regular_price );
			return;
		}

		$quantity = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
		$product->set_price( $this->unit_price( $offer_price, $regular_price, $quantity ) );
	}

	/**
	 * Return the unit price that discounts exactly one of `$quantity` units.
	 *
	 * Works in the store's minor units so the line total (unit x quantity, as
	 * WooCommerce computes it) lands exactly on offer + (quantity - 1) x regular
	 * once rounded to the store's decimals. When the line total does not divide
	 * evenly, the unit price carries extra decimals; WooCommerce keeps them
	 * until it rounds the line.
	 *
	 * @since 1.0.0
	 *
	 * @param string $offer_price   Offer price for one unit.
	 * @param string $regular_price Regular price for one unit.
	 * @param int    $quantity      Line quantity.
	 */
	public function unit_price( string $offer_price, string $regular_price, int $quantity ): string {
		if ( $quantity <= 1 ) {
			return $offer_price;
		}

		$decimals      = max( 0, ( $this->decimals )() );
		$scale         = 10 ** $decimals;
		$offer_minor   = (int) round( (float) $offer_price * $scale );
		$regular_minor = (int) round( (float) $regular_price * $scale );
		$line_minor    = $offer_minor + ( $quantity - 1 ) * $regular_minor;

		if ( 0 === $line_minor % $quantity ) {
			return number_format( intdiv( $line_minor, $quantity ) / $scale, $decimals, '.', '' );
		}

		// Ten extra decimals keep unit x quantity within half a minor unit of
		// the exact line total for any realistic quantity.
		return number_format( $line_minor / $quantity / $scale, $decimals + 10, '.', '' );
	}
}
