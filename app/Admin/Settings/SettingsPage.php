<?php
/**
 * Settings admin page.
 *
 * @package UpsellBay\Admin\Settings
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Settings;

use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;

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
	 * License client.
	 *
	 * @since 1.0.0
	 *
	 * @var LicenseClient
	 */
	private LicenseClient $license_client;

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
	 * Settings section navigation.
	 *
	 * @since 1.0.0
	 *
	 * @var SettingsSectionNavigation
	 */
	private SettingsSectionNavigation $section_navigation;

	/**
	 * Settings save result prepared for the current render.
	 *
	 * @since 1.0.0
	 *
	 * @var array{success: bool, message: string}|null
	 */
	private ?array $prepared_save_result = null;

	/**
	 * Whether the prepared save notice hook has been registered.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $prepared_notice_registered = false;

	/**
	 * Whether the prepared save notice has already been rendered.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $prepared_notice_rendered = false;

	/**
	 * Whether posted settings were already handled for this render.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $posted_settings_handled = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings                                      $settings       Settings service.
	 * @param LicenseClient|callable|null                   $license_client License client, or legacy capability callback.
	 * @param callable|null                                 $can_manage     Capability callback, or legacy nonce callback.
	 * @param callable|array<SettingsSectionInterface>|null $verify_nonce Nonce callback, or legacy sections.
	 * @param array<SettingsSectionInterface>|null          $sections       Sections.
	 * @param SettingsSectionNavigation|null                $section_navigation Settings section navigation.
	 */
	public function __construct( Settings $settings, LicenseClient|callable|null $license_client = null, ?callable $can_manage = null, callable|array|null $verify_nonce = null, ?array $sections = null, ?SettingsSectionNavigation $section_navigation = null ) {
		if ( is_callable( $license_client ) ) {
			$sections       = is_array( $verify_nonce ) ? $verify_nonce : $sections;
			$verify_nonce   = $can_manage;
			$can_manage     = $license_client;
			$license_client = null;
		}

		$this->settings           = $settings;
		$this->license_client     = $license_client ?? new LicenseClient();
		$this->can_manage         = $can_manage ?? static fn (): bool => function_exists( 'current_user_can' ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		$this->verify_nonce       = is_callable( $verify_nonce ) ? $verify_nonce : static fn ( string $nonce ): bool => function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, 'upsellbay_save_settings' );
		$this->sections           = array();
		$this->section_navigation = $section_navigation ?? new SettingsSectionNavigation();

		foreach ( $sections ?? array( new BasicSection(), new StyleSection(), new DataSection() ) as $section ) {
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

		$license_result = $this->maybe_activate_license_from_request( $request );

		if ( is_wp_error( $license_result ) ) {
			return array(
				'success' => false,
				'message' => $license_result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => true === $license_result ? __( 'Settings saved and license activated successfully.', 'upsellbay' ) : __( 'Settings saved.', 'upsellbay' ),
		);
	}

	/**
	 * Activate a posted license key during the normal settings save.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return true|null|\WP_Error
	 */
	private function maybe_activate_license_from_request( array $request ) {
		if ( ! isset( $request['upsellbay_new_license_key'] ) ) {
			return null;
		}

		$license_key = sanitize_text_field( (string) $request['upsellbay_new_license_key'] );

		if ( '' === $license_key ) {
			return null;
		}

		if ( ! preg_match( '/^WPAB-[A-Z0-9]+-[A-Z0-9]+$/', $license_key ) ) {
			return new \WP_Error(
				'upsellbay_license_invalid_format',
				__( 'Invalid license format. Expected: WPAB-XXXXXXXXXXXX-XXXXXXXXXXXX', 'upsellbay' )
			);
		}

		return $this->license_client->activate( $license_key );
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
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function render_content( array $request = array() ): void {
		$this->prepare_render();
		if ( null !== $this->prepared_save_result && ! $this->prepared_notice_rendered ) {
			$this->render_prepared_save_notice( true );
		}

		$settings       = $this->settings->all();
		$active_section = $this->current_section( $request );

		echo '<form method="post">';
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( 'upsellbay_save_settings', 'nonce' );
		}
		$this->section_navigation->render( $active_section );

		if ( 'data' === $active_section ) {
			$this->render_data_section( $settings );
		} elseif ( 'license' === $active_section ) {
			$this->render_license_settings_section();
		} else {
			$this->render_general_settings_section( $settings );
		}

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'upsellbay' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Render the General menu section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Normalized settings.
	 */
	private function render_general_settings_section( array $settings ): void {
		$placements = is_array( $settings['placements'] ?? null ) ? $settings['placements'] : array();
		$style      = is_array( $settings['style_tokens'] ?? null ) ? $settings['style_tokens'] : array();

		echo '<h2>' . esc_html__( 'Basic', 'upsellbay' ) . '</h2>';
		echo '<table class="form-table upsellbay-settings-table" role="presentation"><tbody>';
		$this->checkbox_row( 'enabled', __( 'Enable offers', 'upsellbay' ), (bool) $settings['enabled'], __( 'Allow eligible live offers to render on enabled placements.', 'upsellbay' ), __( 'Turn this off to pause all shopper-facing UpsellBay offers without changing individual offer status.', 'upsellbay' ) );
		$this->checkbox_row( 'test_mode', __( 'Test mode', 'upsellbay' ), (bool) $settings['test_mode'], __( 'Limit previews and draft offer checks to store managers before shoppers see them.', 'upsellbay' ), __( 'Use test mode while configuring offers. It should be disabled before live shoppers need to see offers.', 'upsellbay' ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help tip markup.
		echo '<tr><th scope="row">' . esc_html__( 'Placements', 'upsellbay' ) . ' ' . $this->help_tip( __( 'Choose where UpsellBay is allowed to render active, eligible offers. Individual offers still need matching placement settings.', 'upsellbay' ) ) . '</th><td>';
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help tip markup.
		echo '<tr><th scope="row"><label for="upsellbay-accent-color">' . esc_html__( 'Accent color', 'upsellbay' ) . '</label> ' . $this->help_tip( __( 'Used for UpsellBay offer accents while preserving the theme and WooCommerce layout.', 'upsellbay' ) ) . '</th><td><input id="upsellbay-accent-color" name="accent_color" type="text" class="regular-text" value="' . esc_attr( (string) ( $style['accent_color'] ?? '#2271b1' ) ) . '"></td></tr>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help tip markup.
		echo '<tr><th scope="row"><label for="upsellbay-button-style">' . esc_html__( 'Button style', 'upsellbay' ) . '</label> ' . $this->help_tip( __( 'Theme buttons inherit the storefront button styling. Outline keeps the offer action visually lighter.', 'upsellbay' ) ) . '</th><td><select id="upsellbay-button-style" name="button_style">';
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
	}

	/**
	 * Render the Data menu section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Normalized settings.
	 */
	private function render_data_section( array $settings ): void {
		$retention = is_array( $settings['data_retention'] ?? null ) ? $settings['data_retention'] : array();

		echo '<h2>' . esc_html__( 'Data', 'upsellbay' ) . '</h2>';
		echo '<table class="form-table upsellbay-settings-table" role="presentation"><tbody>';
		foreach (
			array(
				'stats_days'   => __( 'Stats retention days', 'upsellbay' ),
				'session_days' => __( 'Session retention days', 'upsellbay' ),
				'log_days'     => __( 'Log retention days', 'upsellbay' ),
			) as $key => $label
		) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help tip markup.
			echo '<tr><th scope="row"><label for="upsellbay-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> ' . $this->help_tip( $this->retention_help_text( $key ) ) . '</th><td><input id="upsellbay-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="number" min="1" class="small-text" value="' . esc_attr( (string) ( $retention[ $key ] ?? 30 ) ) . '"></td></tr>';
		}
		$this->checkbox_row( 'cleanup_on_delete', __( 'Delete data on uninstall', 'upsellbay' ), (bool) $settings['cleanup_on_delete'], __( 'Keep this off unless the merchant explicitly wants plugin data removed during uninstall.', 'upsellbay' ), __( 'When enabled, uninstall cleanup can remove UpsellBay settings, offers, and aggregate stats instead of preserving them.', 'upsellbay' ) );
		echo '</tbody></table>';
	}

	/**
	 * Render the License menu section.
	 *
	 * @since 1.0.0
	 */
	private function render_license_settings_section(): void {
		echo '<h2>' . esc_html__( 'License', 'upsellbay' ) . '</h2>';
		echo '<table class="form-table upsellbay-settings-table" role="presentation"><tbody>';
		$this->render_license_section();
		echo '</tbody></table>';
	}

	/**
	 * Return the active visible Settings section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	private function current_section( array $request ): string {
		$section = $this->request_key( $request['section'] ?? '' );

		if ( in_array( $section, array( 'general', 'data', 'license' ), true ) ) {
			return $section;
		}

		return 'general';
	}

	/**
	 * Sanitize a request key with a fallback for isolated tests.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 */
	private function request_key( $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( (string) $value );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ?? '' );
	}

	/**
	 * Process posted settings before rendering any page-level notices.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string}|null
	 */
	public function prepare_render(): ?array {
		if ( $this->posted_settings_handled ) {
			return $this->prepared_save_result;
		}

		$this->prepared_save_result    = $this->maybe_handle_posted_settings();
		$this->posted_settings_handled = true;
		if ( null !== $this->prepared_save_result && ! $this->prepared_notice_registered ) {
			add_action( 'upsellbay_admin_page_heading_before', array( $this, 'render_prepared_save_notice' ) );
			$this->prepared_notice_registered = true;
		}

		return $this->prepared_save_result;
	}

	/**
	 * Render the prepared settings save notice above the attached page header.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $inline Whether to render the notice inline for standalone settings pages.
	 */
	public function render_prepared_save_notice( bool $inline = false ): void {
		if ( null === $this->prepared_save_result ) {
			return;
		}

		$notice_class                   = true === $this->prepared_save_result['success'] ? 'notice-success' : 'notice-error';
		$this->prepared_notice_rendered = true;

		if ( $inline ) {
			echo '<div class="notice ' . esc_attr( $notice_class ) . ' inline is-dismissible"><p>' . esc_html( $this->prepared_save_result['message'] ) . '</p></div>';
			return;
		}

		echo '<div class="notice ' . esc_attr( $notice_class ) . ' upsellbay-page-notice is-dismissible"><p>' . esc_html( $this->prepared_save_result['message'] ) . '</p></div>';
	}

	/**
	 * Save settings when the settings tab form posts back to itself.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string}|null
	 */
	private function maybe_handle_posted_settings(): ?array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Presence is checked before sanitizing the method string.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( 'POST' !== $method ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- save() verifies the submitted nonce and sanitizes section values.
		$request = wp_unslash( $_POST );

		return $this->save( $request );
	}

	/**
	 * Render the license section with status, key display, and actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_license_section(): void {
		$license_status = $this->license_client->get_status();
		$masked_key     = $this->license_client->get_masked_key();
		$status_code    = $license_status['status'] ?? 'inactive';
		$expires_at     = $license_status['expires_at'] ?? '';
		$plan           = $license_status['plan'] ?? '';

		// Status row.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr><th scope="row">' . esc_html__( 'Status', 'upsellbay' ) . ' ' . $this->help_tip( __( 'Shows the most recent license state returned by UpsellBay licensing.', 'upsellbay' ) ) . '</th>';
		echo '<td>' . $this->license_badge( $status_code ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' !== $masked_key ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<tr><th scope="row">' . esc_html__( 'Current Key', 'upsellbay' ) . ' ' . $this->help_tip( __( 'Only a masked version is shown here. The full key stays stored in the protected license option.', 'upsellbay' ) ) . '</th>';
			echo '<td><code>' . esc_html( $masked_key ) . '</code></td></tr>';

			if ( '' !== $expires_at ) {
				$expiry_timestamp = strtotime( $expires_at );
				$expiry_date      = $expiry_timestamp > 0 ? wp_date( 'Y-m-d', $expiry_timestamp ) : '';
				$is_expired       = $expiry_timestamp > 0 && $expiry_timestamp < time();
				$expiry_html      = $is_expired
					? '<span style="color:#d63638;">' . esc_html( $expiry_date ) . ' (' . esc_html__( 'Expired', 'upsellbay' ) . ')</span>'
					: '<span style="color:#007017;">' . esc_html( $expiry_date ) . '</span>';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<tr><th scope="row">' . esc_html__( 'Expires', 'upsellbay' ) . ' ' . $this->help_tip( __( 'The date your current license term ends according to the license server.', 'upsellbay' ) ) . '</th>';
				echo '<td>' . $expiry_html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			if ( '' !== $plan ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<tr><th scope="row">' . esc_html__( 'Plan', 'upsellbay' ) . ' ' . $this->help_tip( __( 'The product plan currently associated with the active UpsellBay license.', 'upsellbay' ) ) . '</th>';
				echo '<td>' . esc_html( ucfirst( $plan ) ) . '</td></tr>';
			}

			// Check and Remove actions.
			$check_nonce  = wp_create_nonce( 'upsellbay_check_license' );
			$remove_nonce = wp_create_nonce( 'upsellbay_remove_license' );
			$check_url    = admin_url( 'admin-post.php?action=upsellbay_check_license&_wpnonce=' . $check_nonce );
			$remove_url   = admin_url( 'admin-post.php?action=upsellbay_remove_license&_wpnonce=' . $remove_nonce );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<tr><th scope="row">' . esc_html__( 'Actions', 'upsellbay' ) . ' ' . $this->help_tip( __( 'Use these tools to verify the current license status or remove a stored key from this site.', 'upsellbay' ) ) . '</th>';
			echo '<td>';
			echo '<a href="' . esc_url( $check_url ) . '" class="button">' . esc_html__( 'Check License', 'upsellbay' ) . '</a> ';
			echo '<a href="' . esc_url( $remove_url ) . '" class="button button-secondary upsellbay-button-danger" onclick="return confirm(\'' . esc_js( __( 'Removing this license disconnects this site from UpsellBay updates and support checks until a new key is activated. Offers will continue running.', 'upsellbay' ) ) . '\');">' . esc_html__( 'Remove License', 'upsellbay' ) . '</a>';
			echo '</td></tr>';
		}

		// Activation field for new key.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr id="upsellbay_license_activate"><th scope="row"><label for="upsellbay_new_license_key">' . esc_html__( 'Activate New Key', 'upsellbay' ) . '</label> ' . $this->help_tip( __( 'Paste a new UpsellBay license key here to replace the currently stored key without exposing it in normal option fields.', 'upsellbay' ) ) . '</th>';
		echo '<td>';
		echo '<input type="text" name="upsellbay_new_license_key" id="upsellbay_new_license_key" value="" placeholder="WPAB-XXXXXXXXXXXX-XXXXXXXXXXXX" class="regular-text" autocomplete="off" />';
		echo ' <button type="submit" name="upsellbay_activate_license" value="1" class="button button-primary">' . esc_html__( 'Activate License', 'upsellbay' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Leave blank to keep the existing key unchanged.', 'upsellbay' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Render a license status badge.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status License status code.
	 *
	 * @return string Badge HTML.
	 */
	private function license_badge( string $status ): string {
		$label = match ( $status ) {
			'active'       => __( 'Active', 'upsellbay' ),
			'inactive'     => __( 'Inactive', 'upsellbay' ),
			'expired'      => __( 'Expired', 'upsellbay' ),
			'invalid'      => __( 'Invalid', 'upsellbay' ),
			'dev'          => __( 'Dev Mode', 'upsellbay' ),
			'server_error' => __( 'Server Error', 'upsellbay' ),
			default        => __( 'Unknown', 'upsellbay' ),
		};

		$badge_class = match ( $status ) {
			'active'       => 'upsellbay-badge--active',
			'inactive'     => 'upsellbay-badge--inactive',
			'expired'      => 'upsellbay-badge--expired',
			'invalid'      => 'upsellbay-badge--invalid',
			'dev'          => 'upsellbay-badge--dev',
			'server_error' => 'upsellbay-badge--error',
			default        => 'upsellbay-badge--unknown',
		};

		return '<span class="upsellbay-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $label ) . '</span>';
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
	 * @param string $help        Optional help tip text.
	 */
	private function checkbox_row( string $name, string $label, bool $checked, string $description, string $help = '' ): void {
		echo '<tr><th scope="row">' . esc_html( $label );
		if ( '' !== $help ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- help_tip() returns escaped WooCommerce help tip markup.
			echo ' ' . $this->help_tip( $help );
		}
		echo '</th><td><label><input name="' . esc_attr( $name ) . '" type="checkbox" value="1"';
		if ( $checked ) {
			echo ' checked="checked"';
		}
		echo '> ' . esc_html( $description ) . '</label></td></tr>';
	}

	/**
	 * Render a WooCommerce help tip when available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Tip text.
	 * @return string Help tip markup.
	 */
	private function help_tip( string $text ): string {
		if ( function_exists( 'wc_help_tip' ) ) {
			return wc_help_tip( $text, false );
		}

		return '<span class="description">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Return help copy for retention fields.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Retention key.
	 * @return string Help text.
	 */
	private function retention_help_text( string $key ): string {
		return match ( $key ) {
			'stats_days' => __( 'How long aggregate, non-PII offer analytics are retained for dashboard reporting.', 'upsellbay' ),
			'session_days' => __( 'How long temporary offer session state is retained for shopper interactions such as dismissals.', 'upsellbay' ),
			default => __( 'How long UpsellBay operational logs are retained for troubleshooting.', 'upsellbay' ),
		};
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
