/**
 * Shortcodes page: copy buttons for shortcodes.
 */
( function () {
	'use strict';

	function copyText( value ) {
		var field;
		var copied = false;

		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( value );
		}

		// Sites without HTTPS can't use the clipboard API.
		field = document.createElement( 'textarea' );
		field.value = value;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();

		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			copied = false;
		}

		document.body.removeChild( field );

		return copied ? Promise.resolve() : Promise.reject();
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.crc-copy' ) : null;

		if ( ! button ) {
			return;
		}

		button.dataset.label = button.dataset.label || button.textContent;

		copyText( button.getAttribute( 'data-copy' ) ).then( function () {
			button.textContent = button.getAttribute( 'data-copied' );
			window.clearTimeout( button.crcTimer );
			button.crcTimer = window.setTimeout( function () {
				button.textContent = button.dataset.label;
			}, 1500 );
		}, function () {} );
	} );
}() );
