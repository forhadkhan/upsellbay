/**
 * Behaviour test for the shipped storefront bundle's one-click accept path.
 *
 * Run with `bun test tests/js` (Bun's built-in runner, no extra packages). It loads
 * the built assets/frontend/storefront.js against a minimal DOM stub, so it tests
 * what ships, not the source.
 */

import { beforeEach, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const bundle = readFileSync(join(import.meta.dir, '../../assets/frontend/storefront.js'), 'utf8');

function makeButton() {
	const attributes = {};
	return {
		disabled: false,
		textContent: 'Add to my order',
		dataset: {},
		setAttribute(name, value) {
			attributes[name] = value;
		},
		removeAttribute(name) {
			delete attributes[name];
		},
		attributes,
	};
}

// oneClick: whether the server marked the card for one-click (only when the
// reserved upsellbay_pro_one_click_enabled switch is on; never in 1.0.0).
function makeCard(button, oneClick = true) {
	const classes = new Set();
	const card = {
		dataset: {
			upsellbayPlacement: 'thankyou_offer',
			upsellbayOfferId: '77',
			upsellbaySourceOrderId: '6001',
			upsellbaySourceOrderKey: 'fixture-order-key',
			upsellbayCheckoutUrl: 'https://shop.test/checkout/',
			...(oneClick ? { upsellbayOneClick: '1' } : {}),
		},
		classList: {
			add: (name) => classes.add(name),
			remove: (name) => classes.delete(name),
			contains: (name) => classes.has(name),
		},
		closest: (selector) => (selector === '.upsellbay-offer' ? card : null),
	};
	button.closest = (selector) => {
		if (selector === '.upsellbay-offer__button') {
			return button;
		}
		return selector === '.upsellbay-offer' ? card : null;
	};
	return card;
}

let handler;
let notices;
let requests;
let location;

beforeEach(() => {
	handler = null;
	notices = [];
	requests = [];
	location = { href: 'https://shop.test/order-received/' };

	globalThis.window = globalThis;
	globalThis.wp = { i18n: { __: (text) => text } };
	globalThis.upsellbayStorefront = { restUrl: 'https://shop.test/wp-json/upsellbay/v1', token: 'fixture-token' }; // gate: allow -- test fixture.
	globalThis.location = location;
	globalThis.document = {
		addEventListener: (type, callback) => {
			if (type === 'click') {
				handler = callback;
			}
		},
		getElementById: () => null,
		querySelector: () => ({ prepend: (node) => notices.push(node) }),
		createElement: () => ({ setAttribute() {} }),
		body: {},
	};

	// Evaluate the IIFE bundle; it registers its click handler on document.
	new Function(bundle)();
});

test('a thrown one-click request restores the button and never falls back to checkout', async () => {
	globalThis.fetch = async (url) => {
		requests.push(url);
		throw new TypeError('Failed to fetch');
	};
	const button = makeButton();
	const card = makeCard(button);

	await handler({ target: button });

	expect(requests).toEqual(['https://shop.test/wp-json/upsellbay/v1/one-click-accept']);
	expect(button.disabled).toBe(false);
	expect(button.textContent).toBe('Add to my order');
	expect(button.attributes['aria-busy']).toBeUndefined();
	expect(card.classList.contains('is-loading')).toBe(false);
	expect(location.href).toBe('https://shop.test/order-received/');
	expect(notices).toHaveLength(1);
	expect(notices[0].textContent).toContain('check your order emails before trying again');
});

test('a non-JSON 500 response is treated the same as a thrown request', async () => {
	globalThis.fetch = async (url) => {
		requests.push(url);
		return {
			ok: false,
			status: 500,
			json: async () => {
				throw new SyntaxError('Unexpected token <');
			},
		};
	};
	const button = makeButton();
	makeCard(button);

	await handler({ target: button });

	expect(requests).toHaveLength(1);
	expect(button.disabled).toBe(false);
	expect(location.href).toBe('https://shop.test/order-received/');
	expect(notices[0].textContent).toContain('check your order emails before trying again');
});

test('without the one-click marker a thank-you accept never calls one-click and goes to the separate checkout', async () => {
	const bodies = [];
	globalThis.fetch = async (url, options) => {
		requests.push(url);
		bodies.push(JSON.parse(options.body));
		return { ok: true, status: 200, json: async () => ({ success: true }) };
	};
	const button = makeButton();
	makeCard(button, false);

	await handler({ target: button });

	expect(requests).toEqual(['https://shop.test/wp-json/upsellbay/v1/cart-offer-add']);
	expect(bodies[0].placement).toBe('thankyou_offer');
	expect(bodies[0].offer_id).toBe(77);
	expect(location.href).toBe('https://shop.test/checkout/');
});
