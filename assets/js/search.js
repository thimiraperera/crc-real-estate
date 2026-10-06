/**
 * Listing search: the search box ([crc_listing_search]), the filters
 * ([crc_listing_filters]) and the results ([crc_listing_results]).
 *
 * The place box suggests towns and districts with listings once three
 * letters are typed. The chosen category shows its own choices. The price and
 * rent have two boxes and a slider that move together; bedrooms, bathrooms
 * and furnishing have − and +. Search and Show Listings open the category's
 * archive with only the choices that were made in its address, and Sort by
 * keeps the search and starts from its first page. The results can show as a
 * grid or a list, remembered on the device. Beside the results the filters
 * stay in view while the listings scroll; on phones they fold away behind a
 * Filters bar.
 */
( function () {
	'use strict';

	var text = window.crcReSearch || {};
	var letters = parseInt( text.letters, 10 ) || 3;
	var viewKey = 'crcReListingView';
	var each = function ( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	};

	function digits( value ) {
		return String( value || '' ).replace( /\D/g, '' );
	}

	function money( amount ) {
		return amount ? Number( amount ).toLocaleString( 'en-US' ) : '';
	}

	function json( value, fallback ) {
		try {
			return JSON.parse( value );
		} catch ( error ) {
			return fallback;
		}
	}

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

			// Prices go in the address as plain numbers.
			if ( field.hasAttribute( 'data-crc-money' ) ) {
				value = digits( value );
			}

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

	// The place box: suggestions from the site once three letters are typed.
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
			} ).then( function ( answer ) {
				var found = answer && answer.success && answer.data && Array.isArray( answer.data.places ) ? answer.data.places : [];

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

		// Only the latest answer is shown, and only for three letters or more.
		function lookUp() {
			var query = input.value;
			var mine = ++asked;

			if ( query.trim().length < letters ) {
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
			typing = window.setTimeout( lookUp, 200 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var count = shown.length;

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				if ( list.hidden ) {
					if ( input.value.trim().length >= letters ) {
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

	// The price and the rent: the two boxes and the slider move together.
	function setUpRange( range ) {
		var steps = json( range.getAttribute( 'data-steps' ), [] );
		var low = range.querySelector( '.crc-range-min' );
		var high = range.querySelector( '.crc-range-max' );
		var lowBox = low ? document.getElementById( low.getAttribute( 'aria-controls' ) ) : null;
		var highBox = high ? document.getElementById( high.getAttribute( 'aria-controls' ) ) : null;
		var last = steps.length - 1;

		if ( range.crcRange || last < 1 || ! lowBox || ! highBox ) {
			return;
		}

		range.crcRange = true;

		// The step an amount is at: at or under it for the lowest, at or over it for the highest.
		function stepOf( amount, up ) {
			var at = 0;
			var i;

			if ( up ) {
				for ( i = 0; i <= last; i++ ) {
					if ( steps[ i ] >= amount ) {
						return i;
					}
				}

				return last;
			}

			for ( i = 0; i <= last; i++ ) {
				if ( steps[ i ] <= amount ) {
					at = i;
				}
			}

			return at;
		}

		function paint() {
			var from = parseInt( low.value, 10 );
			var to = parseInt( high.value, 10 );

			range.style.setProperty( '--crc-range-from', ( from / last * 100 ) + '%' );
			range.style.setProperty( '--crc-range-to', ( to / last * 100 ) + '%' );
			low.setAttribute( 'aria-valuetext', from > 0 ? money( steps[ from ] ) : ( text.noMin || 'No min' ) );
			high.setAttribute( 'aria-valuetext', to < last ? money( steps[ to ] ) : ( text.noMax || 'No max' ) );

			// Both handles at the far end: the lowest goes on top, so it can be moved back.
			low.classList.toggle( 'is-on-top', from === to && to === last );
		}

		function fromSlider( moved ) {
			var from = parseInt( low.value, 10 );
			var to = parseInt( high.value, 10 );

			// The handles never cross.
			if ( from > to ) {
				if ( moved === low ) {
					low.value = to;
					from = to;
				} else {
					high.value = from;
					to = from;
				}
			}

			lowBox.value = from > 0 ? money( steps[ from ] ) : '';
			highBox.value = to < last ? money( steps[ to ] ) : '';
			paint();
		}

		function fromBoxes() {
			var lowest = parseInt( digits( lowBox.value ), 10 ) || 0;
			var highest = parseInt( digits( highBox.value ), 10 ) || 0;
			var from = lowest ? stepOf( lowest, false ) : 0;
			var to = highest ? stepOf( highest, true ) : last;

			low.value = Math.min( from, to );
			high.value = to;
			paint();
		}

		low.addEventListener( 'input', function () {
			fromSlider( low );
		} );

		high.addEventListener( 'input', function () {
			fromSlider( high );
		} );

		[ lowBox, highBox ].forEach( function ( box ) {
			box.addEventListener( 'input', fromBoxes );
			box.addEventListener( 'blur', function () {
				box.value = money( digits( box.value ) );
			} );
		} );

		range.crcSync = fromBoxes;
		fromBoxes();
	}

	// Bedrooms, bathrooms and furnishing: a box with − and +.
	function setUpStepper( stepper ) {
		var box = stepper.querySelector( '.crc-stepper-input' );
		var field = stepper.querySelector( 'input[type="hidden"]' );
		var minus = stepper.querySelector( '.crc-stepper-minus' );
		var plus = stepper.querySelector( '.crc-stepper-plus' );
		var values = box ? json( box.getAttribute( 'data-values' ), [] ) : [];
		var texts = box ? json( box.getAttribute( 'data-texts' ), [] ) : [];
		var at = 0;

		if ( stepper.crcStepper || ! field || ! minus || ! plus || ! values.length ) {
			return;
		}

		stepper.crcStepper = true;

		function show() {
			box.value = texts[ at ];
			field.value = values[ at ];
			box.setAttribute( 'aria-valuenow', String( at ) );
			box.setAttribute( 'aria-valuetext', texts[ at ] );
			minus.setAttribute( 'aria-disabled', at <= 0 ? 'true' : 'false' );
			plus.setAttribute( 'aria-disabled', at >= values.length - 1 ? 'true' : 'false' );
		}

		function go( by ) {
			var next = Math.max( 0, Math.min( values.length - 1, at + by ) );

			if ( next !== at ) {
				at = next;
				show();
			}
		}

		function sync() {
			at = Math.max( 0, values.indexOf( field.value ) );
			show();
		}

		[ [ minus, -1 ], [ plus, 1 ] ].forEach( function ( pair ) {
			pair[ 0 ].addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
			} );
			pair[ 0 ].addEventListener( 'click', function () {
				go( pair[ 1 ] );
			} );
		} );

		box.addEventListener( 'keydown', function ( event ) {
			var by = {
				ArrowUp: 1,
				ArrowRight: 1,
				'+': 1,
				ArrowDown: -1,
				ArrowLeft: -1,
				'-': -1,
				Home: -values.length,
				End: values.length
			}[ event.key ];

			if ( by ) {
				event.preventDefault();
				go( by );
			}
		} );

		stepper.crcSync = sync;
		sync();
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

	/*
	 * Beside the results, the filters' column stays in view while the
	 * listings scroll: the column whose parent sets it side by side with
	 * another. A column on its own line never sticks, so it can't cover the
	 * listings under it.
	 */
	function stickyColumn( form ) {
		var el = form;
		var parent;
		var style;
		var side;

		while ( el && el.parentElement && el.parentElement !== document.body ) {
			parent = el.parentElement;
			style = window.getComputedStyle( parent );
			side = ( -1 !== String( style.display ).indexOf( 'flex' ) && 0 === String( style.flexDirection ).indexOf( 'row' ) ) ||
				( -1 !== String( style.display ).indexOf( 'grid' ) && String( style.gridTemplateColumns ).trim().split( /\s+/ ).length > 1 );

			if ( side && parent.children.length > 1 ) {
				return el.getBoundingClientRect().width < parent.getBoundingClientRect().width * 0.8 ? el : null;
			}

			el = parent;
		}

		return null;
	}

	function stick( form ) {
		var wide = window.matchMedia && window.matchMedia( '(min-width: 768px)' ).matches;
		var column = wide ? stickyColumn( form ) : null;

		if ( form.crcColumn && form.crcColumn !== column ) {
			form.crcColumn.classList.remove( 'crc-filters-column' );
		}

		if ( column ) {
			column.classList.add( 'crc-filters-column' );
		}

		form.crcColumn = column;
	}

	function sticky( form ) {
		var waiting = 0;

		stick( form );
		window.addEventListener( 'resize', function () {
			window.clearTimeout( waiting );
			waiting = window.setTimeout( function () {
				stick( form );
			}, 150 );
		} );
	}

	function refresh( form ) {
		showGroups( form );
		each( form.querySelectorAll( '.crc-pill-select' ), syncPill );
		each( form.querySelectorAll( '[data-crc-range], [data-crc-stepper]' ), function ( part ) {
			if ( part.crcSync ) {
				part.crcSync();
			}
		} );
	}

	function setUpForm( form ) {
		var input = form.querySelector( '[data-crc-place]' );

		if ( form.crcSearch ) {
			return;
		}

		form.crcSearch = true;
		each( form.querySelectorAll( '[data-crc-range]' ), setUpRange );
		each( form.querySelectorAll( '[data-crc-stepper]' ), setUpStepper );
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

		if ( form.hasAttribute( 'data-crc-filters' ) ) {
			foldable( form );
			sticky( form );
			form.classList.add( 'is-ready' );
		}
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

	// Grid or list: the choice is remembered on this device.
	function setUpViews( results ) {
		var buttons = results.querySelectorAll( '.crc-results-view' );
		var saved = null;

		if ( results.crcViews || ! buttons.length ) {
			return;
		}

		results.crcViews = true;

		function show( view, keep ) {
			results.classList.toggle( 'crc-results-list', 'list' === view );
			each( buttons, function ( button ) {
				var on = button.getAttribute( 'data-view' ) === view;

				button.classList.toggle( 'is-active', on );
				button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );

			if ( keep ) {
				try {
					window.localStorage.setItem( viewKey, view );
				} catch ( error ) {}
			}
		}

		try {
			saved = window.localStorage.getItem( viewKey );
		} catch ( error ) {}

		if ( 'grid' === saved || 'list' === saved ) {
			show( saved, false );
		}

		each( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				show( button.getAttribute( 'data-view' ), true );
			} );
			button.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
					event.preventDefault();
					show( button.getAttribute( 'data-view' ), true );
				}
			} );
		} );
	}

	function setUpAll() {
		each( document.querySelectorAll( '[data-crc-search], [data-crc-filters]' ), setUpForm );
		each( document.querySelectorAll( '[data-crc-sort]' ), setUpSort );
		each( document.querySelectorAll( '[data-crc-results]' ), setUpViews );
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
