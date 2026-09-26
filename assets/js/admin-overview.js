/**
 * Property overview box: add, remove and reorder the See More popup's groups
 * and details.
 */
jQuery( function ( $ ) {
	'use strict';

	var text = window.crcReOverview || {};
	var $box = $( '.crc-overview-box' );
	var $groups = $box.find( '.crc-overview-box-groups' );
	var groupTemplate = $box.find( '.crc-overview-box-group-template' ).html() || '';
	var detailTemplate = $box.find( '.crc-overview-box-detail-template' ).html() || '';

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

	$groups.find( '.crc-overview-box-details' ).each( function () {
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

	$box.on( 'click', '.crc-overview-box-detail-remove', function ( event ) {
		event.preventDefault();
		$( this ).closest( '.crc-overview-box-detail' ).remove();
	} );

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
