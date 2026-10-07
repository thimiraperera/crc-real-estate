/**
 * Listing search: the search box ([crc_listing_search]), the filters
 * ([crc_listing_filters]) and the results ([crc_listing_results]).
 *
 * The place box suggests towns and districts with listings once three
 * letters are typed. The chosen category shows its own choices: in the search
 * box, rounded boxes with a list to choose from, that can be typed in too
 * ("25m", "25 perches", "2-4"); on phones the categories are a list too. In
 * the filters, the price and
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

	// "%s", or "%1$s" and "%2$s", filled in with words.
	function format( pattern, first, second ) {
		return String( pattern || '%s' ).replace( '%1$s', function () {
			return first;
		} ).replace( '%2$s', function () {
			return second;
		} ).replace( '%s', function () {
			return first;
		} );
	}

	// A rounded button shows what is chosen, or what it is when nothing is.
	function setPill( pill, words ) {
		var label = pill.querySelector( '.crc-pill-text' );
		var empty = label ? label.getAttribute( 'data-empty' ) || '' : '';

		if ( label ) {
			label.textContent = words || empty;
		}

		pill.classList.toggle( 'has-value', '' !== words );
		pill.setAttribute( 'aria-label', words ? empty + ': ' + words : empty );
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

	// An amount as typed: "25000000", "25,000,000", "25m", "2.5 million", "45 lakhs" or "500k".
	function typedAmount( typed ) {
		var match = String( typed || '' ).toLowerCase().replace( /,/g, '' ).match( /(\d+(?:\.\d+)?)\s*(k|thousand|l|lakh|lakhs|lac|lacs|m|mn|million|millions|b|bn|billion)?/ );
		var times = { k: 1e3, thousand: 1e3, l: 1e5, lakh: 1e5, lakhs: 1e5, lac: 1e5, lacs: 1e5, m: 1e6, mn: 1e6, million: 1e6, millions: 1e6, b: 1e9, bn: 1e9, billion: 1e9 };

		return match ? Math.round( parseFloat( match[ 1 ] ) * ( match[ 2 ] ? times[ match[ 2 ] ] : 1 ) ) : 0;
	}

	// A land size as typed, in perches: "25", "25 perches", "2 acres" or "1.5 ha".
	function typedSize( typed ) {
		var match = String( typed || '' ).toLowerCase().replace( /,/g, '' ).match( /(\d+(?:\.\d+)?)\s*(a|ac|acre|acres|h|ha|hectare|hectares)?/ );
		var number;

		if ( ! match ) {
			return 0;
		}

		number = parseFloat( match[ 1 ] ) * ( ! match[ 2 ] ? 1 : ( 'a' === match[ 2 ].charAt( 0 ) ? 160 : 395.37 ) );

		return Math.round( number * 100 ) / 100;
	}

	/*
	 * The search box's boxes to choose from or type in: land size, bedrooms,
	 * the highest price per perch, price or rent, and the property type. A
	 * click lets people type. A number is offered as a choice of its own
	 * ("Up to Rs. 25,000,000", "From 25 perches", "2 – 4 bedrooms"), and
	 * other words narrow the list down. What is chosen shows in the box, and
	 * goes in its fields: one, or the least and the most ("least-most").
	 */
	function setUpCombo( box, form ) {
		var input = box.querySelector( '.crc-combo-input' );
		var pill = box.querySelector( '.crc-combo-pill' );
		var list = box.querySelector( '.crc-dropdown-list' );
		var kind = box.getAttribute( 'data-kind' ) || 'text';
		var texts = json( box.getAttribute( 'data-texts' ), {} ) || {};
		var fields = json( box.getAttribute( 'data-fields' ), [] ).map( function ( id ) {
			return document.getElementById( id );
		} ).filter( Boolean );
		var label = input ? input.getAttribute( 'placeholder' ) || '' : '';
		var hint = input ? input.getAttribute( 'data-hint' ) || label : '';
		var presets = [];
		var shown = [];
		var active = -1;
		var edited = false;
		var chosen;

		if ( box.crcCombo || ! input || ! pill || ! list || ! fields.length ) {
			return;
		}

		box.crcCombo = true;

		each( list.querySelectorAll( '[role="option"]' ), function ( option ) {
			presets.push( {
				value: option.getAttribute( 'data-value' ) || '',
				label: option.textContent.trim()
			} );
		} );

		function number( value ) {
			return Number( value ).toLocaleString( 'en-US' );
		}

		function sizeWords( perches ) {
			if ( perches >= 160 && 0 === perches % 160 ) {
				return format( 160 === perches ? texts.acre : texts.acres, number( perches / 160 ) );
			}

			return format( 1 === perches ? texts.one : texts.many, number( perches ) );
		}

		function roomWords( rooms ) {
			return format( 1 === rooms ? texts.one : texts.many, number( rooms ) );
		}

		// A choice in words: its own in the list, or written the way the site writes it.
		function words( value ) {
			var parts;
			var least;
			var most;
			var i;

			if ( '' === value ) {
				return '';
			}

			for ( i = 0; i < presets.length; i++ ) {
				if ( presets[ i ].value === value ) {
					return presets[ i ].label;
				}
			}

			if ( 'money' === kind ) {
				return format( texts.upTo, ( texts.currency ? texts.currency + ' ' : '' ) + number( value ) );
			}

			if ( 'size' !== kind && 'beds' !== kind ) {
				return value;
			}

			parts = value.split( '-' );
			least = parts[ 0 ] ? parseFloat( parts[ 0 ] ) : 0;
			most = parts[ 1 ] ? parseFloat( parts[ 1 ] ) : 0;

			if ( 'size' === kind ) {
				if ( least && most ) {
					return format( texts.between, sizeWords( least ), sizeWords( most ) );
				}

				return most ? format( texts.upTo, sizeWords( most ) ) : format( texts.from, sizeWords( least ) );
			}

			if ( least && most ) {
				return least === most ? roomWords( least ) : format( texts.between, number( least ), number( most ) );
			}

			return most ? format( texts.upTo, roomWords( most ) ) : format( texts.from, number( least ) );
		}

		function choice( value ) {
			return { value: value, label: words( value ) };
		}

		// What a number typed offers; null for words, which narrow the list down instead.
		function typedChoices( typed ) {
			var text = String( typed || '' ).toLowerCase().replace( /,/g, '' ).trim();
			var pair;
			var amount;

			if ( ! /\d/.test( text ) || 'text' === kind ) {
				return null;
			}

			if ( 'money' === kind ) {
				amount = typedAmount( text );

				return amount > 0 ? [ choice( String( amount ) ) ] : [];
			}

			if ( 'size' === kind ) {
				amount = typedSize( text );

				return amount > 0 ? [ choice( '-' + amount ), choice( amount + '-' ) ] : [];
			}

			// Bedrooms: "2-4" or "2 to 4" for the fewest and the most, or one number.
			pair = text.match( /(\d+)\s*(?:-|–|to)\s*(\d+)/ );

			if ( pair ) {
				amount = [ Math.min( 10, parseInt( pair[ 1 ], 10 ) ), Math.min( 10, parseInt( pair[ 2 ], 10 ) ) ].sort( function ( a, b ) {
					return a - b;
				} );

				if ( amount[ 1 ] <= 0 ) {
					return [];
				}

				return [ choice( ( amount[ 0 ] > 0 ? amount[ 0 ] : '' ) + '-' + amount[ 1 ] ) ];
			}

			amount = Math.min( 10, parseInt( text.match( /\d+/ )[ 0 ], 10 ) );

			return amount > 0 ? [ choice( amount + '-' ), choice( amount + '-' + amount ), choice( '-' + amount ) ] : [];
		}

		function narrowed( typed ) {
			var text = String( typed || '' ).toLowerCase().trim();

			return text ? presets.filter( function ( item ) {
				return -1 !== item.label.toLowerCase().indexOf( text );
			} ) : presets.slice();
		}

		function current() {
			if ( fields.length > 1 ) {
				return fields[ 0 ].value || fields[ 1 ].value ? fields[ 0 ].value + '-' + fields[ 1 ].value : '';
			}

			return fields[ 0 ].value;
		}

		function setActive( index ) {
			var items = list.querySelectorAll( '[role="option"]' );

			active = index;

			each( items, function ( item, i ) {
				item.classList.toggle( 'is-active', i === index );
			} );

			if ( index >= 0 && items[ index ] ) {
				input.setAttribute( 'aria-activedescendant', items[ index ].id );
				items[ index ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function render( items ) {
			var empty;

			shown = items;
			list.innerHTML = '';

			if ( ! items.length ) {
				empty = document.createElement( 'li' );
				empty.className = 'crc-dropdown-empty';
				empty.textContent = texts.none || '';
				list.appendChild( empty );
			}

			items.forEach( function ( item, i ) {
				var li = document.createElement( 'li' );
				var span = document.createElement( 'span' );
				var on = item.value === chosen.value;

				li.id = list.id + '-' + i;
				li.className = 'crc-dropdown-option' + ( on ? ' is-selected' : '' );
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				li.setAttribute( 'data-index', String( i ) );
				span.className = 'crc-dropdown-option-text';
				span.textContent = item.label;
				li.appendChild( span );

				if ( text.check ) {
					li.insertAdjacentHTML( 'beforeend', text.check );
				}

				list.appendChild( li );
			} );
		}

		function chosenIndex() {
			var i;

			for ( i = 0; i < shown.length; i++ ) {
				if ( shown[ i ].value === chosen.value ) {
					return i;
				}
			}

			return -1;
		}

		function open() {
			if ( ! list.hidden ) {
				return;
			}

			if ( form.crcPopups ) {
				form.crcPopups.close( false );
			}

			list.hidden = false;
			pill.classList.add( 'is-open' );
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function close() {
			list.hidden = true;
			pill.classList.remove( 'is-open' );
			input.setAttribute( 'aria-expanded', 'false' );
			setActive( -1 );
		}

		function showAll() {
			render( presets );
			open();
			setActive( chosenIndex() );
		}

		function choose( item ) {
			var parts = fields.length > 1 ? item.value.split( '-' ) : [ item.value ];

			fields.forEach( function ( field, i ) {
				field.value = parts[ i ] || '';
			} );

			chosen = { value: item.value, label: item.value ? item.label : '' };
			edited = false;
			input.value = chosen.label;
			pill.classList.toggle( 'has-value', '' !== chosen.value );
			close();

			// Chosen with the keys: typing again starts afresh.
			if ( document.activeElement === input ) {
				try {
					input.setSelectionRange( 0, input.value.length );
				} catch ( error ) {}
			}
		}

		// Leaving the box: what was typed is used if it can be, or the box shows what is chosen again.
		function commit() {
			var typed = input.value.trim();
			var items;

			// Clicked in, but nothing typed: what is chosen stays.
			if ( ! edited ) {
				input.value = chosen.label;
				return;
			}

			edited = false;

			if ( typed === chosen.label ) {
				input.value = chosen.label;
				return;
			}

			if ( '' === typed ) {
				choose( { value: '', label: '' } );
				return;
			}

			items = typedChoices( typed );

			if ( items && items.length ) {
				choose( items[ 0 ] );
				return;
			}

			items = narrowed( typed );

			if ( 1 === items.length ) {
				choose( items[ 0 ] );
				return;
			}

			input.value = chosen.label;
		}

		function sync() {
			var value = current();

			chosen = { value: value, label: words( value ) };
			input.value = chosen.label;
			pill.classList.toggle( 'has-value', '' !== value );
		}

		// A click empties the box for typing: the hint shows with the blinking
		// cursor, and the list shows every choice, what is chosen ticked.
		input.addEventListener( 'focus', function () {
			edited = false;
			input.value = '';
			input.placeholder = hint;
			showAll();
		} );

		input.addEventListener( 'input', function () {
			var items = typedChoices( input.value );

			edited = true;

			if ( null === items ) {
				items = narrowed( input.value );
			}

			render( items );
			open();
			setActive( items.length ? 0 : -1 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var count = shown.length;

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();

				if ( list.hidden ) {
					showAll();
				} else if ( count ) {
					setActive( 'ArrowDown' === event.key ? ( active + 1 ) % count : ( active <= 0 ? count - 1 : active - 1 ) );
				}
			} else if ( 'Enter' === event.key ) {
				// Enter picks from the open list; with the list closed, it searches.
				if ( ! list.hidden ) {
					event.preventDefault();

					if ( shown[ active ] ) {
						choose( shown[ active ] );
					} else {
						commit();
						close();
					}
				} else {
					commit();
				}
			} else if ( 'Escape' === event.key && ! list.hidden ) {
				event.preventDefault();
				event.stopPropagation();
				edited = false;
				input.value = '';
				close();
			}
		} );

		input.addEventListener( 'blur', function () {
			commit();
			input.placeholder = label;
			close();
		} );

		// A click anywhere on the box lets people type; on the arrow, while typing, it opens or closes the list.
		pill.addEventListener( 'mousedown', function ( event ) {
			if ( event.target === input ) {
				if ( document.activeElement === input && list.hidden ) {
					showAll();
				}

				return;
			}

			event.preventDefault();

			if ( document.activeElement !== input ) {
				input.focus();
			} else if ( list.hidden ) {
				showAll();
			} else {
				close();
			}
		} );

		// Clicking a choice keeps the cursor in the box, then picks it.
		list.addEventListener( 'mousedown', function ( event ) {
			event.preventDefault();
		} );

		// A choice clicked or tapped is set and the box is left, which also closes a phone's keyboard.
		list.addEventListener( 'click', function ( event ) {
			var option = event.target.closest( '[role="option"]' );

			if ( option && shown[ parseInt( option.getAttribute( 'data-index' ), 10 ) ] ) {
				choose( shown[ parseInt( option.getAttribute( 'data-index' ), 10 ) ] );
				input.blur();
			}
		} );

		list.addEventListener( 'mousemove', function ( event ) {
			var option = event.target.closest( '[role="option"]' );
			var index = option ? parseInt( option.getAttribute( 'data-index' ), 10 ) : -1;

			if ( option && index !== active ) {
				setActive( index );
			}
		} );

		box.crcSync = sync;
		chosen = { value: input.getAttribute( 'data-value' ) || '', label: input.value };
		shown = presets.slice();
		sync();
	}

	// A list to choose from, under its button: the property type, or the category on phones.
	function setUpDropdown( box, form ) {
		var toggle = box.querySelector( '[data-crc-toggle]' );
		var list = box.querySelector( '[data-crc-list]' );
		var field = box.querySelector( 'input[type="hidden"]' );
		var radios = box.getAttribute( 'data-radios' );
		var options = list ? list.querySelectorAll( '[role="option"]' ) : [];
		var active = -1;

		if ( box.crcDropdown || ! toggle || ! list || ! options.length || ( ! field && ! radios ) ) {
			return;
		}

		box.crcDropdown = true;

		function value() {
			var checked;

			if ( field ) {
				return field.value;
			}

			checked = form.querySelector( 'input[name="' + radios + '"]:checked' );

			return checked ? checked.value : '';
		}

		function setActive( index ) {
			active = index;

			each( options, function ( option, i ) {
				option.classList.toggle( 'is-active', i === index );
			} );

			if ( index >= 0 && options[ index ] ) {
				list.setAttribute( 'aria-activedescendant', options[ index ].id );
				options[ index ].scrollIntoView( { block: 'nearest' } );
			} else {
				list.removeAttribute( 'aria-activedescendant' );
			}
		}

		function sync() {
			var now = value();
			var text = toggle.querySelector( '[data-crc-dropdown-text]' );
			var chosen = null;
			var words;

			each( options, function ( option ) {
				var on = option.getAttribute( 'data-value' ) === now;

				option.classList.toggle( 'is-selected', on );
				option.setAttribute( 'aria-selected', on ? 'true' : 'false' );

				if ( on ) {
					chosen = option;
				}
			} );

			words = chosen ? chosen.textContent.trim() : '';

			if ( text ) {
				text.textContent = words;
			} else {
				setPill( toggle, '' !== now ? words : '' );
			}
		}

		function choose( option ) {
			var picked = option.getAttribute( 'data-value' ) || '';
			var radio = null;

			if ( form.crcPopups ) {
				form.crcPopups.close( true );
			}

			if ( field ) {
				field.value = picked;
			} else {
				each( form.querySelectorAll( 'input[name="' + radios + '"]' ), function ( input ) {
					if ( input.value === picked ) {
						radio = input;
					}
				} );

				if ( radio && ! radio.checked ) {
					radio.checked = true;
					radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			}

			sync();
		}

		// Opened: the list takes the keys, starting at what is chosen.
		toggle.crcOpened = function () {
			var at = 0;

			each( options, function ( option, i ) {
				if ( option.classList.contains( 'is-selected' ) ) {
					at = i;
				}
			} );

			list.focus( { preventScroll: true } );
			setActive( at );
		};

		list.addEventListener( 'keydown', function ( event ) {
			var count = options.length;

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActive( 'ArrowDown' === event.key ? Math.min( count - 1, active + 1 ) : Math.max( 0, active - 1 ) );
			} else if ( 'Home' === event.key || 'End' === event.key ) {
				event.preventDefault();
				setActive( 'Home' === event.key ? 0 : count - 1 );
			} else if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
				event.preventDefault();

				if ( options[ active ] ) {
					choose( options[ active ] );
				}
			} else if ( 'Tab' === event.key && form.crcPopups ) {
				form.crcPopups.close( false );
			}
		} );

		list.addEventListener( 'click', function ( event ) {
			var option = event.target.closest( '[role="option"]' );

			if ( option ) {
				choose( option );
			}
		} );

		list.addEventListener( 'mousemove', function ( event ) {
			var option = event.target.closest( '[role="option"]' );
			var index = option ? Array.prototype.indexOf.call( options, option ) : -1;

			if ( option && index !== active ) {
				setActive( index );
			}
		} );

		box.crcSync = sync;
		sync();
	}

	/*
	 * The category list on phones opens from its button. Escape, or a click
	 * or tap anywhere else, closes it.
	 */
	function setUpPopups( form ) {
		var toggles = form.querySelectorAll( '[data-crc-toggle]' );
		var current = null;

		if ( form.crcPopups || ! toggles.length ) {
			return;
		}

		function popupOf( toggle ) {
			return document.getElementById( toggle.getAttribute( 'aria-controls' ) || '' );
		}

		function show( toggle, on ) {
			var popup = popupOf( toggle );

			toggle.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			toggle.classList.toggle( 'is-open', on );

			if ( popup ) {
				popup.hidden = ! on;
			}
		}

		function close( back ) {
			var toggle = current;

			if ( ! toggle ) {
				return;
			}

			current = null;
			show( toggle, false );

			if ( back ) {
				toggle.focus( { preventScroll: true } );
			}
		}

		function open( toggle ) {
			close( false );
			current = toggle;
			show( toggle, true );

			if ( toggle.crcOpened ) {
				toggle.crcOpened();
			}
		}

		each( toggles, function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				if ( current === toggle ) {
					close( false );
				} else {
					open( toggle );
				}
			} );

			toggle.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
					event.preventDefault();
					toggle.click();
				} else if ( 'ArrowDown' === event.key && toggle.hasAttribute( 'aria-haspopup' ) ) {
					event.preventDefault();
					open( toggle );
				}
			} );
		} );

		form.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && current ) {
				event.preventDefault();
				close( true );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			var popup = current ? popupOf( current ) : null;

			if ( current && ! current.contains( event.target ) && ! ( popup && popup.contains( event.target ) ) ) {
				close( false );
			}
		} );

		form.crcPopups = { close: close };
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
		each( form.querySelectorAll( '[data-crc-range], [data-crc-stepper], [data-crc-combo], [data-crc-dropdown]' ), function ( part ) {
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
		each( form.querySelectorAll( '[data-crc-combo]' ), function ( box ) {
			setUpCombo( box, form );
		} );
		each( form.querySelectorAll( '[data-crc-dropdown]:not([data-crc-combo])' ), function ( box ) {
			setUpDropdown( box, form );
		} );
		setUpPopups( form );
		refresh( form );

		// Another category: what was open closes, and the category list shows the new one.
		form.addEventListener( 'change', function ( event ) {
			if ( 'category' === event.target.name ) {
				showGroups( form );

				if ( form.crcPopups ) {
					form.crcPopups.close( false );
				}

				each( form.querySelectorAll( '[data-crc-dropdown]:not([data-crc-combo])' ), function ( box ) {
					if ( box.crcSync ) {
						box.crcSync();
					}
				} );
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
