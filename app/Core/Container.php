<?php
/**
 * Minimal service container.
 *
 * @package UpsellBay\Core
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Core;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use InvalidArgumentException;

/**
 * Resolves request-scoped singleton services.
 *
 * @since 1.0.0
 */
final class Container {
	/**
	 * Registered factories.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, callable(self): mixed>
	 */
	private array $factories = array();

	/**
	 * Resolved service instances.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a singleton factory.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $id      Service identifier.
	 * @param callable(self): mixed $factory Service factory.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Determine whether a service exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Service identifier.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Resolve a service once per request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 * @throws InvalidArgumentException When the service is not registered.
	 */
	public function get( string $id ) {
		if ( ! $this->has( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( "UpsellBay service '{$id}' is not registered." );
		}

		if ( ! array_key_exists( $id, $this->instances ) ) {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		}

		return $this->instances[ $id ];
	}
}
