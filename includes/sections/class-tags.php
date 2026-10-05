<?php
/**
 * Tags section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\District;
use CRC\RealEstate\Icons;
use CRC\RealEstate\Shortcodes;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * The listing's key details as rounded tags with icons, for under the title:
 * Galle, 20 Acres, Bare Land. They come from what is filled in on the
 * listing screen, so there is nothing extra to type.
 */
final class Tags {

	const SHORTCODE = 'crc_listing_tags';

	/**
	 * The tags there can be, keyed by name.
	 *
	 * - label: what it is, for screen readers and the Shortcodes page.
	 * - icon:  icon from assets/icons.
	 *
	 * @return array[]
	 */
	public static function kinds() {
		/**
		 * Filters the tags there can be, e.g. to change their icons.
		 *
		 * @param array[] $kinds Name => 'label' and 'icon'.
		 */
		return apply_filters(
			'crc_re_tag_kinds',
			array(
				'district'      => array(
					'label' => __( 'District', 'crc-real-estate' ),
					'icon'  => 'location',
				),
				'land_extent'   => array(
					'label' => __( 'Land extent', 'crc-real-estate' ),
					'icon'  => 'extent',
				),
				'floor_area'    => array(
					'label' => __( 'Floor area', 'crc-real-estate' ),
					'icon'  => 'area',
				),
				'bedrooms'      => array(
					'label' => __( 'Bedrooms', 'crc-real-estate' ),
					'icon'  => 'bed',
				),
				'bathrooms'     => array(
					'label' => __( 'Bathrooms', 'crc-real-estate' ),
					'icon'  => 'bath',
				),
				'property_type' => array(
					'label' => __( 'Property type', 'crc-real-estate' ),
					'icon'  => 'land-plots',
				),
			)
		);
	}

	/**
	 * The tags a category shows, in order.
	 *
	 * @param string $category Category slug.
	 * @return string[]
	 */
	public static function defaults( $category ) {
		$homes = array( 'district', 'bedrooms', 'bathrooms', 'floor_area', 'property_type' );
		$lands = array( 'district', 'land_extent', 'property_type' );

		/**
		 * Filters which tags a category's listings show, in order.
		 *
		 * @param string[] $tags     Tag names.
		 * @param string   $category Category slug.
		 */
		return (array) apply_filters( 'crc_re_listing_tags', in_array( $category, array( 'properties-for-sale', 'properties-for-rent' ), true ) ? $homes : $lands, $category );
	}

	/**
	 * Tag names from the show attribute, e.g. "district, land extent".
	 *
	 * @param string $value Comma-separated names.
	 * @return string[]
	 */
	public static function parse_show( $value ) {
		$kinds = self::kinds();
		$names = array();

		foreach ( explode( ',', (string) $value ) as $name ) {
			$name = str_replace( array( ' ', '-' ), '_', strtolower( trim( $name ) ) );

			if ( isset( $kinds[ $name ] ) && ! in_array( $name, $names, true ) ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * A listing's tags that have a value, as text, e.g. "20 Acres".
	 *
	 * @param int      $post_id Listing ID.
	 * @param string[] $names   Tag names, in order.
	 * @return string[] Name => text.
	 */
	public static function values( $post_id, array $names ) {
		$items  = Overview::common_items();
		$fields = Overview::fields();
		$tags   = array();

		foreach ( $names as $name ) {
			$text = '';

			if ( 'district' === $name ) {
				$district = District::of( $post_id );
				$text     = $district ? $district['name'] : '';
			} elseif ( 'property_type' === $name ) {
				$text = isset( $fields['property_type'] ) ? trim( (string) get_post_meta( $post_id, $fields['property_type']['meta'], true ) ) : '';
			} elseif ( in_array( $name, array( 'bedrooms', 'bathrooms' ), true ) && isset( $items[ $name ] ) ) {
				$number = Overview::sanitize_number( get_post_meta( $post_id, $items[ $name ]['meta'], true ), false );

				if ( '' !== $number ) {
					$text = sprintf(
						'bedrooms' === $name
							/* translators: %s: number of bedrooms. */
							? _n( '%s Bedroom', '%s Bedrooms', (int) $number, 'crc-real-estate' )
							/* translators: %s: number of bathrooms. */
							: _n( '%s Bathroom', '%s Bathrooms', (int) $number, 'crc-real-estate' ),
						number_format_i18n( (int) $number )
					);
				}
			} elseif ( isset( $items[ $name ] ) ) {
				$text = Overview::detail_text( $post_id, $items[ $name ] );
			}

			if ( '' !== $text ) {
				$tags[ $name ] = $text;
			}
		}

		return $tags;
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Tags', 'crc-real-estate' ),
				'description' => __( 'The listing\'s key details as rounded tags with icons, for under the title, for example Galle, 20 Acres and Bare Land. Lands show the district, land extent and property type; properties for sale and for rent show the district, bedrooms, bathrooms, floor area and property type. They come from the District box and the Property overview box on the listing screen, and a tag without a value doesn\'t show.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'   => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
					'show' => array(
						'default'     => '',
						'description' => __( 'Which tags to show, in order, separated by commas: district, land_extent, floor_area, bedrooms, bathrooms, property_type. Leave it out to show the ones for the listing\'s category.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' show="district,property_type"]',
					'[' . self::SHORTCODE . ' id="123"]',
				),
			)
		);
	}

	/**
	 * Renders [crc_listing_tags].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = Shortcodes::atts( self::SHORTCODE, $atts );
		$post = Shortcodes::listing( $atts['id'] );

		if ( ! $post ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'Place this on a listing page, or add id="…" with a listing ID.', 'crc-real-estate' ) );
		}

		$category = Taxonomy::listing_category( $post->ID );
		$names    = '' !== trim( (string) $atts['show'] ) ? self::parse_show( $atts['show'] ) : self::defaults( $category ? $category['slug'] : '' );
		$html     = self::list_html( $post->ID, $names );

		if ( '' === $html ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'This listing has nothing for its tags yet. Fill in its district, property type and size on the listing screen.', 'crc-real-estate' ) );
		}

		return $html;
	}

	/**
	 * A listing's tags as a list, for this shortcode and for listing cards.
	 *
	 * @param int      $post_id Listing ID.
	 * @param string[] $names   Tag names, in order.
	 * @return string An empty string when none has a value.
	 */
	public static function list_html( $post_id, array $names ) {
		$kinds = self::kinds();
		$tags  = self::values( $post_id, array_values( array_intersect( $names, array_keys( $kinds ) ) ) );
		$items = '';

		foreach ( $tags as $name => $text ) {
			$items .= sprintf(
				'<li class="crc-tag crc-tag-%1$s">%2$s<span class="crc-sr">%3$s: </span><span class="crc-tag-text">%4$s</span></li>',
				esc_attr( str_replace( '_', '-', $name ) ),
				Icons::svg( $kinds[ $name ]['icon'], 'crc-tag-icon' ),
				esc_html( $kinds[ $name ]['label'] ),
				esc_html( $text )
			);
		}

		return '' !== $items ? '<ul class="crc-tags">' . $items . '</ul>' : '';
	}
}
