<?php
/**
 * Offer import/export schema foundation.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;

use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Offers\ValidationResult;

/**
 * Validates portable offer JSON and exports site-safe offer definitions.
 *
 * @since 1.0.0
 */
final class ImportExporter {
	private const TYPE    = 'upsellbay_offer_export';
	private const VERSION = 1;

	/**
	 * Offer validator.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferValidator
	 */
	private OfferValidator $validator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferValidator $validator Offer validator.
	 */
	public function __construct( OfferValidator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Export offers as portable JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $offers Offers.
	 */
	public function export( array $offers ): string {
		$payload = array(
			'type'    => self::TYPE,
			'version' => self::VERSION,
			'offers'  => array(),
		);

		foreach ( $offers as $offer ) {
			$meta = $this->validator->normalize( is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array() );
			unset( $meta['_ub_offer_product_id'], $meta['_ub_trigger_product_ids'], $meta['_ub_trigger_category_ids'] );

			$payload['offers'][] = array(
				'title'           => (string) ( $offer['title'] ?? '' ),
				'meta'            => $meta,
				'product_mapping' => array(
					'sku'  => (string) ( $offer['product_sku'] ?? '' ),
					'name' => (string) ( $offer['product_name'] ?? '' ),
				),
			);
		}

		if ( function_exists( 'wp_json_encode' ) ) {
			return (string) wp_json_encode( $payload );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return (string) json_encode( $payload );
	}

	/**
	 * Validate imported JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json Raw JSON.
	 */
	public function validate_json( string $json ): ValidationResult {
		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return new ValidationResult( false, array( 'json' => 'Invalid JSON.' ) );
		}

		if ( self::TYPE !== ( $payload['type'] ?? null ) || self::VERSION !== (int) ( $payload['version'] ?? 0 ) ) {
			return new ValidationResult( false, array( 'schema' => 'Unsupported import schema.' ) );
		}

		if ( ! is_array( $payload['offers'] ?? null ) ) {
			return new ValidationResult( false, array( 'offers' => 'Offers must be an array.' ) );
		}

		foreach ( $payload['offers'] as $index => $offer ) {
			if ( ! is_array( $offer ) || ! is_array( $offer['meta'] ?? null ) ) {
				return new ValidationResult( false, array( 'offers' => 'Offer item is malformed.' ) );
			}

			$normalized = $this->validator->normalize( $offer['meta'] );
			if ( ! is_array( $offer['product_mapping'] ?? null ) ) {
				return new ValidationResult( false, array( 'offer_' . $index => 'Product mapping must be present.' ) );
			}
		}

		return new ValidationResult( true, array(), $payload );
	}
}
