<?php
/**
 * Offers admin page.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

/**
 * Renders the WooCommerce-native offers management page.
 *
 * @since 1.0.0
 */
final class OffersPage {
	/**
	 * Offer list table.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferListTable
	 */
	private OfferListTable $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferListTable $table Offer list table.
	 */
	public function __construct( OfferListTable $table ) {
		$this->table = $table;
	}

	/**
	 * Return page rows.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( array $filters = array() ): array {
		return $this->table->rows( $filters );
	}

	/**
	 * Return a native admin empty state.
	 *
	 * @since 1.0.0
	 *
	 * @return array{title: string, message: string, actions: array<int, array{label: string, url: string}>}
	 */
	public function empty_state(): array {
		return array(
			'title'   => __( 'No UpsellBay offers yet', 'upsellbay' ),
			'message' => __( 'Create a checkout bump, product offer, cart offer, or thank-you follow-on offer when you are ready to test it.', 'upsellbay' ),
			'actions' => array(
				array(
					'label' => __( 'Create offer', 'upsellbay' ),
					'url'   => 'admin.php?page=upsellbay-add-offer',
				),
				array(
					'label' => __( 'Open setup wizard', 'upsellbay' ),
					'url'   => 'admin.php?page=upsellbay-wizard',
				),
			),
		);
	}

	/**
	 * Render page shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		echo '<div class="wrap woocommerce"><h1 class="wp-heading-inline">' . esc_html__( 'UpsellBay Offers', 'upsellbay' ) . '</h1></div>';
	}
}
