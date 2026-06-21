<?php
/**
 * Store API Extender for WooCommerce Blocks integration.
 *
 * @package UpsellBay\Integrations\WooCommerce
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Integrations\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\CartSession;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferPrioritizer;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

/**
 * Exposes eligible offers to the Cart and Checkout blocks via Store API.
 *
 * @since 1.0.0
 */
final class StoreApiExtender {
	/**
	 * Offer repository.
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $offers;

	/**
	 * Offer prioritizer.
	 *
	 * @var OfferPrioritizer
	 */
	private OfferPrioritizer $prioritizer;

	/**
	 * Cart session.
	 *
	 * @var CartSession
	 */
	private CartSession $session;

	/**
	 * Analytics recorder.
	 *
	 * @var AnalyticsRecorder
	 */
	private AnalyticsRecorder $analytics;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discounts;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository         $offers      Offer repository.
	 * @param OfferPrioritizer        $prioritizer Offer prioritizer.
	 * @param CartSession             $session     Cart session.
	 * @param AnalyticsRecorder       $analytics   Analytics recorder.
	 * @param Settings|null           $settings    Plugin settings.
	 * @param DiscountCalculator|null $discounts Discount calculator.
	 */
	public function __construct( OfferRepository $offers, OfferPrioritizer $prioritizer, CartSession $session, AnalyticsRecorder $analytics, ?Settings $settings = null, ?DiscountCalculator $discounts = null ) {
		$this->offers      = $offers;
		$this->prioritizer = $prioritizer;
		$this->session     = $session;
		$this->analytics   = $analytics;
		$this->settings    = $settings ?? new Settings();
		$this->discounts   = $discounts ?? new DiscountCalculator();
	}

	/**
	 * Register hooks for extending Store API schemas.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );
	}

	/**
	 * Register endpoint data on cart and checkout endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_endpoint_data(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		// Cart Endpoint Extender.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => 'upsellbay',
				'data_callback'   => array( $this, 'get_cart_offers_data' ),
				'schema_callback' => array( $this, 'get_offers_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		// Checkout Endpoint Extender.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => 'upsellbay',
				'data_callback'   => array( $this, 'get_checkout_offers_data' ),
				'schema_callback' => array( $this, 'get_offers_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Data callback for Cart schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_cart_offers_data(): array {
		return array(
			'cart_crosssell' => $this->get_offers_for_placement( 'cart_crosssell' ),
		);
	}

	/**
	 * Data callback for Checkout schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_checkout_offers_data(): array {
		return array(
			'checkout_bump' => $this->get_offers_for_placement( 'checkout_bump' ),
		);
	}

	/**
	 * Retrieve schema for offers endpoint data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_offers_schema(): array {
		$offer_schema = array(
			'type'       => 'object',
			'context'    => array( 'view' ),
			'properties' => array(
				'id'           => array(
					'type'    => 'integer',
					'context' => array( 'view' ),
				),
				'title'        => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'headline'     => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'body'         => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'button_text'  => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'product_id'   => array(
					'type'    => 'integer',
					'context' => array( 'view' ),
				),
				'product_name' => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'image_url'    => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'price_html'   => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'reason_label' => array(
					'type'    => 'string',
					'context' => array( 'view' ),
				),
				'in_cart'      => array(
					'type'    => 'boolean',
					'context' => array( 'view' ),
				),
			),
		);

		return array(
			'cart_crosssell' => array(
				'description' => __( 'Eligible Cart cross-sells for UpsellBay', 'upsellbay' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'items'       => $offer_schema,
			),
			'checkout_bump'  => array(
				'description' => __( 'Eligible Checkout bumps for UpsellBay', 'upsellbay' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'items'       => $offer_schema,
			),
		);
	}

	/**
	 * Get formatted offers for a specific placement.
	 *
	 * @param string $placement Placement identifier.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_offers_for_placement( string $placement ): array {
		$context = $this->build_context();

		if ( ! $this->should_render_placement( $placement, $context ) ) {
			return array();
		}

		$limit    = $this->settings->placement_max_display( $placement );
		$all      = $this->offers->query( array( 'limit' => 50 ) );
		$selected = $this->prioritizer->select( $all, $placement, $context, $limit );

		$formatted_offers = array();
		$date             = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		foreach ( $selected as $offer ) {
			$offer_id = (int) ( $offer['id'] ?? 0 );

			// Record view analytics when exposed to the frontend via Store API.
			$this->analytics->record_view( $offer_id, $placement, $date );

			$formatted_offers[] = $this->format_offer_for_api( $offer, $context );
		}

		return $formatted_offers;
	}

	/**
	 * Format offer data for frontend consumption.
	 *
	 * @param array<string, mixed> $offer   Raw offer array.
	 * @param array<string, mixed> $context Render context.
	 * @return array<string, mixed>
	 */
	private function format_offer_for_api( array $offer, array $context ): array {
		$meta         = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$product_id   = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$product      = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$product_name = is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';

		// Determine image.
		$show_image = true === (bool) ( $meta['_ub_show_image'] ?? true );
		$image_url  = '';
		if ( $show_image && is_object( $product ) ) {
			$image_id = method_exists( $product, 'get_image_id' ) ? $product->get_image_id() : 0;
			if ( $image_id ) {
				$image_src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
				$image_url = is_array( $image_src ) ? $image_src[0] : '';
			} elseif ( function_exists( 'wc_placeholder_img_src' ) ) {
				$image_url = wc_placeholder_img_src();
			}
		}

		// Render reason label, for example "Special Offer".
		$reason_lbl_text = (string) ( $meta['_ub_reason_label'] ?? '' );

		// Check if already in cart.
		$cart_product_ids = $context['cart_product_ids'] ?? array();
		$in_cart          = in_array( $product_id, $cart_product_ids, true );

		return array(
			'id'           => (int) ( $offer['id'] ?? 0 ),
			'title'        => (string) ( $offer['title'] ?? '' ),
			'headline'     => (string) ( $meta['_ub_headline'] ?? ( $offer['title'] ?? '' ) ),
			'body'         => function_exists( 'wp_kses_post' ) ? wp_kses_post( (string) ( $meta['_ub_body'] ?? '' ) ) : strip_tags( (string) ( $meta['_ub_body'] ?? '' ), '<a><br><em><strong>' ),
			'button_text'  => (string) ( $meta['_ub_button_text'] ?? __( 'Add', 'upsellbay' ) ),
			'product_id'   => $product_id,
			'product_name' => $product_name,
			'image_url'    => $image_url,
			'price_html'   => $this->get_offer_price_html( $product, $meta ),
			'reason_label' => $reason_lbl_text,
			'in_cart'      => $in_cart,
		);
	}

	/**
	 * Compute the price HTML for the offer.
	 *
	 * @param object|null          $product Product object.
	 * @param array<string, mixed> $meta    Offer meta.
	 * @return string
	 */
	private function get_offer_price_html( ?object $product, array $meta ): string {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return '';
		}

		$original_price = (string) $product->get_price();
		$original_html  = method_exists( $product, 'get_price_html' ) ? (string) $product->get_price_html() : $this->format_price( $original_price );
		$discount       = $this->discounts->calculate( $original_price, $meta );

		if ( null === $discount || $discount['offer_price'] === $discount['original_price'] ) {
			return $original_html;
		}

		return '<del aria-hidden="true">' . $this->format_price( $discount['original_price'] ) . '</del> <ins>' . $this->format_price( $discount['offer_price'] ) . '</ins>';
	}

	/**
	 * Format a price for Store API offer payloads.
	 *
	 * @param string $price Price.
	 */
	private function format_price( string $price ): string {
		return function_exists( 'wc_price' ) ? wc_price( $price ) : '$' . number_format( (float) $price, 2, '.', '' );
	}

	/**
	 * Build context from current WooCommerce request.
	 *
	 * @return array<string, mixed>
	 */
	private function build_context(): array {
		$context = array(
			'cart_product_ids'    => array(),
			'cart_category_ids'   => array(),
			'cart_tag_ids'        => array(),
			'viewed_category_ids' => array(),
			'viewed_tag_ids'      => array(),
			'dismissed_offer_ids' => array_keys( $this->session->state()['dismissed'] ?? array() ),
			'cart_subtotal'       => '0',
			'source_context'      => '',
			'is_preview'          => isset( $_GET['upsellbay_preview'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if ( function_exists( 'get_the_ID' ) && get_the_ID() ) {
			$viewed_product_id              = get_the_ID();
			$context['viewed_product_id']   = $viewed_product_id;
			$context['viewed_category_ids'] = $this->product_term_ids( $viewed_product_id, 'product_cat' );
			$context['viewed_tag_ids']      = $this->product_term_ids( $viewed_product_id, 'product_tag' );
		}

		if ( function_exists( 'WC' ) && isset( WC()->cart ) ) {
			$context['cart_subtotal'] = (string) WC()->cart->get_subtotal();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = (int) ( $cart_item['product_id'] ?? 0 );
				if ( $product_id > 0 ) {
					$context['cart_product_ids'][] = $product_id;
					$context['cart_category_ids']  = array_merge( $context['cart_category_ids'], $this->product_term_ids( $product_id, 'product_cat' ) );
					$context['cart_tag_ids']       = array_merge( $context['cart_tag_ids'], $this->product_term_ids( $product_id, 'product_tag' ) );
				}
			}
		}

		if ( function_exists( 'wp_get_current_user' ) ) {
			$user                  = wp_get_current_user();
			$context['user_roles'] = $user->roles;
		}

		if ( function_exists( 'get_current_user_id' ) ) {
			$customer_id = get_current_user_id();
			if ( $customer_id > 0 ) {
				$context['customer_order_count']    = function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $customer_id ) : 0;
				$context['customer_lifetime_spend'] = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $customer_id ) : '0';
			}
		}

		// Merchant previews should remain inspectable even after a local dismiss action.
		if ( true === ( $context['is_preview'] ?? false ) || ( function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ) ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			$context['dismissed_offer_ids'] = array();
		}

		return $context;
	}

	/**
	 * Determine whether a storefront placement can render for this request.
	 *
	 * @param string               $placement Placement key.
	 * @param array<string, mixed> $context   Render context.
	 */
	private function should_render_placement( string $placement, array $context ): bool {
		$settings = $this->settings->all();

		if ( true !== ( $settings['enabled'] ?? true ) ) {
			return false;
		}

		if ( false === ( $settings['placements'][ $placement ] ?? true ) ) {
			return false;
		}

		if ( true === ( $settings['test_mode'] ?? false ) && ! $this->can_view_test_mode( $context ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Return whether the current viewer can see test-mode offers.
	 *
	 * @param array<string, mixed> $context Render context.
	 */
	private function can_view_test_mode( array $context ): bool {
		if ( true === ( $context['is_preview'] ?? false ) ) {
			return true;
		}

		return function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}

	/**
	 * Return product term IDs when WordPress taxonomy helpers are available.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy   Taxonomy.
	 * @return array<int, int>
	 */
	private function product_term_ids( int $product_id, string $taxonomy ): array {
		if ( $product_id <= 0 || ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'intval', $terms ) ) );
	}
}
