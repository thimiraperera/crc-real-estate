/**
 * District box: a search box that asks the site for matching districts as
 * you type, and fills itself in from the place chosen in the Location box
 * while it is empty.
 */
( function () {
	'use strict';

	var text = window.crcReDistrict || {};
	var box = document.querySelector( '.crc-district-box' );
	var input, hidden, list, toggle, note;
	var shown = [];
	var active = -1;
	var cache = {};
	var asked = 0;
	var typing = 0;
	var pinTimer = 0;
	var lastLookup = 0;

	if ( ! box || ! window.fetch ) {
		return;
	}

	input = box.querySelector( '.crc-combo-input' );
	hidden = box.querySelector( '.crc-district-id' );
	list = box.querySelector( '.crc-combo-list' );
	toggle = box.querySelector( '.crc-combo-toggle' );
	note = box.querySelector( '.crc-district-note' );

	function format( value, word ) {
		return String( value || '' ).replace( '%s', word );
	}

	function chosenName() {
		return hidden.getAttribute( 'data-name' ) || '';
	}

	// Asks the site; a place from the map is sent as its parts.
	function ask( params ) {
		var body = new window.URLSearchParams();

		body.append( 'action', text.action );
		body.append( 'nonce', text.nonce );

		Object.keys( params ).forEach( function ( key ) {
			if ( Array.isArray( params[ key ] ) ) {
				params[ key ].forEach( function ( value ) {
					body.append( key + '[]', value );
				} );
			} else {
				body.append( key, params[ key ] );
			}
		} );

		return window.fetch( text.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( json ) {
			return json && json.success && json.data && Array.isArray( json.data.districts ) ? json.data.districts : Promise.reject( json );
		} );
	}

	function search( query ) {
		var key = query.trim().toLowerCase();

		if ( cache[ key ] ) {
			return Promise.resolve( cache[ key ] );
		}

		return ask( { q: query } ).then( function ( found ) {
			cache[ key ] = found;
			return found;
		} );
	}

	function setActive( index ) {
		var items = list.querySelectorAll( '.crc-combo-option' );
		var i;

		active = index;

		for ( i = 0; i < items.length; i++ ) {
			items[ i ].classList.toggle( 'is-active', i === index );
			items[ i ].setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
		}

		if ( index >= 0 && items[ index ] ) {
			input.setAttribute( 'aria-activedescendant', items[ index ].id );
			items[ index ].scrollIntoView( { block: 'nearest' } );
		} else {
			input.removeAttribute( 'aria-activedescendant' );
		}
	}

	function close() {
		list.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
		setActive( -1 );
	}

	// The best match is ready for Enter, except when the whole list is shown.
	function open( found, message, typed ) {
		var current = hidden.value;

		shown = found;
		list.innerHTML = '';

		found.forEach( function ( district, i ) {
			var li = document.createElement( 'li' );
			var name = document.createElement( 'span' );
			var province = document.createElement( 'span' );

			li.id = 'crc-district-option-' + i;
			li.className = 'crc-combo-option' + ( String( district.id ) === current ? ' is-chosen' : '' );
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'aria-selected', 'false' );
			li.setAttribute( 'data-index', String( i ) );
			name.className = 'crc-combo-name';
			name.textContent = district.name;
			province.className = 'crc-combo-hint';
			province.textContent = district.province;
			li.appendChild( name );
			li.appendChild( province );
			list.appendChild( li );
		} );

		if ( ! found.length ) {
			list.innerHTML = '';
			list.appendChild( document.createElement( 'li' ) );
			list.firstChild.className = 'crc-combo-empty';
			list.firstChild.textContent = message || text.none;
		}

		list.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
		setActive( found.length && ( typed || 1 === found.length ) ? 0 : -1 );
	}

	// Shows the districts for what is typed; only the latest answer is shown.
	function lookUp( query ) {
		var mine = ++asked;

		search( query ).then( function ( found ) {
			if ( mine === asked && document.activeElement === input ) {
				open( found, '', '' !== query.trim() );
			}
		}, function () {
			if ( mine === asked ) {
				open( [], text.failed );
			}
		} );
	}

	function say( message ) {
		note.textContent = message || '';
	}

	function choose( district ) {
		var before = hidden.value;

		hidden.value = district ? String( district.id ) : '';
		hidden.setAttribute( 'data-name', district ? district.name : '' );
		input.value = district ? district.name : '';
		close();

		if ( before !== hidden.value ) {
			hidden.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		}
	}

	// Leaving the box: an exact name counts, empty clears, anything else goes back.
	function settle() {
		var typed = input.value.trim().toLowerCase();
		var match = null;

		close();

		if ( '' === typed ) {
			if ( hidden.value ) {
				choose( null );
			}

			return;
		}

		function exact( found ) {
			found.forEach( function ( district ) {
				if ( ! match && district.name.toLowerCase() === typed ) {
					match = district;
				}
			} );

			return match;
		}

		if ( exact( shown ) ) {
			choose( match );
			return;
		}

		// Typed in full before the list came: look the name up.
		search( input.value ).then( function ( found ) {
			if ( exact( found ) ) {
				choose( match );
			} else if ( document.activeElement !== input ) {
				input.value = chosenName();
			}
		}, function () {
			input.value = chosenName();
		} );
	}

	input.addEventListener( 'input', function () {
		say( '' );
		window.clearTimeout( typing );
		typing = window.setTimeout( function () {
			lookUp( input.value );
		}, 150 );
	} );

	input.addEventListener( 'click', function () {
		if ( list.hidden ) {
			lookUp( input.value === chosenName() ? '' : input.value );
		}
	} );

	input.addEventListener( 'keydown', function ( event ) {
		var count = shown.length;

		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();

			if ( list.hidden ) {
				lookUp( input.value === chosenName() ? '' : input.value );
				return;
			}

			if ( count ) {
				setActive( 'ArrowDown' === event.key ? ( active + 1 ) % count : ( active <= 0 ? count - 1 : active - 1 ) );
			}
		} else if ( 'Enter' === event.key ) {
			// Enter picks a district; it never saves the listing.
			event.preventDefault();

			if ( ! list.hidden && active >= 0 && shown[ active ] ) {
				choose( shown[ active ] );
			} else {
				settle();
			}
		} else if ( 'Escape' === event.key ) {
			if ( ! list.hidden ) {
				event.preventDefault();
				close();
				input.value = chosenName();
			}
		} else if ( 'Tab' === event.key && ! list.hidden && active >= 0 && shown[ active ] ) {
			choose( shown[ active ] );
		}
	} );

	input.addEventListener( 'blur', function () {
		// After a click on an option, which keeps the focus in the box.
		window.setTimeout( function () {
			if ( document.activeElement !== input ) {
				settle();
			}
		}, 0 );
	} );

	// Clicking an option keeps the cursor in the box, then picks it.
	list.addEventListener( 'mousedown', function ( event ) {
		event.preventDefault();
	} );

	list.addEventListener( 'click', function ( event ) {
		var option = event.target.closest ? event.target.closest( '.crc-combo-option' ) : null;

		if ( option ) {
			choose( shown[ parseInt( option.getAttribute( 'data-index' ), 10 ) ] );
		}
	} );

	list.addEventListener( 'mousemove', function ( event ) {
		var option = event.target.closest ? event.target.closest( '.crc-combo-option' ) : null;

		if ( option ) {
			setActive( parseInt( option.getAttribute( 'data-index' ), 10 ) );
		}
	} );

	toggle.addEventListener( 'mousedown', function ( event ) {
		event.preventDefault();
	} );

	toggle.addEventListener( 'click', function () {
		if ( ! list.hidden ) {
			close();
			return;
		}

		input.focus();
		lookUp( '' );
	} );

	// The district of a place, while the box is empty.
	function fillFrom( names ) {
		if ( hidden.value || ! names.length ) {
			return;
		}

		ask( { place: names } ).then( function ( found ) {
			if ( found.length && ! hidden.value ) {
				choose( found[ 0 ] );
				say( format( text.filled, found[ 0 ].name ) );
			}
		}, function () {} );
	}

	function placeNames( address, display ) {
		var names = [];

		address = address || {};
		[ 'ISO3166-2-lvl5', 'state_district', 'county', 'city', 'town', 'village' ].forEach( function ( key ) {
			if ( address[ key ] ) {
				names.push( String( address[ key ] ) );
			}
		} );

		if ( display ) {
			names.push( String( display ) );
		}

		return names;
	}

	// A place chosen in the Location box's search.
	document.addEventListener( 'crc:place', function ( event ) {
		var place = event.detail || {};

		fillFrom( placeNames( place.address, place.display_name ) );
	} );

	/*
	 * A place marked on the map or typed in: OpenStreetMap says which
	 * district it is in. Only while the box is empty, once the pin has
	 * settled, and at most once a second, as its rules ask.
	 */
	document.addEventListener( 'crc:pin', function ( event ) {
		var place = event.detail || {};

		if ( hidden.value || ! isFinite( place.lat ) || ! isFinite( place.lng ) ) {
			return;
		}

		window.clearTimeout( pinTimer );
		pinTimer = window.setTimeout( function () {
			lastLookup = Date.now();
			window.fetch( 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=10&addressdetails=1&accept-language=en' +
				'&lat=' + encodeURIComponent( place.lat ) + '&lon=' + encodeURIComponent( place.lng ), {
				headers: { Accept: 'application/json' },
				referrerPolicy: 'strict-origin-when-cross-origin'
			} ).then( function ( response ) {
				return response.ok ? response.json() : Promise.reject( response.status );
			} ).then( function ( found ) {
				fillFrom( placeNames( found && found.address, found && found.display_name ) );
			} ).catch( function () {} );
		}, Math.max( 600, 1100 - ( Date.now() - lastLookup ) ) );
	} );
}() );
