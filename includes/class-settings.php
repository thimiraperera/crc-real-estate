<?php
/**
 * Plugin settings.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The default contact numbers from Listings → Settings, and each listing's
 * own numbers when it has them.
 */
final class Settings {

	const OPTION         = 'crc_re_contact';
	const DEFAULT_NUMBER = '+94777643264';
	const PHONE_META     = '_crc_phone';
	const WHATSAPP_META  = '_crc_whatsapp';

	/**
	 * Values used until the settings are saved.
	 *
	 * @return string[]
	 */
	public static function defaults() {
		return array(
			'phone'    => self::DEFAULT_NUMBER,
			'whatsapp' => self::DEFAULT_NUMBER,
		);
	}

	/**
	 * A saved setting.
	 *
	 * @param string $key "phone" or "whatsapp".
	 * @return string
	 */
	public static function get( $key ) {
		$saved  = get_option( self::OPTION, array() );
		$values = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

		return isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
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
	 * Cleans the settings before they are saved.
	 *
	 * @param mixed $input Submitted settings.
	 * @return string[]
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'phone'    => self::sanitize_number( isset( $input['phone'] ) ? $input['phone'] : '' ),
			'whatsapp' => self::sanitize_number( isset( $input['whatsapp'] ) ? $input['whatsapp'] : '' ),
		);
	}
}
