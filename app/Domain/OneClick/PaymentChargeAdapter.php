<?php
/**
 * One-click payment charge adapter contract.
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
 * Gateway-specific off-session charge behaviour for one-click post-purchase upsells.
 *
 * Each supported payment gateway (Stripe, WooPayments, …) provides one adapter. The
 * one-click orchestration (OneClickCharger) is entirely gateway-agnostic and speaks only
 * to this contract, so adding a gateway is a new adapter, and the whole flow is
 * verifiable against a simulated adapter without a live gateway or real money.
 *
 * @since 1.0.0
 */
interface PaymentChargeAdapter {
	/**
	 * The WooCommerce payment gateway id this adapter handles (e.g. `stripe`).
	 *
	 * @since 1.0.0
	 */
	public function gateway_id(): string;

	/**
	 * Whether this source order can be charged off-session in one click.
	 *
	 * True only when the gateway supports tokenized off-session charges AND the source
	 * order/customer has a usable reusable token. When false, the caller falls back to
	 * the standard separate-checkout offer flow — preserving "works everywhere".
	 *
	 * @since 1.0.0
	 *
	 * @param object $source_order The completed WC_Order whose payment method is reused.
	 */
	public function supports_one_click( object $source_order ): bool;

	/**
	 * Charge the saved payment method for the new (already-built, unpaid) order.
	 *
	 * Implementations MUST be side-effect-safe to call once per new order: on success
	 * mark the order paid via `payment_complete()`; on a definite decline leave it
	 * unpaid (the orchestrator cancels it); on an authentication requirement leave it
	 * unpaid, void the pending gateway authorization where the gateway allows it, and
	 * return `OneClickResult::requires_action()`. No challenge UI ships yet: the
	 * orchestrator cancels the order and the shopper falls back to the separate checkout.
	 *
	 * Return `declined` / `requires_action` / `unsupported` ONLY when the gateway proves
	 * nothing was charged. When the outcome is unknown (timeout, transport error, the
	 * gateway still processing, money taken but the order not marked paid) return
	 * `OneClickResult::in_progress()` with a PII-free, secret-free reason. That, any
	 * other status, or a thrown exception keeps the order on hold and is never
	 * followed by a second charge.
	 *
	 * @since 1.0.0
	 *
	 * @param object $new_order    The linked follow-on WC_Order to charge.
	 * @param object $source_order The original WC_Order supplying the saved token.
	 */
	public function charge( object $new_order, object $source_order ): OneClickResult;
}
