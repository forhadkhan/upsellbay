<?php
/**
 * Offers admin page.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


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
	 * @param  array<string, mixed> $filters Filters.
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
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function render_content( array $request = array() ): void {
		$filters  = $this->filters_from_request( $request );
		$rows     = $this->rows( $filters );
		$overview = $this->table->overview_data();

		/**
		 * Fires before the Offers section navigation and list-table content.
		 *
		 * @since 1.0.0
		 */
		do_action( 'upsellbay_offers_header_after' );

		$this->section_navigation->render( 'general' );
		$this->render_overview( $overview );
		$this->render_table_controls( $filters );

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
			'title'              => __( 'Offer', 'upsellbay' ),
			'placement'          => __( 'Placement', 'upsellbay' ),
			'status'             => __( 'Status', 'upsellbay' ),
			'health'             => __( 'Health', 'upsellbay' ),
			'priority'           => __( 'Priority', 'upsellbay' ),
			'views'              => __( 'Views', 'upsellbay' ),
			'accepts'            => __( 'Accepts', 'upsellbay' ),
			'attributed_revenue' => __( 'Revenue', 'upsellbay' ),
		) as $column => $heading
		) {
			$this->render_column_heading( $column, $heading, $filters );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			$edit_url   = 'admin.php?page=upsellbay&tab=offers&action=edit&offer_id=' . (int) $row['id'];
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=upsellbay_delete_offer&offer_id=' . (int) $row['id'] ), 'upsellbay_delete_offer' );

			echo '<td><strong>' . esc_html( (string) $row['title'] ) . '</strong><div class="row-actions">';
			echo '<span><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'upsellbay' ) . '</a> | </span>';

			if ( ! empty( $row['preview_url'] ) ) {
				echo '<span class="view"><a href="' . esc_url( $row['preview_url'] ) . '" target="_blank" title="' . esc_attr( $row['preview_msg'] ) . '">' . esc_html__( 'View Live', 'upsellbay' ) . '</a> | </span>';
			} else {
				echo '<span class="view"><span style="color: #a7aaad; cursor: help;" title="' . esc_attr( $row['preview_msg'] ) . '">' . esc_html__( 'View Live', 'upsellbay' ) . '</span> | </span>';
			}

			echo '<span class="trash"><a href="' . esc_url( $delete_url ) . '" class="submitdelete upsellbay-modal-trigger" data-modal-title="' . esc_attr__( 'Delete Offer', 'upsellbay' ) . '" data-modal-message="' . esc_attr__( 'Are you sure you want to permanently delete this offer? This cannot be undone.', 'upsellbay' ) . '" data-modal-confirm="' . esc_attr__( 'Delete', 'upsellbay' ) . '" data-modal-cancel="' . esc_attr__( 'Cancel', 'upsellbay' ) . '">' . esc_html__( 'Delete', 'upsellbay' ) . '</a></span>';
			echo '</div></td>';
			echo '<td>' . esc_html( $this->placement_label( (string) $row['placement'] ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( (string) $row['status'] ) ) . '</td>';
			$health_html = 'ok' === ( $row['health'] ?? 'ok' ) ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'No conflicts', 'upsellbay' ) . '"></span>' : '<span class="dashicons dashicons-warning" style="color: #dba617;" title="' . esc_attr__( 'Placement crowding or funnel overlap detected', 'upsellbay' ) . '"></span>';
			echo '<td>' . wp_kses_post( $health_html ) . '</td>';
			echo '<td>' . esc_html( (string) $row['priority'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['views'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['accepts'] ) . '</td>';
			echo '<td>' . esc_html( $this->format_currency( $row['attributed_revenue'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<div class="tablenav bottom">';
		$this->render_pagination( $filters );
		echo '<br class="clear"></div>';
	}

	/**
	 * Render the offers overview section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, int> $overview Overview counts.
	 */
	private function render_overview( array $overview ): void {
		$total_offers      = (int) ( $overview['total_offers'] ?? 0 );
		$active_offers     = (int) ( $overview['active_offers'] ?? 0 );
		$paused_offers     = (int) ( $overview['paused_offers'] ?? 0 );
		$draft_offers      = (int) ( $overview['draft_offers'] ?? 0 );
		$conflicted_offers = (int) ( $overview['conflicted_offers'] ?? 0 );

		echo '<div class="upsellbay-overview-header">';
		echo '<h3 class="upsellbay-overview-title">' . esc_html__( 'Overview', 'upsellbay' ) . '</h3>';
		echo '</div>';
		echo '<p class="description">' . esc_html( $this->overview_summary_text( $total_offers, $active_offers, $paused_offers, $draft_offers ) ) . '</p>';
		echo '<div class="upsellbay-card-grid upsellbay-card-grid--metrics">';
		$this->summary_item( __( 'Total offers', 'upsellbay' ), (string) $total_offers, __( 'All published UpsellBay offers stored in the private offer library.', 'upsellbay' ) );
		$this->summary_item( __( 'Active offers', 'upsellbay' ), (string) $active_offers, __( 'Offers currently eligible to render when their rules and placement match.', 'upsellbay' ) );
		$this->summary_item( __( 'Paused offers', 'upsellbay' ), (string) $paused_offers, __( 'Offers saved for later but currently disabled from live rendering.', 'upsellbay' ) );
		$this->summary_item( __( 'Draft offers', 'upsellbay' ), (string) $draft_offers, __( 'Unpublished or unfinished offers that are not yet live.', 'upsellbay' ) );
		$this->summary_item( __( 'Active offers with conflicts', 'upsellbay' ), $this->count_or_none( $conflicted_offers ), __( 'Active offers with detected placement crowding or funnel overlap.', 'upsellbay' ) );
		echo '</div>';
	}

	/**
	 * Return the overview sentence.
	 *
	 * @since 1.0.0
	 *
	 * @param int $total_offers Total offers.
	 * @param int $active_offers Active offers.
	 * @param int $paused_offers Paused offers.
	 * @param int $draft_offers Draft offers.
	 */
	private function overview_summary_text( int $total_offers, int $active_offers, int $paused_offers, int $draft_offers ): string {
		/* translators: 1: total offers, 2: active offers, 3: paused offers, 4: draft offers. */
		$message = __( '%1$d total offers: %2$d active, %3$d paused, %4$d draft.', 'upsellbay' );

		return sprintf(
			$message,
			$total_offers,
			$active_offers,
			$paused_offers,
			$draft_offers
		);
	}

	/**
	 * Return a display value for a count or zero-state.
	 *
	 * @since 1.0.0
	 *
	 * @param int $count Count value.
	 */
	private function count_or_none( int $count ): string {
		if ( 0 === $count ) {
			return __( 'None', 'upsellbay' );
		}

		return (string) $count;
	}

	/**
	 * Render a native metric card.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label Metric label.
	 * @param string $value Metric value.
	 * @param string $help  Optional help tip text.
	 */
	private function summary_item( string $label, string $value, string $help = '' ): void {
		echo '<div class="upsellbay-metric-card">';
		if ( '' !== $help ) {
			echo '<span class="upsellbay-metric-card__help">' . $this->help_tip( $help ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help markup.
		}
		echo '<span class="upsellbay-metric-card__value">' . esc_html( $value ) . '</span>';
		echo '<span class="upsellbay-metric-card__label">' . esc_html( $label ) . '</span>';
		echo '</div>';
	}

	/**
	 * Render a WooCommerce help tip when available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Tip text.
	 * @return string
	 */
	private function help_tip( string $text ): string {
		if ( function_exists( 'wc_help_tip' ) ) {
			return wc_help_tip( $text, false );
		}

		return '<span class="description">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Normalize table controls from the current request.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed> $request Request data.
	 * @return array<string, mixed>
	 */
	private function filters_from_request( array $request ): array {
		return array(
			'search'    => $this->sanitize_text( (string) ( $request['s'] ?? $request['search'] ?? '' ) ),
			'placement' => $this->sanitize_key( (string) ( $request['placement'] ?? '' ) ),
			'status'    => $this->sanitize_key( (string) ( $request['status'] ?? '' ) ),
			'health'    => $this->sanitize_key( (string) ( $request['health'] ?? '' ) ),
			'orderby'   => $this->sanitize_key( (string) ( $request['orderby'] ?? 'priority' ) ),
			'order'     => 'desc' === strtolower( (string) ( $request['order'] ?? 'asc' ) ) ? 'desc' : 'asc',
			'paged'     => max( 1, (int) ( $request['paged'] ?? 1 ) ),
			'per_page'  => 20,
		);
	}

	/**
	 * Render search and filter controls.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Filters.
	 */
	private function render_table_controls( array $filters ): void {
		echo '<form method="get" class="upsellbay-offers-table-controls">';
		echo '<input type="hidden" name="page" value="upsellbay">';
		echo '<input type="hidden" name="tab" value="offers">';

		echo '<div class="tablenav top" style="padding-bottom: 10px;">';

		echo '<div class="alignleft actions">';
		$this->select_filter( 'placement', __( 'All placements', 'upsellbay' ), $this->placement_options(), (string) $filters['placement'] );
		$this->select_filter( 'status', __( 'All statuses', 'upsellbay' ), $this->status_options(), (string) $filters['status'] );
		$this->select_filter( 'health', __( 'All health states', 'upsellbay' ), $this->health_options(), (string) $filters['health'] );
		echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'upsellbay' ) . '</button>';

		$has_active_filters = '' !== (string) $filters['placement'] || '' !== (string) $filters['status'] || '' !== (string) $filters['health'] || '' !== (string) $filters['search'];
		if ( $has_active_filters ) {
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=upsellbay&tab=offers' ) ) . '" class="button">' . esc_html__( 'Clear', 'upsellbay' ) . '</a>';
		}

		echo '</div>';

		echo '<div class="alignright actions">';
		echo '<label class="screen-reader-text" for="upsellbay-offer-search-input">' . esc_html__( 'Search offers', 'upsellbay' ) . '</label>';
		echo '<input type="search" id="upsellbay-offer-search-input" name="s" value="' . esc_attr( (string) $filters['search'] ) . '"> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Search offers', 'upsellbay' ) . '</button>';
		echo '</div>';

		echo '<br class="clear"></div>';
		echo '</form>';
	}

	/**
	 * Render one select filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $name    Field name.
	 * @param string                $all     Empty option label.
	 * @param array<string, string> $options Options.
	 * @param string                $current Current value.
	 */
	private function select_filter( string $name, string $all, array $options, string $current ): void {
		echo '<label class="screen-reader-text" for="upsellbay-filter-' . esc_attr( $name ) . '">' . esc_html( $all ) . '</label>';
		echo '<select id="upsellbay-filter-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html( $all ) . '</option>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
	}

	/**
	 * Render a sortable heading th element.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $column  Column key.
	 * @param string               $label   Column label.
	 * @param array<string, mixed> $filters Filters.
	 */
	private function render_column_heading( string $column, string $label, array $filters ): void {
		$is_current = $column === (string) $filters['orderby'];
		$order      = strtolower( (string) $filters['order'] );
		$next_order = $is_current && 'asc' === $order ? 'desc' : 'asc';

		$classes = array( 'manage-column', 'column-' . $column );
		if ( $is_current ) {
			$classes[] = 'sorted';
			$classes[] = $order;
		} else {
			$classes[] = 'sortable';
			// If not sorted, clicking sets to 'asc' (usually), so the class is 'desc'.
			// For some columns default is 'desc', but we just default to 'desc' here to show the 'asc' arrow on hover.
			$classes[] = 'desc';
		}

		$url = $this->table_url(
			array_merge(
				$filters,
				array(
					'orderby' => $column,
					'order'   => $next_order,
					'paged'   => 1,
				)
			)
		);

		echo '<th scope="col" class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		echo '<a href="' . esc_url( $url ) . '">';
		echo '<span>' . esc_html( $label ) . '</span>';
		echo '<span class="sorting-indicators">';
		echo '<span class="sorting-indicator asc" aria-hidden="true"></span>';
		echo '<span class="sorting-indicator desc" aria-hidden="true"></span>';
		echo '</span>';
		echo '</a>';
		echo '</th>';
	}

	/**
	 * Render pagination controls.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Filters.
	 */
	private function render_pagination( array $filters ): void {
		$total_items = $this->table->last_total_items();
		$per_page    = max( 1, (int) ( $filters['per_page'] ?? 20 ) );
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		$current     = min( max( 1, (int) ( $filters['paged'] ?? 1 ) ), $total_pages );
		$one_page    = $total_pages <= 1 ? ' one-page' : '';

		echo '<div class="tablenav-pages' . esc_attr( $one_page ) . '">';
		/* translators: %s: number of offers. */
		$item_label = 1 === $total_items ? __( '%s item', 'upsellbay' ) : __( '%s items', 'upsellbay' );
		echo '<span class="displaying-num">' . esc_html( sprintf( $item_label, number_format_i18n( $total_items ) ) ) . '</span> ';
		echo '<span class="pagination-links">';

		if ( 1 === $current ) {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span> ';
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span> ';
		} else {
			echo '<a class="first-page button" href="' . esc_url( $this->table_url( array_merge( $filters, array( 'paged' => 1 ) ) ) ) . '"><span class="screen-reader-text">' . esc_html__( 'First page', 'upsellbay' ) . '</span><span aria-hidden="true">&laquo;</span></a> ';
			echo '<a class="prev-page button" href="' . esc_url( $this->table_url( array_merge( $filters, array( 'paged' => max( 1, $current - 1 ) ) ) ) ) . '"><span class="screen-reader-text">' . esc_html__( 'Previous page', 'upsellbay' ) . '</span><span aria-hidden="true">&lsaquo;</span></a> ';
		}

		echo '<span class="paging-input">' . esc_html( (string) $current ) . ' ' . esc_html__( 'of', 'upsellbay' ) . ' <span class="total-pages">' . esc_html( (string) $total_pages ) . '</span></span> ';

		if ( $current >= $total_pages ) {
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span> ';
			echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
		} else {
			echo '<a class="next-page button" href="' . esc_url( $this->table_url( array_merge( $filters, array( 'paged' => min( $total_pages, $current + 1 ) ) ) ) ) . '"><span class="screen-reader-text">' . esc_html__( 'Next page', 'upsellbay' ) . '</span><span aria-hidden="true">&rsaquo;</span></a> ';
			echo '<a class="last-page button" href="' . esc_url( $this->table_url( array_merge( $filters, array( 'paged' => $total_pages ) ) ) ) . '"><span class="screen-reader-text">' . esc_html__( 'Last page', 'upsellbay' ) . '</span><span aria-hidden="true">&raquo;</span></a>';
		}

		echo '</span></div>';
	}

	/**
	 * Build a table-control URL.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Filters.
	 */
	private function table_url( array $filters ): string {
		$params = array(
			'page'      => 'upsellbay',
			'tab'       => 'offers',
			's'         => $filters['search'] ?? '',
			'placement' => $filters['placement'] ?? '',
			'status'    => $filters['status'] ?? '',
			'health'    => $filters['health'] ?? '',
			'orderby'   => $filters['orderby'] ?? 'priority',
			'order'     => $filters['order'] ?? 'asc',
			'paged'     => $filters['paged'] ?? 1,
		);
		$params = array_filter(
			$params,
			static fn ( $value ): bool => '' !== (string) $value
		);

		return 'admin.php?' . http_build_query( $params, '', '&' );
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
	 * Return placement filter options.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function placement_options(): array {
		return array(
			'checkout_bump'  => __( 'Checkout bump', 'upsellbay' ),
			'product_upsell' => __( 'Product page offer', 'upsellbay' ),
			'cart_crosssell' => __( 'Cart offer', 'upsellbay' ),
			'thankyou_offer' => __( 'Thank-you offer', 'upsellbay' ),
		);
	}

	/**
	 * Return status filter options.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function status_options(): array {
		return array(
			'active' => __( 'Active', 'upsellbay' ),
			'paused' => __( 'Paused', 'upsellbay' ),
			'draft'  => __( 'Draft', 'upsellbay' ),
		);
	}

	/**
	 * Return health filter options.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function health_options(): array {
		return array(
			'ok'      => __( 'Healthy', 'upsellbay' ),
			'warning' => __( 'Warnings', 'upsellbay' ),
		);
	}

	/**
	 * Format currency value.
	 *
	 * @param  mixed $value Value to format.
	 * @return string
	 */
	private function format_currency( $value ): string {
		$float_val = (float) $value;
		$symbol    = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		return $symbol . number_format_i18n( $float_val, 2 );
	}

	/**
	 * Sanitize a text field with a test-safe fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( preg_replace( '/<[^>]*>/', '', $value ) ?? '' );
	}

	/**
	 * Sanitize a key with a test-safe fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_key( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}
}
