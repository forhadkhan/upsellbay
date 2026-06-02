<?php
/**
 * First-run wizard controller.
 *
 * @package UpsellBay\Admin\Wizard
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Wizard;

use Throwable;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferDefaults;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferService;

/**
 * Creates the first draft offer and stores onboarding completion.
 *
 * @since 1.0.0
 */
final class WizardController {
	/**
	 * Offer service.
	 *
	 * @var OfferService
	 */
	private OfferService $service;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Offer defaults.
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
	 * @param OfferService  $service      Offer service.
	 * @param Settings      $settings     Settings service.
	 * @param OfferDefaults $defaults     Offer defaults.
	 * @param callable|null $can_manage   Capability callback.
	 * @param callable|null $verify_nonce Nonce callback.
	 */
	public function __construct( OfferService $service, Settings $settings, OfferDefaults $defaults, ?callable $can_manage = null, ?callable $verify_nonce = null ) {
		$this->service      = $service;
		$this->settings     = $settings;
		$this->defaults     = $defaults;
		$this->can_manage   = $can_manage ?? static fn (): bool => function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		$this->verify_nonce = $verify_nonce ?? static fn ( string $nonce ): bool => function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, 'upsellbay_wizard' );
	}

	/**
	 * Complete onboarding by creating a draft offer and enabling preview mode.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array{success: bool, message: string, offer_id?: int}
	 */
	public function complete( array $request ): array {
		if ( ! ( $this->can_manage )() ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to run the UpsellBay wizard.', 'upsellbay' ),
			);
		}

		if ( ! ( $this->verify_nonce )( (string) ( $request['nonce'] ?? '' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Wizard save could not be verified. Please try again.', 'upsellbay' ),
			);
		}

		$placement = $this->sanitize_key( (string) ( $request['placement'] ?? 'checkout_bump' ) );
		$meta      = $this->defaults->for_type( $placement );
		$meta      = array_replace(
			$meta,
			array(
				'_ub_offer_product_id' => max( 0, (int) ( $request['offer_product_id'] ?? 0 ) ),
				'_ub_headline'         => $this->sanitize_text( (string) ( $request['headline'] ?? $meta['_ub_headline'] ) ),
				'_ub_discount_type'    => $this->sanitize_key( (string) ( $request['discount_type'] ?? $meta['_ub_discount_type'] ) ),
				'_ub_discount_value'   => (string) ( $request['discount_value'] ?? $meta['_ub_discount_value'] ),
				'_ub_rules'            => $this->rule_from_request( $request ),
			)
		);

		try {
			$offer_id = $this->service->create(
				array(
					'title' => __( 'Checkout bump offer', 'upsellbay' ),
					'meta'  => $meta,
				)
			);
		} catch ( Throwable $throwable ) {
			return array(
				'success' => false,
				'message' => $throwable->getMessage(),
			);
		}

		$settings                        = $this->settings->all();
		$settings['wizard_completed']    = true;
		$settings['wizard_completed_at'] = function_exists( 'time' ) ? time() : 0;
		$settings['first_offer_id']      = $offer_id;
		if ( isset( $request['enable_test_mode'] ) && '' !== (string) $request['enable_test_mode'] ) {
			$settings['test_mode'] = true;
		}
		$this->settings->update( $settings );

		return array(
			'success'  => true,
			'message'  => __( 'First offer draft created.', 'upsellbay' ),
			'offer_id' => $offer_id,
		);
	}

	/**
	 * Render wizard shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		echo '<div class="wrap woocommerce upsellbay-admin">';
		$this->render_content();
		echo '</div>';
	}

	/**
	 * Render setup tab content.
	 *
	 * @since 1.0.0
	 */
	public function render_content(): void {
		$template = dirname( __DIR__, 3 ) . '/templates/admin/wizard.php';
		if ( file_exists( $template ) ) {
			include $template;
			return;
		}
	}

	/**
	 * Build one optional targeting rule from wizard fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array<int, array<string, mixed>>
	 */
	private function rule_from_request( array $request ): array {
		$type = $this->sanitize_key( (string) ( $request['rule_type'] ?? '' ) );
		if ( '' === $type ) {
			return array();
		}

		return array(
			array(
				'type'     => $type,
				'operator' => $this->sanitize_key( (string) ( $request['rule_operator'] ?? 'gte' ) ),
				'value'    => $this->sanitize_text( (string) ( $request['rule_value'] ?? '' ) ),
			),
		);
	}

	/**
	 * Sanitize a key.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_key( string $value ): string {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}

	/**
	 * Sanitize text.
	 *
	 * @param string $value Raw value.
	 */
	private function sanitize_text( string $value ): string {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( preg_replace( '/<[^>]*>/', '', $value ) ?? '' );
	}
}
