<?php
/**
 * Inquiries sent with the inquiry form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Emails each inquiry and keeps it in Inquiries, its own menu in wp-admin,
 * so none is lost when an email doesn't arrive. A new inquiry counts as new
 * until it is opened.
 */
final class Inquiries extends Submissions {

	const NAME     = 'crc_inquiry';
	const NEW_META = '_crc_inquiry_new';

	/**
	 * Details kept with each inquiry: key => field name.
	 */
	const META = array(
		'first_name' => '_crc_inquiry_first_name',
		'last_name'  => '_crc_inquiry_last_name',
		'phone'      => '_crc_inquiry_phone',
		'country'    => '_crc_inquiry_country',
		'email'      => '_crc_inquiry_email',
		'message'    => '_crc_inquiry_message',
		'listing'    => '_crc_inquiry_listing',
		'page'       => '_crc_inquiry_page',
		'note'       => '_crc_inquiry_note',
		'mailed'     => '_crc_inquiry_mailed',
	);

	/**
	 * The Inquiries menu's icon: a speech bubble with a house in it. WordPress
	 * colours it to match the admin menu.
	 *
	 * @return string
	 */
	public static function icon() {
		return self::svg_icon( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M4 2h12a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H9.5L5 18.5V15H4a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3zM10 4.6 5.8 8.2H7V12h2.2V9.8h1.6V12H13V8.2h1.2z"/></svg>' );
	}

	/**
	 * The names WordPress shows for inquiries.
	 *
	 * @return string[]
	 */
	protected static function labels() {
		return array(
			'name'               => __( 'Inquiries', 'crc-real-estate' ),
			'singular_name'      => __( 'Inquiry', 'crc-real-estate' ),
			'menu_name'          => __( 'Inquiries', 'crc-real-estate' ),
			'all_items'          => __( 'All Inquiries', 'crc-real-estate' ),
			'edit_item'          => __( 'Inquiry', 'crc-real-estate' ),
			'view_item'          => __( 'View inquiry', 'crc-real-estate' ),
			'search_items'       => __( 'Search inquiries', 'crc-real-estate' ),
			'not_found'          => __( 'No inquiries yet. Inquiries sent with the inquiry form show here.', 'crc-real-estate' ),
			'not_found_in_trash' => __( 'No inquiries in the Trash.', 'crc-real-estate' ),
			'item_updated'       => __( 'Inquiry updated.', 'crc-real-estate' ),
		);
	}

	/**
	 * "No listing" isn't kept.
	 *
	 * @param string $key   Detail key.
	 * @param string $value Its value.
	 * @return bool
	 */
	protected static function keep( $key, $value ) {
		return ! ( 'listing' === $key && '0' === $value );
	}

	/**
	 * A kept inquiry's details.
	 *
	 * @param int $post_id Inquiry ID.
	 * @return array The keys of META.
	 */
	public static function get( $post_id ) {
		$inquiry            = parent::get( $post_id );
		$inquiry['listing'] = (int) $inquiry['listing'];

		return $inquiry;
	}

	/**
	 * The listing an inquiry is about, if it still exists.
	 *
	 * @param array $inquiry Inquiry.
	 * @return \WP_Post|null
	 */
	public static function listing( array $inquiry ) {
		$post = empty( $inquiry['listing'] ) ? null : get_post( (int) $inquiry['listing'] );

		return $post && Post_Type::NAME === $post->post_type ? $post : null;
	}

	/**
	 * Emails an inquiry to the addresses in Settings. Replying answers the
	 * person who asked.
	 *
	 * @param array $inquiry Checked inquiry: the keys of META except "mailed".
	 * @param int   $post_id Kept inquiry's ID, for a link to it; 0 when it wasn't kept.
	 * @return bool Whether the email was handed over for sending.
	 */
	public static function mail( array $inquiry, $post_id = 0 ) {
		$listing = self::listing( $inquiry );
		$first   = (string) $inquiry['first_name'];
		$title   = $listing ? self::plain( get_the_title( $listing ) ) : '';
		$subject = $listing
			/* translators: %s: listing title. */
			? sprintf( __( 'New inquiry: %s', 'crc-real-estate' ), $title )
			/* translators: %s: site name. */
			: sprintf( __( 'New inquiry from %s', 'crc-real-estate' ), self::plain( get_option( 'blogname' ) ) );

		$intro = $listing
			/* translators: 1: first name, 2: listing title. */
			? __( '%1$s sent an inquiry about %2$s.', 'crc-real-estate' )
			/* translators: 1: first name. */
			: __( '%1$s sent an inquiry from your website.', 'crc-real-estate' );

		return self::send(
			$subject,
			sprintf( esc_html( $intro ), esc_html( $first ), $listing ? '<a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( $title ) . '</a>' : '' ),
			sprintf( $intro, $first, $title ),
			self::rows( $inquiry, $listing ),
			$inquiry,
			$post_id,
			__( 'This inquiry is also kept in wp-admin under Inquiries.', 'crc-real-estate' )
		);
	}

	/**
	 * The details in an inquiry email.
	 *
	 * @param array         $inquiry Inquiry.
	 * @param \WP_Post|null $listing The listing it's about.
	 * @return array[] Rows of array( label, HTML, plain text ).
	 */
	private static function rows( array $inquiry, $listing ) {
		$rows = self::person_rows( $inquiry );

		if ( $listing ) {
			$title  = self::plain( get_the_title( $listing ) );
			$rows[] = array( __( 'Listing', 'crc-real-estate' ), self::link( get_permalink( $listing ), $title ), $title . ' - ' . get_permalink( $listing ) );
		}

		$rows[] = self::text_row( __( 'Message', 'crc-real-estate' ), $inquiry['message'], __( 'No message.', 'crc-real-estate' ) );

		return array_merge( $rows, self::end_rows( $inquiry ) );
	}
}
