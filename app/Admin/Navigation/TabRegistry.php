<?php
/**
 * Admin tab registry.
 *
 * @package UpsellBay\Admin\Navigation
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Navigation;

/**
 * Stores internal admin tabs in display order.
 *
 * @since 1.0.0
 */
final class TabRegistry {
	/**
	 * Tabs keyed by ID.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, AdminTab>
	 */
	private array $tabs = array();

	/**
	 * Default tab ID.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $default_tab_id = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, AdminTab> $tabs Tabs in display order.
	 */
	public function __construct( array $tabs ) {
		foreach ( $tabs as $tab ) {
			$this->tabs[ $tab->id() ] = $tab;

			if ( '' === $this->default_tab_id ) {
				$this->default_tab_id = $tab->id();
			}
		}
	}

	/**
	 * Return all tabs keyed by ID.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, AdminTab>
	 */
	public function tabs(): array {
		return $this->tabs;
	}

	/**
	 * Return a tab by ID or null.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Tab ID.
	 */
	public function get( string $id ): ?AdminTab {
		return $this->tabs[ $id ] ?? null;
	}

	/**
	 * Return default tab.
	 *
	 * @since 1.0.0
	 */
	public function default_tab(): AdminTab {
		return $this->tabs[ $this->default_tab_id ];
	}
}
