/**
 * UpsellBay classic checkout interactions.
 */

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

	return response.json();
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

	await postOffer('bump-toggle', {
		offer_id: Number(card.dataset.upsellbayOfferId || 0),
		placement: card.dataset.upsellbayPlacement || 'checkout_bump',
		accepted: checkbox.checked,
	});

	if (window.jQuery) {
		window.jQuery(document.body).trigger('update_checkout');
	}
});
