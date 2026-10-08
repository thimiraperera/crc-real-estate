<?php
/**
 * Contact Messages screens.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Contact_Messages;

defined( 'ABSPATH' ) || exit;

/**
 * Contact Messages, a menu of its own: every message sent with the contact
 * form, with who wrote and how to reach them, and each message in full with
 * buttons to reply. The menu shows how many are new, and new ones are marked
 * New until they are opened.
 */
final class Contact_Messages_Screen extends Submissions_Screen {

	const STORE = Contact_Messages::class;
	const BOX   = 'crc-contact-message';

	/**
	 * The title of the message box.
	 *
	 * @return string
	 */
	protected static function box_title() {
		return __( 'Message', 'crc-real-estate' );
	}

	/**
	 * The number of new messages, for screen readers.
	 *
	 * @param int $count Number of new messages.
	 * @return string
	 */
	protected static function badge_text( $count ) {
		/* translators: %s: number of new contact messages. */
		return sprintf( _n( '%s new message', '%s new messages', $count, 'crc-real-estate' ), number_format_i18n( $count ) );
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
			'crc_message' => __( 'Message', 'crc-real-estate' ),
			'date'        => __( 'Received', 'crc-real-estate' ),
		);
	}

	/**
	 * Prints a column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Message ID.
	 */
	public function column( $column, $post_id ) {
		$message = Contact_Messages::get( $post_id );

		switch ( $column ) {
			case 'crc_phone':
				echo self::phone_link( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in phone_link().
				break;

			case 'crc_email':
				echo self::email_link( $message['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in email_link().
				break;

			case 'crc_message':
				if ( '' !== $message['subject'] ) {
					echo '<strong>' . esc_html( $message['subject'] ) . '</strong><br>';
				}

				echo esc_html( wp_trim_words( $message['message'], 15, '…' ) );
				break;
		}
	}

	/**
	 * Prints the message.
	 *
	 * @param \WP_Post $post Message.
	 */
	public function details( $post ) {
		$message = Contact_Messages::get( $post->ID );
		$rows    = self::person_rows( $message );

		$rows[ __( 'Subject', 'crc-real-estate' ) ] = '' !== $message['subject'] ? esc_html( $message['subject'] ) : '<span class="description">' . esc_html__( 'No subject.', 'crc-real-estate' ) . '</span>';
		$rows[ __( 'Message', 'crc-real-estate' ) ] = self::text_cell( $message['message'], __( 'No message.', 'crc-real-estate' ) );

		self::table( array_merge( $rows, self::end_rows( $message, $post, __( 'The email about this message couldn\'t be sent, so it is only here. If this keeps happening, ask your host to check the site\'s email, or add an SMTP plugin that sends email through your email account.', 'crc-real-estate' ) ) ) );
	}

	/**
	 * Prints the Reply buttons: email, call, WhatsApp, and Move to Trash.
	 *
	 * @param \WP_Post $post Message.
	 */
	public function reply( $post ) {
		$message = Contact_Messages::get( $post->ID );
		$subject = '' !== $message['subject']
			/* translators: %s: the subject of the message being answered. */
			? sprintf( __( 'Re: %s', 'crc-real-estate' ), html_entity_decode( $message['subject'], ENT_QUOTES, 'UTF-8' ) )
			/* translators: %s: site name. */
			: sprintf( __( 'Your message to %s', 'crc-real-estate' ), html_entity_decode( (string) get_option( 'blogname' ), ENT_QUOTES, 'UTF-8' ) );

		self::reply_buttons( $post, $message, $subject );
	}
}
