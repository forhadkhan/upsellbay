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
 * @since 1.0.0
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
	 * can complete. No challenge UI ships yet, so nothing was charged: the
	 * orchestrator cancels the follow-on order and the caller falls back to the
	 * standard separate-checkout flow, where the gateway runs its own challenge.
	 */
	public const STATUS_REQUIRES_ACTION = 'requires_action';

	/**
	 * The charge outcome is unknown: a follow-on order for this offer exists but is
	 * neither paid nor closed (a charge is in flight, was interrupted, failed with an
	 * exception, or money was taken but the order could not be marked paid). Fail
	 * closed: report neither success nor a fallback, so the shopper cannot end up
	 * paying twice.
	 */
	public const STATUS_IN_PROGRESS = 'in_progress';

	/**
	 * This order/gateway cannot do an off-session one-click charge; the caller
	 * should fall back to the standard separate-checkout offer flow.
	 */
	public const STATUS_UNSUPPORTED = 'unsupported';

	/**
	 * This offer was already accepted and paid for this source order — the
	 * existing follow-on order is returned rather than charging again. Only
	 * issued after the existing order is verified paid.
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
	 * A previous attempt for this offer is unresolved (unpaid and not closed).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $order_id Existing follow-on order ID.
	 * @param string $message  Message.
	 */
	public static function in_progress( int $order_id, string $message = '' ): self {
		return new self( self::STATUS_IN_PROGRESS, $order_id, $message );
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
	 *
	 * True only for outcomes where nothing was charged and nothing is pending.
	 */
	public function should_fall_back(): bool {
		return in_array( $this->status, array( self::STATUS_UNSUPPORTED, self::STATUS_DECLINED, self::STATUS_REQUIRES_ACTION ), true );
	}
}
