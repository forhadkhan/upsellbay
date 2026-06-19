<?php
/**
 * Cart cross-sell renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Renders cart add-on offers.
 *
 * @since 1.0.0
 */
final class CartCrossSellRenderer extends AbstractOfferRenderer {
	/**
	 * Render an offer card.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer   Offer.
	 * @param array<string, mixed> $context Context.
	 */
	public function render_offer( array $offer, array $context = array() ): string {
		static $heading_rendered = false;

		$meta         = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$offer_id     = (int) ( $offer['id'] ?? 0 );
		$product_id   = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$product      = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$headline     = (string) ( $meta['_ub_headline'] ?? ( $offer['title'] ?? '' ) );
		$body         = (string) ( $meta['_ub_body'] ?? '' );
		$button_text  = (string) ( $meta['_ub_button_text'] ?? '' );
		$button_text  = '' !== $button_text ? $button_text : __( 'Add', 'upsellbay' );
		$product_name = is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
		$show_image   = true === (bool) ( $meta['_ub_show_image'] ?? true );
		$image_html   = $show_image ? $this->product_image_html( $product, $product_name ) : '';
		$price_html   = $this->render_price_html( $product, $meta );
		$reason_lbl   = $this->render_reason_label( $meta );

		$cart_product_ids = $context['cart_product_ids'] ?? array();
		$in_cart          = in_array( $product_id, $cart_product_ids, true );

		$control = $in_cart
			? $this->render_already_in_cart_notice()
			: '<button type="button" class="button upsellbay-offer__button" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">' . $this->esc_html( $button_text ) . '</button>';

		$dismiss_btn = $this->render_dismiss_button();

		$classes = array(
			'upsellbay-offer',
			'upsellbay-offer--cart_crosssell',
			'upsellbay-offer--cart-crosssell',
		);
		if ( '' !== $image_html ) {
			$classes[] = 'upsellbay-offer--has-image';
		}
		if ( $in_cart ) {
			$classes[] = 'is-disabled';
		}

		$html = '';
		if ( ! $heading_rendered ) {
			$heading_text = \WPAnchorBay\UpsellBay\Core\Hooks::filter( 'upsellbay_cart_crosssell_heading', __( 'Still missing?', 'upsellbay' ) );
			if ( '' !== $heading_text ) {
				$html .= '<h3 class="upsellbay-offer-section__heading">' . $this->esc_html( $heading_text ) . '</h3>';
			}
			$heading_rendered = true;
		}

		$html .= '<div class="' . $this->esc_attr( implode( ' ', $classes ) ) . '" data-upsellbay-placement="cart_crosssell" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">'
			. $image_html
			. '<div class="upsellbay-offer__content">'
			. '<div class="upsellbay-offer__text">'
			. $reason_lbl
			. '<strong class="upsellbay-offer__headline">' . $this->esc_html( $headline ) . '</strong>'
			. ( '' !== $product_name ? '<span class="upsellbay-offer__product-name">' . $this->esc_html( $product_name ) . '</span>' : '' )
			. ( '' !== $body ? '<div class="upsellbay-offer__body">' . $this->kses_post( $body ) . '</div>' : '' )
			. '</div>'
			. $price_html
			. '</div>'
			. '<div class="upsellbay-offer__action">'
			. $control
			. $dismiss_btn
			. '</div>'
			. '</div>';

		return \WPAnchorBay\UpsellBay\Core\Hooks::filter( 'render_offer_html', $html, $offer, 'cart_crosssell', $context );
	}
}
