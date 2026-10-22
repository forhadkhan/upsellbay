<?php
/**
 * Pro 1.0.0 release hardening tests: one-click gating and the security review fixes.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Api\Routes\LicenseRoute;
use WPAnchorBay\UpsellBayPro\Api\Routes\PublicOfferRoutes;
use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Container;
use WPAnchorBay\UpsellBayPro\Core\Plugin;
use WPAnchorBay\UpsellBayPro\Data\CartSession;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartValidator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\OfferLineRepricer;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBayPro\Domain\Logging\ApiFailureLog;
use WPAnchorBay\UpsellBayPro\Domain\Logging\LoggerInterface;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\PaymentChargeAdapterRegistry;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ThankYouOfferRenderer;
use WPAnchorBay\UpsellBayPro\Integrations\Licensing\LicenseClient;
use WPAnchorBay\UpsellBayPro\Utils\ClientIp;
use WPAnchorBay\UpsellBayPro\Utils\ImportExporter;

/**
 * Run a test with the reserved one-click switch turned on, then turn it off again.
 *
 * One-click is off in 1.0.0; its unit tests keep guarding the 1.1 code by
 * switching it on explicitly.
 *
 * @param callable $test Test body.
 */
function upsellbay_with_one_click( callable $test ): callable {
	return static function () use ( $test ): void {
		add_filter( 'upsellbay_pro_one_click_enabled', '__return_true' );
		try {
			$test();
		} finally {
			unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_one_click_enabled'] );
		}
	};
}

if ( ! function_exists( '__return_true' ) ) {
	/**
	 * WordPress core helper.
	 */
	function __return_true(): bool {
		return true;
	}
}

/**
 * Returns release hardening test cases.
 *
 * @return array<string, callable>
 */
function upsellbay_release_hardening_tests(): array {
	return array(
		// One-click gating.
		'one-click switch is off by default and only a strict true turns it on' => static function (): void {
			assert_false( Constants::one_click_enabled() );

			add_filter( 'upsellbay_pro_one_click_enabled', static fn (): string => 'yes' );
			assert_false( Constants::one_click_enabled() );
			unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_one_click_enabled'] );

			upsellbay_with_one_click( static fn () => assert_true( Constants::one_click_enabled() ) )();
			assert_false( Constants::one_click_enabled() );
		},
		'one-click route is not registered while the switch is off' => static function (): void {
			$harness = upsellbay_one_click_harness( array( 'charged' ) );
			$routes  = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) ) );

			$GLOBALS['upsellbay_test_rest_routes'] = array();
			$routes->register_one_click_route();
			assert_same( array(), $GLOBALS['upsellbay_test_rest_routes'] );

			upsellbay_with_one_click( static fn () => $routes->register_one_click_route() )();
			assert_same( array( 'upsellbay/v1/one-click-accept' ), $GLOBALS['upsellbay_test_rest_routes'] );
			$GLOBALS['upsellbay_test_rest_routes'] = array();
		},
		'one-click accept refuses with 404 and never charges while the switch is off' => static function (): void {
			$GLOBALS['upsellbay_test_orders'][4100] = new UpsellBay_Test_OneClick_Order( 4100, 'stripe', 'processing' );
			$harness                                = upsellbay_one_click_harness( array( 'charged' ) );
			$routes                                 = upsellbay_one_click_routes( $harness['charger'], upsellbay_one_click_offer(), new OfferPrioritizer( new RuleEvaluator( new RuleParser() ) ) );

			$response = $routes->one_click_accept( upsellbay_one_click_params( 4100 ) );

			assert_same( 404, $response['status'] );
			assert_same( 0, $harness['adapter']->calls );
			unset( $GLOBALS['upsellbay_test_orders'][4100] );
		},
		'plugin builds the public routes without any payment adapter while the switch is off' => static function (): void {
			$built = upsellbay_plugin_routes_build_registry();
			assert_false( $built );

			$built_on = false;
			upsellbay_with_one_click(
				static function () use ( &$built_on ): void {
					$built_on = upsellbay_plugin_routes_build_registry();
				}
			)();
			assert_true( $built_on );
		},
		'thank-you card carries no one-click marker while the switch is off' => static function (): void {
			$GLOBALS['upsellbay_test_products'][50] = new UpsellBay_Test_Storefront_Product( 50, 'Follow-on', '19.000000', 0 );
			$renderer                               = new ThankYouOfferRenderer( new DiscountCalculator() );
			$offer                                  = upsellbay_phase4_offer( 40, 'thankyou_offer', 50, 1 );
			$offer['meta']['_ub_button_text']       = '';
			$context                                = array(
				'one_click'       => true,
				'source_order_id' => 800,
			);

			$off = $renderer->render_offer( $offer, $context );
			assert_false( str_contains( $off, 'one-click' ) );
			assert_contains( 'Add to a new checkout', $off );
			assert_contains( 'starts a separate checkout', $off );

			$on = '';
			upsellbay_with_one_click(
				static function () use ( &$on, $renderer, $offer, $context ): void {
					$on = $renderer->render_offer( $offer, $context );
				}
			)();
			assert_contains( 'data-upsellbay-one-click="1"', $on );
		},

		// SH-4: one unit discounted.
		'offer price discounts exactly one unit of the line' => static function (): void {
			$applier = new DiscountApplier( static fn (): int => 2 );

			assert_same( '21.000000', $applier->unit_price( '21.000000', '30.000000', 1 ) );
			assert_same( '27.00', $applier->unit_price( '21.000000', '30.000000', 3 ) );

			// 10.01 + 2 x 25.00 = 60.01 does not divide by 3; unit x 3 must still round to 60.01.
			$unit = $applier->unit_price( '10.010000', '25.000000', 3 );
			assert_same( '60.01', number_format( round( (float) $unit * 3, 2 ), 2, '.', '' ) );

			$product = new UpsellBay_Test_Product( '30.000000' );
			$applier->apply(
				array(
					'data'     => $product,
					'quantity' => 4,
				),
				'21.000000',
				'30.000000'
			);
			assert_same( '27.75', $product->price );

			$applier->apply(
				array(
					'data'     => $product,
					'quantity' => 4,
				),
				null,
				'30.000000'
			);
			assert_same( '30.000000', $product->price );
		},

		// SH-5: price recomputed from current state on every totals run.
		'repricer recomputes the offer price and ignores the stored one' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_repricer_offer() )->reprice( $cart );

			assert_same( '21.000000', $cart->cart_contents['offer']['data']->price );
			assert_same( '21.000000', $cart->cart_contents['offer']['_ub_offer_price'] );
			assert_same( '9.000000', $cart->cart_contents['offer'][ Constants::ATTRIBUTION_DISCOUNT_AMOUNT ] );
		},
		'repricer discounts one unit when the quantity is raised' => static function (): void {
			$cart = upsellbay_repricer_cart( 3 );
			upsellbay_repricer( upsellbay_repricer_offer() )->reprice( $cart );

			assert_same( '27.00', $cart->cart_contents['offer']['data']->price );
		},
		'repricer follows a product price change' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_repricer_offer(), '40.000000' )->reprice( $cart );

			assert_same( '28.000000', $cart->cart_contents['offer']['data']->price );
		},
		'repricer charges the regular price once the offer no longer qualifies' => static function (): void {
			$cases = array(
				'draft'           => array( upsellbay_repricer_offer( array(), 'draft' ), true ),
				'paused'          => array( upsellbay_repricer_offer( array( '_ub_status' => 'paused' ) ), true ),
				'expired'         => array( upsellbay_repricer_offer( array( '_ub_end_at' => gmdate( 'Y-m-d H:i:s', 500000 ) ) ), true ),
				'not started'     => array( upsellbay_repricer_offer( array( '_ub_start_at' => gmdate( 'Y-m-d H:i:s', 2000000 ) ) ), true ),
				'placement off'   => array( upsellbay_repricer_offer(), false ),
				'product changed' => array( upsellbay_repricer_offer( array( '_ub_offer_product_id' => 51 ) ), true ),
				'trigger changed' => array( upsellbay_repricer_offer( array( '_ub_trigger_product_ids' => array( 77 ) ) ), true ),
				'offer deleted'   => array( null, true ),
			);

			foreach ( $cases as $label => $case ) {
				$cart = upsellbay_repricer_cart( 2 );
				upsellbay_repricer( $case[0], '30.000000', $case[1] )->reprice( $cart );

				assert_same( '30.000000', $cart->cart_contents['offer']['data']->price, $label );
				assert_same( '', $cart->cart_contents['offer']['_ub_offer_price'], $label );
				assert_same( '0.000000', $cart->cart_contents['offer'][ Constants::ATTRIBUTION_DISCOUNT_AMOUNT ], $label );
			}
		},
		'repricer keeps the discount while the trigger product is still in the cart' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_repricer_offer( array( '_ub_trigger_product_ids' => array( 99 ) ) ) )->reprice( $cart );

			assert_same( '21.000000', $cart->cart_contents['offer']['data']->price );
		},
		// SQ-4: an offer line with no readable price is removed, not left at the price it carries.
		'repricer removes an offer line whose regular price is unknown' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			( new OfferLineRepricer(
				static fn (): array => upsellbay_repricer_offer(),
				upsellbay_repricer_prioritizer(),
				new DiscountCalculator(),
				new DiscountApplier( static fn (): int => 2 ),
				static fn (): bool => true,
				static fn (): ?string => null
			) )->reprice( $cart );

			assert_false( isset( $cart->cart_contents['offer'] ) );
			assert_true( isset( $cart->cart_contents['trigger'] ) );
		},
		'repricer leaves checkout bump lines to the free host' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			$cart->cart_contents['offer'][ Constants::ATTRIBUTION_OFFER_PLACEMENT ] = 'checkout_bump';
			$cart->cart_contents['offer']['data']->price                           = '5.000000';

			upsellbay_repricer( upsellbay_repricer_offer( array( '_ub_offer_type' => 'checkout_bump' ) ) )->reprice( $cart );

			assert_same( '5.000000', $cart->cart_contents['offer']['data']->price );
			assert_same( '1.000000', $cart->cart_contents['offer']['_ub_offer_price'] );
		},
		'repricer voids a thank-you discount when the source order is gone or cancelled' => static function (): void {
			$offer  = upsellbay_repricer_offer( array( '_ub_offer_type' => 'thankyou_offer' ) );
			$orders = array(
				'missing'    => null,
				'cancelled'  => new UpsellBay_Test_Order( 810, 0, 'wc_order_810', 'cancelled', array( new UpsellBay_Test_Order_Item( 99 ) ), '45.000000' ),
				'processing' => new UpsellBay_Test_Order( 810, 0, 'wc_order_810', 'processing', array( new UpsellBay_Test_Order_Item( 99 ) ), '45.000000' ),
			);

			foreach ( $orders as $label => $order ) {
				$cart = upsellbay_repricer_cart( 1 );
				$cart->cart_contents['offer'][ Constants::ATTRIBUTION_OFFER_PLACEMENT ] = 'thankyou_offer';
				$cart->cart_contents['offer'][ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] = 810;

				( new OfferLineRepricer(
					static fn (): array => $offer,
					upsellbay_repricer_prioritizer(),
					new DiscountCalculator(),
					new DiscountApplier( static fn (): int => 2 ),
					static fn (): bool => true,
					static fn (): string => '30.000000',
					static fn (): ?object => $order
				) )->reprice( $cart );

				assert_same( 'processing' === $label ? '21.000000' : '30.000000', $cart->cart_contents['offer']['data']->price, $label );
			}
		},

		// SH-6: cart-offer-add claims only an offer the storefront would show.
		'cart offer add refuses an offer the storefront would not show' => static function (): void {
			$cases = array(
				'draft'            => array( upsellbay_claim_offer( array(), 'draft' ), 'cart_crosssell', false ),
				'paused'           => array( upsellbay_claim_offer( array( '_ub_status' => 'paused' ) ), 'cart_crosssell', false ),
				'expired'          => array( upsellbay_claim_offer( array( '_ub_end_at' => gmdate( 'Y-m-d H:i:s', 500000 ) ) ), 'cart_crosssell', false ),
				'trigger mismatch' => array( upsellbay_claim_offer( array( '_ub_trigger_product_ids' => array( 77 ) ) ), 'cart_crosssell', false ),
				'dismissed'        => array( upsellbay_claim_offer(), 'cart_crosssell', true ),
			);

			foreach ( $cases as $label => $case ) {
				$harness  = upsellbay_claim_routes( $case[0] );
				if ( $case[2] ) {
					$harness['session']->dismiss_offer( 62, 'cart_crosssell' );
				}
				$response = $harness['routes']->cart_offer_add( upsellbay_claim_params( $harness['token'], $case[1] ) );

				assert_same( 400, $response['status'], $label );
				assert_same( 'This offer is no longer available.', $response['data']['message'], $label );
				assert_same( array(), $harness['added']->getArrayCopy(), $label );
			}
		},
		'cart offer add accepts a published active offer and rejects checkout bumps' => static function (): void {
			$harness  = upsellbay_claim_routes( upsellbay_claim_offer() );
			$response = $harness['routes']->cart_offer_add( upsellbay_claim_params( $harness['token'], 'cart_crosssell' ) );
			assert_same( 200, $response['status'] );
			assert_same( array( 50 ), $harness['added']->getArrayCopy() );

			$bump  = upsellbay_claim_offer( array( '_ub_offer_type' => 'checkout_bump' ) );
			$other = upsellbay_claim_routes( $bump );
			$reply = $other['routes']->cart_offer_add( upsellbay_claim_params( $other['token'], 'checkout_bump' ) );
			assert_same( 400, $reply['status'] );
			assert_same( 'This offer is not valid for this placement.', $reply['data']['message'] );
			assert_same( array(), $other['added']->getArrayCopy() );
		},
		'cart offer add fails closed without an eligibility evaluator' => static function (): void {
			$harness  = upsellbay_claim_routes( upsellbay_claim_offer(), false );
			$response = $harness['routes']->cart_offer_add( upsellbay_claim_params( $harness['token'], 'cart_crosssell' ) );

			assert_same( 400, $response['status'] );
			assert_same( array(), $harness['added']->getArrayCopy() );
		},

		// SH-7: minimal, throttled failure logging and REMOTE_ADDR-only client IP.
		'failed public requests hand listeners only the offer and placement' => static function (): void {
			$seen = array();
			add_action(
				'upsellbay_pro_api_request_failed',
				static function ( string $endpoint, string $reason, array $request ) use ( &$seen ): void {
					$seen[] = array( $endpoint, $reason, $request );
				},
				10,
				3
			);

			$harness = upsellbay_claim_routes( upsellbay_claim_offer( array(), 'draft' ) );
			$params  = upsellbay_claim_params( 'not-the-token', 'cart_crosssell' ) + array(
				'source_order_id'  => 700,
				'source_order_key' => 'wc_order_secret',
			);
			$harness['routes']->cart_offer_add( $params );
			$harness['routes']->cart_offer_add( upsellbay_claim_params( $harness['token'], 'cart_crosssell' ) );
			unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_api_request_failed'] );

			assert_same( array( 'cart_offer_add', 'invalid_token' ), array_slice( $seen[0], 0, 2 ) );
			assert_same( array( 'cart_offer_add', 'ineligible_offer' ), array_slice( $seen[1], 0, 2 ) );
			foreach ( $seen as $event ) {
				assert_same(
					array(
						'offer_id'  => 62,
						'placement' => 'cart_crosssell',
					),
					$event[2]
				);
			}
		},
		'failure log writes one minimal row per endpoint and reason each window' => static function (): void {
			$logger = new UpsellBay_Test_Recording_Logger();
			$taken  = array();
			$log    = new ApiFailureLog(
				$logger,
				static function ( string $key ) use ( &$taken ): bool {
					if ( isset( $taken[ $key ] ) ) {
						return false;
					}
					$taken[ $key ] = true;
					return true;
				}
			);

			$noisy = array(
				'offer_id'         => 5,
				'token'            => 'raw-session-token', // gate: allow -- test fixture.
				'source_order_key' => 'wc_order_secret',
			);
			for ( $i = 0; $i < 50; $i++ ) {
				$log->record( 'cart_offer_add', 'rate_limited', array( 'offer_id' => $i ) + $noisy );
				$log->record( 'cart_offer_add', 'invalid_token', array( 'offer_id' => $i ) + $noisy );
			}
			$log->record( 'cart_offer_add', 'ineligible_offer', $noisy );
			$log->record( 'cart_offer_add', 'ineligible_offer', $noisy );
			$log->record( 'cart_offer_add', 'ineligible_offer', array( 'offer_id' => 6 ) );

			assert_same( 4, count( $logger->rows ) );
			$dump = wp_json_encode( $logger->rows );
			assert_false( str_contains( $dump, 'raw-session-token' ) );
			assert_false( str_contains( $dump, 'wc_order_secret' ) );
			assert_same(
				array(
					'endpoint' => 'cart_offer_add',
					'reason'   => 'rate_limited',
				),
				$logger->rows[0]['context']['metadata']
			);
			assert_false( isset( $logger->rows[0]['context']['object_id'] ) );
			assert_same( 5, $logger->rows[2]['context']['object_id'] );
		},
		'client IP comes from REMOTE_ADDR only and a filter can replace it with a valid IP' => static function (): void {
			$server                          = $_SERVER;
			$_SERVER['REMOTE_ADDR']          = '203.0.113.9';
			$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
			$_SERVER['HTTP_CLIENT_IP']       = '198.51.100.2';

			try {
				assert_same( '203.0.113.9', ClientIp::current() );

				add_filter( 'upsellbay_pro_rate_limit_client_ip', static fn (): string => 'not-an-ip' );
				assert_same( '203.0.113.9', ClientIp::current() );
				unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_rate_limit_client_ip'] );

				add_filter( 'upsellbay_pro_rate_limit_client_ip', static fn (): string => '192.0.2.44' );
				assert_same( '192.0.2.44', ClientIp::current() );
			} finally {
				unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_rate_limit_client_ip'] );
				$_SERVER = $server;
			}

			assert_same( '203.0.113.0', ClientIp::anonymize( '203.0.113.9' ) );
			assert_same( '2001:db8:abcd::', ClientIp::anonymize( '2001:db8:abcd:12:1:2:3:4' ) );
			assert_same( '', ClientIp::anonymize( 'unknown' ) );
		},
		'IPv6 clients share one rate-limit bucket per /64' => static function (): void {
			assert_same( '203.0.113.9', ClientIp::bucket( '203.0.113.9' ) );
			assert_same( '2001:db8:abcd:12::/64', ClientIp::bucket( '2001:db8:abcd:12:1:2:3:4' ) );
			assert_same( ClientIp::bucket( '2001:db8:abcd:12::1' ), ClientIp::bucket( '2001:db8:abcd:12:ffff::9' ) );
			assert_same( 'unknown', ClientIp::bucket( 'unknown' ) );

			$server                 = $_SERVER;
			$_SERVER['REMOTE_ADDR'] = '2001:db8:abcd:12:1:2:3:4';
			try {
				$harness = upsellbay_claim_routes( upsellbay_claim_offer() );
				$harness['routes']->cart_offer_add( upsellbay_claim_params( $harness['token'], 'cart_crosssell' ) );
				assert_same( array( 'cart_offer_add|ip:2001:db8:abcd:12::/64' ), $harness['limits']->keys );
			} finally {
				$_SERVER = $server;
			}
		},

		// SH-8: license key never leaves as plain text.
		'license status and activation responses never carry the full key' => static function (): void {
			require_once dirname( __DIR__ ) . '/app/Api/Routes/LicenseRoute.php';

			$client = upsellbay_license_client(
				array(
					'key'        => 'ABCD-1234-EFGH-5678',
					'status'     => 'active',
					'plan'       => 'agency',
					'expires_at' => '2027-01-01',
				)
			);

			$status = $client->public_status();
			assert_same(
				array(
					'status'     => 'active',
					'plan'       => 'agency',
					'expires_at' => '2027-01-01',
					'masked_key' => 'AB****78',
				),
				$status
			);

			$response = ( new LicenseRoute( $client ) )->status( new WP_REST_Request() );
			assert_false( str_contains( wp_json_encode( $response->get_data() ), 'ABCD-1234-EFGH-5678' ) );
		},
		'license activation hands its hook the key masked' => static function (): void {
			$licence = 'ABCD-1234-EFGH-5678'; // gate: allow -- test fixture, not a real licence.
			$keys    = array();
			add_action(
				'upsellbay_pro_license_activated',
				static function ( string $license_key ) use ( &$keys ): void {
					$keys[] = $license_key;
				}
			);

			$client = upsellbay_license_client(
				array(),
				static fn (): array => array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'success' => true,
							'data'    => array(
								'status'  => 'active',
								'license' => 'valid',
							),
						)
					),
				)
			);
			$result = $client->activate( $licence );
			unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_license_activated'] );

			assert_true( true === $result );
			assert_same( array( 'AB****78' ), $keys );
		},
		'license failures hand hooks the key masked' => static function (): void {
			$licence = 'ABCD-1234-EFGH-5678'; // gate: allow -- test fixture, not a real licence.
			$errors  = array();
			$keys    = array();
			add_action(
				'upsellbay_pro_license_activation_failed',
				static function ( string $license_key, string $code, $error ) use ( &$errors, &$keys ): void {
					unset( $code );
					$keys[]   = $license_key;
					$errors[] = $error;
				},
				10,
				3
			);

			$transport = upsellbay_license_client(
				array(),
				static fn (): WP_Error => new WP_Error( 'http_request_failed', 'cURL error 28 for license_key=' . $licence, array( 'license_key' => $licence ) )
			);
			$transport->activate( $licence );

			$echo = upsellbay_license_client(
				array(),
				static fn (): array => array(
					'response' => array( 'code' => 403 ),
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode(
						array(
							'success'     => false,
							'message'     => 'License ' . $licence . ' is not valid for this site.',
							'license_key' => $licence,
						)
					),
				)
			);
			$result = $echo->activate( $licence );
			unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_license_activation_failed'] );

			assert_same( 2, count( $errors ) );
			// The hook's first argument is the masked key, never the raw one.
			assert_same( array( 'AB****78', 'AB****78' ), $keys );
			foreach ( $errors as $error ) {
				assert_true( $error instanceof WP_Error );
				$dump = wp_json_encode( array( $error->get_error_messages(), $error->get_error_data() ) );
				assert_false( str_contains( $dump, $licence ) );
			}
			assert_false( str_contains( $result->get_error_message(), $licence ) );
		},

		// SH-2: no trust on first use.
		'session token is accepted only when this session issued it and it is fresh' => static function (): void {
			$data    = array();
			$session = new CartSession(
				static function ( string $key ) use ( &$data ) {
					return $data[ $key ] ?? null;
				},
				static function ( string $key, $value ) use ( &$data ): void {
					$data[ $key ] = $value;
				}
			);

			assert_false( $session->validate_token( 'attacker-chosen-token' ) );
			assert_false( $session->validate_token( 'attacker-chosen-token' ) );

			$token = $session->ensure_token();
			assert_true( $session->validate_token( $token ) );
			assert_false( $session->validate_token( $token . 'x' ) );
			assert_false( $session->validate_token( '' ) );

			$data['upsellbay_offer_state']['token_issued_at'] = time() - 3 * 86400;
			assert_false( $session->validate_token( $token ) );

			$data['upsellbay_offer_state']['token_issued_at'] = 0;
			assert_false( $session->validate_token( $token ) );
		},

		// SH-12: import upload checks.
		'import upload must be a real upload of at most 2 MB' => static function (): void {
			$importer = ( new ReflectionClass( ImportExporter::class ) )->newInstanceWithoutConstructor();
			$uploaded = static fn (): bool => true;
			$reader   = static fn (): string => '{"type":"upsellbay_full_export"}';
			$file     = array(
				'tmp_name' => '/tmp/php123',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 32,
			);

			assert_same( '{"type":"upsellbay_full_export"}', $importer->read_upload( $file, $uploaded, $reader ) );

			$refused = array(
				'partial'      => array( array( 'error' => UPLOAD_ERR_PARTIAL ) + $file, $uploaded, $reader, 'upsellbay_import_no_file' ),
				'no file'      => array( array( 'error' => UPLOAD_ERR_NO_FILE ) + $file, $uploaded, $reader, 'upsellbay_import_no_file' ),
				'not uploaded' => array( $file, static fn (): bool => false, $reader, 'upsellbay_import_no_file' ),
				'ini size'     => array( array( 'error' => UPLOAD_ERR_INI_SIZE ) + $file, $uploaded, $reader, 'upsellbay_import_too_large' ),
				'over 2 MB'    => array( array( 'size' => ImportExporter::MAX_UPLOAD_BYTES + 1 ) + $file, $uploaded, $reader, 'upsellbay_import_too_large' ),
				'read 2 MB+'   => array( $file, $uploaded, static fn (): string => str_repeat( 'x', ImportExporter::MAX_UPLOAD_BYTES + 1 ), 'upsellbay_import_too_large' ),
				'unreadable'   => array( $file, $uploaded, static fn (): bool => false, 'upsellbay_import_no_file' ),
			);

			foreach ( $refused as $label => $case ) {
				$result = $importer->read_upload( $case[0], $case[1], $case[2] );
				assert_true( is_wp_error( $result ), $label );
				assert_same( $case[3], $result->get_error_code(), $label );
			}
		},
	);
}

/**
 * Build Pro's real service container and the public routes from it, reporting
 * whether the payment adapter registry was built along the way.
 */
function upsellbay_plugin_routes_build_registry(): bool {
	require_once dirname( __DIR__ ) . '/app/Core/Plugin.php';

	$container = new Container();
	$plugin    = ( new ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
	$property  = new ReflectionProperty( Plugin::class, 'container' );
	$property->setValue( $plugin, $container );
	( new ReflectionMethod( Plugin::class, 'register_services' ) )->invoke( $plugin );

	$built = false;
	$container->set(
		PaymentChargeAdapterRegistry::class,
		static function () use ( &$built ): PaymentChargeAdapterRegistry {
			$built = true;
			return new PaymentChargeAdapterRegistry();
		}
	);

	assert_true( $container->get( PublicOfferRoutes::class ) instanceof PublicOfferRoutes );

	return $built;
}

/**
 * A published, active cart offer (id 61) for product 50 at 30% off.
 *
 * @param array<string, mixed> $meta   Meta overrides.
 * @param string               $status Post status.
 * @return array<string, mixed>
 */
function upsellbay_repricer_offer( array $meta = array(), string $status = 'publish' ): array {
	$offer           = upsellbay_phase4_offer( 61, 'cart_crosssell', 50, 1 );
	$offer['status'] = $status;
	$offer['meta']   = array_replace(
		$offer['meta'],
		array(
			'_ub_discount_type'  => 'percent',
			'_ub_discount_value' => '30',
		),
		$meta
	);

	return $offer;
}

/**
 * Eligibility evaluator on a fixed clock (timestamp 1000000).
 */
function upsellbay_repricer_prioritizer(): OfferPrioritizer {
	return new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 1000000 );
}

/**
 * Repricer over one offer with a fixed regular price.
 *
 * @param array<string, mixed>|null $offer     Offer the loader returns (null = deleted).
 * @param string                    $regular   Current regular price of product 50.
 * @param bool                      $placement Whether the placement is switched on.
 */
function upsellbay_repricer( ?array $offer, string $regular = '30.000000', bool $placement = true ): OfferLineRepricer {
	return new OfferLineRepricer(
		static fn (): ?array => $offer,
		upsellbay_repricer_prioritizer(),
		new DiscountCalculator(),
		new DiscountApplier( static fn (): int => 2 ),
		static fn (): bool => $placement,
		static fn (): string => $regular
	);
}

/**
 * A cart with a trigger product (99) and an accepted cart offer line for product 50.
 *
 * The offer line stores a stale offer price of 1.00, which must never be charged.
 *
 * @param int $quantity Offer line quantity.
 */
function upsellbay_repricer_cart( int $quantity ): UpsellBay_Test_Repricer_Cart {
	return new UpsellBay_Test_Repricer_Cart(
		array(
			'trigger' => array(
				'product_id'    => 99,
				'variation_id'  => 0,
				'quantity'      => 1,
				'line_subtotal' => 45.0,
				'data'          => new UpsellBay_Test_Product( '45.000000' ),
			),
			'offer'   => array(
				'product_id'                              => 50,
				'variation_id'                            => 0,
				'quantity'                                => $quantity,
				'line_subtotal'                           => 1.0 * $quantity,
				'data'                                    => new UpsellBay_Test_Product( '1.000000' ),
				Constants::ATTRIBUTION_OFFER_ID           => 61,
				Constants::ATTRIBUTION_OFFER_PLACEMENT    => 'cart_crosssell',
				'_ub_offer_price'                         => '1.000000',
				Constants::ATTRIBUTION_DISCOUNT_AMOUNT    => '29.000000',
			),
		),
		'46.000000'
	);
}

/**
 * A published, active cart offer (id 62) for product 50.
 *
 * @param array<string, mixed> $meta   Meta overrides.
 * @param string               $status Post status.
 * @return array<string, mixed>
 */
function upsellbay_claim_offer( array $meta = array(), string $status = 'publish' ): array {
	$offer           = upsellbay_phase4_offer( 62, 'cart_crosssell', 50, 1 );
	$offer['status'] = $status;
	$offer['meta']   = array_replace( $offer['meta'], $meta );

	return $offer;
}

/**
 * Public routes for the claim tests, with a session token already issued.
 *
 * @param array<string, mixed> $offer       Offer the loader returns for id 62.
 * @param bool                 $with_policy Whether to wire the eligibility evaluator.
 * @return array{routes: PublicOfferRoutes, session: CartSession, token: string, added: ArrayObject, limits: stdClass}
 */
function upsellbay_claim_routes( array $offer, bool $with_policy = true ): array {
	$session      = upsellbay_array_cart_session();
	$token        = $session->ensure_token();
	$limits       = new stdClass();
	$limits->keys = array();
	$added        = new ArrayObject();
	$product      = static fn (): array => array(
		'id'           => 50,
		'price'        => '19.000000',
		'purchasable'  => true,
		'visible'      => true,
		'in_stock'     => true,
		'type'         => 'simple',
		'subscription' => false,
	);
	$mutator      = new CartMutator(
		$session,
		new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), $product ),
		new DiscountCalculator(),
		static function ( int $product_id ) use ( $added ): string {
			$added[] = $product_id;
			return 'claimed_item';
		},
		static fn (): bool => true,
		static fn (): bool => true
	);
	$routes       = new PublicOfferRoutes(
		static fn ( int $offer_id ): ?array => 62 === $offer_id ? $offer : null,
		$mutator,
		$session,
		static function ( string $endpoint, string $client_key ) use ( $limits ): bool {
			$limits->keys[] = $endpoint . '|' . $client_key;
			return true;
		},
		static fn (): string => '2026-05-30',
		new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) ),
		null,
		null,
		$with_policy ? upsellbay_repricer_prioritizer() : null
	);
	return array(
		'routes'  => $routes,
		'session' => $session,
		'token'   => $token,
		'added'   => $added,
		'limits'  => $limits,
	);
}

/**
 * Claim request params for offer 62.
 *
 * @param string $token     Session token.
 * @param string $placement Placement.
 * @return array<string, mixed>
 */
function upsellbay_claim_params( string $token, string $placement ): array {
	return array(
		'offer_id'  => 62,
		'placement' => $placement,
		'token'     => $token,
	);
}

/**
 * License client over an in-memory state and a scripted license server.
 *
 * @param array<string, mixed> $state       Stored license state.
 * @param callable|null        $remote_post License server response.
 */
function upsellbay_license_client( array $state, ?callable $remote_post = null ): LicenseClient {
	return new LicenseClient(
		static fn (): array => $state,
		static fn (): bool => true,
		static fn (): string => 'store.example.com',
		$remote_post ?? upsellbay_test_valid_license_post(),
		static fn (): array => array()
	);
}

/**
 * Cart double with the parts of WC_Cart the repricer reads.
 *
 * get_subtotal() returns 0 like the real cart does inside
 * woocommerce_before_calculate_totals (calculate_totals() resets totals first).
 */
final class UpsellBay_Test_Repricer_Cart {
	/** @var array<string, array<string, mixed>> */
	public array $cart_contents;

	/**
	 * @param array<string, array<string, mixed>> $items    Cart items.
	 * @param string                              $subtotal Last totals run's subtotal (ignored; see class doc).
	 */
	public function __construct( array $items, string $subtotal ) {
		unset( $subtotal );
		$this->cart_contents = $items;
	}

	/** @return array<string, array<string, mixed>> */
	public function get_cart(): array {
		return $this->cart_contents;
	}

	public function get_subtotal(): string {
		return '0';
	}
}

/**
 * Logger double that records every row.
 */
final class UpsellBay_Test_Recording_Logger implements LoggerInterface {
	/** @var array<int, array<string, mixed>> */
	public array $rows = array();

	public function emergency( string $title, array $context = array() ): void {
		$this->log( 'emergency', $title, $context );
	}

	public function alert( string $title, array $context = array() ): void {
		$this->log( 'alert', $title, $context );
	}

	public function critical( string $title, array $context = array() ): void {
		$this->log( 'critical', $title, $context );
	}

	public function error( string $title, array $context = array() ): void {
		$this->log( 'error', $title, $context );
	}

	public function warning( string $title, array $context = array() ): void {
		$this->log( 'warning', $title, $context );
	}

	public function notice( string $title, array $context = array() ): void {
		$this->log( 'notice', $title, $context );
	}

	public function info( string $title, array $context = array() ): void {
		$this->log( 'info', $title, $context );
	}

	public function debug( string $title, array $context = array() ): void {
		$this->log( 'debug', $title, $context );
	}

	public function log( $level, string $title, array $context = array() ): void {
		$this->rows[] = compact( 'level', 'title', 'context' );
	}
}
