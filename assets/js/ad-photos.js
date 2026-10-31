/**
 * Post a free ad: the photos box. Each photo is made smaller in the browser,
 * so neither side is bigger than the largest size in Settings, turned into
 * WebP (JPEG where the browser can't make WebP; the site turns it into WebP)
 * and sent in small pieces, one photo after another, while people fill in
 * the rest of the form. A piece that doesn't get through is sent again. When
 * the form is sent while photos are still going, it waits for them, then
 * goes with their names.
 */
( function () {
	'use strict';

	var config = window.crcReAdPhotos || {};
	var text = config.messages || {};
	var TYPES = [ 'image/jpeg', 'image/png', 'image/webp' ];
	var SMALLEST = 200;
	var MOST_PIXELS = 120000000;
	var TRIES = 5;
	var CLOSE = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';
	var boxes = [];

	// Fills in %s, or %1$s and %2$s.
	function format( message ) {
		var values = Array.prototype.slice.call( arguments, 1 );
		var next = 0;

		return String( message || '' ).replace( /%(\d+)\$s|%s/g, function ( match, number ) {
			var value = number ? values[ number - 1 ] : values[ next++ ];

			return undefined === value ? '' : String( value );
		} );
	}

	// 32 random letters and numbers, the way the site expects a name.
	function randomId() {
		var bytes = new window.Uint8Array( 16 );
		var hex = '';
		var i;

		window.crypto.getRandomValues( bytes );

		for ( i = 0; i < bytes.length; i++ ) {
			hex += ( bytes[ i ] < 16 ? '0' : '' ) + bytes[ i ].toString( 16 );
		}

		return hex;
	}

	function wait( ms ) {
		return new Promise( function ( resolve ) {
			window.setTimeout( resolve, ms );
		} );
	}

	// Opens a photo, the right way up, as the browser shows it.
	function open( file ) {
		return new Promise( function ( resolve, reject ) {
			var url = window.URL.createObjectURL( file );
			var image = new window.Image();

			image.onload = function () {
				resolve( { image: image, url: url } );
			};
			image.onerror = function () {
				window.URL.revokeObjectURL( url );
				reject( 'unreadable' );
			};
			image.src = url;
		} );
	}

	// Draws a photo at a new size: in halves first, so it stays sharp, then
	// to the size, on white, as photos have no see-through parts.
	function draw( image, width, height ) {
		var source = image;
		var w = image.naturalWidth;
		var h = image.naturalHeight;
		var canvas;
		var context;

		while ( w / 2 >= width && h / 2 >= height ) {
			w = Math.round( w / 2 );
			h = Math.round( h / 2 );
			canvas = document.createElement( 'canvas' );
			canvas.width = w;
			canvas.height = h;
			context = canvas.getContext( '2d' );
			context.imageSmoothingQuality = 'high';
			context.drawImage( source, 0, 0, w, h );
			source = canvas;
		}

		canvas = document.createElement( 'canvas' );
		canvas.width = width;
		canvas.height = height;
		context = canvas.getContext( '2d' );
		context.fillStyle = '#fff';
		context.fillRect( 0, 0, width, height );
		context.imageSmoothingQuality = 'high';
		context.drawImage( source, 0, 0, width, height );

		return canvas;
	}

	function blobOf( canvas, type, quality ) {
		return new Promise( function ( resolve ) {
			canvas.toBlob( resolve, type, quality );
		} );
	}

	// WebP where the browser can make it; Safari makes JPEG, and the site turns it into WebP.
	function encode( canvas ) {
		return blobOf( canvas, 'image/webp', 0.9 ).then( function ( blob ) {
			return blob && 'image/webp' === blob.type ? blob : blobOf( canvas, 'image/jpeg', 0.9 );
		} );
	}

	// Makes a photo ready to send: checked, smaller, and WebP or JPEG.
	function prepare( box, photo ) {
		var url = '';

		return open( photo.file ).then( function ( opened ) {
			var image = opened.image;
			var w = image.naturalWidth;
			var h = image.naturalHeight;
			var scale;

			url = opened.url;

			if ( ! w || ! h ) {
				throw 'unreadable';
			}

			if ( Math.min( w, h ) < SMALLEST ) {
				throw 'small';
			}

			if ( w * h > MOST_PIXELS ) {
				throw 'huge';
			}

			scale = Math.min( 1, box.width / w, box.height / h );

			return encode( draw( image, Math.max( 1, Math.round( w * scale ) ), Math.max( 1, Math.round( h * scale ) ) ) );
		} ).then( function ( blob ) {
			if ( url ) {
				window.URL.revokeObjectURL( url );
			}

			if ( ! blob ) {
				throw 'unreadable';
			}

			if ( blob.size > box.bytes ) {
				throw 'huge';
			}

			photo.blob = blob;
			photo.type = blob.type;
		}, function ( error ) {
			if ( url ) {
				window.URL.revokeObjectURL( url );
			}

			throw error;
		} );
	}

	function progress( photo, part ) {
		photo.item.style.setProperty( '--crc-progress', Math.round( Math.max( 0, Math.min( 1, part ) ) * 100 ) + '%' );
	}

	// Sends one piece of a photo. The answer says how many pieces arrived,
	// or that the whole photo is there.
	function sendPiece( box, photo, index, total ) {
		return new Promise( function ( resolve, reject ) {
			var start = index * box.chunk;
			var piece = photo.blob.slice( start, start + box.chunk );
			var data = new window.FormData();
			var xhr = new window.XMLHttpRequest();

			data.append( 'action', config.action || '' );
			data.append( 'session', box.session() );
			data.append( 'photo', photo.id );
			data.append( 'index', String( index ) );
			data.append( 'total', String( total ) );
			data.append( 'size', String( photo.blob.size ) );
			data.append( 'type', photo.type );
			data.append( 'chunk', piece, 'piece' );

			function stop( code ) {
				photo.xhr = null;
				reject( { code: code } );
			}

			photo.xhr = xhr;
			xhr.open( 'POST', config.url || '' );
			xhr.timeout = 120000;
			xhr.upload.onprogress = function ( event ) {
				if ( event.lengthComputable && event.total ) {
					progress( photo, ( start + piece.size * ( event.loaded / event.total ) ) / photo.blob.size );
				}
			};
			xhr.onload = function () {
				var answer = null;

				try {
					answer = JSON.parse( xhr.responseText );
				} catch ( error ) {
					answer = null;
				}

				if ( xhr.status >= 200 && xhr.status < 300 && answer && answer.success ) {
					photo.xhr = null;
					resolve( answer.data || {} );
				} else if ( answer && answer.data && answer.data.code ) {
					stop( answer.data.code );
				} else {
					stop( 'network' );
				}
			};
			xhr.onerror = function () {
				stop( 'network' );
			};
			xhr.ontimeout = function () {
				stop( 'network' );
			};
			xhr.onabort = function () {
				stop( 'aborted' );
			};
			xhr.send( data );
		} );
	}

	// Sends a photo, piece by piece. A piece that doesn't get through is sent
	// again, waiting longer each time; when the site lost track of the photo,
	// it is sent again from the start, once.
	function upload( box, photo ) {
		var total = Math.max( 1, Math.ceil( photo.blob.size / box.chunk ) );
		var restarted = false;

		function piece( index, tries ) {
			if ( photo.removed ) {
				return Promise.reject( { code: 'aborted' } );
			}

			return sendPiece( box, photo, index, total ).then( function ( answer ) {
				return answer.done ? answer : piece( index + 1, 0 );
			}, function ( error ) {
				if ( ( 'order' === error.code || 'invalid' === error.code ) && ! restarted ) {
					restarted = true;
					photo.id = randomId();
					progress( photo, 0 );

					return piece( 0, 0 );
				}

				if ( 'network' === error.code && tries < TRIES && ! photo.removed ) {
					return wait( 1000 * Math.pow( 2, tries ) ).then( function () {
						return piece( index, tries + 1 );
					} );
				}

				throw error;
			} );
		}

		photo.id = randomId();
		progress( photo, 0 );

		return piece( 0, 0 );
	}

	function showError( box, message ) {
		box.error.textContent = message || '';
		box.error.hidden = ! message;
		box.field.classList.toggle( 'is-invalid', !! message );
	}

	function showStatus( box, message ) {
		box.status.textContent = message || '';
		box.status.hidden = ! message;
	}

	function kept( box ) {
		return box.photos.filter( function ( photo ) {
			return ! photo.removed;
		} );
	}

	function busy( box ) {
		return kept( box ).some( function ( photo ) {
			return 'done' !== photo.state && 'failed' !== photo.state;
		} );
	}

	function failed( box ) {
		return kept( box ).some( function ( photo ) {
			return 'failed' === photo.state;
		} );
	}

	// The count, the numbers in the buttons' names, and whether more can be added.
	function update( box ) {
		var photos = kept( box );
		var full = photos.length >= box.max;

		photos.forEach( function ( photo, i ) {
			var label = format( text.photo, i + 1 );

			photo.remove.setAttribute( 'aria-label', format( text.remove, i + 1 ) );
			photo.remove.title = photo.remove.getAttribute( 'aria-label' );

			if ( photo.image ) {
				photo.image.alt = label;
			}
		} );

		box.list.hidden = ! photos.length;
		box.input.disabled = full;
		box.drop.classList.toggle( 'is-full', full );

		if ( ! box.sendWhenDone ) {
			showStatus( box, photos.length ? format( text.count, photos.length, box.max ) : '' );
		}
	}

	function setState( photo, state ) {
		var labels = {
			waiting: text.preparing,
			working: text.preparing,
			sending: text.sending,
			done: text.sent,
			failed: text.failed
		};

		photo.state = state;
		photo.item.classList.toggle( 'is-working', 'waiting' === state || 'working' === state );
		photo.item.classList.toggle( 'is-sending', 'sending' === state );
		photo.item.classList.toggle( 'is-done', 'done' === state );
		photo.item.classList.toggle( 'is-failed', 'failed' === state );
		photo.stateText.textContent = labels[ state ] || '';
		photo.retry.hidden = 'failed' !== state;

		if ( 'done' === state && ! photo.field ) {
			photo.field = document.createElement( 'input' );
			photo.field.type = 'hidden';
			photo.field.name = 'crc_photos[]';
			photo.item.appendChild( photo.field );
		}

		if ( photo.field ) {
			photo.field.value = 'done' === state ? photo.id : '';
			photo.field.disabled = 'done' !== state;
		}
	}

	function remove( box, photo ) {
		photo.removed = true;

		if ( photo.xhr ) {
			photo.xhr.abort();
		}

		if ( photo.preview ) {
			window.URL.revokeObjectURL( photo.preview );
		}

		if ( photo.item.parentNode ) {
			photo.item.parentNode.removeChild( photo.item );
		}

		box.photos.splice( box.photos.indexOf( photo ), 1 );
		update( box );
		run( box );
	}

	function addPhoto( box, file ) {
		var photo = {
			file: file,
			state: 'waiting',
			item: document.createElement( 'li' ),
			remove: document.createElement( 'button' ),
			retry: document.createElement( 'button' ),
			stateText: document.createElement( 'span' )
		};
		var bar = document.createElement( 'span' );

		photo.item.className = 'crc-ad-photos-item';
		bar.className = 'crc-ad-photos-bar';
		bar.appendChild( document.createElement( 'span' ) );
		photo.stateText.className = 'crc-ad-photos-state';
		photo.remove.type = 'button';
		photo.remove.className = 'crc-ad-photos-remove';
		photo.remove.innerHTML = CLOSE;
		photo.retry.type = 'button';
		photo.retry.className = 'crc-ad-photos-retry';
		photo.retry.textContent = text.retry || '';
		photo.retry.hidden = true;

		photo.remove.addEventListener( 'click', function () {
			remove( box, photo );
			box.input.focus();
		} );

		photo.retry.addEventListener( 'click', function () {
			showError( box, '' );
			setState( photo, 'waiting' );
			run( box );
		} );

		photo.item.appendChild( bar );
		photo.item.appendChild( photo.stateText );
		photo.item.appendChild( photo.remove );
		photo.item.appendChild( photo.retry );
		box.list.appendChild( photo.item );
		box.photos.push( photo );
		setState( photo, 'waiting' );
	}

	function showPreview( photo ) {
		photo.preview = window.URL.createObjectURL( photo.blob );
		photo.image = document.createElement( 'img' );
		photo.image.src = photo.preview;
		photo.image.alt = '';
		photo.item.insertBefore( photo.image, photo.item.firstChild );
	}

	// Adds the photos chosen or dropped, with a word about any that can't be.
	function add( box, files ) {
		var problems = [];
		var skipped = 0;

		showError( box, '' );

		Array.prototype.forEach.call( files || [], function ( file ) {
			var same = kept( box ).some( function ( photo ) {
				return photo.file.name === file.name && photo.file.size === file.size && photo.file.lastModified === file.lastModified;
			} );

			if ( kept( box ).length >= box.max ) {
				skipped++;
			} else if ( -1 === TYPES.indexOf( file.type ) && ! /\.(jpe?g|png|webp)$/i.test( file.name ) ) {
				problems.push( format( text.type, file.name ) );
			} else if ( same ) {
				problems.push( format( text.duplicate, file.name ) );
			} else {
				addPhoto( box, file );
			}
		} );

		if ( skipped ) {
			problems.unshift( format( text.too_many, box.max ) );
		}

		if ( problems.length ) {
			showError( box, problems.join( ' ' ) );
		}

		update( box );
		run( box );
	}

	// Sends the form once every photo is done, when it was sent while they weren't.
	function finished( box ) {
		var button;

		if ( ! box.sendWhenDone ) {
			return;
		}

		box.sendWhenDone = false;
		update( box );

		if ( failed( box ) ) {
			showError( box, text.unsent );
			return;
		}

		button = box.form.querySelector( '.crc-inquiry-button' );

		if ( box.form.requestSubmit ) {
			box.form.requestSubmit( button || undefined );
		} else if ( button ) {
			button.click();
		}
	}

	// Gets the next photo ready and sends it, one at a time.
	function run( box ) {
		var photo;

		if ( box.running ) {
			return;
		}

		photo = kept( box ).filter( function ( item ) {
			return 'waiting' === item.state;
		} )[ 0 ];

		if ( ! photo ) {
			finished( box );
			return;
		}

		box.running = true;
		setState( photo, 'working' );

		( photo.blob ? Promise.resolve() : prepare( box, photo ).then( function () {
			if ( ! photo.removed ) {
				showPreview( photo );
			}
		} ) ).then( function () {
			if ( photo.removed ) {
				throw { code: 'aborted' };
			}

			setState( photo, 'sending' );

			return upload( box, photo );
		} ).then( function () {
			if ( ! photo.removed ) {
				setState( photo, 'done' );
			}
		}, function ( error ) {
			var code = 'string' === typeof error ? error : ( error && error.code ) || 'network';

			if ( photo.removed || 'aborted' === code ) {
				return;
			}

			// Not a photo the form can take: it goes, with a word about why.
			if ( 'small' === code || 'huge' === code || 'unreadable' === code ) {
				remove( box, photo );
				showError( box, format( text[ code ], photo.file.name, SMALLEST ) );
				return;
			}

			setState( photo, 'failed' );
			showError( box, text[ code ] || text.network );
		} ).then( function () {
			box.running = false;
			update( box );
			run( box );
		} );
	}

	function setUp( field ) {
		var box = {
			field: field,
			form: field.closest( 'form' ),
			input: field.querySelector( '.crc-ad-photos-input' ),
			drop: field.querySelector( '.crc-ad-photos-drop' ),
			list: field.querySelector( '.crc-ad-photos-list' ),
			status: field.querySelector( '.crc-ad-photos-status' ),
			error: field.querySelector( '.crc-inquiry-error' ),
			visit: field.querySelector( 'input[name="crc_photo_session"]' ),
			max: parseInt( field.getAttribute( 'data-max' ), 10 ) || 10,
			width: parseInt( field.getAttribute( 'data-width' ), 10 ) || 1440,
			height: parseInt( field.getAttribute( 'data-height' ), 10 ) || 1440,
			chunk: parseInt( field.getAttribute( 'data-chunk' ), 10 ) || 524288,
			bytes: parseInt( field.getAttribute( 'data-bytes' ), 10 ) || 8388608,
			photos: [],
			running: false,
			sendWhenDone: false
		};

		if ( ! box.form || ! box.input || ! box.drop || ! box.list || ! box.status || ! box.error || ! box.visit ) {
			return;
		}

		// The visit's name, made when the first photo goes.
		box.session = function () {
			if ( ! box.visit.value ) {
				box.visit.value = randomId();
			}

			return box.visit.value;
		};

		box.input.addEventListener( 'change', function () {
			add( box, box.input.files );
			box.input.value = '';
		} );

		// Dropped photos are added here, also when the box is full, so the browser never opens them instead.
		[ 'dragenter', 'dragover' ].forEach( function ( type ) {
			box.drop.addEventListener( type, function ( event ) {
				event.preventDefault();
				box.drop.classList.add( 'is-dragging' );
			} );
		} );

		box.drop.addEventListener( 'dragleave', function ( event ) {
			if ( ! box.drop.contains( event.relatedTarget ) ) {
				box.drop.classList.remove( 'is-dragging' );
			}
		} );

		box.drop.addEventListener( 'drop', function ( event ) {
			event.preventDefault();
			box.drop.classList.remove( 'is-dragging' );
			add( box, event.dataTransfer ? event.dataTransfer.files : [] );
		} );

		// Sent: the photos went with the ad.
		box.form.addEventListener( 'reset', function () {
			kept( box ).forEach( function ( photo ) {
				remove( box, photo );
			} );
			// A new ad gets a new visit; a hidden field would keep the old name through the reset.
			box.visit.value = '';
			box.visit.defaultValue = '';
			box.sendWhenDone = false;
			showError( box, '' );
			update( box );
		} );

		boxes.push( box );
	}

	// The form waits while photos are on their way, and doesn't go while some couldn't be sent.
	function hold( event ) {
		var box = boxes.filter( function ( item ) {
			return item.form === event.target;
		} )[ 0 ];

		if ( ! box ) {
			return;
		}

		if ( failed( box ) ) {
			event.preventDefault();
			event.stopPropagation();
			showError( box, text.unsent );
			box.field.scrollIntoView( { block: 'center' } );
			return;
		}

		if ( busy( box ) ) {
			event.preventDefault();
			event.stopPropagation();
			box.sendWhenDone = true;
			showStatus( box, text.waiting );
		}
	}

	function start() {
		var fields = document.querySelectorAll( '[data-crc-photos]' );
		var able = window.Promise && window.FormData && window.URL && window.URL.createObjectURL && window.crypto && window.crypto.getRandomValues && document.createElement( 'canvas' ).toBlob;
		var i;

		for ( i = 0; i < fields.length; i++ ) {
			// Browsers that can't make photos smaller send the form without them.
			if ( able ) {
				setUp( fields[ i ] );
			} else {
				fields[ i ].hidden = true;
			}
		}

		if ( boxes.length ) {
			document.addEventListener( 'submit', hold, true );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
