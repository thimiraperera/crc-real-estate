/**
 * Listings → Widgets, Keyword ticker tab: imports the ticker's keywords from a JSON file into
 * the box, and exports the keywords in the box as a JSON file.
 */
( function () {
	'use strict';

	var text = window.crcSettings;
	var box = document.getElementById( 'crc-ticker-keywords' );

	if ( ! text || ! box ) {
		return;
	}

	var field = box.parentNode;
	var importButton = field.querySelector( '.crc-ticker-import' );
	var exportButton = field.querySelector( '.crc-ticker-export' );
	var file = field.querySelector( '.crc-ticker-file' );
	var note = field.querySelector( '.crc-ticker-note' );

	function say( words, warning ) {
		note.textContent = words;
		note.className = 'crc-ticker-note' + ( warning ? ' crc-warning' : '' );
	}

	function format( template, value ) {
		return template.replace( '%s', String( value ) );
	}

	// The keywords in the box: one a line, without empty lines.
	function lines() {
		return box.value.split( /\r\n|\r|\n/ ).map( function ( line ) {
			return line.trim();
		} ).filter( function ( line ) {
			return '' !== line;
		} );
	}

	// Keywords from a file: {"keywords": [...]}, a plain list, or a list of
	// objects with a keyword, text, name or title.
	function keywordsFrom( data ) {
		var list = data;
		var keys;
		var i;

		if ( list && ! Array.isArray( list ) && 'object' === typeof list ) {
			list = Array.isArray( list.keywords ) ? list.keywords : null;

			if ( ! list ) {
				keys = Object.keys( data );

				for ( i = 0; i < keys.length && ! list; i++ ) {
					list = Array.isArray( data[ keys[ i ] ] ) ? data[ keys[ i ] ] : null;
				}
			}
		}

		if ( ! Array.isArray( list ) ) {
			return [];
		}

		return list.map( function ( item ) {
			if ( item && 'object' === typeof item ) {
				item = item.keyword || item.text || item.name || item.title || '';
			}

			return 'string' === typeof item || 'number' === typeof item ? String( item ).replace( /\s+/g, ' ' ).trim() : '';
		} ).filter( function ( item ) {
			return '' !== item;
		} );
	}

	importButton.addEventListener( 'click', function () {
		file.click();
	} );

	file.addEventListener( 'change', function () {
		var chosen = file.files && file.files[ 0 ];
		var reader;

		if ( ! chosen ) {
			return;
		}

		if ( chosen.size > ( parseInt( text.size, 10 ) || 1048576 ) ) {
			file.value = '';
			say( text.tooBig, true );
			return;
		}

		reader = new window.FileReader();

		reader.onload = function () {
			var max = parseInt( text.max, 10 ) || 200;
			var length = parseInt( text.chars, 10 ) || 100;
			var data;
			var keywords;
			var found;

			file.value = '';

			try {
				data = JSON.parse( String( reader.result ).replace( /^\uFEFF/, '' ) );
			} catch ( error ) {
				say( text.notJson, true );
				return;
			}

			keywords = keywordsFrom( data );
			found = keywords.length;
			keywords = keywords.slice( 0, max ).map( function ( keyword ) {
				return keyword.slice( 0, length );
			} );

			if ( ! keywords.length ) {
				say( text.noWords, true );
				return;
			}

			if ( lines().length && ! window.confirm( format( text.replace, keywords.length ) ) ) {
				return;
			}

			box.value = keywords.join( '\n' );
			say( found > max ? text.cut.replace( '%1$s', String( found ) ).split( '%2$s' ).join( String( max ) ) : format( text.imported, keywords.length ) );
		};

		reader.onerror = function () {
			file.value = '';
			say( text.notJson, true );
		};

		reader.readAsText( chosen );
	} );

	exportButton.addEventListener( 'click', function () {
		var keywords = lines();
		var link;
		var url;

		if ( ! keywords.length ) {
			say( text.empty, true );
			return;
		}

		url = window.URL.createObjectURL( new window.Blob( [ JSON.stringify( { keywords: keywords }, null, '\t' ) + '\n' ], { type: 'application/json' } ) );
		link = document.createElement( 'a' );
		link.href = url;
		link.download = text.file;
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );

		window.setTimeout( function () {
			window.URL.revokeObjectURL( url );
		}, 1000 );

		say( format( text.exported, keywords.length ) );
	} );
}() );
