<?php
/**
 * Compatibility scanner.
 *
 * @package UpsellBay\Domain\Compatibility
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Compatibility;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Detects known checkout and funnel plugins without blocking checkout.
 *
 * @since 1.0.0
 */
final class CompatibilityScanner {
	private const PLUGINS = array(
		'cartflows'  => array(
			'plugin'   => 'cartflows/cartflows.php',
			'severity' => 'warning',
			'message'  => 'CartFlows can replace checkout or funnel flow locations. Test UpsellBay placements before enabling live offers.',
		),
		'funnelkit'  => array(
			'plugin'   => 'funnel-builder/funnel-builder.php',
			'severity' => 'warning',
			'message'  => 'FunnelKit checkout or funnel customizations may change offer placement behavior.',
		),
		'checkoutwc' => array(
			'plugin'   => 'checkout-for-woocommerce/checkout-for-woocommerce.php',
			'severity' => 'warning',
			'message'  => 'CheckoutWC replaces the standard checkout surface. Use test mode before enabling checkout bumps.',
		),
		'cartbay'    => array(
			'plugin'   => 'cartbay/cartbay.php',
			'severity' => 'info',
			'message'  => 'CartBay can coexist with UpsellBay. UpsellBay does not read or write CartBay recovery data.',
		),
	);

	/**
	 * Active plugin callback.
	 *
	 * @var callable(string): bool
	 */
	private $is_active;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $is_active Active plugin callback.
	 */
	public function __construct( ?callable $is_active = null ) {
		$this->is_active = $is_active ?? array( $this, 'is_plugin_active' );
	}

	/**
	 * Return compatibility findings keyed by product.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function findings(): array {
		$findings = array();

		foreach ( self::PLUGINS as $key => $definition ) {
			if ( ( $this->is_active )( $definition['plugin'] ) ) {
				$findings[ $key ] = $definition;
			}
		}

		return $findings;
	}

	/**
	 * Checkout remains fail-open; findings are warnings and diagnostics.
	 *
	 * @since 1.0.0
	 */
	public function should_block_checkout(): bool {
		return false;
	}

	/**
	 * Default active plugin adapter.
	 *
	 * @param string $plugin Plugin basename.
	 */
	private function is_plugin_active( string $plugin ): bool {
		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( $plugin );
		}

		return false;
	}
}
