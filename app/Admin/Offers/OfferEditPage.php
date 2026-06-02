<?php
/**
 * Offer editor admin page.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use Throwable;

/**
 * Handles offer editor forms and native admin notices.
 *
 * @since 1.0.0
 */
final class OfferEditPage {
	/**
	 * Offer service.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferService
	 */
	private OfferService $service;

	/**
	 * Offer validator.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferValidator
	 */
	private OfferValidator $validator;

	/**
	 * Offer defaults.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferDefaults
	 */
	private OfferDefaults $defaults;

	/**
	 * Capability callback.
	 *
	 * @var callable(): bool
	 */
	private $can_manage;

	/**
	 * Nonce verifier.
	 *
	 * @var callable(string): bool
	 */
	private $verify_nonce;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferService       $service      Offer service.
	 * @param OfferValidator     $validator    Offer validator.
	 * @param callable|null      $can_manage   Capability callback.
	 * @param callable|null      $verify_nonce Nonce callback.
	 * @param OfferDefaults|null $defaults Offer defaults.
	 */
	public function __construct( OfferService $service, OfferValidator $validator, ?callable $can_manage = null, ?callable $verify_nonce = null, ?OfferDefaults $defaults = null ) {
		$this->service      = $service;
		$this->validator    = $validator;
		$this->defaults     = $defaults ?? new OfferDefaults();
		$this->can_manage   = $can_manage ?? static fn (): bool => function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		$this->verify_nonce = $verify_nonce ?? static fn ( string $nonce ): bool => function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, 'upsellbay_save_offer' );
	}

	/**
	 * Save submitted offer data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array{success: bool, message: string, offer_id?: int}
	 */
	public function save( array $request ): array {
		if ( ! ( $this->can_manage )() ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage UpsellBay offers.', 'upsellbay' ),
			);
		}

		if ( ! ( $this->verify_nonce )( (string) ( $request['nonce'] ?? '' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Offer save could not be verified. Please try again.', 'upsellbay' ),
			);
		}

		$title = $this->sanitize_text( (string) ( $request['title'] ?? '' ) );
		$meta  = $this->submitted_meta( $request );
		$valid = $this->validator->validate( $meta );

		if ( ! $valid->is_valid() ) {
			return array(
				'success' => false,
				'message' => implode( ' ', $valid->errors() ),
			);
		}

		try {
			$offer_id = isset( $request['offer_id'] ) ? (int) $request['offer_id'] : 0;
			if ( $offer_id > 0 ) {
				$this->service->update(
					$offer_id,
					array(
						'title' => $title,
						'meta'  => $valid->data(),
					)
				);
			} else {
				$offer_id = $this->service->create(
					array(
						'title' => $title,
						'meta'  => $valid->data(),
					)
				);
			}
		} catch ( Throwable $throwable ) {
			return array(
				'success' => false,
				'message' => $throwable->getMessage(),
			);
		}

		return array(
			'success'  => true,
			'message'  => __( 'Offer saved.', 'upsellbay' ),
			'offer_id' => $offer_id,
		);
	}

	/**
	 * Normalize rule rows submitted by the editor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rules Raw rules.
	 * @return array<int, array<string, mixed>>
	 */
	public function normalize_rules( array $rules ): array {
		$allowed_types = array( 'cart_product', 'cart_category', 'cart_tag', 'cart_subtotal', 'viewed_product', 'user_role', 'customer_order_count', 'lifetime_spend', 'stock_status', 'exclude_product_in_cart' );
		$normalized    = array();

		foreach ( $rules as $rule ) {
			$type = $this->sanitize_key( (string) ( $rule['type'] ?? '' ) );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			$normalized[] = array(
				'type'     => $type,
				'operator' => $this->sanitize_key( (string) ( $rule['operator'] ?? 'is' ) ),
				'value'    => is_array( $rule['value'] ?? null ) ? array_map( array( $this, 'positive_int' ), $rule['value'] ) : $this->sanitize_text( (string) ( $rule['value'] ?? '' ) ),
			);
		}

		return $normalized;
	}

	/**
	 * Return progressive editor sections.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, collapsed: bool, fields: array<int, string>}>
	 */
	public function sections(): array {
		return array(
			'basics'    => array(
				'label'     => __( 'Required basics', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( 'title', '_ub_offer_type', '_ub_offer_product_id', '_ub_headline', '_ub_button_text' ),
			),
			'targeting' => array(
				'label'     => __( 'Targeting rules', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_rules_match', '_ub_rules' ),
			),
			'discount'  => array(
				'label'     => __( 'Discount', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_discount_type', '_ub_discount_value' ),
			),
			'placement' => array(
				'label'     => __( 'Placement display', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_show_image', '_ub_placement_config' ),
			),
			'schedule'  => array(
				'label'     => __( 'Schedule and priority', 'upsellbay' ),
				'collapsed' => true,
				'fields'    => array( '_ub_start_at', '_ub_end_at', '_ub_priority' ),
			),
			'advanced'  => array(
				'label'     => __( 'Advanced metadata', 'upsellbay' ),
				'collapsed' => true,
				'fields'    => array( '_ub_trigger_product_ids', '_ub_trigger_category_ids' ),
			),
		);
	}

	/**
	 * Return WooCommerce-native help-tip copy keyed by field group.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function help_tips(): array {
		return array(
			'product_selection'      => __( 'Choose the product shoppers can add from this offer. Product and stock are validated again before cart changes.', 'upsellbay' ),
			'rules'                  => __( 'Rules narrow when an offer appears. Leave them empty for a simple first offer.', 'upsellbay' ),
			'discount'               => __( 'Discounts are calculated server-side from the product price. Shopper-sent prices are ignored.', 'upsellbay' ),
			'display_limits'         => __( 'Priority controls which eligible offer wins when a placement has a display limit.', 'upsellbay' ),
			'test_mode'              => __( 'Test mode lets admins preview offers before shoppers can see them.', 'upsellbay' ),
			'compatibility_warnings' => __( 'Compatibility notices flag checkout plugins that may alter the placement area.', 'upsellbay' ),
		);
	}

	/**
	 * Return accessibility metadata for the editor controls.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function accessibility(): array {
		return array(
			'offer_product_id' => array(
				'label_for'        => 'upsellbay-offer-product-id',
				'aria_describedby' => 'upsellbay-offer-product-help',
			),
			'rules_table'      => array(
				'role'       => 'group',
				'aria_label' => __( 'Offer targeting rules', 'upsellbay' ),
			),
			'advanced_toggle'  => array(
				'role'          => 'button',
				'aria_expanded' => 'false',
			),
		);
	}

	/**
	 * Render editor shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		echo '<div class="wrap woocommerce upsellbay-admin upsellbay-offer-editor">';
		$this->render_content();
		echo '</div>';
	}

	/**
	 * Render editor tab content.
	 *
	 * @since 1.0.0
	 */
	public function render_content(): void {
		echo '<form method="post">';
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( 'upsellbay_save_offer', 'nonce' );
		}

		foreach ( $this->sections() as $section_id => $section ) {
			$classes = 'postbox upsellbay-offer-editor__section';
			if ( $section['collapsed'] ) {
				$classes .= ' closed';
			}
			echo '<div class="' . esc_attr( $classes ) . '" id="upsellbay-section-' . esc_attr( $section_id ) . '">';
			echo '<h2 class="hndle"><span>' . esc_html( $section['label'] ) . '</span></h2>';
			echo '<div class="inside"><table class="form-table" role="presentation"><tbody>';
			foreach ( $section['fields'] as $field ) {
				$this->render_field_row( $field );
			}
			echo '</tbody></table></div></div>';
		}

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save offer', 'upsellbay' ) . '</button> ';
		echo '<a class="button" href="' . esc_url( 'admin.php?page=upsellbay&tab=offers' ) . '">' . esc_html__( 'Back to offers', 'upsellbay' ) . '</a></p>';
		echo '</form>';
	}

	/**
	 * Render an editor field row.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field Field key.
	 */
	private function render_field_row( string $field ): void {
		$labels = array(
			'title'                    => __( 'Offer name', 'upsellbay' ),
			'_ub_offer_type'           => __( 'Placement', 'upsellbay' ),
			'_ub_offer_product_id'     => __( 'Offer product ID', 'upsellbay' ),
			'_ub_headline'             => __( 'Headline', 'upsellbay' ),
			'_ub_button_text'          => __( 'Button text', 'upsellbay' ),
			'_ub_rules_match'          => __( 'Rule matching', 'upsellbay' ),
			'_ub_rules'                => __( 'Rules', 'upsellbay' ),
			'_ub_discount_type'        => __( 'Discount type', 'upsellbay' ),
			'_ub_discount_value'       => __( 'Discount value', 'upsellbay' ),
			'_ub_show_image'           => __( 'Show product image', 'upsellbay' ),
			'_ub_placement_config'     => __( 'Placement options', 'upsellbay' ),
			'_ub_start_at'             => __( 'Start date', 'upsellbay' ),
			'_ub_end_at'               => __( 'End date', 'upsellbay' ),
			'_ub_priority'             => __( 'Priority', 'upsellbay' ),
			'_ub_trigger_product_ids'  => __( 'Trigger product IDs', 'upsellbay' ),
			'_ub_trigger_category_ids' => __( 'Trigger category IDs', 'upsellbay' ),
		);
		$label  = $labels[ $field ] ?? $field;

		echo '<tr><th scope="row"><label for="upsellbay-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';

		if ( '_ub_offer_type' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
			foreach (
				array(
					'checkout_bump'  => __( 'Checkout bump', 'upsellbay' ),
					'product_upsell' => __( 'Product page offer', 'upsellbay' ),
					'cart_crosssell' => __( 'Cart offer', 'upsellbay' ),
					'thankyou_offer' => __( 'Thank-you follow-on offer', 'upsellbay' ),
				) as $value => $option_label
			) {
				echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
		} elseif ( '_ub_rules_match' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '"><option value="all">' . esc_html__( 'All rules', 'upsellbay' ) . '</option><option value="any">' . esc_html__( 'Any rule', 'upsellbay' ) . '</option></select>';
		} elseif ( '_ub_discount_type' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '"><option value="none">' . esc_html__( 'No discount', 'upsellbay' ) . '</option><option value="percent">' . esc_html__( 'Percentage', 'upsellbay' ) . '</option><option value="fixed">' . esc_html__( 'Fixed amount', 'upsellbay' ) . '</option></select>';
		} elseif ( '_ub_show_image' === $field ) {
			echo '<label><input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="checkbox" value="1" checked="checked"> ' . esc_html__( 'Show the WooCommerce product image when available.', 'upsellbay' ) . '</label>';
		} elseif ( str_contains( $field, '_ids' ) || '_ub_offer_product_id' === $field || '_ub_priority' === $field ) {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="number" class="regular-text" min="0">';
		} elseif ( '_ub_rules' === $field || '_ub_placement_config' === $field ) {
			echo '<textarea id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" class="large-text code" rows="3"></textarea>';
		} elseif ( '_ub_start_at' === $field || '_ub_end_at' === $field ) {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="datetime-local" class="regular-text">';
		} else {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="text" class="regular-text">';
		}

		echo '</td></tr>';
	}

	/**
	 * Extract submitted offer meta.
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array<string, mixed>
	 */
	private function submitted_meta( array $request ): array {
		$offer_type = $this->sanitize_key( (string) ( $request['_ub_offer_type'] ?? 'checkout_bump' ) );
		$defaults   = $this->defaults->for_type( $offer_type );
		$show_image = array_key_exists( '_ub_show_image', $request )
			&& false !== $request['_ub_show_image']
			&& '' !== (string) $request['_ub_show_image'];

		return array_replace(
			$defaults,
			array(
				'_ub_offer_type'           => $offer_type,
				'_ub_status'               => $this->sanitize_key( (string) ( $request['_ub_status'] ?? $defaults['_ub_status'] ) ),
				'_ub_offer_product_id'     => (int) ( $request['_ub_offer_product_id'] ?? 0 ),
				'_ub_trigger_product_ids'  => array_map( array( $this, 'positive_int' ), is_array( $request['_ub_trigger_product_ids'] ?? null ) ? $request['_ub_trigger_product_ids'] : array() ),
				'_ub_trigger_category_ids' => array_map( array( $this, 'positive_int' ), is_array( $request['_ub_trigger_category_ids'] ?? null ) ? $request['_ub_trigger_category_ids'] : array() ),
				'_ub_discount_type'        => $this->sanitize_key( (string) ( $request['_ub_discount_type'] ?? $defaults['_ub_discount_type'] ) ),
				'_ub_discount_value'       => (string) ( $request['_ub_discount_value'] ?? $defaults['_ub_discount_value'] ),
				'_ub_headline'             => $this->sanitize_text( (string) ( $request['_ub_headline'] ?? $defaults['_ub_headline'] ) ),
				'_ub_body'                 => $this->sanitize_html( (string) ( $request['_ub_body'] ?? $defaults['_ub_body'] ) ),
				'_ub_button_text'          => $this->sanitize_text( (string) ( $request['_ub_button_text'] ?? $defaults['_ub_button_text'] ) ),
				'_ub_rules'                => $this->normalize_rules( is_array( $request['_ub_rules'] ?? null ) ? $request['_ub_rules'] : array() ),
				'_ub_rules_match'          => $this->sanitize_key( (string) ( $request['_ub_rules_match'] ?? 'all' ) ),
				'_ub_placement_config'     => is_array( $request['_ub_placement_config'] ?? null ) ? array_map( array( $this, 'sanitize_text' ), $request['_ub_placement_config'] ) : $defaults['_ub_placement_config'],
				'_ub_show_image'           => $show_image,
				'_ub_start_at'             => null,
				'_ub_end_at'               => null,
				'_ub_priority'             => (int) ( $request['_ub_priority'] ?? 0 ),
			)
		);
	}

	/**
	 * Sanitize text input.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}

		return trim( preg_replace( '/<[^>]*>/', '', $value ) ?? '' );
	}

	/**
	 * Sanitize a key input.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_key( string $value ): string {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}

	/**
	 * Sanitize limited HTML input.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_html( string $value ): string {
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $value ) ?? '';
	}

	/**
	 * Normalize a positive integer.
	 *
	 * @param mixed $value Raw value.
	 */
	private function positive_int( $value ): int {
		return max( 0, (int) $value );
	}
}
