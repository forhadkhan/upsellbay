<?php
/**
 * Phase 3 admin architecture tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Admin\AdminAssets;
use WPAnchorBay\UpsellBayPro\Admin\AdminBar;
use WPAnchorBay\UpsellBayPro\Admin\CompatibilityNotice;
use WPAnchorBay\UpsellBayPro\Admin\Coexistence;
use WPAnchorBay\UpsellBayPro\Admin\PluginActionLinks;
use WPAnchorBay\UpsellBayPro\Admin\Dashboard\DashboardPage;
use WPAnchorBay\UpsellBayPro\Admin\Help\HelpPage;
use WPAnchorBay\UpsellBayPro\Admin\Offers\OfferVisibilityPanel;
use WPAnchorBay\UpsellBayPro\Admin\OverviewSummary;
use WPAnchorBay\UpsellBayPro\Admin\Settings\BasicSection;
use WPAnchorBay\UpsellBayPro\Admin\Settings\SettingsPage;
use WPAnchorBay\UpsellBayPro\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBayPro\Api\ProductsController;
use WPAnchorBay\UpsellBayPro\Api\Routes\ProductsRoute;
use WPAnchorBay\UpsellBayPro\Core\Constants;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Data\OfferRepository;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferConflictDetector;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferVisibilityInspector;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBayPro\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBayPro\Integrations\Licensing\LicenseClient;
use WPAnchorBay\UpsellBayPro\Utils\ImportExporter;

/**
 * Returns Phase 3 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_admin_architecture_tests(): array {
	return array(
		'pro registers no admin menu of its own, only the host plugin does' => static function (): void {
			// Pro is a guest add-on: it must not register any wp-admin menu/page.
			// All admin surfaces live on the host (upsellbay-order-bump-for-woocommerce)
			// plugin's single page; Pro attaches via extension hooks instead.
			assert_false( class_exists( 'WPAnchorBay\\UpsellBayPro\\Admin\\AdminPageRegistrar' ) );
			assert_false( class_exists( 'WPAnchorBay\\UpsellBayPro\\Admin\\AdminPage' ) );
			assert_false( method_exists( \WPAnchorBay\UpsellBayPro\Core\Plugin::class, 'register_admin_pages' ) );

			$plugin_php = (string) file_get_contents( dirname( __DIR__ ) . '/app/Core/Plugin.php' );
			assert_false( str_contains( $plugin_php, "add_action( 'admin_menu'" ) );
		},
		'plugin action links prepend manage and docs links for eligible admins' => static function (): void {
			$actions = array(
				'deactivate' => '<a href="plugins.php?action=deactivate">Deactivate</a>',
			);

			$GLOBALS['upsellbay_test_current_user_can'] = array(
				'manage_woocommerce' => true,
			);

			$links = ( new PluginActionLinks() )->add_action_links( $actions );

			unset( $GLOBALS['upsellbay_test_current_user_can'] );

			// Pro only adds a Docs link; the Manage link lives on the host plugin's own row.
			assert_same( array( 'docs', 'deactivate' ), array_keys( $links ) );
			assert_contains( 'https://docs.wpanchorbay.com/upsellbay/', $links['docs'] );
			assert_contains( 'target="_blank"', $links['docs'] );
			assert_contains( 'rel="noopener noreferrer"', $links['docs'] );
		},
		'admin header css styles page license banner and suppresses tab focus side borders' => static function (): void {
			$root   = dirname( __DIR__ );
			$plugin = (string) file_get_contents( $root . '/app/Core/Plugin.php' );
			$css    = (string) file_get_contents( $root . '/assets/admin/css/upsellbay-admin.css' );

			assert_contains( 'upsellbay-license-banner', $plugin );
			assert_contains( 'upsellbay_pro_admin_page_license_banner', $plugin );
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

			assert_contains( 'subsubsub upsellbay-pro-settings-section-menu', $general_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings" class="current" aria-current="page">General</a>', $general_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings&amp;section=data">Data</a>', $general_html );
			assert_contains( 'admin.php?page=upsellbay&amp;tab=settings&amp;section=license">License</a>', $general_html );
			assert_contains( '<h2>Basic</h2>', $general_html );
			assert_contains( '<h2>Style</h2>', $general_html );
			assert_contains( 'class="upsellbay-color-picker"', $general_html );
			assert_contains( 'data-default-color="#3858e9"', $general_html );
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

			assert_same( 1, substr_count( $html, '<form method="post"' ) );
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
		'license admin actions redirect back to the license settings section' => static function (): void {
			$plugin = (string) file_get_contents( dirname( __DIR__ ) . '/app/Core/Plugin.php' );

			assert_true( substr_count( $plugin, 'admin_url( Constants::SETTINGS_LICENSE_ACTIVATION_URL )' ) >= 3 );
			assert_false( str_contains( $plugin, "admin_url( 'admin.php?page=upsellbay-pro&tab=settings' )" ) );
		},
		'settings license removal uses woocommerce confirmation modal data instead of inline confirm' => static function (): void {
			$license = new LicenseClient(
				static fn (): array => array(
					'status' => 'active',
					'key'    => 'WPAB-ABCDEFGHIJKL-123456789012',
				),
				static fn (): bool => true
			);
			$page    = new SettingsPage(
				new Settings( static fn (): array => array(), static fn (): bool => true ),
				$license,
				static fn (): bool => true,
				static fn (): bool => true
			);

			ob_start();
			$page->render_content( array( 'section' => 'license' ) );
			$html = (string) ob_get_clean();

			assert_contains( 'upsellbay-license-remove-trigger', $html );
			assert_contains( 'data-modal-title="Remove License"', $html );
			assert_contains( 'data-modal-confirm="Remove License"', $html );
			assert_contains( 'data-modal-cancel="Cancel"', $html );
			assert_false( str_contains( $html, 'onclick="return confirm' ) );
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
			assert_same(
				array( 'upsellbay-admin', 'upsellbay-color-picker' ),
				array_keys( $assets->assets_for_screen( 'woocommerce_page_upsellbay', array( 'tab' => 'settings' ) ) )
			);
			assert_same(
				array( 'jquery', 'wp-color-picker', 'wp-util', 'wc-backbone-modal' ),
				$assets->assets_for_screen( 'woocommerce_page_upsellbay', array( 'tab' => 'settings' ) )['upsellbay-admin']['deps']
			);
			assert_same( array(), $assets->assets_for_screen( 'woocommerce_page_upsellbay-add-offer' ) );
		},
		'products route exposes searchable category tag and role selectors' => static function (): void {
			$GLOBALS['upsellbay_test_current_user_can'] = array( 'manage_woocommerce' => true );
			$GLOBALS['upsellbay_test_terms']['product_cat'][9] = (object) array(
				'term_id' => 9,
				'name'    => 'Accessories',
				'count'   => 3,
				'slug'    => 'accessories',
			);
			$GLOBALS['upsellbay_test_terms']['product_tag'][12] = (object) array(
				'term_id' => 12,
				'name'    => 'Featured',
				'count'   => 2,
				'slug'    => 'featured',
			);
			$GLOBALS['upsellbay_test_roles'] = array(
				'customer'     => array( 'name' => 'Customer' ),
				'shop_manager' => array( 'name' => 'Shop manager' ),
			);

			$controller = new ProductsController();
			$route      = new ProductsRoute( $controller );

			assert_true( $route->can_manage() );
			assert_same( 'Accessories', $controller->categories( new WP_REST_Request( array( 'search' => 'access' ) ) )->get_data()[0]['name'] );
			assert_same( 'Featured', $controller->tags( new WP_REST_Request( array( 'search' => 'feat' ) ) )->get_data()[0]['name'] );
			assert_same( 'customer', $controller->roles( new WP_REST_Request( array( 'search' => 'cust' ) ) )->get_data()[0]['id'] );

			unset( $GLOBALS['upsellbay_test_roles'], $GLOBALS['upsellbay_test_terms']['product_cat'][9], $GLOBALS['upsellbay_test_terms']['product_tag'][12] );
		},
		'dashboard shows overview and analytics from aggregate stats without live order scans' => static function (): void {
			$captured_filters = array();
			$stats = new StatsRepository(
				static function (): void {
				},
				static function ( array $filters ) use ( &$captured_filters ): array {
					$captured_filters = $filters;
					return array(
					array(
						'views'          => 10,
						'accepts'        => 2,
						'dismissals'     => 1,
						'orders'         => 2,
						'revenue'        => '40.000000',
						'discount_total' => '5.000000',
					),
					);
				}
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
			( new DashboardPage( $overview, $stats ) )->render( array( 'range' => '90' ) );
			$html = (string) ob_get_clean();

			assert_contains( '10', $html );
			assert_contains( '20.00%', $html );
			assert_contains( 'Active offers', $html );
			assert_contains( 'On', $html );
			assert_contains( 'range=7', $html );
			assert_contains( 'range=30', $html );
			assert_contains( 'range=90', $html );
			assert_contains( 'class="button button-primary" aria-current="true">Last 90 days', $html );
			assert_contains( 'Performance (Last 90 days)', $html );
			assert_same( 90 * 86400, strtotime( (string) $captured_filters['end_date'] ) - strtotime( (string) $captured_filters['start_date'] ) );
		},
		'compatibility coexistence help and admin bar stay product isolated' => static function (): void {
			$settings    = new Settings( static fn (): array => array( 'test_mode' => true ), static fn (): bool => true );
			$coexistence = new Coexistence( static fn ( string $plugin ): bool => 'cartbay/cartbay.php' === $plugin );
			$notice      = new CompatibilityNotice( $settings, $coexistence );
			$help        = new HelpPage();
			$admin_bar   = new AdminBar( $settings, static fn (): bool => true );

			assert_true( $coexistence->is_cartbay_active() );
			assert_same( array(), $notice->notices() );
			assert_contains( 'AOV offer', implode( ' ', $help->links()[0] ) );
			assert_true( $admin_bar->should_show_indicator() );
		},
		'compatibility notice surfaces scanner findings' => static function (): void {
			$settings = new Settings( static fn (): array => array(), static fn (): bool => true );
			$notice   = new CompatibilityNotice(
				$settings,
				new CompatibilityScanner( static fn ( string $plugin ): bool => 'checkout-for-woocommerce/checkout-for-woocommerce.php' === $plugin )
			);

			assert_same( 'warning', $notice->notices()[0]['type'] );
			assert_contains( 'CheckoutWC replaces the standard checkout surface', $notice->notices()[0]['message'] );
		},
		'visibility panel explains checkout preview prerequisites' => static function (): void {
			$offer      = upsellbay_phase4_offer( 88, 'checkout_bump', 25, 1 );
			$panel      = new OfferVisibilityPanel(
				new OfferVisibilityInspector(
					new Settings( static fn (): array => array(), static fn (): bool => true ),
					new OfferValidator( new OfferSchema(), static fn (): bool => true ),
					new OfferPrioritizer( new RuleEvaluator( new RuleParser() ), static fn (): bool => true ),
					upsellbay_test_offer_repository( array( 88 => $offer ) )
				)
			);

			ob_start();
			$panel->render( $offer );
			$html = (string) ob_get_clean();

			assert_contains( 'Visibility Inspector', $html );
			assert_contains( 'Checkout previews need at least one item in the cart', $html );
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

			unset( $service );

			ob_start();
			( new DashboardPage( new OverviewSummary( $repository, $stats, $settings ), $stats ) )->render();
			$dashboard_html = (string) ob_get_clean();

			ob_start();
			( new SettingsPage( $settings ) )->render();
			$settings_html = (string) ob_get_clean();

			ob_start();
			( new ToolsPage( new ImportExporter( new OfferValidator( new OfferSchema(), static fn (): bool => true ), upsellbay_test_offer_repository( array() ), $settings ), $settings ) )->render();
			$tools_html = (string) ob_get_clean();

			assert_contains( 'Store offer status', $dashboard_html );
			assert_contains( 'Active offers', $dashboard_html );
			assert_contains( '25.00%', $dashboard_html );
			assert_contains( 'Performance (Last 30 days)', $dashboard_html );
			assert_contains( 'form-table', $settings_html );
			assert_contains( 'name="test_mode"', $settings_html );
			assert_contains( 'Export Data', $tools_html );
			assert_contains( 'Import Data', $tools_html );
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
