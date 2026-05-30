<?php
/**
 * Phase 2 data architecture tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);

use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Installer;
use WPAnchorBay\UpsellBay\Core\Scheduler;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Data\StatsRepository;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBay\Domain\Analytics\StatsReconciler;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;
use WPAnchorBay\UpsellBay\Utils\TokenHelper;

/**
 * Returns Phase 2 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_data_architecture_tests(): array {
	return array(
		'offer schema normalizes valid meta and rejects invalid values' => static function (): void {
			$schema     = new OfferSchema();
			$validator  = new OfferValidator( $schema, static fn ( int $product_id ): bool => 10 === $product_id );
			$normalized = $validator->normalize(
				array(
					'_ub_offer_type'           => 'checkout_bump',
					'_ub_status'               => 'active',
					'_ub_offer_product_id'     => '10',
					'_ub_trigger_product_ids'  => array( '10', '11', 'x' ),
					'_ub_trigger_category_ids' => array( '4', 'bad' ),
					'_ub_discount_type'        => 'percent',
					'_ub_discount_value'       => '15.5',
					'_ub_headline'             => str_repeat( 'A', 90 ),
					'_ub_body'                 => '<strong>Useful</strong><script>bad</script>',
					'_ub_rules_match'          => 'bad',
					'_ub_show_image'           => 'yes',
					'_ub_priority'             => '7',
				)
			);

			assert_same( 'checkout_bump', $normalized['_ub_offer_type'] );
			assert_same( 'active', $normalized['_ub_status'] );
			assert_same( 10, $normalized['_ub_offer_product_id'] );
			assert_same( array( 10, 11 ), $normalized['_ub_trigger_product_ids'] );
			assert_same( array( 4 ), $normalized['_ub_trigger_category_ids'] );
			assert_same( 80, strlen( $normalized['_ub_headline'] ) );
			assert_false( str_contains( $normalized['_ub_body'], '<script>' ) );
			assert_same( 'all', $normalized['_ub_rules_match'] );
			assert_true( $normalized['_ub_show_image'] );

			assert_false( $validator->validate( array( '_ub_offer_type' => 'bad' ) )->is_valid() );
			assert_false(
				$validator->validate(
					array(
						'_ub_offer_type'       => 'checkout_bump',
						'_ub_offer_product_id' => 999,
					)
				)->is_valid()
			);
		},
		'offer repository delegates cpt and meta access through callbacks' => static function (): void {
			$posts      = array();
			$repository = new OfferRepository(
				new OfferValidator( new OfferSchema(), static fn (): bool => true ),
				static function ( array $post_data ) use ( &$posts ): int {
					$id           = count( $posts ) + 1;
					$posts[ $id ] = $post_data + array( 'ID' => $id );
					return $id;
				},
				static function ( int $post_id, array $post_data ) use ( &$posts ): bool {
					$posts[ $post_id ] = array_replace( $posts[ $post_id ], $post_data );
					return true;
				},
				static function ( int $post_id ) use ( &$posts ): ?array {
					return $posts[ $post_id ] ?? null;
				},
				static function ( array $query ) use ( &$posts ): array {
					unset( $query );
					return array_values( $posts );
				},
				static function ( int $post_id, string $meta_key, $meta_value ) use ( &$posts ): bool {
					$posts[ $post_id ]['meta'][ $meta_key ] = $meta_value;
					return true;
				},
				static function ( int $post_id, string $meta_key ) use ( &$posts ) {
					return $posts[ $post_id ]['meta'][ $meta_key ] ?? null;
				},
				static function ( int $post_id ) use ( &$posts ): bool {
					$posts[ $post_id ]['post_status'] = 'trash';
					return true;
				}
			);

			$id = $repository->create(
				array(
					'title' => 'Bump',
					'meta'  => array(
						'_ub_offer_type'       => 'checkout_bump',
						'_ub_offer_product_id' => 22,
					),
				)
			);

			$repository->pause( $id );
			$loaded = $repository->get( $id );
			assert_same( 'paused', $loaded['meta']['_ub_status'] );

			$duplicate_id = $repository->duplicate( $id );
			assert_same( 'Bump Copy', $posts[ $duplicate_id ]['post_title'] );
			assert_same( 'paused', $posts[ $duplicate_id ]['meta']['_ub_status'] );
		},
		'stats repository upserts counters and returns bounded summaries' => static function (): void {
			$rows       = array();
			$repository = new StatsRepository(
				static function ( string $key, array $delta ) use ( &$rows ): void {
					$rows[ $key ] ??= array(
						'views'          => 0,
						'accepts'        => 0,
						'dismissals'     => 0,
						'orders'         => 0,
						'revenue'        => '0.000000',
						'discount_total' => '0.000000',
					);
					foreach ( $delta as $field => $value ) {
						$rows[ $key ][ $field ] = in_array( $field, array( 'revenue', 'discount_total' ), true )
							? number_format( (float) $rows[ $key ][ $field ] + (float) $value, 6, '.', '' )
							: $rows[ $key ][ $field ] + $value;
					}
				},
				static function () use ( &$rows ): array {
					return $rows;
				}
			);

			$repository->increment( '2026-05-30', 5, 'checkout_bump', array( 'views' => 1 ) );
			$repository->increment( '2026-05-30', 5, 'checkout_bump', array( 'accepts' => 1, 'revenue' => '12.345678' ) );

			$summary = $repository->summary( '2026-05-01', '2026-05-31' );
			assert_same( 1, $summary['views'] );
			assert_same( 1, $summary['accepts'] );
			assert_same( '12.345678', $summary['revenue'] );
		},
		'cart session stores only non pii offer state and validates tokens' => static function (): void {
			$data    = array();
			$session = new CartSession(
				static function ( string $key ) use ( &$data ) {
					return $data[ $key ] ?? null;
				},
				static function ( string $key, $value ) use ( &$data ): void {
					$data[ $key ] = $value;
				},
				new TokenHelper()
			);

			$token = $session->ensure_token();
			$session->accept_offer( 10, 'checkout_bump', 'abc123' );
			$session->dismiss_offer( 11, 'cart_crosssell' );

			$state = $session->state();
			assert_same( 'checkout_bump', $state['accepted'][10]['placement'] );
			assert_same( 'abc123', $state['accepted'][10]['cart_item_key'] );
			assert_same( 'cart_crosssell', $state['dismissed'][11]['placement'] );
			assert_true( $session->validate_token( $token ) );
			assert_false( isset( $state['email'] ) );
		},
		'attribution keys are centralized constants' => static function (): void {
			assert_same( '_ub_offer_id', Constants::ATTRIBUTION_OFFER_ID );
			assert_same( '_ub_offer_type', Constants::ATTRIBUTION_OFFER_TYPE );
			assert_same( '_ub_source_order_id', Constants::ATTRIBUTION_SOURCE_ORDER_ID );
			assert_same( '_ub_follow_on_order', Constants::ATTRIBUTION_FOLLOW_ON_ORDER );
		},
		'import exporter validates json schema and strips site specific ids' => static function (): void {
			$exporter = new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ) );
			$payload  = $exporter->export(
				array(
					array(
						'title' => 'Offer',
						'meta'  => array(
							'_ub_offer_type'       => 'checkout_bump',
							'_ub_offer_product_id' => 55,
							'_ub_headline'         => 'Add this',
						),
					),
				)
			);

			assert_false( str_contains( $payload, '"_ub_offer_product_id":55' ) );

			$result = $exporter->validate_json( $payload );
			assert_true( $result->is_valid() );
			assert_same( 'upsellbay_offer_export', $result->data()['type'] );

			assert_false( $exporter->validate_json( '{bad json' )->is_valid() );
		},
		'settings retain merchant choices while adding data defaults' => static function (): void {
			$settings = new Settings(
				static fn (): array => array(
					'enabled'        => false,
					'data_retention' => array(
						'stats_days' => '90',
					),
				),
				static fn (): bool => true
			);

			$normalized = $settings->all();
			assert_false( $normalized['enabled'] );
			assert_same( 90, $normalized['data_retention']['stats_days'] );
			assert_same( 30, $normalized['data_retention']['session_days'] );
			assert_false( $normalized['data_retention']['prune_order_attribution'] );
		},
		'analytics recorder and reconciler use stats repository contract' => static function (): void {
			$events     = array();
			$repository = new StatsRepository(
				static function ( string $key, array $delta ) use ( &$events ): void {
					$events[] = array( $key, $delta );
				},
				static fn (): array => array()
			);
			$recorder   = new AnalyticsRecorder( $repository );
			$reconciler = new StatsReconciler( $repository );

			$recorder->record_view( 7, 'product_upsell', '2026-05-30' );
			$recorder->record_accept( 7, 'product_upsell', '2026-05-30', '19.990000', '2.000000' );
			$reconciler->repair_missing_row( '2026-05-30', 7, 'product_upsell' );

			assert_same( array( 'views' => 1 ), $events[0][1] );
			assert_same( '19.990000', $events[1][1]['revenue'] );
			assert_same( array(), $events[2][1] );
		},
		'installer schema contains exact aggregate stats columns' => static function (): void {
			$sql       = '';
			$installer = new Installer(
				new Settings( static fn (): array => array(), static fn (): bool => true ),
				new Scheduler(),
				static function () use ( &$sql ): void {
					$sql = Installer::stats_table_schema_sql( 'wp_upsellbay_offer_stats_daily', '' );
				}
			);

			$installer->activate();

			foreach ( array( 'stat_date', 'offer_id', 'placement', 'views', 'accepts', 'dismissals', 'orders', 'revenue', 'discount_total', 'updated_at' ) as $column ) {
				assert_contains( $column, $sql );
			}
			assert_contains( 'UNIQUE KEY stat_offer_placement (stat_date, offer_id, placement)', $sql );
		},
	);
}
