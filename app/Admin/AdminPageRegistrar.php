<?php
/**
 * WooCommerce admin page registration.
 *
 * @package UpsellBay\Admin
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin;

use WPAnchorBay\UpsellBay\Core\Constants;

/**
 * Registers UpsellBay admin pages under WooCommerce only.
 *
 * @since 1.0.0
 */
final class AdminPageRegistrar {
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Submenu registration callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable(string, string, string, string, string, callable): string
	 */
	private $add_submenu_page;

	/**
	 * Page callbacks keyed by menu slug.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, callable>
	 */
	private array $callbacks;

	/**
	 * Registered screen IDs.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	private array $screen_ids = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null           $add_submenu_page Submenu registration callback.
	 * @param array<string, callable> $callbacks       Page callbacks.
	 */
	public function __construct( ?callable $add_submenu_page = null, array $callbacks = array() ) {
		$this->add_submenu_page = $add_submenu_page ?? static function ( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): string {
			if ( function_exists( 'add_submenu_page' ) ) {
				return (string) add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback );
			}

			return 'woocommerce_page_' . $menu_slug;
		};
		$this->callbacks        = $callbacks;
	}

	/**
	 * Return the supported admin surface slugs.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public static function surface_slugs(): array {
		return array(
			'upsellbay',
		);
	}

	/**
	 * Register WooCommerce submenu pages.
	 *
	 * @since 1.0.0
	 */
	public function register_pages(): void {
		foreach ( $this->page_definitions() as $definition ) {
			$callback = $this->callbacks[ $definition['slug'] ] ?? array( $this, 'render_placeholder' );

			$this->screen_ids[ $definition['slug'] ] = ( $this->add_submenu_page )(
				'woocommerce',
				$definition['page_title'],
				$definition['menu_title'],
				self::CAPABILITY,
				$definition['slug'],
				$callback
			);
		}
	}

	/**
	 * Return registered screen IDs keyed by menu slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function screen_ids(): array {
		return $this->screen_ids;
	}

	/**
	 * Return top-level pages introduced by UpsellBay.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function top_level_pages(): array {
		return array();
	}

	/**
	 * Determine whether a screen ID belongs to UpsellBay.
	 *
	 * @since 1.0.0
	 *
	 * @param string $screen_id Screen ID.
	 */
	public function is_upsellbay_screen( string $screen_id ): bool {
		return 'woocommerce_page_upsellbay' === $screen_id;
	}

	/**
	 * Render a small placeholder for unbound callbacks.
	 *
	 * @since 1.0.0
	 */
	public function render_placeholder(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'UpsellBay', 'upsellbay' ) . '</h1></div>';
	}

	/**
	 * Page definitions.
	 *
	 * @return array<int, array{slug: string, page_title: string, menu_title: string}>
	 */
	private function page_definitions(): array {
		return array(
			array(
				'slug'       => Constants::PLUGIN_SLUG,
				'page_title' => __( 'UpsellBay', 'upsellbay' ),
				'menu_title' => __( 'UpsellBay', 'upsellbay' ),
			),
		);
	}
}
