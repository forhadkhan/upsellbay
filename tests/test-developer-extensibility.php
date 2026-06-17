<?php
/**
 * Phase 6 developer extensibility tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Data\StatsRepository;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBay\Domain\Analytics\StatsReconciler;
use WPAnchorBay\UpsellBay\Domain\Attribution\AttributionWriter;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBay\Domain\Storefront\ClassicCheckoutBump;
use WPAnchorBay\UpsellBay\Domain\Storefront\PlacementRenderer;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Minimal WordPress hook harness for isolated tests.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		unset( $priority );
		$GLOBALS['upsellbay_test_hooks'][ $hook ][] = array( $callback, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Minimal WordPress filter harness for isolated tests.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( string $hook, $value, ...$args ) {
		foreach ( $GLOBALS['upsellbay_test_hooks'][ $hook ] ?? array() as $entry ) {
			$accepted = (int) $entry[1];
			$value    = $entry[0]( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Minimal WordPress action harness for isolated tests.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 */
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Minimal WordPress action runner for isolated tests.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Args.
	 */
	function do_action( string $hook, ...$args ): void {
		foreach ( $GLOBALS['upsellbay_test_hooks'][ $hook ] ?? array() as $entry ) {
			$entry[0]( ...array_slice( $args, 0, (int) $entry[1] ) );
		}
	}
}

/**
 * Returns Phase 6 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_developer_extensibility_tests(): array {
	return array(
		'public hook contract fires stable filters and actions at service boundaries' => static function (): void {
			upsellbay_reset_test_hooks();
			$observed = array();

			add_filter(
				Constants::hook_name( 'offer_schema' ),
				static function ( array $defaults ): array {
					$defaults['_ub_agency_code'] = '';
					return $defaults;
				}
			);
			add_filter(
				Constants::hook_name( 'available_placements' ),
				static function ( array $placements ): array {
					$placements['agency_slot'] = 'Agency slot';
					return $placements;
				}
			);
			add_filter(
				Constants::hook_name( 'offer_query_args' ),
				static function ( array $query_args ): array {
					$query_args['posts_per_page'] = 7;
					return $query_args;
				}
			);
			add_filter(
				Constants::hook_name( 'rule_context' ),
				static function ( array $context ): array {
					$context['cart_subtotal'] = '125.00';
					return $context;
				}
			);
			add_filter(
				Constants::hook_name( 'rule_result' ),
				static function ( bool $result, array $rule ): bool {
					return 'stock_status' === ( $rule['type'] ?? '' ) ? true : $result;
				},
				10,
				2
			);
			add_filter(
				Constants::hook_name( 'eligible_offers' ),
				static function ( array $offers ): array {
					return array_slice( $offers, 0, 1 );
				}
			);
			add_filter(
				Constants::hook_name( 'render_offer_html' ),
				static fn ( string $html ): string => $html . '<!-- agency -->'
			);
			add_filter(
				Constants::hook_name( 'offer_price' ),
				static fn (): string => '20.000000'
			);
			add_filter(
				Constants::hook_name( 'discount_amount' ),
				static fn (): string => '5.000000'
			);
			add_filter(
				Constants::hook_name( 'attribution_meta' ),
				static function ( array $meta ): array {
					$meta['_ub_agency_source'] = 'partner';
					return $meta;
				}
			);
			add_filter(
				Constants::hook_name( 'analytics_event' ),
				static function ( array $event ): array {
					$event['placement'] = 'cart_crosssell';
					return $event;
				}
			);

			foreach ( array( 'offer_created', 'offer_updated', 'offer_rendered', 'attribution_written', 'daily_stats_reconciled' ) as $suffix ) {
				add_action(
					Constants::hook_name( $suffix ),
					static function () use ( &$observed, $suffix ): void {
						$observed[] = $suffix;
					},
					10,
					4
				);
			}

			$schema = new OfferSchema();
			assert_same( '', $schema->defaults()['_ub_agency_code'] );
			assert_same( 'Agency slot', $schema->placements()['agency_slot'] );

			$query_args = array();
			$repository = new OfferRepository(
				new OfferValidator( new OfferSchema(), static fn (): bool => true ),
				static fn (): int => 1,
				static fn (): bool => true,
				static fn (): ?array => null,
				static function ( array $args ) use ( &$query_args ): array {
					$query_args = $args;
					return array();
				}
			);
			$repository->query();
			assert_same( 7, $query_args['posts_per_page'] );

			$evaluator = new RuleEvaluator( new RuleParser() );
			assert_true( $evaluator->matches( array( array( 'type' => 'cart_subtotal', 'operator' => 'gte', 'value' => 100 ) ), 'all', array() ) );
			assert_true( $evaluator->matches( array( array( 'type' => 'stock_status', 'operator' => 'eq', 'value' => 'backorder' ) ), 'all', array( 'stock_status' => 'instock' ) ) );

			$price = ( new DiscountCalculator() )->calculate( '25.00', array( '_ub_discount_type' => 'none', '_ub_discount_value' => '0' ) );
			assert_same( '20.000000', $price['offer_price'] );
			assert_same( '5.000000', $price['discount_amount'] );

			$selected = ( new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ) )
				->select( array( upsellbay_phase4_offer( 1, 'checkout_bump', 50, 1 ), upsellbay_phase4_offer( 2, 'checkout_bump', 50, 2 ) ), 'checkout_bump' );
			assert_same( 1, count( $selected ) );

			$renderer = new PlacementRenderer(
				new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ),
				new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ),
				array( 'checkout_bump' => new ClassicCheckoutBump() ),
				static fn (): string => '2026-05-30'
			);
			assert_contains( '<!-- agency -->', $renderer->render( 'checkout_bump', array( upsellbay_phase4_offer( 3, 'checkout_bump', 50, 1 ) ) ) );

			$item = new UpsellBay_Test_Meta_Object();
			( new AttributionWriter() )->write_order_item( $item, upsellbay_phase4_offer( 4, 'checkout_bump', 50, 1 ), 'checkout_bump', '5.000000', 'checkout' );
			assert_same( 'partner', $item->meta['_ub_agency_source'] );

			$analytics_events = array();
			$analytics = new AnalyticsService(
				new AnalyticsRecorder(
					new StatsRepository(
						static function ( string $key, array $delta ) use ( &$analytics_events ): void {
							$analytics_events[] = array( $key, $delta );
						},
						static fn (): array => array()
					)
				)
			);
			$analytics->record_event( 'accept', 5, 'checkout_bump', '2026-05-30', '20.000000' );
			assert_contains( '|cart_crosssell', $analytics_events[0][0] );

			$saved   = array();
			$service = new OfferService(
				upsellbay_test_offer_repository( array(), $saved ),
				new OfferValidator( new OfferSchema(), static fn (): bool => true )
			);
			$service->create( array( 'title' => 'Created', 'meta' => array( '_ub_offer_product_id' => 50, '_ub_headline' => 'Add warranty', '_ub_button_text' => 'Add to order' ) ) );
			$service->update( 1, array( 'title' => 'Updated', 'meta' => array( '_ub_offer_product_id' => 50, '_ub_headline' => 'Add warranty', '_ub_button_text' => 'Add to order' ) ) );
			( new StatsReconciler( new StatsRepository( static function (): void {}, static fn (): array => array() ) ) )->repair_missing_row( '2026-05-30', 6, 'checkout_bump' );

			assert_true( in_array( 'offer_created', $observed, true ) );
			assert_true( in_array( 'offer_updated', $observed, true ) );
			assert_true( in_array( 'offer_rendered', $observed, true ) );
			assert_true( in_array( 'attribution_written', $observed, true ) );
			assert_true( in_array( 'daily_stats_reconciled', $observed, true ) );
		},
		'import export filters preserve validation while allowing portable mapping' => static function (): void {
			upsellbay_reset_test_hooks();

			add_filter(
				Constants::hook_name( 'export_payload' ),
				static function ( array $payload ): array {
					$payload['agency'] = 'example';
					return $payload;
				}
			);
			add_filter(
				Constants::hook_name( 'import_mapping' ),
				static function ( array $mapping ): array {
					$mapping['sku'] = 'MAPPED-SKU';
					return $mapping;
				}
			);
			add_filter(
				Constants::hook_name( 'import_sku_match' ),
				static fn (): int => 91
			);
			add_filter(
				Constants::hook_name( 'import_validation_errors' ),
				static function ( array $errors ): array {
					$errors['agency'] = 'Agency validation remains enforced.';
					return $errors;
				}
			);
			add_filter(
				Constants::hook_name( 'import_post_status' ),
				static fn (): string => 'draft'
			);

			$exporter = new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ) );
			$json     = $exporter->export(
				array(
					array(
						'title'       => 'Portable offer',
						'product_sku' => 'ORIGINAL-SKU',
						'meta'        => array( '_ub_offer_product_id' => 50 ),
					),
				)
			);

			assert_contains( '"agency":"example"', $json );

			$result = $exporter->validate_json( $json );
			assert_true( $result->is_valid() );
			assert_same( 'MAPPED-SKU', $result->data()['offers'][0]['product_mapping']['sku'] );
			assert_same( 91, $result->data()['offers'][0]['meta']['_ub_offer_product_id'] );
			assert_same( 'draft', $result->data()['offers'][0]['post_status'] );

			$invalid = $exporter->validate_json( '{"type":"upsellbay_offer_export","version":1,"offers":[{"meta":[]}]}');
			assert_false( $invalid->is_valid() );
			assert_same( 'Agency validation remains enforced.', $invalid->errors()['agency'] );
		},
		'prd hook names are documented in developer reference' => static function (): void {
			$docs = (string) file_get_contents( __DIR__ . '/../docs/developer/hooks.md' );
			foreach ( upsellbay_phase6_required_prd_hooks() as $hook ) {
				assert_contains( $hook, $docs );
			}
		},
	);
}

/**
 * Reset the local hook harness.
 *
 * @since 1.0.0
 */
function upsellbay_reset_test_hooks(): void {
	$GLOBALS['upsellbay_test_hooks'] = array();
}

/**
 * Required PRD hook names.
 *
 * @since 1.0.0
 *
 * @return array<int, string>
 */
function upsellbay_phase6_required_prd_hooks(): array {
	return array(
		'upsellbay_offer_schema',
		'upsellbay_available_placements',
		'upsellbay_offer_query_args',
		'upsellbay_rule_context',
		'upsellbay_rule_result',
		'upsellbay_eligible_offers',
		'upsellbay_render_offer_html',
		'upsellbay_offer_price',
		'upsellbay_discount_amount',
		'upsellbay_attribution_meta',
		'upsellbay_analytics_event',
		'upsellbay_offer_created',
		'upsellbay_offer_updated',
		'upsellbay_offer_rendered',
		'upsellbay_offer_accepted',
		'upsellbay_offer_dismissed',
		'upsellbay_attribution_written',
		'upsellbay_follow_on_order_created',
		'upsellbay_daily_stats_reconciled',
	);
}
