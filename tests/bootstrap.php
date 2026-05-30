<?php
/**
 * Test bootstrap for isolated Phase 1 foundation tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

foreach (
	array(
		'app/Core/Constants.php',
		'app/Core/Hooks.php',
		'app/Core/Container.php',
		'app/Core/Settings.php',
		'app/Core/Platform.php',
		'app/Core/Scheduler.php',
		'app/Core/Installer.php',
		'app/Domain/Offers/ValidationResult.php',
		'app/Domain/Offers/OfferSchema.php',
		'app/Domain/Offers/OfferValidator.php',
		'app/Data/OfferRepository.php',
		'app/Data/StatsRepository.php',
		'app/Data/CartSession.php',
		'app/Domain/Analytics/AnalyticsRecorder.php',
		'app/Domain/Analytics/AnalyticsService.php',
		'app/Domain/Analytics/StatsReconciler.php',
		'app/Domain/Rules/RuleParser.php',
		'app/Domain/Rules/RuleEvaluator.php',
		'app/Domain/Discounts/DiscountCalculator.php',
		'app/Domain/Discounts/DiscountApplier.php',
		'app/Domain/Cart/CartValidator.php',
		'app/Domain/Cart/CartMutator.php',
		'app/Domain/Attribution/AttributionWriter.php',
		'app/Domain/Attribution/AttributionReader.php',
		'app/Domain/Offers/OfferService.php',
		'app/Domain/Offers/OfferPrioritizer.php',
		'app/Domain/Storefront/OfferRendererInterface.php',
		'app/Domain/Storefront/AbstractOfferRenderer.php',
		'app/Domain/Storefront/ClassicCheckoutBump.php',
		'app/Domain/Storefront/ProductPageRenderer.php',
		'app/Domain/Storefront/CartCrossSellRenderer.php',
		'app/Domain/Storefront/ThankYouOfferRenderer.php',
		'app/Domain/Storefront/PlacementRenderer.php',
		'app/Domain/Storefront/StorefrontController.php',
		'app/Domain/Compatibility/CompatibilityScanner.php',
		'app/Api/Routes/OfferPreviewRoute.php',
		'app/Api/Routes/PublicOfferRoutes.php',
		'app/Integrations/WooCommerce/CheckoutFields.php',
		'app/Integrations/WooCommerce/BlockCheckoutIntegration.php',
		'app/Integrations/Licensing/LicenseClient.php',
		'app/Utils/ImportExporter.php',
		'app/Utils/TokenHelper.php',
		'app/Utils/RateLimiter.php',
		'app/Utils/Logger.php',
		'app/Admin/AdminPageRegistrar.php',
		'app/Admin/AdminAssets.php',
		'app/Admin/AdminBar.php',
		'app/Admin/CompatibilityNotice.php',
		'app/Admin/Coexistence.php',
		'app/Admin/OverviewSummary.php',
		'app/Admin/Offers/OfferListTable.php',
		'app/Admin/Offers/OffersPage.php',
		'app/Admin/Offers/OfferEditPage.php',
		'app/Admin/Settings/SettingsSectionInterface.php',
		'app/Admin/Settings/AbstractSettingsSection.php',
		'app/Admin/Settings/GeneralSection.php',
		'app/Admin/Settings/StyleSection.php',
		'app/Admin/Settings/DataSection.php',
		'app/Admin/Settings/SettingsPage.php',
		'app/Admin/Analytics/AnalyticsPage.php',
		'app/Admin/Tools/ToolsPage.php',
		'app/Admin/Help/HelpPage.php',
		'app/Domain/Offers/OfferDefaults.php',
		'app/Admin/Wizard/WizardController.php',
		'app/Admin/PreviewLinks.php',
		'app/Domain/Offers/ProductRecommendationAssistant.php',
	) as $file
) {
	$path = $root . '/' . $file;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
