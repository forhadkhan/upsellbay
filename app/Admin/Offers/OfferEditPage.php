<?php
/**
 * Offer editor admin page.
 *
 * @package UpsellBay\Admin\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Offers;

defined( 'ABSPATH' ) || exit;

use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferSchema;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferConflictDetector;
use WPAnchorBay\UpsellBay\Domain\Discounts\DiscountCalculator;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferVisibilityPanel;
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
	 * Offer visibility panel.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferVisibilityPanel|null
	 */
	private ?OfferVisibilityPanel $visibility_panel;

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
	 * Meta for the offer currently being rendered.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	private array $current_meta = array();

	/**
	 * Discount calculator.
	 *
	 * @since 1.0.0
	 *
	 * @var DiscountCalculator
	 */
	private DiscountCalculator $discount_calculator;

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
	 * @param OfferVisibilityPanel|null   $visibility_panel   Visibility diagnostics panel.
	 */
	public function __construct( OfferService $service, OfferValidator $validator, ?callable $can_manage = null, ?callable $verify_nonce = null, ?OfferDefaults $defaults = null, ?OfferSectionNavigation $section_navigation = null, ?OfferConflictDetector $conflict_detector = null, ?LoggerInterface $logger = null, ?OfferVisibilityPanel $visibility_panel = null ) {
		$this->service            = $service;
		$this->validator          = $validator;
		$this->defaults           = $defaults ?? new OfferDefaults();
		$this->section_navigation = $section_navigation ?? new OfferSectionNavigation();
		$this->conflict_detector  = $conflict_detector;
		$this->logger             = $logger;
		$this->visibility_panel   = $visibility_panel;
		$this->discount_calculator = new DiscountCalculator();
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
				'fields'    => array( 'title', '_ub_status', '_ub_offer_type', '_ub_offer_goal', '_ub_offer_product_id', '_ub_reason_label', '_ub_section_heading', '_ub_headline', '_ub_body', '_ub_button_text' ),
			),
			'targeting'       => array(
				'label'     => __( 'Targeting rules', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_rules_match', '_ub_rules' ),
			),
			'discount'        => array(
				'label'     => __( 'Discount', 'upsellbay' ),
				'collapsed' => false,
				'fields'    => array( '_ub_discount_type', '_ub_discount_value' ),
			),
			'placement'       => array(
				'label'     => __( 'Display settings', 'upsellbay' ),
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
			'discount'               => __( 'Discounts are calculated server-side from the selected product price. The updated price preview below the discount fields refreshes as you edit.', 'upsellbay' ),
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
		$offer_id = $this->current_offer_id();
		$offer    = null;

		if ( $offer_id > 0 ) {
			$offer = $this->service->get( $offer_id );
		}

		$this->section_navigation->render( null !== $offer ? '' : 'add_offer' );
		$this->render_notices( $offer );

		$meta  = null !== $offer ? $offer['meta'] : $this->new_offer_meta();
		$title = null !== $offer ? $offer['title'] : '';
		$this->current_meta = $meta;

		if ( null !== $offer ) {
			$context    = array();
			$offer_type = $meta['_ub_offer_type'] ?? '';

			if ( function_exists( 'wc_get_checkout_url' ) ) {
				$context['checkout_url'] = wc_get_checkout_url();
			}
			if ( function_exists( 'wc_get_cart_url' ) ) {
				$context['cart_url'] = wc_get_cart_url();
			}
			if ( function_exists( 'WC' ) && WC()->cart ) {
				$context['cart_product_ids'] = array_map(
					static fn ( array $cart_item ): int => (int) ( $cart_item['product_id'] ?? 0 ),
					WC()->cart->get_cart()
				);
			}
			if ( 'thankyou_offer' === $offer_type && function_exists( 'wc_get_orders' ) ) {
				$latest_orders = wc_get_orders(
					array(
						'limit'   => 1,
						'orderby' => 'date',
						'order'   => 'DESC',
						'status'  => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
					)
				);
				if ( ! empty( $latest_orders ) ) {
					$context['order_received_url'] = $latest_orders[0]->get_checkout_order_received_url();
				}
			}

			$preview_builder = new \WPAnchorBay\UpsellBay\Admin\PreviewLinks();
			$preview         = $preview_builder->for_offer( $offer, $context );

			$preview_info = '';
			if ( 'product_upsell' === $offer_type ) {
				$preview_info = __( 'To ensure reliable previews, this links to the offered product\'s page. The actual offer will appear on your targeted products based on active rules.', 'upsellbay' );
			} elseif ( 'thankyou_offer' === $offer_type ) {
				$preview_info = __( 'Previews use your most recent WooCommerce order. If you haven\'t placed an order yet, you may need to complete a test checkout first.', 'upsellbay' );
			} elseif ( 'cart_crosssell' === $offer_type ) {
				$preview_info = __( 'Links directly to your cart page. Add an item to your cart to see the offer preview.', 'upsellbay' );
			} elseif ( 'checkout_bump' === $offer_type ) {
				$preview_info = __( 'Links directly to your checkout page. You must have an item in your cart to view checkout.', 'upsellbay' );
			}

			echo '<div id="upsellbay-offer-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">';
			echo '<div>';
			/* translators: %d: offer ID */
			echo '<h2 class="wp-heading-inline" style="margin-bottom: 4px;">' . esc_html( sprintf( __( 'UpsellBay Offer: ID - %d', 'upsellbay' ), $offer_id ) ) . '</h2>';
			echo '<p class="description" style="margin-top: 0;">' . esc_html__( 'You can modify and save data', 'upsellbay' ) . '</p>';
			echo '</div>';
			echo '<div style="display: flex; align-items: center; gap: 4px;">';
			if ( '' !== $preview_info && function_exists( 'wc_help_tip' ) ) {
				echo wp_kses_post( wc_help_tip( $preview_info ) );
			}
			if ( $preview['available'] ) {
				printf(
					'<a href="%1$s" target="_blank" class="button button-secondary" title="%2$s">%3$s <span class="dashicons dashicons-external" style="line-height: inherit; font-size: 14px; margin-left: 2px;"></span></a>',
					esc_url( $preview['url'] ),
					esc_attr( $preview['message'] ),
					esc_html__( 'View Live', 'upsellbay' )
				);
			} else {
				printf(
					'<button type="button" class="button button-secondary disabled" title="%1$s" disabled>%2$s <span class="dashicons dashicons-external" style="line-height: inherit; font-size: 14px; margin-left: 2px;"></span></button>',
					esc_attr( $preview['message'] ),
					esc_html__( 'View Live', 'upsellbay' )
				);
			}
			echo '</div>';
			echo '</div>';

			if ( null !== $this->visibility_panel ) {
				$this->visibility_panel->render( $offer );
			}
		} else {
			echo '<h2 id="upsellbay-offer-header" class="wp-heading-inline">' . esc_html__( 'Add UpsellBay Offer', 'upsellbay' ) . '</h2>';
		}
		echo '<hr class="wp-header-end">';
		echo '<form method="post" id="upsellbay-offer-editor-form">';
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

		echo '<div id="upsellbay-offer-summary" class="notice notice-info inline" style="margin-bottom: 15px; display: none; border-left-color: #007cba;"></div>';
		echo '<p class="submit" style="display: flex; gap: 8px; align-items: center;">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save offer', 'upsellbay' ) . '</button>';
		echo '<a class="button" href="' . esc_url( 'admin.php?page=upsellbay&tab=offers' ) . '">' . esc_html__( 'Back to offers', 'upsellbay' ) . '</a>';

		if ( $offer_id > 0 ) {
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=upsellbay_delete_offer&offer_id=' . $offer_id ), 'upsellbay_delete_offer' );
			echo '<a href="' . esc_url( $delete_url ) . '" class="button button-link-delete upsellbay-button-danger upsellbay-modal-trigger" style="margin-left: auto; color: #b32d2e;" data-modal-title="' . esc_attr__( 'Delete Offer', 'upsellbay' ) . '" data-modal-message="' . esc_attr__( 'Are you sure you want to permanently delete this offer? This cannot be undone.', 'upsellbay' ) . '" data-modal-confirm="' . esc_attr__( 'Delete', 'upsellbay' ) . '" data-modal-cancel="' . esc_attr__( 'Cancel', 'upsellbay' ) . '">' . esc_html__( 'Delete offer', 'upsellbay' ) . '</a>';
		}

		echo '</p>';
		echo '</form>';
		$this->current_meta = array();
	}

	/**
	 * Render all page notices between the section menu and the offer header.
	 *
	 * Outputs redirect success/error messages and conflict warnings in a single
	 * region so every notice appears consistently between
	 * #upsellbay-offers-section-menu and #upsellbay-offer-header.
	 *
	 * @since 1.0.0
	 *
	 * @param array{id: int, title: string, meta: array<string, mixed>}|null $offer Current offer data, or null for new offers.
	 */
	private function render_notices( ?array $offer ): void {
		$has_redirect_notices = false;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ub_message'] ) || isset( $_GET['ub_error'] ) || isset( $_GET['wc_message'] ) || isset( $_GET['wc_error'] ) ) {
			$has_redirect_notices = true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$conflict_warnings = array();
		if ( null !== $offer && null !== $this->conflict_detector ) {
			$conflict_warnings = $this->conflict_detector->detect( $offer['id'], $offer['meta'] );
		}

		if ( ! $has_redirect_notices && array() === $conflict_warnings ) {
			return;
		}

		echo '<div class="upsellbay-page-notices">';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ub_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['ub_message'] ) );
			echo '<div class="notice notice-success upsellbay-page-notice"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( isset( $_GET['wc_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['wc_message'] ) );
			echo '<div class="notice notice-success upsellbay-page-notice"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( isset( $_GET['ub_error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['ub_error'] ) );
			echo '<div class="notice notice-error upsellbay-page-notice"><p>' . esc_html( $error ) . '</p></div>';
		}

		if ( isset( $_GET['wc_error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['wc_error'] ) );
			echo '<div class="notice notice-error upsellbay-page-notice"><p>' . esc_html( $error ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		foreach ( $conflict_warnings as $warning ) {
			echo '<div class="notice notice-warning upsellbay-page-notice"><p>' . esc_html( $warning ) . '</p></div>';
		}

		echo '</div>';
	}

	/**
	 * Resolve the current offer ID from the request.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private function current_offer_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['offer_id'] ) ) {
			return function_exists( 'absint' ) ? absint( $_GET['offer_id'] ) : (int) $_GET['offer_id'];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['id'] ) ) {
			return function_exists( 'absint' ) ? absint( $_GET['id'] ) : (int) $_GET['id'];
		}

		return 0;
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
		$labels    = array(
			'title'                        => __( 'Offer name', 'upsellbay' ),
			'_ub_status'                   => __( 'Status', 'upsellbay' ),
			'_ub_offer_type'               => __( 'Offer type', 'upsellbay' ),
			'_ub_offer_product_id'         => __( 'Offer product', 'upsellbay' ),
			'_ub_headline'                 => __( 'Headline', 'upsellbay' ),
			'_ub_body'                     => __( 'Body text', 'upsellbay' ),
			'_ub_button_text'              => __( 'Button text', 'upsellbay' ),
			'_ub_rules_match'              => __( 'Rule matching', 'upsellbay' ),
			'_ub_rules'                    => __( 'Rules', 'upsellbay' ),
			'_ub_discount_type'            => __( 'Discount type', 'upsellbay' ),
			'_ub_discount_value'           => __( 'Discount value', 'upsellbay' ),
			'_ub_show_image'               => __( 'Show product image', 'upsellbay' ),
			'_ub_placement_config'         => __( 'Display position', 'upsellbay' ),
			'_ub_start_at'                 => __( 'Start date', 'upsellbay' ),
			'_ub_end_at'                   => __( 'End date', 'upsellbay' ),
			'_ub_priority'                 => __( 'Priority', 'upsellbay' ),
			'_ub_stats_summary'            => __( 'Performance', 'upsellbay' ),
			'_ub_trigger_product_ids'      => __( 'Trigger product IDs', 'upsellbay' ),
			'_ub_trigger_category_ids'     => __( 'Trigger category IDs', 'upsellbay' ),
			'_ub_offer_goal'               => __( 'Offer goal', 'upsellbay' ),
			'_ub_reason_label'             => __( 'Reason label', 'upsellbay' ),
			'_ub_section_heading'          => __( 'Section heading', 'upsellbay' ),
			'_ub_conflict_override'        => __( 'Conflict override', 'upsellbay' ),
			'_ub_conflict_override_reason' => __( 'Conflict override reason', 'upsellbay' ),
		);
		$label     = $labels[ $field ] ?? $field;
		$label_for = '_ub_placement_config' === $field ? 'upsellbay-_ub_placement_config-position' : 'upsellbay-' . $field;

		$is_required_field = in_array( $field, array( 'title', '_ub_status', '_ub_offer_type', '_ub_offer_product_id', '_ub_headline', '_ub_button_text' ), true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $label_for ) . '">' . esc_html( $label );
		if ( $is_required_field ) {
			echo ' <span class="required" style="color: #d63638;" title="' . esc_attr__( 'Required', 'upsellbay' ) . '">*</span>';
		}
		echo '</label></th><td>';

		if ( '_ub_stats_summary' === $field ) {
			$this->render_stats_summary();
		} elseif ( '_ub_offer_type' === $field ) {
			$descriptions = $this->offer_type_descriptions();

			echo '<select id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" required aria-required="true">';
			echo '<option value="">' . esc_html__( 'Select an offer type', 'upsellbay' ) . '</option>';
			foreach ( $this->offer_type_options() as $option_val => $option_label ) {
				echo '<option value="' . esc_attr( $option_val ) . '" ' . selected( $value, $option_val, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
			echo '<p id="upsellbay-offer-type-description" class="description" data-upsellbay-offer-type-description data-descriptions="' . esc_attr( wp_json_encode( $descriptions ) ) . '"' . ( isset( $descriptions[ (string) $value ] ) ? '' : ' hidden' ) . '>';
			if ( isset( $descriptions[ (string) $value ] ) ) {
				echo esc_html( $descriptions[ (string) $value ] );
			}
			echo '</p>';
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
			echo '<p class="description">' . esc_html__( 'Choose the discount method first. The updated price preview below will refresh automatically.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_body' === $field ) {
			echo '<textarea id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" class="large-text" rows="3" maxlength="240">' . esc_textarea( (string) $value ) . '</textarea>';
			echo '<p class="description">' . esc_html__( 'Optional short description shown below the headline. Max 240 characters. Supports limited HTML: links, line breaks, bold, italic.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_section_heading' === $field ) {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="text" class="regular-text" value="' . esc_attr( (string) $value ) . '" maxlength="80">';
			echo '<p class="description">' . esc_html__( 'Controls the heading shown above the cart offer list in block and classic checkout. Leave blank to use the default Recommended for you.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_show_image' === $field ) {
			echo '<label><input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="checkbox" value="1" ' . checked( $value, true, false ) . '> ' . esc_html__( 'Show the WooCommerce product image when available.', 'upsellbay' ) . '</label>';
		} elseif ( '_ub_conflict_override' === $field ) {
			echo '<label><input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="checkbox" value="1" ' . checked( $value, true, false ) . '> ' . esc_html__( 'Override conflict prevention (advanced)', 'upsellbay' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'If checked, this offer may show even if it conflicts with another offer or the current cart state. Use with caution.', 'upsellbay' ) . '</p>';
		} elseif ( '_ub_offer_product_id' === $field ) {
			$selection_price = '';
			if ( 0 !== (int) $value && function_exists( 'wc_get_product' ) ) {
				$selection_product = wc_get_product( (int) $value );
				if ( $selection_product && method_exists( $selection_product, 'get_price' ) ) {
					$selection_price = (string) $selection_product->get_price();
				}
			}

			echo '<div class="upsellbay-product-selector" data-upsellbay-product-selector>';
			echo '<div class="upsellbay-product-selector__input-wrapper" ' . ( 0 !== (int) $value ? 'style="display:none;"' : '' ) . '>';
			echo '<input id="upsellbay-' . esc_attr( $field ) . '-search" type="text" class="regular-text" placeholder="' . esc_attr__( 'Search for a product...', 'upsellbay' ) . '" autocomplete="off">';
			echo '<button type="button" class="upsellbay-product-selector__clear" style="display: none;" title="' . esc_attr__( 'Clear search', 'upsellbay' ) . '">&times;</button>';
			echo '</div>';
			echo '<input type="hidden" id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( (string) $value ) . '">';
			echo '<input type="hidden" id="upsellbay-' . esc_attr( $field ) . '-price" name="_ub_offer_product_price" value="' . esc_attr( $selection_price ) . '" data-upsellbay-product-price-input>';
			echo '<div class="upsellbay-product-selector__results" data-upsellbay-results></div>';
			echo '<div class="upsellbay-product-selector__selection' . ( 0 !== (int) $value ? ' is-active' : '' ) . '" data-upsellbay-selection' . ( '' !== $selection_price ? ' data-upsellbay-product-price="' . esc_attr( $selection_price ) . '"' : '' ) . '>';
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
			if ( '_ub_placement_config' === $field ) {
				$this->render_placement_config_field( is_array( $value ) ? $value : array(), (string) $val_str );
			} else {
				echo '<textarea id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" style="display:none;" data-upsellbay-json-field="' . esc_attr( $field ) . '">' . esc_textarea( (string) $val_str ) . '</textarea>';
				echo '<div id="upsellbay-builder-' . esc_attr( $field ) . '" class="upsellbay-visual-builder"></div>';
			}
		} elseif ( '_ub_start_at' === $field || '_ub_end_at' === $field ) {
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="datetime-local" class="regular-text" value="' . esc_attr( (string) $value ) . '">';
		} else {
			$display_value = (string) $value;
			if ( '_ub_discount_value' === $field && is_numeric( $display_value ) && str_contains( $display_value, '.' ) ) {
				$display_value = rtrim( rtrim( $display_value, '0' ), '.' );
				if ( '' === $display_value ) {
					$display_value = '0';
				}
			}

			$is_required = in_array( $field, array( 'title', '_ub_headline', '_ub_button_text' ), true );
			echo '<input id="upsellbay-' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" type="text" class="regular-text" value="' . esc_attr( $display_value ) . '"' . ( $is_required ? ' required' : '' ) . '>';
			if ( '_ub_discount_value' === $field ) {
				$this->render_discount_preview();
			}
		}

		echo '</td></tr>';
	}

	/**
	 * Render the live updated price preview for the discount section.
	 *
	 * @since 1.0.0
	 */
	private function render_discount_preview(): void {
		$preview = $this->discount_preview();

		echo '<div class="upsellbay-discount-preview upsellbay-discount-preview--' . esc_attr( $preview['state'] ) . '" data-upsellbay-discount-preview aria-live="polite">';
		echo '<span class="upsellbay-discount-preview__label">' . esc_html__( 'Updated price', 'upsellbay' ) . '</span>';
		echo '<span class="upsellbay-discount-preview__value" data-upsellbay-discount-preview-value>' . wp_kses_post( $preview['value_html'] ) . '</span>';
		echo '<span class="description upsellbay-discount-preview__note" data-upsellbay-discount-preview-note>' . esc_html( $preview['note'] ) . '</span>';
		echo '</div>';
	}

	/**
	 * Resolve the live discount preview for the current editor state.
	 *
	 * @since 1.0.0
	 *
	 * @return array{state: string, value_html: string, note: string}
	 */
	private function discount_preview(): array {
		$product_id = (int) ( $this->current_meta['_ub_offer_product_id'] ?? 0 );
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array(
				'state'      => 'empty',
				'value_html' => __( 'Select a product to preview the updated price.', 'upsellbay' ),
				'note'       => __( 'The discount preview uses the selected product price.', 'upsellbay' ),
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! method_exists( $product, 'get_price' ) ) {
			return array(
				'state'      => 'empty',
				'value_html' => __( 'Select a product to preview the updated price.', 'upsellbay' ),
				'note'       => __( 'The discount preview uses the selected product price.', 'upsellbay' ),
			);
		}

		$original_price = (string) $product->get_price();
		if ( '' === $original_price || ! is_numeric( $original_price ) ) {
			return array(
				'state'      => 'empty',
				'value_html' => __( 'This product does not expose a numeric price yet.', 'upsellbay' ),
				'note'       => __( 'Pick a priced product to preview the discount outcome.', 'upsellbay' ),
			);
		}

		$discount = $this->discount_calculator->calculate( $original_price, $this->current_meta );
		if ( null === $discount ) {
			return array(
				'state'      => 'warning',
				'value_html' => __( 'Unable to calculate the updated price for this configuration.', 'upsellbay' ),
				'note'       => __( 'Double-check the selected discount values and try again.', 'upsellbay' ),
			);
		}

		$original_html = $this->format_currency_value( (float) $discount['original_price'] );
		$offer_html    = $this->format_currency_value( (float) $discount['offer_price'] );

		if ( $discount['offer_price'] === $discount['original_price'] ) {
			return array(
				'state'      => 'regular',
				'value_html' => '<strong>' . esc_html( $offer_html ) . '</strong>',
				'note'       => __( 'No discount is applied, so the current product price is shown here.', 'upsellbay' ),
			);
		}

		return array(
			'state'      => 'discounted',
			'value_html' => '<del>' . esc_html( $original_html ) . '</del> <strong>' . esc_html( $offer_html ) . '</strong>',
			'note'       => __( 'This is the price shoppers will see after the selected discount is applied.', 'upsellbay' ),
		);
	}

	/**
	 * Format a value as a WooCommerce currency string.
	 *
	 * @since 1.0.0
	 *
	 * @param float $value Price value.
	 * @return string
	 */
	private function format_currency_value( float $value ): string {
		$symbol             = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		$position           = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_currency_pos', 'left' ) : 'left';
		$decimals           = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$decimal_separator  = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_price_decimal_sep', '.' ) : '.';
		$thousand_separator = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_price_thousand_sep', ',' ) : ',';
		$formatted          = number_format( $value, $decimals, $decimal_separator, $thousand_separator );

		return match ( $position ) {
			'left_space'   => $symbol . ' ' . $formatted,
			'right_space'  => $formatted . ' ' . $symbol,
			'right'        => $formatted . $symbol,
			default        => $symbol . $formatted,
		};
	}

	/**
	 * Render placement config controls with advanced JSON fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $value   Placement config value.
	 * @param string               $json    Encoded placement config.
	 */
	private function render_placement_config_field( array $value, string $json ): void {
		$schema    = new OfferSchema();
		$positions = $schema->placement_config_positions();
		$position  = (string) ( $value['position'] ?? 'before_submit' );

		echo '<select id="upsellbay-_ub_placement_config-position" class="regular-text" data-upsellbay-placement-position data-target="upsellbay-_ub_placement_config">';
		if ( '' !== $position && ! isset( $positions[ $position ] ) ) {
			echo '<option value="' . esc_attr( $position ) . '" selected="selected">' . esc_html__( 'Custom saved position', 'upsellbay' ) . '</option>';
		}
		foreach ( $positions as $option_val => $option_label ) {
			echo '<option value="' . esc_attr( $option_val ) . '" ' . selected( $position, $option_val, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose from the predefined display positions. This updates the saved placement config without changing WooCommerce hook registration.', 'upsellbay' ) . '</p>';
		echo '<details class="upsellbay-placement-config-advanced">';
		echo '<summary>' . esc_html__( 'Advanced JSON', 'upsellbay' ) . '</summary>';
		echo '<textarea id="upsellbay-_ub_placement_config" name="_ub_placement_config" class="large-text code" rows="4" data-upsellbay-json-field="_ub_placement_config">' . esc_textarea( $json ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Advanced users can add extra placement config keys here. The position selector preserves unknown keys when possible.', 'upsellbay' ) . '</p>';
		echo '</details>';
	}

	/**
	 * Extract submitted offer meta.
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array<string, mixed>
	 */
	private function submitted_meta( array $request ): array {
		$offer_type = $this->sanitize_key( (string) ( $request['_ub_offer_type'] ?? '' ) );
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
				'_ub_section_heading'          => $this->sanitize_text( (string) ( $request['_ub_section_heading'] ?? $defaults['_ub_section_heading'] ) ),
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
	 * Return the offer type options shown in the editor.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function offer_type_options(): array {
		return array(
			OfferSchema::TYPE_CHECKOUT_BUMP  => __( 'Checkout bump', 'upsellbay' ),
			OfferSchema::TYPE_PRODUCT_UPSELL => __( 'Product page offer', 'upsellbay' ),
			OfferSchema::TYPE_CART_CROSSSELL => __( 'Cart offer', 'upsellbay' ),
			OfferSchema::TYPE_THANKYOU_OFFER => __( 'Thank-you follow-on offer', 'upsellbay' ),
		);
	}

	/**
	 * Return concise contextual descriptions for each offer type.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function offer_type_descriptions(): array {
		return array(
			OfferSchema::TYPE_CHECKOUT_BUMP  => __( 'A checkout bump is a last-step add-on shown on checkout before Place order. Shoppers can add it without leaving checkout. It appears when the offer is active, its rules match the current cart or checkout context, and the offered product is available.', 'upsellbay' ),
			OfferSchema::TYPE_PRODUCT_UPSELL => __( 'A product page offer is a related add-on shown near the add-to-cart area on matching product pages. It helps shoppers add a complementary item while considering a product. It appears when the offer is active, the viewed product matches your rules, and the offered product is available.', 'upsellbay' ),
			OfferSchema::TYPE_CART_CROSSSELL => __( 'A cart offer is a cross-sell shown while shoppers review their cart. It recommends an extra item before they continue to checkout. It appears when the offer is active, the current cart matches your rules, and the offered product is available.', 'upsellbay' ),
			OfferSchema::TYPE_THANKYOU_OFFER => __( 'A thank-you follow-on offer is a post-purchase offer shown on the order received page after the main order is complete. It sends shoppers to a separate checkout for the extra item. It appears when the offer is active, the completed order matches your rules, and the offered product is available.', 'upsellbay' ),
		);
	}

	/**
	 * Return neutral defaults for a brand-new offer form.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function new_offer_meta(): array {
		$schema  = new OfferSchema();
		$defaults = $this->defaults->for_type( OfferSchema::TYPE_CHECKOUT_BUMP );

		return array_replace(
			$schema->defaults(),
			$defaults,
			array(
				'_ub_offer_type'       => '',
				'_ub_status'           => 'draft',
				'_ub_discount_type'    => 'none',
				'_ub_discount_value'   => '0.000000',
				'_ub_show_image'       => true,
				'_ub_priority'         => 10,
				'_ub_placement_config' => array(),
			)
		);
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
		$offer_id = $this->current_offer_id();

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
