/**
 * UpsellBay shared admin entry.
 */
document.documentElement.classList.add( 'upsellbay-admin-ready' );

window.jQuery( function ( $ ) {
	if ( $.fn.wpColorPicker ) {
		$( '.upsellbay-color-picker' ).wpColorPicker();
	}

	$( document.body ).on(
		'click',
		'.upsellbay-license-remove-trigger',
		function ( event ) {
			event.preventDefault();

			const $trigger = $( this );
			const modalData = {
				title: $trigger.data( 'modalTitle' ),
				message: $trigger.data( 'modalMessage' ),
				confirm: $trigger.data( 'modalConfirm' ),
				cancel: $trigger.data( 'modalCancel' ),
				url: $trigger.attr( 'href' ),
			};

			if ( $.fn.WCBackboneModal ) {
				$trigger.WCBackboneModal( {
					template: 'upsellbay-confirmation-modal',
					variable: modalData,
				} );
				return;
			}

			if ( window.confirm( modalData.message ) ) {
				window.location = modalData.url;
			}
		}
	);

	$( document.body ).on(
		'click',
		'.upsellbay-confirmation-confirm',
		function ( event ) {
			event.preventDefault();

			const url = $( this ).data( 'url' );
			if ( url ) {
				window.location = url;
			}
		}
	);

	$( document.body ).on(
		'click',
		'.upsellbay-confirmation-cancel',
		function ( event ) {
			event.preventDefault();
			$( this )
				.closest( '.wc-backbone-modal' )
				.find( '.modal-close' )
				.first()
				.trigger( 'click' );
		}
	);
} );
