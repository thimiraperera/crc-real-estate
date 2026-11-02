/**
 * Listings → Settings → Emails: the emails' logo, chosen from the Media
 * Library (PNG, JPG or GIF, which every email app shows), shown before it is
 * saved, and taken off with Remove.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var box = $( '[data-crc-email-logo]' );
		var frame = null;
		var id;
		var preview;
		var remove;
		var empty;

		if ( ! box.length || ! window.wp || ! window.wp.media ) {
			return;
		}

		id = box.find( '[data-crc-logo-id]' );
		preview = box.find( '[data-crc-logo-preview]' );
		remove = box.find( '[data-crc-logo-remove]' );
		empty = box.find( '[data-crc-logo-empty]' ).html();

		box.find( '[data-crc-logo-choose]' ).on( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: box.attr( 'data-title' ),
					button: { text: box.attr( 'data-button' ) },
					library: { type: [ 'image/png', 'image/jpeg', 'image/gif' ] },
					multiple: false
				} );

				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var size = image.sizes && image.sizes.medium ? image.sizes.medium : image;

					id.val( image.id );
					preview.empty().append( $( '<img>', { src: size.url, alt: '' } ) );
					remove.prop( 'hidden', false );
				} );
			}

			frame.open();
		} );

		remove.on( 'click', function () {
			id.val( '' );
			preview.html( empty );
			remove.prop( 'hidden', true );
		} );
	} );
}( jQuery ) );
