/**
 * UpsellBay storefront offer interactions.
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
	await postOffer('cart-offer-add', {
		offer_id: Number(card.dataset.upsellbayOfferId || 0),
		placement: card.dataset.upsellbayPlacement || 'cart_crosssell',
	});
	button.disabled = false;
});
