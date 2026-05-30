<?php
/**
 * Settings admin page.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

use WPAnchorBay\UpsellBay\Core\Settings;

/**
 * Handles Woo-style settings sections and saves.
 *
 * @since 1.0.0
 */
final class SettingsPage {
	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 *
	 * @var Settings
	 */
	private Settings $settings;

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
	 * Settings sections.
	 *
	 * @var array<string, SettingsSectionInterface>
	 */
	private array $sections;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings                             $settings     Settings service.
	 * @param callable|null                        $can_manage   Capability callback.
	 * @param callable|null                        $verify_nonce Nonce callback.
	 * @param array<SettingsSectionInterface>|null $sections Sections.
	 */
	public function __construct( Settings $settings, ?callable $can_manage = null, ?callable $verify_nonce = null, ?array $sections = null ) {
		$this->settings     = $settings;
		$this->can_manage   = $can_manage ?? static fn (): bool => function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		$this->verify_nonce = $verify_nonce ?? static fn ( string $nonce ): bool => function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, 'upsellbay_save_settings' );
		$this->sections     = array();

		foreach ( $sections ?? array( new GeneralSection(), new StyleSection(), new DataSection() ) as $section ) {
			$this->sections[ $section->id() ] = $section;
		}
	}

	/**
	 * Return sections keyed by ID.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, SettingsSectionInterface>
	 */
	public function sections(): array {
		return $this->sections;
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array{success: bool, message: string}
	 */
	public function save( array $request ): array {
		if ( ! ( $this->can_manage )() ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage UpsellBay settings.', 'upsellbay' ),
			);
		}

		if ( ! ( $this->verify_nonce )( (string) ( $request['nonce'] ?? '' ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Settings save could not be verified. Please try again.', 'upsellbay' ),
			);
		}

		$next = $this->settings->all();
		foreach ( $this->sections as $section ) {
			$next = $section->apply( $request, $next );
		}

		$this->settings->update( $next );

		return array(
			'success' => true,
			'message' => __( 'Settings saved.', 'upsellbay' ),
		);
	}

	/**
	 * Render settings shell.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		echo '<div class="wrap woocommerce"><h1>' . esc_html__( 'UpsellBay Settings', 'upsellbay' ) . '</h1></div>';
	}
}
