<?php
/**
 * Classic checkout bump renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Renders the classic checkout checkbox/toggle offer.
 *
 * @since 1.0.0
 */
final class ClassicCheckoutBump extends AbstractOfferRenderer {
	/**
	 * Register classic checkout hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $callback Render callback.
	 */
	public function register_hooks( callable $callback ): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'woocommerce_review_order_before_submit', $callback );
		}
	}

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
		$product_name = is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';
		$show_image   = true === (bool) ( $meta['_ub_show_image'] ?? true );
		$image_html   = $show_image ? $this->product_image_html( $product, $product_name ) : '';
		$price_html   = $this->render_price_html( $product, $meta );

		$desc_id = 'upsellbay-bump-desc-' . $offer_id;

		$control = '<label class="upsellbay-offer__toggle">'
			. '<input type="checkbox" class="upsellbay-offer__checkbox" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '" aria-describedby="' . $this->esc_attr( $desc_id ) . '">'
			. ' <strong class="upsellbay-offer__headline">' . $this->esc_html( $headline ) . '<span class="upsellbay-offer__badge" style="margin-left:8px;background-color:#e2401c;color:#fff;font-size:11px;padding:2px 6px;border-radius:3px;text-transform:uppercase;font-weight:bold;">' . esc_html__( 'One-Time Offer', 'upsellbay' ) . '</span></strong>'
			. '</label>';

		$classes = array(
			'upsellbay-offer',
			'upsellbay-offer--checkout_bump',
			'upsellbay-offer--checkout-compact',
		);
		if ( '' !== $image_html ) {
			$classes[] = 'upsellbay-offer--has-image';
		}

		$html = '<div class="' . $this->esc_attr( implode( ' ', $classes ) ) . '" data-upsellbay-placement="checkout_bump" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">'
			. $image_html
			. '<div class="upsellbay-offer__content">'
			. '<div class="upsellbay-offer__header">'
			. $control
			. $price_html
			. '</div>'
			. ( '' !== $body ? '<div class="upsellbay-offer__body" id="' . $this->esc_attr( $desc_id ) . '">' . $this->kses_post( $body ) . '</div>' : '' )
			. '</div>'
			. '</div>';

		return \WPAnchorBay\UpsellBay\Core\Hooks::filter( 'render_offer_html', $html, $offer, 'checkout_bump', $context );
	}
}
