<?php
/**
 * Admin tab router.
 *
 * @package UpsellBay\Admin\Navigation
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Navigation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Resolves the current internal admin tab from request data.
 *
 * @since 1.0.0
 */
final class TabRouter {
	/**
	 * Tab registry.
	 *
	 * @since 1.0.0
	 *
	 * @var TabRegistry
	 */
	private TabRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param TabRegistry $registry Tab registry.
	 */
	public function __construct( TabRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Resolve the active tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function current_tab( array $request ): AdminTab {
		$tab_id = $this->sanitize_key( (string) ( $request['tab'] ?? '' ) );
		$tab    = '' !== $tab_id ? $this->registry->get( $tab_id ) : null;

		return $tab ?? $this->registry->default_tab();
	}

	/**
	 * Sanitize tab key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_key( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}
}
