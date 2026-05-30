<?php
/**
 * Admin tab definition.
 *
 * @package UpsellBay\Admin\Navigation
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Navigation;

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $id              Tab identifier.
	 * @param string   $label           Tab label.
	 * @param callable $render_callback Tab renderer.
	 */
	public function __construct( string $id, string $label, callable $render_callback ) {
		$this->id              = $this->sanitize_id( $id );
		$this->label           = $label;
		$this->render_callback = $render_callback;
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
