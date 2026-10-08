<?php
/**
 * Inquiries screens.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Inquiries;

defined( 'ABSPATH' ) || exit;

/**
 * Inquiries, a menu of its own: every inquiry with who sent it and how to
 * reach them, and each inquiry in full with buttons to reply. The menu shows
 * how many are new, and new ones are marked New until they are opened.
 */
final class Inquiries_Screen extends Submissions_Screen {

	const STORE = Inquiries::class;
	const BOX   = 'crc-inquiry';

	/**
	 * The title of the inquiry box.
	 *
	 * @return string
	 */
	protected static function box_title() {
		return __( 'Inquiry', 'crc-real-estate' );
	}

	/**
	 * The number of new inquiries, for screen readers.
	 *
	 * @param int $count Number of new inquiries.
	 * @return string
	 */
	protected static function badge_text( $count ) {
		/* translators: %s: number of new inquiries. */
		return sprintf( _n( '%s new inquiry', '%s new inquiries', $count, 'crc-real-estate' ), number_format_i18n( $count ) );
	}

	/**
	 * The list's columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		return array(
			'cb'          => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">',
			'title'       => __( 'Name', 'crc-real-estate' ),
			'crc_phone'   => __( 'Phone', 'crc-real-estate' ),
			'crc_email'   => __( 'Email', 'crc-real-estate' ),
			'crc_listing' => __( 'Listing', 'crc-real-estate' ),
			'date'        => __( 'Received', 'crc-real-estate' ),
		);
	}

	/**
	 * Prints a column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Inquiry ID.
	 */
	public function column( $column, $post_id ) {
		$inquiry = Inquiries::get( $post_id );

		switch ( $column ) {
			case 'crc_phone':
				echo self::phone_link( $inquiry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in phone_link().
				break;

			case 'crc_email':
				echo self::email_link( $inquiry['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in email_link().
				break;

			case 'crc_listing':
				$listing = Inquiries::listing( $inquiry );

				if ( $listing ) {
					printf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_edit_post_link( $listing->ID ) ), esc_html( get_the_title( $listing ) ) );
				} else {
					echo '<span class="description">' . esc_html__( 'General inquiry', 'crc-real-estate' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Prints the inquiry.
	 *
	 * @param \WP_Post $post Inquiry.
	 */
	public function details( $post ) {
		$inquiry = Inquiries::get( $post->ID );
		$listing = Inquiries::listing( $inquiry );
		$rows    = self::person_rows( $inquiry );

		$rows[ __( 'Listing', 'crc-real-estate' ) ] = $listing
			? sprintf(
				'<a href="%1$s">%2$s</a> &middot; <a href="%3$s" target="_blank" rel="noopener">%4$s</a>',
				esc_url( (string) get_edit_post_link( $listing->ID ) ),
				esc_html( get_the_title( $listing ) ),
				esc_url( (string) get_permalink( $listing ) ),
				esc_html__( 'View on the site', 'crc-real-estate' )
			)
			: esc_html__( 'General inquiry, not about a particular listing.', 'crc-real-estate' );

		$rows[ __( 'Message', 'crc-real-estate' ) ] = self::text_cell( $inquiry['message'], __( 'No message.', 'crc-real-estate' ) );

		self::table( array_merge( $rows, self::end_rows( $inquiry, $post, __( 'The email about this inquiry couldn\'t be sent, so it is only here. If this keeps happening, ask your host to check the site\'s email, or add an SMTP plugin that sends email through your email account.', 'crc-real-estate' ) ) ) );
	}

	/**
	 * Prints the Reply buttons: email, call, WhatsApp, and Move to Trash.
	 *
	 * @param \WP_Post $post Inquiry.
	 */
	public function reply( $post ) {
		$inquiry = Inquiries::get( $post->ID );
		$listing = Inquiries::listing( $inquiry );
		$subject = $listing
			/* translators: %s: listing title. */
			? sprintf( __( 'Your inquiry about %s', 'crc-real-estate' ), html_entity_decode( get_the_title( $listing ), ENT_QUOTES, 'UTF-8' ) )
			: __( 'Your inquiry', 'crc-real-estate' );

		self::reply_buttons( $post, $inquiry, $subject );
	}
}
