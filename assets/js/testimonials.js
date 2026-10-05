/**
 * Testimonials: a carousel that goes round and round. Each visit shows a new
 * random choice of the testimonials on the page, even when the page comes
 * from a cache. It moves on to the next one by itself, waits while the mouse
 * or the keyboard is on it, and can be dragged or swiped; a throw glides on
 * and settles on a card. The arrow keys move it too.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;
	var GLIDE = 500;

	function still() {
		return !! ( reduce && reduce.matches );
	}

	function shuffle( list ) {
		var i;
		var j;
		var swap;

		for ( i = list.length - 1; i > 0; i-- ) {
			j = Math.floor( Math.random() * ( i + 1 ) );
			swap = list[ i ];
			list[ i ] = list[ j ];
			list[ j ] = swap;
		}

		return list;
	}

	function ease( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function setUp( root ) {
		var viewport = root.querySelector( '.crc-testimonials-viewport' );
		var track = root.querySelector( '.crc-testimonials-track' );
		var count = Math.max( 1, parseInt( root.getAttribute( 'data-count' ), 10 ) || 10 );
		var delay = Math.max( 0, parseFloat( root.getAttribute( 'data-delay' ) ) || 0 );
		var label = root.getAttribute( 'data-label' ) || '%1$s of %2$s';
		var cards;
		var total;
		var looping = false;
		var copiesEach = 0;
		var step = 0;
		var size = 0;
		var pos = 0;
		var glide = null;
		var frame = 0;
		var timer = 0;
		var measureFrame = 0;
		var drag = null;
		var dragged = false;
		var hovered = false;
		var focused = false;
		var visible = true;
		var resizer = null;
		var seer = null;

		if ( root.crcTestimonials || ! viewport || ! track ) {
			return;
		}

		cards = Array.prototype.slice.call( track.querySelectorAll( '.crc-testimonial' ) );

		if ( ! cards.length ) {
			return;
		}

		// A new random choice on every visit.
		cards = shuffle( cards );
		cards.slice( count ).forEach( function ( card ) {
			card.parentNode.removeChild( card );
		} );
		cards = cards.slice( 0, count );
		total = cards.length;
		cards.forEach( function ( card, i ) {
			card.hidden = false;
			card.setAttribute( 'aria-label', label.replace( '%1$s', String( i + 1 ) ).replace( '%2$s', String( total ) ) );
			track.appendChild( card );
		} );

		function perView() {
			var value = parseFloat( window.getComputedStyle( root ).getPropertyValue( '--crc-testimonials-per-view' ) );

			return value > 0 ? value : 3;
		}

		function copy( card ) {
			var twin = card.cloneNode( true );

			twin.classList.add( 'crc-testimonial-copy' );
			twin.setAttribute( 'aria-hidden', 'true' );
			twin.setAttribute( 'inert', '' );
			twin.removeAttribute( 'aria-label' );
			twin.removeAttribute( 'role' );
			twin.removeAttribute( 'aria-roledescription' );

			return twin;
		}

		// Copies of the whole set before and after it, so it can go round without an end.
		function setCopies( each ) {
			var old = track.querySelectorAll( '.crc-testimonial-copy' );
			var before = document.createDocumentFragment();
			var after = document.createDocumentFragment();
			var i;

			for ( i = 0; i < old.length; i++ ) {
				old[ i ].parentNode.removeChild( old[ i ] );
			}

			for ( i = 0; i < each; i++ ) {
				cards.forEach( function ( card ) {
					before.appendChild( copy( card ) );
					after.appendChild( copy( card ) );
				} );
			}

			track.insertBefore( before, track.firstChild );
			track.appendChild( after );
			copiesEach = each;
		}

		function wrap( value ) {
			return size ? ( ( value % size ) + size ) % size : 0;
		}

		function render() {
			track.style.transform = looping ? 'translate3d(' + ( -( copiesEach * size + pos ) ) + 'px, 0, 0)' : '';
		}

		function clearTimer() {
			window.clearTimeout( timer );
			timer = 0;
		}

		// Moves on by itself, unless someone is on it or it can't be seen.
		function schedule() {
			clearTimer();

			if ( ! looping || ! delay || still() || hovered || focused || drag || glide || ! visible || document.hidden ) {
				return;
			}

			timer = window.setTimeout( function () {
				timer = 0;
				moveTo( Math.round( pos / step ) + 1 );
			}, delay * 1000 );
		}

		function stopGlide() {
			if ( frame ) {
				window.cancelAnimationFrame( frame );
				frame = 0;
			}

			glide = null;
		}

		// Glides to a card; the glide is shifted a whole set when needed, which looks the same.
		function moveTo( index ) {
			var to = index * step;
			var shift = to - wrap( to );
			var start = null;

			stopGlide();
			clearTimer();

			if ( ! looping || ! step ) {
				return;
			}

			pos -= shift;
			to -= shift;

			if ( still() ) {
				pos = wrap( to );
				render();
				schedule();
				return;
			}

			glide = { from: pos, to: to };

			function tick( time ) {
				var t;

				if ( null === start ) {
					start = time;
				}

				t = Math.min( 1, ( time - start ) / GLIDE );
				pos = glide.from + ( glide.to - glide.from ) * ease( t );
				render();

				if ( t < 1 ) {
					frame = window.requestAnimationFrame( tick );
					return;
				}

				frame = 0;
				glide = null;
				pos = wrap( pos );
				render();
				schedule();
			}

			frame = window.requestAnimationFrame( tick );
		}

		function measure() {
			var per;
			var gap;
			var index;
			var each;

			measureFrame = 0;

			if ( ! root.isConnected ) {
				stop();
				return;
			}

			per = perView();
			each = total > per ? Math.max( 1, Math.ceil( ( 2 * per ) / total ) ) : 0;

			if ( ( each > 0 ) !== looping || each !== copiesEach ) {
				stopGlide();
				looping = each > 0;
				setCopies( each );
			}

			// Everything fits: it stays still.
			root.classList.toggle( 'is-static', ! looping );

			index = step ? Math.round( pos / step ) : 0;
			gap = parseFloat( window.getComputedStyle( track ).columnGap ) || 0;
			step = cards[ 0 ].offsetWidth + gap;
			size = total * step;
			pos = looping ? wrap( index * step ) : 0;
			render();
			schedule();
		}

		function scheduleMeasure() {
			if ( ! measureFrame ) {
				measureFrame = window.requestAnimationFrame( measure );
			}
		}

		function onDown( event ) {
			if ( ! looping || drag || ( 'mouse' === event.pointerType && 0 !== event.button ) ) {
				return;
			}

			// No text selecting while the cards are pulled with the mouse.
			if ( 'mouse' === event.pointerType ) {
				event.preventDefault();
			}

			stopGlide();
			clearTimer();
			pos = wrap( pos );
			dragged = false;
			drag = { id: event.pointerId, x: event.clientX, from: pos, samples: [ { t: event.timeStamp, x: event.clientX } ] };
		}

		function onMove( event ) {
			var moved;

			if ( ! drag || event.pointerId !== drag.id ) {
				return;
			}

			moved = event.clientX - drag.x;

			if ( ! dragged ) {
				if ( Math.abs( moved ) < 5 ) {
					return;
				}

				dragged = true;
				root.classList.add( 'is-dragging' );

				try {
					viewport.setPointerCapture( event.pointerId );
				} catch ( error ) {
					// The pointer is already gone.
				}
			}

			drag.samples.push( { t: event.timeStamp, x: event.clientX } );

			while ( drag.samples.length > 2 && event.timeStamp - drag.samples[ 0 ].t > 100 ) {
				drag.samples.shift();
			}

			pos = drag.from - moved;

			// Round and round: stay on the middle set, moving the drag's start with it.
			if ( pos < 0 ) {
				pos += size;
				drag.from += size;
			} else if ( pos >= size ) {
				pos -= size;
				drag.from -= size;
			}

			render();
		}

		function onUp( event ) {
			var samples;
			var first;
			var last;
			var speed = 0;
			var here;
			var target;
			var per;

			if ( ! drag || event.pointerId !== drag.id ) {
				return;
			}

			samples = drag.samples;
			drag = null;
			root.classList.remove( 'is-dragging' );

			if ( ! dragged ) {
				schedule();
				return;
			}

			first = samples[ 0 ];
			last = samples[ samples.length - 1 ];

			// Pixels a millisecond, towards the later cards.
			if ( 'pointercancel' !== event.type && last.t > first.t && event.timeStamp - last.t < 100 ) {
				speed = ( first.x - last.x ) / ( last.t - first.t );
			}

			// A throw glides on, up to a screenful of cards; a flick moves at least one.
			per = Math.max( 1, Math.round( perView() ) );
			here = pos / step;
			target = Math.round( ( pos + speed * 250 ) / step );
			target = Math.max( Math.floor( here ) - per, Math.min( Math.ceil( here ) + per, target ) );

			if ( speed > 0.3 && target <= here ) {
				target = Math.floor( here ) + 1;
			} else if ( speed < -0.3 && target >= here ) {
				target = Math.ceil( here ) - 1;
			}

			moveTo( target );
		}

		// A drag doesn't count as a click on what is under it.
		function onClick( event ) {
			if ( dragged ) {
				event.preventDefault();
				event.stopPropagation();
				dragged = false;
			}
		}

		function onEnter( event ) {
			if ( 'mouse' === event.pointerType ) {
				hovered = true;
				clearTimer();
			}
		}

		function onLeave( event ) {
			if ( 'mouse' === event.pointerType ) {
				hovered = false;
				schedule();
			}
		}

		// Keyboard focus only: a click with the mouse focuses it too, and shouldn't stop it for good.
		function keyboardFocus() {
			var element = document.activeElement;

			if ( ! element || ! root.contains( element ) ) {
				return false;
			}

			try {
				return element.matches( ':focus-visible' );
			} catch ( error ) {
				return true;
			}
		}

		function onFocusIn() {
			focused = keyboardFocus();
			schedule();
		}

		function onFocusOut() {
			window.setTimeout( function () {
				focused = keyboardFocus();
				schedule();
			}, 0 );
		}

		function onKey( event ) {
			if ( ! looping || ( 'ArrowLeft' !== event.key && 'ArrowRight' !== event.key ) ) {
				return;
			}

			event.preventDefault();
			moveTo( Math.round( ( glide ? glide.to : pos ) / step ) + ( 'ArrowRight' === event.key ? 1 : -1 ) );
		}

		function onVisibility() {
			schedule();
		}

		function stop() {
			stopGlide();
			clearTimer();
			viewport.removeEventListener( 'pointerdown', onDown );
			viewport.removeEventListener( 'pointermove', onMove );
			viewport.removeEventListener( 'pointerup', onUp );
			viewport.removeEventListener( 'pointercancel', onUp );
			viewport.removeEventListener( 'pointerenter', onEnter );
			viewport.removeEventListener( 'pointerleave', onLeave );
			viewport.removeEventListener( 'click', onClick, true );
			root.removeEventListener( 'focusin', onFocusIn );
			root.removeEventListener( 'focusout', onFocusOut );
			root.removeEventListener( 'keydown', onKey );
			document.removeEventListener( 'visibilitychange', onVisibility );
			window.removeEventListener( 'resize', scheduleMeasure );

			if ( resizer ) {
				resizer.disconnect();
			}

			if ( seer ) {
				seer.disconnect();
			}

			root.crcTestimonials = null;
		}

		viewport.addEventListener( 'pointerdown', onDown );
		viewport.addEventListener( 'pointermove', onMove );
		viewport.addEventListener( 'pointerup', onUp );
		viewport.addEventListener( 'pointercancel', onUp );
		viewport.addEventListener( 'pointerenter', onEnter );
		viewport.addEventListener( 'pointerleave', onLeave );
		viewport.addEventListener( 'click', onClick, true );
		root.addEventListener( 'focusin', onFocusIn );
		root.addEventListener( 'focusout', onFocusOut );
		root.addEventListener( 'keydown', onKey );
		document.addEventListener( 'visibilitychange', onVisibility );
		window.addEventListener( 'resize', scheduleMeasure );

		if ( window.ResizeObserver ) {
			resizer = new window.ResizeObserver( scheduleMeasure );
			resizer.observe( root );
		}

		// Off screen it waits, so nobody misses a testimonial.
		if ( window.IntersectionObserver ) {
			seer = new window.IntersectionObserver( function ( entries ) {
				visible = entries[ entries.length - 1 ].isIntersecting;
				schedule();
			} );
			seer.observe( root );
		}

		root.crcTestimonials = {
			measure: measure,
			moveTo: moveTo,
			stop: stop,
			state: function () {
				return { pos: pos, step: step, size: size, total: total, looping: looping, copies: copiesEach, waiting: !! timer, gliding: !! glide };
			}
		};

		measure();
	}

	function setUpAll() {
		var roots = document.querySelectorAll( '[data-crc-testimonials]' );
		var i;

		for ( i = 0; i < roots.length; i++ ) {
			setUp( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', setUpAll );
	} else {
		setUpAll();
	}

	// Carousels added later in Elementor's editor.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', setUpAll );
			}
		} );
	}
}() );
