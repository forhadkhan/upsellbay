<?php
/**
 * Whole data import/export helper.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAnchorBay\UpsellBayPro\Core\Hooks;
use WPAnchorBay\UpsellBayPro\Core\Settings;
use WPAnchorBay\UpsellBayPro\Data\OfferRepository;
use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBayPro\Domain\Offers\ValidationResult;
use RuntimeException;

/**
 * Validates and handles exporting and importing of all settings and offers.
 *
 * @since 1.0.0
 */
final class ImportExporter {
	private const TYPE    = 'upsellbay_full_export';
	private const VERSION = 1;

	/**
	 * Largest import file accepted, in bytes (2 MB). A full export of hundreds
	 * of offers is far below it; the cap bounds the transient the preview step
	 * stores.
	 *
	 * @var int
	 */
	public const MAX_UPLOAD_BYTES = 2097152;

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
	 * Read an uploaded import file, or explain why it was refused.
	 *
	 * Accepts only a file PHP reports as uploaded without error, that is a real
	 * upload of this request (is_uploaded_file), and that is at most 2 MB.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $file        One `$_FILES` entry.
	 * @param callable|null        $is_uploaded Upload check (path): bool. Defaults to is_uploaded_file().
	 * @param callable|null        $reader      File reader (path): string|false. Defaults to file_get_contents().
	 * @return string|\WP_Error File contents, or an error with a merchant-facing message.
	 */
	public function read_upload( array $file, ?callable $is_uploaded = null, ?callable $reader = null ): string|\WP_Error {
		$is_uploaded = $is_uploaded ?? 'is_uploaded_file';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local upload, not a remote URL.
		$reader   = $reader ?? static fn ( string $path ) => file_get_contents( $path );
		$tmp_name = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		$error    = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		$size     = (int) ( $file['size'] ?? 0 );

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error || $size > self::MAX_UPLOAD_BYTES ) {
			return new \WP_Error( 'upsellbay_import_too_large', __( 'The import file is larger than 2 MB.', 'upsellbay' ) );
		}

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp_name || true !== ( $is_uploaded )( $tmp_name ) ) {
			return new \WP_Error( 'upsellbay_import_no_file', __( 'Please upload a valid JSON file.', 'upsellbay' ) );
		}

		$contents = ( $reader )( $tmp_name );
		if ( ! is_string( $contents ) ) {
			return new \WP_Error( 'upsellbay_import_no_file', __( 'Please upload a valid JSON file.', 'upsellbay' ) );
		}

		// The reported size comes from the client's request; check what was read.
		if ( strlen( $contents ) > self::MAX_UPLOAD_BYTES ) {
			return new \WP_Error( 'upsellbay_import_too_large', __( 'The import file is larger than 2 MB.', 'upsellbay' ) );
		}

		return $contents;
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
		$offers             = isset( $payload['offers'] ) && is_array( $payload['offers'] ) ? $payload['offers'] : array();

		// Check every offer before writing anything, so one bad offer cannot
		// leave the store half imported.
		$invalid = $this->invalid_offers( $offers );
		if ( array() !== $invalid ) {
			return new ValidationResult( false, $invalid );
		}

		if ( $import_settings && isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$this->settings->update( $this->importable_settings( $payload['settings'] ) );
		}

		$failed = array();

		if ( array() !== $offers ) {
			// Build a map of existing titles to IDs for duplicate detection.
			$existing_offers = $this->offers->query( array( 'limit' => -1 ) );
			$existing_titles = array();
			foreach ( $existing_offers as $existing ) {
				if ( isset( $existing['title'] ) && '' !== $existing['title'] ) {
					$existing_titles[ $existing['title'] ] = $existing['id'];
				}
			}

			foreach ( $offers as $index => $offer ) {
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
				if ( isset( $existing_titles[ $title ] ) && 'skip' === $duplicate_handling ) {
					continue;
				}

				// A write can still fail after the checks above (the post insert,
				// for one). That offer is reported and the rest are still imported.
				try {
					if ( isset( $existing_titles[ $title ] ) && 'overwrite' === $duplicate_handling ) {
						$this->offers->update(
							$existing_titles[ $title ],
							array(
								'title' => $final_title,
								'meta'  => $normalized,
							)
						);
						continue;
					}

					// 'duplicate' falls through and creates a new offer.
					$this->offers->create(
						array(
							'title' => $final_title,
							'meta'  => $normalized,
						)
					);
				} catch ( RuntimeException $exception ) {
					$failed[ 'offer_' . (int) $index ] = $this->offer_error( (int) $index, $title, $exception->getMessage() );
				}
			}
		}

		if ( array() !== $failed ) {
			$failed['partial'] = __( 'The other offers in the file were imported.', 'upsellbay' );

			return new ValidationResult( false, $failed, $payload );
		}

		return new ValidationResult( true, array(), $payload );
	}

	/**
	 * Every offer in the file that would be refused on write, keyed `offer_<index>`.
	 *
	 * @param array<int|string, mixed> $offers Offers from the import file.
	 * @return array<string, string> Merchant-facing error per invalid offer.
	 */
	private function invalid_offers( array $offers ): array {
		$invalid = array();

		foreach ( $offers as $index => $offer ) {
			if ( ! is_array( $offer ) || ! is_array( $offer['meta'] ?? null ) ) {
				continue;
			}

			$result = $this->validator->validate( $this->validator->normalize( $offer['meta'] ) );
			if ( ! $result->is_valid() ) {
				$invalid[ 'offer_' . (int) $index ] = $this->offer_error( (int) $index, (string) ( $offer['title'] ?? '' ), implode( ' ', $result->errors() ) );
			}
		}

		return $invalid;
	}

	/**
	 * Settings from an import file, reduced to what may move between stores.
	 *
	 * Only known settings keys are taken, and the store-specific ones (licence,
	 * wizard progress, the first offer ID, dismissed notices, the data cleanup
	 * switch, which a file must never turn on, and debug logging and the
	 * retention periods, which a file must not change) keep this store's values.
	 * The rest is merged over the current settings and goes through
	 * Settings::update(), the same normaliser the settings page saves through.
	 *
	 * @param array<string, mixed> $imported Settings from the file.
	 * @return array<string, mixed>
	 */
	private function importable_settings( array $imported ): array {
		$imported = array_intersect_key( $imported, $this->settings->defaults() );
		foreach ( array( 'license', 'wizard_completed', 'wizard_completed_at', 'first_offer_id', 'notice_dismissals', 'cleanup_on_delete', 'debug_logging', 'retention_days', 'data_retention' ) as $local_key ) {
			unset( $imported[ $local_key ] );
		}

		return array_replace( $this->settings->all(), $imported );
	}

	/**
	 * Merchant-facing error for one offer in the import file.
	 *
	 * @param int    $index   Position in the file (0-based).
	 * @param string $title   Offer title.
	 * @param string $details Validator or write error.
	 */
	private function offer_error( int $index, string $title, string $details ): string {
		return sprintf(
			/* translators: 1: offer position in the import file, 2: offer title, 3: error details. */
			__( 'Offer %1$d (%2$s) was not imported: %3$s', 'upsellbay' ),
			$index + 1,
			'' !== $title ? $title : __( 'untitled', 'upsellbay' ),
			$details
		);
	}
}
