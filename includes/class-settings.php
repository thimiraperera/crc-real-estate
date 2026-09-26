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
 * each listing's own ones when it has them; and the map settings.
 */
final class Settings {

	const OPTION            = 'crc_re_contact';
	const MAP_OPTION        = 'crc_re_map';
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
	 * Map settings used until they are saved: how far the area on a listing
	 * page reaches (km), where the map starts for a new listing (Galle, Sri
	 * Lanka), and another map style's tiles address and credit (empty for
	 * OpenStreetMap).
	 *
	 * @return array
	 */
	public static function map_defaults() {
		return array(
			'radius' => 15,
			'lat'    => 6.0535,
			'lng'    => 80.221,
			'tiles'  => '',
			'credit' => '',
		);
	}

	/**
	 * A saved map setting.
	 *
	 * @param string $key "radius", "lat", "lng", "tiles" or "credit".
	 * @return float|string|null
	 */
	public static function map( $key ) {
		$saved = self::sanitize_map( get_option( self::MAP_OPTION, array() ) );

		return isset( $saved[ $key ] ) ? $saved[ $key ] : null;
	}

	/**
	 * Cleans the map settings. A value out of range goes back to its default;
	 * a tiles address must be https and contain {z}, {x} and {y}.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public static function sanitize_map( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::map_defaults();
		$number   = function ( $key, $min, $max, $decimals ) use ( $input, $defaults ) {
			$value = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';

			return is_numeric( $value ) && (float) $value >= $min && (float) $value <= $max ? round( (float) $value, $decimals ) : $defaults[ $key ];
		};
		$tiles    = isset( $input['tiles'] ) && is_scalar( $input['tiles'] ) ? trim( sanitize_text_field( (string) $input['tiles'] ) ) : '';
		$valid    = 0 === strpos( $tiles, 'https://' ) && false !== strpos( $tiles, '{z}' ) && false !== strpos( $tiles, '{x}' ) && false !== strpos( $tiles, '{y}' );

		return array(
			'radius' => $number( 'radius', 1, 100, 1 ),
			'lat'    => $number( 'lat', -90, 90, 6 ),
			'lng'    => $number( 'lng', -180, 180, 6 ),
			'tiles'  => $valid ? $tiles : '',
			'credit' => isset( $input['credit'] ) && is_scalar( $input['credit'] ) ? sanitize_text_field( (string) $input['credit'] ) : '',
		);
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
