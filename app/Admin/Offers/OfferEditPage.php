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
use WPAnchorBay\UpsellBay\Domain\Offers\OfferConflictDetector;
use WPAnchorBay\UpsellBay\Domain\Logging\LoggerInterface;
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
	 * Offers section navigation.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferSectionNavigation
	 */
	private OfferSectionNavigation $section_navigation;

	/**
	 * Offer conflict detector.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferConflictDetector|null
	 */
	private ?OfferConflictDetector $conflict_detector;

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
	 * Logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferService                $service            Offer service.
	 * @param OfferValidator              $validator          Offer validator.
	 * @param callable|null               $can_manage         Capability callback.
	 * @param callable|null               $verify_nonce       Nonce callback.
	 * @param OfferDefaults|null          $defaults           Offer defaults.
	 * @param OfferSectionNavigation|null $section_navigation Offers section navigation.
	 * @param OfferConflictDetector|null  $conflict_detector  Offer conflict detector.
	 * @param LoggerInterface|null        $logger             Logger instance.
	 */
	public function __construct( OfferService $service, OfferValidator $validator, ?callable $can_manage = null, ?callable $verify_nonce = null, ?OfferDefaults $defaults = null, ?OfferSectionNavigation $section_navigation = null, ?OfferConflictDetector $conflict_detector = null, ?LoggerInterface $logger = null ) {
		$this->service            = $service;
		$this->validator          = $validator;
		$this->defaults           = $defaults ?? new OfferDefaults();
		$this->section_navigation = $section_navigation ?? new OfferSectionNavigation();
		$this->conflict_detector  = $conflict_detector;
		$this->logger             = $logger;
		$this->can_manage         = $can_manage ?? static fn (): bool => function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		$this->verify_nonce       = $verify_nonce ?? static fn ( string $nonce ): bool => function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, 'upsellbay_save_offer' );
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

		if ( '' === $title ) {
			return array(
				'success' => false,
				'message' => __( 'Offer name is required.', 'upsellbay' ),
			);
		}

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
			if ( null !== $this->logger ) {
				$this->logger->error(
					/* translators: %s: error message */
					sprintf( __( 'Failed to save offer: %s', 'upsellbay' ), $throwable->getMessage() ),
					array(
						'source'       => 'offer_edit',
						'metadata'     => array(
							'exception' => get_class( $throwable ),
							'file'      => $throwable->getFile(),
							'line'      => $throwable->getLine(),
							'trace'     => $throwable->getTraceAsString(),
						),
						'request_data' => $request,
					)
				);
			}

			return array(
				'success' => false,
				'message' => $throwable->getMessage(),
			);
		}

		$message = __( 'Offer saved.', 'upsellbay' );

		if ( null !== $this->conflict_detector ) {
			$warnings = $this->conflict_detector->detect( $offer_id, $valid->data() );
			if ( count( $warnings ) > 0 ) {
				$message .= ' ' . implode( ' ', $warnings );
			}
		}

		return array(
			'success'  => true,
			'message'  => $message,
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
		$allowed_types = array( 'cart_product', 'cart_category', 'cart_tag', 'cart_subtotal', 'viewed_product', 'user_role', 'customer_order_count', 'customer_lifetime_spend', 'stock_status', 'exclude_if_product_in_cart' );
		$normalized    = array();

		foreach ( $rules as $rule ) {
			$type = $this->normalize_rule_type( (string) ( $rule['type'] ?? '' ) );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			$normalized[] = array(
				'type'     => $type,
				'operator' => $this->normalize_rule_operator( (string) ( $rule['operator'] ?? 'eq' ) ),
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
			'basics'          => array(
				'label'     => __( 'Required basics', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( 'title', '_ub_status', '_ub_offer_type', '_ub_offer_goal', '_ub_offer_product_id', '_ub_reason_label', '_ub_headline', '_ub_body', '_ub_button_text' ),
			),
			'targeting'       => array(
				'label'     => __( 'Targeting rules', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_rules_match', '_ub_rules' ),
			),
			'recommendations' => array(
				'label'     => __( 'Recommendations', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_recommendations' ),
			),
			'discount'        => array(
				'label'     => __( 'Discount', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_discount_type', '_ub_discount_value' ),
			),
			'placement'       => array(
				'label'     => __( 'Placement display', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_show_image', '_ub_placement_config' ),
			),
			'schedule'        => array(
				'label'     => __( 'Schedule and priority', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_start_at', '_ub_end_at', '_ub_priority' ),
			),
			'advanced'        => array(
				'label'     => __( 'Advanced metadata', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_stats_summary', '_ub_trigger_product_ids', '_ub_trigger_category_ids', '_ub_conflict_override', '_ub_conflict_override_reason' ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offer_id = isset( $_GET['offer_id'] ) ? (int) $_GET['offer_id'] : ( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		$offer    = null;

		if ( $offer_id > 0 ) {
			$offer = $this->service->get( $offer_id );
		}

		$this->section_navigation->render( null !== $offer ? '' : 'add_offer' );

		$meta  = null !== $offer ? $offer['meta'] : $this->defaults->for_type( 'checkout_bump' );
		$title = null !== $offer ? $offer['title'] : '';

		if ( null !== $offer ) {
			/* translators: %d: offer ID */
			echo '<h2 class="wp-heading-inline">' . esc_html( sprintf( __( 'UpsellBay Offer: ID - %d', 'upsellbay' ), $offer_id ) ) . '</h2>';
			echo '<p class="description" style="margin-bottom: 8px;">' . esc_html__( 'You can modify and save data', 'upsellbay' ) . '</p>';

			if ( null !== $this->conflict_detector ) {
				$warnings = $this->conflict_detector->detect( $offer_id, $meta );
				foreach ( $warnings as $warning ) {
					echo '<div class="notice notice-warning inline"><p>' . esc_html( $warning ) . '</p></div>';
				}
			}
		} else {
			echo '<h2 class="wp-heading-inline">' . esc_html__( 'Add UpsellBay Offer', 'upsellbay' ) . '</h2>';
		}
		echo '<hr class="wp-header-end">';
		echo '<form method="post">';
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( 'upsellbay_save_offer', 'nonce' );
		}

		if ( $offer_id > 0 ) {
			echo '<input type="hidden" name="offer_id" value="' . esc_attr( (string) $offer_id ) . '">';
		}

		foreach ( $this->sections() as $section_id => $section ) {
			$classes = 'postbox upsellbay-offer-editor__section';
			if ( $section['collapsed'] ) {
				$classes .= ' closed';
			}
			echo '<div class="' . esc_attr( $classes ) . '" id="upsellbay-section-' . esc_attr( $section_id ) . '">';
			echo '<h2><span>' . esc_html( $section['label'] ) . '</span></h2>';
			echo '<div class="inside"><table class="form-table" role="presentation"><tbody>';
			foreach ( $section['fields'] as $field ) {
				$value = 'title' === $field ? $title : ( $meta[ $field ] ?? '' );
				$this->render_field_row( $field, $value );
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
	 * @param mixed  $value Field value.
	 */
	private function render_field_row( string $field, $value = '' ): void {
		$labels = array(
			'title'                        => __( 'Offer name', 'upsellbay' ),
			'_ub_status'                   => __( 'Status', 'upsellbay' ),
			'_ub_offer_type'               => __( 'Placement', 'upsellbay' ),
			'_ub_offer_product_id'         => __( 'Offer product', 'upsellbay' ),
			'_ub_headline'                 => __( 'Headline', 'upsellbay' ),
			'_ub_body'                     => __( 'Body text', 'upsellbay' ),
			'_ub_button_text'              => __( 'Button text', 'upsellbay' ),
			'_ub_rules_match'              => __( 'Rule matching', 'upsellbay' ),
			'_ub_rules'                    => __( 'Rules', 'upsellbay' ),
			'_ub_discount_type'            => __( 'Discount type', 'upsellbay' ),
			'_ub_discount_value'           => __( 'Discount value', 'upsellbay' ),
			'_ub_show_image'               => __( 'Show product image', 'upsellbay' ),
			'_ub_placement_config'         => __( 'Placement options', 'upsellbay' ),
			'_ub_start_at'                 => __( 'Start date', 'upsellbay' ),
			'_ub_end_at'                   => __( 'End date', 'upsellbay' ),
			'_ub_priority'                 => __( 'Priority', 'upsellbay' ),
			'_ub_stats_summary'            => __( 'Performance', 'upsellbay' ),
			'_ub_trigger_product_ids'      => __( 'Trigger product IDs', 'upsellbay' ),
			'_ub_trigger_category_ids'     => __( 'Trigger category IDs', 'upsellbay' ),
			'_ub_offer_goal'               => __( 'Offer goal', 'upsellbay' ),
			'_ub_reason_label'             => __( 'Reason label', 'upsellbay' ),
			'_ub_recommendations'          => __( 'Assistant suggestions', 'upsellbay' ),
			'_ub_conflict_override'        => __( 'Conflict override', 'upsellbay' ),
			'_ub_conflict_override_reason' => __( 'Conflict override reason', 'upsellbay' ),
		);
		$label  = $labels[ $field ] ?? $field;

		echo '<tr><th scope="row"><label for="upsellbay-' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';

		if ( '_ub_recommendations' === $field ) {
			echo '<div id="upsellbay-recommendations-container" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">';
			echo '<p class="description">' . esc_html__( 'Select a primary target product above to see AI/WooCommerce product recommendations here.', 'upsellbay' ) . '</p>';
			echo '</div>';
		} elseif ( '_ub_stats_summary' === $field ) {
			$this->render_stats_summary();
		} elseif ( '_ub_offer_type' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
			foreach (
				array(
					'checkout_bump'  => __( 'Checkout bump', 'upsellbay' ),
					'product_upsell' => __( 'Product page offer', 'upsellbay' ),
					'cart_crosssell' => __( 'Cart offer', 'upsellbay' ),
					'thankyou_offer' => __( 'Thank-you follow-on offer', 'upsellbay' ),
				) as $option_val => $option_label
			) {
				echo '<option value="' . esc_attr( $option_val ) . '" ' . selected( $value, $option_val, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
		} elseif ( '_ub_rules_match' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
			echo '<option value="all" ' . selected( $value, 'all', false ) . '>' . esc_html__( 'All rules', 'upsellbay' ) . '</option>';
			echo '<option value="any" ' . selected( $value, 'any', false ) . '>' . esc_html__( 'Any rule', 'upsellbay' ) . '</option>';
			echo '</select>';
		} elseif ( '_ub_status' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
			foreach (
				array(
					'draft'  => __( 'Draft', 'upsellbay' ),
					'active' => __( 'Active', 'upsellbay' ),
					'paused' => __( 'Paused', 'upsellbay' ),
				) as $option_val => $option_label
			) {
				echo '<option value="' . esc_attr( $option_val ) . '" ' . selected( $value, $option_val, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Draft offers are only visible in the admin. Active offers are shown to shoppers. Paused offers are temporarily hidden.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_offer_goal' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
			foreach (
				array(
					'add_on'           => __( 'Add-on (Complementary item)', 'upsellbay' ),
					'upgrade'          => __( 'Upgrade (Better version)', 'upsellbay' ),
					'protection'       => __( 'Protection (Warranty/Insurance)', 'upsellbay' ),
					'threshold_helper' => __( 'Threshold Helper (Free shipping/discount)', 'upsellbay' ),
					'follow_on'        => __( 'Follow-on (Post-purchase)', 'upsellbay' ),
				) as $option_val => $option_label
			) {
				echo '<option value="' . esc_attr( $option_val ) . '" ' . selected( $value, $option_val, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Defines the primary intent of this offer. Used for conflict resolution and performance tracking.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_discount_type' === $field ) {
			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '">';
			echo '<option value="none" ' . selected( $value, 'none', false ) . '>' . esc_html__( 'No discount', 'upsellbay' ) . '</option>';
			echo '<option value="percent" ' . selected( $value, 'percent', false ) . '>' . esc_html__( 'Percentage', 'upsellbay' ) . '</option>';
			echo '<option value="fixed_amount" ' . selected( $value, 'fixed_amount', false ) . '>' . esc_html__( 'Fixed amount off', 'upsellbay' ) . '</option>';
			echo '<option value="fixed_price" ' . selected( $value, 'fixed_price', false ) . '>' . esc_html__( 'Fixed offer price', 'upsellbay' ) . '</option>';
			echo '</select>';
		} elseif ( '_ub_body' === $field ) {
			echo '<textarea id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" class="large-text" rows="3" maxlength="240">' . esc_textarea( (string) $value ) . '</textarea>';
			echo '<p class="description">' . esc_html__( 'Optional short description shown below the headline. Max 240 characters. Supports limited HTML: links, line breaks, bold, italic.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_show_image' === $field ) {
			echo '<label><input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="checkbox" value="1" ' . checked( $value, true, false ) . '> ' . esc_html__( 'Show the WooCommerce product image when available.', 'upsellbay' ) . '</label>';
		} elseif ( '_ub_conflict_override' === $field ) {
			echo '<label><input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="checkbox" value="1" ' . checked( $value, true, false ) . '> ' . esc_html__( 'Override conflict prevention (advanced)', 'upsellbay' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'If checked, this offer may show even if it conflicts with another offer or the current cart state. Use with caution.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_offer_product_id' === $field ) {
			echo '<div class="upsellbay-product-selector" data-upsellbay-product-selector>';
			echo '<div class="upsellbay-product-selector__input-wrapper" ' . ( 0 !== (int) $value ? 'style="display:none;"' : '' ) . '>';
			echo '<input id="upsellbay-' . esc_attr( $field ) . '-search" type="text" class="regular-text" placeholder="' . esc_attr__( 'Search for a product...', 'upsellbay' ) . '" autocomplete="off">';
			echo '<button type="button" class="upsellbay-product-selector__clear" style="display: none;" title="' . esc_attr__( 'Clear search', 'upsellbay' ) . '">&times;</button>';
			echo '</div>';
			echo '<input type="hidden" id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( (string) $value ) . '">';
			echo '<div class="upsellbay-product-selector__results" data-upsellbay-results></div>';
			echo '<div class="upsellbay-product-selector__selection' . ( 0 !== (int) $value ? ' is-active' : '' ) . '" data-upsellbay-selection>';
			if ( 0 !== (int) $value && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $value );
				if ( $product ) {
					echo '<div class="upsellbay-product-selector__result-image">';
					$image = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
					if ( $image ) {
						echo '<img src="' . esc_url( $image ) . '" alt="">';
					}
					echo '</div>';
					echo '<div class="upsellbay-product-selector__result-info">';
					echo '<span class="upsellbay-product-selector__result-name">' . esc_html( $product->get_name() ) . '</span>';
					echo '<span class="upsellbay-product-selector__result-meta">' . wp_kses_post( $product->get_price_html() ) . '</span>';
					echo '</div>';
					echo '<span class="upsellbay-product-selector__selection-remove">&times;</span>';
				}
			}
			echo '</div>';
			echo '</div>';
		} elseif ( '_ub_priority' === $field ) {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="number" class="small-text" min="0" step="1" value="' . esc_attr( (string) (int) $value ) . '">';
			echo '<p class="description">' . esc_html__( 'Lower numbers appear first when multiple offers are eligible for the same placement.', 'upsellbay' ) . '</p>';
		} elseif ( str_contains( $field, '_ids' ) ) {
			$val_str = is_array( $value ) ? implode( ',', $value ) : $value;
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="text" class="regular-text" value="' . esc_attr( (string) $val_str ) . '">';
			if ( '_ub_trigger_product_ids' === $field ) {
				echo '<p class="description">' . esc_html__( 'Comma-separated product IDs. When set, this offer only appears if one of these products is in the cart or being viewed.', 'upsellbay' ) . '</p>';
			} elseif ( '_ub_trigger_category_ids' === $field ) {
				echo '<p class="description">' . esc_html__( 'Comma-separated category IDs. When set, this offer only appears if a product from one of these categories is in the cart or being viewed.', 'upsellbay' ) . '</p>';
			}
		} elseif ( '_ub_rules' === $field || '_ub_placement_config' === $field ) {
			$val_str = is_array( $value ) ? wp_json_encode( $value ) : $value;
			echo '<textarea id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" class="large-text code" rows="3">' . esc_textarea( (string) $val_str ) . '</textarea>';
		} elseif ( '_ub_start_at' === $field || '_ub_end_at' === $field ) {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="datetime-local" class="regular-text" value="' . esc_attr( (string) $value ) . '">';
		} else {
			$is_required = in_array( $field, array( 'title', '_ub_headline', '_ub_button_text' ), true );
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="text" class="regular-text" value="' . esc_attr( (string) $value ) . '"' . ( $is_required ? ' required' : '' ) . '>';
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

		$parse_ids = function ( $val ): array {
			if ( is_array( $val ) ) {
				return array_map( array( $this, 'positive_int' ), $val );
			}
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				return array_map( array( $this, 'positive_int' ), explode( ',', $val ) );
			}
			return array();
		};

		$parse_json = function ( $val, $fallback ) {
			if ( is_array( $val ) ) {
				return $val;
			}
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$decoded = json_decode( wp_unslash( $val ), true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
			return $fallback;
		};

		return array_replace(
			$defaults,
			array(
				'_ub_offer_type'               => $offer_type,
				'_ub_status'                   => $this->sanitize_key( (string) ( $request['_ub_status'] ?? $defaults['_ub_status'] ) ),
				'_ub_offer_product_id'         => (int) ( $request['_ub_offer_product_id'] ?? 0 ),
				'_ub_trigger_product_ids'      => $parse_ids( $request['_ub_trigger_product_ids'] ?? null ),
				'_ub_trigger_category_ids'     => $parse_ids( $request['_ub_trigger_category_ids'] ?? null ),
				'_ub_discount_type'            => $this->sanitize_key( (string) ( $request['_ub_discount_type'] ?? $defaults['_ub_discount_type'] ) ),
				'_ub_discount_value'           => (string) ( $request['_ub_discount_value'] ?? $defaults['_ub_discount_value'] ),
				'_ub_offer_goal'               => $this->sanitize_key( (string) ( $request['_ub_offer_goal'] ?? $defaults['_ub_offer_goal'] ) ),
				'_ub_reason_label'             => $this->sanitize_text( (string) ( $request['_ub_reason_label'] ?? $defaults['_ub_reason_label'] ) ),
				'_ub_headline'                 => $this->sanitize_text( (string) ( $request['_ub_headline'] ?? $defaults['_ub_headline'] ) ),
				'_ub_body'                     => $this->sanitize_html( (string) ( $request['_ub_body'] ?? $defaults['_ub_body'] ) ),
				'_ub_button_text'              => $this->sanitize_text( (string) ( $request['_ub_button_text'] ?? $defaults['_ub_button_text'] ) ),
				'_ub_conflict_override'        => array_key_exists( '_ub_conflict_override', $request ) && false !== $request['_ub_conflict_override'] && '' !== (string) $request['_ub_conflict_override'],
				'_ub_conflict_override_reason' => $this->sanitize_text( (string) ( $request['_ub_conflict_override_reason'] ?? $defaults['_ub_conflict_override_reason'] ) ),
				'_ub_rules'                    => $this->normalize_rules( $parse_json( $request['_ub_rules'] ?? null, array() ) ),
				'_ub_rules_match'              => $this->sanitize_key( (string) ( $request['_ub_rules_match'] ?? 'all' ) ),
				'_ub_placement_config'         => array_map( array( $this, 'sanitize_text' ), $parse_json( $request['_ub_placement_config'] ?? null, $defaults['_ub_placement_config'] ) ),
				'_ub_show_image'               => $show_image,
				'_ub_start_at'                 => $this->sanitize_text( (string) ( $request['_ub_start_at'] ?? '' ) ),
				'_ub_end_at'                   => $this->sanitize_text( (string) ( $request['_ub_end_at'] ?? '' ) ),
				'_ub_priority'                 => (int) ( $request['_ub_priority'] ?? 0 ),
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
	 * Normalize legacy editor rule type aliases to runtime parser keys.
	 *
	 * @param string $value Raw rule type.
	 */
	private function normalize_rule_type( string $value ): string {
		$type = $this->sanitize_key( $value );

		return match ( $type ) {
			'lifetime_spend'         => 'customer_lifetime_spend',
			'exclude_product_in_cart' => 'exclude_if_product_in_cart',
			default                  => $type,
		};
	}

	/**
	 * Normalize legacy editor operator aliases to runtime evaluator keys.
	 *
	 * @param string $value Raw operator.
	 */
	private function normalize_rule_operator( string $value ): string {
		$operator = $this->sanitize_key( $value );

		return match ( $operator ) {
			'is'     => 'eq',
			'is_not' => 'neq',
			default  => $operator,
		};
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

	/**
	 * Render read-only performance stats summary for an existing offer.
	 *
	 * @since 1.0.0
	 */
	private function render_stats_summary(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offer_id = isset( $_GET['offer_id'] ) ? (int) $_GET['offer_id'] : ( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );

		if ( $offer_id <= 0 ) {
			echo '<p class="description">' . esc_html__( 'Performance stats will appear here after the offer is saved and starts receiving traffic.', 'upsellbay' ) . '</p>';
			return;
		}

		$stats = $this->fetch_offer_stats( $offer_id );

		$accept_rate = $stats['views'] > 0
			? number_format( ( $stats['accepts'] / $stats['views'] ) * 100, 1 ) . '%'
			: '—';

		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

		echo '<table class="widefat striped" style="max-width: 480px;">';
		echo '<tbody>';

		$rows = array(
			array( __( 'Views', 'upsellbay' ), number_format_i18n( $stats['views'] ) ),
			array( __( 'Accepts', 'upsellbay' ), number_format_i18n( $stats['accepts'] ) ),
			array( __( 'Dismissals', 'upsellbay' ), number_format_i18n( $stats['dismissals'] ) ),
			array( __( 'Accept rate', 'upsellbay' ), $accept_rate ),
			array( __( 'Orders', 'upsellbay' ), number_format_i18n( $stats['orders'] ) ),
			array(
				__( 'Attributed revenue', 'upsellbay' ),
				$currency_symbol . number_format_i18n( (float) $stats['revenue'], 2 ),
			),
			array(
				__( 'Total discounts', 'upsellbay' ),
				$currency_symbol . number_format_i18n( (float) $stats['discount_total'], 2 ),
			),
		);

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $row[0] ) . '</strong></td>';
			echo '<td>' . esc_html( $row[1] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'All-time aggregate stats for this offer. For date-range analytics, use the Dashboard tab.', 'upsellbay' ) . '</p>';
	}

	/**
	 * Fetch all-time aggregate stats for one offer from the stats table.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 * @return array{views: int, accepts: int, dismissals: int, orders: int, revenue: string, discount_total: string}
	 */
	private function fetch_offer_stats( int $offer_id ): array {
		$defaults = array(
			'views'          => 0,
			'accepts'        => 0,
			'dismissals'     => 0,
			'orders'         => 0,
			'revenue'        => '0.000000',
			'discount_total' => '0.000000',
		);

		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return $defaults;
		}

		$wpdb       = $GLOBALS['wpdb'];
		$table_name = $wpdb->prefix . \WPAnchorBay\UpsellBay\Core\Constants::STATS_TABLE_SUFFIX;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(views), 0) AS views,
					COALESCE(SUM(accepts), 0) AS accepts,
					COALESCE(SUM(dismissals), 0) AS dismissals,
					COALESCE(SUM(orders), 0) AS orders,
					COALESCE(SUM(revenue), 0) AS revenue,
					COALESCE(SUM(discount_total), 0) AS discount_total
				FROM {$table_name}
				WHERE offer_id = %d",
				$offer_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $row ) ) {
			return $defaults;
		}

		return array(
			'views'          => (int) $row['views'],
			'accepts'        => (int) $row['accepts'],
			'dismissals'     => (int) $row['dismissals'],
			'orders'         => (int) $row['orders'],
			'revenue'        => number_format( (float) $row['revenue'], 6, '.', '' ),
			'discount_total' => number_format( (float) $row['discount_total'], 6, '.', '' ),
		);
	}
}
