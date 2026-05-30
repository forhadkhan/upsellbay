<?php
/**
 * Plugin bootstrap coordinator.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

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
use WPAnchorBay\UpsellBay\Admin\Settings\SettingsPage;
use WPAnchorBay\UpsellBay\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBay\Admin\Wizard\WizardController;
use WPAnchorBay\UpsellBay\Api\Routes\OfferPreviewRoute;
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
use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;
use WPAnchorBay\UpsellBay\Utils\ImportExporter;
use WPAnchorBay\UpsellBay\Utils\Logger;
use WPAnchorBay\UpsellBay\Utils\RateLimiter;
use WPAnchorBay\UpsellBay\Utils\TokenHelper;

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
					'checkout_bump'  => new ClassicCheckoutBump(),
					'product_upsell' => new ProductPageRenderer(),
					'cart_crosssell' => new CartCrossSellRenderer(),
					'thankyou_offer' => new ThankYouOfferRenderer(),
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
		$this->container->set( OfferPreviewRoute::class, static fn ( Container $container ): OfferPreviewRoute => new OfferPreviewRoute( $container->get( OfferService::class ) ) );
		$this->container->set( CheckoutFields::class, static fn (): CheckoutFields => new CheckoutFields() );
		$this->container->set( BlockCheckoutIntegration::class, static fn (): BlockCheckoutIntegration => new BlockCheckoutIntegration() );
		$this->container->set( StorefrontController::class, static fn ( Container $container ): StorefrontController => new StorefrontController( $container->get( OfferRepository::class ), $container->get( PlacementRenderer::class ), $container->get( CartSession::class ) ) );
		$this->container->set( ImportExporter::class, static fn ( Container $container ): ImportExporter => new ImportExporter( $container->get( OfferValidator::class ) ) );
		$this->container->set( Logger::class, static fn ( Container $container ): Logger => new Logger( null, (bool) $container->get( Settings::class )->all()['debug_logging'] ) );
		$this->container->set( TokenHelper::class, static fn (): TokenHelper => new TokenHelper() );
		$this->container->set( RateLimiter::class, static fn (): RateLimiter => new RateLimiter() );
		$this->container->set( OfferListTable::class, static fn ( Container $container ): OfferListTable => new OfferListTable( $container->get( OfferRepository::class ), $container->get( OfferService::class ) ) );
		$this->container->set( OffersPage::class, static fn ( Container $container ): OffersPage => new OffersPage( $container->get( OfferListTable::class ) ) );
		$this->container->set( OfferEditPage::class, static fn ( Container $container ): OfferEditPage => new OfferEditPage( $container->get( OfferService::class ), $container->get( OfferValidator::class ), null, null, $container->get( OfferDefaults::class ) ) );
		$this->container->set( WizardController::class, static fn ( Container $container ): WizardController => new WizardController( $container->get( OfferService::class ), $container->get( Settings::class ), $container->get( OfferDefaults::class ) ) );
		$this->container->set( PreviewLinks::class, static fn (): PreviewLinks => new PreviewLinks() );
		$this->container->set( ProductRecommendationAssistant::class, static fn (): ProductRecommendationAssistant => new ProductRecommendationAssistant() );
		$this->container->set( SettingsPage::class, static fn ( Container $container ): SettingsPage => new SettingsPage( $container->get( Settings::class ) ) );
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
				$container->get( WizardController::class )
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
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );

		add_action( 'load-woocommerce_page_upsellbay', array( $this->container->get( HelpPage::class ), 'register' ) );

		$this->container->get( AdminAssets::class )->register_hooks();
		$this->container->get( AdminBar::class )->register_hooks();
		$this->container->get( CompatibilityNotice::class )->register_hooks();
		$this->container->get( BlockCheckoutIntegration::class )->register_hooks();
		$this->container->get( StorefrontController::class )->register_hooks();

		add_action( 'rest_api_init', array( $this->container->get( PublicOfferRoutes::class ), 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->container->get( OfferPreviewRoute::class ), 'register_routes' ) );
		add_action( 'woocommerce_init', array( $this->container->get( CheckoutFields::class ), 'register' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_offer_discounts' ) );
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
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}
}
