<?php
/**
 * Offer schema validator.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferSchema   $schema Product schema.
	 * @param callable|null $product_exists Optional product existence callback.
	 */
	public function __construct( OfferSchema $schema, ?callable $product_exists = null ) {
		$this->schema         = $schema;
		$this->product_exists = $product_exists ?? static function ( int $product_id ): bool {
			if ( $product_id <= 0 ) {
				return false;
			}

			return ! function_exists( 'wc_get_product' ) || false !== wc_get_product( $product_id );
		};
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

		if ( $normalized['_ub_offer_product_id'] <= 0 || ! ( $this->product_exists )( $normalized['_ub_offer_product_id'] ) ) {
			$errors['_ub_offer_product_id'] = 'Offer product does not exist.';
		}

		if ( ! in_array( $normalized['_ub_discount_type'], $this->schema->discount_types(), true ) ) {
			$errors['_ub_discount_type'] = 'Invalid discount type.';
		}

		if ( (float) $normalized['_ub_discount_value'] < 0 ) {
			$errors['_ub_discount_value'] = 'Discount value cannot be negative.';
		}

		if ( '' === $normalized['_ub_headline'] ) {
			$errors['_ub_headline'] = 'Headline is required.';
		}

		if ( '' === $normalized['_ub_button_text'] ) {
			$errors['_ub_button_text'] = 'Button text is required.';
		}

		if ( null !== $normalized['_ub_start_at'] && null !== $normalized['_ub_end_at'] && $normalized['_ub_start_at'] > $normalized['_ub_end_at'] ) {
			$errors['_ub_end_at'] = 'End date must be after start date.';
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

		$normalized['_ub_offer_type']           = $this->sanitize_key( $normalized['_ub_offer_type'] );
		$normalized['_ub_status']               = $this->sanitize_key( $normalized['_ub_status'] );
		$normalized['_ub_offer_product_id']     = max( 0, (int) $normalized['_ub_offer_product_id'] );
		$normalized['_ub_trigger_product_ids']  = $this->int_list( $normalized['_ub_trigger_product_ids'] );
		$normalized['_ub_trigger_category_ids'] = $this->int_list( $normalized['_ub_trigger_category_ids'] );
		$normalized['_ub_discount_type']        = $this->sanitize_key( $normalized['_ub_discount_type'] );
		$normalized['_ub_discount_value']       = number_format( max( 0, (float) $normalized['_ub_discount_value'] ), 6, '.', '' );
		$normalized['_ub_headline']             = substr( $this->sanitize_text( $normalized['_ub_headline'] ), 0, 80 );
		$normalized['_ub_body']                 = substr( $this->sanitize_html( $normalized['_ub_body'] ), 0, 240 );
		$normalized['_ub_button_text']          = substr( $this->sanitize_text( $normalized['_ub_button_text'] ), 0, 40 );
		$normalized['_ub_rules']                = is_array( $normalized['_ub_rules'] ) ? $normalized['_ub_rules'] : array();
		$normalized['_ub_rules_match']          = in_array( $normalized['_ub_rules_match'], array( 'all', 'any' ), true ) ? $normalized['_ub_rules_match'] : 'all';
		$normalized['_ub_placement_config']     = is_array( $normalized['_ub_placement_config'] ) ? $normalized['_ub_placement_config'] : array();
		$normalized['_ub_show_image']           = $this->to_bool( $normalized['_ub_show_image'] );
		$normalized['_ub_start_at']             = $this->normalize_datetime( $normalized['_ub_start_at'] );
		$normalized['_ub_end_at']               = $this->normalize_datetime( $normalized['_ub_end_at'] );
		$normalized['_ub_priority']             = (int) $normalized['_ub_priority'];

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
}
