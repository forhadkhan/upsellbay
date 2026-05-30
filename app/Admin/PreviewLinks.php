<?php
/**
 * Admin preview link builder.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

/**
 * Builds admin-only preview links for supported offer placements.
 *
 * @since 1.0.0
 */
final class PreviewLinks {
	/**
	 * Site URL.
	 *
	 * @var string
	 */
	private string $site_url;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $site_url Site URL.
	 */
	public function __construct( ?string $site_url = null ) {
		$this->site_url = rtrim( $site_url ?? ( function_exists( 'home_url' ) ? home_url() : '' ), '/' );
	}

	/**
	 * Build a preview link from an offer and available context URLs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer   Offer.
	 * @param array<string, mixed> $context Context URLs.
	 * @return array{available: bool, url: string, message: string}
	 */
	public function for_offer( array $offer, array $context = array() ): array {
		$meta       = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
		$placement  = (string) ( $meta['_ub_offer_type'] ?? '' );
		$product_id = (int) ( $meta['_ub_offer_product_id'] ?? 0 );
		$offer_id   = (int) ( $offer['id'] ?? 0 );

		if ( 'checkout_bump' === $placement && isset( $context['checkout_url'] ) && '' !== (string) $context['checkout_url'] ) {
			return $this->available( (string) $context['checkout_url'], $offer_id, $placement );
		}

		if ( 'cart_crosssell' === $placement ) {
			return $this->available( (string) ( $context['cart_url'] ?? '/cart/' ), $offer_id, $placement );
		}

		if ( 'product_upsell' === $placement && $product_id > 0 ) {
			$template = (string) ( $context['product_url'] ?? '/?p=%d' );
			return $this->available( sprintf( $template, $product_id ), $offer_id, $placement );
		}

		if ( 'thankyou_offer' === $placement && isset( $context['order_received_url'] ) && '' !== (string) $context['order_received_url'] ) {
			return $this->available( (string) $context['order_received_url'], $offer_id, $placement );
		}

		return array(
			'available' => false,
			'url'       => '',
			'message'   => __( 'Preview is unavailable until the placement has enough context, such as a product URL or saved test order.', 'upsellbay' ),
		);
	}

	/**
	 * Build an available response.
	 *
	 * @param string $path      URL or path.
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement.
	 * @return array{available: bool, url: string, message: string}
	 */
	private function available( string $path, int $offer_id, string $placement ): array {
		$url       = str_starts_with( $path, 'http' ) ? $path : $this->site_url . '/' . ltrim( $path, '/' );
		$separator = str_contains( $url, '?' ) ? '&' : '?';

		return array(
			'available' => true,
			'url'       => $url . $separator . 'upsellbay_preview=1&offer_id=' . $offer_id . '&placement=' . rawurlencode( $placement ),
			'message'   => __( 'Open this as an admin while test mode is enabled.', 'upsellbay' ),
		);
	}
}
