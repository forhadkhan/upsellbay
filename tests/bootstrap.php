<?php
/**
 * Test bootstrap for isolated Phase 1 foundation tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


$root = dirname( __DIR__ );

defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );

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

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( string $text ): string {
		return addslashes( $text );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $text ): string {
		return htmlspecialchars( $text, ENT_NOQUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, bool $display = true ): string {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $result;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $display = true ): string {
		$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $result;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) ?? '' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://store.test' . $path;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, int $timestamp ): string {
		return date( $format, $timestamp );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $value ): string {
		return strip_tags( $value, '<a><br><em><strong><del><ins><span><div>' );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, int $decimals = 0 ): string {
		return number_format( (float) $number, $decimals, '.', ',' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( array $response ): string {
		return (string) ( $response['body'] ?? '' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action ): string {
		return 'nonce-' . $action;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( string $actionurl, $action = -1, string $name = '_wpnonce' ): string {
		return $actionurl . ( str_contains( $actionurl, '?' ) ? '&' : '?' ) . rawurlencode( $name ) . '=' . rawurlencode( wp_create_nonce( $action ) );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://store.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( int $attachment_id, string $size = 'thumbnail' ): string {
		unset( $size );
		return $attachment_id > 0 ? 'https://store.test/uploads/' . $attachment_id . '.jpg' : '';
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( $number, int $dp = 6 ): string {
		return number_format( (float) $number, $dp, '.', '' );
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( $price ): string {
		return '$' . number_format( (float) $price, 2, '.', '' );
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( int $product_id ) {
		return $GLOBALS['upsellbay_test_products'][ $product_id ] ?? false;
	}
}

if ( ! function_exists( 'wc_get_customer_order_count' ) ) {
	function wc_get_customer_order_count( int $customer_id ): int {
		return (int) ( $GLOBALS['upsellbay_test_customer_order_counts'][ $customer_id ] ?? 0 );
	}
}

if ( ! function_exists( 'wc_get_customer_total_spent' ) ) {
	function wc_get_customer_total_spent( int $customer_id ): string {
		return (string) ( $GLOBALS['upsellbay_test_customer_total_spent'][ $customer_id ] ?? '0' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return (bool) ( $GLOBALS['upsellbay_test_current_user_can'][ $capability ] ?? false );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return (int) ( $GLOBALS['upsellbay_test_current_user_id'] ?? 0 );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $GLOBALS['upsellbay_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value, bool $autoload = true ): bool {
		unset( $autoload );
		$GLOBALS['upsellbay_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( $GLOBALS['upsellbay_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ) {
		return $GLOBALS['upsellbay_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		unset( $expiration );
		$GLOBALS['upsellbay_test_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['upsellbay_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		unset( $priority );
		$GLOBALS['upsellbay_test_hooks'][ $hook ][] = array( $callback, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		foreach ( $GLOBALS['upsellbay_test_hooks'][ $hook ] ?? array() as $entry ) {
			$accepted = (int) $entry[1];
			$value    = $entry[0]( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, ...$args ): void {
		foreach ( $GLOBALS['upsellbay_test_hooks'][ $hook ] ?? array() as $entry ) {
			$entry[0]( ...array_slice( $args, 0, (int) $entry[1] ) );
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		private $data;

		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_messages(): array {
			return array( $this->message );
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
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
		'app/Domain/Offers/OfferConflictDetector.php',
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
		'app/Integrations/WooCommerce/StoreApiExtender.php',
		'app/Integrations/Licensing/LicenseClient.php',
		'app/Utils/ImportExporter.php',
		'app/Utils/TokenHelper.php',
		'app/Utils/RateLimiter.php',
		'app/Utils/Logger.php',
		'app/Admin/Navigation/AdminTab.php',
		'app/Admin/Navigation/TabRegistry.php',
		'app/Admin/Navigation/TabRouter.php',
		'app/Admin/Navigation/TabNavigation.php',
		'app/Admin/Navigation/TabFactory.php',
		'app/Admin/AdminPage.php',
		'app/Admin/AdminPageRegistrar.php',
		'app/Admin/AdminAssets.php',
		'app/Admin/AdminBar.php',
		'app/Admin/CompatibilityNotice.php',
		'app/Admin/Coexistence.php',
		'app/Admin/PluginActionLinks.php',
		'app/Admin/OverviewSummary.php',
		'app/Admin/Dashboard/DashboardPage.php',
		'app/Admin/Offers/OfferListTable.php',
		'app/Admin/Offers/OfferSectionNavigation.php',
		'app/Admin/Offers/OffersPage.php',
		'app/Admin/Offers/OfferEditPage.php',
		'app/Admin/Settings/SettingsSectionInterface.php',
		'app/Admin/Settings/AbstractSettingsSection.php',
		'app/Admin/Settings/BasicSection.php',
		'app/Admin/Settings/StyleSection.php',
		'app/Admin/Settings/DataSection.php',
		'app/Admin/Settings/SettingsSectionNavigation.php',
		'app/Admin/Settings/SettingsPage.php',
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
