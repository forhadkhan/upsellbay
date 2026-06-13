<?php
/**
 * Offer list table data adapter.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

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
		$rows = array();

		foreach ( $this->repository->query( $filters ) as $offer ) {
			$meta      = is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array();
			$placement = (string) ( $meta['_ub_offer_type'] ?? '' );
			$status    = (string) ( $meta['_ub_status'] ?? '' );

			if ( isset( $filters['placement'] ) && '' !== $filters['placement'] && $placement !== $filters['placement'] ) {
				continue;
			}

			if ( isset( $filters['status'] ) && '' !== $filters['status'] && $status !== $filters['status'] ) {
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
			);
		}

		return $rows;
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
}
