/**
 * Category carousel: the arrows, the dots on phones, dragging with the mouse,
 * and the cards running on to the edge of the window. Swiping on phones and
 * touch pads is the browser's own scrolling.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;

	function setUp( root ) {
		var viewport = root.querySelector( '.crc-carousel-viewport' );
		var track = root.querySelector( '.crc-carousel-track' );
		var prev = root.querySelector( '.crc-carousel-prev' );
		var next = root.querySelector( '.crc-carousel-next' );
		var dots = root.querySelectorAll( '.crc-carousel-dot' );
		var drag = null;
		var dragged = false;
		var aim = null;
		var aimTimer = 0;
		var settle = 0;
		var frame = 0;
		var measureFrame = 0;
		var observer = null;

		if ( root.crcCarousel || ! viewport || ! track || ! track.children.length ) {
			return;
		}

		function rtl() {
			return 'rtl' === window.getComputedStyle( root ).direction;
		}

		function behavior() {
			return reduce && reduce.matches ? 'auto' : 'smooth';
		}

		// How far along the cards are, from 0 at the first card, whichever way the page reads.
		function position() {
			return Math.abs( viewport.scrollLeft );
		}

		function furthest() {
			return Math.max( 0, viewport.scrollWidth - viewport.clientWidth );
		}

		// Where each card lines up with the start of the carousel.
		function stops() {
			var cards = track.children;
			var first = cards[ 0 ];
			var end = furthest();
			var list = [];
			var i;

			for ( i = 0; i < cards.length; i++ ) {
				list.push( Math.min( end, rtl() ?
					( first.offsetLeft + first.offsetWidth ) - ( cards[ i ].offsetLeft + cards[ i ].offsetWidth ) :
					cards[ i ].offsetLeft - first.offsetLeft ) );
			}

			list.push( end );

			return list;
		}

		function place( to ) {
			to = Math.max( 0, Math.min( furthest(), to ) );
			viewport.scrollLeft = rtl() ? -to : to;
		}

		function go( to ) {
			to = Math.max( 0, Math.min( furthest(), to ) );
			aim = to;
			window.clearTimeout( aimTimer );
			aimTimer = window.setTimeout( function () {
				aim = null;
			}, 700 );

			if ( viewport.scrollTo ) {
				viewport.scrollTo( { left: rtl() ? -to : to, behavior: behavior() } );
			} else {
				place( to );
			}

			return to;
		}

		// One card along, counting from where an arrow press is already heading.
		function step( forward ) {
			var at = null !== aim ? aim : position();
			var list = stops();
			var target = forward ? furthest() : 0;
			var i;

			if ( forward ) {
				for ( i = 0; i < list.length; i++ ) {
					if ( list[ i ] > at + 2 ) {
						target = list[ i ];
						break;
					}
				}
			} else {
				for ( i = list.length - 1; i >= 0; i-- ) {
					if ( list[ i ] < at - 2 ) {
						target = list[ i ];
						break;
					}
				}
			}

			go( target );
		}

		function setOff( button, off ) {
			if ( button ) {
				button.setAttribute( 'aria-disabled', off ? 'true' : 'false' );
			}
		}

		// The card the carousel is on: the one lined up nearest to the start.
		function current() {
			var list = stops();
			var at = position();
			var best = 0;
			var i;

			for ( i = 1; i < track.children.length; i++ ) {
				if ( Math.abs( list[ i ] - at ) < Math.abs( list[ best ] - at ) ) {
					best = i;
				}
			}

			return best;
		}

		function update() {
			var at = position();
			var end = furthest();
			var on;
			var i;

			frame = 0;
			setOff( prev, at <= 1 );
			setOff( next, at >= end - 1 );
			root.classList.toggle( 'is-static', end <= 1 );

			if ( dots.length ) {
				on = current();

				for ( i = 0; i < dots.length; i++ ) {
					dots[ i ].classList.toggle( 'is-active', i === on );

					if ( i === on ) {
						dots[ i ].setAttribute( 'aria-current', 'true' );
					} else {
						dots[ i ].removeAttribute( 'aria-current' );
					}
				}
			}
		}

		function schedule() {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		}

		// The room between the carousel and the window's edges: the arrows may
		// reach into it, and on the right the cards run on into it.
		function measure() {
			var page = document.documentElement.clientWidth;
			var box;
			var before;
			var after;

			measureFrame = 0;

			if ( ! root.isConnected ) {
				stop();
				return;
			}

			box = root.getBoundingClientRect();
			before = Math.max( 0, Math.floor( rtl() ? page - box.right : box.left ) );
			after = Math.max( 0, Math.floor( rtl() ? box.left : page - box.right ) );

			root.style.setProperty( '--crc-carousel-room-start', before + 'px' );
			root.style.setProperty( '--crc-carousel-room-end', after + 'px' );

			if ( root.classList.contains( 'crc-carousel-bleed' ) ) {
				root.style.setProperty( '--crc-carousel-bleed', after + 'px' );
			}

			update();
		}

		function scheduleMeasure() {
			if ( ! measureFrame ) {
				measureFrame = window.requestAnimationFrame( measure );
			}
		}

		// Snapping stays off while a thrown carousel glides, and comes back once it rests.
		function settleAt( target ) {
			var tries = 0;

			function check() {
				settle = window.setTimeout( function () {
					if ( drag || ( Math.abs( position() - target ) > 1 && ++tries < 25 ) ) {
						check();
						return;
					}

					root.classList.remove( 'is-dragging' );
				}, 100 );
			}

			window.clearTimeout( settle );
			check();
		}

		function onMove( event ) {
			var moved;
			var now = event.timeStamp;

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
			}

			drag.samples.push( { t: now, x: event.clientX } );

			while ( drag.samples.length > 2 && now - drag.samples[ 0 ].t > 100 ) {
				drag.samples.shift();
			}

			place( drag.from - ( rtl() ? -moved : moved ) );
		}

		function onUp( event ) {
			var samples;
			var first;
			var last;
			var speed = 0;
			var at;
			var list;
			var target;
			var i;

			if ( ! drag || event.pointerId !== drag.id ) {
				return;
			}

			window.removeEventListener( 'pointermove', onMove );
			window.removeEventListener( 'pointerup', onUp );
			window.removeEventListener( 'pointercancel', onUp );
			samples = drag.samples;
			drag = null;

			if ( ! dragged ) {
				return;
			}

			first = samples[ 0 ];
			last = samples[ samples.length - 1 ];

			// Pixels a millisecond, towards the later cards.
			if ( 'pointercancel' !== event.type && first && last && last.t > first.t && event.timeStamp - last.t < 100 ) {
				speed = ( first.x - last.x ) / ( last.t - first.t ) * ( rtl() ? -1 : 1 );
			}

			// Carries on a little the way it was thrown, then lines up with the nearest card.
			at = position();
			list = stops();
			target = list[ 0 ];

			for ( i = 0; i < list.length; i++ ) {
				if ( Math.abs( list[ i ] - ( at + speed * 250 ) ) < Math.abs( target - ( at + speed * 250 ) ) ) {
					target = list[ i ];
				}
			}

			if ( speed > 0.2 && target <= at ) {
				target = furthest();

				for ( i = 0; i < list.length; i++ ) {
					if ( list[ i ] > at + 1 ) {
						target = Math.min( target, list[ i ] );
					}
				}
			} else if ( speed < -0.2 && target >= at ) {
				target = 0;

				for ( i = 0; i < list.length; i++ ) {
					if ( list[ i ] < at - 1 ) {
						target = Math.max( target, list[ i ] );
					}
				}
			}

			settleAt( go( target ) );
		}

		function onDown( event ) {
			if ( 'mouse' !== event.pointerType || 0 !== event.button || drag || furthest() <= 1 ) {
				return;
			}

			// No text selecting or picture dragging while the cards are pulled.
			event.preventDefault();
			dragged = false;
			drag = { id: event.pointerId, x: event.clientX, from: position(), samples: [] };
			window.addEventListener( 'pointermove', onMove );
			window.addEventListener( 'pointerup', onUp );
			window.addEventListener( 'pointercancel', onUp );
		}

		// A drag that ends on a button doesn't open its link.
		function onClick( event ) {
			if ( dragged ) {
				event.preventDefault();
				event.stopPropagation();
				dragged = false;
			}
		}

		// The arrows and dots aren't buttons, so Enter and Space are handled here.
		function pressed( event ) {
			if ( 'keydown' !== event.type ) {
				return true;
			}

			if ( 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key ) {
				event.preventDefault();
				return true;
			}

			return false;
		}

		function onArrow( event ) {
			if ( ! pressed( event ) || 'true' === event.currentTarget.getAttribute( 'aria-disabled' ) ) {
				return;
			}

			step( event.currentTarget === next );
		}

		function onDot( event ) {
			var index = Array.prototype.indexOf.call( dots, event.currentTarget );

			if ( ! pressed( event ) || index < 0 ) {
				return;
			}

			go( stops()[ index ] );
		}

		// Safari on iPhones and iPads only shows the pressed look with a touch listener.
		function touched() {}

		function listen( add ) {
			var method = add ? 'addEventListener' : 'removeEventListener';
			var i;

			[ prev, next ].forEach( function ( arrow ) {
				if ( arrow ) {
					arrow[ method ]( 'click', onArrow );
					arrow[ method ]( 'keydown', onArrow );
					arrow[ method ]( 'touchstart', touched, { passive: true } );
				}
			} );

			for ( i = 0; i < dots.length; i++ ) {
				dots[ i ][ method ]( 'click', onDot );
				dots[ i ][ method ]( 'keydown', onDot );
			}
		}

		function stop() {
			viewport.removeEventListener( 'scroll', schedule );
			viewport.removeEventListener( 'pointerdown', onDown );
			viewport.removeEventListener( 'click', onClick, true );
			window.removeEventListener( 'pointermove', onMove );
			window.removeEventListener( 'pointerup', onUp );
			window.removeEventListener( 'pointercancel', onUp );
			window.removeEventListener( 'resize', scheduleMeasure );
			window.removeEventListener( 'load', scheduleMeasure );
			document.removeEventListener( 'animationend', scheduleMeasure, true );
			listen( false );

			if ( observer ) {
				observer.disconnect();
			}

			window.clearTimeout( settle );
			window.clearTimeout( aimTimer );
			root.crcCarousel = null;
		}

		viewport.addEventListener( 'scroll', schedule, { passive: true } );
		viewport.addEventListener( 'pointerdown', onDown );
		viewport.addEventListener( 'click', onClick, true );
		window.addEventListener( 'resize', scheduleMeasure );
		window.addEventListener( 'load', scheduleMeasure );
		// Elementor's entrance animations move the column; measured again when they end.
		document.addEventListener( 'animationend', scheduleMeasure, true );
		listen( true );

		if ( window.ResizeObserver ) {
			observer = new window.ResizeObserver( scheduleMeasure );
			observer.observe( root );
			observer.observe( document.documentElement );
		}

		root.crcCarousel = { measure: measure, step: step, stops: stops, current: current, stop: stop };
		measure();
	}

	function setUpAll() {
		var roots = document.querySelectorAll( '[data-crc-carousel]' );
		var i;

		for ( i = 0; i < roots.length; i++ ) {
			setUp( roots[ i ] );
		}
	}

	setUpAll();

	// Carousels added later, e.g. in Elementor's editor: looked for at most once a frame.
	if ( window.MutationObserver && document.body ) {
		( function () {
			var waiting = false;

			new window.MutationObserver( function () {
				if ( waiting ) {
					return;
				}

				waiting = true;
				window.requestAnimationFrame( function () {
					waiting = false;
					setUpAll();
				} );
			} ).observe( document.body, { childList: true, subtree: true } );
		}() );
	}
}() );
