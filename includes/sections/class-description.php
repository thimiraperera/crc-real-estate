<?php
/**
 * Description section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * The text written in the listing's text box, formatted like post content.
 */
final class Description {

	const SHORTCODE = 'crc_listing_description';

	/**
	 * How many descriptions are being built right now, so a description that
	 * contains this shortcode can't repeat itself forever.
	 *
	 * @var int
	 */
	private static $depth = 0;

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
				'title'       => __( 'Description', 'crc-real-estate' ),
				'description' => __( 'The listing\'s description: the text written in the text box on the listing screen, with its formatting. The last paragraph has no space below it, so it sits neatly inside a box.', 'crc-real-estate' ),
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
	 * Renders [crc_listing_description].
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

		if ( self::$depth > 0 || post_password_required( $post ) ) {
			return '';
		}

		$text = trim( (string) $post->post_content );

		if ( '' === $text ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'This listing has no description yet.', 'crc-real-estate' ) );
		}

		++self::$depth;
		$html = $this->format( $text );
		--self::$depth;

		return '<div class="crc-description">' . $html . '</div>';
	}

	/**
	 * Formats the text the way WordPress formats post content: paragraphs,
	 * typography, shortcodes and responsive images.
	 *
	 * @param string $text Raw text from the editor.
	 * @return string
	 */
	private function format( $text ) {
		$text = wptexturize( $text );
		$text = convert_chars( $text );
		$text = wpautop( $text );
		$text = shortcode_unautop( $text );
		$text = do_shortcode( $text );

		return function_exists( 'wp_filter_content_tags' ) ? wp_filter_content_tags( $text ) : $text;
	}
}
