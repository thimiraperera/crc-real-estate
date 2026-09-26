/**
 * Listing edit screen: the Gallery box and the required featured image.
 */
jQuery( function ( $ ) {
	'use strict';

	var text = window.crcReListing || {};
	var $box = $( '.crc-gallery-admin' );
	var $list = $box.find( '.crc-gallery-admin__list' );
	var $ids = $box.find( '.crc-gallery-admin__ids' );
	var frame;

	function currentIds() {
		return $list.children().map( function () {
			return String( $( this ).data( 'id' ) );
		} ).get();
	}

	function sync() {
		var ids = currentIds();

		$ids.val( ids.join( ',' ) );
		$box.toggleClass( 'has-images', ids.length > 0 );
	}

	function item( id, url ) {
		var $li = $( '<li class="crc-gallery-admin__item"></li>' ).attr( 'data-id', id );
		var $remove = $( '<button type="button" class="crc-gallery-admin__remove"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' )
			.attr( 'aria-label', text.remove || 'Remove image' );

		$li.append( $( '<img alt="">' ).attr( 'src', url ) ).append( $remove );

		return $li;
	}

	if ( $box.length ) {
		$list.sortable( {
			items: '> li',
			cursor: 'move',
			tolerance: 'pointer',
			placeholder: 'crc-gallery-admin__placeholder',
			update: sync
		} );

		$box.on( 'click', '.crc-gallery-admin__remove', function ( event ) {
			event.preventDefault();
			$( this ).closest( 'li' ).remove();
			sync();
		} );

		$box.on( 'click', '.crc-gallery-admin__add', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: text.frameTitle || 'Add gallery images',
					button: { text: text.frameButton || 'Add to gallery' },
					library: { type: 'image' },
					multiple: 'add'
				} );

				frame.on( 'select', function () {
					var ids = currentIds();

					frame.state().get( 'selection' ).each( function ( attachment ) {
						var data = attachment.toJSON();
						var id = String( data.id );
						var sizes = data.sizes || {};
						var url = ( sizes.thumbnail || sizes.medium || data ).url;

						if ( -1 === ids.indexOf( id ) ) {
							ids.push( id );
							$list.append( item( id, url ) );
						}
					} );

					sync();
				} );
			}

			frame.open();
		} );
	}

	/*
	 * Publishing (or updating a published listing) needs a featured image,
	 * which is the large photo, and a category. Saving a draft doesn't.
	 */
	var $featuredBox = $( '#postimagediv' );
	var $categoryBox = $( '#crc_listing_categorydiv' );

	function hasFeaturedImage() {
		return parseInt( $( '#_thumbnail_id' ).val(), 10 ) > 0;
	}

	function hasCategory() {
		return parseInt( $categoryBox.find( 'input[type="radio"]:checked' ).val(), 10 ) > 0;
	}

	function clearError( $box ) {
		$box.removeClass( 'crc-box-missing' ).find( '.crc-box-error' ).remove();
	}

	function showError( $box, message ) {
		clearError( $box );
		$box.addClass( 'crc-box-missing' ).removeClass( 'closed' );
		$box.find( '.inside' ).prepend( $( '<p class="crc-box-error" role="alert"></p>' ).text( message ) );
	}

	$( '#publish' ).on( 'click', function ( event ) {
		var missing = [];

		if ( $featuredBox.length && ! hasFeaturedImage() ) {
			showError( $featuredBox, text.featuredRequired || 'Add a featured image to publish this listing.' );
			missing.push( $featuredBox );
		}

		if ( $categoryBox.length && ! hasCategory() ) {
			showError( $categoryBox, text.categoryRequired || 'Choose a category to publish this listing.' );
			missing.push( $categoryBox );
		}

		if ( ! missing.length ) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();
		$( 'html, body' ).animate( { scrollTop: Math.max( missing[ 0 ].offset().top - 60, 0 ) }, 200 );
	} );

	$categoryBox.on( 'change', 'input[type="radio"]', function () {
		clearError( $categoryBox );
	} );

	// WordPress redraws the Featured image box after an image is chosen.
	if ( window.MutationObserver && $featuredBox.length ) {
		new window.MutationObserver( function () {
			if ( hasFeaturedImage() ) {
				clearError( $featuredBox );
			}
		} ).observe( $featuredBox[ 0 ], { childList: true, subtree: true } );
	}
} );
