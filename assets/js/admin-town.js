/**
 * Town field in the District and town box: suggests the towns already used
 * on other listings as you type (any new town can be typed too), fills the
 * district in from a suggested town while the district is empty, and fills
 * itself in from the place chosen in the Location box while it is empty.
 */
( function () {
	'use strict';

	var text = window.crcReTown || {};
	var box = document.querySelector( '.crc-town-box' );
	var input, list, note;
	var shown = [];
	var active = -1;
	var cache = {};
	var asked = 0;
	var typing = 0;

	if ( ! box || ! window.fetch ) {
		return;
	}

	input = box.querySelector( '.crc-combo-input' );
	list = box.querySelector( '.crc-combo-list' );
	note = box.querySelector( '.crc-town-note' );

	function say( message ) {
		note.textContent = message || '';
	}

	function search( query ) {
		var key = query.trim().toLowerCase();
		var body = new window.URLSearchParams();

		if ( cache[ key ] ) {
			return Promise.resolve( cache[ key ] );
		}

		body.append( 'action', text.action );
		body.append( 'nonce', text.nonce );
		body.append( 'q', query );

		return window.fetch( text.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( json ) {
			var found = json && json.success && json.data && Array.isArray( json.data.towns ) ? json.data.towns : [];

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

	function count( town ) {
		if ( ! town.count ) {
			return '';
		}

		return 1 === town.count ? text.usedOne : String( text.used || '' ).replace( '%s', town.count );
	}

	function open( found ) {
		shown = found;
		list.innerHTML = '';

		if ( ! found.length ) {
			close();
			return;
		}

		found.forEach( function ( town, i ) {
			var li = document.createElement( 'li' );
			var name = document.createElement( 'span' );
			var hint = document.createElement( 'span' );

			li.id = 'crc-town-option-' + i;
			li.className = 'crc-combo-option';
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'aria-selected', 'false' );
			li.setAttribute( 'data-index', String( i ) );
			name.className = 'crc-combo-name';
			name.textContent = town.name;
			hint.className = 'crc-combo-hint';
			hint.textContent = [ town.district, count( town ) ].filter( Boolean ).join( ' · ' );
			li.appendChild( name );
			li.appendChild( hint );
			list.appendChild( li );
		} );

		list.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
		setActive( -1 );
	}

	// Says whether what is typed is a town already used.
	function tell( found ) {
		var typed = input.value.trim().toLowerCase();
		var known = found.some( function ( town ) {
			return town.name.toLowerCase() === typed;
		} );

		say( typed && ! known ? text.isNew : '' );
	}

	function lookUp() {
		var query = input.value;
		var mine = ++asked;

		if ( ! query.trim() ) {
			close();
			say( '' );
			return;
		}

		search( query ).then( function ( found ) {
			if ( mine === asked ) {
				tell( found );

				if ( document.activeElement === input ) {
					open( found );
				}
			}
		}, function () {
			if ( mine === asked ) {
				close();
			}
		} );
	}

	// The district box fills in from the town's district while it is empty.
	function fillDistrict( town ) {
		if ( ! town.code ) {
			return;
		}

		document.dispatchEvent( new window.CustomEvent( 'crc:place', {
			detail: { address: { 'ISO3166-2-lvl5': town.code }, display_name: '' }
		} ) );
	}

	// Anything still being looked up is forgotten, so it can't change what is shown.
	function settle() {
		window.clearTimeout( typing );
		asked++;
	}

	function choose( town ) {
		settle();
		input.value = town.name;
		close();
		say( '' );
		fillDistrict( town );
	}

	input.addEventListener( 'input', function () {
		window.clearTimeout( typing );
		typing = window.setTimeout( lookUp, 150 );
	} );

	input.addEventListener( 'keydown', function ( event ) {
		var total = shown.length;

		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();

			if ( list.hidden ) {
				lookUp();
				return;
			}

			if ( total ) {
				setActive( 'ArrowDown' === event.key ? ( active + 1 ) % total : ( active <= 0 ? total - 1 : active - 1 ) );
			}
		} else if ( 'Enter' === event.key ) {
			// Enter picks a town; it never saves the listing.
			event.preventDefault();

			if ( ! list.hidden && active >= 0 && shown[ active ] ) {
				choose( shown[ active ] );
			} else {
				close();
			}
		} else if ( 'Escape' === event.key ) {
			if ( ! list.hidden ) {
				event.preventDefault();
				close();
			}
		} else if ( 'Tab' === event.key && ! list.hidden && active >= 0 && shown[ active ] ) {
			choose( shown[ active ] );
		}
	} );

	input.addEventListener( 'blur', function () {
		window.setTimeout( function () {
			if ( document.activeElement !== input ) {
				close();
			}
		}, 0 );
	} );

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

	// The town of a place, as maps name it: "Colombo 3" for 00300 in Colombo.
	function townOf( address ) {
		var zone = /^0?(\d{1,2})00$/.exec( String( address.postcode || '' ).trim() );
		var colombo = 'LK-11' === address[ 'ISO3166-2-lvl5' ] || 'Colombo' === address.city;
		var name = '';

		if ( zone && colombo && parseInt( zone[ 1 ], 10 ) >= 1 && parseInt( zone[ 1 ], 10 ) <= 15 ) {
			return 'Colombo ' + parseInt( zone[ 1 ], 10 );
		}

		[ 'town', 'village', 'suburb', 'city_district', 'city', 'hamlet', 'municipality' ].some( function ( key ) {
			name = address[ key ] ? String( address[ key ] ) : '';
			return '' !== name;
		} );

		return name;
	}

	// A place chosen in the Location box's search, while the town is empty.
	document.addEventListener( 'crc:place', function ( event ) {
		var place = event.detail || {};
		var name = place.address ? townOf( place.address ) : '';

		if ( name && ! input.value.trim() ) {
			settle();
			close();
			input.value = name;
			say( String( text.filled || '' ).replace( '%s', name ) );
		}
	} );
}() );
