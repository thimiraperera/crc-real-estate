/**
 * Listing carousel tabs: one tab for each listing category. Clicking a tab,
 * or the arrow keys, Home and End on the tabs, shows its carousel. When the
 * tabs don't all fit (on phones), their row scrolls sideways, fades out on
 * the side with more, and brings the chosen tab into view. The carousels
 * themselves are carousel.js, as for the category carousel.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;

	function setUp( root ) {
		var tabs;
		var row;
		var frame = 0;

		if ( root.crcListings ) {
			return;
		}

		root.crcListings = true;
		tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );
		row = root.querySelector( '.crc-listing-tabs' );

		function panelOf( tab ) {
			return document.getElementById( tab.getAttribute( 'aria-controls' ) );
		}

		// Which sides of the row have tabs running past them, measured on the
		// screen so it works whichever way the page reads.
		function edges() {
			var box;
			var left = Infinity;
			var right = -Infinity;

			frame = 0;

			if ( ! row || ! row.isConnected ) {
				return;
			}

			box = row.getBoundingClientRect();

			tabs.forEach( function ( tab ) {
				var place = tab.getBoundingClientRect();

				left = Math.min( left, place.left );
				right = Math.max( right, place.right );
			} );

			row.classList.toggle( 'is-cut-left', left < box.left - 1 );
			row.classList.toggle( 'is-cut-right', right > box.right + 1 );
		}

		function scheduleEdges() {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( edges );
			}
		}

		// Scrolls the row, never the page, until the tab is clear of the faded sides.
		function reveal( tab ) {
			var box;
			var place;
			var by = 0;
			var room = 40;

			if ( ! row || row.scrollWidth <= row.clientWidth ) {
				return;
			}

			box = row.getBoundingClientRect();
			place = tab.getBoundingClientRect();

			if ( place.left < box.left + room ) {
				by = place.left - box.left - room;
			} else if ( place.right > box.right - room ) {
				by = place.right - box.right + room;
			}

			if ( ! by ) {
				return;
			}

			if ( row.scrollBy ) {
				row.scrollBy( { left: by, behavior: reduce && reduce.matches ? 'auto' : 'smooth' } );
			} else {
				row.scrollLeft += by;
			}
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
				tab.focus( { preventScroll: true } );
			}

			reveal( tab );

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

		if ( row ) {
			row.addEventListener( 'scroll', scheduleEdges, { passive: true } );

			if ( window.ResizeObserver ) {
				new window.ResizeObserver( scheduleEdges ).observe( row );
			} else {
				window.addEventListener( 'resize', scheduleEdges );
			}

			// The names may change width once the site's font has loaded.
			if ( document.fonts && document.fonts.ready ) {
				document.fonts.ready.then( scheduleEdges );
			}

			edges();
		}
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
