# REST API Contracts

REST namespace: `upsellbay/v1`.

Public write routes require a valid UpsellBay session token and are rate-limited per client IP (`REMOTE_ADDR`, IPv6 per /64; filter `upsellbay_pro_rate_limit_client_ip` for sites behind a proxy). They never accept client-sent price, discount, product availability, or eligibility as truth. A session token is accepted only if this session issued it and it is under two days old; a session with no token rejects every token.

| Route | Method | Auth | Required args | Success response | Error statuses |
| --- | --- | --- | --- | --- | --- |
| `/offer-preview` | `GET` | `manage_woocommerce` | `offer_id` | `status=200`, safe preview payload | `404` missing offer |
| `/bump-toggle` | `POST` | session token | `offer_id`, `token`, `accepted` | `success`, `cart_item_key`, `offer_price`, `notices` | `400`, `403`, `404`, `429` |
| `/cart-offer-add` (Pro) | `POST` | session token, or the order-bound thank-you token plus order key for `thankyou_offer` | `offer_id`, `token`, `placement` (`product_upsell`, `cart_crosssell` or `thankyou_offer`; checkout bumps use `/bump-toggle`) | `success`, `cart_item_key`, `offer_price`, `notices` | `400` (wrong placement, order not verified, or the offer is not published, not active, out of schedule, dismissed in this session, or its triggers/rules no longer match), `403`, `404`, `429` |
| `/dismiss` | `POST` | session token | `offer_id`, `token`, `placement` | `success`, `notices` | `400`, `403`, `429` |
| `/license/activate`, `/license/status` (Pro) | `POST`, `GET` | `manage_woocommerce` | `license_key` (activate) | status: `status`, `plan`, `expires_at`, `masked_key`; activate: `success` plus that object under `status`. Never the full key. | `403`, `422`, license-server errors |
| `/analytics/summary` | planned admin route | `manage_woocommerce` | date range | aggregate non-PII summary | `403`, `400` |
| `/import` | planned admin route | `manage_woocommerce` plus nonce | import JSON | validation/import summary | `403`, `400` |

`/one-click-accept` is not registered in Pro 1.0.0: thank-you offers start a separate follow-on checkout through `/cart-offer-add`. The one-click route stays in the code behind the reserved `upsellbay_pro_one_click_enabled` switch (see `hooks.md`) and is not a supported contract.

Price integrity: an accepted offer discounts exactly one unit. On every cart total calculation Pro reloads each of its offer lines' offers, re-checks status, schedule, placement switch, triggers and rules, and recomputes the price from the product's current price; a line that no longer qualifies gets no discount, and a line whose current price cannot be read is removed from the cart. Extra units on the line are charged the regular price. A fixed-price offer never charges more than the product's current price. Cart subtotal rules are judged on the same figure at accept time and on every recalculation: unit price x quantity of every cart line except UpsellBay Pro offer lines (so an accepted offer never counts toward its own threshold). Only a thank-you offer records a source order, and only after the order is verified; a `source_order_id` sent with any other placement is ignored.

## Stores behind a proxy or CDN

Rate limits are counted per client IP, read from `REMOTE_ADDR` only. Behind a reverse proxy, load balancer or CDN, `REMOTE_ADDR` is the proxy's address, so every shopper shares one rate-limit bucket and a busy store can see `429` responses. Return the real client IP from `upsellbay_pro_rate_limit_client_ip`, taken only from a header your own proxy sets (never one the browser can send). Values that are not valid IP addresses are ignored.

```php
add_filter( 'upsellbay_pro_rate_limit_client_ip', function ( string $ip ): string {
	// Cloudflare sets this header; use the one your own proxy sets.
	$real = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';
	return '' !== $real ? $real : $ip;
} );
```

## Full-page caching and guests

Public write routes need the session token that this shopper's WooCommerce session issued. On a page served from a full-page cache, the token in the page markup belongs to whichever session the cache was built for, so a guest's first offer click gets `403` until WooCommerce's cart fragments refresh (`woocommerce_add_to_cart_fragments`) and hand the browser a token for its own session. Keep cart fragments enabled on pages that show offers, or exclude those pages from the full-page cache.

Sensitive-value rules: REST responses must not expose license keys, raw session tokens, customer email, payment identifiers, or raw checkout state.
