<?php
/**
 * Free host <-> Pro seam tests (PRO-3).
 *
 * Where a test needs the host's behaviour it loads the free plugin's real
 * classes from the sibling checkout (or UPSELLBAY_FREE_DIR), so a host-side
 * rename breaks these tests instead of passing against a copy.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Installer;
use WPAnchorBay\UpsellBayPro\Core\ScheduledJobs;
use WPAnchorBay\UpsellBayPro\Core\Scheduler;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ClassicCheckoutBump;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\PlacementRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\StorefrontController;
use WPAnchorBay\UpsellBayPro\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBayPro\Integrations\HostSettingsIntegration;
use WPAnchorBay\UpsellBayPro\Integrations\OfferTypesIntegration;
use WPAnchorBay\UpsellBayPro\Integrations\WooCommerce\BlockCheckoutIntegration;
use WPAnchorBay\UpsellBayPro\Utils\ImportExporter;

/**
 * Load free host classes by path relative to its app/ directory.
 *
 * @param array<int, string> $files Relative paths.
 */
function upsellbay_seam_load_host( array $files ): void {
	$dir = getenv( 'UPSELLBAY_FREE_DIR' );
	$dir = is_string( $dir ) && '' !== $dir ? $dir : dirname( __DIR__, 2 ) . '/upsellbay-order-bump-for-woocommerce';

	if ( ! is_dir( $dir . '/app' ) ) {
		throw new RuntimeException( 'Free host plugin not found at ' . $dir . '; set UPSELLBAY_FREE_DIR.' );
	}

	foreach ( $files as $file ) {
		require_once $dir . '/app/' . $file;
	}
}

/**
 * Run a test body with an isolated hook table and option store.
 *
 * @param callable $body Test body.
 */
function upsellbay_seam_isolated( callable $body ): void {
	$hooks   = $GLOBALS['upsellbay_test_hooks'] ?? array();
	$options = $GLOBALS['upsellbay_test_options'] ?? array();

	$GLOBALS['upsellbay_test_hooks']   = array();
	$GLOBALS['upsellbay_test_options'] = array();

	try {
		$body();
	} finally {
		$GLOBALS['upsellbay_test_hooks']   = $hooks;
		$GLOBALS['upsellbay_test_options'] = $options;
	}
}

/**
 * Seam tests.
 *
 * @return array<string, callable>
 */
function upsellbay_free_seam_tests(): array {
	return array(
		'every scheduled action scheduler hook has a registered handler' => static function (): void {
			upsellbay_seam_isolated(
				static function (): void {
					$jobs = new ScheduledJobs(
						new Settings( static fn (): array => array(), static fn (): bool => true ),
						new StatsRepository( static function (): void {}, static fn (): array => array(), static fn (): int => 0 ),
						static function (): void {},
						static function (): void {}
					);
					$jobs->register_hooks();

					$scheduler = new Scheduler( static fn (): bool => false, static function (): void {}, static function (): void {} );
					foreach ( $scheduler->jobs() as $job ) {
						assert_true( ! empty( $GLOBALS['upsellbay_test_hooks'][ $job['hook'] ] ), 'No handler for ' . $job['hook'] );
					}

					foreach ( $scheduler->retired_hooks() as $hook ) {
						assert_false( in_array( $hook, array_column( $scheduler->jobs(), 'hook' ), true ) );
					}
				}
			);
		},
		'prune stats deletes rows older than the stats retention days only' => static function (): void {
			$cutoffs = array();
			$stats   = new StatsRepository(
				static function (): void {},
				static fn (): array => array(),
				static function ( string $before ) use ( &$cutoffs ): int {
					$cutoffs[] = $before;
					return 3;
				}
			);

			$custom = new ScheduledJobs(
				new Settings( static fn (): array => array( 'data_retention' => array( 'stats_days' => 30 ) ), static fn (): bool => true ),
				$stats,
				static function (): void {},
				static function (): void {},
				static fn (): string => '2026-10-22'
			);
			assert_same( 3, $custom->prune_stats() );
			$custom->prune_stats();

			$default = new ScheduledJobs(
				new Settings( static fn (): array => array(), static fn (): bool => true ),
				$stats,
				static function (): void {},
				static function (): void {},
				static fn (): string => '2026-10-22'
			);
			$default->prune_stats();

			// Same cutoff on a repeat run; the 365-day default applies when unset.
			assert_same( array( '2026-09-22', '2026-09-22', '2025-10-22' ), $cutoffs );
			assert_same( 0, $stats->prune_before( '2026-09-22; DROP TABLE x' ) );
		},
		'installer stamps its own db version option and leaves the host stamp' => static function (): void {
			upsellbay_seam_isolated(
				static function (): void {
					$unscheduled = array();
					$scheduler   = new Scheduler(
						static fn (): bool => true,
						static function (): void {},
						static function ( string $hook ) use ( &$unscheduled ): void {
							$unscheduled[] = $hook;
						}
					);
					$settings    = new Settings( static fn (): array => array( 'cleanup_on_delete' => true ), static fn (): bool => true );
					$installer   = new Installer( $settings, $scheduler, static function (): void {} );

					assert_same( 'upsellbay_pro_db_version', Constants::DB_VERSION_OPTION );
					update_option( 'upsellbay_db_version', '1' );

					$installer->maybe_upgrade();
					assert_same( Constants::DB_VERSION, get_option( 'upsellbay_pro_db_version' ) );
					assert_same( '1', get_option( 'upsellbay_db_version' ) );
					assert_same( array( Constants::hook_name( 'refresh_analytics' ) ), $unscheduled );

					// One-time: a second request runs no migration step.
					$installer->maybe_upgrade();
					assert_same( 1, count( $unscheduled ) );

					$installer->uninstall();
					assert_false( get_option( 'upsellbay_pro_db_version' ) );
					assert_same( '1', get_option( 'upsellbay_db_version' ) );
				}
			);
		},
		'pro cleanup removes only pro keys, tables and actions; shared option and offers survive' => static function (): void {
			if ( ! function_exists( 'esc_sql' ) ) {
				eval( 'function esc_sql( $v ) { return $v; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub.
			}

			upsellbay_seam_isolated(
				static function (): void {
					$previous_wpdb = $GLOBALS['wpdb'] ?? null;
					$wpdb          = new class() {
						public string $prefix = 'wp_';
						public string $posts = 'wp_posts';
						public string $postmeta = 'wp_postmeta';
						/** @var array<int, string> */
						public array $queries = array();

						public function query( string $sql ): int {
							$this->queries[] = $sql;
							return 0;
						}
					};
					$GLOBALS['wpdb'] = $wpdb;

					try {
						$host_row = array(
							'enabled'               => false,
							'test_mode'             => true,
							'placements'            => array( 'checkout_bump' => false, 'product_upsell' => true, 'cart_crosssell' => false, 'thankyou_offer' => true ),
							'placement_max_display' => array( 'checkout_bump' => 2, 'cart_crosssell' => 4 ),
							'style_tokens'          => array( 'accent_color' => '#111111', 'button_style' => 'theme' ),
							'cleanup_on_delete'     => true,
							'wizard_completed'      => true,
							'license'               => array( 'status' => 'valid' ),
							'first_offer_id'        => 9,
						);
						update_option( 'upsellbay_settings', $host_row );
						update_option( Constants::DB_VERSION_OPTION, '2' );
						update_option( Constants::LICENSE_DATA_OPTION, array( 'x' => 1 ) );
						update_option( 'upsellbay_db_version', '1' );

						$unscheduled = 0;
						$scheduler   = new Scheduler( static fn (): bool => true, static function (): void {}, static function () use ( &$unscheduled ): void { ++$unscheduled; } );
						$installer   = new Installer( new Settings(), $scheduler, static function (): void {} );

						$expected_host = $host_row;
						unset( $expected_host['license'], $expected_host['first_offer_id'] );
						unset( $expected_host['placements']['product_upsell'], $expected_host['placements']['cart_crosssell'], $expected_host['placements']['thankyou_offer'], $expected_host['placement_max_display']['cart_crosssell'] );

						$installer->uninstall();

						assert_same( $expected_host, get_option( 'upsellbay_settings' ) );
						assert_same( '1', get_option( 'upsellbay_db_version' ) );
						assert_false( get_option( Constants::DB_VERSION_OPTION ) );
						assert_false( get_option( Constants::LICENSE_DATA_OPTION ) );
						assert_true( $unscheduled > 0 );
						assert_same( 3, count( $wpdb->queries ) );
						foreach ( $wpdb->queries as $sql ) {
							assert_contains( 'DROP TABLE IF EXISTS wp_upsellbay_pro_', $sql );
							assert_false( str_contains( $sql, 'posts' ) || str_contains( $sql, 'postmeta' ) );
						}

						// Clear-all-data: same protection, then Pro defaults are re-seeded.
						update_option( 'upsellbay_settings', $host_row );
						$wpdb->queries = array();
						$installer->clear_all_data();

						$after = get_option( 'upsellbay_settings' );
						assert_true( is_array( $after ) );
						foreach ( array( 'enabled', 'test_mode', 'style_tokens', 'cleanup_on_delete', 'wizard_completed' ) as $key ) {
							assert_same( $host_row[ $key ], $after[ $key ] );
						}
						assert_false( $after['placements']['checkout_bump'] );
						assert_same( 2, $after['placement_max_display']['checkout_bump'] );
						assert_same( 0, $after['first_offer_id'] );
						assert_same( 3, count( $wpdb->queries ) );
						foreach ( $wpdb->queries as $sql ) {
							assert_false( str_contains( $sql, 'posts' ) || str_contains( $sql, 'DELETE' ) );
						}
					} finally {
						$GLOBALS['wpdb'] = $previous_wpdb;
						if ( null === $previous_wpdb ) {
							unset( $GLOBALS['wpdb'] );
						}
					}
				}
			);
		},
		'cleanup leaves a missing or empty shared option alone' => static function (): void {
			upsellbay_seam_isolated(
				static function (): void {
					$writes   = 0;
					$settings = new Settings( static fn (): array => array(), static function () use ( &$writes ): bool { ++$writes; return true; } );
					assert_true( $settings->remove_pro_keys() );
					assert_same( 0, $writes );
				}
			);
		},
		'seed defaults adds only missing pro keys, keeps stored values, and is idempotent' => static function (): void {
			upsellbay_seam_isolated(
				static function (): void {
					$stored = array(
						'enabled'        => false,
						'placements'     => array( 'checkout_bump' => false, 'cart_crosssell' => false ),
						'retention_days' => 90,
					);
					$writes = 0;
					$settings = new Settings(
						static function () use ( &$stored ): array {
							return $stored;
						},
						static function ( array $value ) use ( &$stored, &$writes ): bool {
							$stored = $value;
							++$writes;
							return true;
						}
					);

					$settings->seed_defaults();
					assert_false( $stored['enabled'] );
					assert_false( $stored['placements']['checkout_bump'] );
					assert_false( $stored['placements']['cart_crosssell'] );
					assert_true( $stored['placements']['product_upsell'] );
					assert_same( 90, $stored['retention_days'] );
					assert_same( 0, $stored['first_offer_id'] );
					// Host keys that were absent stay absent.
					assert_false( array_key_exists( 'test_mode', $stored ) );
					assert_false( array_key_exists( 'style_tokens', $stored ) );

					$snapshot = $stored;
					$settings->seed_defaults();
					assert_same( $snapshot, $stored );
					assert_same( 1, $writes );
				}
			);
		},
		'saving the host basic settings keeps pro placement keys and values' => static function (): void {
			upsellbay_seam_load_host(
				array(
					'Core/Constants.php',
					'Core/Hooks.php',
					'Core/Settings.php',
					'Admin/Settings/SettingsSectionInterface.php',
					'Admin/Settings/AbstractSettingsSection.php',
					'Admin/Settings/BasicSection.php',
				)
			);

			upsellbay_seam_isolated(
				static function (): void {
					$stored = array(
						'placements'            => array(
							'checkout_bump'  => true,
							'product_upsell' => true,
							'cart_crosssell' => false,
							'thankyou_offer' => true,
						),
						'placement_max_display' => array(
							'checkout_bump'  => 1,
							'product_upsell' => 1,
							'cart_crosssell' => 4,
							'thankyou_offer' => 1,
						),
					);
					// Mirror update_option(): the pre_update_option filter sees the new and old value.
					$host = new \WPAnchorBay\UpsellBay\Core\Settings(
						static function () use ( &$stored ): array {
							return $stored;
						},
						static function ( array $value ) use ( &$stored ): bool {
							$stored = apply_filters( 'pre_update_option_upsellbay_settings', $value, $stored );
							return true;
						}
					);

					( new HostSettingsIntegration( new Settings( static fn (): array => array(), static fn (): bool => true ) ) )->register_hooks();

					// The host's own normalized read now knows Pro's keys instead of pruning them.
					assert_false( $host->all()['placements']['cart_crosssell'] );
					assert_same( 4, $host->all()['placement_max_display']['cart_crosssell'] );

					// The merchant unticks the checkout bump on the host form, which renders no Pro toggles.
					$next = ( new \WPAnchorBay\UpsellBay\Admin\Settings\BasicSection() )->apply(
						array( 'placement_max_display' => array( 'checkout_bump' => 2 ) ),
						$host->all()
					);
					$host->update( $next );

					assert_false( $stored['placements']['checkout_bump'] );
					assert_same( 2, $stored['placement_max_display']['checkout_bump'] );
					assert_true( $stored['placements']['product_upsell'] );
					assert_false( $stored['placements']['cart_crosssell'] );
					assert_true( $stored['placements']['thankyou_offer'] );
					assert_same( 4, $stored['placement_max_display']['cart_crosssell'] );

					// A write Pro starts itself is not overridden.
					$pro = new Settings(
						static fn (): array => $stored,
						static function ( array $value ) use ( &$stored ): bool {
							$stored = apply_filters( 'pre_update_option_upsellbay_settings', $value, $stored );
							return true;
						}
					);
					$own                                 = $pro->all();
					$own['placements']['product_upsell'] = false;
					$pro->update( $own );
					assert_false( $stored['placements']['product_upsell'] );
				}
			);
		},
		'host editor reads pro offer type copy positions descriptions labels and preview links' => static function (): void {
			upsellbay_seam_load_host(
				array(
					'Core/Constants.php',
					'Core/Hooks.php',
					'Domain/Offers/OfferSchema.php',
					'Domain/Offers/OfferDefaults.php',
				)
			);

			upsellbay_seam_isolated(
				static function (): void {
					( new OfferTypesIntegration() )->register_hooks();

					$schema = new \WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema();
					foreach ( array( 'product_upsell', 'cart_crosssell', 'thankyou_offer' ) as $type ) {
						assert_true( in_array( $type, $schema->offer_types(), true ) );
						assert_true( isset( $schema->placements()[ $type ] ) );
					}

					assert_same( 'after_add_to_cart', $schema->offer_type_positions()['product_upsell'] );
					assert_same( 'before_submit', $schema->offer_type_positions()['checkout_bump'] );
					assert_true( isset( $schema->placement_config_positions()['order_received_actions'] ) );

					$draft = ( new \WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults() )->for_type( 'thankyou_offer' );
					assert_same( 'thankyou_offer', $draft['_ub_offer_type'] );
					assert_same( 'Add another useful item', $draft['_ub_headline'] );
					assert_same( array( 'position' => 'order_received_actions' ), $draft['_ub_placement_config'] );

					$bump = ( new \WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults() )->for_type( 'checkout_bump' );
					assert_same( 'Complete your order with this add-on', $bump['_ub_headline'] );

					$descriptions = apply_filters( 'upsellbay_offer_type_descriptions', array( 'checkout_bump' => 'host' ) );
					assert_same( 'host', $descriptions['checkout_bump'] );
					assert_contains( 'cart', $descriptions['cart_crosssell'] );

					$fallback = array(
						'available' => false,
						'url'       => '',
						'message'   => 'host fallback',
					);
					$offer    = array(
						'id'   => 9,
						'meta' => array( '_ub_offer_type' => 'cart_crosssell' ),
					);
					$cart     = apply_filters( 'upsellbay_offer_preview_link', $fallback, 'cart_crosssell', $offer, array( 'cart_url' => '/cart/' ) );
					assert_true( $cart['available'] );
					assert_contains( 'placement=cart_crosssell', $cart['url'] );
					assert_same( $fallback, apply_filters( 'upsellbay_offer_preview_link', $fallback, 'checkout_bump', $offer, array() ) );
				}
			);
		},
		'pro checkout bump stands down when the host already rendered it and claims it otherwise' => static function (): void {
			upsellbay_seam_isolated(
				static function (): void {
					$GLOBALS['upsellbay_test_current_user_can'] = array( 'manage_woocommerce' => false );
					$GLOBALS['upsellbay_test_products'][50]     = new UpsellBay_Test_Storefront_Product( 50, 'Warranty', '20.00', 12 );

					$build = static function (): StorefrontController {
						$controller = new StorefrontController(
							upsellbay_test_offer_repository( array( 1 => upsellbay_phase4_offer( 1, 'checkout_bump', 50, 1 ) ) ),
							new PlacementRenderer(
								new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true, static fn (): int => 100 ),
								new AnalyticsRecorder( new StatsRepository( static function (): void {}, static fn (): array => array() ) ),
								array( 'checkout_bump' => new ClassicCheckoutBump( new DiscountCalculator() ) ),
								static fn (): string => '2026-05-30'
							),
							upsellbay_array_cart_session(),
							new Settings( static fn (): array => array(), static fn (): bool => true )
						);
						$controller->register_hooks();
						return $controller;
					};

					// Host first: it answers the shared filter for the placement it rendered.
					add_filter(
						'upsellbay_placement_already_rendered',
						static fn ( $rendered, $placement = '' ) => true === $rendered || 'checkout_bump' === $placement,
						10,
						2
					);
					ob_start();
					$build()->render_checkout_bump();
					assert_same( '', (string) ob_get_clean() );

					// Pro first: it renders once, then tells the host (and itself) the placement is taken.
					$GLOBALS['upsellbay_test_hooks'] = array();
					$pro                             = $build();
					ob_start();
					$pro->render_checkout_bump();
					assert_contains( 'data-upsellbay-placement="checkout_bump"', (string) ob_get_clean() );
					assert_true( apply_filters( 'upsellbay_placement_already_rendered', false, 'checkout_bump' ) );
					assert_false( apply_filters( 'upsellbay_placement_already_rendered', false, 'product_upsell' ) );
					ob_start();
					$pro->render_checkout_bump();
					assert_same( '', (string) ob_get_clean() );

					unset( $GLOBALS['upsellbay_test_current_user_can'], $GLOBALS['upsellbay_test_products'][50] );
				}
			);
		},
		'tools tab is added to the host admin tab registry before help' => static function (): void {
			upsellbay_seam_load_host( array( 'Admin/Navigation/AdminTab.php' ) );

			upsellbay_seam_isolated(
				static function (): void {
					$settings = new Settings( static fn (): array => array(), static fn (): bool => true );
					$tools    = new ToolsPage( new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ), upsellbay_test_offer_repository( array() ), $settings ), $settings );
					$tools->register_hooks();

					$noop = static function (): void {};
					$tabs = array(
						new \WPAnchorBay\UpsellBay\Admin\Navigation\AdminTab( 'dashboard', 'Dashboard', $noop ),
						new \WPAnchorBay\UpsellBay\Admin\Navigation\AdminTab( 'help', 'Help', $noop ),
					);
					$ids  = static fn ( array $list ): array => array_map( static fn ( $tab ): string => $tab->id(), $list );

					$filtered = apply_filters( 'upsellbay_admin_tabs', $tabs );
					assert_same( array( 'dashboard', 'tools', 'help' ), $ids( $filtered ) );
					assert_same( array( 'dashboard', 'tools', 'help' ), $ids( apply_filters( 'upsellbay_admin_tabs', $filtered ) ) );
					assert_false( isset( $GLOBALS['upsellbay_test_hooks']['upsellbay_tools_tab_content'] ) );
				}
			);
		},
		'block checkout bundle guards the shared plugin name and loads only on cart or checkout' => static function (): void {
			$root   = dirname( __DIR__ );
			$source = (string) file_get_contents( $root . '/src/block-checkout/checkout-bump.js' );
			$built  = (string) file_get_contents( $root . '/assets/frontend/block-checkout.js' );
			$php    = (string) file_get_contents( $root . '/app/Integrations/WooCommerce/BlockCheckoutIntegration.php' );

			assert_contains( "getPlugin( PLUGIN_NAME )", $source );
			assert_contains( 'getPlugin', $built );
			assert_contains( "add_action( 'wp_enqueue_scripts', array( \$this, 'enqueue_asset' ), 20 )", $php );
			assert_false( str_contains( $php, "add_action( 'woocommerce_blocks_checkout_block_registration'" ) );

			// No cart/checkout context in this request: nothing may be enqueued.
			assert_false( ( new BlockCheckoutIntegration() )->is_cart_or_checkout_page() );
		},
	);
}
