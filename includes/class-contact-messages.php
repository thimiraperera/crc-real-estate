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

	const NAME      = 'crc_contact_message';
	const NEW_META  = '_crc_contact_new';
	const MAIL_LIST = 'contact';

	/**
	 * Details kept with each message: key => field name. Only older messages
	 * have a subject, from when the form asked for one.
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
	 * What the email about a message says.
	 *
	 * @param array $message Checked message: the keys of META except "mailed" and "subject".
	 * @param int   $post_id Kept message's ID; 0 when it wasn't kept.
	 * @return array See Submissions::email().
	 */
	public static function email( array $message, $post_id = 0 ) {
		$first = self::greeting( $message );
		/* translators: %s: first name. */
		$intro  = __( '%s sent a message from your website.', 'crc-real-estate' );
		$rows   = self::person_rows( $message );
		$rows[] = self::text_row( __( 'Message', 'crc-real-estate' ), $message['message'], __( 'No message.', 'crc-real-estate' ) );

		return array(
			/* translators: %s: the name of the person who wrote. */
			'subject'    => sprintf( __( 'New message: %s', 'crc-real-estate' ), self::name( $message ) ),
			'label'      => __( 'New message', 'crc-real-estate' ),
			'heading'    => self::name( $message ),
			'intro_html' => sprintf( esc_html( $intro ), esc_html( $first ) ),
			'intro_text' => sprintf( $intro, $first ),
			'rows'       => array_merge( $rows, self::end_rows( $message ) ),
			'button'     => __( 'View the message', 'crc-real-estate' ),
			'kept'       => __( 'The message is also saved on your website\'s dashboard, under Contact Messages.', 'crc-real-estate' ),
		);
	}

	/**
	 * A made-up message, to see and test its email.
	 *
	 * @return array
	 */
	public static function sample() {
		return array(
			'first_name' => 'Nimal',
			'last_name'  => 'Perera',
			'phone'      => '+94771234567',
			'country'    => 'LK',
			'email'      => 'nimal.perera@example.com',
			'subject'    => '',
			'message'    => __( 'I\'d like to sell my land in Galle. Could you call me after 5 pm?', 'crc-real-estate' ),
			'page'       => home_url( '/' ),
			'note'       => '',
		);
	}
}
