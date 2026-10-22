import { registerPlugin, getPlugin } from '@wordpress/plugins';
const { ExperimentalOrderMeta } = window.wc.blocksCheckout;
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

// invalidateResolutionForStoreSelector() queues an async, unawaited
// background refetch of the whole cart. Its resolution can land while the
// shopper is still filling in the checkout form, and Woo Blocks then
// re-syncs the form's contact/address fields from that response, silently
// wiping out whatever the shopper had already typed (confirmed by direct
// observation: email, name, and address fields cleared mid-fill with no
// visible cause). receiveCartContents() applies a fetched cart snapshot
// synchronously and explicitly excludes customer address data from what it
// overwrites, so the offer's price/cart-item state still refreshes without
// touching in-progress form input.
const refreshBlockCart = async () => {
	if ( ! ( window.wp && window.wp.data && window.wp.data.dispatch ) ) {
		return;
	}

	try {
		const cartData = await apiFetch( { path: '/wc/store/v1/cart' } );
		const cartDispatcher = window.wp.data.dispatch( 'wc/store/cart' );
		if ( cartDispatcher && typeof cartDispatcher.receiveCartContents === 'function' ) {
			cartDispatcher.receiveCartContents( cartData );
		}
	} catch ( error ) {
		// A failed background refresh shouldn't block the accept/remove flow.
	}
};

const CheckoutBumpOffer = ( { offer } ) => {
	const [ isLoading, setIsLoading ] = useState( false );

	const handleToggle = async ( event ) => {
		const isChecked = event.target.checked;
		setIsLoading( true );

		try {
			await apiFetch( {
				path: '/upsellbay/v1/bump-toggle',
				method: 'POST',
				data: {
					offer_id: offer.id,
					placement: 'checkout_bump',
					accepted: isChecked,
					token: window.upsellbayStorefront?.token || '',
				},
			} );

			await refreshBlockCart();
		} catch ( error ) {
			console.error( 'UpsellBay Toggle Error:', error );
			// If it fails, revert the checkbox visually by letting the React state or block refresh handle it.
		} finally {
			setIsLoading( false );
		}
	};

	let classes = 'upsellbay-offer upsellbay-offer--checkout_bump upsellbay-offer--checkout-compact';
	if ( offer.image_url ) {
		classes += ' upsellbay-offer--has-image';
	}
	if ( isLoading ) {
		classes += ' is-loading';
	}

	const descId = `upsellbay-bump-desc-${ offer.id }`;

		return (
			<div className={ classes } data-upsellbay-placement="checkout_bump" data-upsellbay-offer-id={ offer.id }>
				{ offer.image_url && (
					<div className="upsellbay-offer__image">
						<img src={ offer.image_url } alt={ offer.product_name } />
					</div>
				) }
				<div className="upsellbay-offer__content">
				<div className="upsellbay-offer__header">
					<label className="upsellbay-offer__toggle">
						<input 
							type="checkbox" 
							className="upsellbay-offer__checkbox" 
							onChange={ handleToggle }
							disabled={ isLoading }
							checked={ offer.in_cart }
							aria-describedby={ offer.body ? descId : undefined }
							/>
							<strong className="upsellbay-offer__headline">
								{ offer.headline }
								<span className="upsellbay-offer__badge">{ __( 'One-Time Offer', 'upsellbay' ) }</span>
							</strong>
						</label>
					{ offer.price_html && (
						<div className="upsellbay-offer__price" dangerouslySetInnerHTML={ { __html: offer.price_html } } />
					) }
				</div>
				{ offer.body && (
					<div className="upsellbay-offer__body" id={ descId } dangerouslySetInnerHTML={ { __html: offer.body } } />
				) }
			</div>
		</div>
	);
};

const CheckoutBumpList = ( { extensions } ) => {
	// checkout_bump data is registered on the Store API Cart schema (see
	// StoreApiExtender's register_endpoint_data() for why), and the same
	// ExperimentalOrderMeta slot mounts on both the Cart and Checkout
	// blocks, so this must only render on the checkout page — otherwise it
	// would also appear on the cart page. is_cart()/is_checkout() are not
	// reliable this early in the block-registration hook lifecycle, so use
	// WooCommerce's own standard body classes instead, which are resolved
	// at template-render time.
	if ( ! document.body.classList.contains( 'woocommerce-checkout' ) ) {
		return null;
	}

	const offers = extensions?.upsellbay?.checkout_bump || [];

	if ( ! offers || offers.length === 0 ) {
		return null;
	}

	return (
		<div className="upsellbay-block-offers upsellbay-block-offers--checkout">
			{ offers.map( ( offer ) => (
				<CheckoutBumpOffer key={ offer.id } offer={ offer } />
			) ) }
		</div>
	);
};

const PLUGIN_NAME = 'upsellbay-checkout-bumps';

export const registerCheckoutBumps = () => {
	// The free host registers the same plugin name for the same Store API
	// checkout_bump data. registerPlugin() throws on a taken name, and two
	// registrations would render every bump twice, so the second loader stands
	// down and the bump renders once.
	if ( typeof getPlugin === 'function' && getPlugin( PLUGIN_NAME ) ) {
		return;
	}

	registerPlugin( PLUGIN_NAME, {
		render: ( { extensions } ) => (
			<ExperimentalOrderMeta>
				<CheckoutBumpList extensions={ extensions } />
			</ExperimentalOrderMeta>
		),
		scope: 'woocommerce-checkout',
	} );
};
