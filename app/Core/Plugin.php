<?php
/**
 * Plugin bootstrap coordinator.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Admin\AdminAssets;
use WPAnchorBay\UpsellBay\Admin\AdminBar;
use WPAnchorBay\UpsellBay\Admin\AdminPage;
use WPAnchorBay\UpsellBay\Admin\AdminPageRegistrar;
use WPAnchorBay\UpsellBay\Admin\CompatibilityNotice;
use WPAnchorBay\UpsellBay\Admin\Coexistence;
use WPAnchorBay\UpsellBay\Admin\Dashboard\DashboardPage;
use WPAnchorBay\UpsellBay\Admin\Help\HelpPage;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabFactory;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabRegistry;
use WPAnchorBay\UpsellBay\Admin\Navigation\TabRouter;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferEditPage;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferListTable;
use WPAnchorBay\UpsellBay\Admin\Offers\OffersPage;
use WPAnchorBay\UpsellBay\Admin\OverviewSummary;
use WPAnchorBay\UpsellBay\Admin\PreviewLinks;
use WPAnchorBay\UpsellBay\Admin\Settings\LogsSectionRenderer;
use WPAnchorBay\UpsellBay\Admin\Settings\SettingsPage;
use WPAnchorBay\UpsellBay\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBay\Admin\Wizard\WizardController;
use WPAnchorBay\UpsellBay\Api\ProductsController;
use WPAnchorBay\UpsellBay\Api\Routes\LicenseRoute;
use WPAnchorBay\UpsellBay\Api\Routes\OfferPreviewRoute;
use WPAnchorBay\UpsellBay\Api\Routes\ProductsRoute;
use WPAnchorBay\UpsellBay\Api\Routes\PublicOfferRoutes;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Data\StatsRepository;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBay\Domain\Analytics\StatsReconciler;
use WPAnchorBay\UpsellBay\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBay\Domain\Cart\CartValidator;
use WPAnchorBay\UpsellBay\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBay\Domain\Attribution\AttributionReader;
use WPAnchorBay\UpsellBay\Domain\Attribution\AttributionWriter;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferConflictDetector;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Offers\ProductRecommendationAssistant;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBay\Domain\Storefront\CartCrossSellRenderer;
use WPAnchorBay\UpsellBay\Domain\Storefront\ClassicCheckoutBump;
use WPAnchorBay\UpsellBay\Domain\Storefront\PlacementRenderer;
use WPAnchorBay\UpsellBay\Domain\Storefront\ProductPageRenderer;
use WPAnchorBay\UpsellBay\Domain\Storefront\StorefrontController;
use WPAnchorBay\UpsellBay\Domain\Storefront\ThankYouOfferRenderer;
use WPAnchorBay\UpsellBay\Integrations\WooCommerce\BlockCheckoutIntegration;
use WPAnchorBay\UpsellBay\Integrations\WooCommerce\CheckoutFields;
use WPAnchorBay\UpsellBay\Integrations\WooCommerce\CouponLimiter;
use WPAnchorBay\UpsellBay\Integrations\WooCommerce\StoreApiExtender;
use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;
use WPAnchorBay\UpsellBay\Utils\RateLimiter;
use WPAnchorBay\UpsellBay\Utils\TokenHelper;
use WPAnchorBay\UpsellBay\Data\LogRepository;
use WPAnchorBay\UpsellBay\Domain\Logging\LoggerInterface;
use WPAnchorBay\UpsellBay\Domain\Logging\DatabaseLogger;

/**
 * Owns service registration, hook topology, and startup order.
 *
 * @since 1.0.0
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container.
	 *
	 * @since 1.0.0
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether hooks have been registered.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Container|null $container Optional container.
	 */
	private function __construct( ?Container $container = null ) {
		$this->container = $container ?? new Container();
		$this->register_services();
	}

	/**
	 * Return singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @param Container|null $container Optional container for first call.
	 */
	public static function instance( ?Container $container = null ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $container );
		}

		return self::$instance;
	}

	/**
	 * Initialize hooks once.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;
		$this->register_hooks();
		$this->container->get( Updater::class )->init();

		if ( function_exists( 'do_action' ) ) {
			do_action( Constants::hook_name( 'loaded' ), $this );
		}
	}

	/**
	 * Return the service container.
	 *
	 * @since 1.0.0
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Register foundation services.
	 *
	 * @since 1.0.0
	 */
	private function register_services(): void {
		$this->container->set( Settings::class, static fn (): Settings => new Settings() );
		$this->container->set( Scheduler::class, static fn (): Scheduler => new Scheduler() );
		$this->container->set(
			Installer::class,
			static fn ( Container $container ): Installer => new Installer(
				$container->get( Settings::class ),
				$container->get( Scheduler::class )
			)
		);
		$this->container->set( LicenseClient::class, static fn (): LicenseClient => new LicenseClient() );
		$this->container->set( Updater::class, static fn ( Container $container ): Updater => new Updater( $container->get( LicenseClient::class ) ) );
		$this->container->set( OfferSchema::class, static fn (): OfferSchema => new OfferSchema() );
		$this->container->set( OfferDefaults::class, static fn (): OfferDefaults => new OfferDefaults() );
		$this->container->set( OfferValidator::class, static fn ( Container $container ): OfferValidator => new OfferValidator( $container->get( OfferSchema::class ) ) );
		$this->container->set( OfferRepository::class, static fn ( Container $container ): OfferRepository => new OfferRepository( $container->get( OfferValidator::class ) ) );
		$this->container->set( OfferConflictDetector::class, static fn ( Container $container ): OfferConflictDetector => new OfferConflictDetector( $container->get( OfferRepository::class ) ) );
		$this->container->set( OfferService::class, static fn ( Container $container ): OfferService => new OfferService( $container->get( OfferRepository::class ), $container->get( OfferValidator::class ) ) );
		$this->container->set( RuleParser::class, static fn (): RuleParser => new RuleParser() );
		$this->container->set( RuleEvaluator::class, static fn ( Container $container ): RuleEvaluator => new RuleEvaluator( $container->get( RuleParser::class ) ) );
		$this->container->set( DiscountCalculator::class, static fn (): DiscountCalculator => new DiscountCalculator() );
		$this->container->set( DiscountApplier::class, static fn (): DiscountApplier => new DiscountApplier() );
		$this->container->set( StatsRepository::class, static fn (): StatsRepository => new StatsRepository() );
		$this->container->set( CartSession::class, static fn ( Container $container ): CartSession => new CartSession( null, null, $container->get( TokenHelper::class ) ) );
		$this->container->set( CartValidator::class, static fn ( Container $container ): CartValidator => new CartValidator( $container->get( RuleEvaluator::class ), $container->get( DiscountCalculator::class ) ) );
		$this->container->set( CartMutator::class, static fn ( Container $container ): CartMutator => new CartMutator( $container->get( CartSession::class ), $container->get( CartValidator::class ), $container->get( DiscountCalculator::class ) ) );
		$this->container->set( AnalyticsRecorder::class, static fn ( Container $container ): AnalyticsRecorder => new AnalyticsRecorder( $container->get( StatsRepository::class ) ) );
		$this->container->set( AnalyticsService::class, static fn ( Container $container ): AnalyticsService => new AnalyticsService( $container->get( AnalyticsRecorder::class ) ) );
		$this->container->set( StatsReconciler::class, static fn ( Container $container ): StatsReconciler => new StatsReconciler( $container->get( StatsRepository::class ) ) );
		$this->container->set( OfferPrioritizer::class, static fn ( Container $container ): OfferPrioritizer => new OfferPrioritizer( $container->get( RuleEvaluator::class ) ) );
		$this->container->set(
			PlacementRenderer::class,
			static fn ( Container $container ): PlacementRenderer => new PlacementRenderer(
				$container->get( OfferPrioritizer::class ),
				$container->get( AnalyticsRecorder::class ),
				array(
					'checkout_bump'  => new ClassicCheckoutBump( $container->get( DiscountCalculator::class ) ),
					'product_upsell' => new ProductPageRenderer( $container->get( DiscountCalculator::class ) ),
					'cart_crosssell' => new CartCrossSellRenderer( $container->get( DiscountCalculator::class ) ),
					'thankyou_offer' => new ThankYouOfferRenderer( $container->get( DiscountCalculator::class ) ),
				)
			)
		);
		$this->container->set( AttributionWriter::class, static fn (): AttributionWriter => new AttributionWriter() );
		$this->container->set( AttributionReader::class, static fn (): AttributionReader => new AttributionReader() );
		$this->container->set( CompatibilityScanner::class, static fn (): CompatibilityScanner => new CompatibilityScanner() );
		$this->container->set(
			PublicOfferRoutes::class,
			static fn ( Container $container ): PublicOfferRoutes => new PublicOfferRoutes(
				static fn ( int $offer_id ): ?array => $container->get( OfferService::class )->get( $offer_id ),
				$container->get( CartMutator::class ),
				$container->get( CartSession::class ),
				static fn ( string $endpoint, string $client_key ): bool => $container->get( RateLimiter::class )->hit( $endpoint, $client_key ),
				null,
				$container->get( AnalyticsService::class )
			)
		);
		$this->container->set( ProductsController::class, static fn ( Container $container ): ProductsController => new ProductsController( $container->get( ProductRecommendationAssistant::class ) ) );
		$this->container->set( ProductsRoute::class, static fn ( Container $container ): ProductsRoute => new ProductsRoute( $container->get( ProductsController::class ) ) );
		$this->container->set( OfferPreviewRoute::class, static fn ( Container $container ): OfferPreviewRoute => new OfferPreviewRoute( $container->get( OfferService::class ) ) );
		$this->container->set( CheckoutFields::class, static fn (): CheckoutFields => new CheckoutFields() );
		$this->container->set( StoreApiExtender::class, static fn ( Container $container ): StoreApiExtender => new StoreApiExtender( $container->get( OfferRepository::class ), $container->get( OfferPrioritizer::class ), $container->get( CartSession::class ), $container->get( AnalyticsRecorder::class ), $container->get( Settings::class ) ) );
		$this->container->set( BlockCheckoutIntegration::class, static fn ( Container $container ): BlockCheckoutIntegration => new BlockCheckoutIntegration( $container->get( Settings::class ), $container->get( CartSession::class ) ) );
		$this->container->set( StorefrontController::class, static fn ( Container $container ): StorefrontController => new StorefrontController( $container->get( OfferRepository::class ), $container->get( PlacementRenderer::class ), $container->get( CartSession::class ), $container->get( Settings::class ) ) );
		$this->container->set( CouponLimiter::class, static fn (): CouponLimiter => new CouponLimiter() );
		$this->container->set( ImportExporter::class, static fn ( Container $container ): ImportExporter => new ImportExporter( $container->get( OfferValidator::class ) ) );
		$this->container->set( LogRepository::class, static fn (): LogRepository => new LogRepository() );
		$this->container->set( LoggerInterface::class, static fn ( Container $container ): DatabaseLogger => new DatabaseLogger( $container->get( LogRepository::class ) ) );
		$this->container->set( TokenHelper::class, static fn (): TokenHelper => new TokenHelper() );
		$this->container->set( RateLimiter::class, static fn (): RateLimiter => new RateLimiter() );
		$this->container->set( OfferListTable::class, static fn ( Container $container ): OfferListTable => new OfferListTable( $container->get( OfferRepository::class ), $container->get( OfferService::class ), $container->get( OfferConflictDetector::class ) ) );
		$this->container->set( OffersPage::class, static fn ( Container $container ): OffersPage => new OffersPage( $container->get( OfferListTable::class ) ) );
		$this->container->set( OfferEditPage::class, static fn ( Container $container ): OfferEditPage => new OfferEditPage( $container->get( OfferService::class ), $container->get( OfferValidator::class ), null, null, $container->get( OfferDefaults::class ), null, $container->get( OfferConflictDetector::class ), $container->get( LoggerInterface::class ) ) );
		$this->container->set( WizardController::class, static fn ( Container $container ): WizardController => new WizardController( $container->get( OfferService::class ), $container->get( Settings::class ), $container->get( OfferDefaults::class ), null, null, $container->get( LoggerInterface::class ) ) );
		$this->container->set( PreviewLinks::class, static fn (): PreviewLinks => new PreviewLinks() );
		$this->container->set(
			ProductRecommendationAssistant::class,
			static fn (): ProductRecommendationAssistant => new ProductRecommendationAssistant(
				static function ( int $product_id ): array {
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
					return $product ? $product->get_upsell_ids() : array();
				},
				static function ( int $product_id ): array {
					$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
					return $product ? $product->get_cross_sell_ids() : array();
				},
				static function ( int $category_id ): array {
					return function_exists( 'wc_get_products' ) ? wc_get_products(
						array(
							'category' => array( get_term_by( 'id', $category_id, 'product_cat' )->slug ?? '' ),
							'return'   => 'ids',
							'limit'    => 5,
						)
					) : array();
				}
			)
		);
		$this->container->set( LogsSectionRenderer::class, static fn ( Container $container ): LogsSectionRenderer => new LogsSectionRenderer( $container->get( LogRepository::class ) ) );
		$this->container->set( SettingsPage::class, static fn ( Container $container ): SettingsPage => new SettingsPage( $container->get( Settings::class ), null, null, null, null, null, $container->get( LogsSectionRenderer::class ) ) );
		$this->container->set( ToolsPage::class, static fn ( Container $container ): ToolsPage => new ToolsPage( $container->get( ImportExporter::class ), $container->get( Settings::class ) ) );
		$this->container->set( HelpPage::class, static fn (): HelpPage => new HelpPage() );
		$this->container->set( OverviewSummary::class, static fn ( Container $container ): OverviewSummary => new OverviewSummary( $container->get( OfferRepository::class ), $container->get( StatsRepository::class ), $container->get( Settings::class ) ) );
		$this->container->set( DashboardPage::class, static fn ( Container $container ): DashboardPage => new DashboardPage( $container->get( OverviewSummary::class ), $container->get( StatsRepository::class ) ) );
		$this->container->set(
			TabFactory::class,
			static fn ( Container $container ): TabFactory => new TabFactory(
				$container->get( DashboardPage::class ),
				$container->get( OffersPage::class ),
				$container->get( OfferEditPage::class ),
				$container->get( SettingsPage::class ),
				$container->get( ToolsPage::class ),
				$container->get( WizardController::class ),
				$container->get( HelpPage::class )
			)
		);
		$this->container->set(
			TabRegistry::class,
			static fn ( Container $container ): TabRegistry => $container->get( TabFactory::class )->registry()
		);
		$this->container->set( TabRouter::class, static fn ( Container $container ): TabRouter => new TabRouter( $container->get( TabRegistry::class ) ) );
		$this->container->set( AdminPage::class, static fn ( Container $container ): AdminPage => new AdminPage( $container->get( TabRegistry::class ), $container->get( TabRouter::class ) ) );
		$this->container->set( Coexistence::class, static fn (): Coexistence => new Coexistence() );
		$this->container->set( CompatibilityNotice::class, static fn ( Container $container ): CompatibilityNotice => new CompatibilityNotice( $container->get( Settings::class ), $container->get( Coexistence::class ) ) );
		$this->container->set( AdminAssets::class, static fn (): AdminAssets => new AdminAssets() );
		$this->container->set( AdminBar::class, static fn ( Container $container ): AdminBar => new AdminBar( $container->get( Settings::class ) ) );
		$this->container->set(
			AdminPageRegistrar::class,
			static fn ( Container $container ): AdminPageRegistrar => new AdminPageRegistrar(
				null,
				array(
					'upsellbay' => array( $container->get( AdminPage::class ), 'render' ),
				)
			)
		);
	}

	/**
	 * Register WordPress and WooCommerce hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'init', array( $this->container->get( Installer::class ), 'register_offer_post_type' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_dependency_notices' ) );
		add_action( 'admin_notices', array( $this, 'render_license_banner' ) );
		add_action( 'upsellbay_admin_page_license_banner', array( $this, 'render_page_license_banner' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'admin_post_upsellbay_activate_license', array( $this, 'handle_activate_license' ) );
		add_action( 'admin_post_upsellbay_remove_license', array( $this, 'handle_remove_license' ) );
		add_action( 'admin_post_upsellbay_check_license', array( $this, 'handle_check_license' ) );
		add_action( 'admin_post_upsellbay_delete_offer', array( $this, 'handle_delete_offer' ) );

		$this->container->get( AdminAssets::class )->register_hooks();
		$this->container->get( AdminBar::class )->register_hooks();
		$this->container->get( CompatibilityNotice::class )->register_hooks();
		$this->container->get( StoreApiExtender::class )->register_hooks();
		$this->container->get( BlockCheckoutIntegration::class )->register_hooks();
		$this->container->get( StorefrontController::class )->register_hooks();
		$this->container->get( CouponLimiter::class )->register();

		add_action( 'update_option_' . Constants::SETTINGS_OPTION, array( $this, 'log_settings_update' ), 10, 3 );
		add_action( Constants::hook_name( 'offer_created' ), array( $this, 'log_offer_created' ), 10, 2 );
		add_action( Constants::hook_name( 'offer_updated' ), array( $this, 'log_offer_updated' ), 10, 2 );
		add_action( Constants::hook_name( 'offer_deleted' ), array( $this, 'log_offer_deleted' ) );
		add_action( Constants::hook_name( 'offer_accepted' ), array( $this, 'log_offer_accepted' ), 10, 3 );
		add_action( Constants::hook_name( 'offer_dismissed' ), array( $this, 'log_offer_dismissed' ), 10, 2 );
		add_action( Constants::hook_name( 'offer_rendered' ), array( $this, 'log_offer_rendered' ), 10, 4 );
		add_action( Constants::hook_name( 'api_request_failed' ), array( $this, 'log_api_request_failed' ), 10, 3 );
		add_action( Constants::hook_name( 'license_activated' ), array( $this, 'log_license_activated' ) );
		add_action( Constants::hook_name( 'license_activation_failed' ), array( $this, 'log_license_activation_failed' ), 10, 3 );
		add_action( Constants::hook_name( 'license_check_failed' ), array( $this, 'log_license_check_failed' ), 10, 2 );
		add_action( Constants::hook_name( 'attribution_written' ), array( $this, 'log_attribution_written' ), 10, 4 );
		add_action( Constants::hook_name( 'follow_on_order_created' ), array( $this, 'log_follow_on_order_created' ), 10, 3 );
		add_action( Constants::hook_name( 'daily_stats_reconciled' ), array( $this, 'log_daily_stats_reconciled' ), 10, 3 );
		add_action( Constants::hook_name( 'prune_logs' ), array( $this, 'prune_logs' ) );

		add_action( 'rest_api_init', array( $this->container->get( PublicOfferRoutes::class ), 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->container->get( ProductsRoute::class ), 'register' ) );
		add_action( 'rest_api_init', array( $this->container->get( OfferPreviewRoute::class ), 'register_routes' ) );
		add_action( 'rest_api_init', array( $this, 'register_license_routes' ) );
		add_action( Constants::hook_name( 'check_license' ), array( $this, 'check_license' ) );
		add_action( 'woocommerce_init', array( $this->container->get( CheckoutFields::class ), 'register' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_offer_discounts' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'write_offer_line_item_attribution' ), 10, 4 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'record_order_offer_analytics' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'record_order_offer_analytics' ) );
	}

	/**
	 * Register admin pages after WordPress admin APIs and translations are ready.
	 *
	 * @since 1.0.0
	 */
	public function register_admin_pages(): void {
		$this->container->get( AdminPageRegistrar::class )->register_pages();
	}

	/**
	 * Apply session-scoped offer prices during cart total calculation.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $cart WooCommerce cart object.
	 */
	public function apply_offer_discounts( $cart ): void {
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( is_array( $cart_item ) ) {
				$this->container->get( DiscountApplier::class )->apply_to_cart_item( $cart_item );
			}
		}
	}

	/**
	 * Persist accepted offer attribution onto checkout order items.
	 *
	 * @since 1.0.0
	 *
	 * @param object               $item          WooCommerce order item.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $values        Cart item values.
	 * @param object|null          $order         WooCommerce order.
	 */
	public function write_offer_line_item_attribution( object $item, string $cart_item_key, array $values, ?object $order = null ): void {
		unset( $cart_item_key );

		$offer_id = (int) ( $values[ Constants::ATTRIBUTION_OFFER_ID ] ?? 0 );
		if ( $offer_id <= 0 ) {
			return;
		}

		$offer     = $this->container->get( OfferService::class )->get( $offer_id );
		$placement = (string) ( $values[ Constants::ATTRIBUTION_OFFER_PLACEMENT ] ?? $values[ Constants::ATTRIBUTION_OFFER_TYPE ] ?? '' );
		$discount  = (string) ( $values[ Constants::ATTRIBUTION_DISCOUNT_AMOUNT ] ?? '0.000000' );
		$context   = (string) ( $values[ Constants::ATTRIBUTION_SOURCE_CONTEXT ] ?? $values['_ub_source_context'] ?? $placement );

		if ( null === $offer ) {
			$offer = array(
				'id'   => $offer_id,
				'meta' => array(
					'_ub_offer_type'    => (string) ( $values[ Constants::ATTRIBUTION_OFFER_TYPE ] ?? $placement ),
					'_ub_discount_type' => (string) ( $values[ Constants::ATTRIBUTION_DISCOUNT_TYPE ] ?? 'none' ),
				),
			);
		}

		$this->container->get( AttributionWriter::class )->write_order_item( $item, $offer, $placement, $discount, $context );

		$source_order_id = (int) ( $values[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] ?? 0 );
		if ( $source_order_id > 0 && null !== $order ) {
			$this->container->get( AttributionWriter::class )->write_follow_on_order( $order, $source_order_id, $offer_id );
		}
	}

	/**
	 * Record order-level offer analytics once per order.
	 *
	 * @since 1.0.0
	 *
	 * @param int          $order_id    Order ID.
	 * @param array<mixed> $posted_data Posted checkout data.
	 * @param object|null  $order       WooCommerce order.
	 */
	public function record_order_offer_analytics( int $order_id, array $posted_data = array(), ?object $order = null ): void {
		unset( $posted_data );

		if ( null === $order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return;
		}

		if ( method_exists( $order, 'get_meta' ) && $order->get_meta( '_ub_order_analytics_recorded' ) ) {
			return;
		}

		$date = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}

			$offer_id = (int) $item->get_meta( Constants::ATTRIBUTION_OFFER_ID );
			if ( $offer_id <= 0 ) {
				continue;
			}

			$placement = (string) $item->get_meta( Constants::ATTRIBUTION_OFFER_PLACEMENT );
			$discount  = (string) $item->get_meta( Constants::ATTRIBUTION_DISCOUNT_AMOUNT );
			$revenue   = method_exists( $item, 'get_total' ) ? (string) $item->get_total() : '0.000000';

			$this->container->get( AnalyticsService::class )->record_event( 'order', $offer_id, $placement, $date, $revenue, $discount );
		}

		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_ub_order_analytics_recorded', true );
		}

		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * Declare WooCommerce feature compatibility at the documented lifecycle point.
	 *
	 * @since 1.0.0
	 */
	public function declare_wc_feature_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) || '' === Constants::plugin_file() ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			Constants::plugin_file(),
			true
		);

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			Constants::plugin_file(),
			true
		);
	}

	/**
	 * Run self-healing migrations and scheduler checks when dependencies are available.
	 *
	 * @since 1.0.0
	 */
	public function maybe_upgrade(): void {
		if ( ! Platform::is_woocommerce_active() ) {
			return;
		}

		$this->container->get( Installer::class )->maybe_upgrade();
	}

	/**
	 * Register license REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_license_routes(): void {
		$route = new LicenseRoute( $this->container->get( LicenseClient::class ) );
		$route->register();
	}

	/**
	 * Run the scheduled license background check.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function check_license(): void {
		$this->container->get( LicenseClient::class )->background_check();
	}

	/**
	 * Handle license activation from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_activate_license(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage licenses.', 'upsellbay' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		check_admin_referer( 'upsellbay_activate_license' );

		$license_key = isset( $_POST['upsellbay_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['upsellbay_license_key'] ) ) : '';
		if ( '' === $license_key && isset( $_POST['upsellbay_new_license_key'] ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['upsellbay_new_license_key'] ) );
		}
		$result = $this->container->get( LicenseClient::class )->activate( $license_key );

		$redirect_url = admin_url( Constants::SETTINGS_LICENSE_ACTIVATION_URL );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( 'wc_error', $result->get_error_message(), $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'wc_message', __( 'License activated successfully.', 'upsellbay' ), $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle license removal from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_remove_license(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage licenses.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_remove_license' );

		$this->container->get( LoggerInterface::class )->info(
			__( 'License removed by user', 'upsellbay' ),
			array(
				'source'  => 'license_management',
				'user_id' => get_current_user_id(),
			)
		);

		$this->container->get( LicenseClient::class )->remove_local();

		$redirect_url = add_query_arg(
			'wc_message',
			__( 'Local license data removed.', 'upsellbay' ),
			admin_url( Constants::SETTINGS_LICENSE_ACTIVATION_URL )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle manual license check from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_check_license(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage licenses.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_check_license' );

		$is_valid     = $this->container->get( LicenseClient::class )->is_valid();
		$redirect_url = admin_url( Constants::SETTINGS_LICENSE_ACTIVATION_URL );

		$this->container->get( LoggerInterface::class )->info(
			/* translators: %s: validity status */
			sprintf( __( 'Manual license check performed. Status: %s', 'upsellbay' ), $is_valid ? 'valid' : 'invalid' ),
			array(
				'source'  => 'license_management',
				'user_id' => get_current_user_id(),
			)
		);

		if ( $is_valid ) {
			$redirect_url = add_query_arg( 'wc_message', __( 'License check complete: valid.', 'upsellbay' ), $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'wc_error', __( 'License check failed: inactive.', 'upsellbay' ), $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle deleting an offer from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_delete_offer(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage offers.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_delete_offer' );

		$offer_id = isset( $_GET['offer_id'] ) ? (int) $_GET['offer_id'] : 0;
		if ( $offer_id > 0 ) {
			$this->container->get( OfferService::class )->delete( $offer_id );
		}

		$redirect_url = admin_url( 'admin.php?page=upsellbay&tab=offers' );
		$redirect_url = add_query_arg( 'wc_message', rawurlencode( __( 'Offer deleted.', 'upsellbay' ) ), $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render a license warning banner on the Plugins admin page.
	 *
	 * Hooked into admin_notices.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_license_banner(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen ) {
			return;
		}

		if ( 'plugins' !== $screen->id ) {
			return;
		}

		$html = $this->get_license_notice_html( 'notice-warning is-dismissible' );

		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render a license warning banner inside UpsellBay admin pages.
	 *
	 * Hooked into upsellbay_admin_page_license_banner so it appears above regular
	 * admin notices, the attached page header, and tab navigation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page_license_banner(): void {
		$html = $this->get_license_page_banner_html();

		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Build the UpsellBay page-level license banner HTML.
	 *
	 * This intentionally does not use the WordPress .notice class. WooCommerce
	 * admin scripts can reparent .notice nodes after load, which moves this
	 * banner back into tab content on some screens.
	 *
	 * @since 1.0.0
	 *
	 * @return string Banner HTML or empty string.
	 */
	private function get_license_page_banner_html(): string {
		return $this->get_license_notice_html( 'upsellbay-license-banner', false );
	}

	/**
	 * Build license warning notice HTML if the license is missing or in a
	 * problem state. Returns empty string when no notice is needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $additional_classes Additional CSS classes for the notice div.
	 * @param bool   $include_notice_class Whether to include the WordPress .notice class.
	 *
	 * @return string Notice HTML or empty string.
	 */
	private function get_license_notice_html( string $additional_classes = 'notice-warning', bool $include_notice_class = true ): string {
		$license_status = $this->container->get( LicenseClient::class )->get_status();
		$status_code    = $license_status['status'] ?? 'inactive';

		// Active or dev-mode license needs no notice.
		if ( in_array( $status_code, array( 'active', 'dev' ), true ) ) {
			return '';
		}

		$settings_license_url = admin_url( Constants::SETTINGS_LICENSE_ACTIVATION_URL );

		$message = match ( $status_code ) {
			'expired'      => __( 'Your UpsellBay license has expired. Renew your license to restore updates and support.', 'upsellbay' ),
			'invalid'      => __( 'The UpsellBay license key on this site is invalid. Please verify and re-enter your license key.', 'upsellbay' ),
			'server_error' => __( 'UpsellBay could not verify your license because the license server is unreachable. Offers will keep running, but updates and support checks are suspended.', 'upsellbay' ),
			default        => __( 'UpsellBay license is not activated. Activate your license key to receive updates and support.', 'upsellbay' ),
		};

		if ( '' === $message ) {
			return '';
		}

		$classes = true === $include_notice_class ? 'notice ' . $additional_classes : $additional_classes;

		return sprintf(
			'<div class="%s" role="alert"><p><strong>%s </strong> <a href="%s" class="button button-small">%s</a></p></div>',
			esc_attr( $classes ),
			esc_html( $message ),
			esc_url( $settings_license_url ),
			esc_html__( 'Manage License', 'upsellbay' )
		);
	}

	/**
	 * Render actionable dependency notices.
	 *
	 * @since 1.0.0
	 */
	public function render_dependency_notices(): void {
		if ( ! function_exists( 'esc_html' ) || ! function_exists( 'wp_get_environment_type' ) ) {
			return;
		}

		$wp_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
		$result     = Platform::check( PHP_VERSION, $wp_version, Platform::is_woocommerce_active() );

		if ( $result['ok'] ) {
			return;
		}

		foreach ( $result['errors'] as $message ) {
			echo '<div class="notice notice-error upsellbay-notice"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Log when settings are updated.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @param string $option    Option name.
	 */
	public function log_settings_update( $old_value, $new_value, string $option ): void {
		unset( $old_value, $option );

		if ( ! is_array( $new_value ) ) {
			return;
		}

		$this->container->get( LoggerInterface::class )->info(
			__( 'UpsellBay settings updated', 'upsellbay' ),
			array(
				'source'  => 'settings_save',
				'user_id' => get_current_user_id(),
			)
		);
	}

	/**
	 * Log when an offer is created.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id Offer ID.
	 * @param array<string, mixed> $offer    Offer payload.
	 */
	public function log_offer_created( int $offer_id, array $offer ): void {
		$this->container->get( LoggerInterface::class )->info(
			/* translators: %d: offer ID */
			sprintf( __( 'Offer #%d created', 'upsellbay' ), $offer_id ),
			array(
				'source'      => 'offer_create',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'user_id'     => get_current_user_id(),
				'metadata'    => array( 'title' => $offer['title'] ?? '' ),
			)
		);
	}

	/**
	 * Log when an offer is updated.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id Offer ID.
	 * @param array<string, mixed> $offer    Offer payload.
	 */
	public function log_offer_updated( int $offer_id, array $offer ): void {
		$this->container->get( LoggerInterface::class )->info(
			/* translators: %d: offer ID */
			sprintf( __( 'Offer #%d updated', 'upsellbay' ), $offer_id ),
			array(
				'source'      => 'offer_update',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'user_id'     => get_current_user_id(),
				'metadata'    => array( 'title' => $offer['title'] ?? '' ),
			)
		);
	}

	/**
	 * Log when an offer is deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function log_offer_deleted( int $offer_id ): void {
		$this->container->get( LoggerInterface::class )->info(
			/* translators: %d: offer ID */
			sprintf( __( 'Offer #%d deleted', 'upsellbay' ), $offer_id ),
			array(
				'source'      => 'offer_delete',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'user_id'     => get_current_user_id(),
			)
		);
	}

	/**
	 * Log when an offer is accepted.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id  Offer ID.
	 * @param string               $placement Offer placement.
	 * @param array<string, mixed> $result    Add to cart result.
	 */
	public function log_offer_accepted( int $offer_id, string $placement, array $result ): void {
		$this->container->get( LoggerInterface::class )->info(
			/* translators: 1: offer ID, 2: placement */
			sprintf( __( 'Offer #%1$d accepted at %2$s', 'upsellbay' ), $offer_id, $placement ),
			array(
				'source'      => 'offer_accept',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'metadata'    => array(
					'placement' => $placement,
					'result'    => $result,
				),
			)
		);
	}

	/**
	 * Log when an offer is dismissed.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Offer placement.
	 */
	public function log_offer_dismissed( int $offer_id, string $placement ): void {
		$this->container->get( LoggerInterface::class )->info(
			/* translators: 1: offer ID, 2: placement */
			sprintf( __( 'Offer #%1$d dismissed at %2$s', 'upsellbay' ), $offer_id, $placement ),
			array(
				'source'      => 'offer_dismiss',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'metadata'    => array(
					'placement' => $placement,
				),
			)
		);
	}

	/**
	 * Log when a public API request is blocked.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $endpoint Endpoint requested.
	 * @param string               $reason   Reason for failure.
	 * @param array<string, mixed> $params   Request parameters.
	 */
	public function log_api_request_failed( string $endpoint, string $reason, array $params ): void {
		$this->container->get( LoggerInterface::class )->warning(
			/* translators: 1: endpoint, 2: reason */
			sprintf( __( 'API request to %1$s failed (%2$s)', 'upsellbay' ), $endpoint, $reason ),
			array(
				'source'       => 'api_guard',
				'request_data' => $params,
			)
		);
	}

	/**
	 * Log successful license activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key Activated license key.
	 */
	public function log_license_activated( string $license_key ): void {
		$this->container->get( LoggerInterface::class )->info(
			/* translators: %s: license key suffix */
			sprintf( __( 'License activated successfully: %s', 'upsellbay' ), str_repeat( '*', max( 0, strlen( $license_key ) - 4 ) ) . substr( $license_key, -4 ) ),
			array(
				'source'  => 'license_activation',
				'user_id' => get_current_user_id(),
			)
		);
	}

	/**
	 * Log failed license activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key Failed license key.
	 * @param string $error_code  Error code.
	 * @param mixed  $error_obj   Optional. The WP_Error object.
	 */
	public function log_license_activation_failed( string $license_key, string $error_code, mixed $error_obj = null ): void {
		$context = array(
			'source'  => 'license_activation',
			'user_id' => get_current_user_id(),
		);

		if ( is_wp_error( $error_obj ) ) {
			$context['metadata'] = array(
				'error_messages' => $error_obj->get_error_messages(),
				'error_data'     => $error_obj->get_error_data(),
			);
		}

		$this->container->get( LoggerInterface::class )->warning(
			/* translators: %s: error code */
			sprintf( __( 'License activation failed: %s', 'upsellbay' ), $error_code ),
			$context
		);
	}

	/**
	 * Log failed background license check.
	 *
	 * @since 1.0.0
	 *
	 * @param string $error_code Error code.
	 * @param mixed  $error_obj  Optional. The WP_Error object.
	 */
	public function log_license_check_failed( string $error_code, mixed $error_obj = null ): void {
		$context = array(
			'source'  => 'license_check',
			'user_id' => 0,
		);

		if ( is_wp_error( $error_obj ) ) {
			$context['metadata'] = array(
				'error_messages' => $error_obj->get_error_messages(),
				'error_data'     => $error_obj->get_error_data(),
			);
		}

		$this->container->get( LoggerInterface::class )->warning(
			/* translators: %s: error code */
			sprintf( __( 'Background license check failed: %s', 'upsellbay' ), $error_code ),
			$context
		);
	}

	/**
	 * Log when an offer is rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id  Offer ID.
	 * @param string               $placement Offer placement.
	 * @param array<string, mixed> $offer     Offer data.
	 * @param array<string, mixed> $context   Context payload.
	 */
	public function log_offer_rendered( int $offer_id, string $placement, array $offer, array $context ): void {
		// Log as debug, since rendering happens often.
		$this->container->get( LoggerInterface::class )->debug(
			/* translators: 1: offer ID, 2: placement */
			sprintf( __( 'Offer #%1$d rendered at %2$s', 'upsellbay' ), $offer_id, $placement ),
			array(
				'source'      => 'offer_render',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'metadata'    => array(
					'placement'   => $placement,
					'offer_title' => $offer['title'] ?? '',
					'context'     => $context,
				),
			)
		);
	}

	/**
	 * Log when attribution is written to a cart item.
	 *
	 * @since 1.0.0
	 *
	 * @param object               $item             WC_Order_Item_Product or WC_Order_Item.
	 * @param array<string, mixed> $attribution_meta Attribution meta payload.
	 * @param array<string, mixed> $offer            Offer data.
	 * @param string               $placement        Offer placement.
	 */
	public function log_attribution_written( $item, array $attribution_meta, array $offer, string $placement ): void {
		$offer_id = (int) ( $offer['id'] ?? 0 );
		$this->container->get( LoggerInterface::class )->info(
			/* translators: 1: offer ID, 2: placement */
			sprintf( __( 'Attribution written for Offer #%1$d at %2$s', 'upsellbay' ), $offer_id, $placement ),
			array(
				'source'      => 'attribution',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'metadata'    => array(
					'placement' => $placement,
					'meta'      => $attribution_meta,
				),
			)
		);
	}

	/**
	 * Log when a follow-on order is created.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order           WC_Order.
	 * @param int    $source_order_id Source order ID.
	 * @param int    $source_offer_id Source offer ID.
	 */
	public function log_follow_on_order_created( $order, int $source_order_id, int $source_offer_id ): void {
		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : 0;
		$this->container->get( LoggerInterface::class )->info(
			/* translators: 1: order ID, 2: source order ID */
			sprintf( __( 'Follow-on order #%1$d created from order #%2$d', 'upsellbay' ), $order_id, $source_order_id ),
			array(
				'source'      => 'follow_on_order',
				'object_type' => 'offer',
				'object_id'   => $source_offer_id,
				'metadata'    => array(
					'follow_on_order_id' => $order_id,
					'source_order_id'    => $source_order_id,
				),
			)
		);
	}

	/**
	 * Log when daily stats are reconciled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date      Date YYYY-MM-DD.
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Offer placement.
	 */
	public function log_daily_stats_reconciled( string $date, int $offer_id, string $placement ): void {
		$this->container->get( LoggerInterface::class )->debug(
			/* translators: 1: offer ID, 2: date */
			sprintf( __( 'Stats reconciled for Offer #%1$d on %2$s', 'upsellbay' ), $offer_id, $date ),
			array(
				'source'      => 'stats_reconcile',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'metadata'    => array(
					'placement' => $placement,
					'date'      => $date,
				),
			)
		);
	}

	/**
	 * Prune old log entries.
	 *
	 * @since 1.0.0
	 */
	public function prune_logs(): void {
		$settings = $this->container->get( Settings::class )->all();
		$days     = isset( $settings['data_retention']['log_days'] ) ? (int) $settings['data_retention']['log_days'] : 30;
		$this->container->get( LogRepository::class )->cleanup_old_logs( $days );
	}
}
