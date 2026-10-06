/**
 * Listing search: the search box ([crc_listing_search]), the filters
 * ([crc_listing_filters]) and the results' Sort by ([crc_listing_results]).
 *
 * The place box suggests towns and districts with listings as you type. The
 * chosen category shows its own choices. Search and Show listings open the
 * category's page with only the choices that were made in its address, and
 * Sort by keeps the search and starts from its first page. On phones the
 * filters fold away behind a Filters bar.
 */
( function () {
	'use strict';

	var text = window.crcReSearch || {};
	var each = function ( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	};

	// The chosen category and its page: a tab, a dropdown, or none.
	function categoryOf( form ) {
		var field = form.querySelector( '[name="category"]' );
		var chosen;

		if ( ! field ) {
			return { value: '', url: form.getAttribute( 'action' ) || '' };
		}

		if ( 'SELECT' === field.tagName ) {
			chosen = field.options[ field.selectedIndex ];
		} else {
			chosen = form.querySelector( '[name="category"]:checked' );
		}

		return {
			value: chosen ? chosen.value : '',
			url: chosen ? chosen.getAttribute( 'data-url' ) || '' : ''
		};
	}

	// The address for a form: the category's page, then each choice made.
	function address( form ) {
		var base = categoryOf( form ).url || form.getAttribute( 'action' ) || window.location.pathname;
		var params = [];

		each( form.elements, function ( field ) {
			var group = field.closest ? field.closest( '[data-for]' ) : null;
			var value;

			if ( ! field.name || 'category' === field.name || field.matches( ':disabled' ) || ( group && group.hidden ) ) {
				return;
			}

			if ( ( 'radio' === field.type || 'checkbox' === field.type ) && ! field.checked ) {
				return;
			}

			if ( 'submit' === field.type || 'button' === field.type ) {
				return;
			}

			value = String( field.value || '' ).trim();

			if ( '' !== value ) {
				params.push( encodeURIComponent( field.name ) + '=' + encodeURIComponent( value ).replace( /%20/g, '+' ) );
			}
		} );

		return base + ( params.length ? ( -1 === base.indexOf( '?' ) ? '?' : '&' ) + params.join( '&' ) : '' );
	}

	// Shows the chosen category's own choices; the others are switched off.
	function showGroups( form ) {
		var category = categoryOf( form ).value || 'all';

		each( form.querySelectorAll( '[data-for]' ), function ( group ) {
			var on = -1 !== ( ' ' + group.getAttribute( 'data-for' ) + ' ' ).indexOf( ' ' + category + ' ' );

			group.hidden = ! on;

			if ( 'FIELDSET' === group.tagName ) {
				group.disabled = ! on;
			}
		} );
	}

	// A rounded dropdown shows the chosen choice's text.
	function syncPill( select ) {
		var pill = select.closest( '.crc-pill' );
		var label = pill ? pill.querySelector( '.crc-pill-text' ) : null;
		var option = select.options[ select.selectedIndex ];

		if ( label && option ) {
			label.textContent = option.text;
			pill.classList.toggle( 'has-value', '' !== select.value );
		}
	}

	// The place box: suggestions from the site as you type.
	function places( form, input ) {
		var list = document.getElementById( input.getAttribute( 'aria-controls' ) );
		var shown = [];
		var active = -1;
		var cache = {};
		var asked = 0;
		var typing = 0;

		if ( ! list || ! text.ajaxUrl || ! window.fetch ) {
			return;
		}

		function ask( query ) {
			var category = categoryOf( form ).value;
			var key = category + '|' + query.trim().toLowerCase();
			var url = text.ajaxUrl + ( -1 === text.ajaxUrl.indexOf( '?' ) ? '?' : '&' ) +
				'action=' + encodeURIComponent( text.action ) + '&q=' + encodeURIComponent( query.trim() ) + '&category=' + encodeURIComponent( category );

			if ( cache[ key ] ) {
				return Promise.resolve( cache[ key ] );
			}

			return window.fetch( url, {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' }
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( json ) {
				var found = json && json.success && json.data && Array.isArray( json.data.places ) ? json.data.places : [];

				cache[ key ] = found;
				return found;
			} );
		}

		function setActive( index ) {
			var items = list.querySelectorAll( '.crc-place-option' );

			active = index;

			each( items, function ( item, i ) {
				item.classList.toggle( 'is-active', i === index );
				item.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			} );

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

		function open( found ) {
			shown = found;
			list.innerHTML = '';

			if ( ! found.length ) {
				close();
				return;
			}

			found.forEach( function ( place, i ) {
				var li = document.createElement( 'li' );
				var words = document.createElement( 'span' );
				var name = document.createElement( 'span' );
				var note = document.createElement( 'span' );

				li.id = list.id + '-' + i;
				li.className = 'crc-place-option';
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );
				li.setAttribute( 'data-index', String( i ) );
				words.className = 'crc-place-option-text';
				name.className = 'crc-place-option-name';
				name.textContent = place.name;
				note.className = 'crc-place-option-note';
				note.textContent = place.note || '';
				words.appendChild( name );
				words.appendChild( note );

				if ( text.pin ) {
					li.innerHTML = text.pin;
				}

				li.appendChild( words );
				list.appendChild( li );
			} );

			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			setActive( -1 );
		}

		// Only the latest answer is shown.
		function lookUp() {
			var query = input.value;
			var mine = ++asked;

			if ( ! query.trim() ) {
				close();
				return;
			}

			ask( query ).then( function ( found ) {
				if ( mine === asked && document.activeElement === input ) {
					open( found );
				}
			}, function () {
				if ( mine === asked ) {
					close();
				}
			} );
		}

		// Anything still being looked up is forgotten, so the list stays closed.
		function choose( place ) {
			window.clearTimeout( typing );
			asked++;
			input.value = place.name;
			close();
		}

		input.addEventListener( 'input', function () {
			window.clearTimeout( typing );
			typing = window.setTimeout( lookUp, 180 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var count = shown.length;

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				if ( list.hidden ) {
					if ( input.value.trim() ) {
						event.preventDefault();
						lookUp();
					}

					return;
				}

				event.preventDefault();

				if ( count ) {
					setActive( 'ArrowDown' === event.key ? ( active + 1 ) % count : ( active <= 0 ? count - 1 : active - 1 ) );
				}
			} else if ( 'Enter' === event.key ) {
				// Enter on a suggestion picks it; otherwise it searches.
				if ( ! list.hidden && active >= 0 && shown[ active ] ) {
					event.preventDefault();
					choose( shown[ active ] );
				} else {
					close();
				}
			} else if ( 'Escape' === event.key ) {
				if ( ! list.hidden ) {
					event.preventDefault();
					close();
				}
			} else if ( 'Tab' === event.key ) {
				close();
			}
		} );

		input.addEventListener( 'blur', function () {
			window.setTimeout( function () {
				if ( document.activeElement !== input ) {
					close();
				}
			}, 0 );
		} );

		// Clicking a suggestion keeps the cursor in the box, then picks it.
		list.addEventListener( 'mousedown', function ( event ) {
			event.preventDefault();
		} );

		list.addEventListener( 'click', function ( event ) {
			var option = event.target.closest( '.crc-place-option' );

			if ( option ) {
				choose( shown[ parseInt( option.getAttribute( 'data-index' ), 10 ) ] );
			}
		} );

		list.addEventListener( 'mousemove', function ( event ) {
			var option = event.target.closest( '.crc-place-option' );
			var index = option ? parseInt( option.getAttribute( 'data-index' ), 10 ) : -1;

			if ( option && index !== active ) {
				setActive( index );
			}
		} );

		// Another category has other places.
		form.addEventListener( 'change', function ( event ) {
			if ( 'category' === event.target.name ) {
				close();
			}
		} );
	}

	// On phones the filters fold away behind the Filters bar.
	function foldable( form ) {
		var toggle = form.querySelector( '[data-crc-filters-toggle]' );

		if ( ! toggle ) {
			return;
		}

		function flip() {
			var open = ! form.classList.contains( 'is-open' );

			form.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		toggle.addEventListener( 'click', flip );
		toggle.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
				event.preventDefault();
				flip();
			}
		} );

		form.classList.add( 'is-foldable' );
	}

	function refresh( form ) {
		showGroups( form );
		each( form.querySelectorAll( '.crc-pill-select' ), syncPill );
	}

	function setUpForm( form ) {
		var input = form.querySelector( '[data-crc-place]' );

		if ( form.crcSearch ) {
			return;
		}

		form.crcSearch = true;
		refresh( form );

		form.addEventListener( 'change', function ( event ) {
			if ( 'category' === event.target.name ) {
				showGroups( form );
			}

			if ( event.target.classList.contains( 'crc-pill-select' ) ) {
				syncPill( event.target );
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			window.location.href = address( form );
		} );

		if ( input ) {
			places( form, input );
		}

		foldable( form );
	}

	// Sort by: straight away, from the first page, keeping the search.
	function setUpSort( form ) {
		var select = form.querySelector( 'select' );

		if ( form.crcSearch || ! select ) {
			return;
		}

		form.crcSearch = true;
		select.addEventListener( 'change', function () {
			window.location.href = address( form );
		} );
	}

	function setUpAll() {
		each( document.querySelectorAll( '[data-crc-search], [data-crc-filters]' ), setUpForm );
		each( document.querySelectorAll( '[data-crc-sort]' ), setUpSort );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', setUpAll );
	} else {
		setUpAll();
	}

	// Back to a page kept by the browser: its choices show again as they were left.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			each( document.querySelectorAll( '[data-crc-search], [data-crc-filters]' ), refresh );
		}
	} );

	// Added later in Elementor's editor.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', setUpAll );
			}
		} );
	}
}() );
