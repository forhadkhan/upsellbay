<?php
/**
 * Offer rule evaluator.
 *
 * @package UpsellBay\Domain\Rules
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Rules;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Hooks;

/**
 * Evaluates normalized offer rules against a server-side context.
 *
 * @since 1.0.0
 */
final class RuleEvaluator {
	/**
	 * Rule parser.
	 *
	 * @var RuleParser
	 */
	private RuleParser $parser;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param RuleParser $parser Rule parser.
	 */
	public function __construct( RuleParser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * Return whether rules match the context.
	 *
	 * Empty rules mean eligible. Malformed rules fail closed.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed                $rules   Raw or normalized rules.
	 * @param string               $match_mode all|any.
	 * @param array<string, mixed> $context Context.
	 */
	public function matches( $rules, string $match_mode, array $context ): bool {
		/**
		 * Filter the server-side context used for rule evaluation.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $context Rule context.
		 */
		$context = Hooks::filter( 'rule_context', $context );
		$parsed  = $this->parser->parse( $rules );
		if ( null === $parsed ) {
			return false;
		}

		if ( array() === $parsed ) {
			return true;
		}

		$results = array_map( fn ( array $rule ): bool => $this->evaluate_rule( $rule, $context ), $parsed );

		return 'any' === $match_mode ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/**
	 * Evaluate one rule.
	 *
	 * @param array<string, mixed> $rule    Rule.
	 * @param array<string, mixed> $context Context.
	 */
	private function evaluate_rule( array $rule, array $context ): bool {
		$result = match ( $rule['type'] ) {
			'cart_product'                  => $this->compare_list( $context['cart_product_ids'] ?? array(), $rule['operator'], $rule['value'] ),
			'cart_category'                 => $this->compare_list( $context['cart_category_ids'] ?? array(), $rule['operator'], $rule['value'] ),
			'cart_tag'                      => $this->compare_list( $context['cart_tag_ids'] ?? array(), $rule['operator'], $rule['value'] ),
			'cart_subtotal'                 => $this->compare_number( $context['cart_subtotal'] ?? 0, $rule['operator'], $rule['value'] ),
			'viewed_product'                => $this->compare_list( array( $context['viewed_product_id'] ?? 0 ), $rule['operator'], $rule['value'] ),
			'user_role'                     => $this->compare_list( $context['user_roles'] ?? array(), $rule['operator'], $rule['value'], false ),
			'customer_order_count'          => $this->compare_number( $context['customer_order_count'] ?? 0, $rule['operator'], $rule['value'] ),
			'customer_lifetime_spend'       => $this->compare_number( $context['customer_lifetime_spend'] ?? 0, $rule['operator'], $rule['value'] ),
			'stock_status'                  => $this->compare_scalar( (string) ( $context['stock_status'] ?? '' ), $rule['operator'], (string) $rule['value'] ),
			'exclude_if_product_in_cart'    => ! $this->compare_list( $context['cart_product_ids'] ?? array(), 'in', $rule['value'] ),
			default                         => false,
		};

		/**
		 * Filter the result for a single normalized rule.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                 $result  Rule match result.
		 * @param array<string, mixed> $rule    Normalized rule.
		 * @param array<string, mixed> $context Rule context.
		 */
		return (bool) Hooks::filter( 'rule_result', $result, $rule, $context );
	}

	/**
	 * Compare list values.
	 *
	 * @param mixed  $actual   Actual list.
	 * @param string $operator Operator.
	 * @param mixed  $expected Expected list.
	 * @param bool   $use_numeric Whether values are numeric.
	 */
	private function compare_list( $actual, string $operator, $expected, bool $use_numeric = true ): bool {
		$actual_values   = $this->list_values( $actual, $use_numeric );
		$expected_values = $this->list_values( $expected, $use_numeric );
		$intersect       = array_intersect( $actual_values, $expected_values );

		return match ( $operator ) {
			'in', 'contains', 'eq' => array() !== $intersect,
			'not_in', 'neq'       => array() === $intersect,
			default               => false,
		};
	}

	/**
	 * Compare scalar values.
	 *
	 * @param string $actual   Actual.
	 * @param string $operator Operator.
	 * @param string $expected Expected.
	 */
	private function compare_scalar( string $actual, string $operator, string $expected ): bool {
		return match ( $operator ) {
			'eq', 'in'       => $actual === $expected,
			'neq', 'not_in'  => $actual !== $expected,
			default          => false,
		};
	}

	/**
	 * Compare numeric values.
	 *
	 * @param mixed  $actual   Actual.
	 * @param string $operator Operator.
	 * @param mixed  $expected Expected.
	 */
	private function compare_number( $actual, string $operator, $expected ): bool {
		$actual_number   = (float) $actual;
		$expected_number = (float) $expected;

		return match ( $operator ) {
			'eq'  => $actual_number === $expected_number,
			'neq' => $actual_number !== $expected_number,
			'gt'  => $actual_number > $expected_number,
			'gte' => $actual_number >= $expected_number,
			'lt'  => $actual_number < $expected_number,
			'lte' => $actual_number <= $expected_number,
			default => false,
		};
	}

	/**
	 * Normalize values to a list.
	 *
	 * @param mixed $value   Value.
	 * @param bool  $use_numeric Whether values are numeric.
	 * @return array<int, int|string>
	 */
	private function list_values( $value, bool $use_numeric ): array {
		$values = is_array( $value ) ? $value : array( $value );

		return array_values(
			array_filter(
				array_map(
					static fn ( $item ) => $use_numeric ? (int) $item : (string) $item,
					$values
				),
				static fn ( $item ): bool => $use_numeric ? $item > 0 : '' !== $item
			)
		);
	}
}
