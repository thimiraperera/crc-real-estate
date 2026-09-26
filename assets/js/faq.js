/**
 * FAQs: questions open and close smoothly. Without the script they still
 * open and close, just without the motion.
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
		var items = list.querySelectorAll( '.crc-faq-item' );
		var i;

		if ( list.crcFaq ) {
			return;
		}

		list.crcFaq = true;

		for ( i = 0; i < items.length; i++ ) {
			watch( items[ i ] );
		}
	}

	function watch( item ) {
		var question = item.querySelector( '.crc-faq-question' );

		if ( ! question ) {
			return;
		}

		question.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			toggle( item, ! item.open || item.classList.contains( 'is-closing' ) );
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
}() );
