<?php
/**
 * WooCommerce cart session offer state.
 *
 * @package UpsellBay\Data
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Utils\TokenHelper;

/**
 * Stores non-PII accepted/dismissed offer state in the WooCommerce session.
 *
 * @since 1.0.0
 */
final class CartSession {
	private const SESSION_KEY = 'upsellbay_offer_state';

	/**
	 * Session reader.
	 *
	 * @var callable(string): mixed
	 */
	private $reader;

	/**
	 * Session writer.
	 *
	 * @var callable(string, mixed): void
	 */
	private $writer;

	/**
	 * Token helper.
	 *
	 * @var TokenHelper
	 */
	private TokenHelper $tokens;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null    $reader Session reader.
	 * @param callable|null    $writer Session writer.
	 * @param TokenHelper|null $tokens Token helper.
	 */
	public function __construct( ?callable $reader = null, ?callable $writer = null, ?TokenHelper $tokens = null ) {
		$this->reader = $reader ?? array( $this, 'wc_session_get' );
		$this->writer = $writer ?? array( $this, 'wc_session_set' );
		$this->tokens = $tokens ?? new TokenHelper();
	}

	/**
	 * Return normalized session state.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function state(): array {
		$state = ( $this->reader )( self::SESSION_KEY );
		$state = is_array( $state ) ? $state : array();

		return array(
			'accepted'        => is_array( $state['accepted'] ?? null ) ? $state['accepted'] : array(),
			'dismissed'       => is_array( $state['dismissed'] ?? null ) ? $state['dismissed'] : array(),
			'token_hash'      => (string) ( $state['token_hash'] ?? '' ),
			'token_raw'       => (string) ( $state['token_raw'] ?? '' ),
			'token_issued_at' => (int) ( $state['token_issued_at'] ?? 0 ),
		);
	}

	/**
	 * Store accepted offer state.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id      Offer ID.
	 * @param string               $placement     Placement key.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $extra         Extra non-PII state.
	 */
	public function accept_offer( int $offer_id, string $placement, string $cart_item_key, array $extra = array() ): void {
		$state                          = $this->state();
		$state['accepted'][ $offer_id ] = array_filter(
			array_merge(
				array(
					'placement'     => $placement,
					'cart_item_key' => $cart_item_key,
					'accepted_at'   => time(),
				),
				$extra
			),
			static fn ( $value ): bool => null !== $value && '' !== $value && 0 !== $value
		);
		$this->save( $state );
	}

	/**
	 * Store dismissed offer state.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $offer_id  Offer ID.
	 * @param string $placement Placement key.
	 */
	public function dismiss_offer( int $offer_id, string $placement ): void {
		$state                           = $this->state();
		$state['dismissed'][ $offer_id ] = array(
			'placement'    => $placement,
			'dismissed_at' => time(),
		);
		$this->save( $state );
	}

	/**
	 * Remove accepted offer state after a cart item is removed.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function forget_accepted_offer( int $offer_id ): void {
		$state = $this->state();
		unset( $state['accepted'][ $offer_id ] );
		$this->save( $state );
	}

	/**
	 * Ensure a REST validation token exists and is reused if still fresh.
	 *
	 * @since 1.0.0
	 */
	public function ensure_token(): string {
		$state     = $this->state();
		$issued_at = (int) ( $state['token_issued_at'] ?? 0 );
		$token_raw = (string) ( $state['token_raw'] ?? '' );
		$day       = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;

		if ( '' !== $token_raw && $issued_at > ( time() - $day ) ) {
			return $token_raw;
		}

		$token                    = $this->tokens->generate( 32 );
		$state['token_raw']       = $token;
		$state['token_hash']      = $this->tokens->hash( $token );
		$state['token_issued_at'] = time();
		$this->save( $state );

		return $token;
	}

	/**
	 * Validate a REST token without storing raw token values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Raw token.
	 */
	public function validate_token( string $token ): bool {
		$state = $this->state();
		return '' !== $state['token_hash'] && $this->tokens->verify( $token, $state['token_hash'] );
	}

	/**
	 * Save normalized state.
	 *
	 * @param array<string, mixed> $state State.
	 */
	private function save( array $state ): void {
		unset( $state['email'], $state['customer_id'], $state['phone'] );
		( $this->writer )( self::SESSION_KEY, $state );
	}

	/**
	 * Woo session getter.
	 *
	 * @param string $key Session key.
	 * @return mixed
	 */
	private function wc_session_get( string $key ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return WC()->session->get( $key );
		}

		return null;
	}

	/**
	 * Woo session setter.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 */
	private function wc_session_set( string $key, $value ): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( $key, $value );
		}
	}
}
