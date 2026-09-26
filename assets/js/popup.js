/**
 * Popups on listing pages. A button with data-crc-popup="popup-id" opens the
 * popup with that id; its close button, the dark layer and Esc close it.
 * The is-open class runs the opening and closing animations in popup.css.
 */
( function () {
	'use strict';

	var root = document.documentElement;
	var current = null;
	var opener = null;
	var closing = null;

	function animated() {
		return ! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	// Stops the page behind from scrolling, without the page jumping sideways.
	function lockPage() {
		root.style.setProperty( '--crc-popup-scrollbar', Math.max( 0, window.innerWidth - root.clientWidth ) + 'px' );
		root.classList.add( 'crc-popup-open' );
	}

	function unlockPage() {
		root.classList.remove( 'crc-popup-open' );
		root.style.removeProperty( '--crc-popup-scrollbar' );
	}

	function stopClosing() {
		if ( closing ) {
			closing.panel.removeEventListener( 'transitionend', closing.done );
			window.clearTimeout( closing.timer );
			closing = null;
		}
	}

	// Tidies up after a popup has closed, however it was closed.
	function closed( popup ) {
		if ( closing && closing.popup === popup ) {
			stopClosing();
		}

		popup.classList.remove( 'is-open' );

		if ( current !== popup ) {
			return;
		}

		current = null;
		unlockPage();

		if ( opener && opener.focus ) {
			opener.focus();
		}

		opener = null;
	}

	function hide( popup ) {
		if ( 'function' === typeof popup.close ) {
			if ( popup.open ) {
				popup.close(); // Fires "close", which tidies up.
				return;
			}
		} else {
			popup.removeAttribute( 'open' );
		}

		closed( popup );
	}

	function prepare( popup ) {
		if ( popup.crcPopupReady ) {
			return;
		}

		popup.crcPopupReady = true;

		popup.addEventListener( 'close', function () {
			closed( popup );
		} );

		// Esc: close with the animation instead of at once.
		popup.addEventListener( 'cancel', function ( event ) {
			event.preventDefault();
			close( popup );
		} );
	}

	function open( popup, trigger ) {
		var body = popup.querySelector( '.crc-popup-body' );
		var closeButton = popup.querySelector( '.crc-popup-close' );

		prepare( popup );
		stopClosing();

		if ( current && current !== popup ) {
			hide( current );
		}

		if ( current !== popup ) {
			current = popup;
			opener = trigger || null;
			lockPage();

			if ( 'function' === typeof popup.showModal ) {
				if ( ! popup.open ) {
					popup.showModal();
				}
			} else {
				popup.setAttribute( 'open', '' );
			}

			if ( body ) {
				body.scrollTop = 0;
			}

			// Draw the closed look first, so the opening animates.
			popup.classList.remove( 'is-open' );
			void popup.offsetWidth;
		}

		popup.classList.add( 'is-open' );

		if ( closeButton ) {
			closeButton.focus( { preventScroll: true } );
		}
	}

	function close( popup ) {
		var panel;
		var done;

		if ( ! popup || popup !== current || ! popup.classList.contains( 'is-open' ) ) {
			return;
		}

		popup.classList.remove( 'is-open' );
		panel = popup.querySelector( '.crc-popup-dialog' );

		if ( ! panel || ! animated() ) {
			hide( popup );
			return;
		}

		stopClosing();

		done = function ( event ) {
			if ( event && event.target !== panel ) {
				return;
			}

			stopClosing();
			hide( popup );
		};

		closing = {
			popup: popup,
			panel: panel,
			done: done,
			timer: window.setTimeout( done, 400 )
		};

		panel.addEventListener( 'transitionend', done );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var button = target.closest ? target.closest( '[data-crc-popup]' ) : null;
		var popup;

		if ( button ) {
			popup = document.getElementById( button.getAttribute( 'data-crc-popup' ) );

			if ( popup && popup.classList.contains( 'crc-popup' ) ) {
				event.preventDefault();
				open( popup, button );
			}

			return;
		}

		if ( ! current || ! current.contains( target ) ) {
			return;
		}

		if ( target === current || ( target.closest && target.closest( '[data-crc-popup-close]' ) ) ) {
			event.preventDefault();
			close( current );
		}
	} );

	// Browsers without the dialog element don't send "cancel" on Esc.
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && current && 'function' !== typeof current.showModal ) {
			close( current );
		}
	} );
}() );
