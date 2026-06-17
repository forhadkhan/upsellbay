<?php
/**
 * Admin tab definition.
 *
 * @package UpsellBay\Admin\Navigation
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Navigation;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Defines one internal UpsellBay admin tab.
 *
 * @since 1.0.0
 */
final class AdminTab {
	/**
	 * Tab identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Tab label.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Tab render callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable
	 */
	private $render_callback;

	/**
	 * Optional pre-render callback.
	 *
	 * @since 1.0.0
	 *
	 * @var callable|null
	 */
	private $prepare_callback;

	/**
	 * Whether the tab should appear in the navigation bar.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $show_in_nav;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $id               Tab identifier.
	 * @param string        $label            Tab label.
	 * @param callable      $render_callback  Tab renderer.
	 * @param callable|null $prepare_callback Optional pre-render callback.
	 * @param bool          $show_in_nav      Whether to show in the navigation bar.
	 */
	public function __construct( string $id, string $label, callable $render_callback, ?callable $prepare_callback = null, bool $show_in_nav = true ) {
		$this->id               = $this->sanitize_id( $id );
		$this->label            = $label;
		$this->render_callback  = $render_callback;
		$this->prepare_callback = $prepare_callback;
		$this->show_in_nav      = $show_in_nav;
	}

	/**
	 * Return tab identifier.
	 *
	 * @since 1.0.0
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Return tab label.
	 *
	 * @since 1.0.0
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Whether the tab should appear in the navigation bar.
	 *
	 * @since 1.0.0
	 */
	public function show_in_nav(): bool {
		return $this->show_in_nav;
	}

	/**
	 * Render tab content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request context.
	 */
	public function render( array $request ): void {
		( $this->render_callback )( $request );
	}

	/**
	 * Run pre-render work for this tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request context.
	 */
	public function prepare( array $request ): void {
		if ( null === $this->prepare_callback ) {
			return;
		}

		( $this->prepare_callback )( $request );
	}

	/**
	 * Sanitize an internal tab ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Raw ID.
	 */
	private function sanitize_id( string $id ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $id );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $id ) ?? '' );
	}
}
