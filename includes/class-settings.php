<?php
/**
 * Plugin settings.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The default contact numbers and button texts from Listings → Settings, and
 * each listing's own ones when it has them.
 */
final class Settings {

	const OPTION            = 'crc_re_contact';
	const DEFAULT_NUMBER    = '+94777643264';
	const PHONE_META        = '_crc_phone';
	const WHATSAPP_META     = '_crc_whatsapp';
	const CALL_TEXT_META    = '_crc_call_text';
	const MESSAGE_TEXT_META = '_crc_message_text';
	const NUMBER_TAG        = '{number}';

	/**
	 * Values used until the settings are saved.
	 *
	 * @return string[]
	 */
	public static function defaults() {
		return array(
			'phone'        => self::DEFAULT_NUMBER,
			'whatsapp'     => self::DEFAULT_NUMBER,
			/* translators: Keep {number}: it is replaced by the phone number. */
			'call_text'    => __( 'Call {number}', 'crc-real-estate' ),
			/* translators: Keep {number}: it is replaced by the WhatsApp number. */
			'message_text' => __( 'Message {number}', 'crc-real-estate' ),
		);
	}

	/**
	 * A saved setting. An empty number hides its button; an empty button
	 * text goes back to the default text.
	 *
	 * @param string $key "phone", "whatsapp", "call_text" or "message_text".
	 * @return string
	 */
	public static function get( $key ) {
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = self::defaults();
		$value    = array_key_exists( $key, $saved ) ? (string) $saved[ $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );

		if ( '' === $value && in_array( $key, array( 'call_text', 'message_text' ), true ) ) {
			$value = $defaults[ $key ];
		}

		return $value;
	}

	/**
	 * The number to show on a listing: its own number, or the default one.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $key     "phone" or "whatsapp".
	 * @return string
	 */
	public static function number_for( $post_id, $key ) {
		$own = (string) get_post_meta( $post_id, 'whatsapp' === $key ? self::WHATSAPP_META : self::PHONE_META, true );

		return '' !== $own ? $own : self::get( $key );
	}

	/**
	 * The text for a listing's Call or Message button, with {number}
	 * replaced by the button's number.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $button  "call" or "message".
	 * @return string
	 */
	public static function button_text( $post_id, $button ) {
		$is_message = 'message' === $button;
		$text       = (string) get_post_meta( $post_id, $is_message ? self::MESSAGE_TEXT_META : self::CALL_TEXT_META, true );

		if ( '' === $text ) {
			$text = self::get( $is_message ? 'message_text' : 'call_text' );
		}

		return trim( str_replace( self::NUMBER_TAG, self::number_for( $post_id, $is_message ? 'whatsapp' : 'phone' ), $text ) );
	}

	/**
	 * Keeps a phone number readable: digits, spaces, +, - and brackets.
	 *
	 * @param mixed $value Typed number.
	 * @return string
	 */
	public static function sanitize_number( $value ) {
		$value = preg_replace( '/[^0-9+()\-\s]/', '', (string) $value );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Keeps a button text as plain text on one line.
	 *
	 * @param mixed $value Typed text.
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		return trim( sanitize_text_field( (string) $value ) );
	}

	/**
	 * Cleans the settings before they are saved.
	 *
	 * @param mixed $input Submitted settings.
	 * @return string[]
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'phone'        => self::sanitize_number( isset( $input['phone'] ) ? $input['phone'] : '' ),
			'whatsapp'     => self::sanitize_number( isset( $input['whatsapp'] ) ? $input['whatsapp'] : '' ),
			'call_text'    => self::sanitize_text( isset( $input['call_text'] ) ? $input['call_text'] : '' ),
			'message_text' => self::sanitize_text( isset( $input['message_text'] ) ? $input['message_text'] : '' ),
		);
	}
}
