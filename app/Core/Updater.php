<?php
/**
 * Plugin update checker bootstrap.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Initializes the automatic update checker via Plugin Update Checker.
 *
 * @since 1.0.0
 */
final class Updater {
	/**
	 * License client.
	 *
	 * @since 1.0.0
	 *
	 * @var LicenseClient
	 */
	private LicenseClient $license_client;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LicenseClient $license_client License client.
	 */
	public function __construct( LicenseClient $license_client ) {
		$this->license_client = $license_client;
	}

	/**
	 * Initialize PUC with the current license key.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		$license_data = $this->license_client->get_status();
		$license_key  = '';

		if ( isset( $license_data['key'] ) ) {
			$license_key = sanitize_text_field( (string) $license_data['key'] );
		}

		if ( '' === $license_key ) {
			return;
		}

		$update_url = Constants::LICENSE_SERVER_URL . '/update-check/' . Constants::LICENSE_PRODUCT_SLUG . '/' . rawurlencode( $license_key );
		$checker    = PucFactory::buildUpdateChecker( $update_url, Constants::plugin_file(), Constants::PLUGIN_SLUG );

		$checker->addQueryArgFilter(
			static function ( array $args ): array {
				$args['host'] = wp_parse_url( home_url(), PHP_URL_HOST );
				return $args;
			}
		);
	}
}
