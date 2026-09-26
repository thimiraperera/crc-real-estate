<?php
/**
 * Title section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * The listing's title as an H3 heading.
 */
final class Title {

	const SHORTCODE = 'crc_listing_title';

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
				'title'       => __( 'Title', 'crc-real-estate' ),
				'description' => __( 'The listing\'s title as a heading, for example "Bare land for sale in Galle".', 'crc-real-estate' ),
				'attributes'  => array(
					'id' => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' id="123"]',
				),
			)
		);
	}

	/**
	 * Renders [crc_listing_title].
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

		return '<h3 class="crc-title">' . esc_html( get_the_title( $post ) ) . '</h3>';
	}
}
