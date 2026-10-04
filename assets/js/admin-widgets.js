/**
 * Widgets page: the category carousel's cards. Adding, removing and ordering
 * cards, and choosing their pictures.
 */
jQuery( function ( $ ) {
	'use strict';

	var text = window.crcWidgets || {};
	var $list = $( '.crc-cards-list' );
	var template = document.getElementById( 'crc-cards-template' );
	var $note = $( '.crc-cards-note' );
	var max = parseInt( text.max, 10 ) || 50;
	var frame;
	var $target;

	if ( ! $list.length || ! template ) {
		return;
	}

	// Gives every card's fields names that follow the order on the page.
	function renumber() {
		var $rows = $list.children( '.crc-cards-row' );

		$rows.each( function ( index ) {
			var $row = $( this );

			$row.find( '[data-name]' ).each( function () {
				var field = this.getAttribute( 'data-name' );

				this.name = text.option + '[cards][' + index + '][' + field + ']';

				if ( this.id ) {
					$row.find( 'label[for="' + this.id + '"]' ).attr( 'for', 'crc-card-' + index + '-' + field );
					this.id = 'crc-card-' + index + '-' + field;
				}
			} );

			$row.find( '.crc-cards-row-up' ).prop( 'disabled', 0 === index );
			$row.find( '.crc-cards-row-down' ).prop( 'disabled', index === $rows.length - 1 );
		} );

		$( '.crc-cards-empty' ).prop( 'hidden', $rows.length > 0 );
		$( '.crc-cards-add' ).prop( 'disabled', $rows.length >= max );
		$note.text( $rows.length >= max ? String( text.full || '' ).replace( '%s', String( max ) ) : '' );
	}

	function setPicture( $row, id, url ) {
		var label = url ? text.change : text.choose;

		$row.find( '.crc-cards-row-image' ).val( id ? String( id ) : '' );
		$row.find( '.crc-cards-row-choose img' ).attr( 'src', url || '' ).prop( 'hidden', ! url );
		$row.find( '.crc-cards-row-choose .dashicons' ).prop( 'hidden', !! url );
		$row.find( '.crc-cards-row-choose' ).attr( { 'aria-label': label, title: label } );
	}

	function isEmpty( $row ) {
		return ! $row.find( 'input' ).filter( function () {
			return '' !== $.trim( this.value );
		} ).length;
	}

	$list.sortable( {
		items: '> .crc-cards-row',
		handle: '.crc-cards-row-handle',
		cursor: 'move',
		axis: 'y',
		placeholder: 'crc-cards-placeholder',
		// The gap left behind is as tall as the card, which is taller on phones.
		start: function ( event, ui ) {
			ui.placeholder.outerHeight( ui.item.outerHeight() );
		},
		update: renumber
	} );

	$( '.crc-cards-add' ).on( 'click', function () {
		var $row = $( template.content.cloneNode( true ).querySelector( '.crc-cards-row' ) );

		if ( $list.children( '.crc-cards-row' ).length >= max ) {
			return;
		}

		$list.append( $row );
		renumber();
		$row.find( '.crc-cards-row-title' ).trigger( 'focus' );
	} );

	$list.on( 'click', '.crc-cards-row-remove', function () {
		var $row = $( this ).closest( '.crc-cards-row' );

		if ( ! isEmpty( $row ) && ! window.confirm( text.remove ) ) {
			return;
		}

		$row.remove();
		renumber();
		$( '.crc-cards-add' ).trigger( 'focus' );
	} );

	$list.on( 'click', '.crc-cards-row-up, .crc-cards-row-down', function () {
		var $button = $( this );
		var $row = $button.closest( '.crc-cards-row' );
		var up = $button.hasClass( 'crc-cards-row-up' );
		var $other = up ? $row.prev( '.crc-cards-row' ) : $row.next( '.crc-cards-row' );

		if ( ! $other.length ) {
			return;
		}

		if ( up ) {
			$row.insertBefore( $other );
		} else {
			$row.insertAfter( $other );
		}

		renumber();

		// At the top or bottom the button turns off, so keep the keyboard on the card.
		$( $button.prop( 'disabled' ) ? $row.find( up ? '.crc-cards-row-down' : '.crc-cards-row-up' ) : $button ).trigger( 'focus' );
	} );

	$list.on( 'click', '.crc-cards-row-choose', function () {
		$target = $( this ).closest( '.crc-cards-row' );

		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		if ( ! frame ) {
			frame = window.wp.media( {
				title: text.frameTitle || 'Choose a picture',
				button: { text: text.frameButton || 'Use this picture' },
				library: { type: 'image' },
				multiple: false
			} );

			// Opens on the card's own picture, or with nothing picked.
			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );
				var id = $target ? parseInt( $target.find( '.crc-cards-row-image' ).val(), 10 ) : 0;
				var attachment = id ? window.wp.media.attachment( id ) : null;

				if ( attachment ) {
					attachment.fetch();
				}

				selection.reset( attachment ? [ attachment ] : [] );
			} );

			frame.on( 'select', function () {
				var data = frame.state().get( 'selection' ).first().toJSON();
				var sizes = data.sizes || {};

				if ( $target ) {
					setPicture( $target, data.id, ( sizes.thumbnail || sizes.medium || data ).url );
					$target.find( '.crc-cards-row-choose' ).trigger( 'focus' );
				}
			} );
		}

		frame.open();
	} );

	renumber();
} );
