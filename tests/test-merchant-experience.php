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


use WPAnchorBay\UpsellBay\Admin\Help\HelpPage;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferEditPage;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferListTable;
use WPAnchorBay\UpsellBay\Admin\Offers\OffersPage;
use WPAnchorBay\UpsellBay\Admin\PreviewLinks;
use WPAnchorBay\UpsellBay\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBay\Admin\Wizard\WizardController;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Offers\ProductRecommendationAssistant;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;

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
		'admin pages expose native empty states with setup actions' => static function (): void {
			$offers    = new OffersPage( new OfferListTable( upsellbay_test_offer_repository( array() ), new OfferService( upsellbay_test_offer_repository( array() ), new OfferValidator( new OfferSchema(), static fn (): bool => true ) ) ) );
			$tools     = new ToolsPage( new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ) ), new Settings( static fn (): array => array(), static fn (): bool => true ) );
			$help      = new HelpPage();

			assert_contains( 'Create offer', $offers->empty_state()['actions'][0]['label'] );
			assert_contains( 'Import offers', $tools->import_empty_state()['title'] );
			assert_contains( 'First offer tutorial', $help->empty_state()['actions'][0]['label'] );
		},
		'preview links route known placements and explain unavailable previews' => static function (): void {
			$links = new PreviewLinks( 'https://example.test' );

			assert_false( $links->for_offer( upsellbay_phase5_offer( 9, 'checkout_bump', 44 ), array( 'checkout_url' => '/checkout/' ) )['available'] );
			assert_contains( 'at least one item in the cart', $links->for_offer( upsellbay_phase5_offer( 9, 'checkout_bump', 44 ), array( 'checkout_url' => '/checkout/' ) )['message'] );
			assert_contains( 'upsellbay_preview=1', $links->for_offer( upsellbay_phase5_offer( 9, 'checkout_bump', 44 ), array( 'checkout_url' => '/checkout/', 'cart_product_ids' => array( 12 ) ) )['url'] );
			assert_contains( '/product/44/', $links->for_offer( upsellbay_phase5_offer( 9, 'product_upsell', 44 ), array( 'product_url' => '/product/%d/' ) )['url'] );
			assert_false( $links->for_offer( upsellbay_phase5_offer( 9, 'thankyou_offer', 44 ), array() )['available'] );
			assert_contains( 'saved test order', $links->for_offer( upsellbay_phase5_offer( 9, 'thankyou_offer', 44 ), array() )['message'] );
		},
		'offer editor exposes help tips progressive sections and accessibility metadata' => static function (): void {
			$page = new OfferEditPage(
				new OfferService( upsellbay_test_offer_repository( array() ), new OfferValidator( new OfferSchema(), static fn (): bool => true ) ),
				new OfferValidator( new OfferSchema(), static fn (): bool => true ),
				static fn (): bool => true,
				static fn (): bool => true,
				new OfferDefaults()
			);

			assert_same( array( 'basics', 'targeting', 'discount', 'placement', 'schedule', 'advanced' ), array_keys( $page->sections() ) );
			assert_true( in_array( '_ub_section_heading', $page->sections()['basics']['fields'], true ) );
			assert_false( $page->sections()['advanced']['collapsed'] );
			assert_contains( 'server-side', $page->help_tips()['discount'] );
			assert_same( 'upsellbay-offer-product-id', $page->accessibility()['offer_product_id']['label_for'] );
			assert_same( 'button', $page->accessibility()['advanced_toggle']['role'] );
		},
		'offer editor preview shows the updated price and falls back to the actual price when no discount is set' => static function (): void {
			$offer_id = 88;
			$GLOBALS['upsellbay_test_products'][321] = new class() {
				public function get_price() {
					return '80';
				}

				public function get_price_html() {
					return '$80.00';
				}

				public function get_name() {
					return 'Demo Product';
				}

				public function get_image_id() {
					return 0;
				}
			};

			$offers = array(
				88 => array(
					'id'    => 88,
					'title' => 'Discounted offer',
					'meta'  => array(
						'_ub_offer_type'       => 'checkout_bump',
						'_ub_offer_product_id' => 321,
						'_ub_discount_type'    => 'percent',
						'_ub_discount_value'   => '25',
					),
				),
				89 => array(
					'id'    => 89,
					'title' => 'Regular price offer',
					'meta'  => array(
						'_ub_offer_type'       => 'checkout_bump',
						'_ub_offer_product_id' => 321,
						'_ub_discount_type'    => 'none',
						'_ub_discount_value'   => '0.000000',
					),
				),
			);
			$service = new OfferService(
				upsellbay_test_offer_repository( $offers ),
				new OfferValidator( new OfferSchema(), static fn (): bool => true )
			);
			$page    = new OfferEditPage(
				$service,
				new OfferValidator( new OfferSchema(), static fn (): bool => true ),
				static fn (): bool => true,
				static fn (): bool => true,
				new OfferDefaults()
			);

			$_GET['offer_id'] = (string) $offer_id;
			ob_start();
			$page->render_content();
			$discounted_html = (string) ob_get_clean();

			$_GET['offer_id'] = '89';
			ob_start();
			$page->render_content();
			$regular_html = (string) ob_get_clean();
			unset( $_GET['offer_id'] );

			assert_contains( 'upsellbay-discount-preview--discounted', $discounted_html );
			assert_contains( 'Updated price', $discounted_html );
			assert_contains( '<del>$80.00</del>', $discounted_html );
			assert_contains( '<strong>$60.00</strong>', $discounted_html );
			assert_contains( 'upsellbay-_ub_offer_product_id-price', $discounted_html );
			assert_contains( 'data-upsellbay-product-price="80"', $discounted_html );

			assert_contains( 'upsellbay-discount-preview--regular', $regular_html );
			assert_contains( '<strong>$80.00</strong>', $regular_html );
			assert_contains( 'No discount is applied', $regular_html );
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
