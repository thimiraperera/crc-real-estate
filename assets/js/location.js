/**
 * Location maps on listing pages: the area around the property, drawn as a
 * circle. The page only knows the circle, never the exact place.
 */
( function () {
	'use strict';

	function draw( el ) {
		var config;
		var touch;
		var map;
		var area;

		if ( el.crcMap || ! window.L ) {
			return;
		}

		try {
			config = JSON.parse( el.getAttribute( 'data-crc-map' ) );
		} catch ( error ) {
			return;
		}

		// On phones, one finger scrolls the page instead of moving the map.
		touch = window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches;

		map = window.L.map( el, {
			scrollWheelZoom: false,
			dragging: ! touch,
			maxZoom: 14
		} );

		// OpenStreetMap needs to know which site asks for its maps, so the tiles
		// send the site's address (never the page's), whatever the site's
		// Referrer-Policy says.
		window.L.tileLayer( config.tiles, {
			attribution: config.credit,
			maxZoom: 19,
			referrerPolicy: 'strict-origin-when-cross-origin'
		} ).addTo( map );

		area = window.L.circle( [ config.lat, config.lng ], {
			radius: config.radius,
			className: 'crc-location-area',
			interactive: false
		} ).addTo( map );

		map.fitBounds( area.getBounds(), { padding: [ 16, 16 ] } );
		el.crcMap = map;
	}

	function start() {
		var maps = document.querySelectorAll( '.crc-location-map[data-crc-map]' );
		var i;

		for ( i = 0; i < maps.length; i++ ) {
			draw( maps[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
