<?php
/**
 * Supported offer rule definitions.
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
 * Defines the rule types, operators, and value contracts shared by admin and runtime.
 *
 * @since 1.0.0
 */
final class RuleDefinitions {
	public const VALUE_PRODUCTS     = 'products';
	public const VALUE_CATEGORIES   = 'categories';
	public const VALUE_TAGS         = 'tags';
	public const VALUE_ROLES        = 'roles';
	public const VALUE_NUMBER       = 'number';
	public const VALUE_STOCK_STATUS = 'stock_status';

	private const DEFINITIONS = array(
		'cart_product'               => array(
			'label'            => 'Cart contains product',
			'value_type'       => self::VALUE_PRODUCTS,
			'operators'        => array( 'contains', 'not_in' ),
			'default_operator' => 'contains',
			'multiple'         => true,
			'operator_visible' => true,
		),
		'cart_category'              => array(
			'label'            => 'Cart contains category',
			'value_type'       => self::VALUE_CATEGORIES,
			'operators'        => array( 'contains' ),
			'default_operator' => 'contains',
			'multiple'         => true,
			'operator_visible' => false,
		),
		'cart_tag'                   => array(
			'label'            => 'Cart contains tag',
			'value_type'       => self::VALUE_TAGS,
			'operators'        => array( 'contains' ),
			'default_operator' => 'contains',
			'multiple'         => true,
			'operator_visible' => false,
		),
		'cart_subtotal'              => array(
			'label'            => 'Cart subtotal is',
			'value_type'       => self::VALUE_NUMBER,
			'operators'        => array( 'gt', 'gte', 'lt', 'lte', 'eq', 'neq' ),
			'default_operator' => 'gte',
			'multiple'         => false,
			'operator_visible' => true,
			'min'              => 0,
			'step'             => '0.01',
		),
		'viewed_product'             => array(
			'label'            => 'Currently viewing product',
			'value_type'       => self::VALUE_PRODUCTS,
			'operators'        => array( 'contains' ),
			'default_operator' => 'contains',
			'multiple'         => true,
			'operator_visible' => false,
		),
		'user_role'                  => array(
			'label'            => 'User role is',
			'value_type'       => self::VALUE_ROLES,
			'operators'        => array( 'contains' ),
			'default_operator' => 'contains',
			'multiple'         => true,
			'operator_visible' => false,
		),
		'customer_order_count'       => array(
			'label'            => 'Customer order count is',
			'value_type'       => self::VALUE_NUMBER,
			'operators'        => array( 'gt', 'gte', 'lt', 'lte', 'eq', 'neq' ),
			'default_operator' => 'gte',
			'multiple'         => false,
			'operator_visible' => true,
			'min'              => 0,
			'step'             => '1',
		),
		'customer_lifetime_spend'    => array(
			'label'            => 'Customer lifetime spend is',
			'value_type'       => self::VALUE_NUMBER,
			'operators'        => array( 'gt', 'gte', 'lt', 'lte', 'eq', 'neq' ),
			'default_operator' => 'gte',
			'multiple'         => false,
			'operator_visible' => true,
			'min'              => 0,
			'step'             => '0.01',
		),
		'stock_status'               => array(
			'label'            => 'Offered product stock status is',
			'value_type'       => self::VALUE_STOCK_STATUS,
			'operators'        => array( 'eq' ),
			'default_operator' => 'eq',
			'multiple'         => false,
			'operator_visible' => false,
		),
		'exclude_if_product_in_cart' => array(
			'label'            => 'Exclude if cart contains product',
			'value_type'       => self::VALUE_PRODUCTS,
			'operators'        => array( 'not_in' ),
			'default_operator' => 'not_in',
			'multiple'         => true,
			'operator_visible' => false,
		),
	);

	/**
	 * Return all supported rule definitions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return self::DEFINITIONS;
	}

	/**
	 * Return a single rule definition.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Rule type.
	 * @return array<string, mixed>|null
	 */
	public function get( string $type ): ?array {
		return self::DEFINITIONS[ $type ] ?? null;
	}

	/**
	 * Return supported canonical rule types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function types(): array {
		return array_keys( self::DEFINITIONS );
	}

	/**
	 * Return supported stock statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function stock_statuses(): array {
		return array(
			'instock'     => function_exists( '__' ) ? __( 'In stock', 'upsellbay' ) : 'In stock',
			'outofstock'  => function_exists( '__' ) ? __( 'Out of stock', 'upsellbay' ) : 'Out of stock',
			'onbackorder' => function_exists( '__' ) ? __( 'On backorder', 'upsellbay' ) : 'On backorder',
		);
	}

	/**
	 * Normalize rule aliases to canonical runtime keys.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Raw rule type.
	 */
	public function normalize_type( string $type ): string {
		return match ( $type ) {
			'lifetime_spend'          => 'customer_lifetime_spend',
			'exclude_product_in_cart' => 'exclude_if_product_in_cart',
			default                   => $type,
		};
	}

	/**
	 * Normalize operator aliases to canonical runtime keys.
	 *
	 * @since 1.0.0
	 *
	 * @param string $operator Raw operator.
	 */
	public function normalize_operator( string $operator ): string {
		return match ( $operator ) {
			'is'              => 'eq',
			'is_not'          => 'neq',
			'in'              => 'contains',
			'not_contains'    => 'not_in',
			'greater_than'    => 'gt',
			'greater_or_equal' => 'gte',
			'>='              => 'gte',
			'>'               => 'gt',
			'less_than'       => 'lt',
			'less_or_equal'   => 'lte',
			'<='              => 'lte',
			'<'               => 'lt',
			default           => $operator,
		};
	}

	/**
	 * Return a valid operator for a rule type or null when unsupported.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type     Rule type.
	 * @param string $operator Requested operator.
	 */
	public function resolve_operator( string $type, string $operator ): ?string {
		$definition = $this->get( $type );
		if ( null === $definition ) {
			return null;
		}

		$operator = $this->normalize_operator( $operator );
		if ( 'eq' === $operator && in_array( 'contains', $definition['operators'], true ) ) {
			$operator = 'contains';
		}
		if ( 'neq' === $operator && in_array( 'not_in', $definition['operators'], true ) ) {
			$operator = 'not_in';
		}
		if ( ! in_array( $operator, $definition['operators'], true ) ) {
			return null;
		}

		return $operator;
	}

	/**
	 * Return a rule type's default operator.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Rule type.
	 */
	public function default_operator( string $type ): string {
		$definition = $this->get( $type );
		return is_array( $definition ) ? (string) $definition['default_operator'] : '';
	}

	/**
	 * Return admin-facing definitions for JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function admin_config(): array {
		$rules = array();
		foreach ( self::DEFINITIONS as $type => $definition ) {
			$operators = array();
			foreach ( $definition['operators'] as $operator ) {
				$operators[] = array(
					'value' => $operator,
					'label' => $this->operator_label( $operator ),
				);
			}

			$rules[ $type ] = array(
				'label'           => $this->rule_label( $type ),
				'valueType'       => $definition['value_type'],
				'operators'       => $operators,
				'defaultOperator' => $definition['default_operator'],
				'multiple'        => $definition['multiple'],
				'operatorVisible' => $definition['operator_visible'],
				'min'             => $definition['min'] ?? null,
				'step'            => $definition['step'] ?? null,
			);
		}

		return array(
			'rules'         => $rules,
			'stockStatuses' => array_map(
				static fn ( string $label, string $value ): array => array(
					'value' => $value,
					'label' => $label,
				),
				$this->stock_statuses(),
				array_keys( $this->stock_statuses() )
			),
		);
	}

	/**
	 * Return an operator label.
	 *
	 * @param string $operator Operator key.
	 */
	private function operator_label( string $operator ): string {
		return match ( $operator ) {
			'contains' => function_exists( '__' ) ? __( 'Contains', 'upsellbay' ) : 'Contains',
			'not_in'   => function_exists( '__' ) ? __( 'Does not contain', 'upsellbay' ) : 'Does not contain',
			'eq'       => function_exists( '__' ) ? __( 'Equals', 'upsellbay' ) : 'Equals',
			'neq'      => function_exists( '__' ) ? __( 'Does not equal', 'upsellbay' ) : 'Does not equal',
			'gt'       => function_exists( '__' ) ? __( 'Greater than', 'upsellbay' ) : 'Greater than',
			'gte'      => function_exists( '__' ) ? __( 'Greater than or equal to', 'upsellbay' ) : 'Greater than or equal to',
			'lt'       => function_exists( '__' ) ? __( 'Less than', 'upsellbay' ) : 'Less than',
			'lte'      => function_exists( '__' ) ? __( 'Less than or equal to', 'upsellbay' ) : 'Less than or equal to',
			default    => $operator,
		};
	}

	/**
	 * Return a rule label.
	 *
	 * @param string $type Rule type.
	 */
	private function rule_label( string $type ): string {
		return match ( $type ) {
			'cart_product'               => function_exists( '__' ) ? __( 'Cart contains product', 'upsellbay' ) : 'Cart contains product',
			'cart_category'              => function_exists( '__' ) ? __( 'Cart contains category', 'upsellbay' ) : 'Cart contains category',
			'cart_tag'                   => function_exists( '__' ) ? __( 'Cart contains tag', 'upsellbay' ) : 'Cart contains tag',
			'cart_subtotal'              => function_exists( '__' ) ? __( 'Cart subtotal is', 'upsellbay' ) : 'Cart subtotal is',
			'viewed_product'             => function_exists( '__' ) ? __( 'Currently viewing product', 'upsellbay' ) : 'Currently viewing product',
			'user_role'                  => function_exists( '__' ) ? __( 'User role is', 'upsellbay' ) : 'User role is',
			'customer_order_count'       => function_exists( '__' ) ? __( 'Customer order count is', 'upsellbay' ) : 'Customer order count is',
			'customer_lifetime_spend'    => function_exists( '__' ) ? __( 'Customer lifetime spend is', 'upsellbay' ) : 'Customer lifetime spend is',
			'stock_status'               => function_exists( '__' ) ? __( 'Offered product stock status is', 'upsellbay' ) : 'Offered product stock status is',
			'exclude_if_product_in_cart' => function_exists( '__' ) ? __( 'Exclude if cart contains product', 'upsellbay' ) : 'Exclude if cart contains product',
			default                      => $type,
		};
	}
}
