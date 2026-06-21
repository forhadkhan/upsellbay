<?php
/**
 * Offer list table data adapter.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferConflictDetector;

/**
 * Provides native list-table compatible offer rows and actions.
 *
 * @since 1.0.0
 */
final class OfferListTable {
	/**
	 * Offer repository.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $repository;

	/**
	 * Offer service.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferService
	 */
	private OfferService $service;

	/**
	 * Offer conflict detector.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferConflictDetector|null
	 */
	private ?OfferConflictDetector $conflict_detector;

	/**
	 * Total rows after filters and before pagination.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	private int $last_total_items = 0;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository            $repository        Offer repository.
	 * @param OfferService               $service           Offer service.
	 * @param OfferConflictDetector|null $conflict_detector Offer conflict detector.
	 */
	public function __construct( OfferRepository $repository, OfferService $service, ?OfferConflictDetector $conflict_detector = null ) {
		$this->repository        = $repository;
		$this->service           = $service;
		$this->conflict_detector = $conflict_detector;
	}

	/**
	 * Return normalized rows for list-table rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( array $filters = array() ): array {
		$filters = $this->normalize_filters( $filters );
		$rows    = array();

		// Pre-compute context for preview links.
		$context = array();
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			$context['checkout_url'] = wc_get_checkout_url();
		}
		if ( function_exists( 'wc_get_cart_url' ) ) {
			$context['cart_url'] = wc_get_cart_url();
		}
		if ( function_exists( 'wc_get_orders' ) ) {
			$latest_orders = wc_get_orders(
				array(
					'limit'   => 1,
					'orderby' => 'date',
					'order'   => 'DESC',
					'status'  => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				)
			);
			if ( ! empty( $latest_orders ) ) {
				$context['order_received_url'] = $latest_orders[0]->get_checkout_order_received_url();
			}
		}

		$preview_builder = new \WPAnchorBay\UpsellBay\Admin\PreviewLinks();

		foreach ( $this->repository->query( $filters ) as $offer ) {
			$meta      = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
			$placement = (string) ( $meta['_ub_offer_type'] ?? '' );
			$status    = (string) ( $meta['_ub_status'] ?? '' );

			if ( '' !== $filters['placement'] && $placement !== $filters['placement'] ) {
				continue;
			}

			if ( '' !== $filters['status'] && $status !== $filters['status'] ) {
				continue;
			}

			$id = (int) ( $offer['id'] ?? $offer['ID'] ?? 0 );

			$health = 'ok';
			if ( 'active' === $status && null !== $this->conflict_detector ) {
				$warnings = $this->conflict_detector->detect( $id, $meta );
				if ( count( $warnings ) > 0 ) {
					$health = 'warning';
				}
			}

			$preview = $preview_builder->for_offer( $offer, $context );

			$rows[] = array(
				'id'                 => $id,
				'title'              => (string) ( $offer['title'] ?? $offer['post_title'] ?? '' ),
				'placement'          => $placement,
				'status'             => $status,
				'health'             => $health,
				'priority'           => (int) ( $meta['_ub_priority'] ?? 0 ),
				'views'              => (int) ( $offer['views'] ?? 0 ),
				'accepts'            => (int) ( $offer['accepts'] ?? 0 ),
				'attributed_revenue' => (string) ( $offer['attributed_revenue'] ?? '0.000000' ),
				'preview_url'        => $preview['available'] ? $preview['url'] : '',
				'preview_msg'        => $preview['message'],
			);
		}

		$rows = $this->apply_search_filter( $rows, $filters['search'] );
		$rows = $this->apply_health_filter( $rows, $filters['health'] );
		$rows = $this->sort_rows( $rows, $filters['orderby'], $filters['order'] );

		$this->last_total_items = count( $rows );

		if ( $filters['per_page'] > 0 ) {
			return array_slice( $rows, ( $filters['paged'] - 1 ) * $filters['per_page'], $filters['per_page'] );
		}

		return $rows;
	}

	/**
	 * Return the filtered row count from the last rows call.
	 *
	 * @since 1.0.0
	 */
	public function last_total_items(): int {
		return $this->last_total_items;
	}

	/**
	 * Return overview data for the offers tab.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int>
	 */
	public function overview_data(): array {
		$offers = $this->repository->query(
			array(
				'limit' => -1,
			)
		);

		$summary = array(
			'total_offers'      => 0,
			'active_offers'     => 0,
			'paused_offers'     => 0,
			'draft_offers'      => 0,
			'conflicted_offers' => 0,
		);

		foreach ( $offers as $offer ) {
			++$summary['total_offers'];

			$meta   = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
			$status = (string) ( $meta['_ub_status'] ?? 'draft' );

			if ( in_array( $status, array( 'active', 'paused', 'draft' ), true ) ) {
				++$summary[ $status . '_offers' ];
			}

			if ( 'active' !== $status || null === $this->conflict_detector ) {
				continue;
			}

			$warnings = $this->conflict_detector->detect( (int) ( $offer['id'] ?? $offer['ID'] ?? 0 ), $meta );
			if ( count( $warnings ) > 0 ) {
				++$summary['conflicted_offers'];
			}
		}

		return $summary;
	}

	/**
	 * Return sortable table columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function sortable_columns(): array {
		return array( 'title', 'placement', 'status', 'health', 'priority', 'views', 'accepts', 'attributed_revenue' );
	}

	/**
	 * Return supported bulk actions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function bulk_actions(): array {
		return array( 'pause', 'duplicate', 'trash' );
	}

	/**
	 * Handle a nonce/capability-checked row action.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action   Action.
	 * @param int    $offer_id Offer ID.
	 */
	public function handle_row_action( string $action, int $offer_id ): bool {
		if ( $offer_id < 1 ) {
			return false;
		}

		if ( 'pause' === $action ) {
			return $this->service->pause( $offer_id );
		}

		if ( 'duplicate' === $action ) {
			return $this->service->duplicate( $offer_id ) > 0;
		}

		if ( 'trash' === $action || 'delete' === $action ) {
			return $this->service->delete( $offer_id );
		}

		return false;
	}

	/**
	 * Normalize supported table controls.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array{placement: string, status: string, health: string, search: string, orderby: string, order: string, paged: int, per_page: int}
	 */
	private function normalize_filters( array $filters ): array {
		$allowed_orderby = $this->sortable_columns();
		$orderby         = (string) ( $filters['orderby'] ?? 'priority' );
		$order           = strtolower( (string) ( $filters['order'] ?? 'asc' ) );

		return array(
			'placement' => $this->sanitize_key( (string) ( $filters['placement'] ?? '' ) ),
			'status'    => $this->sanitize_key( (string) ( $filters['status'] ?? '' ) ),
			'health'    => $this->sanitize_key( (string) ( $filters['health'] ?? '' ) ),
			'search'    => strtolower( trim( (string) ( $filters['search'] ?? $filters['s'] ?? '' ) ) ),
			'orderby'   => in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'priority',
			'order'     => 'desc' === $order ? 'desc' : 'asc',
			'paged'     => max( 1, (int) ( $filters['paged'] ?? 1 ) ),
			'per_page'  => max( 0, (int) ( $filters['per_page'] ?? 0 ) ),
		);
	}

	/**
	 * Apply a simple merchant-facing search across visible row fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rows    Rows.
	 * @param string                           $search  Search term.
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_search_filter( array $rows, string $search ): array {
		if ( '' === $search ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $search ): bool {
					$haystack = strtolower( implode( ' ', array( $row['title'], $row['placement'], $row['status'], $row['health'] ) ) );
					return str_contains( $haystack, $search );
				}
			)
		);
	}

	/**
	 * Apply health filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rows    Rows.
	 * @param string                           $health  Health.
	 * @return array<int, array<string, mixed>>
	 */
	private function apply_health_filter( array $rows, string $health ): array {
		if ( '' === $health ) {
			return $rows;
		}

		$filtered = array_filter( $rows, static fn ( array $row ): bool => (string) ( $row['health'] ?? '' ) === $health );

		return array_values( $filtered );
	}

	/**
	 * Sort list-table rows.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rows     Rows.
	 * @param string                           $orderby  Column key.
	 * @param string                           $order    Sort direction.
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_rows( array $rows, string $orderby, string $order ): array {
		usort(
			$rows,
			static function ( array $a, array $b ) use ( $orderby, $order ): int {
				$a_value = $a[ $orderby ] ?? '';
				$b_value = $b[ $orderby ] ?? '';

				if ( in_array( $orderby, array( 'priority', 'views', 'accepts', 'attributed_revenue' ), true ) ) {
					$result = (float) $a_value <=> (float) $b_value;
				} else {
					$result = strnatcasecmp( (string) $a_value, (string) $b_value );
				}

				return 'desc' === $order ? -$result : $result;
			}
		);

		return $rows;
	}

	/**
	 * Sanitize a table-control key.
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
