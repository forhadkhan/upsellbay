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

use WPAnchorBay\UpsellBayPro\Admin\PreviewLinks;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;

/**
 * The free host plugin ships and renders only the checkout bump, and exposes
 * exactly that one offer type through its shared offer editor
 * (`OfferSchema::offer_types()`, filtered via `upsellbay_offer_types`). Pro
 * renders the product-page, cart, and thank-you placements, so it adds those
 * three types to the host's filtered list — making them selectable and
 * saveable in the shared editor only while Pro is active.
 *
 * The host editor also reads the type's label, required position, draft copy,
 * description and preview link through its own filters; Pro answers each of
 * them for its three types only and never overrides the host's checkout bump.
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
	 * Pro offer defaults (copy and positions).
	 *
	 * @var OfferDefaults
	 */
	private OfferDefaults $defaults;

	/**
	 * Pro offer schema (labels).
	 *
	 * @var OfferSchema
	 */
	private OfferSchema $schema;

	/**
	 * Pro preview link builder.
	 *
	 * @var PreviewLinks
	 */
	private PreviewLinks $preview_links;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferDefaults|null $defaults      Offer defaults.
	 * @param OfferSchema|null   $schema        Offer schema.
	 * @param PreviewLinks|null  $preview_links Preview link builder.
	 */
	public function __construct( ?OfferDefaults $defaults = null, ?OfferSchema $schema = null, ?PreviewLinks $preview_links = null ) {
		$this->defaults      = $defaults ?? new OfferDefaults();
		$this->schema        = $schema ?? new OfferSchema();
		$this->preview_links = $preview_links ?? new PreviewLinks();
	}

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
		add_filter( 'upsellbay_available_placements', array( $this, 'add_placement_labels' ) );
		add_filter( 'upsellbay_placement_config_positions', array( $this, 'add_position_labels' ) );
		add_filter( 'upsellbay_offer_type_positions', array( $this, 'add_type_positions' ) );
		add_filter( 'upsellbay_offer_type_copy', array( $this, 'add_type_copy' ) );
		add_filter( 'upsellbay_offer_type_descriptions', array( $this, 'add_type_descriptions' ) );
		add_filter( 'upsellbay_offer_preview_link', array( $this, 'preview_link' ), 10, 4 );
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

	/**
	 * Add Pro's placement labels to the host's placement map.
	 *
	 * The host editor lists only the types present in this map, so without it
	 * the Pro types are registered but never selectable.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $placements Host placement key to label map.
	 * @return array<string, string>
	 */
	public function add_placement_labels( $placements ): array {
		return $this->merge_pro_entries( $placements, $this->schema->placements() );
	}

	/**
	 * Add Pro's display position labels to the host's position map.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $positions Host position key to label map.
	 * @return array<string, string>
	 */
	public function add_position_labels( $positions ): array {
		$positions = is_array( $positions ) ? $positions : array();
		$labels    = $this->schema->placement_config_positions();

		foreach ( self::PRO_OFFER_TYPES as $type ) {
			$position = $this->defaults->type_positions()[ $type ] ?? '';
			if ( '' !== $position && isset( $labels[ $position ] ) && ! isset( $positions[ $position ] ) ) {
				$positions[ $position ] = (string) $labels[ $position ];
			}
		}

		return $positions;
	}

	/**
	 * Add the required display position of each Pro offer type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $positions Host offer type to position map.
	 * @return array<string, string>
	 */
	public function add_type_positions( $positions ): array {
		return $this->merge_pro_entries( $positions, $this->defaults->type_positions() );
	}

	/**
	 * Add the draft copy used to seed a new offer of each Pro type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $copy Host offer type to copy map.
	 * @return array<string, mixed>
	 */
	public function add_type_copy( $copy ): array {
		return $this->merge_pro_entries( $copy, $this->defaults->copy_map() );
	}

	/**
	 * Add the editor description of each Pro offer type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $descriptions Host offer type to description map.
	 * @return array<string, string>
	 */
	public function add_type_descriptions( $descriptions ): array {
		return $this->merge_pro_entries(
			$descriptions,
			array(
				OfferSchema::TYPE_PRODUCT_UPSELL => __( 'A product page offer is a related add-on shown near the add-to-cart area on matching product pages. It helps shoppers add a complementary item while considering a product. It appears when the offer is active, the viewed product matches your rules, and the offered product is available.', 'upsellbay' ),
				OfferSchema::TYPE_CART_CROSSSELL => __( 'A cart offer is a cross-sell shown while shoppers review their cart. It recommends an extra item before they continue to checkout. It appears when the offer is active, the current cart matches your rules, and the offered product is available.', 'upsellbay' ),
				OfferSchema::TYPE_THANKYOU_OFFER => __( 'A thank-you follow-on offer is a post-purchase offer shown on the order received page after the main order is complete. Accepting it adds the item to a new cart and starts a separate checkout; the completed order is never changed or charged again. It appears when the offer is active, the completed order matches your rules, and the offered product is available.', 'upsellbay' ),
			)
		);
	}

	/**
	 * Build the admin preview link for a Pro offer type.
	 *
	 * Types Pro does not own keep the host's result untouched.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed                $result    Host preview result.
	 * @param string               $placement Offer type.
	 * @param array<string, mixed> $offer     Offer.
	 * @param array<string, mixed> $context   Context URLs.
	 * @return mixed
	 */
	public function preview_link( $result, $placement = '', $offer = array(), $context = array() ) {
		if ( ! in_array( (string) $placement, self::PRO_OFFER_TYPES, true ) || ! is_array( $offer ) ) {
			return $result;
		}

		return $this->preview_links->for_offer( $offer, is_array( $context ) ? $context : array() );
	}

	/**
	 * Add Pro-type entries to a host map without replacing anything the host set.
	 *
	 * @param mixed                $host Host map.
	 * @param array<string, mixed> $pro  Pro map keyed by offer type.
	 * @return array<string, mixed>
	 */
	private function merge_pro_entries( $host, array $pro ): array {
		$host = is_array( $host ) ? $host : array();

		foreach ( self::PRO_OFFER_TYPES as $type ) {
			if ( isset( $pro[ $type ] ) && ! isset( $host[ $type ] ) ) {
				$host[ $type ] = $pro[ $type ];
			}
		}

		return $host;
	}
}
