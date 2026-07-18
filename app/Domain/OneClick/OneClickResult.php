<?php
/**
 * One-click post-purchase charge result.
 *
 * @package UpsellBay\Domain\OneClick
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\OneClick;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Immutable outcome of a one-click post-purchase charge attempt.
 *
 * @since 1.1.0
 */
final class OneClickResult {
	/**
	 * A new linked order was created and the saved payment method was charged.
	 */
	public const STATUS_CHARGED = 'charged';

	/**
	 * The saved payment method was declined; no paid order remains.
	 */
	public const STATUS_DECLINED = 'declined';

	/**
	 * The gateway needs customer authentication (e.g. 3DS) before the charge
	 * can complete — the caller must present a challenge and confirm.
	 */
	public const STATUS_REQUIRES_ACTION = 'requires_action';

	/**
	 * This order/gateway cannot do an off-session one-click charge; the caller
	 * should fall back to the standard separate-checkout offer flow.
	 */
	public const STATUS_UNSUPPORTED = 'unsupported';

	/**
	 * This offer was already accepted for this source order — the existing
	 * follow-on order is returned rather than charging again.
	 */
	public const STATUS_DUPLICATE = 'duplicate';

	/**
	 * Outcome status.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Resulting order ID (0 when none).
	 *
	 * @var int
	 */
	private int $order_id;

	/**
	 * Human-readable message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Gateway client secret for an authentication challenge (requires_action only).
	 *
	 * @var string
	 */
	private string $client_secret;

	/**
	 * Constructor.
	 *
	 * @param string $status        Outcome status.
	 * @param int    $order_id      Resulting order ID.
	 * @param string $message       Message.
	 * @param string $client_secret Auth challenge secret.
	 */
	private function __construct( string $status, int $order_id, string $message, string $client_secret = '' ) {
		$this->status        = $status;
		$this->order_id      = $order_id;
		$this->message       = $message;
		$this->client_secret = $client_secret;
	}

	/**
	 * A successful charge.
	 *
	 * @param int    $order_id New order ID.
	 * @param string $message  Message.
	 */
	public static function charged( int $order_id, string $message = '' ): self {
		return new self( self::STATUS_CHARGED, $order_id, $message );
	}

	/**
	 * A declined charge.
	 *
	 * @param string $message Message.
	 */
	public static function declined( string $message = '' ): self {
		return new self( self::STATUS_DECLINED, 0, $message );
	}

	/**
	 * An authentication challenge is required.
	 *
	 * @param int    $order_id      Pending order ID.
	 * @param string $client_secret Gateway client secret.
	 * @param string $message       Message.
	 */
	public static function requires_action( int $order_id, string $client_secret, string $message = '' ): self {
		return new self( self::STATUS_REQUIRES_ACTION, $order_id, $message, $client_secret );
	}

	/**
	 * The gateway/order cannot do one-click.
	 *
	 * @param string $message Message.
	 */
	public static function unsupported( string $message = '' ): self {
		return new self( self::STATUS_UNSUPPORTED, 0, $message );
	}

	/**
	 * The offer was already accepted for this order.
	 *
	 * @param int $order_id Existing follow-on order ID.
	 */
	public static function duplicate( int $order_id ): self {
		return new self( self::STATUS_DUPLICATE, $order_id, 'Offer already accepted for this order.' );
	}

	/**
	 * Status.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Resulting order ID.
	 */
	public function order_id(): int {
		return $this->order_id;
	}

	/**
	 * Message.
	 */
	public function message(): string {
		return $this->message;
	}

	/**
	 * Auth challenge secret.
	 */
	public function client_secret(): string {
		return $this->client_secret;
	}

	/**
	 * Whether the caller should fall back to the separate-checkout flow.
	 */
	public function should_fall_back(): bool {
		return self::STATUS_UNSUPPORTED === $this->status || self::STATUS_DECLINED === $this->status;
	}
}
