/**
 * UpsellBay storefront offer interactions.
 */

import { __ } from '@wordpress/i18n';

const config = window.upsellbayStorefront || {};

async function postOffer(endpoint, payload) {
	if (!config.restUrl || !config.token) {
		return null;
	}

	const response = await window.fetch(`${config.restUrl}/${endpoint}`, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			...payload,
			token: config.token,
		}),
	});

	const body = await response.json();
	return {
		ok: response.ok && body?.success !== false,
		status: response.status,
		body,
	};
}

function showNotice(message, type = 'error') {
	if (!message) {
		return;
	}

	const notice = document.createElement('div');
	notice.className = `woocommerce-${type} upsellbay-offer-notice`;
	notice.textContent = message;

	const target = document.querySelector('.woocommerce-notices-wrapper') || document.body;
	target.prepend(notice);
}

document.addEventListener('click', async (event) => {
	const button = event.target.closest('.upsellbay-offer__button');
	if (!button) {
		return;
	}

	const card = button.closest('.upsellbay-offer');
	if (!card) {
		return;
	}

	button.disabled = true;
	const result = await postOffer('cart-offer-add', {
		offer_id: Number(card.dataset.upsellbayOfferId || 0),
		placement: card.dataset.upsellbayPlacement || 'cart_crosssell',
		source_order_id: Number(card.dataset.upsellbaySourceOrderId || 0),
	});
	button.disabled = false;

	if (!result?.ok) {
		showNotice(result?.body?.message || __('Unable to add this offer. Please try again.', 'upsellbay'));
		return;
	}

	showNotice(result?.body?.message || __('Offer added to your cart.', 'upsellbay'), 'message');

	if (card.dataset.upsellbayPlacement === 'thankyou_offer' && (config.checkoutUrl || config.cartUrl)) {
		window.location.href = config.checkoutUrl || config.cartUrl;
		return;
	}

	if (window.jQuery) {
		window.jQuery(document.body).trigger('wc_fragment_refresh');
	}
});
