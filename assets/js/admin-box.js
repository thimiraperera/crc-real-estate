/**
 * Listing screen boxes with ready-made groups and groups of the listing's own
 * (Property overview, Property features): shows the ready-made groups for the
 * chosen category, and adds, removes and reorders groups and their rows.
 */
jQuery( function ( $ ) {
	'use strict';

	var text = window.crcReBox || {};

	// New groups and rows get numbers no saved one has.
	var next = Date.now();

	function fill( template, group, row ) {
		return template.split( '{g}' ).join( group ).split( '{d}' ).join( row );
	}

	function build( template, group, row ) {
		return $( $.parseHTML( $.trim( fill( template, group, row ) ) ) );
	}

	// Rows move within their own list only.
	function sortRows( $list ) {
		$list.sortable( {
			items: '> .crc-box-item',
			handle: '.crc-box-item-handle',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'crc-box-item-placeholder'
		} );
	}

	function setUp( $box ) {
		var $groups = $box.find( '.crc-box-groups' );
		var groupTemplate = $box.find( '.crc-box-group-template' ).html() || '';
		var itemTemplate = $box.find( '.crc-box-item-template' ).html() || '';
		var extraTemplate = $box.find( '.crc-box-extra-template' ).html() || '';

		$groups.sortable( {
			items: '> .crc-box-group',
			handle: '.crc-box-group-handle',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'crc-box-group-placeholder',
			forcePlaceholderSize: true
		} );

		// The rows of the listing's own groups and those added to ready-made groups.
		$box.find( '.crc-box-items' ).each( function () {
			sortRows( $( this ) );
		} );

		$box.on( 'click', '.crc-box-group-add', function ( event ) {
			var $group = build( groupTemplate, next++, next++ );

			event.preventDefault();
			$groups.append( $group );
			sortRows( $group.find( '.crc-box-items' ) );
			$group.find( '.crc-box-group-title' ).trigger( 'focus' );
		} );

		$box.on( 'click', '.crc-box-item-add', function ( event ) {
			var $group = $( this ).closest( '.crc-box-group' );
			var $row = build( itemTemplate, $group.attr( 'data-group' ), next++ );

			event.preventDefault();
			$group.find( '.crc-box-items' ).append( $row );
			$row.find( 'input' ).first().trigger( 'focus' );
		} );

		// A row of the listing's own in a ready-made group.
		$box.on( 'click', '.crc-box-extra-add', function ( event ) {
			var $list = $( this ).closest( '.crc-box-section' ).find( '.crc-box-extras' );
			var $row = build( extraTemplate, $list.attr( 'data-group' ), next++ );

			event.preventDefault();
			$list.append( $row );
			$row.find( 'input' ).first().trigger( 'focus' );
		} );

		$box.on( 'click', '.crc-box-item-remove', function ( event ) {
			event.preventDefault();
			$( this ).closest( '.crc-box-item' ).remove();
		} );

		$box.on( 'click', '.crc-box-group-remove', function ( event ) {
			var $group = $( this ).closest( '.crc-box-group' );
			var filled = $group.find( 'input[type="text"]' ).filter( function () {
				return '' !== $.trim( this.value );
			} ).length;

			event.preventDefault();

			if ( filled && ! window.confirm( text.confirmRemove || 'Remove this group and everything in it?' ) ) {
				return;
			}

			$group.remove();
		} );

		/*
		 * A field in more than one group, such as Land extent, keeps one value,
		 * and a feature in more than one group keeps one tick.
		 */
		$box.on( 'input change', '.crc-box-section :input', function () {
			var source = this;
			var tick = 'checkbox' === source.type;

			$box.find( '.crc-box-section :input' ).filter( function () {
				return this !== source && this.name && this.name === source.name && ( ! tick || this.value === source.value );
			} ).each( function () {
				if ( tick ) {
					this.checked = source.checked;
				} else {
					$( this ).val( $( source ).val() );
				}
			} );
		} );
	}

	$( '.crc-box' ).each( function () {
		setUp( $( this ) );
	} );

	/*
	 * The ready-made groups follow the category chosen in the Category box.
	 * Groups for other categories are switched off, so they aren't saved.
	 */
	function showCategory() {
		var chosen = String( $( '#crc_listing_categorydiv input[type="radio"]:checked' ).val() || '' );
		var hasCategory = '' !== chosen && '0' !== chosen;

		$( '.crc-box-section' ).each( function () {
			var categories = String( $( this ).attr( 'data-categories' ) || '' );
			var on = 'all' === categories || ( hasCategory && -1 !== $.inArray( chosen, categories.split( ' ' ) ) );

			$( this ).prop( 'hidden', ! on ).find( ':input' ).prop( 'disabled', ! on );
		} );

		$( '.crc-box-no-category' ).prop( 'hidden', hasCategory );
	}

	$( '#crc_listing_categorydiv' ).on( 'change', 'input[type="radio"]', showCategory );
	showCategory();

	// Locked fields that come from the Price box follow it as it is typed.
	function follow( field, linked ) {
		$( field ).on( 'input', function () {
			var number = parseInt( String( $( this ).val() ).replace( /[,\s]/g, '' ).split( '.' )[ 0 ], 10 );

			$( '[data-crc-linked="' + linked + '"]' ).val( number > 0 ? number.toLocaleString( 'en-US' ) : '' );
		} );
	}

	follow( '#crc-per-perch-field', 'price_per_perch' );
	follow( '#crc-price-field', 'price' );
	follow( '#crc-price-field', 'rent' );
} );
