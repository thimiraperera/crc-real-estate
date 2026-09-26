<?php
/**
 * Listing owner details.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The owner of a listed property: name, phone, email, address and notes.
 * Kept for the team only: never shown on the site or given out by the REST API.
 */
final class Owner {

	/**
	 * Owner details: key => field name.
	 */
	const FIELDS = array(
		'first_name' => '_crc_owner_first_name',
		'last_name'  => '_crc_owner_last_name',
		'phone'      => '_crc_owner_phone',
		'email'      => '_crc_owner_email',
		'address'    => '_crc_owner_address',
		'notes'      => '_crc_owner_notes',
	);

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the details: private, and only for people who can edit the listing.
	 */
	public function register() {
		foreach ( self::FIELDS as $key => $meta_key ) {
			register_post_meta(
				Post_Type::NAME,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => function ( $value ) use ( $key ) {
						return self::sanitize( $key, $value );
					},
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * The details' names.
	 *
	 * @return string[]
	 */
	public static function labels() {
		return array(
			'first_name' => __( 'First name', 'crc-real-estate' ),
			'last_name'  => __( 'Last name', 'crc-real-estate' ),
			'phone'      => __( 'Phone', 'crc-real-estate' ),
			'email'      => __( 'Email', 'crc-real-estate' ),
			'address'    => __( 'Address', 'crc-real-estate' ),
			'notes'      => __( 'Special notes', 'crc-real-estate' ),
		);
	}

	/**
	 * A listing's owner details.
	 *
	 * @param int $post_id Listing ID.
	 * @return string[] Key => value; empty when not given.
	 */
	public static function get( $post_id ) {
		$owner = array();

		foreach ( self::FIELDS as $key => $meta_key ) {
			$owner[ $key ] = (string) get_post_meta( $post_id, $meta_key, true );
		}

		return $owner;
	}

	/**
	 * Cleans a detail: an email address, a phone number, text on one line,
	 * or, for the address and notes, text on several lines.
	 *
	 * @param string $key   Detail key.
	 * @param mixed  $value Typed value.
	 * @return string
	 */
	public static function sanitize( $key, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $key ) {
			case 'email':
				$value = trim( sanitize_email( $value ) );

				return is_email( $value ) ? $value : '';

			case 'phone':
				return Settings::sanitize_number( sanitize_text_field( $value ) );

			case 'address':
			case 'notes':
				return trim( sanitize_textarea_field( str_replace( array( "\r\n", "\r" ), "\n", $value ) ) );

			default:
				return trim( sanitize_text_field( $value ) );
		}
	}

	/**
	 * Saves a listing's owner details. Empty ones are removed.
	 *
	 * @param int   $post_id Listing ID.
	 * @param array $values  Key => value; keys not given are left as they are.
	 */
	public static function save( $post_id, array $values ) {
		foreach ( self::FIELDS as $key => $meta_key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$value = self::sanitize( $key, $values[ $key ] );

			if ( '' !== $value ) {
				update_post_meta( $post_id, $meta_key, wp_slash( $value ) );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}
}
