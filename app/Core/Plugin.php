<?php
/**
 * Plugin bootstrap coordinator.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
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
use WPAnchorBay\UpsellBayPro\Admin\PreviewLinks;
use WPAnchorBay\UpsellBayPro\Admin\Settings\LogsSectionRenderer;
use WPAnchorBay\UpsellBayPro\Admin\Settings\SettingsPage;
use WPAnchorBay\UpsellBayPro\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBayPro\Admin\Wizard\WizardController;
use WPAnchorBay\UpsellBayPro\Api\ProductsController;
use WPAnchorBay\UpsellBayPro\Api\Routes\LicenseRoute;
use WPAnchorBay\UpsellBayPro\Api\Routes\OfferPreviewRoute;
use WPAnchorBay\UpsellBayPro\Api\Routes\ProductsRoute;
use WPAnchorBay\UpsellBayPro\Api\Routes\PublicOfferRoutes;
use WPAnchorBay\UpsellBayPro\Data\CartSession;
use WPAnchorBay\UpsellBayPro\Data\OfferRepository;
use WPAnchorBay\UpsellBayPro\Data\StatsRepository;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\AnalyticsService;
use WPAnchorBay\UpsellBayPro\Domain\Analytics\StatsReconciler;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartMutator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\CartValidator;
use WPAnchorBay\UpsellBayPro\Domain\Cart\OfferLineRepricer;
use WPAnchorBay\UpsellBayPro\Domain\Compatibility\CompatibilityScanner;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountApplier;
use WPAnchorBay\UpsellBayPro\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBayPro\Domain\Attribution\AttributionReader;
use WPAnchorBay\UpsellBayPro\Domain\Attribution\AttributionWriter;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferConflictDetector;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferVisibilityInspector;
use WPAnchorBay\UpsellBayPro\Domain\Offers\ProductRecommendationAssistant;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBayPro\Domain\Rules\RuleParser;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\CartCrossSellRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ClassicCheckoutBump;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\PlacementRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ProductPageRenderer;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\StorefrontController;
use WPAnchorBay\UpsellBayPro\Domain\Storefront\ThankYouOfferRenderer;
use WPAnchorBay\UpsellBayPro\Integrations\WooCommerce\BlockCheckoutIntegration;
use WPAnchorBay\UpsellBayPro\Integrations\WooCommerce\CouponLimiter;
use WPAnchorBay\UpsellBayPro\Integrations\WooCommerce\StoreApiExtender;
use WPAnchorBay\UpsellBayPro\Integrations\Licensing\LicenseClient;
use WPAnchorBay\UpsellBayPro\Integrations\OffersListColumnsIntegration;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\OneClickCharger;
use WPAnchorBay\UpsellBayPro\Domain\OneClick\PaymentChargeAdapterRegistry;
use WPAnchorBay\UpsellBayPro\Integrations\OfferEditDiagnosticsIntegration;
use WPAnchorBay\UpsellBayPro\Integrations\OfferTypesIntegration;
use WPAnchorBay\UpsellBayPro\Integrations\HostSettingsIntegration;
use WPAnchorBay\UpsellBayPro\Integrations\OneClick\StripeChargeAdapter;
use WPAnchorBay\UpsellBayPro\Utils\ImportExporter;
use WPAnchorBay\UpsellBayPro\Utils\RateLimiter;
use WPAnchorBay\UpsellBayPro\Utils\TokenHelper;
use WPAnchorBay\UpsellBayPro\Data\LogRepository;
use WPAnchorBay\UpsellBayPro\Domain\Logging\ApiFailureLog;
use WPAnchorBay\UpsellBayPro\Domain\Logging\LoggerInterface;
use WPAnchorBay\UpsellBayPro\Domain\Logging\DatabaseLogger;

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
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
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
		$this->container->set( OfferVisibilityInspector::class, static fn ( Container $container ): OfferVisibilityInspector => new OfferVisibilityInspector( $container->get( Settings::class ), $container->get( OfferValidator::class ), $container->get( OfferPrioritizer::class ), $container->get( OfferRepository::class ), $container->get( CartSession::class ) ) );
		$this->container->set( OfferVisibilityPanel::class, static fn ( Container $container ): OfferVisibilityPanel => new OfferVisibilityPanel( $container->get( OfferVisibilityInspector::class ) ) );
		$this->container->set( OfferService::class, static fn ( Container $container ): OfferService => new OfferService( $container->get( OfferRepository::class ), $container->get( OfferValidator::class ) ) );
		$this->container->set( RuleParser::class, static fn (): RuleParser => new RuleParser() );
		$this->container->set( RuleEvaluator::class, static fn ( Container $container ): RuleEvaluator => new RuleEvaluator( $container->get( RuleParser::class ) ) );
		$this->container->set( DiscountCalculator::class, static fn (): DiscountCalculator => new DiscountCalculator() );
		$this->container->set( DiscountApplier::class, static fn (): DiscountApplier => new DiscountApplier() );
		$this->container->set( StatsRepository::class, static fn (): StatsRepository => new StatsRepository() );
		$this->container->set( OffersListColumnsIntegration::class, static fn ( Container $container ): OffersListColumnsIntegration => new OffersListColumnsIntegration( $container->get( OfferRepository::class ), $container->get( OfferConflictDetector::class ), $container->get( StatsRepository::class ) ) );
		$this->container->set( OfferEditDiagnosticsIntegration::class, static fn ( Container $container ): OfferEditDiagnosticsIntegration => new OfferEditDiagnosticsIntegration( $container->get( OfferConflictDetector::class ), $container->get( OfferVisibilityPanel::class ) ) );
		$this->container->set(
			ScheduledJobs::class,
			fn ( Container $container ): ScheduledJobs => new ScheduledJobs(
				$container->get( Settings::class ),
				$container->get( StatsRepository::class ),
				array( $this, 'check_license' ),
				array( $this, 'prune_logs' )
			)
		);
		$this->container->set( HostSettingsIntegration::class, static fn ( Container $container ): HostSettingsIntegration => new HostSettingsIntegration( $container->get( Settings::class ) ) );
		$this->container->set( OfferTypesIntegration::class, static fn ( Container $container ): OfferTypesIntegration => new OfferTypesIntegration( null, null, $container->get( PreviewLinks::class ) ) );
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
			PaymentChargeAdapterRegistry::class,
			static function (): PaymentChargeAdapterRegistry {
				$registry = new PaymentChargeAdapterRegistry();
				if ( class_exists( '\WC_Stripe_API' ) ) {
					$registry->register( new StripeChargeAdapter() );
				}
				/**
				 * Register additional one-click payment charge adapters.
				 *
				 * @since 1.0.0
				 *
				 * @param PaymentChargeAdapterRegistry $registry Adapter registry.
				 */
				Hooks::action( 'register_one_click_adapters', $registry );
				return $registry;
			}
		);
		$this->container->set(
			OneClickCharger::class,
			static fn ( Container $container ): OneClickCharger => new OneClickCharger(
				$container->get( PaymentChargeAdapterRegistry::class ),
				$container->get( DiscountCalculator::class ),
				$container->get( AttributionWriter::class )
			)
		);
		$this->container->set(
			PublicOfferRoutes::class,
			static fn ( Container $container ): PublicOfferRoutes => new PublicOfferRoutes(
				static fn ( int $offer_id ): ?array => $container->get( OfferService::class )->get( $offer_id ),
				$container->get( CartMutator::class ),
				$container->get( CartSession::class ),
				static fn ( string $endpoint, string $client_key ): bool => $container->get( RateLimiter::class )->hit( $endpoint, $client_key ),
				null,
				$container->get( AnalyticsService::class ),
				$container->get( TokenHelper::class ),
				// One-click is off in 1.0.0: without the switch no charger, adapter
				// registry or Stripe adapter is ever built.
				Constants::one_click_enabled() ? $container->get( OneClickCharger::class ) : null,
				$container->get( OfferPrioritizer::class )
			)
		);
		$this->container->set(
			OfferLineRepricer::class,
			static fn ( Container $container ): OfferLineRepricer => new OfferLineRepricer(
				static fn ( int $offer_id ): ?array => $container->get( OfferService::class )->get( $offer_id ),
				$container->get( OfferPrioritizer::class ),
				$container->get( DiscountCalculator::class ),
				$container->get( DiscountApplier::class ),
				static function ( string $placement ) use ( $container ): bool {
					$settings = $container->get( Settings::class )->all();

					return true === ( $settings['enabled'] ?? true ) && false !== ( $settings['placements'][ $placement ] ?? true );
				}
			)
		);
		$this->container->set( ProductsController::class, static fn ( Container $container ): ProductsController => new ProductsController( $container->get( ProductRecommendationAssistant::class ) ) );
		$this->container->set( ProductsRoute::class, static fn ( Container $container ): ProductsRoute => new ProductsRoute( $container->get( ProductsController::class ) ) );
		$this->container->set( OfferPreviewRoute::class, static fn ( Container $container ): OfferPreviewRoute => new OfferPreviewRoute( $container->get( OfferService::class ) ) );
		$this->container->set( StoreApiExtender::class, static fn ( Container $container ): StoreApiExtender => new StoreApiExtender( $container->get( OfferRepository::class ), $container->get( OfferPrioritizer::class ), $container->get( CartSession::class ), $container->get( AnalyticsRecorder::class ), $container->get( Settings::class ) ) );
		$this->container->set( BlockCheckoutIntegration::class, static fn ( Container $container ): BlockCheckoutIntegration => new BlockCheckoutIntegration( $container->get( Settings::class ), $container->get( CartSession::class ) ) );
		$this->container->set( StorefrontController::class, static fn ( Container $container ): StorefrontController => new StorefrontController( $container->get( OfferRepository::class ), $container->get( PlacementRenderer::class ), $container->get( CartSession::class ), $container->get( Settings::class ), $container->get( TokenHelper::class ) ) );
		$this->container->set( CouponLimiter::class, static fn (): CouponLimiter => new CouponLimiter() );
		$this->container->set( ImportExporter::class, static fn ( Container $container ): ImportExporter => new ImportExporter( $container->get( OfferValidator::class ), $container->get( OfferRepository::class ), $container->get( Settings::class ) ) );
		$this->container->set( LogRepository::class, static fn (): LogRepository => new LogRepository() );
		$this->container->set( LoggerInterface::class, static fn ( Container $container ): DatabaseLogger => new DatabaseLogger( $container->get( LogRepository::class ) ) );
		$this->container->set( ApiFailureLog::class, static fn ( Container $container ): ApiFailureLog => new ApiFailureLog( $container->get( LoggerInterface::class ) ) );
		$this->container->set( TokenHelper::class, static fn (): TokenHelper => new TokenHelper() );
		$this->container->set( RateLimiter::class, static fn (): RateLimiter => new RateLimiter() );
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
		$this->container->set( ToolsPage::class, static fn ( Container $container ): ToolsPage => new ToolsPage( $container->get( ImportExporter::class ), $container->get( Settings::class ), $container->get( CompatibilityScanner::class ) ) );
		$this->container->set( HelpPage::class, static fn (): HelpPage => new HelpPage() );
		$this->container->set( OverviewSummary::class, static fn ( Container $container ): OverviewSummary => new OverviewSummary( $container->get( OfferRepository::class ), $container->get( StatsRepository::class ), $container->get( Settings::class ) ) );
		$this->container->set( DashboardPage::class, static fn ( Container $container ): DashboardPage => new DashboardPage( $container->get( OverviewSummary::class ), $container->get( StatsRepository::class ) ) );
		$this->container->set( Coexistence::class, static fn (): Coexistence => new Coexistence() );
		$this->container->set( CompatibilityNotice::class, static fn ( Container $container ): CompatibilityNotice => new CompatibilityNotice( $container->get( Settings::class ), $container->get( CompatibilityScanner::class ) ) );
		$this->container->set( PluginActionLinks::class, static fn (): PluginActionLinks => new PluginActionLinks() );
		$this->container->set( AdminAssets::class, static fn (): AdminAssets => new AdminAssets() );
		$this->container->set( AdminBar::class, static fn ( Container $container ): AdminBar => new AdminBar( $container->get( Settings::class ) ) );
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
		// The host renamed its page-heading notice slot; the old
		// `upsellbay_admin_page_license_banner` action no longer fires.
		add_action( 'upsellbay_admin_page_heading_before', array( $this, 'render_page_license_banner' ) );
		add_action( 'admin_post_upsellbay_pro_activate_license', array( $this, 'handle_activate_license' ) );
		add_action( 'admin_post_upsellbay_pro_remove_license', array( $this, 'handle_remove_license' ) );
		add_action( 'admin_post_upsellbay_pro_check_license', array( $this, 'handle_check_license' ) );
		add_action( 'admin_post_upsellbay_pro_delete_offer', array( $this, 'handle_delete_offer' ) );
		add_action( 'admin_post_upsellbay_pro_clear_all_data', array( $this, 'handle_clear_all_data' ) );
		add_action( 'admin_post_upsellbay_pro_export_data', array( $this, 'handle_export_data' ) );
		add_action( 'admin_post_upsellbay_pro_import_data', array( $this, 'handle_import_data' ) );
		add_action( 'admin_post_upsellbay_pro_confirm_import', array( $this, 'handle_confirm_import' ) );

		$this->container->get( AdminAssets::class )->register_hooks();
		$this->container->get( AdminBar::class )->register_hooks();
		$this->container->get( CompatibilityNotice::class )->register_hooks();
		$this->container->get( SettingsPage::class )->register_hooks();
		$this->container->get( ToolsPage::class )->register_hooks();
		$this->container->get( OffersListColumnsIntegration::class )->register_hooks();
		$this->container->get( OfferEditDiagnosticsIntegration::class )->register_hooks();
		$this->container->get( OfferTypesIntegration::class )->register_hooks();
		$this->container->get( HostSettingsIntegration::class )->register_hooks();
		$this->container->get( DashboardPage::class )->register_hooks();
		$this->container->get( PluginActionLinks::class )->register_hooks();
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
		add_action( Constants::hook_name( 'offer_skipped' ), array( $this, 'log_offer_skipped' ), 10, 5 );
		add_action( Constants::hook_name( 'api_request_failed' ), array( $this, 'log_api_request_failed' ), 10, 3 );
		add_action( Constants::hook_name( 'license_activated' ), array( $this, 'log_license_activated' ) );
		add_action( Constants::hook_name( 'license_activation_failed' ), array( $this, 'log_license_activation_failed' ), 10, 3 );
		add_action( Constants::hook_name( 'license_check_failed' ), array( $this, 'log_license_check_failed' ), 10, 2 );
		add_action( Constants::hook_name( 'attribution_written' ), array( $this, 'log_attribution_written' ), 10, 4 );
		add_action( Constants::hook_name( 'follow_on_order_created' ), array( $this, 'log_follow_on_order_created' ), 10, 3 );
		add_action( Constants::hook_name( 'daily_stats_reconciled' ), array( $this, 'log_daily_stats_reconciled' ), 10, 3 );
		$this->container->get( ScheduledJobs::class )->register_hooks();

		// The free host registers only the shared checkout-bump and dismiss routes on the
		// upsellbay/v1 namespace. Pro owns its non-bump placements: it registers the
		// cart-offer-add route (and one-click-accept, only when the reserved one-click
		// switch is on; never in 1.0.0) via the host's route action, and hooks
		// the host's token seam so post-purchase thank-you actions authorize on host endpoints.
		add_action( 'upsellbay_rest_routes', array( $this->container->get( ProductsRoute::class ), 'register' ) );
		add_action( 'upsellbay_rest_routes', array( $this->container->get( PublicOfferRoutes::class ), 'register_one_click_route' ) );
		add_action( 'upsellbay_rest_routes', array( $this->container->get( PublicOfferRoutes::class ), 'register_cart_offer_add_route' ) );
		add_action( 'upsellbay_rest_routes', array( $this, 'register_license_routes' ) );
		add_filter( 'upsellbay_offer_action_token_valid', array( $this->container->get( PublicOfferRoutes::class ), 'filter_action_token_valid' ), 10, 4 );
		// Priority 20: after the free host's handler (10) on the same hook, so Pro's
		// server-side decision is the last word on the lines Pro adds.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_offer_discounts' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'write_offer_line_item_attribution' ), 10, 4 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'record_order_offer_analytics' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'record_order_offer_analytics' ) );
	}

	/**
	 * Price accepted Pro offer lines from current server state on every totals run.
	 *
	 * The offer is reloaded and re-checked and its price recomputed each time
	 * (OfferLineRepricer); the price stored at accept time is never applied.
	 * Checkout-bump lines are the free host's and are left to it.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $cart WooCommerce cart object.
	 */
	public function apply_offer_discounts( $cart ): void {
		if ( ! is_object( $cart ) ) {
			return;
		}

		$this->container->get( OfferLineRepricer::class )->reprice( $cart );
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

		// Follow-on attribution belongs to thank-you offers only; CartMutator::accept()
		// stores a source order on no other placement, and this check keeps a line
		// from an older session from linking an order it was never verified against.
		$source_order_id = 'thankyou_offer' === $placement ? (int) ( $values[ Constants::ATTRIBUTION_SOURCE_ORDER_ID ] ?? 0 ) : 0;
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
		check_admin_referer( 'upsellbay_pro_activate_license' );

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

		check_admin_referer( 'upsellbay_pro_remove_license' );

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

		check_admin_referer( 'upsellbay_pro_check_license' );

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

		check_admin_referer( 'upsellbay_pro_delete_offer' );

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
	 * Handle clearing all data from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_clear_all_data(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage data.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_pro_clear_all_data' );

		$this->container->get( Installer::class )->clear_all_data();

		$redirect_url = admin_url( 'admin.php?page=upsellbay&tab=settings&section=data' );
		$redirect_url = add_query_arg( 'wc_message', rawurlencode( __( 'UpsellBay Pro data has been permanently deleted and reset to defaults. Offers were kept.', 'upsellbay' ) ), $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle exporting all data from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_export_data(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage data.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_pro_export_data' );

		$json = $this->container->get( ImportExporter::class )->export_all();

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="upsellbay-export-' . ( function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' ) ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle importing whole data from the admin-post action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_import_data(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage data.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_pro_import_data' );

		$redirect_url = admin_url( 'admin.php?page=upsellbay&tab=tools' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read_upload() checks error, size and is_uploaded_file(); the path is never echoed.
		$file = isset( $_FILES['import_file'] ) && is_array( $_FILES['import_file'] ) ? $_FILES['import_file'] : array();
		$json = $this->container->get( ImportExporter::class )->read_upload( $file );
		if ( is_wp_error( $json ) ) {
			$redirect_url = add_query_arg( 'wc_error', rawurlencode( $json->get_error_message() ), $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Validate JSON lightly before storing it.
		$payload = json_decode( (string) $json, true );
		if ( ! is_array( $payload ) || ! isset( $payload['type'] ) || '' === $payload['type'] ) {
			$redirect_url = add_query_arg( 'wc_error', rawurlencode( __( 'Invalid or unsupported JSON file.', 'upsellbay' ) ), $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$hash = wp_generate_password( 12, false );
		set_transient( 'upsellbay_import_' . $hash, $json, HOUR_IN_SECONDS );

		$redirect_url = add_query_arg( 'preview_import', $hash, $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle confirming the import after preview.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_confirm_import(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to manage data.', 'upsellbay' ) );
		}

		check_admin_referer( 'upsellbay_pro_confirm_import' );

		$hash = isset( $_POST['import_hash'] ) ? sanitize_key( wp_unslash( $_POST['import_hash'] ) ) : '';
		$json = get_transient( 'upsellbay_import_' . $hash );

		$redirect_url = admin_url( 'admin.php?page=upsellbay&tab=tools' );

		if ( false === $json || '' === $json ) {
			$redirect_url = add_query_arg( 'wc_error', rawurlencode( __( 'Import session expired. Please upload the file again.', 'upsellbay' ) ), $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$preferences = array(
			'import_settings'    => isset( $_POST['import_settings'] ),
			'append_text'        => isset( $_POST['append_text'] ),
			'duplicate_handling' => isset( $_POST['duplicate_handling'] ) ? sanitize_text_field( wp_unslash( $_POST['duplicate_handling'] ) ) : 'duplicate',
		);

		$result = $this->container->get( ImportExporter::class )->import_all( (string) $json, $preferences );
		delete_transient( 'upsellbay_import_' . $hash );

		if ( $result->is_valid() ) {
			$redirect_url = add_query_arg( 'wc_message', rawurlencode( __( 'Data imported successfully.', 'upsellbay' ) ), $redirect_url );
		} else {
			$errors       = implode( ' ', $result->errors() );
			$redirect_url = add_query_arg( 'wc_error', rawurlencode( $errors ), $redirect_url );
		}

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
	 * Hooked into the host's upsellbay_admin_page_heading_before slot so it
	 * appears in the page notices area, above the active tab's content.
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
			echo '<div class="notice notice-error upsellbay-pro-notice"><p>' . esc_html( $message ) . '</p></div>';
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
		// Endpoint, reason and offer ID only, at most one row per kind each hour.
		$this->container->get( ApiFailureLog::class )->record( $endpoint, $reason, $params );
	}

	/**
	 * Log successful license activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key Activated license key, masked by LicenseClient.
	 */
	public function log_license_activated( string $license_key ): void {
		// Masked again unless it already is, in case another caller fires the hook raw.
		$masked_key = str_contains( $license_key, '*' ) ? $license_key : $this->container->get( LicenseClient::class )->mask_key( $license_key );

		$this->container->get( LoggerInterface::class )->info(
			/* translators: %s: masked license key */
			sprintf( __( 'License activated successfully: %s', 'upsellbay' ), $masked_key ),
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
	 * @param string $license_key Failed license key, masked by LicenseClient.
	 * @param string $error_code  Error code.
	 * @param mixed  $error_obj   Optional. The WP_Error object.
	 */
	public function log_license_activation_failed( string $license_key, string $error_code, mixed $error_obj = null ): void {
		$context = array(
			'source'   => 'license_activation',
			'user_id'  => get_current_user_id(),
			'metadata' => array(
				// Masked again unless it already is, in case another caller fires the hook raw.
				'masked_key' => str_contains( $license_key, '*' ) ? $license_key : $this->container->get( LicenseClient::class )->mask_key( $license_key ),
			),
		);

		$context = $this->license_failure_context( $context, $error_obj, $license_key );

		$this->container->get( LoggerInterface::class )->warning(
			/* translators: %s: error code */
			sprintf( __( 'License activation failed: %s', 'upsellbay' ), $error_code ),
			$context
		);
	}

	/**
	 * Add a license failure's messages and diagnostics to a log context, key masked.
	 *
	 * The key is masked at the source: LicenseClient fires its hooks with the
	 * masked key only. The key passed here is therefore already masked, so
	 * redact() is defence in depth on already-masked text, not a guard against a
	 * raw key.
	 *
	 * @param array<string, mixed> $context     Log context with a `metadata` array.
	 * @param mixed                $error_obj   WP_Error or anything else.
	 * @param string               $license_key License key to mask.
	 * @return array<string, mixed>
	 */
	private function license_failure_context( array $context, mixed $error_obj, string $license_key ): array {
		if ( ! is_wp_error( $error_obj ) ) {
			return $context;
		}

		$client = $this->container->get( LicenseClient::class );

		$context['metadata']['error_messages'] = $client->redact( $error_obj->get_error_messages(), $license_key );

		$error_data = $client->redact( $error_obj->get_error_data(), $license_key );
		if ( is_array( $error_data ) ) {
			if ( isset( $error_data['request_data'] ) && is_array( $error_data['request_data'] ) ) {
				$context['request_data'] = $error_data['request_data'];
			}

			if ( isset( $error_data['response_data'] ) && is_array( $error_data['response_data'] ) ) {
				$context['response_data'] = $error_data['response_data'];
			}

			$context['metadata']['error_data'] = $error_data;
		}

		return $context;
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
		$context = $this->license_failure_context(
			array(
				'source'   => 'license_check',
				'user_id'  => 0,
				'metadata' => array(),
			),
			$error_obj,
			(string) ( $this->container->get( LicenseClient::class )->get_status()['key'] ?? '' )
		);

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
		if ( true !== ( $this->container->get( Settings::class )->all()['debug_logging'] ?? false ) ) {
			return;
		}

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
	 * Log when an offer is skipped before render.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id  Offer ID.
	 * @param string               $placement Offer placement.
	 * @param array<string, mixed> $offer     Offer data.
	 * @param array<string, mixed> $context   Context payload.
	 * @param array<int, string>   $reasons   Skip reasons.
	 */
	public function log_offer_skipped( int $offer_id, string $placement, array $offer, array $context, array $reasons ): void {
		if ( true !== ( $this->container->get( Settings::class )->all()['debug_logging'] ?? false ) ) {
			return;
		}

		$this->container->get( LoggerInterface::class )->debug(
			/* translators: 1: offer ID, 2: placement */
			sprintf( __( 'Offer #%1$d skipped at %2$s', 'upsellbay' ), $offer_id, $placement ),
			array(
				'source'      => 'offer_skip',
				'object_type' => 'offer',
				'object_id'   => $offer_id,
				'metadata'    => array(
					'placement'   => $placement,
					'offer_title' => $offer['title'] ?? '',
					'reasons'     => $reasons,
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
