<?php
/**
 * Merchant-facing offer defaults.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Builds safe draft defaults for new offer forms and onboarding.
 *
 * @since 1.0.0
 */
final class OfferDefaults {
	/**
	 * Return defaults for an offer type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $offer_type Offer type.
	 * @return array<string, mixed>
	 */
	public function for_type( string $offer_type ): array {
		$schema     = new OfferSchema();
		$offer_type = in_array( $offer_type, $schema->offer_types(), true ) ? $offer_type : OfferSchema::TYPE_CHECKOUT_BUMP;
		$copy       = $this->copy_for_type( $offer_type );

		return array_replace(
			$schema->defaults(),
			array(
				'_ub_offer_type'               => $offer_type,
				'_ub_status'                   => 'draft',
				'_ub_discount_type'            => 'none',
				'_ub_discount_value'           => '0.000000',
				'_ub_offer_goal'               => $copy['offer_goal'],
				'_ub_reason_label'             => $copy['reason_label'],
				'_ub_conflict_override'        => false,
				'_ub_conflict_override_reason' => '',
				'_ub_section_heading'          => $copy['section_heading'],
				'_ub_headline'                 => $copy['headline'],
				'_ub_body'                     => $copy['body'],
				'_ub_button_text'              => $copy['button_text'],
				'_ub_placement_config'         => $this->placement_config( $offer_type ),
				'_ub_show_image'               => true,
				'_ub_priority'                 => 10,
			)
		);
	}

	/**
	 * Return placement-specific copy.
	 *
	 * @param string $offer_type Offer type.
	 * @return array{offer_goal: string, reason_label: string, section_heading: string, headline: string, body: string, button_text: string}
	 */
	private function copy_for_type( string $offer_type ): array {
		$copy = array(
			OfferSchema::TYPE_CHECKOUT_BUMP  => array(
				'offer_goal'   => 'add_on',
				'reason_label' => __( 'Special Offer', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'     => __( 'Complete your order with this add-on', 'upsellbay' ),
				'body'         => __( 'Add a relevant product before placing the order.', 'upsellbay' ),
				'button_text'  => __( 'Add to order', 'upsellbay' ),
			),
			OfferSchema::TYPE_PRODUCT_UPSELL => array(
				'offer_goal'   => 'add_on',
				'reason_label' => __( 'Recommended', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'     => __( 'Frequently bought with this product', 'upsellbay' ),
				'body'         => __( 'Show a relevant add-on on the product page.', 'upsellbay' ),
				'button_text'  => __( 'Add item', 'upsellbay' ),
			),
			OfferSchema::TYPE_CART_CROSSSELL => array(
				'offer_goal'   => 'upgrade',
				'reason_label' => __( 'Most Popular', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'     => __( 'Recommended for your cart', 'upsellbay' ),
				'body'         => __( 'Offer a useful add-on while the shopper reviews the cart.', 'upsellbay' ),
				'button_text'  => __( 'Add to cart', 'upsellbay' ),
			),
			OfferSchema::TYPE_THANKYOU_OFFER => array(
				'offer_goal'   => 'follow_on',
				'reason_label' => __( 'Exclusive', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'     => __( 'Add another useful item', 'upsellbay' ),
				'body'         => __( 'Send shoppers to a separate checkout for this follow-on offer.', 'upsellbay' ),
				'button_text'  => __( 'View offer', 'upsellbay' ),
			),
		);

		return $copy[ $offer_type ];
	}

	/**
	 * Return placement defaults.
	 *
	 * @param string $offer_type Offer type.
	 * @return array<string, string>
	 */
	private function placement_config( string $offer_type ): array {
		if ( OfferSchema::TYPE_CHECKOUT_BUMP === $offer_type ) {
			return array( 'position' => 'before_submit' );
		}

		if ( OfferSchema::TYPE_PRODUCT_UPSELL === $offer_type ) {
			return array( 'position' => 'after_add_to_cart' );
		}

		if ( OfferSchema::TYPE_CART_CROSSSELL === $offer_type ) {
			return array( 'position' => 'after_cart_table' );
		}

		return array( 'position' => 'order_received_actions' );
	}
}
