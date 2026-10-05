/**
 * Listing screen: the suggested name and short link under the title, kept up
 * to date as the boxes are filled in. The name is made the same way as on
 * the site (Listing_Name in PHP), e.g. "Bare land for sale in Galle".
 */
( function ( window ) {
	'use strict';

	var text = window.crcReName || {};

	// "%1$s %2$s" with the values filled in.
	function fill( pattern, values ) {
		return String( pattern || '' ).replace( /%(\d)\$s/g, function ( all, n ) {
			var value = values[ parseInt( n, 10 ) - 1 ];

			return undefined === value ? '' : String( value );
		} );
	}

	// "Bare Land" becomes "bare land"; short forms in capitals, such as "A/C", stay.
	function lower( type ) {
		return String( type || '' ).trim().split( /\s+/ ).filter( Boolean ).map( function ( word ) {
			var letters = word.replace( /[^\p{L}]/gu, '' );

			return letters.length > 1 && letters === letters.toUpperCase() ? word : word.toLowerCase();
		} ).join( ' ' );
	}

	function capital( value ) {
		return value ? value.charAt( 0 ).toUpperCase() + value.slice( 1 ) : value;
	}

	// A whole number above 0 written without commas, or '' (like Overview::sanitize_number).
	function wholeNumber( value ) {
		var number = String( undefined === value || null === value ? '' : value ).replace( /[,\s]/g, '' );

		if ( ! /^\d*\.?\d*$/.test( number ) || ! /\d/.test( number ) ) {
			return '';
		}

		number = Math.floor( parseFloat( number ) );

		return number > 0 ? String( number ) : '';
	}

	function offer( category, offeredFor ) {
		var asked = String( offeredFor || '' ).trim().toLowerCase();
		var offers = text.offers || {};
		var found = '';

		Object.keys( offers ).forEach( function ( key ) {
			if ( ! found && -1 !== offers[ key ].indexOf( asked ) ) {
				found = key;
			}
		} );

		return found || ( 'properties-for-rent' === category ? 'rent' : 'sale' );
	}

	function suggest( parts ) {
		var clean = {};
		var type, homes, bedrooms, name;

		[ 'category', 'offered_for', 'property_type', 'bedrooms', 'district' ].forEach( function ( key ) {
			clean[ key ] = String( parts && undefined !== parts[ key ] && null !== parts[ key ] ? parts[ key ] : '' ).trim();
		} );

		type = lower( clean.property_type );

		if ( ! type ) {
			type = lower( 'lands' === clean.category ? text.land : text.property );
		}

		homes = 'properties-for-sale' === clean.category || 'properties-for-rent' === clean.category;
		bedrooms = homes ? wholeNumber( clean.bedrooms ) : '';

		if ( bedrooms ) {
			type = fill( text.bedrooms, [ bedrooms, type ] );
		}

		name = clean.district ?
			fill( text.withDistrict, [ type, text[ offer( clean.category, clean.offered_for ) ], clean.district ] ) :
			fill( text.noDistrict, [ type, text[ offer( clean.category, clean.offered_for ) ] ] );

		return capital( name );
	}

	// Like WordPress's sanitize_title() for the plain words of a name.
	function sanitizeTitle( value ) {
		return String( value ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' )
			.replace( /<[^>]*>/g, '' ).toLowerCase().replace( /&.+?;/g, '' ).replace( /\./g, '-' )
			.replace( /[^%a-z0-9 _-]/g, '' ).replace( /\s+/g, '-' ).replace( /-+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	// The name's words without short ones such as "in": bare-land-for-sale-galle.
	function slug( name ) {
		var skip = text.skip || [];

		return sanitizeTitle( String( name || '' ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase().split( /\s+/ ).filter( function ( word ) {
			return word && -1 === skip.indexOf( word );
		} ).join( ' ' ) );
	}

	window.crcListingName = { suggest: suggest, slug: slug, lower: lower };

	if ( ! window.jQuery ) {
		return;
	}

	window.jQuery( function ( $ ) {
		var $help = $( '.crc-name-help' );
		var $name = $help.find( '.crc-name-help-name' );
		var $slug = $help.find( '.crc-name-help-slug' );
		var $useName = $help.find( '.crc-name-help-use-name' );
		var $useLink = $help.find( '.crc-name-help-use-link' );
		var $note = $help.find( '.crc-name-help-note' );
		var categories = text.categories || {};
		var watched = [
			'#title',
			'#post_name',
			'[name="crc_property_type"]',
			'[name="crc_offered_for"]',
			'[name="crc_details[bedrooms]"]',
			'[name="tax_input[crc_listing_category][]"]',
			'.crc-district-id'
		].join( ', ' );

		if ( ! $help.length ) {
			return;
		}

		function parts() {
			var $district = $( '.crc-district-id' );

			return {
				category: categories[ $( '[name="tax_input[crc_listing_category][]"]:checked' ).val() ] || '',
				offered_for: $( '[name="crc_offered_for"]' ).val() || '',
				property_type: $( '[name="crc_property_type"]' ).val() || '',
				bedrooms: $( '[name="crc_details[bedrooms]"]' ).first().val() || '',
				district: $district.val() ? $district.attr( 'data-name' ) || '' : ''
			};
		}

		function update() {
			var name = suggest( parts() );
			var link = slug( name );
			var $real = $( '#post_name' );

			$name.text( name );
			$slug.text( link );
			$useName.prop( 'disabled', $.trim( $( '#title' ).val() ) === name );
			$useLink.prop( 'hidden', ! $real.length ).prop( 'disabled', ! link || $real.val() === link );
		}

		$( document ).on( 'input change', watched, update );

		$useName.on( 'click', function () {
			$( '#title' ).val( $name.text() ).trigger( 'input' );
			$note.text( '' );
			update();
		} );

		// Sets the link the way WordPress's own Edit link does, so its permalink line shows it too.
		$useLink.on( 'click', function () {
			var link = $slug.text();
			var $real = $( '#post_name' );

			if ( ! link || ! $real.length ) {
				return;
			}

			$real.val( link );
			$note.text( text.linkSet || '' );
			update();

			if ( window.ajaxurl && $( '#samplepermalinknonce' ).length && $( '#post_ID' ).val() ) {
				$.post( window.ajaxurl, {
					action: 'sample-permalink',
					post_id: $( '#post_ID' ).val(),
					new_slug: link,
					new_title: $( '#title' ).val(),
					samplepermalinknonce: $( '#samplepermalinknonce' ).val()
				}, function ( data ) {
					if ( data && '-1' !== String( data ) ) {
						$( '#edit-slug-box' ).html( data ).removeClass( 'hidden' );
					}
				} );
			}
		} );

		update();
	} );
}( window ) );
