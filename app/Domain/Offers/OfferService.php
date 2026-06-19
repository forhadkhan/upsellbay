<?php
/**
 * Offer lifecycle service.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use RuntimeException;
use WPAnchorBay\UpsellBay\Core\Hooks;
use WPAnchorBay\UpsellBay\Data\OfferRepository;

/**
 * Centralizes offer lifecycle behavior for admin and API callers.
 *
 * @since 1.0.0
 */
final class OfferService {
	/**
	 * Offer repository.
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $repository;

	/**
	 * Offer validator.
	 *
	 * @var OfferValidator
	 */
	private OfferValidator $validator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferRepository $repository Offer repository.
	 * @param OfferValidator  $validator  Offer validator.
	 */
	public function __construct( OfferRepository $repository, OfferValidator $validator ) {
		$this->repository = $repository;
		$this->validator  = $validator;
	}

	/**
	 * Create an offer after schema validation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer Offer payload.
	 */
	public function create( array $offer ): int {
		$this->assert_valid( $offer['meta'] ?? array() );
		$offer_id = $this->repository->create( $offer );

		/**
		 * Fires after an offer is created through the public service boundary.
		 *
		 * @since 1.0.0
		 *
		 * @param int                  $offer_id Offer ID.
		 * @param array<string, mixed> $offer    Submitted offer payload.
		 */
		Hooks::action( 'offer_created', $offer_id, $offer );

		return $offer_id;
	}

	/**
	 * Update an offer after schema validation.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id Offer ID.
	 * @param array<string, mixed> $offer    Offer payload.
	 * @throws RuntimeException When the offer ID or meta is invalid.
	 */
	public function update( int $offer_id, array $offer ): bool {
		if ( $offer_id <= 0 ) {
			throw new RuntimeException( 'Invalid offer ID.' );
		}

		$this->assert_valid( $offer['meta'] ?? array() );
		$updated = $this->repository->update( $offer_id, $offer );
		if ( $updated ) {
			/**
			 * Fires after an offer is updated through the public service boundary.
			 *
			 * @since 1.0.0
			 *
			 * @param int                  $offer_id Offer ID.
			 * @param array<string, mixed> $offer    Submitted offer payload.
			 */
			Hooks::action( 'offer_updated', $offer_id, $offer );
		}

		return $updated;
	}

	/**
	 * Duplicate an existing offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 * @throws RuntimeException When the offer ID is invalid.
	 */
	public function duplicate( int $offer_id ): int {
		if ( $offer_id <= 0 ) {
			throw new RuntimeException( 'Invalid offer ID.' );
		}

		return $this->repository->duplicate( $offer_id );
	}

	/**
	 * Pause an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function pause( int $offer_id ): bool {
		return $offer_id > 0 && $this->repository->pause( $offer_id );
	}

	/**
	 * Activate an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function activate( int $offer_id ): bool {
		return $offer_id > 0 && $this->repository->activate( $offer_id );
	}

	/**
	 * Delete an offer by trashing the CPT record.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function delete( int $offer_id ): bool {
		$deleted = $offer_id > 0 && $this->repository->trash( $offer_id );
		if ( $deleted ) {
			Hooks::action( 'offer_deleted', $offer_id );
		}
		return $deleted;
	}

	/**
	 * Load an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $offer_id ): ?array {
		return $offer_id > 0 ? $this->repository->get( $offer_id ) : null;
	}

	/**
	 * Build a safe preview payload from an offer array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer Offer payload.
	 * @return array<string, mixed>
	 */
	public function preview_payload( array $offer ): array {
		$meta = $this->validator->normalize( is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array() );

		return array(
			'id'               => (int) ( $offer['id'] ?? 0 ),
			'title'            => (string) ( $offer['title'] ?? '' ),
			'placement'        => $meta['_ub_offer_type'],
			'offer_product_id' => $meta['_ub_offer_product_id'],
			'headline'         => $meta['_ub_headline'],
			'body'             => $meta['_ub_body'],
			'button_text'      => $meta['_ub_button_text'],
			'discount_type'    => $meta['_ub_discount_type'],
			'discount_value'   => $meta['_ub_discount_value'],
		);
	}

	/**
	 * Validate meta and throw a service-level exception on failure.
	 *
	 * @param array<string, mixed> $meta Offer meta.
	 * @throws RuntimeException When offer meta is invalid.
	 */
	private function assert_valid( array $meta ): void {
		$result = $this->validator->validate( $meta );
		if ( ! $result->is_valid() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( implode( ' ', $result->errors() ) );
		}
	}
}
