/**
 * All Listings: the On the website switch and the Sold or Rented button
 * change a listing straight away, without reloading the page.
 */
( function () {
	'use strict';

	var text = window.crcReList || {};

	function send( action, params ) {
		var body = new URLSearchParams();
		var key;

		body.append( 'action', action );
		body.append( 'nonce', text.nonce || '' );

		for ( key in params ) {
			if ( Object.prototype.hasOwnProperty.call( params, key ) ) {
				body.append( key, params[ key ] );
			}
		}

		return window.fetch( text.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.text();
			} )
			.then( function ( raw ) {
				var json;

				// WordPress answers 0 or -1 when the session has ended.
				if ( '0' === raw.trim() || '-1' === raw.trim() ) {
					throw new Error( text.loggedOut || '' );
				}

				try {
					json = JSON.parse( raw );
				} catch ( error ) {
					throw new Error( text.failed || '' );
				}

				if ( ! json.success ) {
					throw new Error( json.data && json.data.message ? json.data.message : text.failed || '' );
				}

				return json.data;
			} );
	}

	function showError( cell, message ) {
		var note = cell.querySelector( '.crc-row-error' );

		if ( ! note ) {
			note = document.createElement( 'p' );
			note.className = 'crc-row-error';
			note.setAttribute( 'role', 'alert' );
			cell.appendChild( note );
		}

		note.textContent = message || text.failed || '';
	}

	function clearError( cell ) {
		var note = cell.querySelector( '.crc-row-error' );

		if ( note ) {
			note.parentNode.removeChild( note );
		}
	}

	// The row's "— Unlisted" after the title, and its status class.
	function markRow( id, on ) {
		var row = document.getElementById( 'post-' + id );
		var title = row ? row.querySelector( '.column-title strong' ) : null;
		var state = title ? title.querySelector( '.crc-state-unlisted' ) : null;
		var quick = document.getElementById( 'inline_' + id );
		var status = quick ? quick.querySelector( '._status' ) : null;
		var before;

		if ( ! row ) {
			return;
		}

		// Quick Edit fills itself from here, so it keeps what the switch did.
		if ( status ) {
			status.textContent = on ? 'publish' : 'private';
		}

		row.classList.toggle( 'status-private', ! on );
		row.classList.toggle( 'status-publish', on );

		if ( ! title ) {
			return;
		}

		if ( ! state ) {
			Array.prototype.forEach.call( title.querySelectorAll( '.post-state' ), function ( span ) {
				if ( span.textContent.trim() === text.unlisted ) {
					state = span;
				}
			} );
		}

		if ( on && state ) {
			before = state.previousSibling;

			if ( before && 3 === before.nodeType && /^\s*[—,]\s*$/.test( before.nodeValue ) ) {
				before.parentNode.removeChild( before );
			}

			state.parentNode.removeChild( state );
		} else if ( ! on && ! state ) {
			state = document.createElement( 'span' );
			state.className = 'post-state crc-state-unlisted';
			state.textContent = text.unlisted || '';
			title.appendChild( document.createTextNode( ' — ' ) );
			title.appendChild( state );
		}
	}

	function flip( button ) {
		var cell = button.closest( '.crc-live-cell' );
		var id = button.getAttribute( 'data-id' );
		var on = 'true' !== button.getAttribute( 'aria-checked' );

		button.disabled = true;
		button.classList.add( 'is-busy' );
		clearError( cell );

		send( text.live, { id: id, on: on ? '1' : '0' } )
			.then( function ( data ) {
				cell.innerHTML = data.html;
				markRow( id, data.on );

				if ( cell.querySelector( '.crc-switch' ) ) {
					cell.querySelector( '.crc-switch' ).focus();
				}
			} )
			.catch( function ( error ) {
				button.disabled = false;
				button.classList.remove( 'is-busy' );
				showError( cell, error.message );
			} );
	}

	function mark( button ) {
		var cell = button.closest( '.crc-availability-cell' );

		button.disabled = true;
		clearError( cell );

		send( text.gone, { id: button.getAttribute( 'data-id' ), gone: button.getAttribute( 'data-gone' ) } )
			.then( function ( data ) {
				cell.innerHTML = data.html;

				if ( cell.querySelector( '.crc-gone' ) ) {
					cell.querySelector( '.crc-gone' ).focus();
				}
			} )
			.catch( function ( error ) {
				button.disabled = false;
				showError( cell, error.message );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest ? event.target.closest( '.crc-switch, .crc-gone' ) : null;

		if ( ! target || target.disabled ) {
			return;
		}

		event.preventDefault();

		if ( target.classList.contains( 'crc-switch' ) ) {
			flip( target );
		} else {
			mark( target );
		}
	} );
}() );
