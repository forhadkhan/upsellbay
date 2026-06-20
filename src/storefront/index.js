/**
 * UpsellBay storefront offer interactions.
 */

import { __ } from '@wordpress/i18n';

const config = window.upsellbayStorefront || {};

async function postOffer(endpoint, payload) {
	const fragment = document.getElementById('upsellbay-token-fragment');
	const currentToken = fragment ? fragment.dataset.token : config.token;

	if (!config.restUrl || !currentToken) {
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
			token: currentToken,
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
	const dismiss = event.target.closest('[data-upsellbay-dismiss]');
	if (dismiss) {
		const card = dismiss.closest('.upsellbay-offer');
		if (!card) return;

		dismiss.disabled = true;
		const result = await postOffer('dismiss', {
			offer_id: Number(card.dataset.upsellbayOfferId || 0),
			placement: card.dataset.upsellbayPlacement || 'product_upsell',
		});

		if (result?.ok) {
			const section = card.closest('.upsellbay-offer-section');
			card.remove();
			if (section && !section.querySelector('.upsellbay-offer')) {
				section.remove();
			}
		} else {
			dismiss.disabled = false;
		}
		return;
	}

	const button = event.target.closest('.upsellbay-offer__button');
	if (!button) {
		return;
	}

	const card = button.closest('.upsellbay-offer');
	if (!card) {
		return;
	}

	button.disabled = true;
	button.setAttribute('aria-busy', 'true');
	card.classList.add('is-loading');

	const isThankYou = card.dataset.upsellbayPlacement === 'thankyou_offer';
	const originalText = button.textContent;
	if (isThankYou) {
		button.textContent = __('Adding...', 'upsellbay');
	}

	const result = await postOffer('cart-offer-add', {
		offer_id: Number(card.dataset.upsellbayOfferId || 0),
		placement: card.dataset.upsellbayPlacement || 'cart_crosssell',
		source_order_id: Number(card.dataset.upsellbaySourceOrderId || 0),
	});

	card.classList.remove('is-loading');
	button.removeAttribute('aria-busy');

	if (!result?.ok) {
		button.disabled = false;
		if (isThankYou) {
			button.textContent = originalText;
		}
		showNotice(result?.body?.message || __('Unable to add this offer. Please try again.', 'upsellbay'));
		return;
	}

	if (isThankYou && (config.checkoutUrl || config.cartUrl)) {
		showNotice(__('Adding to new checkout…', 'upsellbay'), 'message');
		window.location.href = config.checkoutUrl || config.cartUrl;
		return;
	}

	// On success, leave button disabled and change text
	button.disabled = true;
	button.textContent = __('Already in cart', 'upsellbay');

	showNotice(result?.body?.message || __('Offer added to your cart.', 'upsellbay'), 'message');

	// 1. Notify Classic WooCommerce Themes (triggers mini-cart fragments to update and slide out)
	if (window.jQuery) {
		window.jQuery(document.body).trigger('added_to_cart', [null, null, window.jQuery(button)]);
		window.jQuery(document.body).trigger('wc_fragment_refresh');
	}

	// 2. Notify WooCommerce Blocks (invalidates cached cart data so the block refreshes)
	if (window.wp && window.wp.data && window.wp.data.dispatch) {
		const cartDispatcher = window.wp.data.dispatch('wc/store/cart');
		if (cartDispatcher && typeof cartDispatcher.invalidateResolutionForStoreSelector === 'function') {
			cartDispatcher.invalidateResolutionForStoreSelector('getCartData');
		}
	}
});
