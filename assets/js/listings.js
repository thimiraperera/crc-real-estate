/**
 * Listing carousel tabs: one tab for each listing category. Clicking a tab,
 * or the arrow keys, Home and End on the tabs, shows its carousel. The
 * carousels themselves are carousel.js, as for the category carousel.
 */
( function () {
	'use strict';

	function setUp( root ) {
		var tabs;

		if ( root.crcListings ) {
			return;
		}

		root.crcListings = true;
		tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );

		function panelOf( tab ) {
			return document.getElementById( tab.getAttribute( 'aria-controls' ) );
		}

		function select( tab, focus ) {
			var carousel;

			tabs.forEach( function ( other ) {
				var on = other === tab;
				var panel = panelOf( other );

				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;

				if ( panel ) {
					panel.hidden = ! on;
				}
			} );

			if ( focus ) {
				tab.focus();
			}

			// The carousel measures itself now that it can be seen.
			carousel = panelOf( tab ) ? panelOf( tab ).querySelector( '[data-crc-carousel]' ) : null;

			if ( carousel && carousel.crcCarousel ) {
				carousel.crcCarousel.measure();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;

				if ( 'ArrowRight' === event.key ) {
					next = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === event.key ) {
					next = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					next = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					next = tabs[ tabs.length - 1 ];
				} else if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
					event.preventDefault();
					select( tab, false );
					return;
				}

				if ( next ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );
	}

	function setUpAll() {
		var roots = document.querySelectorAll( '[data-crc-listings]' );
		var i;

		for ( i = 0; i < roots.length; i++ ) {
			setUp( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', setUpAll );
	} else {
		setUpAll();
	}

	// Carousels added later in Elementor's editor.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', setUpAll );
			}
		} );
	}
}() );
