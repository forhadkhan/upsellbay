<?php
/**
 * Product coexistence checks.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

/**
 * Detects adjacent products without reading private product state.
 *
 * @since 1.0.0
 */
final class Coexistence {
	/**
	 * Plugin-active callback.
	 *
	 * @var callable(string): bool
	 */
	private $is_plugin_active;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $is_plugin_active Plugin-active callback.
	 */
	public function __construct( ?callable $is_plugin_active = null ) {
		$this->is_plugin_active = $is_plugin_active ?? static function ( string $plugin ): bool {
			if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			return function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin );
		};
	}

	/**
	 * Detect whether CartBay is active.
	 *
	 * @since 1.0.0
	 */
	public function is_cartbay_active(): bool {
		return ( $this->is_plugin_active )( 'cartbay/cartbay.php' );
	}
}
