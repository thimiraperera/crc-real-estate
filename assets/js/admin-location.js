/**
 * Location box: a map to mark the listing's exact place, with a search.
 * Only people who edit listings see this map.
 */
( function () {
	'use strict';

	var text = window.crcReLocation || {};
	var box = document.querySelector( '.crc-location-box' );
	var L = window.L;
	var mapEl, latField, lngField, searchField, findButton, results, clearButton;
	var map, pin, start;
	var lastSearch = 0;

	if ( ! box || ! L ) {
		return;
	}

	mapEl = box.querySelector( '.crc-location-box-map' );
	latField = document.getElementById( 'crc-location-lat' );
	lngField = document.getElementById( 'crc-location-lng' );
	searchField = document.getElementById( 'crc-location-search' );
	findButton = box.querySelector( '.crc-location-box-find' );
	results = box.querySelector( '.crc-location-box-results' );
	clearButton = box.querySelector( '.crc-location-box-clear' );

	// The place in the fields, when both are numbers in range.
	function typedPlace() {
		var lat = parseFloat( latField.value );
		var lng = parseFloat( lngField.value );

		if ( isFinite( lat ) && isFinite( lng ) && Math.abs( lat ) <= 90 && Math.abs( lng ) <= 180 ) {
			return L.latLng( lat, lng );
		}

		return null;
	}

	function fill( place ) {
		latField.value = place.lat.toFixed( 6 );
		lngField.value = place.lng.toFixed( 6 );
	}

	function mark( place, zoom ) {
		if ( pin ) {
			pin.setLatLng( place );
		} else {
			pin = L.marker( place, { draggable: true, autoPan: true } ).addTo( map );
			pin.on( 'dragend', function () {
				fill( pin.getLatLng() );
			} );
		}

		fill( place );

		if ( zoom ) {
			map.setView( place, zoom );
		}
	}

	// A marked place, or the starting point from Settings (Galle, Sri Lanka, unless changed).
	start = typedPlace();
	map = L.map( mapEl ).setView( start || L.latLng( text.start ? text.start.lat : 6.0535, text.start ? text.start.lng : 80.221 ), start ? 15 : 11 );

	// OpenStreetMap needs to know which site asks for its maps and search, so
	// these send the site's address (never the page's), whatever the site's
	// Referrer-Policy says.
	L.tileLayer( text.tiles, {
		attribution: text.credit,
		maxZoom: 19,
		referrerPolicy: 'strict-origin-when-cross-origin'
	} ).addTo( map );

	if ( start ) {
		mark( start );
	}

	map.on( 'click', function ( event ) {
		mark( event.latlng );
	} );

	// Typed or pasted numbers, including both in Latitude: "6.0535, 80.2210".
	function typed() {
		var both = /^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/.exec( latField.value );
		var place;

		if ( both ) {
			latField.value = both[ 1 ];
			lngField.value = both[ 2 ];
		}

		place = typedPlace();

		if ( place ) {
			mark( place, Math.max( map.getZoom(), 15 ) );
		}
	}

	latField.addEventListener( 'change', typed );
	lngField.addEventListener( 'change', typed );

	clearButton.addEventListener( 'click', function ( event ) {
		event.preventDefault();
		latField.value = '';
		lngField.value = '';

		if ( pin ) {
			map.removeLayer( pin );
			pin = null;
		}
	} );

	function show( items, message ) {
		var i;
		var li;
		var button;

		results.innerHTML = '';

		if ( message ) {
			li = document.createElement( 'li' );
			li.className = 'crc-location-box-message';
			li.textContent = message;
			results.appendChild( li );
		}

		for ( i = 0; i < items.length; i++ ) {
			li = document.createElement( 'li' );
			button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button-link';
			button.textContent = items[ i ].display_name;
			button.setAttribute( 'data-lat', items[ i ].lat );
			button.setAttribute( 'data-lng', items[ i ].lon );
			li.appendChild( button );
			results.appendChild( li );
		}

		results.hidden = ! message && ! items.length;
	}

	/*
	 * Search with OpenStreetMap's Nominatim: only on Enter or the Search
	 * button, never while typing, and at most once a second, as its rules ask.
	 */
	function search() {
		var query = searchField.value.trim();
		var bounds = map.getBounds();
		var url;

		if ( ! query || Date.now() - lastSearch < 1000 ) {
			return;
		}

		lastSearch = Date.now();
		findButton.disabled = true;
		show( [], text.searching );

		url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5' +
			'&q=' + encodeURIComponent( query ) +
			'&viewbox=' + [ bounds.getWest(), bounds.getNorth(), bounds.getEast(), bounds.getSouth() ].join( ',' ) +
			'&accept-language=' + encodeURIComponent( document.documentElement.lang || 'en' );

		window.fetch( url, {
			headers: { Accept: 'application/json' },
			referrerPolicy: 'strict-origin-when-cross-origin'
		} ).then( function ( response ) {
			return response.ok ? response.json() : Promise.reject( response.status );
		} ).then( function ( found ) {
			found = Array.isArray( found ) ? found : [];
			show( found, found.length ? '' : text.noResults );
		} ).catch( function () {
			show( [], text.failed );
		} ).then( function () {
			findButton.disabled = false;
		} );
	}

	findButton.addEventListener( 'click', function ( event ) {
		event.preventDefault();
		search();
	} );

	// Enter searches, instead of saving the listing.
	searchField.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			search();
		}
	} );

	results.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( 'button[data-lat]' ) : null;

		if ( ! button ) {
			return;
		}

		event.preventDefault();
		mark( L.latLng( parseFloat( button.getAttribute( 'data-lat' ) ), parseFloat( button.getAttribute( 'data-lng' ) ) ), 16 );
		show( [], '' );
	} );

	// The map needs to know its size after the box is opened, moved or resized.
	if ( window.ResizeObserver ) {
		new window.ResizeObserver( function () {
			map.invalidateSize();
		} ).observe( mapEl );
	}
}() );
