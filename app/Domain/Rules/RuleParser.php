<?php
/**
 * Offer rule parser.
 *
 * @package UpsellBay\Domain\Rules
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Rules;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Normalizes P0 offer targeting rules.
 *
 * @since 1.0.0
 */
final class RuleParser {
	/**
	 * Rule definitions.
	 *
	 * @var RuleDefinitions
	 */
	private RuleDefinitions $definitions;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param RuleDefinitions|null $definitions Rule definitions.
	 */
	public function __construct( ?RuleDefinitions $definitions = null ) {
		$this->definitions = $definitions ?? new RuleDefinitions();
	}

	/**
	 * Parse raw rules into a normalized array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $rules Raw rules.
	 * @return array<int, array<string, mixed>>|null Null means malformed.
	 */
	public function parse( $rules ): ?array {
		if ( ! is_array( $rules ) ) {
			return null;
		}

		$parsed = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				return null;
			}

			$type = $this->definitions->normalize_type( $this->sanitize_key( $rule['type'] ?? '' ) );
			if ( null === $this->definitions->get( $type ) ) {
				return null;
			}

			$operator = $this->definitions->resolve_operator(
				$type,
				$this->sanitize_key( $rule['operator'] ?? $this->definitions->default_operator( $type ) )
			);
			if ( null === $operator ) {
				return null;
			}

			$parsed[] = array(
				'type'     => $type,
				'operator' => $operator,
				'value'    => $rule['value'] ?? null,
			);
		}

		return $parsed;
	}

	/**
	 * Sanitize a rule key.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_key( $value ): string {
		return function_exists( 'sanitize_key' ) ? sanitize_key( (string) $value ) : strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ?? '' );
	}
}
