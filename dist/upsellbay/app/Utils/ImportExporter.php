<?php
/**
 * Offer import/export schema foundation.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;

use WPAnchorBay\UpsellBay\Core\Hooks;
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

		$payload = Hooks::filter( 'export_payload', $payload, $offers );

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
			return new ValidationResult( false, $this->filter_import_errors( array( 'schema' => 'Unsupported import schema.' ), $payload ) );
		}

		if ( ! is_array( $payload['offers'] ?? null ) ) {
			return new ValidationResult( false, $this->filter_import_errors( array( 'offers' => 'Offers must be an array.' ), $payload ) );
		}

		foreach ( $payload['offers'] as $index => $offer ) {
			if ( ! is_array( $offer ) || ! is_array( $offer['meta'] ?? null ) ) {
				return new ValidationResult( false, $this->filter_import_errors( array( 'offers' => 'Offer item is malformed.' ), $payload ) );
			}

			$normalized = $this->validator->normalize( $offer['meta'] );
			if ( ! is_array( $offer['product_mapping'] ?? null ) ) {
				return new ValidationResult( false, $this->filter_import_errors( array( 'offer_' . $index => 'Product mapping must be present.' ), $payload ) );
			}

			$mapping = Hooks::filter( 'import_mapping', $offer['product_mapping'], $offer, $index );
			if ( is_array( $mapping ) ) {
				$payload['offers'][ $index ]['product_mapping'] = $mapping;
				$matched_product_id                             = (int) Hooks::filter( 'import_sku_match', 0, (string) ( $mapping['sku'] ?? '' ), $mapping, $offer );
				if ( $matched_product_id > 0 ) {
					$normalized['_ub_offer_product_id'] = $matched_product_id;
				}
			}

			$payload['offers'][ $index ]['meta']        = $normalized;
			$payload['offers'][ $index ]['post_status'] = (string) Hooks::filter( 'import_post_status', 'draft', $offer, $index );
		}

		return new ValidationResult( true, array(), $payload );
	}

	/**
	 * Filter import validation errors without bypassing validation.
	 *
	 * @param array<string, string> $errors  Validation errors.
	 * @param array<string, mixed>  $payload Import payload.
	 * @return array<string, string>
	 */
	private function filter_import_errors( array $errors, array $payload ): array {
		return Hooks::filter( 'import_validation_errors', $errors, $payload );
	}
}
