<?php
/**
 * Registers the Pro offer types on the shared host offer editor.
 *
 * @package UpsellBayPro\Integrations
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The free host plugin ships and renders only the checkout bump, and exposes
 * exactly that one offer type through its shared offer editor
 * (`OfferSchema::offer_types()`, filtered via `upsellbay_offer_types`). Pro
 * renders the product-page, cart, and thank-you placements, so it adds those
 * three types to the host's filtered list — making them selectable and
 * saveable in the shared editor only while Pro is active.
 *
 * @since 1.0.0
 */
final class OfferTypesIntegration {
	/**
	 * Offer types Pro adds to the host's selectable set.
	 *
	 * @var array<int, string>
	 */
	private const PRO_OFFER_TYPES = array( 'product_upsell', 'cart_crosssell', 'thankyou_offer' );

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter( 'upsellbay_offer_types', array( $this, 'add_pro_offer_types' ) );
	}

	/**
	 * Add the Pro offer types to the host's available set.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $types Host-provided offer type keys.
	 * @return array<int, string>
	 */
	public function add_pro_offer_types( $types ): array {
		$types = is_array( $types ) ? array_values( array_map( 'strval', $types ) ) : array();

		foreach ( self::PRO_OFFER_TYPES as $type ) {
			if ( ! in_array( $type, $types, true ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}
}
