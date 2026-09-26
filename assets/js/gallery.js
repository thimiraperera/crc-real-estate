/**
 * Full-screen photo viewer for listing galleries.
 */
( function () {
	'use strict';

	var text = window.crcReGallery || {};
	var icons = {
		close: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
		prev: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		next: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
	};
	var box, img, counter, closeBtn, prevBtn, nextBtn;
	var items = [];
	var current = 0;
	var returnFocus = null;
	var touchStart = null;

	function label( key, fallback ) {
		return text[ key ] || fallback;
	}

	function button( className, key, fallback ) {
		var el = document.createElement( 'button' );
		el.type = 'button';
		el.className = 'crc-lightbox-btn ' + className;
		el.setAttribute( 'aria-label', label( key, fallback ) );
		el.innerHTML = icons[ key ];
		return el;
	}

	function build() {
		var stage;

		if ( box ) {
			return;
		}

		box = document.createElement( 'div' );
		box.className = 'crc-lightbox';
		box.hidden = true;
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', label( 'dialog', 'Photo viewer' ) );

		closeBtn = button( 'crc-lightbox-close', 'close', 'Close' );
		prevBtn = button( 'crc-lightbox-prev', 'prev', 'Previous photo' );
		nextBtn = button( 'crc-lightbox-next', 'next', 'Next photo' );

		stage = document.createElement( 'figure' );
		stage.className = 'crc-lightbox-stage';
		img = document.createElement( 'img' );
		img.className = 'crc-lightbox-img';
		img.alt = '';
		counter = document.createElement( 'figcaption' );
		counter.className = 'crc-lightbox-counter';
		counter.setAttribute( 'aria-live', 'polite' );
		stage.appendChild( img );
		stage.appendChild( counter );

		box.appendChild( closeBtn );
		box.appendChild( prevBtn );
		box.appendChild( stage );
		box.appendChild( nextBtn );
		document.body.appendChild( box );

		closeBtn.addEventListener( 'click', close );
		prevBtn.addEventListener( 'click', function () {
			step( -1 );
		} );
		nextBtn.addEventListener( 'click', function () {
			step( 1 );
		} );

		// Clicking the dark background closes the viewer.
		box.addEventListener( 'click', function ( event ) {
			if ( event.target === box || event.target === stage ) {
				close();
			}
		} );

		box.addEventListener( 'touchstart', function ( event ) {
			touchStart = event.changedTouches[ 0 ].clientX;
		}, { passive: true } );

		box.addEventListener( 'touchend', function ( event ) {
			var distance;

			if ( null === touchStart ) {
				return;
			}

			distance = event.changedTouches[ 0 ].clientX - touchStart;
			touchStart = null;

			if ( Math.abs( distance ) > 40 ) {
				step( distance < 0 ? 1 : -1 );
			}
		} );

		document.addEventListener( 'keydown', onKey );
	}

	function onKey( event ) {
		if ( ! box || box.hidden ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			close();
		} else if ( 'ArrowRight' === event.key ) {
			step( 1 );
		} else if ( 'ArrowLeft' === event.key ) {
			step( -1 );
		} else if ( 'Tab' === event.key ) {
			keepFocusInside( event );
		}
	}

	function keepFocusInside( event ) {
		var buttons = [ closeBtn, prevBtn, nextBtn ].filter( function ( el ) {
			return ! el.hidden;
		} );
		var first = buttons[ 0 ];
		var last = buttons[ buttons.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function wrap( index ) {
		return ( index + items.length ) % items.length;
	}

	function setImage( el, item ) {
		el.removeAttribute( 'srcset' );

		if ( item.srcset ) {
			el.sizes = '100vw';
			el.srcset = item.srcset;
		}

		el.src = item.src;
	}

	function preload( index ) {
		var item = items[ wrap( index ) ];

		if ( item ) {
			setImage( new Image(), item );
		}
	}

	function show( index ) {
		var item;

		current = wrap( index );
		item = items[ current ];

		setImage( img, item );
		img.alt = item.alt || '';
		counter.textContent = label( 'counter', '%1$s / %2$s' )
			.replace( '%1$s', current + 1 )
			.replace( '%2$s', items.length );

		prevBtn.hidden = items.length < 2;
		nextBtn.hidden = items.length < 2;

		if ( items.length > 1 ) {
			preload( current + 1 );
			preload( current - 1 );
		}
	}

	function step( direction ) {
		if ( items.length > 1 ) {
			show( current + direction );
		}
	}

	function open( list, index, trigger ) {
		build();
		items = list;
		returnFocus = trigger;
		box.hidden = false;
		document.documentElement.classList.add( 'crc-lightbox-open' );
		show( index );
		closeBtn.focus();
	}

	function close() {
		box.hidden = true;
		document.documentElement.classList.remove( 'crc-lightbox-open' );
		img.removeAttribute( 'srcset' );
		img.removeAttribute( 'src' );

		if ( returnFocus && returnFocus.focus ) {
			returnFocus.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '.crc-gallery[data-crc-lightbox] a.crc-gallery-item' ) : null;
		var data;
		var list;

		if ( ! link || event.ctrlKey || event.metaKey || event.shiftKey ) {
			return;
		}

		data = link.parentNode.querySelector( '.crc-gallery-data' );

		try {
			list = JSON.parse( data ? data.textContent : '[]' );
		} catch ( error ) {
			return;
		}

		if ( ! list.length ) {
			return;
		}

		event.preventDefault();
		open( list, parseInt( link.getAttribute( 'data-index' ), 10 ) || 0, link );
	} );
}() );
