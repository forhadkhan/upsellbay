<?php
/**
 * Whole data import/export helper.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBay\Core\Hooks;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Data\OfferRepository;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Offers\ValidationResult;

/**
 * Validates and handles exporting and importing of all settings and offers.
 *
 * @since 1.0.0
 */
final class ImportExporter {
	private const TYPE    = 'upsellbay_full_export';
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
	 * Offer repository.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $offers;

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferValidator  $validator Offer validator.
	 * @param OfferRepository $offers    Offer repository.
	 * @param Settings        $settings  Settings service.
	 */
	public function __construct( OfferValidator $validator, OfferRepository $offers, Settings $settings ) {
		$this->validator = $validator;
		$this->offers    = $offers;
		$this->settings  = $settings;
	}

	/**
	 * Export all data (settings and offers) as JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return string JSON string.
	 */
	public function export_all(): string {
		$offers_data = $this->offers->query( array( 'limit' => -1 ) );
		$payload     = array(
			'type'     => self::TYPE,
			'version'  => self::VERSION,
			'settings' => $this->settings->all(),
			'offers'   => array(),
		);

		foreach ( $offers_data as $offer ) {
			$meta = $this->validator->normalize( is_array( $offer['meta'] ?? null ) ? $offer['meta'] : array() );

			$payload['offers'][] = array(
				'title'  => (string) ( $offer['title'] ?? '' ),
				'status' => (string) ( $offer['status'] ?? 'publish' ),
				'meta'   => $meta,
			);
		}

		$payload = Hooks::filter( 'export_all_payload', $payload );

		if ( function_exists( 'wp_json_encode' ) ) {
			return (string) wp_json_encode( $payload );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return (string) json_encode( $payload );
	}

	/**
	 * Import all data (settings and offers) from JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $json        Raw JSON string.
	 * @param array<string, mixed> $preferences Import preferences.
	 * @return ValidationResult
	 */
	public function import_all( string $json, array $preferences = array() ): ValidationResult {
		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return new ValidationResult( false, array( 'json' => 'Invalid JSON.' ) );
		}

		if ( self::TYPE !== ( $payload['type'] ?? null ) || self::VERSION !== (int) ( $payload['version'] ?? 0 ) ) {
			return new ValidationResult( false, array( 'schema' => 'Unsupported import schema or file format.' ) );
		}

		$import_settings    = (bool) ( $preferences['import_settings'] ?? true );
		$append_text        = (bool) ( $preferences['append_text'] ?? false );
		$duplicate_handling = (string) ( $preferences['duplicate_handling'] ?? 'duplicate' );

		if ( $import_settings && isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$this->settings->update( $payload['settings'] );
		}

		if ( isset( $payload['offers'] ) && is_array( $payload['offers'] ) ) {
			// Build a map of existing titles to IDs for duplicate detection.
			$existing_offers = $this->offers->query( array( 'limit' => -1 ) );
			$existing_titles = array();
			foreach ( $existing_offers as $existing ) {
				if ( isset( $existing['title'] ) && '' !== $existing['title'] ) {
					$existing_titles[ $existing['title'] ] = $existing['id'];
				}
			}

			foreach ( $payload['offers'] as $offer ) {
				if ( ! is_array( $offer ) || ! is_array( $offer['meta'] ?? null ) ) {
					continue;
				}

				$normalized  = $this->validator->normalize( $offer['meta'] );
				$title       = (string) ( $offer['title'] ?? '' );
				$final_title = $title;

				if ( $append_text ) {
					$final_title = trim( $title . ' (Import)' );
				}

				// Duplicate handling based on original title match.
				if ( isset( $existing_titles[ $title ] ) ) {
					if ( 'skip' === $duplicate_handling ) {
						continue;
					}

					if ( 'overwrite' === $duplicate_handling ) {
						$this->offers->update(
							$existing_titles[ $title ],
							array(
								'title' => $final_title,
								'meta'  => $normalized,
							)
						);
						continue;
					}

					// 'duplicate' strategy will just fall through and create a new one.
					// If append text wasn't checked, we might want to append " Copy" or similar,
					// but user opted for "duplicate", so we just create it.
				}

				$this->offers->create(
					array(
						'title' => $final_title,
						'meta'  => $normalized,
					)
				);
			}
		}

		return new ValidationResult( true, array(), $payload );
	}
}
