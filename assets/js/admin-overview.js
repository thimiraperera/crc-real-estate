/**
 * Property overview box: shows the ready-made groups for the chosen category,
 * and adds, removes and reorders details in them and in the listing's own
 * groups.
 */
jQuery( function ( $ ) {
	'use strict';

	var text = window.crcReOverview || {};
	var $box = $( '.crc-overview-box' );
	var $groups = $box.find( '.crc-overview-box-groups' );
	var groupTemplate = $box.find( '.crc-overview-box-group-template' ).html() || '';
	var detailTemplate = $box.find( '.crc-overview-box-detail-template' ).html() || '';
	var extraTemplate = $box.find( '.crc-overview-box-extra-template' ).html() || '';

	// New groups and details get numbers no saved one has.
	var next = Date.now();

	if ( ! $box.length ) {
		return;
	}

	function fill( template, group, detail ) {
		return template.split( '{g}' ).join( group ).split( '{d}' ).join( detail );
	}

	// Details move within their own group only.
	function sortDetails( $list ) {
		$list.sortable( {
			items: '> .crc-overview-box-detail',
			handle: '.crc-overview-box-detail-handle',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'crc-overview-box-detail-placeholder'
		} );
	}

	$groups.sortable( {
		items: '> .crc-overview-box-group',
		handle: '.crc-overview-box-group-handle',
		axis: 'y',
		tolerance: 'pointer',
		placeholder: 'crc-overview-box-group-placeholder',
		forcePlaceholderSize: true
	} );

	// The details of the listing's own groups and those added to ready-made groups.
	$box.find( '.crc-overview-box-details' ).each( function () {
		sortDetails( $( this ) );
	} );

	$box.on( 'click', '.crc-overview-box-group-add', function ( event ) {
		var $group = $( $.parseHTML( $.trim( fill( groupTemplate, next++, next++ ) ) ) );

		event.preventDefault();
		$groups.append( $group );
		sortDetails( $group.find( '.crc-overview-box-details' ) );
		$group.find( '.crc-overview-box-group-title' ).trigger( 'focus' );
	} );

	$box.on( 'click', '.crc-overview-box-detail-add', function ( event ) {
		var $group = $( this ).closest( '.crc-overview-box-group' );
		var $detail = $( $.parseHTML( $.trim( fill( detailTemplate, $group.attr( 'data-group' ), next++ ) ) ) );

		event.preventDefault();
		$group.find( '.crc-overview-box-details' ).append( $detail );
		$detail.find( 'input' ).first().trigger( 'focus' );
	} );

	// A detail of the listing's own in a ready-made group.
	$box.on( 'click', '.crc-overview-box-extra-add', function ( event ) {
		var $list = $( this ).closest( '.crc-overview-box-section' ).find( '.crc-overview-box-extras' );
		var $detail = $( $.parseHTML( $.trim( fill( extraTemplate, $list.attr( 'data-group' ), next++ ) ) ) );

		event.preventDefault();
		$list.append( $detail );
		$detail.find( 'input' ).first().trigger( 'focus' );
	} );

	$box.on( 'click', '.crc-overview-box-detail-remove', function ( event ) {
		event.preventDefault();
		$( this ).closest( '.crc-overview-box-detail' ).remove();
	} );

	/*
	 * The ready-made groups follow the category chosen in the Category box.
	 * Groups for other categories are switched off, so they aren't saved.
	 */
	function showCategory() {
		var chosen = String( $( '#crc_listing_categorydiv input[type="radio"]:checked' ).val() || '' );
		var hasCategory = '' !== chosen && '0' !== chosen;

		$box.find( '.crc-overview-box-section' ).each( function () {
			var categories = String( $( this ).attr( 'data-categories' ) || '' );
			var on = 'all' === categories || ( hasCategory && -1 !== $.inArray( chosen, categories.split( ' ' ) ) );

			$( this ).prop( 'hidden', ! on ).find( ':input' ).prop( 'disabled', ! on );
		} );

		$box.find( '.crc-overview-box-no-category' ).prop( 'hidden', hasCategory );
	}

	$( '#crc_listing_categorydiv' ).on( 'change', 'input[type="radio"]', showCategory );
	showCategory();

	// A detail in more than one group, such as Land extent, keeps one value.
	$box.on( 'input change', '.crc-overview-box-section :input', function () {
		var source = this;

		$box.find( '.crc-overview-box-section :input' ).filter( function () {
			return this !== source && this.name && this.name === source.name;
		} ).val( $( source ).val() );
	} );

	// Locked fields that come from the Price box follow it as it is typed.
	function follow( field, linked ) {
		$( field ).on( 'input', function () {
			var number = parseInt( String( $( this ).val() ).replace( /[,\s]/g, '' ).split( '.' )[ 0 ], 10 );

			$box.find( '[data-crc-linked="' + linked + '"]' ).val( number > 0 ? number.toLocaleString( 'en-US' ) : '' );
		} );
	}

	follow( '#crc-per-perch-field', 'price_per_perch' );
	follow( '#crc-price-field', 'price' );
	follow( '#crc-price-field', 'rent' );

	$box.on( 'click', '.crc-overview-box-group-remove', function ( event ) {
		var $group = $( this ).closest( '.crc-overview-box-group' );
		var filled = $group.find( 'input' ).filter( function () {
			return '' !== $.trim( this.value );
		} ).length;

		event.preventDefault();

		if ( filled && ! window.confirm( text.confirmRemove || 'Remove this group and all its details?' ) ) {
			return;
		}

		$group.remove();
	} );
} );
