<?php
/**
 * Base storefront offer renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

use WPAnchorBay\UpsellBay\Core\Hooks;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;

/**
 * Provides escaped native offer-card markup.
 *
 * @since 1.0.0
 */
abstract class AbstractOfferRenderer implements OfferRendererInterface {
	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator
	 */
	protected DiscountCalculator $discount_calculator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param DiscountCalculator $discount_calculator Discount calculator.
	 */
	public function __construct( DiscountCalculator $discount_calculator ) {
		$this->discount_calculator = $discount_calculator;
	}

	/**
	 * Render shared offer markup.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer             Offer.
	 * @param string               $placement         Placement key.
	 * @param bool                 $is_checkbox       Whether primary control is a checkbox.
	 * @param array<string, mixed> $context           Render context.
	 * @param string               $placement_classes Additional CSS classes.
	 * @return string
	 */
	protected function render_card( array $offer, string $placement, bool $is_checkbox, array $context = array(), string $placement_classes = '' ): string {
		$meta         = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$offer_id     = (int) ( $offer['id'] ?? 0 );
		$product_id   = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$product      = $this->load_product( $product_id );
		$headline     = (string) ( $meta['_ub_headline'] ?? ( $offer['title'] ?? '' ) );
		$body         = (string) ( $meta['_ub_body'] ?? '' );
		$button_text  = (string) ( $meta['_ub_button_text'] ?? '' );
		$button_text  = '' !== $button_text ? $button_text : 'Add offer';
		$config       = is_array( $meta['_ub_placement_config'] ?? null ) ? $meta['_ub_placement_config'] : array();
		$position     = (string) ( $config['position'] ?? 'default' );
		$product_name = $this->product_name( $product );
		$show_image   = true === (bool) ( $meta['_ub_show_image'] ?? true );
		$image_html   = $show_image ? $this->product_image_html( $product, $product_name ) : '';
		$price_html   = $this->render_price_html( $product, $meta );

		$control = $is_checkbox
			? '<label class="upsellbay-offer__toggle"><input type="checkbox" class="upsellbay-offer__checkbox" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '"> <span class="upsellbay-offer__button-text">' . $this->esc_html( $button_text ) . '</span></label>'
			: '<button type="button" class="button upsellbay-offer__button" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">' . $this->esc_html( $button_text ) . '</button>';

		$source_order_attr = (int) ( $context['source_order_id'] ?? 0 ) > 0 ? ' data-upsellbay-source-order-id="' . $this->esc_attr( (string) (int) $context['source_order_id'] ) . '"' : '';

		$classes = array(
			'upsellbay-offer',
			'upsellbay-offer--' . $placement,
			'upsellbay-offer--position-' . $position,
		);
		if ( '' !== $image_html ) {
			$classes[] = 'upsellbay-offer--has-image';
		}
		if ( '' !== $placement_classes ) {
			$classes[] = $placement_classes;
		}

		$html = '<div class="' . $this->esc_attr( implode( ' ', $classes ) ) . '" data-upsellbay-placement="' . $this->esc_attr( $placement ) . '" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '"' . $source_order_attr . '>'
			. $image_html
			. '<div class="upsellbay-offer__content">'
			. '<div class="upsellbay-offer__text">'
			. '<strong class="upsellbay-offer__headline">' . $this->esc_html( $headline ) . '</strong>'
			. ( '' !== $product_name ? '<span class="upsellbay-offer__product-name">' . $this->esc_html( $product_name ) . '</span>' : '' )
			. ( '' !== $body ? '<div class="upsellbay-offer__body">' . $this->kses_post( $body ) . '</div>' : '' )
			. '</div>'
			. $price_html
			. '</div>'
			. '<div class="upsellbay-offer__action">' . $control . '</div>'
			. '</div>';

		return Hooks::filter( 'render_offer_html', $html, $offer, $placement, $context );
	}

	/**
	 * Load the WooCommerce product for an offer.
	 *
	 * @param int $product_id Product ID.
	 * @return object|null
	 */
	private function load_product( int $product_id ): ?object {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		return is_object( $product ) ? $product : null;
	}

	/**
	 * Return a product display name.
	 *
	 * @param object|null $product Product object.
	 */
	private function product_name( ?object $product ): string {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_name' ) ) {
			return '';
		}

		return (string) $product->get_name();
	}

	/**
	 * Render the product image when available.
	 *
	 * @param object|null $product      Product object.
	 * @param string      $product_name Product name for alt text.
	 */
	protected function product_image_html( ?object $product, string $product_name = '' ): string {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_image_id' ) || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		$image_id = (int) $product->get_image_id();
		if ( $image_id <= 0 ) {
			return '';
		}

		$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
		if ( ! is_string( $image_url ) || '' === $image_url ) {
			return '';
		}

		return '<div class="upsellbay-offer__image"><img src="' . $this->esc_attr( $image_url ) . '" alt="' . $this->esc_attr( $product_name ) . '"></div>';
	}

	/**
	 * Render original/offer price details.
	 *
	 * @since 1.0.0
	 *
	 * @param object|null          $product Product object.
	 * @param array<string, mixed> $meta    Offer meta.
	 * @return string
	 */
	protected function render_price_html( ?object $product, array $meta ): string {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return '';
		}

		$original = (string) $product->get_price();
		$discount = $this->discount_calculator->calculate( $original, $meta );
		if ( null === $discount ) {
			return '';
		}

		$original_price = $this->format_price( $discount['original_price'] );
		$offer_price    = $this->format_price( $discount['offer_price'] );

		if ( $discount['offer_price'] === $discount['original_price'] ) {
			return '<div class="upsellbay-offer__price"><span class="upsellbay-offer__price-current">' . $this->kses_post( $offer_price ) . '</span></div>';
		}

		return '<div class="upsellbay-offer__price"><del class="upsellbay-offer__price-original">' . $this->kses_post( $original_price ) . '</del> <ins class="upsellbay-offer__price-current">' . $this->kses_post( $offer_price ) . '</ins></div>';
	}

	/**
	 * Format a price through WooCommerce helpers when available.
	 *
	 * @param string $price Price.
	 */
	protected function format_price( string $price ): string {
		return function_exists( 'wc_price' ) ? wc_price( $price ) : '$' . number_format( (float) $price, 2, '.', '' );
	}

	/**
	 * Escape text.
	 *
	 * @param string $value Value.
	 */
	protected function esc_html( string $value ): string {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape an attribute.
	 *
	 * @param string $value Value.
	 */
	protected function esc_attr( string $value ): string {
		return function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Allow limited post HTML.
	 *
	 * @param string $value Value.
	 */
	protected function kses_post( string $value ): string {
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Render the dismiss button.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label Optional button label.
	 * @return string
	 */
	protected function render_dismiss_button( string $label = '' ): string {
		$label = '' === $label ? __( 'No thanks', 'upsellbay' ) : $label;
		return '<button type="button" class="upsellbay-offer__dismiss" data-upsellbay-dismiss>' . $this->esc_html( $label ) . '</button>';
	}

	/**
	 * Render the reason label.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta Offer meta.
	 * @return string
	 */
	protected function render_reason_label( array $meta ): string {
		$reason = (string) ( $meta['_ub_reason_label'] ?? '' );
		if ( '' === $reason ) {
			return '';
		}
		return '<span class="upsellbay-offer__reason">' . $this->esc_html( $reason ) . '</span>';
	}

	/**
	 * Render the already in cart notice.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function render_already_in_cart_notice(): string {
		return '<button type="button" class="button upsellbay-offer__button" disabled>' . $this->esc_html( __( 'Already in cart', 'upsellbay' ) ) . '</button>';
	}
}
