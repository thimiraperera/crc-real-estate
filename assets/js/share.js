/**
 * Share button: opens the device's share menu, or copies the link where
 * there is no share menu.
 */
( function () {
	'use strict';

	var text = window.crcReShare || {};

	function copyText( value ) {
		var field;
		var copied = false;

		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( value );
		}

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
		var button = event.target.closest ? event.target.closest( '.crc-share' ) : null;
		var label;
		var url;
		var title;

		if ( ! button ) {
			return;
		}

		url = button.getAttribute( 'data-url' ) || window.location.href;
		title = button.getAttribute( 'data-title' ) || document.title;

		if ( navigator.share ) {
			navigator.share( { title: title, url: url } ).catch( function () {} );
			return;
		}

		label = button.querySelector( '.crc-share-label' );

		copyText( url ).then( function () {
			if ( ! label ) {
				return;
			}

			label.dataset.label = label.dataset.label || label.textContent;
			label.textContent = text.copied || 'Link copied';
			window.clearTimeout( button.crcTimer );
			button.crcTimer = window.setTimeout( function () {
				label.textContent = label.dataset.label;
			}, 2000 );
		}, function () {} );
	} );
}() );
