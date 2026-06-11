/**
 * UpsellBay classic checkout interactions.
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

function showNotice(message) {
	if (!message) {
		return;
	}

	const notice = document.createElement('div');
	notice.className = 'woocommerce-error upsellbay-offer-notice';
	notice.textContent = message;

	const target = document.querySelector('.woocommerce-notices-wrapper') || document.body;
	target.prepend(notice);
}

document.addEventListener('change', async (event) => {
	const checkbox = event.target.closest('.upsellbay-offer__checkbox');
	if (!checkbox) {
		return;
	}

	const card = checkbox.closest('.upsellbay-offer');
	if (!card) {
		return;
	}

	checkbox.disabled = true;
	const result = await postOffer('bump-toggle', {
		offer_id: Number(card.dataset.upsellbayOfferId || 0),
		placement: card.dataset.upsellbayPlacement || 'checkout_bump',
		accepted: checkbox.checked,
	});
	checkbox.disabled = false;

	if (!result?.ok) {
		checkbox.checked = !checkbox.checked;
		showNotice(result?.body?.message || __('Unable to update this offer. Please try again.', 'upsellbay'));
		return;
	}

	if (window.jQuery) {
		window.jQuery(document.body).trigger('update_checkout');
	}
});
