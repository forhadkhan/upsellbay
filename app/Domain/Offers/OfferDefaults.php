<?php
/**
 * Merchant-facing offer defaults.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\Offers;

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
		$copy = $this->copy_map();

		return $copy[ $offer_type ];
	}

	/**
	 * Return the draft copy map for every offer type, keyed by offer type.
	 *
	 * The host's shared editor seeds new offers from this map through
	 * `upsellbay_offer_type_copy` (see OfferTypesIntegration).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{offer_goal: string, reason_label: string, section_heading: string, headline: string, body: string, button_text: string}>
	 */
	public function copy_map(): array {
		return array(
			OfferSchema::TYPE_CHECKOUT_BUMP  => array(
				'offer_goal'      => 'add_on',
				'reason_label'    => __( 'Special Offer', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'        => __( 'Complete your order with this add-on', 'upsellbay' ),
				'body'            => __( 'Add this item to your order before you pay.', 'upsellbay' ),
				'button_text'     => __( 'Add to order', 'upsellbay' ),
			),
			OfferSchema::TYPE_PRODUCT_UPSELL => array(
				'offer_goal'      => 'add_on',
				'reason_label'    => __( 'Recommended', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'        => __( 'Frequently bought with this product', 'upsellbay' ),
				'body'            => __( 'Pairs well with the item you are viewing.', 'upsellbay' ),
				'button_text'     => __( 'Add item', 'upsellbay' ),
			),
			OfferSchema::TYPE_CART_CROSSSELL => array(
				'offer_goal'      => 'upgrade',
				'reason_label'    => __( 'Special Offer', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'        => __( 'Recommended for your cart', 'upsellbay' ),
				'body'            => __( 'Add this to your cart before you check out.', 'upsellbay' ),
				'button_text'     => __( 'Add to cart', 'upsellbay' ),
			),
			OfferSchema::TYPE_THANKYOU_OFFER => array(
				'offer_goal'      => 'follow_on',
				'reason_label'    => __( 'Thank-you offer', 'upsellbay' ),
				'section_heading' => __( 'Recommended for you', 'upsellbay' ),
				'headline'        => __( 'Add another useful item', 'upsellbay' ),
				'body'            => __( 'Get this item with a separate checkout.', 'upsellbay' ),
				'button_text'     => __( 'View offer', 'upsellbay' ),
			),
		);
	}

	/**
	 * Return the display position each offer type renders at.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function type_positions(): array {
		return array(
			OfferSchema::TYPE_CHECKOUT_BUMP  => 'before_submit',
			OfferSchema::TYPE_PRODUCT_UPSELL => 'after_add_to_cart',
			OfferSchema::TYPE_CART_CROSSSELL => 'after_cart_table',
			OfferSchema::TYPE_THANKYOU_OFFER => 'order_received_actions',
		);
	}

	/**
	 * Return placement defaults.
	 *
	 * @param string $offer_type Offer type.
	 * @return array<string, string>
	 */
	private function placement_config( string $offer_type ): array {
		return array( 'position' => $this->type_positions()[ $offer_type ] ?? 'order_received_actions' );
	}
}
