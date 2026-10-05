/**
 * FAQs: questions open and close smoothly, one at a time: opening one closes
 * the one that was open. Without the script they still open and close, one
 * at a time in browsers that know the details element's name, just without
 * the motion.
 */
( function () {
	'use strict';

	var DURATION = 300;

	function still() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	// Opens or closes a question, sliding its answer's height.
	function toggle( item, open ) {
		var answer = item.querySelector( '.crc-faq-answer' );
		var from = item.open && answer ? answer.getBoundingClientRect().height : 0;
		var motion;
		var to;

		function done() {
			// Only the latest motion finishes the question.
			if ( item.crcMotion !== motion ) {
				return;
			}

			item.crcMotion = null;
			item.classList.remove( 'is-closing' );

			if ( ! open ) {
				item.open = false;
			}
		}

		// A question tapped again while it moves turns back from where it is.
		if ( item.crcMotion ) {
			item.crcMotion.cancel();
			item.crcMotion = null;
		}

		item.classList.toggle( 'is-closing', ! open );

		if ( ! answer || ! answer.animate || still() ) {
			item.open = open;
			item.classList.remove( 'is-closing' );
			return;
		}

		item.open = true;
		to = open ? answer.getBoundingClientRect().height : 0;

		motion = answer.animate(
			[ { height: from + 'px' }, { height: to + 'px' } ],
			{ duration: DURATION, easing: 'ease' }
		);
		item.crcMotion = motion;

		// A motion stopped by a newer one just ends; nothing to report.
		if ( motion.finished && motion.finished.then ) {
			motion.finished.then( done, function () {} );
		} else {
			motion.onfinish = done;
		}
	}

	function setUp( list ) {
		var items = Array.prototype.slice.call( list.querySelectorAll( '.crc-faq-item' ) );

		if ( list.crcFaq ) {
			return;
		}

		list.crcFaq = true;

		items.forEach( function ( item ) {
			// The script keeps one open at a time itself, so the one closing can slide shut.
			item.removeAttribute( 'name' );
			watch( item, items );
		} );
	}

	function watch( item, items ) {
		var question = item.querySelector( '.crc-faq-question' );

		if ( ! question ) {
			return;
		}

		question.addEventListener( 'click', function ( event ) {
			var opening = ! item.open || item.classList.contains( 'is-closing' );

			event.preventDefault();

			// Opening a question closes the one that was open.
			if ( opening ) {
				items.forEach( function ( other ) {
					if ( other !== item && other.open && ! other.classList.contains( 'is-closing' ) ) {
						toggle( other, false );
					}
				} );
			}

			toggle( item, opening );
		} );

		// The browser can open a question by itself, e.g. to show a word found with Ctrl+F: the others close.
		item.addEventListener( 'toggle', function () {
			if ( ! item.open || item.classList.contains( 'is-closing' ) ) {
				return;
			}

			items.forEach( function ( other ) {
				if ( other !== item && other.open && ! other.classList.contains( 'is-closing' ) ) {
					toggle( other, false );
				}
			} );
		} );
	}

	function start() {
		var lists = document.querySelectorAll( '[data-crc-faq]' );
		var i;

		for ( i = 0; i < lists.length; i++ ) {
			setUp( lists[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	// Questions added later in Elementor's editor.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', start );
			}
		} );
	}
}() );
