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

	function setUp( hint ) {
		var wrap = hint.closest( '.crc-scroll-hint-wrap' );

		if ( hint.crcHint ) {
			return;
		}

		hint.crcHint = true;

		if ( wrap ) {
			wraps.push( wrap );
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
