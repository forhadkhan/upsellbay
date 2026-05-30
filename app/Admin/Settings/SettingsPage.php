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
		echo '<div class="wrap woocommerce upsellbay-admin">';
		$this->render_content();
		echo '</div>';
	}

	/**
	 * Render settings tab content.
	 *
	 * @since 1.0.0
	 */
	public function render_content(): void {
		$settings   = $this->settings->all();
		$placements = is_array( $settings['placements'] ?? null ) ? $settings['placements'] : array();
		$style      = is_array( $settings['style_tokens'] ?? null ) ? $settings['style_tokens'] : array();
		$retention  = is_array( $settings['data_retention'] ?? null ) ? $settings['data_retention'] : array();

		echo '<h2>' . esc_html__( 'Settings', 'upsellbay' ) . '</h2>';
		echo '<form method="post">';
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( 'upsellbay_save_settings', 'nonce' );
		}
		echo '<h2>' . esc_html__( 'General', 'upsellbay' ) . '</h2>';
		echo '<table class="form-table upsellbay-settings-table" role="presentation"><tbody>';
		$this->checkbox_row( 'enabled', __( 'Enable offers', 'upsellbay' ), (bool) $settings['enabled'], __( 'Allow eligible live offers to render on enabled placements.', 'upsellbay' ) );
		$this->checkbox_row( 'test_mode', __( 'Test mode', 'upsellbay' ), (bool) $settings['test_mode'], __( 'Limit previews and draft offer checks to store managers before shoppers see them.', 'upsellbay' ) );
		echo '<tr><th scope="row">' . esc_html__( 'Placements', 'upsellbay' ) . '</th><td>';
		foreach ( $this->placement_labels() as $key => $label ) {
			$is_checked = true === (bool) ( $placements[ $key ] ?? false );
			echo '<label class="upsellbay-checkbox-row"><input type="checkbox" name="placements[' . esc_attr( $key ) . ']" value="1"';
			if ( $is_checked ) {
				echo ' checked="checked"';
			}
			echo '> ' . esc_html( $label ) . '</label><br>';
		}
		echo '</td></tr></tbody></table>';

		echo '<h2>' . esc_html__( 'Style', 'upsellbay' ) . '</h2>';
		echo '<table class="form-table upsellbay-settings-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="upsellbay-accent-color">' . esc_html__( 'Accent color', 'upsellbay' ) . '</label></th><td><input id="upsellbay-accent-color" name="accent_color" type="text" class="regular-text" value="' . esc_attr( (string) ( $style['accent_color'] ?? '#2271b1' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="upsellbay-button-style">' . esc_html__( 'Button style', 'upsellbay' ) . '</label></th><td><select id="upsellbay-button-style" name="button_style">';
		foreach (
			array(
				'theme'   => __( 'Use theme buttons', 'upsellbay' ),
				'outline' => __( 'Outline', 'upsellbay' ),
			) as $value => $label
		) {
			echo '<option value="' . esc_attr( $value ) . '"';
			if ( ( $style['button_style'] ?? 'theme' ) === $value ) {
				echo ' selected="selected"';
			}
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr></tbody></table>';

		echo '<h2>' . esc_html__( 'Data', 'upsellbay' ) . '</h2>';
		echo '<table class="form-table upsellbay-settings-table" role="presentation"><tbody>';
		foreach (
			array(
				'stats_days'   => __( 'Stats retention days', 'upsellbay' ),
				'session_days' => __( 'Session retention days', 'upsellbay' ),
				'log_days'     => __( 'Log retention days', 'upsellbay' ),
			) as $key => $label
		) {
			echo '<tr><th scope="row"><label for="upsellbay-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="upsellbay-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="number" min="1" class="small-text" value="' . esc_attr( (string) ( $retention[ $key ] ?? 30 ) ) . '"></td></tr>';
		}
		$this->checkbox_row( 'cleanup_on_delete', __( 'Delete data on uninstall', 'upsellbay' ), (bool) $settings['cleanup_on_delete'], __( 'Keep this off unless the merchant explicitly wants plugin data removed during uninstall.', 'upsellbay' ) );
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'upsellbay' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Render a checkbox table row.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param bool   $checked     Whether the field is checked.
	 * @param string $description Field description.
	 */
	private function checkbox_row( string $name, string $label, bool $checked, string $description ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input name="' . esc_attr( $name ) . '" type="checkbox" value="1"';
		if ( $checked ) {
			echo ' checked="checked"';
		}
		echo '> ' . esc_html( $description ) . '</label></td></tr>';
	}

	/**
	 * Return placement labels.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function placement_labels(): array {
		return array(
			'product_upsell' => __( 'Product page offer', 'upsellbay' ),
			'cart_crosssell' => __( 'Cart offer', 'upsellbay' ),
			'checkout_bump'  => __( 'Checkout bump', 'upsellbay' ),
			'thankyou_offer' => __( 'Thank-you offer', 'upsellbay' ),
		);
	}
}
