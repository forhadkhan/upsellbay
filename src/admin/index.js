/**
 * UpsellBay shared admin entry.
 */
document.documentElement.classList.add( 'upsellbay-admin-ready' );

window.jQuery( function ( $ ) {
	if ( $.fn.wpColorPicker ) {
		$( '.upsellbay-color-picker' ).wpColorPicker();
	}
} );
