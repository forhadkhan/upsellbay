<?php
/**
 * Phase 3 admin architecture tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);

use WPAnchorBay\UpsellBay\Admin\AdminAssets;
use WPAnchorBay\UpsellBay\Admin\AdminBar;
use WPAnchorBay\UpsellBay\Admin\AdminPage;
use WPAnchorBay\UpsellBay\Admin\AdminPageRegistrar;
use WPAnchorBay\UpsellBay\Admin\CompatibilityNotice;
use WPAnchorBay\UpsellBay\Admin\Coexistence;
use WPAnchorBay\UpsellBay\Admin\Dashboard\DashboardPage;
use WPAnchorBay\UpsellBay\Admin\Help\HelpPage;
use WPAnchorBay\UpsellBay\Admin\Navigation\AdminTab;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabFactory;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabRegistry;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabRouter;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferEditPage;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferListTable;
use WPAnchorBay\UpsellBay\Admin\Offers\OffersPage;
use WPAnchorBay\UpsellBay\Admin\OverviewSummary;
use WPAnchorBay\UpsellBay\Admin\Settings\SettingsPage;
use WPAnchorBay\UpsellBay\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBay\Admin\Wizard\WizardController;
use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Data\StatsRepository;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;

/**
 * Returns Phase 3 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_admin_architecture_tests(): array {
	return array(
		'admin registrar exposes a single woocommerce submenu page' => static function (): void {
			$registered = array();
			$registrar  = new AdminPageRegistrar(
				static function ( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ) use ( &$registered ): string {
					$registered[] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
					return 'woocommerce_page_' . $menu_slug;
				}
			);

			$registrar->register_pages();
			$slugs = array_column( $registered, 'menu_slug' );

			assert_same( 'woocommerce', $registered[0]['parent_slug'] );
			assert_same( 'manage_woocommerce', $registered[0]['capability'] );
			assert_same(
				array(
					'upsellbay',
				),
				$slugs
			);
			assert_same( array(), $registrar->top_level_pages() );
			assert_true( $registrar->is_upsellbay_screen( 'woocommerce_page_upsellbay' ) );
			assert_false( $registrar->is_upsellbay_screen( 'woocommerce_page_upsellbay-settings' ) );
			assert_false( $registrar->is_upsellbay_screen( 'woocommerce_page_wc-orders' ) );
		},
		'admin tabs define dashboard first and route invalid tabs to dashboard' => static function (): void {
			$registry = new TabRegistry(
				array(
					new AdminTab( 'dashboard', 'Dashboard', static function ( array $request ): void { unset( $request ); } ),
					new AdminTab( 'offers', 'Offers', static function ( array $request ): void { unset( $request ); } ),
				)
			);
			$router   = new TabRouter( $registry );

			assert_same( 'dashboard', $registry->default_tab()->id() );
			assert_same( array( 'dashboard', 'offers' ), array_keys( $registry->tabs() ) );
			assert_same( 'offers', $router->current_tab( array( 'tab' => 'offers' ) )->id() );
			assert_same( 'dashboard', $router->current_tab( array( 'tab' => 'missing' ) )->id() );
		},
		'unified admin page renders woocommerce style tabs with dashboard default' => static function (): void {
			$registry = new TabRegistry(
				array(
					new AdminTab(
						'dashboard',
						'Dashboard',
						static function ( array $request ): void {
							unset( $request );
							echo '<p>Dashboard content</p>';
						}
					),
					new AdminTab(
						'settings',
						'Settings',
						static function ( array $request ): void {
							unset( $request );
							echo '<p>Settings content</p>';
						}
					),
				)
			);
			$page     = new AdminPage( $registry, new TabRouter( $registry ) );

			ob_start();
			$page->render( array() );
			$html = (string) ob_get_clean();

			assert_contains( 'nav-tab-wrapper woo-nav-tab-wrapper', $html );
			assert_contains( 'Dashboard / Overview', $html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings', $html );
			assert_contains( 'Dashboard content', $html );
			assert_false( str_contains( $html, 'Settings content' ) );
		},
		'unified admin page tolerates wordpress page hook callback argument' => static function (): void {
			$registry = new TabRegistry(
				array(
					new AdminTab(
						'dashboard',
						'Dashboard / Overview',
						static function ( array $request ): void {
							unset( $request );
							echo '<p>Dashboard content</p>';
						}
					),
				)
			);
			$page     = new AdminPage( $registry, new TabRouter( $registry ) );

			ob_start();
			$page->render( 'woocommerce_page_upsellbay' );
			$html = (string) ob_get_clean();

			assert_contains( 'Dashboard content', $html );
		},
		'tab factory owns admin section definitions and offer editor routing' => static function (): void {
			$repository = upsellbay_test_offer_repository( array() );
			$validator  = new OfferValidator( new OfferSchema(), static fn (): bool => true );
			$service    = new OfferService( $repository, $validator );
			$settings   = new Settings( static fn (): array => array(), static fn (): bool => true );
			$stats      = new StatsRepository( static function (): void {}, static fn (): array => array() );
			$factory    = new TabFactory(
				new DashboardPage( new OverviewSummary( $repository, $stats, $settings ), $stats ),
				new OffersPage( new OfferListTable( $repository, $service ) ),
				new OfferEditPage( $service, $validator ),
				new SettingsPage( $settings ),
				new ToolsPage( new ImportExporter( $validator ), $settings ),
				new WizardController( $service, $settings, new \WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults() ),
				new HelpPage()
			);
			$registry   = $factory->registry();

			assert_same( array( 'dashboard', 'offers', 'settings', 'tools', 'setup', 'help' ), array_keys( $registry->tabs() ) );

			ob_start();
			$registry->get( 'offers' )->render( array( 'action' => 'edit' ) );
			$html = (string) ob_get_clean();

			assert_contains( 'Add UpsellBay Offer', $html );
			assert_false( str_contains( $html, 'wp-list-table' ) );
		},
		'offer list table maps native actions to repository calls' => static function (): void {
			$repository = upsellbay_test_offer_repository(
				array(
					11 => array(
						'id'     => 11,
						'title'  => 'Checkout bump',
						'status' => 'publish',
						'meta'   => array(
							'_ub_offer_type'       => 'checkout_bump',
							'_ub_status'           => 'active',
							'_ub_offer_product_id' => 25,
							'_ub_priority'         => 4,
						),
					),
				)
			);
			$table      = new OfferListTable( $repository, new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn (): bool => true ) ) );

			$rows = $table->rows( array( 'placement' => 'checkout_bump', 'status' => 'active' ) );

			assert_same( 1, count( $rows ) );
			assert_same( 'Checkout bump', $rows[0]['title'] );
			assert_same( array( 'pause', 'duplicate', 'trash' ), $table->bulk_actions() );
			assert_true( $table->handle_row_action( 'pause', 11 ) );
			assert_true( $table->handle_row_action( 'duplicate', 11 ) );
			assert_true( $table->handle_row_action( 'trash', 11 ) );
		},
		'offer editor shell sanitizes and validates submitted fields' => static function (): void {
			$saved      = array();
			$repository = upsellbay_test_offer_repository( array(), $saved );
			$page       = new OfferEditPage(
				new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn ( int $product_id ): bool => 25 === $product_id ) ),
				new OfferValidator( new OfferSchema(), static fn ( int $product_id ): bool => 25 === $product_id ),
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);

			$result = $page->save(
				array(
					'nonce'                  => 'good',
					'title'                  => ' <b>Bundle</b> ',
					'_ub_offer_type'         => 'checkout_bump',
					'_ub_status'             => 'active',
					'_ub_offer_product_id'   => '25',
					'_ub_discount_type'      => 'percent',
					'_ub_discount_value'     => '15',
					'_ub_headline'           => str_repeat( 'A', 90 ),
					'_ub_body'               => '<strong>Save</strong><script>bad</script>',
					'_ub_rules_match'        => 'any',
					'_ub_rules'              => array(
						array(
							'type'     => 'cart_subtotal',
							'operator' => 'gte',
							'value'    => '50',
						),
					),
					'_ub_placement_config'   => array( 'position' => 'before_submit' ),
					'_ub_show_image'         => 'on',
					'_ub_priority'           => '3',
				)
			);

			assert_true( $result['success'] );
			assert_same( 'Bundle', $saved['title'] );
			assert_same( 80, strlen( $saved['meta']['_ub_headline'] ) );
			assert_false( str_contains( $saved['meta']['_ub_body'], '<script>' ) );
			assert_same( 'cart_subtotal', $saved['meta']['_ub_rules'][0]['type'] );

			assert_false( $page->save( array( 'nonce' => 'bad' ) )['success'] );
		},
		'settings page persists normalized sections with nonce and capability checks' => static function (): void {
			$stored   = array();
			$settings = new Settings(
				static fn (): array => array(),
				static function ( array $value ) use ( &$stored ): bool {
					$stored = $value;
					return true;
				}
			);
			$page     = new SettingsPage(
				$settings,
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);

			$result = $page->save(
				array(
					'nonce'                  => 'good',
					'enabled'                => '1',
					'test_mode'              => '1',
					'placements'             => array( 'checkout_bump' => '1' ),
					'accent_color'           => '#cc0000',
					'button_style'           => 'theme',
					'stats_days'             => '90',
					'session_days'           => '14',
					'log_days'               => '7',
					'cleanup_on_delete'      => '0',
				)
			);

			assert_true( $result['success'] );
			assert_true( $stored['enabled'] );
			assert_true( $stored['test_mode'] );
			assert_same( '#cc0000', $stored['style_tokens']['accent_color'] );
			assert_same( 90, $stored['data_retention']['stats_days'] );
			assert_false( $stored['cleanup_on_delete'] );
			assert_same( array( 'general', 'style', 'data' ), array_keys( $page->sections() ) );
			assert_false( $page->save( array( 'nonce' => 'bad' ) )['success'] );
		},
		'admin assets load only on upsellbay screens' => static function (): void {
			$assets = new AdminAssets();

			assert_same( array(), $assets->assets_for_screen( 'woocommerce_page_wc-orders' ) );
			assert_same(
				array( 'upsellbay-admin', 'upsellbay-analytics' ),
				array_keys( $assets->assets_for_screen( 'woocommerce_page_upsellbay' ) )
			);
			assert_same(
				array( 'upsellbay-admin', 'upsellbay-offer-editor' ),
				array_keys( $assets->assets_for_screen( 'woocommerce_page_upsellbay', array( 'tab' => 'offers', 'action' => 'edit' ) ) )
			);
			assert_same(
				array( 'upsellbay-admin', 'upsellbay-offer-editor' ),
				array_keys( $assets->assets_for_screen( 'woocommerce_page_upsellbay', array( 'tab' => 'setup' ) ) )
			);
			assert_same(
				array( 'upsellbay-admin', 'upsellbay-analytics' ),
				array_keys( $assets->assets_for_screen( 'woocommerce_page_upsellbay', array( 'tab' => 'dashboard' ) ) )
			);
			assert_same( array(), $assets->assets_for_screen( 'woocommerce_page_upsellbay-add-offer' ) );
		},
		'dashboard shows overview and analytics from aggregate stats without live order scans' => static function (): void {
			$stats = new StatsRepository(
				static function (): void {
				},
				static fn (): array => array(
					array(
						'views'          => 10,
						'accepts'        => 2,
						'dismissals'     => 1,
						'orders'         => 2,
						'revenue'        => '40.000000',
						'discount_total' => '5.000000',
					),
				)
			);

			$overview = new OverviewSummary(
				upsellbay_test_offer_repository(
					array(
						7 => array(
							'id'     => 7,
							'title'  => 'Offer',
							'status' => 'publish',
							'meta'   => array( '_ub_status' => 'active' ),
						),
					)
				),
				$stats,
				new Settings( static fn (): array => array( 'enabled' => true, 'test_mode' => true ), static fn (): bool => true )
			);

			ob_start();
			( new DashboardPage( $overview, $stats ) )->render();
			$html = (string) ob_get_clean();

			assert_contains( '10', $html );
			assert_contains( '20.00%', $html );
			assert_contains( 'Active offers', $html );
			assert_contains( 'On', $html );
		},
		'compatibility coexistence tools help and admin bar stay product isolated' => static function (): void {
			$settings    = new Settings( static fn (): array => array( 'test_mode' => true ), static fn (): bool => true );
			$coexistence = new Coexistence( static fn ( string $plugin ): bool => 'cartbay/cartbay.php' === $plugin );
			$notice      = new CompatibilityNotice( $settings, $coexistence );
			$tools       = new ToolsPage( new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ) ), $settings );
			$help        = new HelpPage();
			$admin_bar   = new AdminBar( $settings, static fn (): bool => true );

			assert_true( $coexistence->is_cartbay_active() );
			assert_same( array(), $notice->notices() );
			assert_false( str_contains( implode( ' ', $tools->diagnostics() ), 'license_key' ) );
			assert_contains( 'AOV offer', implode( ' ', $help->links()[0] ) );
			assert_true( $admin_bar->should_show_indicator() );
		},
		'admin pages render native operational surfaces instead of placeholder headings' => static function (): void {
			$repository = upsellbay_test_offer_repository(
				array(
					11 => array(
						'id'    => 11,
						'title' => 'Warranty bump',
						'meta'  => array(
							'_ub_offer_type'       => 'checkout_bump',
							'_ub_status'           => 'active',
							'_ub_offer_product_id' => 25,
							'_ub_priority'         => 4,
						),
					),
				)
			);
			$service    = new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn (): bool => true ) );
			$settings   = new Settings( static fn (): array => array( 'enabled' => true, 'test_mode' => true ), static fn (): bool => true );
			$stats      = new StatsRepository(
				static function (): void {
				},
				static fn (): array => array(
					array(
						'views'          => 20,
						'accepts'        => 5,
						'dismissals'     => 2,
						'orders'         => 4,
						'revenue'        => '80.000000',
						'discount_total' => '10.000000',
					),
				)
			);

			ob_start();
			( new DashboardPage( new OverviewSummary( $repository, $stats, $settings ), $stats ) )->render();
			$dashboard_html = (string) ob_get_clean();

			ob_start();
			( new OffersPage( new OfferListTable( $repository, $service ) ) )->render();
			$offers_html = (string) ob_get_clean();

			ob_start();
			( new SettingsPage( $settings ) )->render();
			$settings_html = (string) ob_get_clean();

			ob_start();
			( new ToolsPage( new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ) ), $settings ) )->render();
			$tools_html = (string) ob_get_clean();

			assert_contains( 'Dashboard / Overview', $dashboard_html );
			assert_contains( 'Active offers', $dashboard_html );
			assert_contains( '25.00%', $dashboard_html );
			assert_contains( 'Performance (Last 30 days)', $dashboard_html );
			assert_contains( 'wp-list-table', $offers_html );
			assert_contains( 'Warranty bump', $offers_html );
			assert_contains( 'form-table', $settings_html );
			assert_contains( 'name="test_mode"', $settings_html );
			assert_contains( 'System diagnostics', $tools_html );
			assert_contains( 'Import offers', $tools_html );
		},
		'admin architecture excludes recovery modules from runtime surfaces' => static function (): void {
			$surfaces = AdminPageRegistrar::surface_slugs();
			$runtime  = implode( ' ', $surfaces );

			foreach ( array( 'recovery', 'abandoned', 'sequence', 'unsubscribe', 'restore' ) as $forbidden ) {
				assert_false( str_contains( $runtime, $forbidden ) );
			}
		},
	);
}

/**
 * Build a repository backed by arrays for admin tests.
 *
 * @since 1.0.0
 *
 * @param array<int, array<string, mixed>> $offers Offers.
 * @param array<string, mixed>            $saved  Last saved payload.
 */
function upsellbay_test_offer_repository( array $offers, array &$saved = array() ): OfferRepository {
	return new OfferRepository(
		new OfferValidator( new OfferSchema(), static fn (): bool => true ),
		static function ( array $post_data ) use ( &$offers, &$saved ): int {
			$saved = array(
				'title' => (string) $post_data['post_title'],
				'meta'  => array(),
			);
			return count( $offers ) + 1;
		},
		static function ( int $post_id, array $post_data ) use ( &$offers, &$saved ): bool {
			unset( $post_id );
			$saved['title'] = (string) $post_data['post_title'];
			return true;
		},
		static fn ( int $post_id ): ?array => $offers[ $post_id ] ?? null,
		static fn ( array $query ): array => array_values( $offers ),
		static function ( int $post_id, string $meta_key, $meta_value ) use ( &$offers, &$saved ): bool {
			$offers[ $post_id ]['meta'][ $meta_key ] = $meta_value;
			$saved['meta'][ $meta_key ]              = $meta_value;
			return true;
		},
		static fn ( int $post_id, string $meta_key ) => $offers[ $post_id ]['meta'][ $meta_key ] ?? null,
		static function ( int $post_id ) use ( &$offers ): bool {
			$offers[ $post_id ]['status'] = 'trash';
			return true;
		}
	);
}
