<?php
/**
 * Suggested listing names and short links.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Overview;

defined( 'ABSPATH' ) || exit;

/**
 * Makes a name for a listing from what is filled in, such as "Bare land for
 * sale in Galle" or "3 bedroom house for rent in Kandy", and a short link
 * from it, such as bare-land-for-sale-galle. The listing screen suggests
 * both; a listing saved with an empty title gets them by itself, and so does
 * an imported row without a title.
 */
final class Listing_Name {

	const NONCE = 'crc_listing_name_nonce';

	/**
	 * The words the name is made of, also used by the listing screen's script
	 * so both make the same name.
	 *
	 * @return array
	 */
	public static function texts() {
		return array(
			/* translators: 1: property type, 2: "for sale", "for rent" or "for lease", 3: district. */
			'withDistrict' => __( '%1$s %2$s in %3$s', 'crc-real-estate' ),
			/* translators: 1: property type, 2: "for sale", "for rent" or "for lease". */
			'noDistrict'   => __( '%1$s %2$s', 'crc-real-estate' ),
			/* translators: 1: number of bedrooms, 2: property type, e.g. "3 bedroom house". */
			'bedrooms'     => __( '%1$s bedroom %2$s', 'crc-real-estate' ),
			'sale'         => __( 'for sale', 'crc-real-estate' ),
			'rent'         => __( 'for rent', 'crc-real-estate' ),
			'lease'        => __( 'for lease', 'crc-real-estate' ),
			'land'         => __( 'Land', 'crc-real-estate' ),
			'property'     => __( 'Property', 'crc-real-estate' ),
			// Short words left out of short links.
			'skip'         => array( 'in', 'a', 'an', 'the', 'of', 'at', 'on' ),
			// What Offered for can say, in lower case, checked in this order.
			'offers'       => array(
				'rent'  => array_values( array_unique( array( 'rent', 'for rent', 'rental', strtolower( __( 'Rent', 'crc-real-estate' ) ) ) ) ),
				'lease' => array_values( array_unique( array( 'lease', 'for lease', strtolower( __( 'Lease', 'crc-real-estate' ) ) ) ) ),
				'sale'  => array_values( array_unique( array( 'sale', 'for sale', strtolower( __( 'Sale', 'crc-real-estate' ) ) ) ) ),
			),
		);
	}

	/**
	 * What a listing is offered for: what Offered for says when it is sale,
	 * rent or lease, otherwise its category's.
	 *
	 * @param string $category    Category slug.
	 * @param string $offered_for Offered for, as typed.
	 * @return string sale, rent or lease.
	 */
	public static function offer( $category, $offered_for ) {
		$asked = strtolower( trim( (string) $offered_for ) );

		foreach ( self::texts()['offers'] as $offer => $words ) {
			if ( in_array( $asked, $words, true ) ) {
				return $offer;
			}
		}

		return 'properties-for-rent' === $category ? 'rent' : 'sale';
	}

	/**
	 * A property type written to sit inside a sentence: "Bare Land" becomes
	 * "bare land", while short forms in capitals, such as "A/C", stay.
	 *
	 * @param string $type Property type.
	 * @return string
	 */
	public static function lower( $type ) {
		$words = preg_split( '/\s+/', trim( (string) $type ), -1, PREG_SPLIT_NO_EMPTY );

		$mb = function_exists( 'mb_strtolower' );

		foreach ( $words as $i => $word ) {
			$letters = (string) preg_replace( '/[^\p{L}]/u', '', $word );
			$short   = ( $mb ? mb_strlen( $letters, 'UTF-8' ) : strlen( $letters ) ) > 1 && $letters === ( $mb ? mb_strtoupper( $letters, 'UTF-8' ) : strtoupper( $letters ) );

			if ( ! $short ) {
				$words[ $i ] = $mb ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word );
			}
		}

		return implode( ' ', $words );
	}

	/**
	 * Text with a capital first letter.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function capital( $text ) {
		if ( '' === $text || ! function_exists( 'mb_substr' ) ) {
			return ucfirst( $text );
		}

		return mb_strtoupper( mb_substr( $text, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $text, 1, null, 'UTF-8' );
	}

	/**
	 * The suggested name.
	 *
	 * @param array $parts 'category' (slug), 'offered_for', 'property_type', 'bedrooms' and 'district' (name).
	 * @return string
	 */
	public static function suggest( array $parts ) {
		$parts = wp_parse_args(
			array_map(
				function ( $value ) {
					return is_scalar( $value ) ? trim( (string) $value ) : '';
				},
				$parts
			),
			array(
				'category'      => '',
				'offered_for'   => '',
				'property_type' => '',
				'bedrooms'      => '',
				'district'      => '',
			)
		);
		$text  = self::texts();
		$type  = self::lower( $parts['property_type'] );

		if ( '' === $type ) {
			$type = self::lower( 'lands' === $parts['category'] ? $text['land'] : $text['property'] );
		}

		// Bedrooms are only asked for homes.
		$homes    = in_array( $parts['category'], array( 'properties-for-sale', 'properties-for-rent' ), true );
		$bedrooms = $homes ? Overview::sanitize_number( $parts['bedrooms'], false ) : '';

		if ( '' !== $bedrooms ) {
			$type = sprintf( $text['bedrooms'], $bedrooms, $type );
		}

		$offer = $text[ self::offer( $parts['category'], $parts['offered_for'] ) ];
		$name  = '' !== $parts['district'] ? sprintf( $text['withDistrict'], $type, $offer, $parts['district'] ) : sprintf( $text['noDistrict'], $type, $offer );

		return self::capital( $name );
	}

	/**
	 * A short link from a name: its words without short ones such as "in",
	 * e.g. bare-land-for-sale-galle.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function slug( $name ) {
		$skip  = self::texts()['skip'];
		$words = preg_split( '/\s+/', strtolower( remove_accents( (string) $name ) ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array_filter(
			$words,
			function ( $word ) use ( $skip ) {
				return ! in_array( $word, $skip, true );
			}
		);

		return sanitize_title( implode( ' ', $words ) );
	}

	/**
	 * The parts of a saved listing's name.
	 *
	 * @param int $post_id Listing ID.
	 * @return array
	 */
	public static function parts( $post_id ) {
		$fields   = Overview::fields();
		$items    = Overview::common_items();
		$category = Taxonomy::listing_category( $post_id );
		$district = District::of( $post_id );

		return array(
			'category'      => $category ? $category['slug'] : '',
			'offered_for'   => isset( $fields['offered_for'] ) ? (string) get_post_meta( $post_id, $fields['offered_for']['meta'], true ) : '',
			'property_type' => isset( $fields['property_type'] ) ? (string) get_post_meta( $post_id, $fields['property_type']['meta'], true ) : '',
			'bedrooms'      => isset( $items['bedrooms'] ) ? (string) get_post_meta( $post_id, $items['bedrooms']['meta'], true ) : '',
			'district'      => $district ? $district['name'] : '',
		);
	}

	/**
	 * The suggested name of a saved listing.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function for_listing( $post_id ) {
		return self::suggest( self::parts( $post_id ) );
	}

	/**
	 * The parts of a name from an imported row.
	 *
	 * @param string[] $cells Row, keyed by column name.
	 * @return array
	 */
	public static function parts_from_cells( array $cells ) {
		$cell     = function ( $name ) use ( $cells ) {
			return isset( $cells[ $name ] ) && is_scalar( $cells[ $name ] ) ? trim( (string) $cells[ $name ] ) : '';
		};
		$district = District::find( $cell( 'district' ) );
		$names    = District::districts();

		return array(
			'category'      => '' !== $cell( 'category' ) ? Listing_Data::category_slug( $cell( 'category' ) ) : '',
			'offered_for'   => $cell( 'offered_for' ),
			'property_type' => wp_strip_all_tags( $cell( 'property_type' ) ),
			'bedrooms'      => $cell( 'bedrooms' ),
			'district'      => '' !== $district ? self::district_name( $district, $names[ $district ]['name'] ) : '',
		);
	}

	/**
	 * A district's name as the site shows it: its term's, which can be edited.
	 *
	 * @param string $slug    District slug.
	 * @param string $default Name to use without a term.
	 * @return string
	 */
	private static function district_name( $slug, $default ) {
		$term = get_term_by( 'slug', $slug, District::NAME );

		return $term && ! is_wp_error( $term ) ? (string) $term->name : (string) $default;
	}

	/**
	 * The parts of the name being saved from the listing screen: what was
	 * sent, and what is saved for anything not sent.
	 *
	 * @param int $post_id Listing ID.
	 * @return array
	 */
	private static function parts_from_request( $post_id ) {
		$parts = self::parts( $post_id );
		$text  = function ( $value ) {
			return is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
		};
		$first = function ( $ids ) {
			foreach ( (array) $ids as $id ) {
				if ( is_scalar( $id ) && absint( $id ) ) {
					return absint( $id );
				}
			}

			return 0;
		};

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in fill_in().
		if ( isset( $_POST['crc_property_type'] ) ) {
			$parts['property_type'] = $text( $_POST['crc_property_type'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in $text.
		}

		if ( isset( $_POST['crc_offered_for'] ) ) {
			$parts['offered_for'] = $text( $_POST['crc_offered_for'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in $text.
		}

		if ( isset( $_POST['crc_details']['bedrooms'] ) ) {
			$parts['bedrooms'] = $text( $_POST['crc_details']['bedrooms'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in $text.
		}

		if ( isset( $_POST['tax_input'][ Taxonomy::NAME ] ) ) {
			$term              = get_term( $first( wp_unslash( $_POST['tax_input'][ Taxonomy::NAME ] ) ), Taxonomy::NAME ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read as numbers.
			$parts['category'] = $term && ! is_wp_error( $term ) ? (string) $term->slug : '';
		}

		if ( isset( $_POST['tax_input'][ District::NAME ] ) ) {
			$term              = get_term( $first( wp_unslash( $_POST['tax_input'][ District::NAME ] ) ), District::NAME ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read as numbers.
			$parts['district'] = $term && ! is_wp_error( $term ) ? (string) $term->name : '';
		}
		// phpcs:enable

		return $parts;
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'wp_insert_post_data', array( $this, 'fill_in' ), 10, 2 );
	}

	/**
	 * Names a listing saved from the listing screen with an empty title, and
	 * gives it the short link when it has no link yet.
	 *
	 * @param array $data    Slashed post data about to be saved.
	 * @param array $postarr Post data as sent.
	 * @return array
	 */
	public function fill_in( $data, $postarr ) {
		if ( Post_Type::NAME !== $data['post_type'] || '' !== trim( (string) $data['post_title'] ) || in_array( $data['post_status'], array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return $data;
		}

		// Only for the listing being saved from its screen, where the name box is.
		if ( ! isset( $_POST[ self::NONCE ], $_POST['post_ID'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_listing_name' ) ) {
			return $data;
		}

		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( ! $id || absint( $_POST['post_ID'] ) !== $id ) {
			return $data;
		}

		$name               = self::suggest( self::parts_from_request( $id ) );
		$data['post_title'] = wp_slash( $name );

		if ( '' === (string) $data['post_name'] && empty( $postarr['post_name'] ) ) {
			$slug = self::slug( $name );

			if ( '' !== $slug ) {
				$data['post_name'] = wp_unique_post_slug( $slug, $id, $data['post_status'], $data['post_type'], isset( $data['post_parent'] ) ? (int) $data['post_parent'] : 0 );
			}
		}

		return $data;
	}
}
