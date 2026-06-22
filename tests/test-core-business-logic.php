<?php
/**
 * Phase 4 core business logic tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Api\Routes\PublicOfferRoutes;
use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Data\StatsRepository;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBay\Domain\Attribution\AttributionReader;
use WPAnchorBay\UpsellBay\Domain\Attribution\AttributionWriter;
use WPAnchorBay\UpsellBay\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBay\Domain\Cart\CartValidator;
use WPAnchorBay\UpsellBay\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferVisibilityInspector;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBay\Domain\Storefront\CartCrossSellRenderer;
use WPAnchorBay\UpsellBay\Domain\Storefront\ClassicCheckoutBump;
use WPAnchorBay\UpsellBay\Domain\Storefront\PlacementRenderer;
use WPAnchorBay\UpsellBay\Domain\Storefront\ProductPageRenderer;
use WPAnchorBay\UpsellBay\Domain\Storefront\StorefrontController;
use WPAnchorBay\UpsellBay\Domain\Storefront\ThankYouOfferRenderer;
use WPAnchorBay\UpsellBay\Integrations\WooCommerce\StoreApiExtender;
use WPAnchorBay\UpsellBay\Utils\TokenHelper;

/**
 * Returns Phase 4 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_core_business_logic_tests(): array {
	return array(
		'offer service validates lifecycle actions and preview payloads' => static function (): void {
			$saved      = array();
			$repository = upsellbay_test_offer_repository( array(), $saved );
			$service    = new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn (): bool => true ) );

			$offer_id = $service->create(
				array(
					'title' => 'Checkout bump',
					'meta'  => array(
						'_ub_offer_type'       => 'checkout_bump',
						'_ub_offer_product_id' => 44,
						'_ub_headline'         => 'Add warranty',
						'_ub_button_text'      => 'Add to order',
					),
				)
			);

			assert_same( 1, $offer_id );
			assert_same( 'Checkout bump', $saved['title'] );
			assert_true( $service->pause( $offer_id ) );
			assert_true( $service->activate( $offer_id ) );

			$preview = $service->preview_payload(
				array(
					'id'    => 7,
					'title' => 'Preview',
					'meta'  => array(
						'_ub_offer_type'       => 'checkout_bump',
						'_ub_offer_product_id' => 44,
						'_ub_discount_type'    => 'percent',
						'_ub_discount_value'   => '10',
						'_ub_headline'         => 'Add this',
					),
				)
			);

			assert_same( 7, $preview['id'] );
			assert_same( 'checkout_bump', $preview['placement'] );
			assert_same( 'Add this', $preview['headline'] );
		},
		'rule parser and evaluator support all p0 rule families' => static function (): void {
			$parser    = new RuleParser();
			$evaluator = new RuleEvaluator( $parser );
			$context   = array(
				'cart_product_ids'        => array( 10, 20 ),
				'cart_category_ids'       => array( 3 ),
				'cart_tag_ids'            => array( 8 ),
				'cart_subtotal'           => '75.50',
				'viewed_product_id'       => 20,
				'user_roles'              => array( 'customer' ),
				'customer_order_count'    => 2,
				'customer_lifetime_spend' => '220.00',
				'stock_status'            => 'instock',
			);

			$rules = array(
				array( 'type' => 'cart_product', 'operator' => 'in', 'value' => array( 10 ) ),
				array( 'type' => 'cart_category', 'operator' => 'in', 'value' => array( 3 ) ),
				array( 'type' => 'cart_tag', 'operator' => 'in', 'value' => array( 8 ) ),
				array( 'type' => 'cart_subtotal', 'operator' => 'gte', 'value' => '50' ),
				array( 'type' => 'viewed_product', 'operator' => 'eq', 'value' => 20 ),
				array( 'type' => 'user_role', 'operator' => 'in', 'value' => array( 'customer' ) ),
				array( 'type' => 'customer_order_count', 'operator' => 'gte', 'value' => 1 ),
				array( 'type' => 'customer_lifetime_spend', 'operator' => 'gte', 'value' => '100' ),
				array( 'type' => 'stock_status', 'operator' => 'eq', 'value' => 'instock' ),
				array( 'type' => 'exclude_if_product_in_cart', 'operator' => 'not_in', 'value' => array( 99 ) ),
			);

			assert_true( $evaluator->matches( $rules, 'all', $context ) );
			assert_true( $evaluator->matches( array(), 'all', $context ) );
			assert_true( $evaluator->matches( array( array( 'type' => 'bad' ) ), 'all', $context ) === false );
			assert_true( $evaluator->matches( array( array( 'type' => 'cart_subtotal', 'operator' => 'lt', 'value' => '50' ), $rules[0] ), 'any', $context ) );
		},
		'discount calculator clamps invalid prices and never trusts client input' => static function (): void {
			$calculator = new DiscountCalculator();

			assert_same( '90.000000', $calculator->calculate( '100', array( '_ub_discount_type' => 'percent', '_ub_discount_value' => '10' ) )['offer_price'] );
			assert_same( '85.000000', $calculator->calculate( '100', array( '_ub_discount_type' => 'fixed_amount', '_ub_discount_value' => '15' ) )['offer_price'] );
			assert_same( '12.990000', $calculator->calculate( '100', array( '_ub_discount_type' => 'fixed_price', '_ub_discount_value' => '12.99' ) )['offer_price'] );
			assert_same( '100.000000', $calculator->calculate( '100', array( '_ub_discount_type' => 'none', '_ub_discount_value' => '999' ) )['offer_price'] );
			assert_same( '0.000000', $calculator->calculate( '5', array( '_ub_discount_type' => 'fixed_amount', '_ub_discount_value' => '99' ) )['offer_price'] );
			assert_same( null, $calculator->calculate( '-1', array( '_ub_discount_type' => 'percent', '_ub_discount_value' => '10' ) ) );
			assert_same( null, $calculator->calculate( '100', array( '_ub_discount_type' => 'client_price', '_ub_discount_value' => '1' ) ) );
		},
		'prioritizer filters status schedule dismissal placement rules and limits' => static function (): void {
			$prioritizer = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn ( int $product_id ): bool => 50 === $product_id, static fn (): int => 100 );
			$offers      = array(
				upsellbay_phase4_offer( 1, 'checkout_bump', 50, 20 ),
				upsellbay_phase4_offer( 2, 'checkout_bump', 50, 5 ),
				upsellbay_phase4_offer( 3, 'checkout_bump', 50, 1, 'paused' ),
				upsellbay_phase4_offer( 4, 'cart_crosssell', 50, 1 ),
				upsellbay_phase4_offer( 5, 'checkout_bump', 99, 1 ),
				upsellbay_phase4_offer( 6, 'checkout_bump', 50, 1, 'active', 200, 300 ),
			);

			$selected = $prioritizer->select( $offers, 'checkout_bump', array( 'dismissed_offer_ids' => array( 1 ) ), 1 );

			assert_same( 2, $selected[0]['id'] );
			assert_same( 1, count( $selected ) );
		},
		'prioritizer exposes skip reasons for suppressed checkout bumps' => static function (): void {
			$prioritizer = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 );
			$offer       = upsellbay_phase4_offer( 41, 'checkout_bump', 25, 1 );
			$result      = $prioritizer->evaluate(
				$offer,
				'checkout_bump',
				array(
					'cart_product_ids'    => array( 25 ),
					'dismissed_offer_ids' => array( 41 ),
				)
			);

			assert_false( $result['eligible'] );
			assert_true( in_array( 'dismissed_in_session', $result['reasons'], true ) );
			assert_true( in_array( 'product_already_in_cart', $result['reasons'], true ) );
		},
		'rule aliases and trigger ids are applied to storefront eligibility' => static function (): void {
			$page  = new \WPAnchorBay\UpsellBay\Admin\Offers\OfferEditPage(
				new OfferService( upsellbay_test_offer_repository( array() ), new OfferValidator( new OfferSchema(), static fn (): bool => true ) ),
				new OfferValidator( new OfferSchema(), static fn (): bool => true )
			);
			$rules = $page->normalize_rules(
				array(
					array( 'type' => 'lifetime_spend', 'operator' => 'gte', 'value' => '100' ),
					array( 'type' => 'exclude_product_in_cart', 'operator' => 'in', 'value' => array( 99 ) ),
				)
			);

			assert_same( 'customer_lifetime_spend', $rules[0]['type'] );
			assert_same( 'exclude_if_product_in_cart', $rules[1]['type'] );
			assert_true( ( new RuleEvaluator( new RuleParser() ) )->matches( $rules, 'all', array( 'customer_lifetime_spend' => '125.00', 'cart_product_ids' => array( 10 ) ) ) );

			$prioritizer = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 );
			$offer       = upsellbay_phase4_offer( 77, 'product_upsell', 50, 1 );
			$offer['meta']['_ub_trigger_product_ids'] = array( 15 );

			assert_same( array(), $prioritizer->select( array( $offer ), 'product_upsell', array( 'viewed_product_id' => 10 ), 1 ) );
			assert_same( 77, $prioritizer->select( array( $offer ), 'product_upsell', array( 'viewed_product_id' => 15 ), 1 )[0]['id'] );
		},
		'storefront controller respects settings placement toggles and dismissed offers' => static function (): void {
			$GLOBALS['upsellbay_test_current_user_can'] = array( 'manage_woocommerce' => false );
			$GLOBALS['upsellbay_test_products'][50]     = new UpsellBay_Test_Storefront_Product( 50, 'Warranty', '20.00', 12 );

			$repository = upsellbay_test_offer_repository( array( 1 => upsellbay_phase4_offer( 1, 'checkout_bump', 50, 1 ) ) );
			$renderer   = new PlacementRenderer(
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ),
				new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ),
				array( 'checkout_bump' => new ClassicCheckoutBump( new DiscountCalculator() ) ),
				static fn (): string => '2026-05-30'
			);
			$session    = upsellbay_array_cart_session();

			$disabled = new StorefrontController(
				$repository,
				$renderer,
				$session,
				new Settings( static fn (): array => array( 'enabled' => false ), static fn (): bool => true )
			);
			ob_start();
			$disabled->render_checkout_bump();
			assert_same( '', (string) ob_get_clean() );

			$test_mode = new StorefrontController(
				$repository,
				$renderer,
				$session,
				new Settings( static fn (): array => array( 'test_mode' => true ), static fn (): bool => true )
			);
			ob_start();
			$test_mode->render_checkout_bump();
			assert_same( '', (string) ob_get_clean() );

			$session->dismiss_offer( 1, 'checkout_bump' );
			$enabled = new StorefrontController(
				$repository,
				$renderer,
				$session,
				new Settings( static fn (): array => array(), static fn (): bool => true )
			);
			ob_start();
			$enabled->render_checkout_bump();
			assert_same( '', (string) ob_get_clean() );
		},
		'cart validator mutator and discount applier preserve server controlled state' => static function (): void {
			$cart_items = array();
			$session    = upsellbay_array_cart_session();
			$validator  = new CartValidator(
				new RuleEvaluator( new RuleParser() ),
				new DiscountCalculator(),
				static fn ( int $product_id ): array => array(
					'id'          => $product_id,
					'price'       => '25.000000',
					'purchasable' => true,
					'visible'     => true,
					'in_stock'    => true,
					'type'        => 'simple',
					'subscription'=> false,
				)
			);
			$mutator    = new CartMutator(
				$session,
				$validator,
				new DiscountCalculator(),
				static function ( int $product_id, int $quantity, array $cart_item_data ) use ( &$cart_items ): string {
					$key                = 'item_' . ( count( $cart_items ) + 1 );
					$cart_items[ $key ] = compact( 'product_id', 'quantity', 'cart_item_data' );
					return $key;
				},
				static function ( string $cart_item_key ) use ( &$cart_items ): bool {
					unset( $cart_items[ $cart_item_key ] );
					return true;
				},
				static function ( string $cart_item_key ) use ( &$cart_items ): bool {
					return isset( $cart_items[ $cart_item_key ] );
				}
			);

			$offer  = upsellbay_phase4_offer( 9, 'checkout_bump', 50, 1 );
			$result = $mutator->accept( $offer, 'checkout_bump', array( 'client_price' => '0.01' ) );

			assert_true( $result['success'] );
			assert_same( 1, count( $cart_items ) );
			assert_same( '25.000000', $cart_items[ $result['cart_item_key'] ]['cart_item_data']['_ub_original_price'] );
			assert_same( '25.000000', $cart_items[ $result['cart_item_key'] ]['cart_item_data']['_ub_offer_price'] );

			$duplicate = $mutator->accept( $offer, 'checkout_bump', array() );
			assert_same( $result['cart_item_key'], $duplicate['cart_item_key'] );
			assert_same( 1, count( $cart_items ) );

			$product = new UpsellBay_Test_Product( '30.000000' );
			$item    = array(
				'data'            => $product,
				'_ub_offer_price' => '21.000000',
			);
			( new DiscountApplier() )->apply_to_cart_item( $item );
			assert_same( '21.000000', $product->price );

			assert_true( $mutator->remove( 9 )['success'] );
			assert_same( 0, count( $cart_items ) );
		},
		'placement renderers record views and keep placement logic separate' => static function (): void {
			$GLOBALS['upsellbay_test_products'][50] = new UpsellBay_Test_Storefront_Product( 50, 'Warranty', '25.00', 12 );
			$events      = array();
			$stats       = new StatsRepository( static function ( string $key, array $delta ) use ( &$events ): void { $events[] = array( $key, $delta ); }, static fn (): array => array() );
			$renderer    = new PlacementRenderer(
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ),
				new AnalyticsRecorder( $stats ),
				array(
					'checkout_bump'  => new ClassicCheckoutBump( new DiscountCalculator() ),
					'product_upsell' => new ProductPageRenderer( new DiscountCalculator() ),
					'cart_crosssell' => new CartCrossSellRenderer( new DiscountCalculator() ),
					'thankyou_offer' => new ThankYouOfferRenderer( new DiscountCalculator() ),
				),
				static fn (): string => '2026-05-30'
			);

			$html = $renderer->render( 'checkout_bump', array( upsellbay_phase4_offer( 12, 'checkout_bump', 50, 1 ) ), array() );

			assert_contains( 'upsellbay-offer', $html );
			assert_contains( 'type="checkbox"', $html );
			assert_contains( 'Warranty', $html );
			assert_contains( '<img', $html );
			assert_contains( '$25.00', $html );
			assert_same( array( 'views' => 1 ), $events[0][1] );

			$product_renderer = new ProductPageRenderer( new DiscountCalculator() );
			$product_html     = $product_renderer->render_offer(
				array(
					'id'    => 13,
					'title' => 'Product upsell',
					'meta'  => array(
						'_ub_offer_type'       => 'product_upsell',
						'_ub_offer_product_id' => 50,
						'_ub_headline'         => 'Frequently bought together',
						'_ub_section_heading'  => 'Recommended for you',
					),
				),
				array( 'cart_product_ids' => array() )
			);

			assert_contains( 'Recommended for you', $product_html );

			$thankyou_html = ( new ThankYouOfferRenderer( new DiscountCalculator() ) )->render_offer(
				array(
					'id'    => 14,
					'title' => 'Thank you offer',
					'meta'  => array(
						'_ub_offer_type'       => 'thankyou_offer',
						'_ub_offer_product_id' => 50,
						'_ub_headline'         => 'Add this after checkout',
						'_ub_button_text'      => 'Add to order',
					),
				),
				array(
					'source_order_id'  => 123,
					'source_order_key' => 'wc_order_123',
					'token'            => 'token-123',
					'rest_url'         => 'https://store.test/wp-json/upsellbay/v1',
					'checkout_url'     => 'https://store.test/checkout/',
				)
			);

			assert_contains( 'data-upsellbay-source-order-id="123"', $thankyou_html );
			assert_contains( 'data-upsellbay-source-order-key="wc_order_123"', $thankyou_html );
			assert_contains( 'data-upsellbay-token="token-123"', $thankyou_html );
			assert_contains( 'data-upsellbay-rest-url="https://store.test/wp-json/upsellbay/v1"', $thankyou_html );
			assert_contains( 'data-upsellbay-checkout-url="https://store.test/checkout/"', $thankyou_html );
		},
		'public routes validate token rate limit and ignore client supplied price' => static function (): void {
			$session = upsellbay_array_cart_session();
			$token   = $session->ensure_token();
			$offer   = upsellbay_phase4_offer( 22, 'checkout_bump', 50, 1 );
			$mutator = new CartMutator(
				$session,
				new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), static fn (): array => array( 'id' => 50, 'price' => '19.000000', 'purchasable' => true, 'visible' => true, 'in_stock' => true, 'type' => 'simple', 'subscription' => false ) ),
				new DiscountCalculator(),
				static fn (): string => 'cart_key',
				static fn (): bool => true,
				static fn (): bool => true
			);
			$routes  = new PublicOfferRoutes(
				static fn ( int $offer_id ): ?array => 22 === $offer_id ? $offer : null,
				$mutator,
				$session,
				static function (): bool {
					static $hits = 0;
					++$hits;
					return $hits <= 1;
				},
				static fn (): string => '2026-05-30',
				new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) )
			);

			$response = $routes->bump_toggle( array( 'offer_id' => 22, 'placement' => 'checkout_bump', 'token' => $token, 'price' => '0.01', 'accepted' => true ) );

			assert_same( 200, $response['status'] );
			assert_true( $response['data']['success'] );
			assert_same( '19.000000', $response['data']['offer_price'] );
			assert_same( 429, $routes->dismiss( array( 'offer_id' => 22, 'placement' => 'checkout_bump', 'token' => $token ) )['status'] );
			assert_same( 403, $routes->bump_toggle( array( 'offer_id' => 22, 'token' => 'bad' ) )['status'] );
		},
		'thank-you route validates against verified source order context' => static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$GLOBALS['upsellbay_test_products'][50]   = new UpsellBay_Test_Storefront_Product( 50, 'Follow-on', '19.000000', 0 );
			$GLOBALS['upsellbay_test_orders'][700]    = new UpsellBay_Test_Order(
				700,
				0,
				'wc_order_700',
				'processing',
				array( new UpsellBay_Test_Order_Item( 99 ) ),
				'45.000000'
			);

			$cart_items                    = array();
			$session                       = upsellbay_array_cart_session();
			$token                         = ( new TokenHelper() )->sign_action(
				'thankyou_offer',
				array(
					'source_order_id'  => 700,
					'source_order_key' => 'wc_order_700',
				)
			);
			$offer                         = upsellbay_phase4_offer( 33, 'thankyou_offer', 50, 1 );
			$offer['meta']['_ub_rules']    = array(
				array(
					'type'     => 'cart_product',
					'operator' => 'eq',
					'value'    => array( 99 ),
				),
			);
			$mutator                       = new CartMutator(
				$session,
				new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), static fn (): array => array( 'id' => 50, 'price' => '19.000000', 'purchasable' => true, 'visible' => true, 'in_stock' => true, 'type' => 'simple', 'subscription' => false ) ),
				new DiscountCalculator(),
				static function ( int $product_id, int $quantity, array $cart_item_data ) use ( &$cart_items ): string {
					$cart_items['thankyou_item'] = compact( 'product_id', 'quantity', 'cart_item_data' );
					return 'thankyou_item';
				},
				static fn (): bool => true,
				static fn (): bool => true
			);
			$routes                        = new PublicOfferRoutes(
				static fn ( int $offer_id ): ?array => 33 === $offer_id ? $offer : null,
				$mutator,
				$session,
				static fn (): bool => true,
				static fn (): string => '2026-05-30',
				new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) )
			);

			$response = $routes->cart_offer_add(
				array(
					'offer_id'         => 33,
					'placement'        => 'thankyou_offer',
					'source_order_id'  => 700,
					'source_order_key' => 'wc_order_700',
					'token'            => $token,
				)
			);

			assert_same( 200, $response['status'] );
			assert_true( $response['data']['success'] );
			assert_same( 50, $cart_items['thankyou_item']['product_id'] );
			assert_same( 700, $cart_items['thankyou_item']['cart_item_data'][ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] );

			$dismiss_response = $routes->dismiss(
				array(
					'offer_id'         => 33,
					'placement'        => 'thankyou_offer',
					'source_order_id'  => 700,
					'source_order_key' => 'wc_order_700',
					'token'            => $token,
				)
			);

			assert_same( 200, $dismiss_response['status'] );
			assert_true( $dismiss_response['data']['success'] );

			unset( $GLOBALS['upsellbay_test_current_user_id'], $GLOBALS['upsellbay_test_orders'][700] );
		},
		'store api offer payload uses configured discount value for price html' => static function (): void {
			$GLOBALS['upsellbay_test_products'][61] = new UpsellBay_Test_Storefront_Product( 61, 'Warranty', '100.00', 0 );
			$offer                                  = upsellbay_phase4_offer( 61, 'checkout_bump', 61, 1 );
			$offer['meta']['_ub_discount_type']     = 'percent';
			$offer['meta']['_ub_discount_value']    = '25';
			$offer['meta']['_ub_section_heading']   = 'Recommended for you';
			$repository                             = upsellbay_test_offer_repository( array( 61 => $offer ) );
			$extender                               = new StoreApiExtender(
				$repository,
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ),
				upsellbay_array_cart_session(),
				new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ),
				new Settings( static fn (): array => array(), static fn (): bool => true )
			);

			$data = $extender->get_checkout_offers_data();

			assert_contains( '$100.00', $data['checkout_bump'][0]['price_html'] );
			assert_contains( '$75.00', $data['checkout_bump'][0]['price_html'] );
			assert_same( 'Recommended for you', $data['checkout_bump'][0]['section_heading'] );
		},
		'attribution writer and reader use woo crud object methods only' => static function (): void {
			$item   = new UpsellBay_Test_Meta_Object();
			$order  = new UpsellBay_Test_Meta_Object();
			$writer = new AttributionWriter();
			$reader = new AttributionReader();

			$writer->write_order_item( $item, upsellbay_phase4_offer( 30, 'checkout_bump', 50, 1 ), 'checkout_bump', '3.000000', 'checkout' );
			$writer->write_follow_on_order( $order, 99, 30 );

			assert_same( 30, $reader->read_order_item( $item )[ Constants::ATTRIBUTION_OFFER_ID ] );
			assert_same( 99, $reader->read_order( $order )[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] );
			assert_true( $reader->read_order( $order )[ Constants::ATTRIBUTION_FOLLOW_ON_ORDER ] );
			assert_true( $order->saved );
		},
		'plugin wires checkout line item attribution and order analytics hooks' => static function (): void {
			$plugin = (string) file_get_contents( __DIR__ . '/../app/Core/Plugin.php' );

			assert_contains( 'woocommerce_checkout_create_order_line_item', $plugin );
			assert_contains( 'write_offer_line_item_attribution', $plugin );
			assert_contains( 'record_order_offer_analytics', $plugin );
		},
		'analytics service records accepted offer order lifecycle aggregates' => static function (): void {
			$events  = array();
			$service = new AnalyticsService(
				new AnalyticsRecorder(
					new StatsRepository(
						static function ( string $key, array $delta ) use ( &$events ): void {
							$events[] = array( $key, $delta );
						},
						static fn (): array => array()
					)
				)
			);

			$service->record_event( 'accept', 40, 'cart_crosssell', '2026-05-30', '30.000000', '5.000000' );
			$service->record_event( 'dismiss', 40, 'cart_crosssell', '2026-05-30' );
			$service->record_event( 'order', 40, 'cart_crosssell', '2026-05-30', '30.000000', '5.000000' );

			assert_same( array( 'accepts' => 1, 'revenue' => '30.000000', 'discount_total' => '5.000000' ), $events[0][1] );
			assert_same( array( 'dismissals' => 1 ), $events[1][1] );
			assert_same( array( 'orders' => 1, 'revenue' => '30.000000', 'discount_total' => '5.000000' ), $events[2][1] );
		},
		'compatibility scanner detects risky checkout plugins without hard failing checkout' => static function (): void {
			$scanner  = new CompatibilityScanner(
				static fn ( string $plugin ): bool => 'cartflows/cartflows.php' === $plugin
			);
			$findings = $scanner->findings();

			assert_same( 'warning', $findings['cartflows']['severity'] );
			assert_false( $scanner->should_block_checkout() );
		},
		'visibility inspector reports checkout preview prerequisites' => static function (): void {
			$offer     = upsellbay_phase4_offer( 77, 'checkout_bump', 44, 1 );
			$inspector = new OfferVisibilityInspector(
				new Settings( static fn (): array => array(), static fn (): bool => true ),
				new OfferValidator( new OfferSchema(), static fn (): bool => true ),
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true ),
				upsellbay_test_offer_repository( array( 77 => $offer ) )
			);
			$report    = $inspector->inspect( $offer, array( 'cart_product_ids' => array() ) );

			assert_same( 'eligible', $report['status'] );
			assert_contains( 'at least one item in the cart', $report['preview']['message'] );
		},
		'core business logic contains no cartbay runtime coupling' => static function (): void {
			$paths = array_merge(
				glob( __DIR__ . '/../app/Domain/**/*.php' ) ?: array(),
				glob( __DIR__ . '/../app/Api/**/*.php' ) ?: array()
			);

			foreach ( $paths as $path ) {
				$contents = (string) file_get_contents( $path );
				assert_false( str_contains( $contents, 'cartbay_' ) );
				assert_false( str_contains( $contents, '_cartbay_' ) );
				assert_false( str_contains( $contents, 'WPAnchorBay\\CartBay' ) );
			}
		},
	);
}

/**
 * Build a normalized Phase 4 offer fixture.
 *
 * @since 1.0.0
 *
 * @param int         $id         Offer ID.
 * @param string      $type       Offer type.
 * @param int         $product_id Product ID.
 * @param int         $priority   Priority.
 * @param string      $status     Status.
 * @param int|null    $start_at   Start timestamp.
 * @param int|null    $end_at     End timestamp.
 * @return array<string, mixed>
 */
function upsellbay_phase4_offer( int $id, string $type, int $product_id, int $priority, string $status = 'active', ?int $start_at = null, ?int $end_at = null ): array {
	return array(
		'id'    => $id,
		'title' => 'Offer ' . $id,
		'meta'  => array(
			'_ub_offer_type'       => $type,
			'_ub_status'           => $status,
			'_ub_offer_product_id' => $product_id,
			'_ub_discount_type'    => 'none',
			'_ub_discount_value'   => '0.000000',
			'_ub_headline'         => 'Add offer ' . $id,
			'_ub_body'             => 'Useful add-on',
			'_ub_button_text'      => 'Add to order',
			'_ub_rules'            => array(),
			'_ub_rules_match'      => 'all',
			'_ub_show_image'       => true,
			'_ub_start_at'         => null === $start_at ? null : gmdate( 'Y-m-d H:i:s', $start_at ),
			'_ub_end_at'           => null === $end_at ? null : gmdate( 'Y-m-d H:i:s', $end_at ),
			'_ub_priority'         => $priority,
		),
	);
}

/**
 * Build an array-backed cart session.
 *
 * @since 1.0.0
 */
function upsellbay_array_cart_session(): CartSession {
	$data = array();

	return new CartSession(
		static function ( string $key ) use ( &$data ) {
			return $data[ $key ] ?? null;
		},
		static function ( string $key, $value ) use ( &$data ): void {
			$data[ $key ] = $value;
		}
	);
}

/**
 * Product test double.
 *
 * @since 1.0.0
 */
final class UpsellBay_Test_Product {
	/**
	 * Current price.
	 *
	 * @var string
	 */
	public string $price;

	/**
	 * Constructor.
	 *
	 * @param string $price Price.
	 */
	public function __construct( string $price ) {
		$this->price = $price;
	}

	/**
	 * Set price.
	 *
	 * @param string $price Price.
	 */
	public function set_price( string $price ): void {
		$this->price = $price;
	}
}

/**
 * Storefront product test double.
 *
 * @since 1.0.0
 */
final class UpsellBay_Test_Storefront_Product {
	private int $id;
	private string $name;
	private string $price;
	private int $image_id;

	public function __construct( int $id, string $name, string $price, int $image_id = 0 ) {
		$this->id       = $id;
		$this->name     = $name;
		$this->price    = $price;
		$this->image_id = $image_id;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_price(): string {
		return $this->price;
	}

	public function get_image_id(): int {
		return $this->image_id;
	}

	public function is_purchasable(): bool {
		return true;
	}

	public function is_visible(): bool {
		return true;
	}

	public function is_in_stock(): bool {
		return true;
	}

	public function get_type(): string {
		return 'simple';
	}
}

/**
 * Source order item test double.
 *
 * @since 1.0.0
 */
final class UpsellBay_Test_Order_Item {
	private int $product_id;

	public function __construct( int $product_id ) {
		$this->product_id = $product_id;
	}

	public function get_product_id(): int {
		return $this->product_id;
	}
}

/**
 * Source order test double.
 *
 * @since 1.0.0
 */
final class UpsellBay_Test_Order {
	private int $id;
	private int $user_id;
	private string $order_key;
	private string $status;
	/** @var array<int, UpsellBay_Test_Order_Item> */
	private array $items;
	private string $subtotal;

	/**
	 * Constructor.
	 *
	 * @param int                              $id        Order ID.
	 * @param int                              $user_id   Customer user ID.
	 * @param string                           $order_key Order key.
	 * @param string                           $status    Order status.
	 * @param array<int, UpsellBay_Test_Order_Item> $items Order items.
	 * @param string                           $subtotal  Order subtotal.
	 */
	public function __construct( int $id, int $user_id, string $order_key, string $status, array $items, string $subtotal ) {
		$this->id        = $id;
		$this->user_id   = $user_id;
		$this->order_key = $order_key;
		$this->status    = $status;
		$this->items     = $items;
		$this->subtotal  = $subtotal;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_user_id(): int {
		return $this->user_id;
	}

	public function get_order_key(): string {
		return $this->order_key;
	}

	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Return order items.
	 *
	 * @return array<int, UpsellBay_Test_Order_Item>
	 */
	public function get_items(): array {
		return $this->items;
	}

	public function get_subtotal(): string {
		return $this->subtotal;
	}
}

/**
 * Woo CRUD meta object test double.
 *
 * @since 1.0.0
 */
final class UpsellBay_Test_Meta_Object {
	/**
	 * Meta values.
	 *
	 * @var array<string, mixed>
	 */
	public array $meta = array();

	/**
	 * Whether save was called.
	 *
	 * @var bool
	 */
	public bool $saved = false;

	/**
	 * Add meta.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function add_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Update meta.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Read meta.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? null;
	}

	/**
	 * Save.
	 */
	public function save(): void {
		$this->saved = true;
	}
}
