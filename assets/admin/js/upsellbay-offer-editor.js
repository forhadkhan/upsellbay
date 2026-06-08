/**
 * UpsellBay offer editor entry.
 */
document.addEventListener( 'click', ( event ) => {
	const button = event.target.closest( '[data-upsellbay-add-rule]' );

	if ( ! button ) {
		return;
	}

	event.preventDefault();
	document.dispatchEvent( new CustomEvent( 'upsellbay:add-rule' ) );
} );

/**
 * UpsellBay product selector.
 */
window.jQuery( function ( $ ) {
	const $selector = $( '[data-upsellbay-product-selector]' );
	if ( ! $selector.length ) {
		return;
	}

	const $search = $selector.find( 'input[type="text"]' );
	const $input = $selector.find( 'input[type="hidden"]' );
	const $results = $selector.find( '[data-upsellbay-results]' );
	const $selection = $selector.find( '[data-upsellbay-selection]' );

	let searchTimeout = null;

	$search.on( 'input', function () {
		const query = $( this ).val();

		clearTimeout( searchTimeout );
		if ( query.length < 3 ) {
			$results.empty().removeClass( 'is-active' );
			return;
		}

		searchTimeout = setTimeout( () => {
			$.ajax( {
				url: `${ window.upsellbay_data.rest_url }upsellbay/v1/products`,
				data: { search: query },
				beforeSend: ( xhr ) => {
					xhr.setRequestHeader( 'X-WP-Nonce', window.upsellbay_data.nonce );
				},
				success: ( response ) => {
					$results.empty();
					if ( response && response.length ) {
						response.forEach( ( product ) => {
							const $result = $( `
								<div class="upsellbay-product-selector__result" data-id="${ product.id }">
									<div class="upsellbay-product-selector__result-image">
										${ product.image ? `<img src="${ product.image }" alt="">` : '' }
									</div>
									<div class="upsellbay-product-selector__result-info">
										<span class="upsellbay-product-selector__result-name">${ product.name }</span>
										<span class="upsellbay-product-selector__result-meta">ID: ${ product.id } | SKU: ${ product.sku || 'N/A' } | ${ product.price }</span>
									</div>
								</div>
							` );

							$result.on( 'click', () => {
								selectProduct( product );
							} );

							$results.append( $result );
						} );
						$results.addClass( 'is-active' );
					} else {
						$results.removeClass( 'is-active' );
					}
				},
			} );
		}, 300 );
	} );

	function selectProduct( product ) {
		$input.val( product.id );
		$search.val( '' ).hide();
		$results.empty().removeClass( 'is-active' );

		$selection.html( `
			<div class="upsellbay-product-selector__result-image">
				${ product.image ? `<img src="${ product.image }" alt="">` : '' }
			</div>
			<div class="upsellbay-product-selector__result-info">
				<span class="upsellbay-product-selector__result-name">${ product.name }</span>
				<span class="upsellbay-product-selector__result-meta">${ product.price }</span>
			</div>
			<span class="upsellbay-product-selector__selection-remove">&times;</span>
		` ).addClass( 'is-active' );

		$selection.find( '.upsellbay-product-selector__selection-remove' ).on( 'click', () => {
			$input.val( '' );
			$selection.empty().removeClass( 'is-active' );
			$search.show().focus();
		} );
	}

	$( document ).on( 'click', ( e ) => {
		if ( ! $( e.target ).closest( $selector ).length ) {
			$results.removeClass( 'is-active' );
		}
	} );
} );

	// Initialization of product selector from PHP rendered value
	jQuery( '.upsellbay-product-selector' ).each( function () {
		const container = jQuery( this );
		const input = container.find( 'input[type="hidden"]' );
		const initialId = input.attr( 'value' ); // Read raw attribute in case it's set by PHP but not JS yet

		if ( initialId && initialId !== '0' ) {
			jQuery.ajax( {
				url: window.upsellbay_data.rest_url + 'upsellbay/v1/products',
				data: { include: initialId },
				beforeSend: ( xhr ) => {
					if ( window.upsellbay_data && window.upsellbay_data.nonce ) {
						xhr.setRequestHeader( 'X-WP-Nonce', window.upsellbay_data.nonce );
					}
				},
				success: ( response ) => {
					if ( response && response.length ) {
						// We have to scope the variables for this specific instance
						const search = container.find( 'input[type="text"]' );
						const results = container.find( '[data-upsellbay-results]' );
						const selection = container.find( '[data-upsellbay-selection]' );

						const product = response[0];
						
						// Reproducing selectProduct logic here to avoid scope issues in the loop
						input.val( product.id );
						search.val( '' ).hide();
						results.empty().removeClass( 'is-active' );

						selection.html( '
							<div class="upsellbay-product-selector__result-image">
								' + ( product.image ? '<img src="' + product.image + '" alt="">' : '' ) + '
							</div>
							<div class="upsellbay-product-selector__result-info">
								<span class="upsellbay-product-selector__result-name">' + product.name + '</span>
								<span class="upsellbay-product-selector__result-meta">' + product.price + '</span>
							</div>
							<span class="upsellbay-product-selector__selection-remove">&times;</span>
						' ).addClass( 'is-active' );

						selection.find( '.upsellbay-product-selector__selection-remove' ).on( 'click', () => {
							input.val( '' );
							selection.empty().removeClass( 'is-active' );
							search.show().focus();
						} );
					}
				},
			} );
		}
	} );
