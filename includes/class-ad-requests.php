<?php
/**
 * Free ads sent with the post a free ad form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * Emails each free ad and keeps it in Free Ads, its own menu in wp-admin, so
 * none is lost when an email doesn't arrive. A new ad counts as new until it
 * is opened. Nothing is published by itself: the site's team collects the
 * photos, checks the details and adds the listing.
 */
final class Ad_Requests extends Submissions {

	const NAME     = 'crc_ad_request';
	const NEW_META = '_crc_ad_new';

	/**
	 * Details kept with each ad: key => field name.
	 */
	const META = array(
		'name'          => '_crc_ad_name',
		'phone'         => '_crc_ad_phone',
		'country'       => '_crc_ad_country',
		'email'         => '_crc_ad_email',
		'category'      => '_crc_ad_category',
		'property_type' => '_crc_ad_property_type',
		'location'      => '_crc_ad_location',
		'price'         => '_crc_ad_price',
		'land_size'     => '_crc_ad_land_size',
		'bedrooms'      => '_crc_ad_bedrooms',
		'title'         => '_crc_ad_title',
		'description'   => '_crc_ad_description',
		'page'          => '_crc_ad_page',
		'note'          => '_crc_ad_note',
		'mailed'        => '_crc_ad_mailed',
	);

	/**
	 * The Free Ads menu's icon: a house with a plus. WordPress colours it to
	 * match the admin menu.
	 *
	 * @return string
	 */
	public static function icon() {
		return self::svg_icon( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M10 1.5 19 9.4h-2.5V18h-13V9.4H1zM9.2 9.6v2.3H6.9v1.6h2.3v2.3h1.6v-2.3h2.3v-1.6h-2.3V9.6z"/></svg>' );
	}

	/**
	 * The names WordPress shows for free ads.
	 *
	 * @return string[]
	 */
	protected static function labels() {
		return array(
			'name'               => __( 'Free Ads', 'crc-real-estate' ),
			'singular_name'      => __( 'Free Ad', 'crc-real-estate' ),
			'menu_name'          => __( 'Free Ads', 'crc-real-estate' ),
			'all_items'          => __( 'All Free Ads', 'crc-real-estate' ),
			'edit_item'          => __( 'Free Ad', 'crc-real-estate' ),
			'view_item'          => __( 'View free ad', 'crc-real-estate' ),
			'search_items'       => __( 'Search free ads', 'crc-real-estate' ),
			'not_found'          => __( 'No free ads yet. Ads sent with the post a free ad form show here.', 'crc-real-estate' ),
			'not_found_in_trash' => __( 'No free ads in the Trash.', 'crc-real-estate' ),
			'item_updated'       => __( 'Free ad updated.', 'crc-real-estate' ),
		);
	}

	/**
	 * Its name in the list in wp-admin: the ad's title.
	 *
	 * @param array $ad Details.
	 * @return string
	 */
	protected static function title( array $ad ) {
		return isset( $ad['title'] ) && '' !== (string) $ad['title'] ? (string) $ad['title'] : self::name( $ad );
	}

	/**
	 * Its text, which the list's search also looks through: the description,
	 * then who sent it, the property type and where it is, so the list can be
	 * searched by name or town too. The screens show the details on their
	 * own, so this text isn't shown anywhere.
	 *
	 * @param array $ad Details.
	 * @return string
	 */
	protected static function content( array $ad ) {
		$parts = array();

		foreach ( array( 'description', 'name', 'property_type', 'location' ) as $key ) {
			if ( isset( $ad[ $key ] ) && '' !== trim( (string) $ad[ $key ] ) ) {
				$parts[] = trim( (string) $ad[ $key ] );
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * The name of an ad's category.
	 *
	 * @param string $slug Category, e.g. "lands".
	 * @return string
	 */
	public static function category( $slug ) {
		$terms = Taxonomy::terms();

		return isset( $terms[ $slug ]['name'] ) ? (string) $terms[ $slug ]['name'] : (string) $slug;
	}

	/**
	 * An ad's price written out, e.g. "Rs. 25,000,000", or an empty string.
	 *
	 * @param string $digits Price as digits.
	 * @return string
	 */
	public static function price( $digits ) {
		$digits = Price_Card::sanitize_amount( $digits );

		return '' !== $digits ? Price_Card::money( $digits ) : '';
	}

	/**
	 * The ad's details after the person: label => value, in the form's order.
	 *
	 * @param array $ad Details.
	 * @return string[]
	 */
	public static function property( array $ad ) {
		return array(
			__( 'Category', 'crc-real-estate' )         => self::category( $ad['category'] ),
			__( 'Property type', 'crc-real-estate' )    => (string) $ad['property_type'],
			__( 'District or town', 'crc-real-estate' ) => (string) $ad['location'],
			__( 'Price', 'crc-real-estate' )            => self::price( $ad['price'] ),
			__( 'Land size', 'crc-real-estate' )        => (string) $ad['land_size'],
			__( 'Bedrooms', 'crc-real-estate' )         => (string) $ad['bedrooms'],
			__( 'Ad title', 'crc-real-estate' )         => (string) $ad['title'],
		);
	}

	/**
	 * Emails an ad to the addresses in Settings. Replying answers the person
	 * who sent it.
	 *
	 * @param array $ad      Checked ad: the keys of META except "mailed".
	 * @param int   $post_id Kept ad's ID, for a link to it; 0 when it wasn't kept.
	 * @return bool Whether the email was handed over for sending.
	 */
	public static function mail( array $ad, $post_id = 0 ) {
		$who   = self::greeting( $ad );
		$title = self::plain( $ad['title'] );
		/* translators: %s: the name of the person who sent the ad. */
		$intro = __( '%s would like to post a free ad on your website. Contact them to collect the photos and check the details.', 'crc-real-estate' );
		$rows  = self::person_rows( $ad );

		foreach ( self::property( $ad ) as $label => $value ) {
			if ( '' !== $value ) {
				$rows[] = array( $label, esc_html( $value ), $value );
			}
		}

		$rows[] = self::text_row( __( 'Description', 'crc-real-estate' ), $ad['description'], __( 'No description.', 'crc-real-estate' ) );

		return self::send(
			/* translators: %s: the ad's title. */
			sprintf( __( 'New free ad: %s', 'crc-real-estate' ), '' !== $title ? $title : self::name( $ad ) ),
			sprintf( esc_html( $intro ), esc_html( $who ) ),
			sprintf( $intro, $who ),
			array_merge( $rows, self::end_rows( $ad ) ),
			$ad,
			$post_id,
			__( 'This ad is also kept in wp-admin under Free Ads.', 'crc-real-estate' )
		);
	}
}
