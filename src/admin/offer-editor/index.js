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
