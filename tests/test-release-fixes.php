<?php
/**
 * Pro 1.0.0 release fix pass: Soqua SQ-1..SQ-4, Sherlock SH-1 and SH-3, licence hook masking.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Api\Routes\PublicOfferRoutes;
use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Container;
use WPAnchorBay\UpsellBayPro\Core\Plugin;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Data\CartSession;
use WPAnchorBay\UpsellBayPro\Data\OfferRepository;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBayPro\Domain\Attribution\AttributionWriter;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartValidator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\OfferLineRepricer;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBayPro\Utils\ImportExporter;

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * WordPress core stub: every insert fails, as with `$wp_error = true` on a database error.
	 *
	 * @param array<string, mixed> $post_data Post data.
	 * @param bool                 $wp_error  Whether to return a WP_Error.
	 * @return WP_Error
	 */
	function wp_insert_post( array $post_data, bool $wp_error = false ): WP_Error {
		unset( $post_data, $wp_error );
		return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
	}
}

/**
 * Returns release fix test cases.
 *
 * @return array<string, callable>
 */
function upsellbay_release_fixes_tests(): array {
	return array(
		// SQ-1: cart_subtotal rules are judged on the lines, not the reset WC totals.
		'rule subtotal sums non-offer lines and leaves Pro offer lines out' => static function (): void {
			$items = upsellbay_repricer_cart( 3 )->get_cart();
			$items['second'] = array(
				'product_id' => 70,
				'quantity'   => 2,
				'data'       => new UpsellBay_Test_Product( '2.500000' ),
			);

			// 45 x 1 + 2.50 x 2; the offer line (1.00 x 3) is not counted.
			assert_same( '50.000000', OfferLineRepricer::rule_subtotal( $items ) );
			assert_same( '0.000000', OfferLineRepricer::rule_subtotal( array() ) );
		},
		'repricer keeps a discount when the cart meets a subtotal >= rule' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_subtotal_offer( 'gte', '40' ) )->reprice( $cart );

			assert_same( '0', $cart->get_subtotal() );
			assert_same( '21.000000', $cart->cart_contents['offer']['data']->price );
			assert_same( '9.000000', $cart->cart_contents['offer'][ Constants::ATTRIBUTION_DISCOUNT_AMOUNT ] );
		},
		'repricer judges a subtotal <= rule on the real subtotal, not 0' => static function (): void {
			$within = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_subtotal_offer( 'lte', '50' ) )->reprice( $within );
			assert_same( '21.000000', $within->cart_contents['offer']['data']->price );

			// 45 is above 40: a subtotal read as 0 would wrongly keep the discount.
			$above = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_subtotal_offer( 'lte', '40' ) )->reprice( $above );
			assert_same( '30.000000', $above->cart_contents['offer']['data']->price );
			assert_same( '', $above->cart_contents['offer']['_ub_offer_price'] );
		},
		'repricer drops the discount when the cart is under a subtotal threshold' => static function (): void {
			$cart = upsellbay_repricer_cart( 1 );
			upsellbay_repricer( upsellbay_subtotal_offer( 'gte', '50' ) )->reprice( $cart );

			assert_same( '30.000000', $cart->cart_contents['offer']['data']->price );
			assert_same( '0.000000', $cart->cart_contents['offer'][ Constants::ATTRIBUTION_DISCOUNT_AMOUNT ] );
		},
		'the offer line itself never counts toward its own subtotal threshold' => static function (): void {
			// 45 trigger + 10 x 1.00 on the offer line would pass >= 50 if the line counted.
			$cart = upsellbay_repricer_cart( 10 );
			upsellbay_repricer( upsellbay_subtotal_offer( 'gte', '50' ) )->reprice( $cart );

			assert_same( '30.000000', $cart->cart_contents['offer']['data']->price );
		},

		// SQ-4: a line with no readable price is removed through WC_Cart::remove_cart_item().
		'repricer removes an unpriceable offer line through the cart API' => static function (): void {
			$cart = new UpsellBay_Test_Removable_Cart( upsellbay_repricer_cart( 1 )->get_cart() );
			( new OfferLineRepricer(
				static fn (): array => upsellbay_repricer_offer(),
				upsellbay_repricer_prioritizer(),
				new DiscountCalculator(),
				new DiscountApplier( static fn (): int => 2 ),
				static fn (): bool => true,
				static fn (): ?string => null
			) )->reprice( $cart );

			assert_same( array( 'offer' ), $cart->removed );
			assert_same( array( 'trigger' ), array_keys( $cart->cart_contents ) );
			assert_same( '45.000000', $cart->cart_contents['trigger']['data']->price );
		},

		// SH-1: a client-sent source order is never trusted outside a verified thank-you claim.
		'cart offer add ignores a foreign source order on a cart cross-sell' => static function (): void {
			$harness  = upsellbay_fix_routes( static fn (): string => 'line_1' );
			$params   = upsellbay_claim_params( $harness['token'], 'cart_crosssell' ) + array(
				'source_order_id'  => 700,
				'source_order_key' => 'wc_order_someone_else',
			);
			$response = $harness['routes']->cart_offer_add( $params );

			assert_same( 200, $response['status'] );
			assert_same( 1, count( $harness['added']->getArrayCopy() ) );
			$item_data = $harness['added'][0];
			assert_false( isset( $item_data[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] ) );
			assert_false( isset( $harness['session']->state()['accepted'][62]['source_order_id'] ) );

			$order = new UpsellBay_Test_Meta_Order();
			upsellbay_fix_attribution_plugin()->write_offer_line_item_attribution( new UpsellBay_Test_Meta_Item(), 'line_1', $item_data, $order );
			assert_same( array(), $order->meta );
		},
		'order attribution links a source order only for a thank-you line' => static function (): void {
			$plugin = upsellbay_fix_attribution_plugin();
			$line   = array(
				Constants::ATTRIBUTION_OFFER_ID        => 62,
				Constants::ATTRIBUTION_OFFER_PLACEMENT => 'cart_crosssell',
				Constants::ATTRIBUTION_SOURCE_ORDER_ID => 700,
			);

			// A line from before this fix that still carries a client-sent order ID.
			$cross_sell = new UpsellBay_Test_Meta_Order();
			$plugin->write_offer_line_item_attribution( new UpsellBay_Test_Meta_Item(), 'a', $line, $cross_sell );
			assert_same( array(), $cross_sell->meta );

			$thank_you = new UpsellBay_Test_Meta_Order();
			$plugin->write_offer_line_item_attribution( new UpsellBay_Test_Meta_Item(), 'b', array( Constants::ATTRIBUTION_OFFER_PLACEMENT => 'thankyou_offer' ) + $line, $thank_you );
			assert_same( 700, $thank_you->meta[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] );
			assert_true( $thank_you->meta[ Constants::ATTRIBUTION_FOLLOW_ON_ORDER ] );
		},
		'cart mutator stores a source order only for a thank-you offer' => static function (): void {
			$added   = new ArrayObject();
			$mutator = upsellbay_fix_mutator( upsellbay_array_cart_session(), $added, static fn (): string => 'line_1' );

			$mutator->accept( upsellbay_claim_offer(), 'cart_crosssell', array( 'source_order_id' => 810 ) );
			assert_false( isset( $added[0][ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] ) );

			$thank_you = upsellbay_claim_offer( array( '_ub_offer_type' => 'thankyou_offer' ) );
			$mutator->accept( $thank_you + array( 'id' => 63 ), 'thankyou_offer', array( 'source_order_id' => 810 ) );
			assert_same( 810, $added[1][ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] );
		},

		// SQ-2: a refused WooCommerce add records no accept.
		'a refused add to cart returns an error and records no accept' => static function (): void {
			$session = upsellbay_array_cart_session();
			$mutator = upsellbay_fix_mutator( $session, new ArrayObject(), static fn (): string => '' );

			$result = $mutator->accept( upsellbay_claim_offer(), 'cart_crosssell', array() );
			assert_true( $result instanceof WP_Error );
			assert_same( 'add_to_cart_failed', $result->get_error_code() );
			assert_same( array(), $session->state()['accepted'] );
		},
		'cart offer add reports a refused add without the accept event or success notice' => static function (): void {
			$accepted = 0;
			add_action(
				'upsellbay_pro_offer_accepted',
				static function () use ( &$accepted ): void {
					++$accepted;
				}
			);

			try {
				$harness  = upsellbay_fix_routes( static fn (): string => '' );
				$response = $harness['routes']->cart_offer_add( upsellbay_claim_params( $harness['token'], 'cart_crosssell' ) );
			} finally {
				unset( $GLOBALS['upsellbay_test_hooks']['upsellbay_pro_offer_accepted'] );
			}

			assert_same( 400, $response['status'] );
			assert_false( $response['data']['success'] );
			assert_same( 'Unable to add this offer. Please try again.', $response['data']['message'] );
			assert_same( 0, $accepted );
			assert_same( array(), $harness['session']->state()['accepted'] );
		},

		// SQ-3: a fixed offer price never exceeds the current price.
		'fixed price offers are capped at the current product price' => static function (): void {
			$calculator = new DiscountCalculator();
			$meta       = array(
				'_ub_discount_type'  => 'fixed_price',
				'_ub_discount_value' => '25',
			);

			assert_same( '15.000000', $calculator->calculate( '40', array( '_ub_discount_value' => '15' ) + $meta )['offer_price'] );

			$on_sale = $calculator->calculate( '19', $meta );
			assert_same( '19.000000', $on_sale['offer_price'] );
			assert_same( '0.000000', $on_sale['discount_amount'] );

			assert_same( '0.000000', $calculator->calculate( '0', $meta )['offer_price'] );
		},

		// SH-3: import hardening.
		'imported settings go through the settings normaliser and keep store-local values' => static function (): void {
			$stored = array(
				'license'          => array(
					'status'     => 'unknown',
					'masked_key' => '',
					'checked_at' => 0,
				),
				'first_offer_id'   => 12,
				'free_host_only'   => 'kept',
				'wizard_completed' => true,
			);
			$import = upsellbay_fix_importer( $stored );
			$result = $import['importer']->import_all(
				upsellbay_fix_export(
					array(
						'test_mode'         => '1',
						// A file must never switch data deletion on; this store's value stays.
						'cleanup_on_delete' => true,
						'style_tokens'      => array(
							'accent_color' => 'red;}body{display:none',
							'button_style' => '<script>',
						),
						'license'           => array(
							'status'     => 'active',
							'masked_key' => 'XX****XX',
						),
						'first_offer_id'    => 999,
						'wizard_completed'  => false,
						'injected_key'      => 'nope',
					),
					array()
				)
			);

			assert_true( $result->is_valid() );
			$written = $import['written']->value;
			assert_true( $written['test_mode'] );
			assert_false( $written['cleanup_on_delete'] );
			assert_same( Settings::DEFAULT_ACCENT_COLOR, $written['style_tokens']['accent_color'] );
			assert_same( 'theme', $written['style_tokens']['button_style'] );
			assert_same( 'unknown', $written['license']['status'] );
			assert_same( 12, $written['first_offer_id'] );
			assert_true( $written['wizard_completed'] );
			assert_same( 'kept', $written['free_host_only'] );
			assert_false( isset( $written['injected_key'] ) );
		},
		'an import file cannot write meta keys outside the offer schema' => static function (): void {
			$import = upsellbay_fix_importer( array() );
			$offer  = upsellbay_fix_import_offer( 'Crafted', 50 );

			$offer['meta']['_wp_page_template'] = 'evil.php';
			$offer['meta']['_thumbnail_id']     = 99;
			$offer['meta']['junk_key']          = str_repeat( 'x', 1000 );

			$result = $import['importer']->import_all( upsellbay_fix_export( array(), array( $offer ) ) );

			assert_true( $result->is_valid() );
			$keys = array_map(
				static fn ( string $entry ): string => substr( $entry, (int) strpos( $entry, ':' ) + 1 ),
				array_keys( $import['meta']->getArrayCopy() )
			);
			assert_false( in_array( '_wp_page_template', $keys, true ) );
			assert_false( in_array( '_thumbnail_id', $keys, true ) );
			assert_false( in_array( 'junk_key', $keys, true ) );
			assert_true( in_array( '_ub_offer_type', $keys, true ) );
			assert_true( in_array( '_ub_offer_product_id', $keys, true ) );
		},
		'imported settings keep this store logging and retention values' => static function (): void {
			$stored = array(
				'debug_logging'  => false,
				'retention_days' => 365,
				'data_retention' => array(
					'stats_days'              => 365,
					'session_days'            => 30,
					'log_days'                => 30,
					'prune_order_attribution' => false,
				),
			);
			$import = upsellbay_fix_importer( $stored );
			$result = $import['importer']->import_all(
				upsellbay_fix_export(
					array(
						'debug_logging'  => true,
						'retention_days' => 1,
						'data_retention' => array(
							'stats_days'              => 1,
							'session_days'            => 1,
							'log_days'                => 1,
							'prune_order_attribution' => true,
						),
					),
					array()
				)
			);

			assert_true( $result->is_valid() );
			$written = $import['written']->value;
			assert_false( $written['debug_logging'] );
			assert_same( 365, $written['retention_days'] );
			assert_same( 365, $written['data_retention']['stats_days'] );
			assert_false( $written['data_retention']['prune_order_attribution'] );
		},
		'settings normaliser keeps a valid hex accent colour and button style' => static function (): void {
			$settings = ( new Settings( static fn (): array => array(), static fn (): bool => true ) )->normalize(
				array(
					'style_tokens' => array(
						'accent_color' => '#ABC123',
						'button_style' => 'outline',
					),
				)
			);

			assert_same(
				array(
					'accent_color' => '#ABC123',
					'button_style' => 'outline',
				),
				$settings['style_tokens']
			);
		},
		'an invalid offer in the file stops the import before anything is written' => static function (): void {
			$import = upsellbay_fix_importer( array() );
			$result = $import['importer']->import_all(
				upsellbay_fix_export(
					array( 'test_mode' => true ),
					array(
						upsellbay_fix_import_offer( 'Good offer', 50 ),
						upsellbay_fix_import_offer( 'Bad offer', 999 ),
					)
				)
			);

			assert_false( $result->is_valid() );
			assert_same( array( 'offer_1' ), array_keys( $result->errors() ) );
			assert_true( str_contains( $result->errors()['offer_1'], 'Offer 2 (Bad offer) was not imported' ) );
			assert_same( array(), $import['posts']->getArrayCopy() );
			assert_same( null, $import['written']->value );
		},
		'a failed offer write is reported and the other offers are still imported' => static function (): void {
			$import = upsellbay_fix_importer( array(), 'Fails on insert' );
			$result = $import['importer']->import_all(
				upsellbay_fix_export(
					array(),
					array(
						upsellbay_fix_import_offer( 'First', 50 ),
						upsellbay_fix_import_offer( 'Fails on insert', 50 ),
						upsellbay_fix_import_offer( 'Third', 50 ),
					)
				)
			);

			assert_false( $result->is_valid() );
			assert_same( array( 'offer_1', 'partial' ), array_keys( $result->errors() ) );
			assert_true( str_contains( $result->errors()['offer_1'], 'The offer could not be saved.' ) );
			assert_same( array( 'First', 'Third' ), array_column( $import['posts']->getArrayCopy(), 'post_title' ) );
		},
		'a WP_Error from wp_insert_post is a failed insert, not post 1' => static function (): void {
			$repository = new OfferRepository( new OfferValidator( new OfferSchema(), static fn (): bool => true ) );
			$insert     = new ReflectionMethod( OfferRepository::class, 'wp_insert_offer' );

			assert_same( 0, $insert->invoke( $repository, array( 'post_title' => 'x' ) ) );

			$thrown = '';
			try {
				$repository->create( upsellbay_fix_import_offer( 'x', 50 ) );
			} catch ( RuntimeException $exception ) {
				$thrown = $exception->getMessage();
			}
			assert_same( 'The offer could not be saved.', $thrown );
		},
	);
}

/**
 * Cart offer (id 61) with one cart_subtotal rule.
 *
 * @param string $operator Rule operator.
 * @param string $value    Rule value.
 * @return array<string, mixed>
 */
function upsellbay_subtotal_offer( string $operator, string $value ): array {
	return upsellbay_repricer_offer(
		array(
			'_ub_rules' => array(
				array(
					'type'     => 'cart_subtotal',
					'operator' => $operator,
					'value'    => $value,
				),
			),
		)
	);
}

/**
 * Cart mutator that records each add's cart item data.
 *
 * @param CartSession $session     Session.
 * @param ArrayObject $added       Receives the item data of each add.
 * @param callable    $add_returns Returns the cart item key WooCommerce would.
 */
function upsellbay_fix_mutator( CartSession $session, ArrayObject $added, callable $add_returns ): CartMutator {
	$product = static fn (): array => array(
		'id'           => 50,
		'price'        => '19.000000',
		'purchasable'  => true,
		'visible'      => true,
		'in_stock'     => true,
		'type'         => 'simple',
		'subscription' => false,
	);

	return new CartMutator(
		$session,
		new CartValidator( new RuleEvaluator( new RuleParser() ), new DiscountCalculator(), $product ),
		new DiscountCalculator(),
		static function ( int $product_id, int $quantity, array $item_data ) use ( $added, $add_returns ): string {
			unset( $product_id, $quantity );
			$added[] = $item_data;
			return ( $add_returns )();
		},
		static fn (): bool => true,
		static fn (): bool => false
	);
}

/**
 * Public routes over offer 62 with a recording mutator.
 *
 * @param callable $add_returns Cart item key WooCommerce returns for the add.
 * @return array{routes: PublicOfferRoutes, session: CartSession, token: string, added: ArrayObject}
 */
function upsellbay_fix_routes( callable $add_returns ): array {
	$session = upsellbay_array_cart_session();
	$token   = $session->ensure_token();
	$added   = new ArrayObject();
	$offer   = upsellbay_claim_offer();
	$routes  = new PublicOfferRoutes(
		static fn ( int $offer_id ): ?array => 62 === $offer_id ? $offer : null,
		upsellbay_fix_mutator( $session, $added, $add_returns ),
		$session,
		static fn (): bool => true,
		static fn (): string => '2026-05-30',
		new AnalyticsService( new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) ),
		null,
		null,
		upsellbay_repricer_prioritizer()
	);

	return array(
		'routes'  => $routes,
		'session' => $session,
		'token'   => $token,
		'added'   => $added,
	);
}

/**
 * Plugin wired with just what order line attribution needs.
 */
function upsellbay_fix_attribution_plugin(): Plugin {
	require_once dirname( __DIR__ ) . '/app/Core/Plugin.php';

	$container = new Container();
	$container->set(
		OfferService::class,
		static fn (): object => new class() {
			/**
			 * No stored offer: attribution falls back to the line's own values.
			 *
			 * @param int $offer_id Offer ID.
			 */
			public function get( int $offer_id ): ?array {
				unset( $offer_id );
				return null;
			}
		}
	);
	$container->set( AttributionWriter::class, static fn (): AttributionWriter => new AttributionWriter() );

	$plugin   = ( new ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
	$property = new ReflectionProperty( Plugin::class, 'container' );
	$property->setValue( $plugin, $container );

	return $plugin;
}

/**
 * Importer over in-memory settings and offers.
 *
 * @param array<string, mixed> $stored      Stored settings option.
 * @param string               $fail_title  Offer title whose insert fails.
 * @return array{importer: ImportExporter, posts: ArrayObject, written: stdClass}
 */
function upsellbay_fix_importer( array $stored, string $fail_title = '' ): array {
	$posts          = new ArrayObject();
	$meta_log       = new ArrayObject();
	$written        = new stdClass();
	$written->value = null;
	$validator      = new OfferValidator( new OfferSchema(), static fn ( int $product_id ): bool => 50 === $product_id );
	$repository     = new OfferRepository(
		$validator,
		static function ( array $post_data ) use ( $posts, $fail_title ): int {
			if ( $fail_title === $post_data['post_title'] ) {
				return 0;
			}
			$id           = count( $posts ) + 1;
			$posts[ $id ] = $post_data;
			return $id;
		},
		static fn (): bool => true,
		static fn (): ?array => null,
		static fn (): array => array(),
		static function ( int $post_id, string $key, $value ) use ( $meta_log ): bool {
			$meta_log[ $post_id . ':' . $key ] = $value;
			return true;
		},
		static fn () => null,
		static fn (): bool => true
	);
	$settings       = new Settings(
		static fn (): array => $stored,
		static function ( array $value ) use ( $written ): bool {
			$written->value = $value;
			return true;
		}
	);

	return array(
		'importer' => new ImportExporter( $validator, $repository, $settings ),
		'posts'    => $posts,
		'meta'     => $meta_log,
		'written'  => $written,
	);
}

/**
 * A full-export JSON document.
 *
 * @param array<string, mixed>             $settings Settings.
 * @param array<int, array<string, mixed>> $offers   Offers.
 */
function upsellbay_fix_export( array $settings, array $offers ): string {
	return (string) wp_json_encode(
		array(
			'type'     => 'upsellbay_full_export',
			'version'  => 1,
			'settings' => $settings,
			'offers'   => $offers,
		)
	);
}

/**
 * One offer as it appears in an export file.
 *
 * @param string $title      Title.
 * @param int    $product_id Offered product.
 * @return array<string, mixed>
 */
function upsellbay_fix_import_offer( string $title, int $product_id ): array {
	$offer = upsellbay_phase4_offer( 1, 'cart_crosssell', $product_id, 1 );

	return array(
		'title'  => $title,
		'status' => 'publish',
		'meta'   => $offer['meta'],
	);
}

/**
 * Cart double with WC_Cart::remove_cart_item().
 */
final class UpsellBay_Test_Removable_Cart {
	/** @var array<string, array<string, mixed>> */
	public array $cart_contents;

	/** @var array<int, string> */
	public array $removed = array();

	/**
	 * @param array<string, array<string, mixed>> $items Cart items.
	 */
	public function __construct( array $items ) {
		$this->cart_contents = $items;
	}

	/** @return array<string, array<string, mixed>> */
	public function get_cart(): array {
		return $this->cart_contents;
	}

	public function remove_cart_item( string $key ): bool {
		$this->removed[] = $key;
		unset( $this->cart_contents[ $key ] );
		return true;
	}
}

/**
 * Order double that records meta writes.
 */
final class UpsellBay_Test_Meta_Order {
	/** @var array<string, mixed> */
	public array $meta = array();

	/**
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}

	public function save(): int {
		return 1;
	}
}

/**
 * Order item double that records meta writes.
 */
final class UpsellBay_Test_Meta_Item {
	/** @var array<string, mixed> */
	public array $meta = array();

	/**
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function add_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}
}
