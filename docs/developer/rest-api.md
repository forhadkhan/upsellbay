# REST API Contracts

REST namespace: `upsellbay/v1`.

Public write routes require a valid UpsellBay session token and are rate-limited. They never accept client-sent price, discount, product availability, or eligibility as truth.

| Route | Method | Auth | Required args | Success response | Error statuses |
| --- | --- | --- | --- | --- | --- |
| `/offer-preview` | `GET` | `manage_woocommerce` | `offer_id` | `status=200`, safe preview payload | `404` missing offer |
| `/bump-toggle` | `POST` | session token | `offer_id`, `token`, `accepted` | `success`, `cart_item_key`, `offer_price`, `notices` | `400`, `403`, `404`, `429` |
| `/cart-offer-add` | `POST` | session token | `offer_id`, `token`, `placement` | `success`, `cart_item_key`, `offer_price`, `notices` | `400`, `403`, `404`, `429` |
| `/dismiss` | `POST` | session token | `offer_id`, `token`, `placement` | `success`, `notices` | `400`, `403`, `429` |
| `/analytics/summary` | planned admin route | `manage_woocommerce` | date range | aggregate non-PII summary | `403`, `400` |
| `/import` | planned admin route | `manage_woocommerce` plus nonce | import JSON | validation/import summary | `403`, `400` |

Sensitive-value rules: REST responses must not expose license keys, raw session tokens, customer email, payment identifiers, or raw checkout state.
