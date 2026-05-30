<?php
/**
 * Thank-you offer renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

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
		unset( $context );
		return $this->render_card( $offer, 'thankyou_offer', false );
	}
}
