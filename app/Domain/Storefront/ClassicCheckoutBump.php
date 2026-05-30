<?php
/**
 * Classic checkout bump renderer.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

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
		unset( $context );
		return $this->render_card( $offer, 'checkout_bump', true );
	}
}
