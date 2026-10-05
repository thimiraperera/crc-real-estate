/**
 * Widgets page: the lists on its tabs, such as the category carousel's cards
 * and the FAQs. Adding, removing and ordering rows, and choosing the cards'
 * pictures. Each list says how it is saved in data attributes.
 */
jQuery( function ( $ ) {
	'use strict';

	var text = window.crcWidgets || {};
	var frame;
	var $target;

	function format( value, number ) {
		return String( value || '' ).replace( '%s', String( number ) );
	}

	function setUp( $editor ) {
		var $list = $editor.find( '[data-crc-items]' ).first();
		var template = $editor.find( 'template[data-crc-template]' ).get( 0 );
		var option = String( $editor.attr( 'data-option' ) || '' );
		var key = String( $editor.attr( 'data-key' ) || 'items' );
		var prefix = String( $editor.attr( 'data-prefix' ) || '' );
		var max = parseInt( $editor.attr( 'data-max' ), 10 ) || 50;
		var $add = $editor.find( '[data-crc-add]' );
		var $note = $editor.find( '[data-crc-note]' );

		if ( ! $list.length || ! template || ! option ) {
			return;
		}

		function rows() {
			return $list.children( '[data-crc-row]' );
		}

		// Gives every row's fields names that follow the order on the page.
		function renumber() {
			var $rows = rows();

			$rows.each( function ( index ) {
				var $row = $( this );

				$row.find( '[data-name]' ).each( function () {
					var field = this.getAttribute( 'data-name' );

					this.name = option + '[' + key + '][' + index + '][' + field + ']';

					if ( this.id ) {
						$row.find( 'label[for="' + this.id + '"]' ).attr( 'for', prefix + index + '-' + field );
						this.id = prefix + index + '-' + field;
					}
				} );

				$row.find( '[data-crc-up]' ).prop( 'disabled', 0 === index );
				$row.find( '[data-crc-down]' ).prop( 'disabled', index === $rows.length - 1 );
			} );

			$editor.find( '[data-crc-empty]' ).prop( 'hidden', $rows.length > 0 );
			$add.prop( 'disabled', $rows.length >= max );
			$note.text( $rows.length >= max ? format( $editor.attr( 'data-full' ), max ) : '' );
		}

		function isEmpty( $row ) {
			return ! $row.find( 'input, textarea' ).filter( function () {
				return '' !== $.trim( this.value );
			} ).length;
		}

		$list.sortable( {
			items: '> [data-crc-row]',
			handle: '[data-crc-handle]',
			cursor: 'move',
			axis: 'y',
			placeholder: 'crc-cards-placeholder',
			// The gap left behind is as tall as the row, which is taller on phones.
			start: function ( event, ui ) {
				ui.placeholder.outerHeight( ui.item.outerHeight() );
			},
			update: renumber
		} );

		$add.on( 'click', function () {
			var $row = $( template.content.cloneNode( true ).querySelector( '[data-crc-row]' ) );

			if ( rows().length >= max ) {
				return;
			}

			$list.append( $row );
			renumber();
			$row.find( '[data-crc-focus]' ).first().trigger( 'focus' );
		} );

		$list.on( 'click', '[data-crc-remove]', function () {
			var $row = $( this ).closest( '[data-crc-row]' );

			if ( ! isEmpty( $row ) && ! window.confirm( $editor.attr( 'data-confirm' ) ) ) {
				return;
			}

			$row.remove();
			renumber();
			$add.trigger( 'focus' );
		} );

		$list.on( 'click', '[data-crc-up], [data-crc-down]', function () {
			var $button = $( this );
			var $row = $button.closest( '[data-crc-row]' );
			var up = undefined !== $button.attr( 'data-crc-up' );
			var $other = up ? $row.prev( '[data-crc-row]' ) : $row.next( '[data-crc-row]' );

			if ( ! $other.length ) {
				return;
			}

			if ( up ) {
				$row.insertBefore( $other );
			} else {
				$row.insertAfter( $other );
			}

			renumber();

			// At the top or bottom the button turns off, so keep the keyboard on the row.
			$( $button.prop( 'disabled' ) ? $row.find( up ? '[data-crc-down]' : '[data-crc-up]' ) : $button ).trigger( 'focus' );
		} );

		renumber();
	}

	$( '[data-crc-list]' ).each( function () {
		setUp( $( this ) );
	} );

	// The carousel's cards: choosing a picture.
	function setPicture( $row, id, url ) {
		var label = url ? text.change : text.choose;

		$row.find( '.crc-cards-row-image' ).val( id ? String( id ) : '' );
		$row.find( '.crc-cards-row-choose img' ).attr( 'src', url || '' ).prop( 'hidden', ! url );
		$row.find( '.crc-cards-row-choose .dashicons' ).prop( 'hidden', !! url );
		$row.find( '.crc-cards-row-choose' ).attr( { 'aria-label': label, title: label } );
	}

	$( document ).on( 'click', '.crc-cards-row-choose', function () {
		$target = $( this ).closest( '[data-crc-row]' );

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
} );
