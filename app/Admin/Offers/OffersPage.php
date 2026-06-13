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
	 * Offers section navigation.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferSectionNavigation
	 */
	private OfferSectionNavigation $section_navigation;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferListTable              $table              Offer list table.
	 * @param OfferSectionNavigation|null $section_navigation Offers section navigation.
	 */
	public function __construct( OfferListTable $table, ?OfferSectionNavigation $section_navigation = null ) {
		$this->table              = $table;
		$this->section_navigation = $section_navigation ?? new OfferSectionNavigation();
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
					'url'   => 'admin.php?page=upsellbay&tab=offers&action=edit',
				),
				array(
					'label' => __( 'Open setup wizard', 'upsellbay' ),
					'url'   => 'admin.php?page=upsellbay&tab=setup',
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
		echo '<div class="wrap woocommerce upsellbay-admin">';
		$this->render_content();
		echo '</div>';
	}

	/**
	 * Render offers tab content.
	 *
	 * @since 1.0.0
	 */
	public function render_content(): void {
		$rows = $this->rows();

		/**
		 * Fires before the Offers section navigation and list-table content.
		 *
		 * @since 1.0.0
		 */
		do_action( 'upsellbay_offers_header_after' );

		$this->section_navigation->render( 'general' );

		if ( array() === $rows ) {
			$empty = $this->empty_state();
			echo '<div class="notice notice-info inline"><p><strong>' . esc_html( $empty['title'] ) . '</strong></p><p>' . esc_html( $empty['message'] ) . '</p><p>';
			foreach ( $empty['actions'] as $action ) {
				echo '<a class="button" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a> ';
			}
			echo '</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped table-view-list upsellbay-offers-table">';
		echo '<thead><tr>';
		foreach (
			array(
				__( 'Offer', 'upsellbay' ),
				__( 'Placement', 'upsellbay' ),
				__( 'Status', 'upsellbay' ),
				__( 'Health', 'upsellbay' ),
				__( 'Priority', 'upsellbay' ),
				__( 'Views', 'upsellbay' ),
				__( 'Accepts', 'upsellbay' ),
				__( 'Revenue', 'upsellbay' ),
			) as $heading
		) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) $row['title'] ) . '</strong><div class="row-actions"><span><a href="' . esc_url( 'admin.php?page=upsellbay&tab=offers&action=edit&offer_id=' . (int) $row['id'] ) . '">' . esc_html__( 'Edit', 'upsellbay' ) . '</a></span></div></td>';
			echo '<td>' . esc_html( $this->placement_label( (string) $row['placement'] ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( (string) $row['status'] ) ) . '</td>';
			$health_html = 'ok' === ( $row['health'] ?? 'ok' ) ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'No conflicts', 'upsellbay' ) . '"></span>' : '<span class="dashicons dashicons-warning" style="color: #dba617;" title="' . esc_attr__( 'Placement crowding or funnel overlap detected', 'upsellbay' ) . '"></span>';
			echo '<td>' . $health_html . '</td>';
			echo '<td>' . esc_html( (string) $row['priority'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['views'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['accepts'] ) . '</td>';
			echo '<td>' . esc_html( $this->format_currency( $row['attributed_revenue'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Return a merchant-readable placement label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $placement Placement key.
	 */
	private function placement_label( string $placement ): string {
		$labels = array(
			'checkout_bump'  => __( 'Checkout bump', 'upsellbay' ),
			'product_upsell' => __( 'Product page offer', 'upsellbay' ),
			'cart_crosssell' => __( 'Cart offer', 'upsellbay' ),
			'thankyou_offer' => __( 'Thank-you offer', 'upsellbay' ),
		);

		return $labels[ $placement ] ?? $placement;
	}

	/**
	 * Format currency value.
	 *
	 * @param mixed $value Value to format.
	 * @return string
	 */
	private function format_currency( $value ): string {
		$float_val = (float) $value;
		$symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		return $symbol . number_format_i18n( $float_val, 2 );
	}
}
