<?php
/**
 * Admin tab factory.
 *
 * @package UpsellBay\Admin\Navigation
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Admin\Navigation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Admin\Dashboard\DashboardPage;
use WPAnchorBay\UpsellBay\Admin\Help\HelpPage;
use WPAnchorBay\UpsellBay\Admin\Offers\OfferEditPage;
use WPAnchorBay\UpsellBay\Admin\Offers\OffersPage;
use WPAnchorBay\UpsellBay\Admin\Settings\SettingsPage;
use WPAnchorBay\UpsellBay\Admin\Tools\ToolsPage;
use WPAnchorBay\UpsellBay\Admin\Wizard\WizardController;

/**
 * Builds the internal UpsellBay admin tab registry.
 *
 * @since 1.0.0
 */
final class TabFactory {
	/**
	 * Dashboard tab renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var DashboardPage
	 */
	private DashboardPage $dashboard;

	/**
	 * Offers list renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var OffersPage
	 */
	private OffersPage $offers;

	/**
	 * Offer editor renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferEditPage
	 */
	private OfferEditPage $offer_editor;

	/**
	 * Settings renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * Tools renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var ToolsPage
	 */
	private ToolsPage $tools;

	/**
	 * Setup wizard renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var WizardController
	 */
	private WizardController $wizard;

	/**
	 * Help renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var HelpPage
	 */
	private HelpPage $help;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param DashboardPage    $dashboard    Dashboard renderer.
	 * @param OffersPage       $offers       Offers list renderer.
	 * @param OfferEditPage    $offer_editor Offer editor renderer.
	 * @param SettingsPage     $settings     Settings renderer.
	 * @param ToolsPage        $tools        Tools renderer.
	 * @param WizardController $wizard       Setup wizard renderer.
	 * @param HelpPage         $help         Help renderer.
	 */
	public function __construct( DashboardPage $dashboard, OffersPage $offers, OfferEditPage $offer_editor, SettingsPage $settings, ToolsPage $tools, WizardController $wizard, HelpPage $help ) {
		$this->dashboard    = $dashboard;
		$this->offers       = $offers;
		$this->offer_editor = $offer_editor;
		$this->settings     = $settings;
		$this->tools        = $tools;
		$this->wizard       = $wizard;
		$this->help         = $help;
	}

	/**
	 * Build the tab registry.
	 *
	 * @since 1.0.0
	 */
	public function registry(): TabRegistry {
		return new TabRegistry(
			array(
				new AdminTab(
					'dashboard',
					__( 'Dashboard', 'upsellbay' ),
					function ( array $request ): void {
						$this->dashboard->render( $request );
					}
				),
				new AdminTab(
					'offers',
					__( 'Offers', 'upsellbay' ),
					function ( array $request ): void {
						if ( 'edit' === $this->request_key( $request['action'] ?? '' ) ) {
							$this->offer_editor->render_content();
							return;
						}

						$this->offers->render_content( $request );
					},
					function ( array $request ): void {
						$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
						if ( 'POST' === $method && 'edit' === $this->request_key( $request['action'] ?? '' ) ) {
							// phpcs:ignore WordPress.Security.NonceVerification.Missing
							$result = $this->offer_editor->save( $_POST );
							// phpcs:ignore WordPress.Security.NonceVerification.Missing
							$posted_offer_id = isset( $_POST['offer_id'] ) ? (int) $_POST['offer_id'] : 0;

							$redirect_url = admin_url( 'admin.php?page=upsellbay&tab=offers&action=edit' );
							if ( isset( $result['offer_id'] ) ) {
								$redirect_url = add_query_arg( 'offer_id', $result['offer_id'], $redirect_url );
							} elseif ( $posted_offer_id > 0 ) {
								$redirect_url = add_query_arg( 'offer_id', $posted_offer_id, $redirect_url );
							}

							if ( true === $result['success'] ) {
								$redirect_url = add_query_arg( 'wc_message', rawurlencode( __( 'Offer saved successfully.', 'upsellbay' ) ), $redirect_url );
							} else {
								$redirect_url = add_query_arg( 'wc_error', rawurlencode( $result['message'] ), $redirect_url );
							}

							wp_safe_redirect( $redirect_url );
							exit;
						}
					}
				),
				new AdminTab(
					'settings',
					__( 'Settings', 'upsellbay' ),
					function ( array $request ): void {
						$this->settings->render_content( $request );
					},
					function ( array $request ): void {
						unset( $request );
						$this->settings->prepare_render();
					}
				),
				new AdminTab(
					'tools',
					__( 'Tools', 'upsellbay' ),
					function ( array $request ): void {
						unset( $request );
						$this->tools->render_content();
					}
				),
				new AdminTab(
					'setup',
					$this->wizard->is_completed() ? __( 'Setup', 'upsellbay' ) : __( 'Get started', 'upsellbay' ),
					function ( array $request ): void {
						unset( $request );
						$this->wizard->render_content();
					},
					null,
					false
				),
				new AdminTab(
					'help',
					__( 'Help', 'upsellbay' ),
					function ( array $request ): void {
						unset( $request );
						$this->help->render_content();
					}
				),
			)
		);
	}

	/**
	 * Sanitize a request key.
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
}
