<?php
/**
 * Offer rule parser.
 *
 * @package UpsellBay\Domain\Rules
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Rules;

/**
 * Normalizes P0 offer targeting rules.
 *
 * @since 1.0.0
 */
final class RuleParser {
	private const TYPES = array(
		'cart_product',
		'cart_category',
		'cart_tag',
		'cart_subtotal',
		'viewed_product',
		'user_role',
		'customer_order_count',
		'customer_lifetime_spend',
		'stock_status',
		'exclude_if_product_in_cart',
	);

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

			$type = $this->sanitize_key( $rule['type'] ?? '' );
			if ( ! in_array( $type, self::TYPES, true ) ) {
				return null;
			}

			$parsed[] = array(
				'type'     => $type,
				'operator' => $this->sanitize_key( $rule['operator'] ?? 'eq' ),
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
