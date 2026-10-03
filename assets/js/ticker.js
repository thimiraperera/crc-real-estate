/**
 * Keyword ticker: the row of keywords moves on its own, slows down and
 * stops while the mouse is over it (or it has keyboard focus), and can be
 * dragged. Let go while moving and it glides on, slowing down, before it
 * carries on at its own speed. People who ask for less motion get a row
 * that only moves when they drag it.
 */
( function () {
	'use strict';

	// Seconds: how gently it stops under the mouse, starts again, and glides after a throw.
	var STOPPING = 0.45;
	var STARTING = 0.8;
	var GLIDING = 0.7;

	// The fastest a throw can be, in pixels a second.
	var THROW = 4000;

	// Only the moves of the last moment count for how fast it was thrown.
	var SAMPLE = 100;

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	function now() {
		return window.performance && window.performance.now ? window.performance.now() : Date.now();
	}

	function lessMotion() {
		return window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;
	}

	function setUp( root ) {
		var track = root.querySelector( '.crc-ticker-track' );
		var group = track ? track.querySelector( '.crc-ticker-group' ) : null;
		var motion = lessMotion();
		var base = clamp( parseFloat( root.getAttribute( 'data-speed' ) ) || 50, 5, 400 );
		var sign = 'right' === root.getAttribute( 'data-direction' ) ? 1 : -1;
		var auto = ! ( motion && motion.matches );
		var offset = 0;
		var velocity = 0;
		var width = 0;
		var hovered = false;
		var focused = false;
		var drag = null;
		var thrown = false;
		var visible = true;
		var frame = 0;
		var last = 0;
		var api = {};

		if ( ! group || root.crcTicker ) {
			return;
		}

		root.crcTicker = api;

		// The speed it is heading for: its own, or nothing while held, hovered or focused.
		function target() {
			return auto && ! hovered && ! focused && ! drag ? base * sign : 0;
		}

		// Keeps the row within one copy's width, so it loops without a seam.
		function place() {
			if ( width > 0 ) {
				offset = ( ( offset % width ) + width ) % width - width;
			}

			track.style.transform = 'translate3d(' + offset.toFixed( 2 ) + 'px, 0, 0)';
		}

		// Measures the words and adds copies until they fill the width twice over.
		function measure() {
			var copies = track.children.length - 1;
			var need;
			var copy;

			if ( root.classList.contains( 'crc-ticker-full' ) ) {
				root.style.setProperty( '--crc-ticker-vw', document.documentElement.clientWidth + 'px' );
			}

			width = group.getBoundingClientRect().width;
			need = width > 0 ? Math.ceil( root.clientWidth / width ) + 1 : 0;

			while ( copies < need ) {
				copy = group.cloneNode( true );
				copy.setAttribute( 'aria-hidden', 'true' );
				copy.classList.add( 'crc-ticker-copy' );
				track.appendChild( copy );
				copies++;
			}

			while ( copies > Math.max( need, 1 ) ) {
				track.removeChild( track.lastElementChild );
				copies--;
			}

			place();
		}

		// Moves the row on by a short time, in seconds.
		function tick( seconds ) {
			var goal = target();
			var ease;

			if ( ! drag ) {
				ease = thrown ? GLIDING : ( 0 === goal ? STOPPING : STARTING );
				velocity += ( goal - velocity ) * ( 1 - Math.exp( -seconds / ease ) );

				if ( Math.abs( goal - velocity ) < 0.5 ) {
					velocity = goal;
					thrown = false;
				}

				offset += velocity * seconds;
			}

			place();
		}

		function moving() {
			return !! drag || 0 !== velocity || 0 !== target();
		}

		function loop( time ) {
			frame = 0;

			if ( ! root.isConnected ) {
				return;
			}

			// A long gap (another tab, a slow phone) counts as a short one, so nothing jumps.
			tick( last ? Math.min( 0.05, ( time - last ) / 1000 ) : 0 );
			last = time;

			if ( visible && moving() ) {
				frame = window.requestAnimationFrame( loop );
			} else {
				last = 0;
			}
		}

		function wake() {
			if ( ! frame && visible && ! window.crcTickerManual && moving() ) {
				last = 0;
				frame = window.requestAnimationFrame( loop );
			}
		}

		// Dragging, and throwing it when let go.
		root.addEventListener( 'pointerdown', function ( event ) {
			if ( drag || ( 'mouse' === event.pointerType && 0 !== event.button ) ) {
				return;
			}

			drag = {
				id: event.pointerId,
				x: event.clientX,
				moves: [ { x: event.clientX, time: now() } ]
			};
			velocity = 0;
			thrown = false;
			root.classList.add( 'is-dragging' );

			try {
				root.setPointerCapture( event.pointerId );
			} catch ( error ) {
				// Some browsers can't capture a pointer that has already left.
			}

			// No text selection while dragging with a mouse.
			if ( 'mouse' === event.pointerType ) {
				event.preventDefault();
			}

			wake();
		} );

		root.addEventListener( 'pointermove', function ( event ) {
			var time = now();

			if ( ! drag || event.pointerId !== drag.id ) {
				return;
			}

			offset += event.clientX - drag.x;
			drag.x = event.clientX;
			drag.moves.push( { x: event.clientX, time: time } );

			while ( drag.moves.length > 2 && time - drag.moves[ 0 ].time > SAMPLE ) {
				drag.moves.shift();
			}

			place();
		} );

		function letGo( event ) {
			var time = now();
			var moves;
			var first;
			var end;
			var span;

			if ( ! drag || event.pointerId !== drag.id ) {
				return;
			}

			moves = drag.moves;
			first = moves[ 0 ];
			end = moves[ moves.length - 1 ];
			span = ( end.time - first.time ) / 1000;

			// Held still before letting go: no throw.
			velocity = span > 0 && time - end.time < SAMPLE ? clamp( ( end.x - first.x ) / span, -THROW, THROW ) : 0;
			thrown = true;
			drag = null;
			root.classList.remove( 'is-dragging' );

			try {
				root.releasePointerCapture( event.pointerId );
			} catch ( error ) {
				// Already released.
			}

			wake();
		}

		root.addEventListener( 'pointerup', letGo );
		root.addEventListener( 'pointercancel', letGo );

		// Slows down and stops under the mouse; carries on when it leaves.
		root.addEventListener( 'pointerenter', function ( event ) {
			if ( 'touch' !== event.pointerType ) {
				hovered = true;
				wake();
			}
		} );

		root.addEventListener( 'pointerleave', function ( event ) {
			if ( 'touch' !== event.pointerType ) {
				hovered = false;
				wake();
			}
		} );

		// Stops while it has keyboard focus, so it can be read at leisure.
		root.addEventListener( 'focus', function () {
			var keyboard = true;

			try {
				keyboard = root.matches( ':focus-visible' );
			} catch ( error ) {
				keyboard = true;
			}

			focused = keyboard;
			wake();
		} );

		root.addEventListener( 'blur', function () {
			focused = false;
			wake();
		} );

		if ( motion ) {
			var follow = function () {
				auto = ! motion.matches;
				wake();
			};

			if ( motion.addEventListener ) {
				motion.addEventListener( 'change', follow );
			} else if ( motion.addListener ) {
				motion.addListener( follow );
			}
		}

		// Rests while it is off the screen.
		if ( window.IntersectionObserver ) {
			new window.IntersectionObserver( function ( entries ) {
				visible = entries[ entries.length - 1 ].isIntersecting;

				if ( visible ) {
					wake();
				} else if ( frame ) {
					window.cancelAnimationFrame( frame );
					frame = 0;
					last = 0;
				}
			} ).observe( root );
		}

		if ( window.ResizeObserver ) {
			new window.ResizeObserver( measure ).observe( group );
		}

		window.addEventListener( 'resize', measure );

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( measure, function () {} );
		}

		api.tick = tick;
		api.measure = measure;
		api.state = function () {
			return {
				offset: offset,
				velocity: velocity,
				width: width,
				target: target(),
				hovered: hovered,
				focused: focused,
				dragging: !! drag,
				thrown: thrown,
				copies: track.children.length - 1,
				running: !! frame
			};
		};

		measure();
		wake();
	}

	function start() {
		var roots = document.querySelectorAll( '[data-crc-ticker]' );
		var i;

		for ( i = 0; i < roots.length; i++ ) {
			setUp( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	// Tickers added later, e.g. in Elementor's editor: looked for at most once a frame.
	if ( window.MutationObserver ) {
		var waiting = false;

		new window.MutationObserver( function () {
			if ( ! waiting ) {
				waiting = true;
				window.requestAnimationFrame( function () {
					waiting = false;
					start();
				} );
			}
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
