<?php
/**
 * Thank-you offer renderer.
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
 * Renders thank-you follow-on checkout offers.
 *
 * @since 1.0.0
 */
final class ThankYouOfferRenderer extends AbstractOfferRenderer {
	/**
	 * Render an offer card.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer   Offer.
	 * @param array<string, mixed> $context Context.
	 */
	public function render_offer( array $offer, array $context = array() ): string {
		$meta         = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$offer_id     = (int) ( $offer['id'] ?? 0 );
		$product_id   = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$product      = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$headline     = (string) ( $meta['_ub_headline'] ?? ( $offer['title'] ?? '' ) );
		$body         = (string) ( $meta['_ub_body'] ?? '' );
		$button_text  = (string) ( $meta['_ub_button_text'] ?? '' );
		$button_text  = '' !== $button_text ? $button_text : __( 'Add to a new checkout', 'upsellbay' );
		$product_name = is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
		$show_image   = true === (bool) ( $meta['_ub_show_image'] ?? true );
		$image_html   = $show_image ? $this->product_image_html( $product, $product_name ) : '';
		$price_html   = $this->render_price_html( $product, $meta );

		$source_order_id   = (int) ( $context['source_order_id'] ?? 0 );
		$source_order_attr = $source_order_id > 0 ? ' data-upsellbay-source-order-id="' . $this->esc_attr( (string) $source_order_id ) . '"' : '';
		$token             = (string) ( $context['token'] ?? '' );
		$token_attr        = '' !== $token ? ' data-upsellbay-token="' . $this->esc_attr( $token ) . '"' : '';

		$control     = '<button type="button" class="button wp-element-button wc-block-components-button upsellbay-offer__button" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">' . $this->esc_html( $button_text ) . '</button>';
		$dismiss_btn = $this->render_dismiss_button();
		$explainer   = '<div class="upsellbay-offer__explainer">' . $this->esc_html( __( 'Your original order is complete. Adding this item starts a separate checkout.', 'upsellbay' ) ) . '</div>';

		$classes = array(
			'upsellbay-offer',
			'upsellbay-offer--thankyou_offer',
			'upsellbay-offer--thankyou-followon',
		);
		if ( '' !== $image_html ) {
			$classes[] = 'upsellbay-offer--has-image';
		}

		$html = '<div class="' . $this->esc_attr( implode( ' ', $classes ) ) . '" data-upsellbay-placement="thankyou_offer" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '"' . $source_order_attr . $token_attr . '>'
			. $image_html
			. '<div class="upsellbay-offer__content">'
			. '<div class="upsellbay-offer__text">'
			. '<strong class="upsellbay-offer__headline">' . $this->esc_html( $headline ) . '</strong>'
			. ( '' !== $product_name ? '<span class="upsellbay-offer__product-name">' . $this->esc_html( $product_name ) . '</span>' : '' )
			. ( '' !== $body ? '<div class="upsellbay-offer__body">' . $this->kses_post( $body ) . '</div>' : '' )
			. '</div>'
			. $price_html
			. $explainer
			. '</div>'
			. '<div class="upsellbay-offer__action">'
			. $control
			. $dismiss_btn
			. '</div>'
			. '</div>';

		return \WPAnchorBay\UpsellBay\Core\Hooks::filter( 'render_offer_html', $html, $offer, 'thankyou_offer', $context );
	}
}
