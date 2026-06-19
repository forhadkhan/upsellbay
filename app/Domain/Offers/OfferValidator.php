<?php
/**
 * Offer schema validator.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Sanitizes, normalizes, and validates offer configuration metadata.
 *
 * @since 1.0.0
 */
final class OfferValidator {
	/**
	 * Offer schema.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferSchema
	 */
	private OfferSchema $schema;

	/**
	 * Product existence callback.
	 *
	 * @var callable(int): bool
	 */
	private $product_exists;

	/**
	 * Product context callback.
	 *
	 * @var callable(int): array<string, mixed>|null
	 */
	private $product_context;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferSchema   $schema Product schema.
	 * @param callable|null $product_exists Optional product existence callback.
	 * @param callable|null $product_context Optional product context callback.
	 */
	public function __construct( OfferSchema $schema, ?callable $product_exists = null, ?callable $product_context = null ) {
		$this->schema          = $schema;
		$this->product_exists  = $product_exists ?? static function ( int $product_id ): bool {
			if ( $product_id <= 0 ) {
				return false;
			}

			return ! function_exists( 'wc_get_product' ) || false !== wc_get_product( $product_id );
		};
		$this->product_context = $product_context ?? array( $this, 'load_product_context' );
	}

	/**
	 * Validate raw meta.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta Raw meta.
	 */
	public function validate( array $meta ): ValidationResult {
		$normalized = $this->normalize( $meta );
		$errors     = array();

		if ( ! in_array( $normalized['_ub_offer_type'], $this->schema->offer_types(), true ) ) {
			$errors['_ub_offer_type'] = 'Invalid offer type.';
		}

		if ( ! in_array( $normalized['_ub_status'], $this->schema->statuses(), true ) ) {
			$errors['_ub_status'] = 'Invalid offer status.';
		}

		if ( ! in_array( $normalized['_ub_discount_type'], $this->schema->discount_types(), true ) ) {
			$errors['_ub_discount_type'] = 'Invalid discount type.';
		}

		if ( (float) $normalized['_ub_discount_value'] < 0 ) {
			$errors['_ub_discount_value'] = 'Discount value cannot be negative.';
		}

		if ( null !== $normalized['_ub_start_at'] && null !== $normalized['_ub_end_at'] && $normalized['_ub_start_at'] > $normalized['_ub_end_at'] ) {
			$errors['_ub_end_at'] = 'End date must be after start date.';
		}

		if ( 'active' === $normalized['_ub_status'] ) {
			$errors = array_replace( $errors, $this->validate_active_offer( $normalized ) );
		}

		return new ValidationResult( array() === $errors, $errors, $normalized );
	}

	/**
	 * Normalize raw meta into the schema shape.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta Raw meta.
	 * @return array<string, mixed>
	 */
	public function normalize( array $meta ): array {
		$normalized = array_replace( $this->schema->defaults(), $meta );

		$normalized['_ub_offer_type']               = $this->sanitize_key( $normalized['_ub_offer_type'] );
		$normalized['_ub_status']                   = $this->sanitize_key( $normalized['_ub_status'] );
		$normalized['_ub_offer_product_id']         = max( 0, (int) $normalized['_ub_offer_product_id'] );
		$normalized['_ub_trigger_product_ids']      = $this->int_list( $normalized['_ub_trigger_product_ids'] );
		$normalized['_ub_trigger_category_ids']     = $this->int_list( $normalized['_ub_trigger_category_ids'] );
		$normalized['_ub_discount_type']            = $this->sanitize_key( $normalized['_ub_discount_type'] );
		$normalized['_ub_discount_value']           = number_format( max( 0, (float) $normalized['_ub_discount_value'] ), 6, '.', '' );
		$normalized['_ub_offer_goal']               = in_array( $normalized['_ub_offer_goal'], array( 'add_on', 'upgrade', 'protection', 'threshold_helper', 'follow_on' ), true ) ? $normalized['_ub_offer_goal'] : 'add_on';
		$normalized['_ub_reason_label']             = substr( $this->sanitize_text( $normalized['_ub_reason_label'] ), 0, 80 );
		$normalized['_ub_conflict_override']        = $this->to_bool( $normalized['_ub_conflict_override'] );
		$normalized['_ub_conflict_override_reason'] = substr( $this->sanitize_text( $normalized['_ub_conflict_override_reason'] ), 0, 240 );
		$normalized['_ub_headline']                 = substr( $this->sanitize_text( $normalized['_ub_headline'] ), 0, 80 );
		$normalized['_ub_body']                     = substr( $this->sanitize_html( $normalized['_ub_body'] ), 0, 240 );
		$normalized['_ub_button_text']              = substr( $this->sanitize_text( $normalized['_ub_button_text'] ), 0, 40 );
		$normalized['_ub_rules']                    = is_array( $normalized['_ub_rules'] ) ? $normalized['_ub_rules'] : array();
		$normalized['_ub_rules_match']              = in_array( $normalized['_ub_rules_match'], array( 'all', 'any' ), true ) ? $normalized['_ub_rules_match'] : 'all';
		$normalized['_ub_placement_config']         = is_array( $normalized['_ub_placement_config'] ) ? $normalized['_ub_placement_config'] : array();
		$normalized['_ub_show_image']               = $this->to_bool( $normalized['_ub_show_image'] );
		$normalized['_ub_start_at']                 = $this->normalize_datetime( $normalized['_ub_start_at'] );
		$normalized['_ub_end_at']                   = $this->normalize_datetime( $normalized['_ub_end_at'] );
		$normalized['_ub_priority']                 = (int) $normalized['_ub_priority'];

		return $normalized;
	}

	/**
	 * Normalize integer lists.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, int>
	 */
	private function int_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();
		foreach ( $value as $item ) {
			$id = (int) $item;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize a key.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_key( $value ): string {
		return function_exists( 'sanitize_key' ) ? sanitize_key( (string) $value ) : strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ?? '' );
	}

	/**
	 * Sanitize text.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_text( $value ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $value ) : trim( strip_tags( (string) $value ) );
	}

	/**
	 * Sanitize limited HTML.
	 *
	 * @param mixed $value Raw value.
	 */
	private function sanitize_html( $value ): string {
		if ( function_exists( 'wp_kses' ) ) {
			return wp_kses(
				(string) $value,
				array(
					'a'      => array(
						'href'  => true,
						'title' => true,
					),
					'br'     => array(),
					'em'     => array(),
					'strong' => array(),
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		return strip_tags( (string) $value, '<a><br><em><strong>' );
	}

	/**
	 * Normalize a datetime string.
	 *
	 * @param mixed $value Raw value.
	 */
	private function normalize_datetime( $value ): ?string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Cast common checkbox values.
	 *
	 * @param mixed $value Raw value.
	 */
	private function to_bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true );
	}

	/**
	 * Validate fields required for a live storefront offer.
	 *
	 * @param array<string, mixed> $meta Normalized offer meta.
	 * @return array<string, string>
	 */
	private function validate_active_offer( array $meta ): array {
		$errors     = array();
		$product_id = (int) $meta['_ub_offer_product_id'];

		if ( $product_id <= 0 ) {
			$errors['_ub_offer_product_id'] = 'Offer product is required.';
		} elseif ( ! ( $this->product_exists )( $product_id ) ) {
			$errors['_ub_offer_product_id'] = 'Offer product does not exist.';
		}

		if ( '' === $meta['_ub_headline'] ) {
			$errors['_ub_headline'] = 'Headline is required.';
		}

		if ( '' === $meta['_ub_button_text'] ) {
			$errors['_ub_button_text'] = 'Button text is required.';
		}

		$product = $product_id > 0 ? ( $this->product_context )( $product_id ) : null;
		if ( is_array( $product ) ) {
			if ( false === ( $product['purchasable'] ?? true ) ) {
				$errors['_ub_offer_product_id_purchasable'] = 'Offer product is not currently purchasable.';
			}
			if ( false === ( $product['in_stock'] ?? true ) ) {
				$errors['_ub_offer_product_id_stock'] = 'Offer product is out of stock.';
			}
			if ( false === ( $product['visible'] ?? true ) ) {
				$errors['_ub_offer_product_id_visible'] = 'Offer product is not visible in the catalog.';
			}

			$product_type = (string) ( $product['type'] ?? 'simple' );
			if ( in_array( $meta['_ub_offer_type'], array( OfferSchema::TYPE_CHECKOUT_BUMP, OfferSchema::TYPE_THANKYOU_OFFER ), true ) && ! in_array( $product_type, array( 'simple', 'variation' ), true ) ) {
				$errors['_ub_offer_product_id_type'] = 'Checkout and thank-you offers require a simple product or variation.';
			}

			if ( true === ( $product['subscription'] ?? false ) && 'none' !== $meta['_ub_discount_type'] ) {
				$errors['_ub_discount_type_subscription'] = 'Subscription products cannot receive UpsellBay discounts.';
			}
		}

		$discount_value = (float) $meta['_ub_discount_value'];
		if ( 'none' !== $meta['_ub_discount_type'] && $discount_value <= 0 ) {
			$errors['_ub_discount_value_required'] = 'Discount value is required when a discount type is selected.';
		}
		if ( 'percent' === $meta['_ub_discount_type'] && $discount_value > 100 ) {
			$errors['_ub_discount_value_percent'] = 'Percentage discount cannot be greater than 100.';
		}

		$rule_errors = $this->validate_rules( $meta['_ub_rules'] );
		foreach ( $rule_errors as $index => $message ) {
			$errors[ '_ub_rules_' . $index ] = $message;
		}

		$position_error = $this->validate_placement_position( (string) $meta['_ub_offer_type'], $meta['_ub_placement_config'] );
		if ( '' !== $position_error ) {
			$errors['_ub_placement_config'] = $position_error;
		}

		if ( true === $meta['_ub_conflict_override'] && '' === $meta['_ub_conflict_override_reason'] ) {
			$errors['_ub_conflict_override_reason'] = 'Conflict override requires a reason.';
		}

		return $errors;
	}

	/**
	 * Validate normalized targeting rules.
	 *
	 * @param mixed $rules Rules.
	 * @return array<int, string>
	 */
	private function validate_rules( $rules ): array {
		if ( ! is_array( $rules ) ) {
			return array( 'Rules must be an array.' );
		}

		$errors = array();
		foreach ( $rules as $index => $rule ) {
			if ( ! is_array( $rule ) ) {
				$errors[ $index ] = 'Each rule must be an object.';
				continue;
			}

			$type     = $this->sanitize_key( $rule['type'] ?? '' );
			$operator = $this->sanitize_key( $rule['operator'] ?? 'eq' );
			$value    = $rule['value'] ?? null;

			if ( ! in_array( $type, array( 'cart_product', 'cart_category', 'cart_tag', 'cart_subtotal', 'viewed_product', 'user_role', 'customer_order_count', 'customer_lifetime_spend', 'stock_status', 'exclude_if_product_in_cart' ), true ) ) {
				$errors[ $index ] = 'Rule type is not supported.';
				continue;
			}

			if ( in_array( $type, array( 'cart_subtotal', 'customer_order_count', 'customer_lifetime_spend' ), true ) ) {
				if ( ! in_array( $operator, array( 'eq', 'neq', 'gt', 'gte', 'lt', 'lte' ), true ) ) {
					$errors[ $index ] = 'Cart subtotal rules require a numeric comparison operator.';
				} elseif ( ! is_numeric( $value ) ) {
					$errors[ $index ] = 'Numeric rules require a numeric value.';
				}
				continue;
			}

			if ( 'stock_status' === $type ) {
				if ( ! in_array( (string) $value, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
					$errors[ $index ] = 'Stock status rule requires a supported stock status.';
				}
				continue;
			}

			if ( ! in_array( $operator, array( 'eq', 'neq', 'in', 'not_in', 'contains' ), true ) ) {
				$errors[ $index ] = 'Rule operator is not supported for this rule type.';
				continue;
			}

			if ( array() === $this->rule_value_list( $value, 'user_role' !== $type ) ) {
				$errors[ $index ] = 'Rule value is required.';
			}
		}

		return $errors;
	}

	/**
	 * Normalize rule values to a non-empty list.
	 *
	 * @param mixed $value Rule value.
	 * @param bool  $require_integer Whether values must be numeric.
	 * @return array<int, int|string>
	 */
	private function rule_value_list( $value, bool $require_integer ): array {
		$values = is_array( $value ) ? $value : array( $value );
		$list   = array();
		foreach ( $values as $item ) {
			if ( $require_integer ) {
				$item = (int) $item;
				if ( $item > 0 ) {
					$list[] = $item;
				}
			} else {
				$item = trim( (string) $item );
				if ( '' !== $item ) {
					$list[] = $item;
				}
			}
		}
		return $list;
	}

	/**
	 * Validate placement display metadata against the offer placement.
	 *
	 * @param string $offer_type Offer type.
	 * @param mixed  $config Placement config.
	 */
	private function validate_placement_position( string $offer_type, $config ): string {
		if ( ! is_array( $config ) || ! isset( $config['position'] ) || '' === (string) $config['position'] ) {
			return '';
		}

		$expected = array(
			OfferSchema::TYPE_CHECKOUT_BUMP  => 'before_submit',
			OfferSchema::TYPE_PRODUCT_UPSELL => 'after_add_to_cart',
			OfferSchema::TYPE_CART_CROSSSELL => 'after_cart_table',
			OfferSchema::TYPE_THANKYOU_OFFER => 'order_received_actions',
		);

		if ( isset( $expected[ $offer_type ] ) && (string) $config['position'] !== $expected[ $offer_type ] ) {
			$messages = array(
				OfferSchema::TYPE_CHECKOUT_BUMP  => 'Checkout bump display position must use the checkout bump area.',
				OfferSchema::TYPE_PRODUCT_UPSELL => 'Product page offer display position must use the product page area.',
				OfferSchema::TYPE_CART_CROSSSELL => 'Cart offer display position must use the cart offer area.',
				OfferSchema::TYPE_THANKYOU_OFFER => 'Thank-you offer display position must use the thank-you follow-on area.',
			);

			return $messages[ $offer_type ];
		}

		return '';
	}

	/**
	 * Load WooCommerce product state when WooCommerce is available.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	private function load_product_context( int $product_id ): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! is_object( $product ) ) {
			return null;
		}

		return array(
			'purchasable'  => ! method_exists( $product, 'is_purchasable' ) || $product->is_purchasable(),
			'visible'      => ! method_exists( $product, 'is_visible' ) || $product->is_visible(),
			'in_stock'     => ! method_exists( $product, 'is_in_stock' ) || $product->is_in_stock(),
			'type'         => method_exists( $product, 'get_type' ) ? $product->get_type() : 'simple',
			'subscription' => function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $product ),
		);
	}
}
