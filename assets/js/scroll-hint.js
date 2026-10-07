/**
 * Scroll down hint ([crc_scroll_hint]): a click or tap scrolls smoothly to
 * what is under the section it is in (or to its target), and it fades away
 * once the page has been scrolled, coming back at the top.
 */
( function () {
	'use strict';

	var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var wraps = [];
	var waiting = false;

	// Where a hint scrolls to: its target, or the bottom of its section.
	function aim( hint, wrap ) {
		var selector = wrap ? wrap.getAttribute( 'data-target' ) || '' : '';
		var offset = wrap ? parseInt( wrap.getAttribute( 'data-offset' ), 10 ) || 0 : 0;
		var target = null;
		var section;

		if ( selector ) {
			try {
				target = document.querySelector( selector );
			} catch ( error ) {
				target = null;
			}
		}

		if ( target ) {
			return target.getBoundingClientRect().top + window.pageYOffset - offset;
		}

		section = hint.closest( '.hero' ) || hint.closest( '.e-con.e-parent' ) || hint.closest( '.elementor-top-section' ) || hint.closest( 'section' );

		if ( section ) {
			return section.getBoundingClientRect().bottom + window.pageYOffset - offset;
		}

		return window.pageYOffset + window.innerHeight * 0.85;
	}

	function go( hint, wrap ) {
		var top = Math.max( 0, Math.round( aim( hint, wrap ) ) );

		try {
			window.scrollTo( { top: top, behavior: still ? 'auto' : 'smooth' } );
		} catch ( error ) {
			window.scrollTo( 0, top );
		}
	}

	// Scrolled down a little: the hints fade away; back at the top they return.
	function update() {
		var scrolled = window.pageYOffset > 40;

		waiting = false;
		wraps.forEach( function ( wrap ) {
			wrap.classList.toggle( 'is-scrolled', scrolled );
		} );
	}

	/*
	 * The rounding that keeps the line evenly inside the section's own
	 * rounded corners: the section's corner rounding less the gap, measured
	 * to the middle of the line. The section is the box the hint is set in.
	 */
	function evenRound( wrap, edge ) {
		var widget = wrap.closest( '.elementor-widget' );
		var holder = ( widget || wrap ).offsetParent;
		var corner;
		var gap;

		if ( ! holder || holder === document.body || holder === document.documentElement ) {
			return 0;
		}

		corner = String( window.getComputedStyle( holder ).borderBottomLeftRadius || '0' ).split( ' ' )[ 0 ];
		corner = -1 !== corner.indexOf( '%' ) ? parseFloat( corner ) * holder.offsetWidth / 100 : parseFloat( corner ) || 0;
		gap = parseFloat( window.getComputedStyle( wrap ).paddingLeft ) || 0;

		return Math.max( 0, corner - gap - edge );
	}

	/*
	 * The U-shaped line: two halves, each from the bottom middle out along the
	 * bottom, round the corner and up its side, drawn to the line's width so
	 * the corners keep their rounding however wide the section is.
	 */
	function drawLine( wrap ) {
		var svg = wrap.querySelector( '.crc-scroll-hint-lines' );
		var halves = svg ? svg.querySelectorAll( 'path' ) : [];
		var box = svg ? svg.getBoundingClientRect() : null;
		var edge = 1;
		var width;
		var height;
		var bottom;
		var middle;
		var round;

		if ( halves.length < 2 || ! box || box.width < 4 || box.height < 4 ) {
			return;
		}

		width = Math.round( box.width );
		height = Math.round( box.height );
		bottom = height - edge;
		middle = width / 2;
		round = wrap.getAttribute( 'data-radius' ) || 'auto';
		round = 'auto' === round ? evenRound( wrap, edge ) : parseFloat( round ) || 0;
		round = Math.max( 0, Math.min( round, middle - edge, bottom - edge ) );

		svg.setAttribute( 'viewBox', '0 0 ' + width + ' ' + height );
		halves[ 0 ].setAttribute( 'd', 'M' + middle + ' ' + bottom + 'H' + ( edge + round ) + 'A' + round + ' ' + round + ' 0 0 1 ' + edge + ' ' + ( bottom - round ) + 'V' + edge );
		halves[ 1 ].setAttribute( 'd', 'M' + middle + ' ' + bottom + 'H' + ( width - edge - round ) + 'A' + round + ' ' + round + ' 0 0 0 ' + ( width - edge ) + ' ' + ( bottom - round ) + 'V' + edge );
	}

	function setUp( hint ) {
		var wrap = hint.closest( '.crc-scroll-hint-wrap' );
		var waitingLine = 0;

		if ( hint.crcHint ) {
			return;
		}

		hint.crcHint = true;

		if ( wrap ) {
			wraps.push( wrap );

			if ( wrap.classList.contains( 'has-frame' ) ) {
				drawLine( wrap );

				// Drawn again when the section changes width.
				if ( window.ResizeObserver ) {
					new window.ResizeObserver( function () {
						drawLine( wrap );
					} ).observe( wrap );
				} else {
					window.addEventListener( 'resize', function () {
						window.clearTimeout( waitingLine );
						waitingLine = window.setTimeout( function () {
							drawLine( wrap );
						}, 100 );
					} );
				}
			}
		}

		hint.addEventListener( 'click', function () {
			go( hint, wrap );
		} );

		hint.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
				event.preventDefault();
				go( hint, wrap );
			}
		} );
	}

	function setUpAll() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-crc-scroll-hint]' ), setUp );
		update();
	}

	window.addEventListener( 'scroll', function () {
		if ( ! waiting ) {
			waiting = true;
			window.requestAnimationFrame( update );
		}
	}, { passive: true } );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', setUpAll );
	} else {
		setUpAll();
	}

	// Added later in Elementor's editor.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', setUpAll );
			}
		} );
	}
}() );
