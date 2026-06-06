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
use WPAnchorBay\UpsellBay\Admin\Settings\BasicSection;
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
use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;
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

			assert_contains( 'upsellbay-layout-header', $html );
			assert_contains( 'upsellbay-layout-header__heading', $html );
			assert_contains( 'upsellbay-layout-header__actions', $html );
			assert_contains( 'upsellbay-layout-header__tabs', $html );
			assert_contains( 'nav-tab-wrapper woo-nav-tab-wrapper', $html );
			assert_true( strpos( $html, 'upsellbay-layout-header' ) < strpos( $html, 'nav-tab-wrapper' ) );
			assert_true( strpos( $html, 'nav-tab-wrapper' ) < strpos( $html, 'upsellbay-tab-content' ) );
			assert_contains( 'Dashboard', $html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings', $html );
			assert_contains( 'Dashboard content', $html );
			assert_false( str_contains( $html, 'Settings content' ) );
		},
		'unified admin page tolerates wordpress page hook callback argument' => static function (): void {
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
				)
			);
			$page     = new AdminPage( $registry, new TabRouter( $registry ) );

			ob_start();
			$page->render( 'woocommerce_page_upsellbay' );
			$html = (string) ob_get_clean();

			assert_contains( 'Dashboard content', $html );
		},
		'unified admin page keeps plugin notices above attached header and tabs for offers' => static function (): void {
			$previous_hooks = $GLOBALS['upsellbay_test_hooks'] ?? array();
			$repository     = upsellbay_test_offer_repository( array() );
			$offers_page    = new OffersPage(
				new OfferListTable(
					$repository,
					new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn (): bool => true ) )
				)
			);
			$registry       = new TabRegistry(
				array(
					new AdminTab(
						'offers',
						'Offers',
						static function () use ( $offers_page ): void {
							$offers_page->render_content();
						}
					),
				)
			);

			add_action(
				'upsellbay_admin_page_heading_before',
				static function (): void {
					echo '<div class="notice upsellbay-page-notice">plugin notice</div>';
				}
			);
			add_action(
				'upsellbay_offers_header_after',
				static function (): void {
					echo '<div class="notice upsellbay-offers-notice">offers notice</div>';
				}
			);

			ob_start();
			( new AdminPage( $registry, new TabRouter( $registry ) ) )->render( array( 'tab' => 'offers' ) );
			$html = (string) ob_get_clean();

			$GLOBALS['upsellbay_test_hooks'] = $previous_hooks;

			assert_contains( 'upsellbay-page-notices', $html );
			assert_true( strpos( $html, 'upsellbay-page-notice' ) < strpos( $html, 'upsellbay-layout-header' ) );
			assert_true( strpos( $html, 'upsellbay-layout-header__heading' ) < strpos( $html, 'nav-tab-wrapper' ) );
			assert_true( strpos( $html, 'nav-tab-wrapper' ) < strpos( $html, 'upsellbay-tab-content' ) );
			assert_true( strpos( $html, 'upsellbay-tab-content' ) < strpos( $html, 'upsellbay-offers-section-menu' ) );
			assert_true( strpos( $html, 'upsellbay-offers-section-menu' ) < strpos( $html, 'upsellbay-offers-notice' ) );
		},
		'admin header css styles page license banner and suppresses tab focus side borders' => static function (): void {
			$root   = dirname( __DIR__ );
			$plugin = (string) file_get_contents( $root . '/app/Core/Plugin.php' );
			$css    = (string) file_get_contents( $root . '/assets/admin/css/upsellbay-admin.css' );

			assert_contains( 'upsellbay-license-banner', $plugin );
			assert_contains( 'upsellbay_admin_page_license_banner', $plugin );
			assert_contains( "get_license_notice_html( 'upsellbay-license-banner', false )", $plugin );
			assert_contains( '<p><strong>%s </strong> <a href="%s"', $plugin );
			assert_false( str_contains( $plugin, "esc_html__( 'UpsellBay License'" ) );
			assert_contains( '.upsellbay-license-banner-slot:empty', $css );
			assert_contains( '.upsellbay-page-notices:empty', $css );
			assert_contains( '.upsellbay-license-banner', $css );
			assert_contains( 'background: #f0b849;', $css );
			assert_contains( 'text-align: center;', $css );
			assert_contains( 'width: calc(100% + 20px);', $css );
			assert_contains( '.upsellbay-license-banner .button', $css );
			assert_contains( 'background: #1e1e1e;', $css );
			assert_contains( 'color: #fff;', $css );
			assert_contains( 'color: #f0b849;', $css );
			assert_contains( '.upsellbay-tab-content', $css );
			assert_contains( 'padding-top: 14px;', $css );
			assert_contains( 'box-shadow: none;', $css );
			assert_contains( 'outline: none;', $css );
		},
		'admin page renders redirect notices above the attached header on every tab' => static function (): void {
			$registry      = new TabRegistry(
				array(
					new AdminTab(
						'tools',
						'Tools',
						static function (): void {
							echo '<p>Tools content</p>';
						}
					),
				)
			);
			$previous_get  = $_GET;
			$_GET          = array(
				'wc_error' => 'License check failed: inactive.',
			);

			ob_start();
			( new AdminPage( $registry, new TabRouter( $registry ) ) )->render( array( 'tab' => 'tools' ) );
			$html = (string) ob_get_clean();

			$_GET = $previous_get;

			assert_contains( 'License check failed: inactive.', $html );
			assert_true( strpos( $html, 'License check failed: inactive.' ) < strpos( $html, 'upsellbay-layout-header' ) );
			assert_true( strpos( $html, 'upsellbay-layout-header' ) < strpos( $html, 'upsellbay-tab-content' ) );
			assert_true( strpos( $html, 'upsellbay-tab-content' ) < strpos( $html, 'Tools content' ) );
		},
		'settings save notice renders above the attached header instead of inside tab content' => static function (): void {
			$settings = new Settings( static fn (): array => array(), static fn (): bool => true );
			$page     = new SettingsPage(
				$settings,
				null,
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);
			$registry = new TabRegistry(
				array(
					new AdminTab(
						'settings',
						'Settings',
						static function () use ( $page ): void {
							$page->render_content();
						},
						static function () use ( $page ): void {
							$page->prepare_render();
						}
					),
				)
			);

			$previous_hooks  = $GLOBALS['upsellbay_test_hooks'] ?? array();
			$previous_post   = $_POST;
			$previous_method = $_SERVER['REQUEST_METHOD'] ?? null;

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = array( 'nonce' => 'good' );

			ob_start();
			( new AdminPage( $registry, new TabRouter( $registry ) ) )->render( array( 'tab' => 'settings' ) );
			$html = (string) ob_get_clean();

			$GLOBALS['upsellbay_test_hooks'] = $previous_hooks;
			$_POST                           = $previous_post;
			if ( null === $previous_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $previous_method;
			}

			assert_contains( 'Settings saved.', $html );
			assert_contains( 'upsellbay-page-notices', $html );
			assert_true( strpos( $html, 'Settings saved.' ) < strpos( $html, 'upsellbay-layout-header' ) );
			assert_true( strpos( $html, 'upsellbay-tab-content' ) < strpos( $html, '<form method="post">' ) );
			assert_false( strpos( $html, 'Settings saved.' ) > strpos( $html, 'upsellbay-tab-content' ) );
		},
		'settings and tools page warnings stay in the top notice area' => static function (): void {
			$tabs = array( 'settings', 'tools' );

			foreach ( $tabs as $tab_id ) {
				$previous_hooks = $GLOBALS['upsellbay_test_hooks'] ?? array();
				add_action(
					'upsellbay_admin_page_heading_before',
					static function () use ( $tab_id ): void {
						echo '<div class="notice warning upsellbay-page-notice">' . esc_html( 'top warning for ' . $tab_id ) . '</div>';
					}
				);

				$registry = new TabRegistry(
					array(
						new AdminTab(
							$tab_id,
							ucfirst( $tab_id ),
							static function () use ( $tab_id ): void {
								echo '<p>' . esc_html( $tab_id . ' body' ) . '</p>';
							}
						),
					)
				);

				ob_start();
				( new AdminPage( $registry, new TabRouter( $registry ) ) )->render( array( 'tab' => $tab_id ) );
				$html = (string) ob_get_clean();

				$GLOBALS['upsellbay_test_hooks'] = $previous_hooks;

				$notice_position  = strpos( $html, 'top warning for ' . $tab_id );
				$notices_position = strpos( $html, 'upsellbay-page-notices' );
				$header_position  = strpos( $html, 'upsellbay-layout-header' );
				$content_position = strpos( $html, 'upsellbay-tab-content' );

				assert_true( false !== $notice_position );
				assert_true( $notices_position < $notice_position );
				assert_true( $notice_position < $header_position );
				assert_true( $header_position < $content_position );
				assert_false( $content_position < $notice_position );
			}
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
		'setup tab label is onboarding-first until wizard completion' => static function (): void {
			$repository = upsellbay_test_offer_repository( array() );
			$validator  = new OfferValidator( new OfferSchema(), static fn (): bool => true );
			$service    = new OfferService( $repository, $validator );
			$stats      = new StatsRepository( static function (): void {}, static fn (): array => array() );

			$incomplete_settings = new Settings( static fn (): array => array( 'wizard_completed' => false ), static fn (): bool => true );
			$incomplete_factory  = new TabFactory(
				new DashboardPage( new OverviewSummary( $repository, $stats, $incomplete_settings ), $stats ),
				new OffersPage( new OfferListTable( $repository, $service ) ),
				new OfferEditPage( $service, $validator ),
				new SettingsPage( $incomplete_settings ),
				new ToolsPage( new ImportExporter( $validator ), $incomplete_settings ),
				new WizardController( $service, $incomplete_settings, new \WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults() ),
				new HelpPage()
			);

			$completed_settings = new Settings( static fn (): array => array( 'wizard_completed' => true ), static fn (): bool => true );
			$completed_factory  = new TabFactory(
				new DashboardPage( new OverviewSummary( $repository, $stats, $completed_settings ), $stats ),
				new OffersPage( new OfferListTable( $repository, $service ) ),
				new OfferEditPage( $service, $validator ),
				new SettingsPage( $completed_settings ),
				new ToolsPage( new ImportExporter( $validator ), $completed_settings ),
				new WizardController( $service, $completed_settings, new \WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults() ),
				new HelpPage()
			);

			assert_same( 'Get started', $incomplete_factory->registry()->get( 'setup' )->label() );
			assert_same( 'Setup', $completed_factory->registry()->get( 'setup' )->label() );
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
		'offers page exposes a header-after hook for offers notices below the offers title' => static function (): void {
			$previous_hooks = $GLOBALS['upsellbay_test_hooks'] ?? array();
			$repository     = upsellbay_test_offer_repository( array() );
			$page           = new OffersPage(
				new OfferListTable(
					$repository,
					new OfferService( $repository, new OfferValidator( new OfferSchema(), static fn (): bool => true ) )
				)
			);

			add_action(
				'upsellbay_offers_header_after',
				static function (): void {
					echo '<div class="notice upsellbay-offers-notice">offers notice</div>';
				}
			);

			ob_start();
			$page->render_content();
			$html = (string) ob_get_clean();

			$GLOBALS['upsellbay_test_hooks'] = $previous_hooks;

			assert_true( strpos( $html, 'upsellbay-offers-section-menu' ) < strpos( $html, 'upsellbay-offers-notice' ) );
			assert_true( strpos( $html, 'upsellbay-offers-notice' ) < strpos( $html, 'No UpsellBay offers yet' ) );
		},
		'offers tab renders native section links above list and editor content' => static function (): void {
			$repository = upsellbay_test_offer_repository( array() );
			$validator  = new OfferValidator( new OfferSchema(), static fn (): bool => true );
			$service    = new OfferService( $repository, $validator );

			ob_start();
			( new OffersPage( new OfferListTable( $repository, $service ) ) )->render_content();
			$list_html = (string) ob_get_clean();

			ob_start();
			( new OfferEditPage( $service, $validator ) )->render_content();
			$editor_html = (string) ob_get_clean();

			assert_contains( 'subsubsub upsellbay-offers-section-menu', $list_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=offers" class="current" aria-current="page">General</a>', $list_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=offers&amp;action=edit">Add Offer</a>', $list_html );
			assert_true( strpos( $list_html, 'upsellbay-offers-section-menu' ) < strpos( $list_html, 'No UpsellBay offers yet' ) );

			assert_contains( 'admin.php?page=upsellbay&amp;tab=offers">General</a>', $editor_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=offers&amp;action=edit" class="current" aria-current="page">Add Offer</a>', $editor_html );
			assert_true( strpos( $editor_html, 'upsellbay-offers-section-menu' ) < strpos( $editor_html, 'Add UpsellBay Offer' ) );
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
			assert_same( array( 'basic', 'style', 'data' ), array_keys( $page->sections() ) );
			assert_same( 'basic', ( new BasicSection() )->id() );
			assert_same( 'Basic', ( new BasicSection() )->label() );
			assert_false( $page->save( array( 'nonce' => 'bad' ) )['success'] );
		},
		'settings tab renders native section menu and routes section content' => static function (): void {
			$settings = new Settings( static fn (): array => array(), static fn (): bool => true );
			$page     = new SettingsPage( $settings );

			ob_start();
			$page->render_content( array( 'section' => 'general' ) );
			$general_html = (string) ob_get_clean();

			ob_start();
			$page->render_content( array( 'section' => 'data' ) );
			$data_html = (string) ob_get_clean();

			ob_start();
			$page->render_content( array( 'section' => 'license' ) );
			$license_html = (string) ob_get_clean();

			assert_contains( 'subsubsub upsellbay-settings-section-menu', $general_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings" class="current" aria-current="page">General</a>', $general_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings&amp;section=data">Data</a>', $general_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings&amp;section=license">License</a>', $general_html );
			assert_contains( '<h2>Basic</h2>', $general_html );
			assert_contains( '<h2>Style</h2>', $general_html );
			assert_false( str_contains( $general_html, '<h2>Data</h2>' ) );
			assert_false( str_contains( $general_html, 'id="upsellbay_license_activate"' ) );

			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings&amp;section=data" class="current" aria-current="page">Data</a>', $data_html );
			assert_contains( '<h2>Data</h2>', $data_html );
			assert_false( str_contains( $data_html, '<h2>Basic</h2>' ) );
			assert_false( str_contains( $data_html, '<h2>Style</h2>' ) );

			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings&amp;section=license" class="current" aria-current="page">License</a>', $license_html );
			assert_contains( '<h2>License</h2>', $license_html );
			assert_contains( 'id="upsellbay_license_activate"', $license_html );
			assert_false( str_contains( $license_html, '<h2>Data</h2>' ) );
		},
		'settings license row saves through the existing settings form without nesting forms' => static function (): void {
			$stored        = array();
			$license_state = array();
			$settings      = new Settings(
				static fn (): array => array(),
				static function ( array $value ) use ( &$stored ): bool {
					$stored = $value;
					return true;
				}
			);
			$license       = new LicenseClient(
				static function () use ( &$license_state ): array {
					return $license_state;
				},
				static function ( array $state ) use ( &$license_state ): bool {
					$license_state = $state;
					return true;
				},
				static fn (): string => 'store.test',
				upsellbay_test_valid_license_post()
			);
			$page          = new SettingsPage(
				$settings,
				$license,
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);

			ob_start();
			$page->render_content( array( 'section' => 'license' ) );
			$html = (string) ob_get_clean();

			assert_same( 1, substr_count( $html, '<form' ) );
			assert_contains( 'id="upsellbay_license_activate"', $html );
			assert_contains( 'name="upsellbay_new_license_key"', $html );
			assert_contains( 'Activate License', $html );

			$result = $page->save(
				array(
					'nonce'                    => 'good',
					'upsellbay_new_license_key' => 'WPAB-ABCDEFGHIJKL-123456789012',
				)
			);

			assert_true( $result['success'] );
			assert_same( 'active', $license_state['status'] );
			assert_same( 'WPAB-ABCDEFGHIJKL-123456789012', $license_state['key'] );
			assert_false( isset( $stored['license']['key'] ) );
		},
		'settings tab render processes posted license saves' => static function (): void {
			$license_state = array();
			$settings      = new Settings( static fn (): array => array(), static fn (): bool => true );
			$license       = new LicenseClient(
				static function () use ( &$license_state ): array {
					return $license_state;
				},
				static function ( array $state ) use ( &$license_state ): bool {
					$license_state = $state;
					return true;
				},
				static fn (): string => 'store.test',
				upsellbay_test_valid_license_post()
			);
			$page          = new SettingsPage(
				$settings,
				$license,
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);
			$previous_post = $_POST;
			$previous_method = $_SERVER['REQUEST_METHOD'] ?? null;

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = array(
				'nonce'                    => 'good',
				'upsellbay_new_license_key' => 'WPAB-ZYXWVUTSRQPO-123456789012',
			);

			ob_start();
			$page->render_content();
			$html = (string) ob_get_clean();

			$_POST = $previous_post;
			if ( null === $previous_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $previous_method;
			}

			assert_same( 'active', $license_state['status'] );
			assert_same( 'WPAB-ZYXWVUTSRQPO-123456789012', $license_state['key'] );
			assert_contains( 'Settings saved and license activated successfully.', $html );
		},
		'settings tab post is processed before top page notices render' => static function (): void {
			$license_state = array( 'status' => 'inactive' );
			$settings      = new Settings( static fn (): array => array(), static fn (): bool => true );
			$license       = new LicenseClient(
				static function () use ( &$license_state ): array {
					return $license_state;
				},
				static function ( array $state ) use ( &$license_state ): bool {
					$license_state = $state;
					return true;
				},
				static fn (): string => 'store.test',
				upsellbay_test_valid_license_post()
			);
			$page          = new SettingsPage(
				$settings,
				$license,
				static fn (): bool => true,
				static fn ( string $nonce ): bool => 'good' === $nonce
			);
			$registry      = new TabRegistry(
				array(
					new AdminTab(
						'settings',
						'Settings',
						static function () use ( $page ): void {
							$page->render_content();
						},
						static function () use ( $page ): void {
							$page->prepare_render();
						}
					),
				)
			);

			$previous_hooks  = $GLOBALS['upsellbay_test_hooks'] ?? array();
			$previous_post   = $_POST;
			$previous_method = $_SERVER['REQUEST_METHOD'] ?? null;

			add_action(
				'upsellbay_admin_page_heading_before',
				static function () use ( &$license_state ): void {
					echo '<div data-license-before="' . esc_attr( (string) ( $license_state['status'] ?? 'missing' ) ) . '"></div>';
				}
			);

			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = array(
				'nonce'                    => 'good',
				'upsellbay_new_license_key' => 'WPAB-PRENDERTEST-123456789012',
			);

			ob_start();
			( new AdminPage( $registry, new TabRouter( $registry ) ) )->render( array( 'tab' => 'settings' ) );
			$html = (string) ob_get_clean();

			$GLOBALS['upsellbay_test_hooks'] = $previous_hooks;
			$_POST                           = $previous_post;
			if ( null === $previous_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $previous_method;
			}

			assert_same( 'active', $license_state['status'] );
			assert_contains( 'data-license-before="active"', $html );
			assert_contains( 'Settings saved and license activated successfully.', $html );
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

			assert_contains( 'Store offer status', $dashboard_html );
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

/**
 * Build a fake successful license-server activation callback.
 *
 * @since 1.0.0
 *
 * @return callable(string, array<string, mixed>): array<string, mixed>
 */
function upsellbay_test_valid_license_post(): callable {
	return static function ( string $url, array $args ): array {
		unset( $url, $args );

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'success'    => true,
					'message'    => 'License activated successfully.',
					'license'    => 'valid',
					'expires_at' => '2026-12-31 23:59:59',
				)
			),
		);
	};
}
