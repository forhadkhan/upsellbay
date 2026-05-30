<?php
/**
 * Private updater shell.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;

use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;

/**
 * Coordinates future private update checks through the license client.
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
	 * Return product identity for update requests.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function product_identity(): array {
		return array(
			'slug'    => Constants::PLUGIN_SLUG,
			'product' => Constants::LICENSE_PRODUCT_SLUG,
			'version' => Constants::VERSION,
		);
	}

	/**
	 * License client accessor for future updater integration.
	 *
	 * @since 1.0.0
	 */
	public function license_client(): LicenseClient {
		return $this->license_client;
	}
}
