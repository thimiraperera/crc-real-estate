<?php
/**
 * Messages sent with the contact form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Emails each message from the contact form and keeps it in Contact
 * Messages, its own menu in wp-admin, so none is lost when an email doesn't
 * arrive. A new message counts as new until it is opened.
 */
final class Contact_Messages extends Submissions {

	const NAME     = 'crc_contact_message';
	const NEW_META = '_crc_contact_new';

	/**
	 * Details kept with each message: key => field name.
	 */
	const META = array(
		'first_name' => '_crc_contact_first_name',
		'last_name'  => '_crc_contact_last_name',
		'phone'      => '_crc_contact_phone',
		'country'    => '_crc_contact_country',
		'email'      => '_crc_contact_email',
		'subject'    => '_crc_contact_subject',
		'message'    => '_crc_contact_message',
		'page'       => '_crc_contact_page',
		'note'       => '_crc_contact_note',
		'mailed'     => '_crc_contact_mailed',
	);

	/**
	 * The Contact Messages menu's icon: an envelope. WordPress colours it to
	 * match the admin menu.
	 *
	 * @return string
	 */
	public static function icon() {
		return self::svg_icon( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M3 3.5h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2zM2.8 5.9 10 11l7.2-5.1v1.9L10 12.9 2.8 7.8z"/></svg>' );
	}

	/**
	 * The names WordPress shows for contact messages.
	 *
	 * @return string[]
	 */
	protected static function labels() {
		return array(
			'name'               => __( 'Contact Messages', 'crc-real-estate' ),
			'singular_name'      => __( 'Contact Message', 'crc-real-estate' ),
			'menu_name'          => __( 'Contact Messages', 'crc-real-estate' ),
			'all_items'          => __( 'All Contact Messages', 'crc-real-estate' ),
			'edit_item'          => __( 'Contact Message', 'crc-real-estate' ),
			'view_item'          => __( 'View contact message', 'crc-real-estate' ),
			'search_items'       => __( 'Search contact messages', 'crc-real-estate' ),
			'not_found'          => __( 'No contact messages yet. Messages sent with the contact form show here.', 'crc-real-estate' ),
			'not_found_in_trash' => __( 'No contact messages in the Trash.', 'crc-real-estate' ),
			'item_updated'       => __( 'Contact message updated.', 'crc-real-estate' ),
		);
	}

	/**
	 * Its text, which the list's search also looks through: the subject and
	 * the message, so the list can be searched by subject too.
	 *
	 * @param array $message Details.
	 * @return string
	 */
	protected static function content( array $message ) {
		$text    = parent::content( $message );
		$subject = isset( $message['subject'] ) ? trim( (string) $message['subject'] ) : '';

		return '' !== $subject ? $subject . "\n\n" . $text : $text;
	}

	/**
	 * Emails a message to the addresses in Settings. Replying answers the
	 * person who wrote.
	 *
	 * @param array $message Checked message: the keys of META except "mailed".
	 * @param int   $post_id Kept message's ID, for a link to it; 0 when it wasn't kept.
	 * @return bool Whether the email was handed over for sending.
	 */
	public static function mail( array $message, $post_id = 0 ) {
		$first   = self::greeting( $message );
		$topic   = isset( $message['subject'] ) ? self::plain( $message['subject'] ) : '';
		/* translators: %s: the message's subject, or the name of the person who wrote. */
		$subject = sprintf( __( 'New message: %s', 'crc-real-estate' ), '' !== $topic ? $topic : self::name( $message ) );
		/* translators: %s: first name. */
		$intro = __( '%s sent a message from your website.', 'crc-real-estate' );
		$rows  = self::person_rows( $message );

		if ( '' !== $topic ) {
			$rows[] = array( __( 'Subject', 'crc-real-estate' ), esc_html( $topic ), $topic );
		}

		$rows[] = self::text_row( __( 'Message', 'crc-real-estate' ), $message['message'], __( 'No message.', 'crc-real-estate' ) );

		return self::send(
			$subject,
			sprintf( esc_html( $intro ), esc_html( $first ) ),
			sprintf( $intro, $first ),
			array_merge( $rows, self::end_rows( $message ) ),
			$message,
			$post_id,
			__( 'This message is also kept in wp-admin under Contact Messages.', 'crc-real-estate' )
		);
	}
}
