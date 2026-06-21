import { registerPlugin } from '@wordpress/plugins';
const { ExperimentalOrderMeta } = window.wc.blocksCheckout;
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const refreshBlockCart = () => {
	if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
		const cartDispatcher = window.wp.data.dispatch( 'wc/store/cart' );
		if ( cartDispatcher && typeof cartDispatcher.invalidateResolutionForStoreSelector === 'function' ) {
			cartDispatcher.invalidateResolutionForStoreSelector( 'getCartData' );
		}
	}
};

const CartCrossSellOffer = ( { offer } ) => {
	const [ isLoading, setIsLoading ] = useState( false );

	const handleAdd = async () => {
		setIsLoading( true );

		try {
			// Call our custom REST API to add the offer to the cart.
			// The REST API handles rule validation, discounts, and adding the product.
			await apiFetch( {
				path: '/upsellbay/v1/cart-offer-add',
				method: 'POST',
				data: {
					offer_id: offer.id,
					placement: 'cart_crosssell',
					token: window.upsellbayStorefront?.token || '',
				},
			} );

			refreshBlockCart();
		} catch ( error ) {
			console.error( 'UpsellBay Add Error:', error );
		} finally {
			setIsLoading( false );
		}
	};

	const handleDismiss = async () => {
		setIsLoading( true );

		try {
			await apiFetch( {
				path: '/upsellbay/v1/dismiss',
				method: 'POST',
				data: {
					offer_id: offer.id,
					placement: 'cart_crosssell',
					token: window.upsellbayStorefront?.token || '',
				},
			} );

			refreshBlockCart();
		} catch ( error ) {
			console.error( 'UpsellBay Dismiss Error:', error );
		} finally {
			setIsLoading( false );
		}
	};

	let classes = 'upsellbay-offer upsellbay-offer--cart_crosssell upsellbay-offer--cart-crosssell';
	if ( offer.image_url ) {
		classes += ' upsellbay-offer--has-image';
	}
	if ( offer.in_cart || isLoading ) {
		classes += ' is-disabled';
	}

	return (
		<div className={ classes } data-upsellbay-placement="cart_crosssell" data-upsellbay-offer-id={ offer.id }>
			{ offer.image_url && (
				<div className="upsellbay-offer__image">
					<img src={ offer.image_url } alt={ offer.product_name } />
				</div>
			) }
			<div className="upsellbay-offer__content">
				<div className="upsellbay-offer__text">
					{ offer.reason_label && (
						<span className="upsellbay-offer__reason">{ offer.reason_label }</span>
					) }
					<strong className="upsellbay-offer__headline">{ offer.headline }</strong>
					{ offer.product_name && (
						<span className="upsellbay-offer__product-name">{ offer.product_name }</span>
					) }
					{ offer.body && (
						<div className="upsellbay-offer__body" dangerouslySetInnerHTML={ { __html: offer.body } } />
					) }
				</div>
				{ offer.price_html && (
					<div className="upsellbay-offer__price" dangerouslySetInnerHTML={ { __html: offer.price_html } } />
				) }
			</div>
			<div className="upsellbay-offer__action">
				{ offer.in_cart ? (
					<span className="upsellbay-offer__notice">{ __( 'Added to cart', 'upsellbay' ) }</span>
				) : (
					<button 
						type="button" 
						className="button wp-element-button wc-block-components-button upsellbay-offer__button" 
						onClick={ handleAdd }
						disabled={ isLoading }
					>
						{ isLoading ? __( 'Adding...', 'upsellbay' ) : offer.button_text }
					</button>
				) }
				<button 
					type="button" 
					className="upsellbay-offer__dismiss" 
					onClick={ handleDismiss }
					disabled={ isLoading }
				>
					{ __( 'No thanks', 'upsellbay' ) }
				</button>
			</div>
		</div>
	);
};

const CartCrossSellsList = ( { extensions } ) => {
	const offers = extensions?.upsellbay?.cart_crosssell || [];

	if ( ! offers || offers.length === 0 ) {
		return null;
	}

	return (
		<div className="upsellbay-block-offers upsellbay-block-offers--cart">
			<h3 className="upsellbay-offer-section__heading">{ __( 'Still missing?', 'upsellbay' ) }</h3>
			{ offers.map( ( offer ) => (
				<CartCrossSellOffer key={ offer.id } offer={ offer } />
			) ) }
		</div>
	);
};

export const registerCartCrossSells = () => {
	registerPlugin( 'upsellbay-cart-cross-sells', {
		render: ( { extensions } ) => (
			<ExperimentalOrderMeta>
				<CartCrossSellsList extensions={ extensions } />
			</ExperimentalOrderMeta>
		),
		scope: 'woocommerce-checkout',
	} );
};
