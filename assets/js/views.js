/**
 * Counts one view of the listing on this page, at most once a day per browser.
 */
( function () {
	'use strict';

	var config = window.crcReViews;
	var key;
	var now = Date.now();
	var last;

	if ( ! config || ! config.id || ! window.fetch ) {
		return;
	}

	// Leave search engines and automated browsers out of the count.
	if ( navigator.webdriver || /bot|crawl|spider|slurp|lighthouse|headless/i.test( navigator.userAgent ) ) {
		return;
	}

	key = 'crc_re_viewed_' + config.id;

	try {
		last = parseInt( window.localStorage.getItem( key ), 10 );

		if ( last && now - last < config.window * 1000 ) {
			return;
		}

		window.localStorage.setItem( key, String( now ) );
	} catch ( error ) {
		// Storage can be blocked; count the view anyway.
	}

	window.fetch( config.url, {
		method: 'POST',
		credentials: 'omit',
		headers: { Accept: 'application/json' }
	} ).then( function ( response ) {
		return response.ok ? response.json() : null;
	} ).then( function ( data ) {
		var badges;
		var i;
		var labelEl;

		if ( ! data || ! data.label ) {
			return;
		}

		// Show the new count, even on cached pages.
		badges = document.querySelectorAll( '[data-crc-views="' + config.id + '"]' );

		for ( i = 0; i < badges.length; i++ ) {
			labelEl = badges[ i ].querySelector( '.crc-gallery-badge-label' );

			if ( labelEl ) {
				labelEl.textContent = data.label;
			}

			badges[ i ].hidden = false;
		}
	} ).catch( function () {} );
}() );
