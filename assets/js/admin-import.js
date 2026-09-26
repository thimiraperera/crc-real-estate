/**
 * Listings → Import & Export: sends the chosen CSV file, then asks the site
 * to import it a little at a time, showing what happened to each row.
 */
( function () {
	'use strict';

	var settings = window.crcImport;
	var page = document.querySelector( '.crc-import' );
	var form = page ? page.querySelector( '.crc-import-form' ) : null;

	if ( ! settings || ! form ) {
		return;
	}

	var text = settings.text;
	var input = form.querySelector( 'input[type="file"]' );
	var startButton = form.querySelector( '.crc-import-start' );
	var run = page.querySelector( '.crc-import-run' );
	var bar = run.querySelector( '.crc-import-bar' );
	var status = run.querySelector( '.crc-import-status' );
	var counts = run.querySelector( '.crc-import-counts' );
	var note = run.querySelector( '.crc-import-note' );
	var resume = run.querySelector( '.crc-import-resume' );
	var stop = run.querySelector( '.crc-import-stop' );
	var log = run.querySelector( '.crc-import-log' );
	var pending = page.querySelector( '.crc-import-pending' );
	var running = false;
	var tries = 0;
	var inFlight = null;
	var importId = '';
	var seen = 0;
	var shown = {};

	// Each run of steps has its own number, so a timer left from an earlier one does nothing.
	var loop = 0;

	// Waits before asking again after a step that got no answer: 2, 4 then 8 seconds.
	var RETRIES = [ 2000, 4000, 8000 ];

	// Wait while another step is still working.
	var WAIT = 3000;

	function format( template ) {
		var values = Array.prototype.slice.call( arguments, 1 );

		return template.replace( /%(\d+)\$s|%s/g, function ( match, position ) {
			var value = position ? values[ position - 1 ] : values.shift();

			return undefined === value ? '' : String( value );
		} );
	}

	function number( value ) {
		return Number( value ).toLocaleString();
	}

	// Why the site didn't answer, in plain words.
	function reason( code ) {
		if ( [ 408, 504, 522, 524 ].indexOf( code ) > -1 ) {
			return text.slow;
		}

		if ( [ 429, 503, 508 ].indexOf( code ) > -1 ) {
			return text.busySite;
		}

		if ( [ 403, 406, 412 ].indexOf( code ) > -1 ) {
			return text.blocked;
		}

		return code >= 500 ? text.siteError : text.noAnswer;
	}

	function post( action, body ) {
		body = body || new FormData();
		body.append( 'action', action );
		body.append( 'nonce', settings.nonce );

		return fetch( settings.ajax, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		} ).then( function ( response ) {
			return response.text().then( function ( raw ) {
				var data = null;
				var error;

				// WordPress answers "0" when the person isn't logged in any more.
				if ( '0' === raw.trim() || '-1' === raw.trim() ) {
					error = new Error( text.loggedOut );
					error.loggedOut = true;
					throw error;
				}

				try {
					data = JSON.parse( raw );
				} catch ( parseError ) {
					data = null;
				}

				if ( ! data || 'object' !== typeof data ) {
					throw new Error( response.ok ? text.noAnswer : reason( response.status ) );
				}

				return data;
			} );
		}, function () {
			throw new Error( text.noAnswer );
		} );
	}

	function message( data ) {
		return data && data.data && data.data.message ? data.data.message : text.noAnswer;
	}

	function show( element, visible ) {
		element.hidden = ! visible;
	}

	function busy( on ) {
		running = on;
		startButton.disabled = on;
		input.disabled = on;
		show( stop, on || ! resume.hidden );
	}

	function say( words, kind ) {
		note.textContent = words;
		note.className = 'crc-import-note' + ( kind ? ' ' + kind : '' );
	}

	function progress( done, total, tally ) {
		bar.max = Math.max( 1, total );
		bar.value = done;
		status.textContent = format( text.progress, number( done ), number( total ) );

		if ( tally ) {
			counts.textContent = format( text.counts, number( tally.created ), number( tally.updated ), number( tally.failed ) );
		}
	}

	// Lists what happened to a row, once, in the order of the spreadsheet's rows.
	function report( row ) {
		var item;
		var head;
		var what;
		var link;
		var list;

		if ( ! row || shown[ row.line ] ) {
			return;
		}

		shown[ row.line ] = true;
		item = document.createElement( 'li' );
		head = document.createElement( 'p' );
		what = document.createElement( 'strong' );
		item.className = 'crc-import-row is-' + row.action;
		what.textContent = format( text.row, row.line ) + ': ' + ( 'created' === row.action ? text.created : ( 'updated' === row.action ? text.updated : text.failedRow ) );
		head.appendChild( what );
		head.appendChild( document.createTextNode( ' · ' + ( row.title || text.noTitle ) ) );

		if ( row.status ) {
			head.appendChild( document.createTextNode( ' (' + ( text.statuses[ row.status ] || row.status ) + ')' ) );
		}

		if ( row.link && /^https?:\/\//.test( row.link ) ) {
			link = document.createElement( 'a' );
			link.href = row.link;
			link.target = '_blank';
			link.rel = 'noopener';
			link.textContent = text.edit;
			head.appendChild( document.createTextNode( ' · ' ) );
			head.appendChild( link );
		}

		item.appendChild( head );

		if ( row.warnings && row.warnings.length ) {
			list = document.createElement( 'ul' );

			row.warnings.forEach( function ( warning ) {
				var line = document.createElement( 'li' );

				line.textContent = warning;
				list.appendChild( line );
			} );

			item.appendChild( list );
		}

		item.setAttribute( 'data-line', String( row.line ) );
		log.insertBefore( item, Array.prototype.filter.call( log.children, function ( other ) {
			return Number( other.getAttribute( 'data-line' ) ) > Number( row.line );
		} )[ 0 ] || null );
	}

	function paused( why ) {
		busy( false );
		say( format( text.failed, why ), 'crc-warning' );
		show( resume, true );
		show( stop, true );
	}

	function finish( words ) {
		busy( false );
		say( words, 'crc-import-done' );
		show( resume, false );
		show( stop, false );
	}

	function failed( words ) {
		busy( false );
		say( words, 'crc-warning' );
		show( resume, false );
		show( stop, false );
	}

	// Shows an unfinished import, with Continue and Stop.
	function showPending( info ) {
		show( pending.querySelector( '.crc-import-resume' ), true );
		importId = info.run || '';
		pending.querySelector( '.crc-import-pending-text' ).textContent = format( text.pending, number( info.done ), number( info.total ) );
		show( pending, true );
		progress( info.done, info.total, info.counts );
	}

	// Runs a step later, unless this run of steps has ended meanwhile.
	function later( wait, mine ) {
		window.setTimeout( function () {
			if ( running && mine === loop ) {
				step( mine );
			}
		}, wait );
	}

	function step( mine, fresh ) {
		var body = new FormData();

		body.append( 'run', importId );
		body.append( 'seen', String( seen ) );

		if ( fresh ) {
			body.append( 'fresh', '1' );
		}
		inFlight = post( 'crc_re_import_step', body );

		inFlight.then( function ( data ) {
			var result = data.data;

			inFlight = null;

			// Rows a step finished are listed, even when Stop was pressed meanwhile.
			if ( data.success && result && ! result.busy ) {
				( result.reports || [] ).forEach( report );
				( result.log || [] ).forEach( report );
				seen = result.seen || seen;
				progress( result.done, result.total, result.counts );
			}

			if ( mine !== loop ) {
				return;
			}

			if ( ! data.success ) {
				failed( message( data ) );
				return;
			}

			tries = 0;

			// Another step is still working, e.g. in another window: wait for it.
			if ( result.busy ) {
				say( text.waiting );
				later( WAIT, mine );
				return;
			}

			if ( result.finished ) {
				finish( result.stopped ? text.stopped : text.finished );
				return;
			}

			if ( running ) {
				say( text.working );
				step( mine );
			}
		} ).catch( function ( error ) {
			inFlight = null;

			if ( mine !== loop ) {
				return;
			}

			if ( error && error.loggedOut ) {
				tries = 0;
				failed( text.loggedOut );
				return;
			}

			// A step cut short by the server: the import is saved as it goes, so ask again.
			if ( tries < RETRIES.length ) {
				later( RETRIES[ tries++ ], mine );
				return;
			}

			tries = 0;
			paused( error && error.message ? error.message : text.noAnswer );
		} );
	}

	function begin( fresh ) {
		loop++;
		tries = 0;
		show( run, true );
		show( pending, false );
		show( resume, false );
		say( text.working );
		busy( true );
		step( loop, fresh );
	}

	function clearUnknown() {
		Array.prototype.forEach.call( run.querySelectorAll( '.crc-import-unknown' ), function ( element ) {
			element.parentNode.removeChild( element );
		} );
	}

	form.addEventListener( 'submit', function ( event ) {
		var file = input.files && input.files[ 0 ];
		var body;

		event.preventDefault();

		if ( running ) {
			return;
		}

		// An unfinished import is continued or stopped first.
		if ( ! pending.hidden || ! resume.hidden ) {
			window.alert( text.unfinished );
			return;
		}

		if ( ! file ) {
			window.alert( text.choose );
			input.focus();
			return;
		}

		if ( ! /\.(csv|txt)$/i.test( file.name ) ) {
			window.alert( text.notCsv );
			input.focus();
			return;
		}

		if ( file.size > settings.maxSize ) {
			window.alert( text.tooBig );
			input.focus();
			return;
		}

		body = new FormData();
		body.append( 'file', file );

		loop++;
		tries = 0;
		log.textContent = '';
		counts.textContent = '';
		bar.value = 0;
		shown = {};
		seen = 0;
		clearUnknown();
		show( run, true );
		show( pending, false );
		show( resume, false );
		status.textContent = text.reading;
		say( '' );
		busy( true );
		show( stop, false );

		post( 'crc_re_import_start', body ).then( function ( data ) {
			var unknown;

			if ( ! data.success ) {
				failed( message( data ) );
				status.textContent = '';

				if ( data.data && data.data.pending ) {
					show( run, false );
					showPending( data.data.pending );
				}
				return;
			}

			if ( ! running ) {
				return;
			}

			importId = data.data.run;
			progress( 0, data.data.total, { created: 0, updated: 0, failed: 0 } );
			begin();

			if ( data.data.unknown && data.data.unknown.length ) {
				unknown = document.createElement( 'p' );
				unknown.className = 'crc-import-unknown crc-warning';
				unknown.setAttribute( 'role', 'alert' );
				unknown.textContent = format( text.unknown, data.data.unknown.join( ', ' ) );
				run.insertBefore( unknown, log );
			}
		} ).catch( function ( error ) {
			failed( error && error.loggedOut ? text.loggedOut : format( text.startFailed, error && error.message ? error.message : text.noAnswer ) );
			status.textContent = '';
		} );
	} );

	// Whether the site answered that it was asked to stop the import.
	var stopAsked = false;

	function stopImport( fromNotice ) {
		var body = new FormData();

		body.append( 'run', importId );

		return post( 'crc_re_import_stop', body ).then( function ( data ) {
			var result = data.data || {};

			if ( ! data.success ) {
				failed( message( data ) );
				return;
			}

			// A step is still working: it was asked to stop, so ask again shortly.
			if ( result.busy ) {
				stopAsked = true;

				return new Promise( function ( resolve ) {
					window.setTimeout( resolve, WAIT );
				} ).then( function () {
					return stopImport( fromNotice );
				} );
			}

			show( pending, false );

			if ( result.report ) {
				report( result.report );
			}

			( result.log || [] ).forEach( report );

			if ( result.counts ) {
				progress( result.done, result.total, result.counts );
			}

			finish( false === result.stopped ? text.finished : text.stopped );
		} );
	}

	page.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( 'button' ) : null;
		var fromNotice;

		if ( ! button ) {
			return;
		}

		if ( button.classList.contains( 'crc-import-resume' ) ) {
			if ( ! running ) {
				begin( true );
			}
			return;
		}

		if ( button.classList.contains( 'crc-import-stop' ) ) {
			if ( ! window.confirm( text.stopAsk ) ) {
				return;
			}

			fromNotice = ! pending.hidden;
			stopAsked = false;
			loop++;
			running = false;
			startButton.disabled = true;

			// Say what is happening while it waits for a step that is still working.
			show( run, true );
			show( pending, false );
			show( resume, false );
			show( stop, false );
			say( text.stopping );

			// A step still being worked on would save the import again, so it is stopped after that step.
			Promise.resolve( inFlight ).catch( function () {} ).then( function () {
				return stopImport( fromNotice );
			} ).catch( function ( error ) {
				var reasonText = error && error.message ? error.message : text.noAnswer;
				var why = error && error.loggedOut ? text.loggedOut : format( stopAsked ? text.stopAsked : text.stopFailed, reasonText );

				busy( false );
				say( why, 'crc-warning' );

				// Only one pair of buttons: the notice's when Stop was pressed there.
				// When the site was already asked to stop, only Stop, to check.
				if ( fromNotice ) {
					show( pending, true );
					show( pending.querySelector( '.crc-import-resume' ), ! stopAsked );
					show( resume, false );
					show( stop, false );
				} else {
					show( resume, ! stopAsked );
					show( stop, true );
				}
			} );
		}
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( running ) {
			event.preventDefault();
			event.returnValue = text.leave;
			return text.leave;
		}
	} );

	if ( settings.pending && pending ) {
		// An import left unfinished, e.g. when the page was closed.
		showPending( settings.pending );
	} else if ( settings.last ) {
		// The last import ended while the page was closed: what it did.
		show( run, true );
		progress( settings.last.done, settings.last.total, settings.last.counts );
		( settings.last.log || [] ).forEach( report );
		say( settings.last.stopped ? text.lastStopped : text.lastDone, 'crc-import-done' );
		show( resume, false );
		show( stop, false );
	}
}() );
