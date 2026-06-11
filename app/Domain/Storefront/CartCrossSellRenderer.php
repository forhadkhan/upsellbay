<?php
/**
 * Cart cross-sell renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

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
		return $this->render_card( $offer, 'cart_crosssell', false, $context );
	}
}
