<?php
/**
 * Base storefront offer renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

/**
 * Provides escaped native offer-card markup.
 *
 * @since 1.0.0
 */
abstract class AbstractOfferRenderer implements OfferRendererInterface {
	/**
	 * Render shared offer markup.
	 *
	 * @param array<string, mixed> $offer       Offer.
	 * @param string               $placement   Placement key.
	 * @param bool                 $is_checkbox Whether primary control is a checkbox.
	 */
	protected function render_card( array $offer, string $placement, bool $is_checkbox ): string {
		$meta        = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$offer_id    = (int) ( $offer['id'] ?? 0 );
		$headline    = (string) ( $meta['_ub_headline'] ?? ( $offer['title'] ?? '' ) );
		$body        = (string) ( $meta['_ub_body'] ?? '' );
		$button_text = (string) ( $meta['_ub_button_text'] ?? '' );
		$button_text = '' !== $button_text ? $button_text : 'Add offer';

		$control = $is_checkbox
			? '<label class="upsellbay-offer__toggle"><input type="checkbox" class="upsellbay-offer__checkbox" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '"> <span>' . $this->esc_html( $button_text ) . '</span></label>'
			: '<button type="button" class="button upsellbay-offer__button" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">' . $this->esc_html( $button_text ) . '</button>';

		return '<div class="upsellbay-offer upsellbay-offer--' . $this->esc_attr( $placement ) . '" data-upsellbay-placement="' . $this->esc_attr( $placement ) . '" data-upsellbay-offer-id="' . $this->esc_attr( (string) $offer_id ) . '">'
			. '<div class="upsellbay-offer__content">'
			. '<strong class="upsellbay-offer__headline">' . $this->esc_html( $headline ) . '</strong>'
			. ( '' !== $body ? '<div class="upsellbay-offer__body">' . $this->kses_post( $body ) . '</div>' : '' )
			. '</div>'
			. '<div class="upsellbay-offer__action">' . $control . '</div>'
			. '</div>';
	}

	/**
	 * Escape text.
	 *
	 * @param string $value Value.
	 */
	private function esc_html( string $value ): string {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape an attribute.
	 *
	 * @param string $value Value.
	 */
	private function esc_attr( string $value ): string {
		return function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Allow limited post HTML.
	 *
	 * @param string $value Value.
	 */
	private function kses_post( string $value ): string {
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : strip_tags( $value, '<a><br><em><strong>' );
	}
}
