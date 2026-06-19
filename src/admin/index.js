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
		'.upsellbay-license-remove-trigger, .upsellbay-modal-trigger',
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

	$( document.body ).on( 'click', '.upsellbay-copy-log', function ( event ) {
		event.preventDefault();
		const $btn = $( this );
		const text = $btn.data( 'clipboardText' );

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () {
				const originalText = $btn.text();
				$btn.text( 'Copied!' );
				setTimeout( function () {
					$btn.text( originalText );
				}, 2000 );
			} );
		} else {
			const $temp = $( '<textarea>' );
			$( 'body' ).append( $temp );
			$temp.val( text ).select();
			document.execCommand( 'copy' );
			$temp.remove();

			const originalText = $btn.text();
			$btn.text( 'Copied!' );
			setTimeout( function () {
				$btn.text( originalText );
			}, 2000 );
		}
	} );

	// Toggle placement card active/inactive state and enable/disable max display inputs.
	$( '.upsellbay-placements-grid' ).on( 'change', '.upsellbay-placement-toggle input[type="checkbox"]', function () {
		const $checkbox = $( this );
		const $card = $checkbox.closest( '.upsellbay-placement-card' );
		const $numberInput = $card.find( '.upsellbay-placement-card__number-input' );

		if ( $checkbox.is( ':checked' ) ) {
			$card.addClass( 'upsellbay-placement-card--active' ).removeClass( 'upsellbay-placement-card--inactive' );
			$numberInput.prop( 'disabled', false );
		} else {
			$card.removeClass( 'upsellbay-placement-card--active' ).addClass( 'upsellbay-placement-card--inactive' );
			$numberInput.prop( 'disabled', true );
		}
	} );
} );
