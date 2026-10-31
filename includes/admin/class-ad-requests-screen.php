<?php
/**
 * Free Ads screens.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Ad_Photos;
use CRC\RealEstate\Ad_Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Free Ads, a menu of its own: every ad sent with the post a free ad form,
 * with who sent it, what it is and its first photo, and each ad in full with
 * its photos to open or download, and buttons to reply. The menu shows how
 * many are new, and new ones are marked New until they are opened.
 */
final class Ad_Requests_Screen extends Submissions_Screen {

	const STORE = Ad_Requests::class;
	const BOX   = 'crc-ad-request';

	/**
	 * The title of the ad box.
	 *
	 * @return string
	 */
	protected static function box_title() {
		return __( 'Free Ad', 'crc-real-estate' );
	}

	/**
	 * The number of new ads, for screen readers.
	 *
	 * @param int $count Number of new ads.
	 * @return string
	 */
	protected static function badge_text( $count ) {
		/* translators: %s: number of new free ads. */
		return sprintf( _n( '%s new free ad', '%s new free ads', $count, 'crc-real-estate' ), number_format_i18n( $count ) );
	}

	/**
	 * The list's columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		return array(
			'cb'           => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">',
			'title'        => __( 'Ad', 'crc-real-estate' ),
			'crc_name'     => __( 'Name', 'crc-real-estate' ),
			'crc_phone'    => __( 'Phone', 'crc-real-estate' ),
			'crc_property' => __( 'Property', 'crc-real-estate' ),
			'crc_price'    => __( 'Price', 'crc-real-estate' ),
			'crc_photos'   => __( 'Photos', 'crc-real-estate' ),
			'date'         => __( 'Received', 'crc-real-estate' ),
		);
	}

	/**
	 * Prints a column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Ad ID.
	 */
	public function column( $column, $post_id ) {
		$ad = Ad_Requests::get( $post_id );

		switch ( $column ) {
			case 'crc_name':
				echo esc_html( Ad_Requests::name( $ad ) );
				break;

			case 'crc_phone':
				echo self::phone_link( $ad ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in phone_link().
				break;

			case 'crc_property':
				$place = implode( ' · ', array_filter( array( $ad['property_type'], $ad['location'] ), 'strlen' ) );

				echo esc_html( Ad_Requests::category( $ad['category'] ) );

				if ( '' !== $place ) {
					echo '<br><span class="description">' . esc_html( $place ) . '</span>';
				}
				break;

			case 'crc_price':
				$price = Ad_Requests::price( $ad['price'] );

				echo '' !== $price ? esc_html( $price ) : '<span class="description">' . esc_html__( 'Not given', 'crc-real-estate' ) . '</span>';
				break;

			case 'crc_photos':
				$photos = Ad_Photos::of( $post_id );

				if ( ! $photos ) {
					echo '<span class="description">' . esc_html__( 'None', 'crc-real-estate' ) . '</span>';
					break;
				}

				printf(
					'<a class="crc-ad-photo-thumb" href="%1$s"><img src="%2$s" alt="" loading="lazy" width="48" height="48"><span>%3$s</span></a>',
					esc_url( get_edit_post_link( $post_id ) ),
					esc_url( Ad_Photos::url( $post_id, 0 ) ),
					/* translators: %s: number of photos. */
					esc_html( sprintf( _n( '%s photo', '%s photos', count( $photos ), 'crc-real-estate' ), number_format_i18n( count( $photos ) ) ) )
				);
				break;
		}
	}

	/**
	 * The screen of one ad: everything that was sent, its photos, and
	 * buttons to reply.
	 */
	public function boxes() {
		parent::boxes();

		add_meta_box( self::BOX . '-photos', __( 'Photos', 'crc-real-estate' ), array( $this, 'photos' ), Ad_Requests::NAME, 'normal', 'high' );
	}

	/**
	 * Prints the ad's photos, each to open full size or download, and a
	 * button to download them all.
	 *
	 * @param \WP_Post $post Ad.
	 */
	public function photos( $post ) {
		$photos = Ad_Photos::of( $post->ID );

		if ( ! $photos ) {
			echo '<p class="description">' . esc_html__( 'No photos were sent with this ad. Ask for them when you call.', 'crc-real-estate' ) . '</p>';
			return;
		}

		printf(
			'<p class="crc-ad-photos-actions"><a class="button" href="%1$s">%2$s</a> <span class="description">%3$s</span></p>',
			esc_url( Ad_Photos::zip_url( $post->ID ) ),
			/* translators: %s: number of photos. */
			esc_html( sprintf( _n( 'Download the photo', 'Download all %s photos', count( $photos ), 'crc-real-estate' ), number_format_i18n( count( $photos ) ) ) ),
			esc_html__( 'As a zip file, ready to add to the listing.', 'crc-real-estate' )
		);

		echo '<ul class="crc-ad-photos-grid">';

		foreach ( $photos as $n => $photo ) {
			printf(
				'<li><a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" loading="lazy"></a><span class="crc-ad-photos-meta">%3$s · <a href="%4$s">%5$s</a></span></li>',
				esc_url( Ad_Photos::url( $post->ID, $n ) ),
				/* translators: %s: number of the photo. */
				esc_attr( sprintf( __( 'Photo %s', 'crc-real-estate' ), number_format_i18n( $n + 1 ) ) ),
				/* translators: 1: width in pixels, 2: height in pixels, 3: file size, e.g. 312 KB. */
				esc_html( sprintf( __( '%1$s × %2$s, %3$s', 'crc-real-estate' ), number_format_i18n( $photo['width'] ), number_format_i18n( $photo['height'] ), size_format( $photo['bytes'] ) ) ),
				esc_url( Ad_Photos::url( $post->ID, $n, true ) ),
				esc_html__( 'Download', 'crc-real-estate' )
			);
		}

		echo '</ul>';
	}

	/**
	 * Prints the ad.
	 *
	 * @param \WP_Post $post Ad.
	 */
	public function details( $post ) {
		$ad   = Ad_Requests::get( $post->ID );
		$rows = self::person_rows( $ad );

		foreach ( Ad_Requests::property( $ad ) as $label => $value ) {
			$rows[ $label ] = '' !== $value ? esc_html( $value ) : '<span class="description">' . esc_html__( 'Not given.', 'crc-real-estate' ) . '</span>';
		}

		$rows[ __( 'Description', 'crc-real-estate' ) ] = self::text_cell( $ad['description'], __( 'No description.', 'crc-real-estate' ) );

		self::table( array_merge( $rows, self::end_rows( $ad, $post, __( 'The email about this ad couldn\'t be sent, so it is only here. If this keeps happening, ask your host to check the site\'s email, or add an SMTP plugin that sends email through your email account.', 'crc-real-estate' ) ) ) );
	}

	/**
	 * Prints the Reply buttons: email, call, WhatsApp, and Move to Trash,
	 * with a reminder of what comes next.
	 *
	 * @param \WP_Post $post Ad.
	 */
	public function reply( $post ) {
		$ad    = Ad_Requests::get( $post->ID );
		$title = '' !== (string) $post->post_title ? (string) $post->post_title : Ad_Requests::name( $ad );

		self::reply_buttons(
			$post,
			$ad,
			/* translators: %s: the ad's title. */
			sprintf( __( 'Your free ad: %s', 'crc-real-estate' ), html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ),
			__( 'Call to talk about the property and check the details. This ad isn\'t on the website: when it is ready, add it as a new listing in Listings, with the photos from this ad.', 'crc-real-estate' )
		);
	}
}
