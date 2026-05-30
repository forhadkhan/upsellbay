<?php
/**
 * Offer meta schema.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

/**
 * Defines supported offer types, placements, and meta defaults.
 *
 * @since 1.0.0
 */
final class OfferSchema {
	public const TYPE_CHECKOUT_BUMP  = 'checkout_bump';
	public const TYPE_PRODUCT_UPSELL = 'product_upsell';
	public const TYPE_CART_CROSSSELL = 'cart_crosssell';
	public const TYPE_THANKYOU_OFFER = 'thankyou_offer';

	/**
	 * Return schema defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'_ub_offer_type'           => self::TYPE_CHECKOUT_BUMP,
			'_ub_status'               => 'draft',
			'_ub_offer_product_id'     => 0,
			'_ub_trigger_product_ids'  => array(),
			'_ub_trigger_category_ids' => array(),
			'_ub_discount_type'        => 'none',
			'_ub_discount_value'       => '0.000000',
			'_ub_headline'             => '',
			'_ub_body'                 => '',
			'_ub_button_text'          => '',
			'_ub_rules'                => array(),
			'_ub_rules_match'          => 'all',
			'_ub_placement_config'     => array(),
			'_ub_show_image'           => true,
			'_ub_start_at'             => null,
			'_ub_end_at'               => null,
			'_ub_priority'             => 0,
		);
	}

	/**
	 * Valid offer types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function offer_types(): array {
		return array( self::TYPE_CHECKOUT_BUMP, self::TYPE_PRODUCT_UPSELL, self::TYPE_CART_CROSSSELL, self::TYPE_THANKYOU_OFFER );
	}

	/**
	 * Valid statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function statuses(): array {
		return array( 'active', 'paused', 'draft' );
	}

	/**
	 * Valid discount types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function discount_types(): array {
		return array( 'none', 'percent', 'fixed_amount', 'fixed_price' );
	}
}
