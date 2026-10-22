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


use WPAnchorBay\UpsellBayPro\Api\Routes\PublicOfferRoutes;
use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Data\CartSession;
use WPAnchorBay\UpsellBayPro\Data\OfferRepository;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBayPro\Domain\Attribution\AttributionReader;
use WPAnchorBay\UpsellBayPro\Domain\Attribution\AttributionWriter;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartValidator;
use WPAnchorBay\UpsellBayPro\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferVisibilityInspector;
use WPAnchorBay\UpsellBayPro\Data\FbtRepository;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\FollowOnLinkageWriter;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\OneClickCharger;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\OneClickResult;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\PaymentChargeAdapter;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\PaymentChargeAdapterRegistry;
use WPAnchorBay\UpsellBayPro\Domain\Recommendations\FbtMiner;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleDefinitions;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\CartCrossSellRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ClassicCheckoutBump;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\PlacementRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ProductPageRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\StorefrontController;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ThankYouOfferRenderer;
use WPAnchorBay\UpsellBayPro\Integrations\OneClick\StripeChargeAdapter;
use WPAnchorBay\UpsellBayPro\Integrations\WooCommerce\StoreApiExtender;
use WPAnchorBay\UpsellBayPro\Utils\TokenHelper;

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
			$definitions = new RuleDefinitions();
			assert_same(
				array( 'cart_product', 'cart_category', 'cart_tag', 'cart_subtotal', 'viewed_product', 'user_role', 'customer_order_count', 'customer_lifetime_spend', 'stock_status', 'exclude_if_product_in_cart' ),
				$definitions->types()
			);
			assert_same( 'contains', $definitions->resolve_operator( 'cart_product', 'in' ) );
			assert_same( null, $definitions->resolve_operator( 'cart_product', 'gt' ) );

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
				array( 'type' => 'cart_product', 'operator' => 'contains', 'value' => array( 10 ) ),
				array( 'type' => 'cart_category', 'operator' => 'contains', 'value' => array( 3 ) ),
				array( 'type' => 'cart_tag', 'operator' => 'contains', 'value' => array( 8 ) ),
				array( 'type' => 'cart_subtotal', 'operator' => 'gte', 'value' => '50' ),
				array( 'type' => 'viewed_product', 'operator' => 'contains', 'value' => array( 20 ) ),
				array( 'type' => 'user_role', 'operator' => 'contains', 'value' => array( 'customer' ) ),
				array( 'type' => 'customer_order_count', 'operator' => 'gte', 'value' => 1 ),
				array( 'type' => 'customer_lifetime_spend', 'operator' => 'gte', 'value' => '100' ),
				array( 'type' => 'stock_status', 'operator' => 'eq', 'value' => 'instock' ),
				array( 'type' => 'exclude_if_product_in_cart', 'operator' => 'not_in', 'value' => array( 99 ) ),
			);

			assert_true( $evaluator->matches( $rules, 'all', $context ) );
			assert_true( $evaluator->matches( array(), 'all', $context ) );
			assert_true( $evaluator->matches( array( array( 'type' => 'bad' ) ), 'all', $context ) === false );
			assert_true( $evaluator->matches( array( array( 'type' => 'cart_subtotal', 'operator' => 'lt', 'value' => '50' ), $rules[0] ), 'any', $context ) );
			assert_false( $evaluator->matches( array( array( 'type' => 'user_role', 'operator' => 'contains', 'value' => array( 'guest' ) ) ), 'all', $context ) );
			assert_false( $evaluator->matches( array( array( 'type' => 'cart_product', 'operator' => 'gt', 'value' => array( 10 ) ) ), 'all', $context ) );
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
			// A percent discount over 100 must clamp to 100 (never a negative offer price),
			// and a negative discount value must clamp to 0 (never a discount that inflates the price).
			// OfferValidator rejects both at save time, but the calculator must stay safe on its own
			// in case a bad value ever reaches it another way (direct DB edit, a future caller, etc.).
			assert_same( '0.000000', $calculator->calculate( '100', array( '_ub_discount_type' => 'percent', '_ub_discount_value' => '150' ) )['offer_price'] );
			assert_same( '100.000000', $calculator->calculate( '100', array( '_ub_discount_type' => 'fixed_amount', '_ub_discount_value' => '-10' ) )['offer_price'] );
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
		'prioritizer evaluates stock status rules from the offered product' => static function (): void {
			$GLOBALS['upsellbay_test_products'][60] = new class() {
				public function get_stock_status(): string {
					return 'onbackorder';
				}
			};

			$offer = upsellbay_phase4_offer( 60, 'checkout_bump', 60, 1 );
			$offer['meta']['_ub_rules'] = array(
				array(
					'type'     => 'stock_status',
					'operator' => 'eq',
					'value'    => 'onbackorder',
				),
			);

			$prioritizer = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 );

			assert_same( 60, $prioritizer->select( array( $offer ), 'checkout_bump', array(), 1 )[0]['id'] );

			$offer['meta']['_ub_rules'][0]['value'] = 'instock';
			assert_same( array(), $prioritizer->select( array( $offer ), 'checkout_bump', array(), 1 ) );
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
			// Legacy rule type aliases are resolved by the shared rule definitions
			// that the offer-save flow (owned by the host plugin) relies on.
			$definitions = new RuleDefinitions();
			assert_same( 'customer_lifetime_spend', $definitions->normalize_type( 'lifetime_spend' ) );
			assert_same( 'exclude_if_product_in_cart', $definitions->normalize_type( 'exclude_product_in_cart' ) );

			$rules = ( new RuleParser() )->parse(
				array(
					array( 'type' => 'lifetime_spend', 'operator' => 'gte', 'value' => '100' ),
				)
			);

			assert_same( 'customer_lifetime_spend', $rules[0]['type'] );
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
				'data'     => $product,
				'quantity' => 1,
			);
			( new DiscountApplier() )->apply( $item, '21.000000', '30.000000' );
			assert_same( '21.000000', $product->price );

			// Subscription products must never have their price mutated here — a
			// discounted price on the initial cart item would carry forward into
			// every recurring renewal, not just the first charge.
			$subscription_product        = new UpsellBay_Test_Product( '30.000000' );
			WC_Subscriptions_Product::$is_subscription_result = true;
			( new DiscountApplier() )->apply(
				array(
					'data'     => $subscription_product,
					'quantity' => 1,
				),
				'21.000000',
				'30.000000'
			);
			assert_same( '30.000000', $subscription_product->price );
			WC_Subscriptions_Product::$is_subscription_result = false;

			assert_true( $mutator->remove( 9 )['success'] );
			assert_same( 0, count( $cart_items ) );
		},
		'offer validator rejects unsupported product types on every placement' => static function (): void {
			$product_context = static fn ( int $product_id ): array => array(
				'purchasable' => true,
				'in_stock'    => true,
				'visible'     => true,
				'type'        => 'variable',
			);

			foreach ( array( 'checkout_bump', 'product_upsell', 'cart_crosssell', 'thankyou_offer' ) as $placement ) {
				$validator = new OfferValidator( new OfferSchema(), static fn (): bool => true, $product_context );
				$meta      = upsellbay_phase4_offer( 1, $placement, 42, 1 )['meta'];
				$result    = $validator->validate( $meta );

				assert_false( $result->is_valid() );
				assert_true( isset( $result->errors()['_ub_offer_product_id_type'] ) );
			}
		},
		'offer validator rejects out-of-range discount values at save time' => static function (): void {
			$product_context = static fn ( int $product_id ): array => array(
				'purchasable' => true,
				'in_stock'    => true,
				'visible'     => true,
				'type'        => 'simple',
			);
			$validator       = new OfferValidator( new OfferSchema(), static fn (): bool => true, $product_context );

			$meta                        = upsellbay_phase4_offer( 1, 'checkout_bump', 42, 1 )['meta'];
			$meta['_ub_discount_type']   = 'percent';
			$meta['_ub_discount_value']  = '150';
			$over_max                    = $validator->validate( $meta );
			assert_false( $over_max->is_valid() );
			assert_true( isset( $over_max->errors()['_ub_discount_value_percent'] ) );

			$meta['_ub_discount_type']   = 'fixed_amount';
			$meta['_ub_discount_value']  = '-10';
			$negative                    = $validator->validate( $meta );
			assert_false( $negative->is_valid() );
			assert_true( isset( $negative->errors()['_ub_discount_value'] ) );

			$meta['_ub_discount_type']   = 'percent';
			$meta['_ub_discount_value']  = '100';
			$at_max                      = $validator->validate( $meta );
			assert_true( ! isset( $at_max->errors()['_ub_discount_value_percent'] ) );
		},
		'one-click charger enforces idempotency and gateway-capability fallback' => static function (): void {
			// Result value semantics.
			assert_same( 'charged', OneClickResult::charged( 5 )->status() );
			assert_same( 5, OneClickResult::charged( 5 )->order_id() );
			assert_false( OneClickResult::charged( 5 )->should_fall_back() );
			assert_true( OneClickResult::declined( 'x' )->should_fall_back() );
			assert_true( OneClickResult::unsupported( 'x' )->should_fall_back() );
			assert_same( 9, OneClickResult::duplicate( 9 )->order_id() );

			$offer       = array( 'id' => 77, 'meta' => array( '_ub_offer_product_id' => 50 ) );
			$source      = new WC_Order( 6001, 'some_gateway' );
			$attribution = new class() implements FollowOnLinkageWriter {
				public function write_follow_on_order( object $order, int $source_order_id, int $offer_id ): bool {
					return true;
				}
			};

			// Idempotency: an existing, paid follow-on order for this (order, offer)
			// returns duplicate and never reaches the charge path.
			$duplicate = new OneClickCharger(
				new PaymentChargeAdapterRegistry(),
				new DiscountCalculator(),
				$attribution,
				null,
				static fn ( int $src, int $off ): int => 5555,
				static fn ( int $order_id ): object => new UpsellBay_Test_OneClick_Order( $order_id, 'some_gateway', 'completed' )
			);
			$dup = $duplicate->charge( $source, $offer, 'thankyou_offer' );
			assert_same( 'duplicate', $dup->status() );
			assert_same( 5555, $dup->order_id() );

			// No adapter registered for the gateway -> unsupported (caller falls back to
			// the standard separate-checkout offer flow).
			$unsupported = new OneClickCharger(
				new PaymentChargeAdapterRegistry(),
				new DiscountCalculator(),
				$attribution,
				null,
				static fn ( int $src, int $off ): int => 0
			);
			$uns = $unsupported->charge( $source, $offer, 'thankyou_offer' );
			assert_same( 'unsupported', $uns->status() );
			assert_true( $uns->should_fall_back() );
		},
		'one-click retry after requires_action never reports success or charges again' => static function (): void {
			$harness = upsellbay_one_click_harness( array( 'requires_action', 'charged' ) );
			$source  = new UpsellBay_Test_OneClick_Order( 6100, 'test_gateway', 'completed' );
			$offer   = array( 'id' => 77, 'meta' => array( '_ub_offer_product_id' => 50 ) );

			$first = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
			assert_same( 'requires_action', $first->status() );
			assert_true( $first->should_fall_back() );
			// No challenge UI ships, so the unpaid follow-on order is closed out, not left pending.
			assert_same( 'cancelled', $harness['state']->orders[ $first->order_id() ]->get_status() );
			assert_false( $harness['state']->orders[ $first->order_id() ]->is_paid() );

			$retry = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
			assert_false( in_array( $retry->status(), array( 'charged', 'duplicate' ), true ) );
			assert_true( $retry->should_fall_back() );
			// One follow-on order per (source, offer): the retry never reaches the gateway again.
			assert_same( 1, $harness['adapter']->calls );
			assert_same( 1, count( $harness['state']->orders ) );
		},
		'one-click retry after a real charge is deduplicated without a second charge' => static function (): void {
			$harness = upsellbay_one_click_harness( array( 'charged', 'charged' ) );
			$source  = new UpsellBay_Test_OneClick_Order( 6101, 'test_gateway', 'completed' );
			$offer   = array( 'id' => 77, 'meta' => array( '_ub_offer_product_id' => 50 ) );

			$first = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
			assert_same( 'charged', $first->status() );
			assert_true( $harness['state']->orders[ $first->order_id() ]->is_paid() );

			$retry = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
			assert_same( 'duplicate', $retry->status() );
			assert_same( $first->order_id(), $retry->order_id() );
			assert_same( 1, $harness['adapter']->calls );
		},
		'one-click retry after a definite decline falls back without success' => static function (): void {
			foreach ( array( 'declined', 'unsupported' ) as $outcome ) {
				$harness = upsellbay_one_click_harness( array( $outcome, 'charged' ) );
				$source  = new UpsellBay_Test_OneClick_Order( 6102, 'test_gateway', 'completed' );
				$offer   = array( 'id' => 77, 'meta' => array( '_ub_offer_product_id' => 50 ) );

				$first = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
				assert_true( $first->should_fall_back() );
				$linked = $harness['state']->orders[ array_key_first( $harness['state']->orders ) ];
				assert_same( 'cancelled', $linked->get_status() );

				$retry = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
				assert_false( in_array( $retry->status(), array( 'charged', 'duplicate' ), true ) );
				assert_true( $retry->should_fall_back() );
				assert_same( 1, $harness['adapter']->calls );
			}
		},
		'one-click retry while the first attempt is still unpaid fails closed' => static function (): void {
			$harness = upsellbay_one_click_harness( array( 'charged' ) );
			$source  = new UpsellBay_Test_OneClick_Order( 6103, 'test_gateway', 'completed' );
			$offer   = array( 'id' => 77, 'meta' => array( '_ub_offer_product_id' => 50 ) );

			// A linked follow-on order exists but is still pending (in-flight or interrupted charge).
			$pending                                   = new UpsellBay_Test_OneClick_Order( 8800, 'test_gateway', 'pending' );
			$harness['state']->orders[8800]            = $pending;
			$harness['state']->links['6103:77']        = 8800;

			$retry = $harness['charger']->charge( $source, $offer, 'thankyou_offer' );
			assert_same( 'in_progress', $retry->status() );
			assert_false( $retry->should_fall_back() );
			assert_same( 0, $harness['adapter']->calls );
			assert_same( 'pending', $pending->get_status() );
		},
		'one-click adapter exception or unknown outcome holds the order and never falls back' => static function (): void {
			foreach ( array( 'throw', 'in_progress', 'bogus' ) as $outcome ) {
				$harness = upsellbay_one_click_harness( array( $outcome, 'charged' ) );
				$source  = new UpsellBay_Test_OneClick_Order( 6104, 'test_gateway', 'completed' );

				$first  = $harness['charger']->charge( $source, upsellbay_one_click_offer(), 'thankyou_offer' );
				$linked = $harness['state']->orders[ array_key_first( $harness['state']->orders ) ];
				assert_same( 'in_progress', $first->status() );
				assert_false( $first->should_fall_back() );
				// Unknown is not declined: the order stays open (on hold), never cancelled.
				assert_same( 'on-hold', $linked->get_status() );
				$note = (string) end( $linked->notes );
				assert_contains( 'outcome is unknown', $note );
				// The note is masked: no exception message (which may carry secrets or PII).
				assert_false( str_contains( $note, 'sk_live' ) );
				assert_false( str_contains( $note, '@' ) );

				// A retry resolves through the existing linked order: no second charge.
				$retry = $harness['charger']->charge( $source, upsellbay_one_click_offer(), 'thankyou_offer' );
				assert_same( 'in_progress', $retry->status() );
				assert_false( $retry->should_fall_back() );
				assert_same( 1, $harness['adapter']->calls );
				assert_same( 1, count( $harness['state']->orders ) );
			}
		},
		'one-click route reports in_progress without fallback when the adapter throws' => upsellbay_with_one_click( static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$GLOBALS['upsellbay_test_orders'][6210]    = new UpsellBay_Test_OneClick_Order( 6210, 'test_gateway', 'processing' );
			$harness                                   = upsellbay_one_click_harness( array( 'throw' ) );
			$routes                                    = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) ) );

			$response = $routes->one_click_accept( upsellbay_one_click_params( 6210 ) );
			assert_same( 200, $response['status'] );
			assert_false( $response['data']['success'] );
			assert_same( 'in_progress', $response['data']['status'] );
			assert_false( isset( $response['data']['should_fall_back'] ) );
			assert_same( 1, $harness['adapter']->calls );
		} ),
		'one-click charges the tax-inclusive order total' => static function (): void {
			$harness                    = upsellbay_one_click_harness( array( 'charged' ) );
			$harness['state']->tax_rate = 0.10;
			$source                     = new UpsellBay_Test_OneClick_Order( 6105, 'test_gateway', 'completed' );

			$result = $harness['charger']->charge( $source, upsellbay_one_click_offer(), 'thankyou_offer' );
			$linked = $harness['state']->orders[ $result->order_id() ];
			assert_same( 'charged', $result->status() );
			assert_true( $linked->last_and_taxes );
			// 19.00 item + 10% tax: the adapter charges the order total, tax included.
			assert_same( 20.9, $harness['adapter']->charged_totals[0] );
		},
		'one-click refuses ineligible offers before any charge' => upsellbay_with_one_click( static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$GLOBALS['upsellbay_test_orders'][6220]    = new UpsellBay_Test_OneClick_Order( 6220, 'test_gateway', 'processing' );
			$prioritizer                               = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) );
			$past                                      = gmdate( 'Y-m-d H:i:s', time() - 7200 );
			$future                                    = gmdate( 'Y-m-d H:i:s', time() + 7200 );
			$cases                                     = array(
				'draft'          => upsellbay_one_click_offer( array(), 'draft' ),
				'trashed'        => upsellbay_one_click_offer( array(), 'trash' ),
				'paused'         => upsellbay_one_click_offer( array( '_ub_status' => 'paused' ) ),
				'wrong place'    => upsellbay_one_click_offer( array( '_ub_offer_type' => 'cart_crosssell' ) ),
				'rule fails'     => upsellbay_one_click_offer( array( '_ub_rules' => array( array( 'type' => 'cart_subtotal', 'operator' => 'gte', 'value' => '1000' ) ) ) ),
				'ended'          => upsellbay_one_click_offer( array( '_ub_end_at' => $past ) ),
				'not started'    => upsellbay_one_click_offer( array( '_ub_start_at' => $future ) ),
				'no prioritizer' => null,
			);

			foreach ( $cases as $label => $offer ) {
				$harness  = upsellbay_one_click_harness( array( 'charged' ) );
				$routes   = upsellbay_one_click_routes( $harness['charger'], $offer ?? upsellbay_one_click_offer(), null === $offer ? null : $prioritizer );
				$response = $routes->one_click_accept( upsellbay_one_click_params( 6220 ) );
				assert_same( 400, $response['status'] );
				assert_same( 'ineligible', $response['data']['status'] );
				assert_false( isset( $response['data']['should_fall_back'] ) );
				if ( 0 !== $harness['adapter']->calls || array() !== $harness['state']->orders ) {
					throw new RuntimeException( 'Ineligible offer reached the charge path: ' . $label );
				}
			}

			// Control: the same wiring charges an eligible offer.
			$harness  = upsellbay_one_click_harness( array( 'charged' ) );
			$response = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), $prioritizer )->one_click_accept( upsellbay_one_click_params( 6220 ) );
			assert_true( $response['data']['success'] );
			assert_same( 1, $harness['adapter']->calls );
		} ),
		'one-click falls back to checkout without charging for an expired source order' => upsellbay_with_one_click( static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$old                                       = new UpsellBay_Test_OneClick_Order( 6230, 'test_gateway', 'completed' );
			$old->created_at                           = time() - ( 25 * 3600 );
			$undated                                   = new UpsellBay_Test_OneClick_Order( 6231, 'test_gateway', 'completed' );
			$undated->created_at                       = null;
			$GLOBALS['upsellbay_test_orders'][6230]    = $old;
			$GLOBALS['upsellbay_test_orders'][6231]    = $undated;
			$prioritizer                               = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) );

			foreach ( array( 6230, 6231 ) as $order_id ) {
				$harness  = upsellbay_one_click_harness( array( 'charged' ) );
				$response = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), $prioritizer )->one_click_accept( upsellbay_one_click_params( $order_id ) );
				assert_same( 'expired', $response['data']['status'] );
				assert_true( $response['data']['should_fall_back'] );
				assert_same( 0, $harness['adapter']->calls );
			}

			// The documented filter widens the window.
			add_filter( 'upsellbay_pro_one_click_max_order_age', static fn (): int => 48 * 3600 );
			$harness  = upsellbay_one_click_harness( array( 'charged' ) );
			$response = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), $prioritizer )->one_click_accept( upsellbay_one_click_params( 6230 ) );
			unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_one_click_max_order_age'] );
			assert_true( $response['data']['success'] );
			assert_same( 1, $harness['adapter']->calls );
		} ),
		'one-click route charges only a paid source order' => upsellbay_with_one_click( static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$prioritizer                               = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) );
			$cases                                     = array(
				6250 => array( 'pending', false ),
				6251 => array( 'on-hold', false ),
				6252 => array( 'processing', true ),
				6253 => array( 'completed', true ),
			);

			foreach ( $cases as $order_id => list( $status, $paid ) ) {
				$GLOBALS['upsellbay_test_orders'][ $order_id ] = new UpsellBay_Test_OneClick_Order( $order_id, 'test_gateway', $status );
				$harness  = upsellbay_one_click_harness( array( 'charged' ) );
				$response = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), $prioritizer )->one_click_accept( upsellbay_one_click_params( $order_id ) );

				if ( $paid ) {
					assert_true( $response['data']['success'] );
					assert_same( 1, $harness['adapter']->calls );
					continue;
				}

				assert_false( $response['data']['success'] );
				assert_same( 'unsupported', $response['data']['status'] );
				assert_true( $response['data']['should_fall_back'] );
				// Refused by the route itself: the charger is never entered.
				if ( 0 !== $harness['state']->charger_entries || 0 !== $harness['adapter']->calls || array() !== $harness['state']->orders ) {
					throw new RuntimeException( 'Unpaid source order reached the charge path: ' . $status );
				}
			}
		} ),
		'one-click charger fails closed for an unpaid source order' => static function (): void {
			$offer = upsellbay_one_click_offer();

			foreach ( array( 'pending', 'on-hold' ) as $status ) {
				$harness = upsellbay_one_click_harness( array( 'charged' ) );
				$result  = $harness['charger']->charge( new UpsellBay_Test_OneClick_Order( 6260, 'test_gateway', $status ), $offer, 'thankyou_offer' );
				assert_same( 'unsupported', $result->status() );
				if ( 0 !== $harness['adapter']->calls || array() !== $harness['state']->orders ) {
					throw new RuntimeException( 'Charger reached the adapter for an unpaid source order: ' . $status );
				}
			}

			// A source order without is_paid() fails closed too.
			$harness = upsellbay_one_click_harness( array( 'charged' ) );
			$result  = $harness['charger']->charge( new UpsellBay_Test_Order( 6261, 0, 'wc_order_6261', 'completed', array(), '0' ), $offer, 'thankyou_offer' );
			assert_same( 'unsupported', $result->status() );
			assert_same( 0, $harness['adapter']->calls );

			foreach ( array( 'processing', 'completed' ) as $status ) {
				$harness = upsellbay_one_click_harness( array( 'charged' ) );
				$result  = $harness['charger']->charge( new UpsellBay_Test_OneClick_Order( 6262, 'test_gateway', $status ), $offer, 'thankyou_offer' );
				assert_same( 'charged', $result->status() );
				assert_same( 1, $harness['adapter']->calls );
			}
		},
		'one-click never charges when the follow-on linkage cannot be written' => static function (): void {
			$harness                      = upsellbay_one_click_harness( array( 'charged' ) );
			$harness['state']->link_fails = true;

			$result = $harness['charger']->charge( new UpsellBay_Test_OneClick_Order( 6270, 'test_gateway', 'completed' ), upsellbay_one_click_offer(), 'thankyou_offer' );
			assert_same( 'unsupported', $result->status() );
			assert_same( 0, $harness['adapter']->calls );
			// The unlinked order it built is closed, not left pending.
			assert_same( 1, count( $harness['state']->orders ) );
			assert_same( 'cancelled', current( $harness['state']->orders )->get_status() );

			// The real writer reports false for an order that cannot hold meta.
			assert_false( ( new AttributionWriter() )->write_follow_on_order( new stdClass(), 1, 2 ) );
		},
		'public route rate limit is keyed on the client IP, not the client token' => upsellbay_with_one_click( static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$GLOBALS['upsellbay_test_orders'][6240]    = new UpsellBay_Test_OneClick_Order( 6240, 'test_gateway', 'completed' );
			$previous_ip                               = $_SERVER['REMOTE_ADDR'] ?? null;
			$_SERVER['REMOTE_ADDR']                    = '203.0.113.7';
			$limits                                    = new stdClass();
			$harness                                   = upsellbay_one_click_harness( array( 'charged' ) );
			$routes                                    = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) ), $limits );

			$routes->one_click_accept( array( 'token' => 'forged-one' ) + upsellbay_one_click_params( 6240 ) );
			$routes->one_click_accept( array( 'token' => 'forged-two' ) + upsellbay_one_click_params( 6240 ) );
			$routes->one_click_accept( upsellbay_one_click_params( 6240 ) );
			if ( null === $previous_ip ) {
				unset( $_SERVER['REMOTE_ADDR'] );
			} else {
				$_SERVER['REMOTE_ADDR'] = $previous_ip;
			}

			$guard_keys = array_values( array_filter( $limits->keys, static fn ( string $key ): bool => str_starts_with( $key, 'one_click_accept|' ) ) );
			assert_same( 3, count( $guard_keys ) );
			// Varying the token never yields a fresh bucket.
			assert_same( array( 'one_click_accept|ip:203.0.113.7' ), array_values( array_unique( $guard_keys ) ) );
			// Once the order is verified it gets its own bucket too.
			assert_true( in_array( 'one_click_accept_order|order:6240', $limits->keys, true ) );
		} ),
		'stripe adapter: processing, exceptions and non-card errors are in_progress, never declined' => static function (): void {
			$adapter = new StripeChargeAdapter();
			$source  = new UpsellBay_Test_OneClick_Order( 6300, 'stripe', 'completed' );
			$source->meta['_stripe_customer_id'] = 'cus_test';
			$source->meta['_stripe_source_id']   = 'pm_test';

			$cases = array(
				'processing'  => array( (object) array( 'id' => 'pi_proc1234', 'status' => 'processing' ), 'in_progress' ),
				'exception'   => array( new RuntimeException( 'timeout' ), 'in_progress' ),
				'api error'   => array( (object) array( 'error' => (object) array( 'type' => 'api_error' ) ), 'in_progress' ),
				'idempotency' => array( (object) array( 'error' => (object) array( 'type' => 'idempotency_error' ) ), 'in_progress' ),
				'capture'     => array( (object) array( 'id' => 'pi_cap', 'status' => 'requires_capture' ), 'in_progress' ),
				'empty'       => array( null, 'in_progress' ),
				'card error'  => array( (object) array( 'error' => (object) array( 'type' => 'card_error', 'code' => 'card_declined' ) ), 'declined' ),
				'unpaid pi'   => array( (object) array( 'error' => (object) array( 'type' => 'invalid_request_error', 'payment_intent' => (object) array( 'status' => 'requires_payment_method' ) ) ), 'declined' ),
				'3ds'         => array( (object) array( 'error' => (object) array( 'type' => 'card_error', 'code' => 'authentication_required' ) ), 'requires_action' ),
				'succeeded'   => array( (object) array( 'id' => 'pi_ok5678', 'status' => 'succeeded' ), 'charged' ),
			);

			foreach ( $cases as $label => $case ) {
				$new_order                                            = new UpsellBay_Test_OneClick_Order( 9500, 'stripe', 'pending' );
				$new_order->total                                     = 20.9;
				$new_order->meta[ Constants::ATTRIBUTION_SOURCE_OFFER_ID ] = 77;
				WC_Stripe_API::$responses                             = array( $case[0] );
				WC_Stripe_API::$requests                              = array();

				$result = $adapter->charge( $new_order, $source );
				if ( $case[1] !== $result->status() ) {
					throw new RuntimeException( $label . ': expected ' . $case[1] . ', got ' . $result->status() );
				}
				if ( 'processing' === $label ) {
					assert_false( $new_order->is_paid() );
					// The intent is recorded so the gateway webhook can settle the order.
					assert_same( 'pi_proc1234', $new_order->meta['_stripe_intent_id'] );
					assert_false( str_contains( $result->message(), 'pi_proc1234' ) );
				}
				// Deterministic idempotency key per source order + offer; the amount is the order total.
				$sent = WC_Stripe_API::$requests[0];
				assert_same( 'ub-' . substr( hash( 'sha256', home_url() ), 0, 10 ) . '-6300-77', $sent['key'] );
				assert_same( 2090, $sent['request']['amount'] );
			}
			// The key filter is scoped to the one request.
			assert_same( 'other', apply_filters( 'wc_stripe_idempotency_key', 'other', array() ) );
			assert_same( array(), $GLOBALS['upsellbay_test_hooks']['wc_stripe_idempotency_key'] ?? array() );
		},
		'stripe adapter: a succeeded intent the order cannot record as paid is in_progress, intent kept' => static function (): void {
			$adapter = new StripeChargeAdapter();
			$source  = new UpsellBay_Test_OneClick_Order( 6301, 'stripe', 'completed' );
			$source->meta['_stripe_customer_id'] = 'cus_test';
			$source->meta['_stripe_source_id']   = 'pm_test';

			$new_order                         = new UpsellBay_Test_OneClick_Order( 9501, 'stripe', 'pending' );
			$new_order->total                  = 19.0;
			$new_order->payment_complete_fails = true;
			WC_Stripe_API::$responses          = array( (object) array( 'id' => 'pi_paid9999', 'status' => 'succeeded' ) );

			$result = $adapter->charge( $new_order, $source );
			assert_same( 'in_progress', $result->status() );
			assert_same( 'pi_paid9999', $new_order->meta['_stripe_intent_id'] );
			assert_same( 'pi_paid9999', $new_order->transaction_id );
			assert_contains( 'pi_...9999', $result->message() );
		},
		'one-click route never reports success for a requires_action attempt or its retry' => upsellbay_with_one_click( static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$source                                    = new UpsellBay_Test_OneClick_Order( 6200, 'test_gateway', 'processing' );
			$GLOBALS['upsellbay_test_orders'][6200]    = $source;

			$harness = upsellbay_one_click_harness( array( 'requires_action', 'charged' ) );
			$routes  = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) ) );
			$params  = upsellbay_one_click_params( 6200 );

			foreach ( array( 'requires_action', 'declined' ) as $expected_status ) {
				$response = $routes->one_click_accept( $params );
				assert_same( 200, $response['status'] );
				assert_false( $response['data']['success'] );
				assert_true( $response['data']['should_fall_back'] );
				assert_same( $expected_status, $response['data']['status'] );
				// The gateway client secret never reaches the browser: no challenge UI consumes it.
				assert_false( isset( $response['data']['client_secret'] ) );
			}
			assert_same( 1, $harness['adapter']->calls );
		} ),
		'fbt miner scores, floors, and ranks co-occurring products' => static function (): void {
			// Synthetic order history: product 1 appears in 5 orders; 1+2 co-occur 3
			// times (meets the min-support floor of 3), 1+3 only once (floored out).
			$orders = array(
				array( 1, 2 ),
				array( 1, 2 ),
				array( 1, 2 ),
				array( 1, 3 ),
				array( 1 ),
			);

			$written = array();
			$repository = new FbtRepository(
				null,
				static function ( array $rows ) use ( &$written ): int {
					$written = $rows;
					return count( $rows );
				}
			);

			$miner  = new FbtMiner( $repository, static fn (): iterable => $orders );
			$result = $miner->rebuild( 365, 8 );

			assert_same( 5, $result['orders_scanned'] );

			// Only the 1->2 pair survives the min-support floor; 1->3 (co=1) is dropped.
			$pairs = array_map(
				static fn ( array $row ): string => $row['anchor_product_id'] . '->' . $row['recommended_product_id'],
				$written
			);
			assert_true( in_array( '1->2', $pairs, true ) );
			assert_false( in_array( '1->3', $pairs, true ) );

			// confidence = 3/5 = 0.6; score = 0.6 * ln(1 + 3).
			$row = null;
			foreach ( $written as $candidate ) {
				if ( 1 === $candidate['anchor_product_id'] && 2 === $candidate['recommended_product_id'] ) {
					$row = $candidate;
				}
			}
			assert_true( null !== $row );
			assert_same( 3, $row['co_occurrence_count'] );
			assert_same( 5, $row['anchor_order_count'] );
			assert_same( round( 0.6 * log( 4 ), 4 ), round( $row['score'], 4 ) );
		},
		'prioritizer resolves which offer wins when several are eligible for one placement' => static function (): void {
			$prioritizer = new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 );

			// A single eligible offer is always the winner.
			$single = array( upsellbay_phase4_offer( 1, 'checkout_bump', 50, 10 ) );
			assert_same( array( 1 ), array_column( $prioritizer->select( $single, 'checkout_bump', array(), 1 ), 'id' ) );

			// Lower _ub_priority number wins, per the documented convention
			// ("lower numbers render first" — admin field help text and
			// docs/developer/offer-schema.md agree).
			$by_priority = array(
				upsellbay_phase4_offer( 1, 'checkout_bump', 50, 10 ),
				upsellbay_phase4_offer( 2, 'checkout_bump', 50, 20 ),
			);
			assert_same( 1, $prioritizer->select( $by_priority, 'checkout_bump', array(), 1 )[0]['id'] );

			// Equal priority ties break on ascending offer id.
			$by_id_tiebreak = array(
				upsellbay_phase4_offer( 1, 'checkout_bump', 50, 10 ),
				upsellbay_phase4_offer( 2, 'checkout_bump', 50, 10 ),
			);
			assert_same( 1, $prioritizer->select( $by_id_tiebreak, 'checkout_bump', array(), 1 )[0]['id'] );

			// Offers on different placements never compete with each other —
			// each placement gets its own eligible offer independently.
			$cart_offer     = upsellbay_phase4_offer( 1, 'cart_crosssell', 50, 10 );
			$checkout_offer = upsellbay_phase4_offer( 2, 'checkout_bump', 50, 10 );
			$mixed          = array( $cart_offer, $checkout_offer );
			assert_same( 1, $prioritizer->select( $mixed, 'cart_crosssell', array(), 1 )[0]['id'] );
			assert_same( 2, $prioritizer->select( $mixed, 'checkout_bump', array(), 1 )[0]['id'] );

			// _ub_conflict_override has no effect on runtime selection — it
			// only suppresses an admin-side save-time advisory warning in
			// OfferConflictDetector. Confirm setting it doesn't change the
			// priority-based outcome above (a lower-priority, non-overridden
			// offer still loses to a higher-priority one regardless).
			$overridden_but_lower_priority        = upsellbay_phase4_offer( 1, 'checkout_bump', 50, 20 );
			$overridden_but_lower_priority['meta']['_ub_conflict_override'] = true;
			$higher_priority_no_override          = upsellbay_phase4_offer( 2, 'checkout_bump', 50, 10 );
			$override_has_no_runtime_effect       = array( $overridden_but_lower_priority, $higher_priority_no_override );
			assert_same( 2, $prioritizer->select( $override_has_no_runtime_effect, 'checkout_bump', array(), 1 )[0]['id'] );
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
					return $hits <= 2;
				},
				static fn (): string => '2026-05-30',
				new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) )
			);

			$response = $routes->bump_toggle( array( 'offer_id' => 22, 'placement' => 'checkout_bump', 'token' => $token, 'price' => '0.01', 'accepted' => true ) );

			assert_same( 200, $response['status'] );
			assert_true( $response['data']['success'] );
			assert_same( '19.000000', $response['data']['offer_price'] );
			// Rate limiting runs before token validation: a bad token under the limit (hit 2) is rejected as 403.
			assert_same( 403, $routes->bump_toggle( array( 'offer_id' => 22, 'token' => 'bad' ) )['status'] );
			// Over the limit (hit 3 onward) the request is stopped with 429 before the token is checked, valid or not.
			assert_same( 429, $routes->dismiss( array( 'offer_id' => 22, 'placement' => 'checkout_bump', 'token' => $token ) )['status'] );
			assert_same( 429, $routes->bump_toggle( array( 'offer_id' => 22, 'token' => 'bad' ) )['status'] );
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
				static fn ( int $offer_id ): ?array => 33 === $offer_id ? $offer + array( 'status' => 'publish' ) : null,
				$mutator,
				$session,
				static fn (): bool => true,
				static fn (): string => '2026-05-30',
				new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) ),
				null,
				null,
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true )
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
		'thank-you route accepts registered customer order key when rest user is anonymous' => static function (): void {
			$GLOBALS['upsellbay_test_current_user_id'] = 0;
			$GLOBALS['upsellbay_test_products'][50]   = new UpsellBay_Test_Storefront_Product( 50, 'Follow-on', '19.000000', 0 );
			$GLOBALS['upsellbay_test_orders'][701]    = new UpsellBay_Test_Order(
				701,
				12,
				'wc_order_701',
				'processing',
				array( new UpsellBay_Test_Order_Item( 99 ) ),
				'45.000000'
			);

			$cart_items = array();
			$session    = upsellbay_array_cart_session();
			$token      = ( new TokenHelper() )->sign_action(
				'thankyou_offer',
				array(
					'source_order_id'  => 701,
					'source_order_key' => 'wc_order_701',
				)
			);
			$offer      = upsellbay_phase4_offer( 34, 'thankyou_offer', 50, 1 );
			$mutator    = new CartMutator(
				$session,
				new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), static fn (): array => array( 'id' => 50, 'price' => '19.000000', 'purchasable' => true, 'visible' => true, 'in_stock' => true, 'type' => 'simple', 'subscription' => false ) ),
				new DiscountCalculator(),
				static function ( int $product_id, int $quantity, array $cart_item_data ) use ( &$cart_items ): string {
					$cart_items['registered_thankyou_item'] = compact( 'product_id', 'quantity', 'cart_item_data' );
					return 'registered_thankyou_item';
				},
				static fn (): bool => true,
				static fn (): bool => true
			);
			$routes     = new PublicOfferRoutes(
				static fn ( int $offer_id ): ?array => 34 === $offer_id ? $offer + array( 'status' => 'publish' ) : null,
				$mutator,
				$session,
				static fn (): bool => true,
				static fn (): string => '2026-05-30',
				new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) ),
				null,
				null,
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true )
			);

			$response = $routes->cart_offer_add(
				array(
					'offer_id'         => 34,
					'placement'        => 'thankyou_offer',
					'source_order_id'  => 701,
					'source_order_key' => 'wc_order_701',
					'token'            => $token,
				)
			);

			assert_same( 200, $response['status'] );
			assert_true( $response['data']['success'] );
			assert_same( 50, $cart_items['registered_thankyou_item']['product_id'] );

			unset( $GLOBALS['upsellbay_test_current_user_id'], $GLOBALS['upsellbay_test_orders'][701] );
		},
		'storefront script config carries a wp_rest nonce and built assets send it' => static function (): void {
			$session    = upsellbay_array_cart_session();
			$storefront = new StorefrontController(
				upsellbay_test_offer_repository( array() ),
				new PlacementRenderer( new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ), new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ), array(), static fn (): string => '2026-05-30' ),
				$session
			);
			$config     = $storefront->script_config();

			assert_same( 'nonce-wp_rest', $config['nonce'] );
			assert_same( $session->ensure_token(), $config['token'] );

			// Both runtime bundles that call the REST routes must attach the nonce header.
			foreach ( array( 'storefront', 'classic-checkout' ) as $entry ) {
				$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/' . $entry . '/index.js' );
				$built  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/frontend/' . $entry . '.js' );
				assert_contains( "headers['X-WP-Nonce'] = config.nonce", $source );
				assert_contains( 'X-WP-Nonce', $built );
			}
		},
		'pro registers no additional block checkout field visible to shoppers' => static function (): void {
			assert_false( class_exists( 'WPAnchorBay\\UpsellBayPro\\Integrations\\WooCommerce\\CheckoutFields' ) );

			$root     = dirname( __DIR__ ) . '/app';
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}
				$source = (string) file_get_contents( $file->getPathname() );
				assert_false( str_contains( $source, 'woocommerce_register_additional_checkout_field' ), $file->getPathname() );
				assert_false( str_contains( $source, 'upsellbay/accepted_offer' ), $file->getPathname() );
			}
		},
		'thank-you offer is shown and claimable on the same order context for customer targeted rules' => static function (): void {
			$GLOBALS['upsellbay_test_user_roles'][12]            = array( 'customer' );
			$GLOBALS['upsellbay_test_customer_order_counts'][12] = 3;
			$GLOBALS['upsellbay_test_customer_total_spent'][12]  = '250.00';

			$rule_sets = array(
				'order count' => array( array( 'type' => 'customer_order_count', 'operator' => 'gte', 'value' => 2 ) ),
				'lifetime'    => array( array( 'type' => 'customer_lifetime_spend', 'operator' => 'gte', 'value' => 200 ) ),
				'role'        => array( array( 'type' => 'user_role', 'operator' => 'contains', 'value' => array( 'customer' ) ) ),
			);
			foreach ( $rule_sets as $label => $rules ) {
				$offer                      = upsellbay_phase4_offer( 40, 'thankyou_offer', 50, 1 );
				$offer['meta']['_ub_rules'] = $rules;
				$result                     = upsellbay_thankyou_show_and_claim( $offer, 12, 12 );
				assert_true( $result['shown'], 'shown for ' . $label );
				assert_same( 200, $result['claim']['status'], 'claimable for ' . $label );
			}

			// A rule the order customer does not meet blocks both the display and the claim.
			$offer                      = upsellbay_phase4_offer( 40, 'thankyou_offer', 50, 1 );
			$offer['meta']['_ub_rules'] = array( array( 'type' => 'customer_order_count', 'operator' => 'gte', 'value' => 10 ) );
			$result                     = upsellbay_thankyou_show_and_claim( $offer, 12, 12 );
			assert_false( $result['shown'] );
			assert_same( 400, $result['claim']['status'] );

			// Guest order: empty roles and no customer facts, so customer targeting fails on both checks.
			$offer                      = upsellbay_phase4_offer( 40, 'thankyou_offer', 50, 1 );
			$offer['meta']['_ub_rules'] = array( array( 'type' => 'customer_order_count', 'operator' => 'gte', 'value' => 1 ) );
			$guest                      = upsellbay_thankyou_show_and_claim( $offer, 0, 0 );
			assert_false( $guest['shown'] );
			assert_same( 400, $guest['claim']['status'] );

			$offer['meta']['_ub_rules'] = array( array( 'type' => 'user_role', 'operator' => 'contains', 'value' => array( 'customer' ) ) );
			$guest_role                 = upsellbay_thankyou_show_and_claim( $offer, 0, 0 );
			assert_false( $guest_role['shown'] );
			assert_same( 400, $guest_role['claim']['status'] );

			unset( $GLOBALS['upsellbay_test_user_roles'][12], $GLOBALS['upsellbay_test_customer_order_counts'][12], $GLOBALS['upsellbay_test_customer_total_spent'][12] );
		},
		'thank-you offer is shown and claimable on the same order context for product targeted rules' => static function (): void {
			$GLOBALS['upsellbay_test_product_terms']['product_cat'][99] = array( 7 );

			$product_rule  = array( array( 'type' => 'cart_product', 'operator' => 'contains', 'value' => array( 99 ) ) );
			$category_rule = array( array( 'type' => 'cart_category', 'operator' => 'contains', 'value' => array( 7 ) ) );
			$subtotal_rule = array( array( 'type' => 'cart_subtotal', 'operator' => 'gte', 'value' => 40 ) );
			foreach ( array( 'product' => $product_rule, 'category' => $category_rule, 'subtotal' => $subtotal_rule ) as $label => $rules ) {
				$offer                      = upsellbay_thankyou_offer_with_rules( $rules );
				$result                     = upsellbay_thankyou_show_and_claim( $offer, 0, 0 );
				assert_true( $result['shown'], 'shown for ' . $label );
				assert_same( 200, $result['claim']['status'], 'claimable for ' . $label );
			}

			// Order contains a different product: neither shown nor claimable.
			$other_rule = array( array( 'type' => 'cart_product', 'operator' => 'contains', 'value' => array( 12345 ) ) );
			$result     = upsellbay_thankyou_show_and_claim( upsellbay_thankyou_offer_with_rules( $other_rule ), 0, 0 );
			assert_false( $result['shown'] );
			assert_same( 400, $result['claim']['status'] );

			unset( $GLOBALS['upsellbay_test_product_terms']['product_cat'][99] );
		},
		'thank-you offer without targeting is unchanged for guest and customer orders' => static function (): void {
			$GLOBALS['upsellbay_test_user_roles'][12] = array( 'customer' );
			$offer                                    = upsellbay_thankyou_offer_with_rules( array() );

			foreach ( array( 0, 12 ) as $user_id ) {
				$result = upsellbay_thankyou_show_and_claim( $offer, $user_id, $user_id );
				assert_true( $result['shown'] );
				assert_same( 200, $result['claim']['status'] );
			}

			unset( $GLOBALS['upsellbay_test_user_roles'][12] );
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

			$data = $extender->get_cart_offers_data();

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
 * Thank-you offer fixture with the given targeting rules.
 *
 * @param array<int, array<string, mixed>> $rules Rules.
 * @return array<string, mixed>
 */
function upsellbay_thankyou_offer_with_rules( array $rules ): array {
	$offer                      = upsellbay_phase4_offer( 40, 'thankyou_offer', 50, 1 );
	$offer['meta']['_ub_rules'] = $rules;

	return $offer;
}

/**
 * Run the thank-you display and the claim route for the same order.
 *
 * The order holds product 99 (subtotal 45). A guest order (user 0) is verified by
 * the order key on both paths.
 *
 * @param array<string, mixed> $offer           Offer.
 * @param int                  $order_user_id   Customer on the order (0 = guest).
 * @param int                  $current_user_id Logged-in user viewing the page.
 * @return array{shown: bool, claim: array<string, mixed>}
 */
function upsellbay_thankyou_show_and_claim( array $offer, int $order_user_id, int $current_user_id ): array {
	$GLOBALS['upsellbay_test_products'][50]             = new UpsellBay_Test_Storefront_Product( 50, 'Follow-on', '19.000000', 0 );
	$GLOBALS['upsellbay_test_current_user_id']          = $current_user_id;
	$GLOBALS['upsellbay_test_current_user_can']         = array( 'manage_woocommerce' => false );
	$GLOBALS['upsellbay_test_orders'][800]              = new UpsellBay_Test_Order( 800, $order_user_id, 'wc_order_800', 'processing', array( new UpsellBay_Test_Order_Item( 99 ) ), '45.000000' );
	$_GET['key']                                        = 'wc_order_800';

	$session  = upsellbay_array_cart_session();
	$stats    = new StatsRepository( static function (): void {}, static fn (): array => array() );
	$renderer = new PlacementRenderer(
		new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ),
		new AnalyticsRecorder( $stats ),
		array( 'thankyou_offer' => new ThankYouOfferRenderer( new DiscountCalculator() ) ),
		static fn (): string => '2026-05-30'
	);
	$storefront = new StorefrontController(
		upsellbay_test_offer_repository( array( 40 => $offer ) ),
		$renderer,
		$session,
		new Settings( static fn (): array => array(), static fn (): bool => true )
	);

	ob_start();
	$storefront->render_thankyou_offer( 800 );
	$html = (string) ob_get_clean();

	$mutator = new CartMutator(
		$session,
		new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), static fn (): array => array( 'id' => 50, 'price' => '19.000000', 'purchasable' => true, 'visible' => true, 'in_stock' => true, 'type' => 'simple', 'subscription' => false ) ),
		new DiscountCalculator(),
		static fn (): string => 'thankyou_item',
		static fn (): bool => true,
		static fn (): bool => true
	);
	$routes  = new PublicOfferRoutes(
		static fn ( int $offer_id ): ?array => 40 === $offer_id ? $offer + array( 'status' => 'publish' ) : null,
		$mutator,
		$session,
		static fn (): bool => true,
		static fn (): string => '2026-05-30',
		new AnalyticsService( new AnalyticsRecorder( $stats ) ),
		null,
		null,
		new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 )
	);
	$claim   = $routes->cart_offer_add(
		array(
			'offer_id'         => 40,
			'placement'        => 'thankyou_offer',
			'source_order_id'  => 800,
			'source_order_key' => 'wc_order_800',
			'token'            => ( new TokenHelper() )->sign_action(
				'thankyou_offer',
				array(
					'source_order_id'  => 800,
					'source_order_key' => 'wc_order_800',
				)
			),
		)
	);

	unset( $_GET['key'], $GLOBALS['upsellbay_test_orders'][800], $GLOBALS['upsellbay_test_current_user_id'] );

	return array(
		'shown' => str_contains( $html, 'data-upsellbay-source-order-id="800"' ),
		'claim' => $claim,
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

	/**
	 * Get price.
	 */
	public function get_price(): string {
		return $this->price;
	}
}

if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
	/**
	 * Stub matching the real WooCommerce Subscriptions class the discount
	 * applier checks for, so the bailout branch is testable without the
	 * actual extension installed.
	 */
	final class WC_Subscriptions_Product {
		/**
		 * Controls what is_subscription() returns for the next check.
		 *
		 * @var bool
		 */
		public static bool $is_subscription_result = false;

		/**
		 * Mirrors the real static API.
		 *
		 * @param mixed $product Product.
		 */
		public static function is_subscription( $product ): bool {
			unset( $product );
			return self::$is_subscription_result;
		}
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

/**
 * Build a OneClickCharger wired to an in-memory order store and a scripted adapter.
 *
 * Linkage is recorded at write_follow_on_order() time, like the real AttributionWriter,
 * so a retry sees the same follow-on order the first attempt created.
 *
 * @param array<int, string> $outcomes Adapter outcomes, consumed one per charge() call.
 * @return array{charger: OneClickCharger, adapter: UpsellBay_Test_Scripted_Charge_Adapter, state: stdClass}
 */
function upsellbay_one_click_harness( array $outcomes ): array {
	$GLOBALS['upsellbay_test_products'][50] = new UpsellBay_Test_Storefront_Product( 50, 'Follow-on', '19.00', 0 );

	$state           = new stdClass();
	$state->orders   = array();
	$state->links    = array();
	$state->next_id  = 9000;
	$state->tax_rate = 0.0;

	$adapter  = new UpsellBay_Test_Scripted_Charge_Adapter( $outcomes );
	$registry = new PaymentChargeAdapterRegistry();
	$registry->register( $adapter );

	$state->link_fails      = false;
	$state->charger_entries = 0;

	$attribution = new class( $state ) implements FollowOnLinkageWriter {
		private stdClass $state;

		public function __construct( stdClass $state ) {
			$this->state = $state;
		}

		public function write_follow_on_order( object $order, int $source_order_id, int $offer_id ): bool {
			if ( $this->state->link_fails ) {
				return false;
			}
			$this->state->links[ $source_order_id . ':' . $offer_id ] = (int) $order->get_id();
			return true;
		}
	};

	$charger = new OneClickCharger(
		$registry,
		new DiscountCalculator(),
		$attribution,
		static function ( object $source ) use ( $state ): object {
			unset( $source );
			$order                             = new UpsellBay_Test_OneClick_Order( ++$state->next_id, '', 'pending' );
			$order->tax_rate                   = $state->tax_rate;
			$state->orders[ $order->get_id() ] = $order;
			return $order;
		},
		static function ( int $source_order_id, int $offer_id ) use ( $state ): int {
			++$state->charger_entries;
			return (int) ( $state->links[ $source_order_id . ':' . $offer_id ] ?? 0 );
		},
		static fn ( int $order_id ): ?object => $state->orders[ $order_id ] ?? null
	);

	return array(
		'charger' => $charger,
		'adapter' => $adapter,
		'state'   => $state,
	);
}

/**
 * A published, active thank-you offer for product 50, with meta overrides.
 *
 * @param array<string, mixed> $meta   Meta overrides.
 * @param string               $status Offer post status.
 * @return array<string, mixed>
 */
function upsellbay_one_click_offer( array $meta = array(), string $status = 'publish' ): array {
	return array(
		'id'     => 77,
		'status' => $status,
		'meta'   => array_replace(
			array(
				'_ub_offer_type'       => 'thankyou_offer',
				'_ub_status'           => 'active',
				'_ub_offer_product_id' => 50,
				'_ub_rules'            => array(),
			),
			$meta
		),
	);
}

/**
 * PublicOfferRoutes wired for one-click tests, recording every rate-limit key.
 *
 * @param OneClickCharger       $charger     Charger.
 * @param array<string, mixed>  $offer       Offer the loader returns for id 77.
 * @param OfferPrioritizer|null $eligibility Eligibility evaluator (null = not wired).
 * @param stdClass|null         $limits      Receives ->keys (array of "endpoint|key").
 */
function upsellbay_one_click_routes( OneClickCharger $charger, array $offer, ?OfferPrioritizer $eligibility, ?stdClass $limits = null ): PublicOfferRoutes {
	$limits       = $limits ?? new stdClass();
	$limits->keys = array();
	$product      = static fn (): array => array(
		'id'          => 50,
		'price'       => '19.00',
		'purchasable' => true,
		'visible'     => true,
		'in_stock'    => true,
		'type'        => 'simple',
	);

	return new PublicOfferRoutes(
		static fn ( int $offer_id ): ?array => 77 === $offer_id ? $offer : null,
		new CartMutator( upsellbay_array_cart_session(), new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), $product ), new DiscountCalculator(), static fn (): string => '', static fn (): bool => true, static fn (): bool => true ),
		upsellbay_array_cart_session(),
		static function ( string $endpoint, string $client_key ) use ( $limits ): bool {
			$limits->keys[] = $endpoint . '|' . $client_key;
			return true;
		},
		static fn (): string => '2026-05-30',
		new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) ),
		new TokenHelper(),
		$charger,
		$eligibility
	);
}

/**
 * One-click accept params for a source order, signed like the thank-you page does.
 *
 * @return array<string, mixed>
 */
function upsellbay_one_click_params( int $source_order_id ): array {
	return array(
		'offer_id'         => 77,
		'placement'        => 'thankyou_offer',
		'source_order_id'  => $source_order_id,
		'source_order_key' => 'wc_order_' . $source_order_id,
		'token'            => ( new TokenHelper() )->sign_action(
			'thankyou_offer',
			array(
				'source_order_id'  => $source_order_id,
				'source_order_key' => 'wc_order_' . $source_order_id,
			)
		),
	);
}

/**
 * Payment adapter double that replays scripted gateway outcomes and counts charges.
 *
 * @since 1.1.0
 */
final class UpsellBay_Test_Scripted_Charge_Adapter implements PaymentChargeAdapter {
	/** @var array<int, string> */
	private array $outcomes;

	/** @var int */
	public int $calls = 0;

	/**
	 * @param array<int, string> $outcomes Outcomes in call order.
	 */
	public function __construct( array $outcomes ) {
		$this->outcomes = $outcomes;
	}

	public function gateway_id(): string {
		return 'test_gateway';
	}

	public function supports_one_click( object $source_order ): bool {
		unset( $source_order );
		return true;
	}

	/** @var array<int, float> Order totals seen at charge time. */
	public array $charged_totals = array();

	public function charge( object $new_order, object $source_order ): OneClickResult {
		unset( $source_order );
		$outcome = $this->outcomes[ $this->calls ] ?? 'declined';
		++$this->calls;
		$this->charged_totals[] = (float) $new_order->get_total();

		switch ( $outcome ) {
			case 'charged':
				$new_order->payment_complete( 'txn_' . $new_order->get_id() );
				return OneClickResult::charged( (int) $new_order->get_id() );
			case 'requires_action':
				return OneClickResult::requires_action( (int) $new_order->get_id(), 'pi_secret_test' );
			case 'unsupported':
				return OneClickResult::unsupported( 'gateway lost its token' );
			case 'in_progress':
				return OneClickResult::in_progress( (int) $new_order->get_id(), 'gateway still processing' );
			case 'bogus':
				return OneClickResult::duplicate( (int) $new_order->get_id() );
			case 'throw':
				throw new RuntimeException( 'gateway transport failure sk_live_secret customer@example.com' );
			default:
				return OneClickResult::declined( 'card declined' );
		}
	}
}

if ( ! class_exists( 'WC_Stripe_API' ) ) {
	/**
	 * WC_Stripe_API double: replays scripted responses and records each request with
	 * the Idempotency-Key the real client would send (after its filter runs).
	 */
	class WC_Stripe_API {
		/** @var array<int, mixed> */
		public static array $responses = array();
		/** @var array<int, array<string, mixed>> */
		public static array $requests = array();

		/**
		 * @param array<string, mixed> $request Request body.
		 * @return mixed
		 */
		public static function request( $request, $api = 'charges', $method = 'POST' ) {
			$key              = apply_filters( 'wc_stripe_idempotency_key', 'payment_intents' === $api ? 'random-uuid' : null, $request );
			self::$requests[] = array(
				'request' => $request,
				'api'     => $api,
				'method'  => $method,
				'key'     => $key,
			);
			$next             = array_shift( self::$responses );
			if ( $next instanceof Throwable ) {
				throw $next;
			}

			return $next;
		}
	}
}

/**
 * WC_Order double for the one-click flow: source order and follow-on order alike.
 *
 * @since 1.1.0
 */
final class UpsellBay_Test_OneClick_Order {
	private int $id;
	private string $payment_method;
	private string $status;
	/** @var array<int, array<string, mixed>> */
	public array $items = array();
	/** @var array<int, string> */
	public array $notes = array();
	/** @var array<string, mixed> */
	public array $meta = array();
	/** @var float Tax rate calculate_totals( true ) applies (stands in for WC tax tables). */
	public float $tax_rate = 0.0;
	/** @var bool|null Last calculate_totals() argument. */
	public ?bool $last_and_taxes = null;
	/** @var float */
	public float $total = 0.0;
	/** @var int|null Creation timestamp; null = no date. */
	public ?int $created_at;
	/** @var bool Whether payment_complete() returns false without marking paid. */
	public bool $payment_complete_fails = false;
	/** @var string */
	public string $transaction_id = '';

	public function __construct( int $id, string $payment_method, string $status ) {
		$this->id             = $id;
		$this->payment_method = $payment_method;
		$this->status         = $status;
		$this->created_at     = time() - 60;
	}

	public function get_date_created(): ?DateTimeImmutable {
		return null === $this->created_at ? null : new DateTimeImmutable( '@' . $this->created_at );
	}

	/**
	 * @return mixed
	 */
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}

	/**
	 * @param mixed $value Value.
	 */
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	public function add_order_note( string $note ): int {
		$this->notes[] = $note;
		return count( $this->notes );
	}

	public function get_total(): float {
		return $this->total;
	}

	public function get_currency(): string {
		return 'USD';
	}

	public function set_transaction_id( string $transaction_id ): void {
		$this->transaction_id = $transaction_id;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_order_key(): string {
		return 'wc_order_' . $this->id;
	}

	public function get_user_id(): int {
		return 0;
	}

	public function get_customer_id(): int {
		return 0;
	}

	public function get_payment_method(): string {
		return $this->payment_method;
	}

	public function set_payment_method( string $payment_method ): void {
		$this->payment_method = $payment_method;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function is_paid(): bool {
		return in_array( $this->status, array( 'processing', 'completed' ), true );
	}

	public function update_status( string $status, string $note = '' ): bool {
		$this->status  = $status;
		$this->notes[] = $note;
		return true;
	}

	public function payment_complete( string $transaction_id = '' ): bool {
		unset( $transaction_id );
		if ( $this->payment_complete_fails ) {
			return false;
		}
		$this->status = 'processing';
		return true;
	}

	/**
	 * @param array<string, mixed> $args Line args.
	 */
	public function add_product( object $product, int $quantity, array $args = array() ): int {
		$this->items[] = array(
			'product'  => $product,
			'quantity' => $quantity,
			'args'     => $args,
		);
		return count( $this->items );
	}

	public function calculate_totals( bool $and_taxes = true ): void {
		$this->last_and_taxes = $and_taxes;
		$lines                = 0.0;
		foreach ( $this->items as $item ) {
			$lines += (float) ( $item['args']['total'] ?? 0 );
		}
		$this->total = round( $lines * ( 1 + ( $and_taxes ? $this->tax_rate : 0.0 ) ), 2 );
	}

	public function save(): int {
		return $this->id;
	}
}
