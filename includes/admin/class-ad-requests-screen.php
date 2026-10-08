<?php
/**
 * Free Ads screens.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Ad_Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Free Ads, a menu of its own: every ad sent with the post a free ad form,
 * with who sent it and what it is, and each ad in full with buttons to
 * reply. The menu shows how many are new, and new ones are marked New until
 * they are opened.
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
		}
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
		$title = '' !== $ad['title'] ? $ad['title'] : Ad_Requests::name( $ad );

		self::reply_buttons(
			$post,
			$ad,
			/* translators: %s: the ad's title. */
			sprintf( __( 'Your free ad: %s', 'crc-real-estate' ), html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ),
			__( 'Ask for the photos when you reply. This ad isn\'t on the website: when the details are checked, add it as a new listing in Listings.', 'crc-real-estate' )
		);
	}
}
