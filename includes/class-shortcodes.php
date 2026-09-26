<?php
/**
 * Shortcode registry.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps every shortcode with its documentation, so the Shortcodes page always lists
 * what the plugin offers, and gives shortcodes shared helpers.
 */
final class Shortcodes {

	/**
	 * Registered shortcodes and their documentation, keyed by tag.
	 *
	 * @var array[]
	 */
	private static $items = array();

	/**
	 * Registers a shortcode together with its documentation.
	 *
	 * @param string   $tag      Shortcode tag.
	 * @param callable $callback Render callback.
	 * @param array    $info     {
	 *     Documentation shown on the Shortcodes page.
	 *
	 *     @type string  $title       Section name.
	 *     @type string  $description What the shortcode shows.
	 *     @type array[] $attributes  Attribute name => array( 'default' => …, 'description' => … ).
	 *     @type array   $examples    Example shortcodes.
	 * }
	 */
	public static function add( $tag, $callback, array $info ) {
		self::$items[ $tag ] = wp_parse_args(
			$info,
			array(
				'title'       => $tag,
				'description' => '',
				'attributes'  => array(),
				'examples'    => array( '[' . $tag . ']' ),
			)
		);

		add_shortcode( $tag, $callback );
	}

	/**
	 * All registered shortcodes.
	 *
	 * @return array[]
	 */
	public static function all() {
		return self::$items;
	}

	/**
	 * Fills in defaults for a shortcode's attributes.
	 *
	 * @param string       $tag  Shortcode tag.
	 * @param array|string $atts Attributes from the shortcode.
	 * @return array
	 */
	public static function atts( $tag, $atts ) {
		$defaults = array();

		foreach ( self::$items[ $tag ]['attributes'] as $name => $attribute ) {
			$defaults[ $name ] = isset( $attribute['default'] ) ? $attribute['default'] : '';
		}

		return shortcode_atts( $defaults, $atts, $tag );
	}

	/**
	 * Finds the listing a shortcode is about: the id attribute if given,
	 * otherwise the listing being viewed.
	 *
	 * @param int|string $id Listing ID from the shortcode.
	 * @return \WP_Post|null
	 */
	public static function listing( $id = 0 ) {
		$id = absint( $id );

		if ( ! $id ) {
			$id = (int) get_the_ID();

			if ( Post_Type::NAME !== get_post_type( $id ) ) {
				$id = is_singular( Post_Type::NAME ) ? (int) get_queried_object_id() : 0;
			}
		}

		$post = $id ? get_post( $id ) : null;

		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return null;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Explains to editors why a shortcode shows nothing. Visitors see nothing.
	 *
	 * @param string $tag     Shortcode tag.
	 * @param string $message Explanation.
	 * @return string
	 */
	public static function placeholder( $tag, $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return sprintf(
			'<div class="crc-placeholder" style="padding:12px 16px;border:1px dashed #8c8f94;border-radius:8px;color:#50575e;font-size:14px;line-height:1.5;">[%s] %s</div>',
			esc_html( $tag ),
			esc_html( $message )
		);
	}

	/**
	 * Reads yes/no attribute values. Anything except no, false, off or 0 counts as yes.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	public static function is_on( $value ) {
		return ! in_array( strtolower( trim( (string) $value ) ), array( 'no', 'false', 'off', '0' ), true );
	}
}
