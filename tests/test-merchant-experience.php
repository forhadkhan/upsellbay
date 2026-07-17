<?php
/**
 * Phase 5 merchant experience tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Admin\Help\HelpPage;
use WPAnchorBay\UpsellBayPro\Admin\PreviewLinks;
use WPAnchorBay\UpsellBayPro\Admin\Wizard\WizardController;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\ProductRecommendationAssistant;

/**
 * Returns Phase 5 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_merchant_experience_tests(): array {
	return array(
		'wizard creates a draft checkout bump and persists completion state' => static function (): void {
			$saved      = array();
			$stored     = array();
			$repository = upsellbay_test_offer_repository( array(), $saved );
			$settings   = new Settings(
				static fn (): array => array( 'test_mode' => false ),
				static function ( array $value ) use ( &$stored ): bool {
					$stored = $value;
					return true;
				}
			);
			$wizard     = new WizardController(
				new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn ( int $product_id ): bool => 44 === $product_id ) ),
				$settings,
				new OfferDefaults(),
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);

			$result = $wizard->complete(
				array(
					'nonce'                => 'good',
					'offer_product_id'     => '44',
					'placement'            => 'checkout_bump',
					'headline'             => 'Add warranty protection',
					'discount_type'        => 'percent',
					'discount_value'       => '10',
					'rule_type'            => 'cart_subtotal',
					'rule_operator'        => 'gte',
					'rule_value'           => '50',
					'enable_test_mode'     => '1',
				)
			);

			assert_true( $result['success'] );
			assert_same( 1, $result['offer_id'] );
			assert_same( 'Checkout bump offer', $saved['title'] );
			assert_same( 'draft', $saved['meta']['_ub_status'] );
			assert_same( 'checkout_bump', $saved['meta']['_ub_offer_type'] );
			assert_same( 44, $saved['meta']['_ub_offer_product_id'] );
			assert_same( 'cart_subtotal', $saved['meta']['_ub_rules'][0]['type'] );
			assert_true( $stored['wizard_completed'] );
			assert_true( $stored['test_mode'] );
			assert_same( 1, $stored['first_offer_id'] );
			assert_false( $wizard->complete( array( 'nonce' => 'bad' ) )['success'] );
		},
		'offer defaults populate critical merchant-facing fields without activating offers' => static function (): void {
			$defaults = new OfferDefaults();
			$meta     = $defaults->for_type( 'checkout_bump' );

			assert_same( 'checkout_bump', $meta['_ub_offer_type'] );
			assert_same( 'draft', $meta['_ub_status'] );
			assert_same( 'Complete your order with this add-on', $meta['_ub_headline'] );
			assert_same( 'Add to order', $meta['_ub_button_text'] );
			assert_same( 'Recommended for you', $defaults->for_type( 'cart_crosssell' )['_ub_section_heading'] );
			assert_same( 'before_submit', $meta['_ub_placement_config']['position'] );
			assert_same( '0.000000', $meta['_ub_discount_value'] );
			assert_true( $meta['_ub_show_image'] );
		},
		'help page exposes a native empty state with a setup action' => static function (): void {
			$help = new HelpPage();

			assert_contains( 'First offer tutorial', $help->empty_state()['actions'][0]['label'] );
		},
		'preview links route known placements and explain unavailable previews' => static function (): void {
			$links = new PreviewLinks( 'https://example.test' );

			assert_false( $links->for_offer( upsellbay_phase5_offer( 9, 'checkout_bump', 44 ), array( 'checkout_url' => '/checkout/' ) )['available'] );
			assert_contains( 'at least one item in the cart', $links->for_offer( upsellbay_phase5_offer( 9, 'checkout_bump', 44 ), array( 'checkout_url' => '/checkout/' ) )['message'] );
			assert_contains( 'upsellbay_preview=1', $links->for_offer( upsellbay_phase5_offer( 9, 'checkout_bump', 44 ), array( 'checkout_url' => '/checkout/', 'cart_product_ids' => array( 12 ) ) )['url'] );

			$product_upsell_offer = upsellbay_phase5_offer( 9, 'product_upsell', 44 );
			$product_upsell_offer['meta']['_ub_trigger_product_ids'] = array( 44 );
			assert_contains( '/product/44/', $links->for_offer( $product_upsell_offer, array( 'product_url' => '/product/%d/' ) )['url'] );

			assert_false( $links->for_offer( upsellbay_phase5_offer( 9, 'thankyou_offer', 44 ), array() )['available'] );
			assert_contains( 'saved test order', $links->for_offer( upsellbay_phase5_offer( 9, 'thankyou_offer', 44 ), array() )['message'] );
		},
		'local recommendation assistant ranks explainable optional product suggestions' => static function (): void {
			$assistant = new ProductRecommendationAssistant(
				static fn ( int $product_id ): array => array( 10, 11 ),
				static fn ( int $product_id ): array => array( 12 ),
				static fn ( int $category_id ): array => array( 13, 10 ),
				static fn (): array => array( 14 )
			);

			$suggestions = $assistant->suggest(
				array(
					'base_product_id' => 5,
					'category_ids'    => array( 7 ),
					'limit'           => 4,
				)
			);

			assert_same( array( 10, 11, 12, 13 ), array_column( $suggestions, 'product_id' ) );
			assert_contains( 'WooCommerce upsell', $suggestions[0]['reason'] );
			assert_same( array(), $assistant->suggest( array( 'limit' => 4 ) ) );
		},
		'merchant copy avoids recovery funnel and unsupported lift language' => static function (): void {
			$copy = implode(
				' ',
				array_merge(
					array_column( ( new HelpPage() )->links(), 'label' ),
					array( ( new OfferDefaults() )->for_type( 'checkout_bump' )['_ub_headline'] )
				)
			);

			foreach ( array( 'abandoned cart', 'recovery sequence', 'funnel builder', 'CartBay upgrade', 'guaranteed lift' ) as $forbidden ) {
				assert_false( str_contains( strtolower( $copy ), strtolower( $forbidden ) ) );
			}
		},
	);
}

/**
 * Build a Phase 5 offer fixture.
 *
 * @since 1.0.0
 *
 * @param int    $id         Offer ID.
 * @param string $type       Offer type.
 * @param int    $product_id Product ID.
 * @return array<string, mixed>
 */
function upsellbay_phase5_offer( int $id, string $type, int $product_id ): array {
	return array(
		'id'    => $id,
		'title' => 'Offer ' . $id,
		'meta'  => array(
			'_ub_offer_type'       => $type,
			'_ub_offer_product_id' => $product_id,
		),
	);
}
