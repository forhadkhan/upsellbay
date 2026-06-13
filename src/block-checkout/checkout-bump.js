import { registerPlugin } from '@wordpress/plugins';
const { ExperimentalOrderMeta, extensionCartUpdate } = window.wc.blocksCheckout;
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

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

			// Inform the Checkout Block to invalidate and refetch its data (totals)
			extensionCartUpdate( { namespace: 'upsellbay' } );
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
				<img src={ offer.image_url } className="upsellbay-offer__image" alt={ offer.product_name } />
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
						<strong className="upsellbay-offer__headline">{ offer.headline }</strong>
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

export const registerCheckoutBumps = () => {
	registerPlugin( 'upsellbay-checkout-bumps', {
		render: ( { extensions } ) => (
			<ExperimentalOrderMeta>
				<CheckoutBumpList extensions={ extensions } />
			</ExperimentalOrderMeta>
		),
		scope: 'woocommerce-checkout',
	} );
};
