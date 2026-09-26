<?php
/**
 * SVG icons.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the plugin's icons from assets/icons as inline SVG, so they take the
 * text colour around them.
 */
final class Icons {

	/**
	 * Icon markup already read, keyed by name.
	 *
	 * @var string[]
	 */
	private static $cache = array();

	/**
	 * Inline SVG for assets/icons/icon-{name}.svg.
	 *
	 * @param string $name  Icon name, e.g. "eye".
	 * @param string $class CSS class for the svg element.
	 * @return string SVG markup, or an empty string if the icon doesn't exist.
	 */
	public static function svg( $name, $class = '' ) {
		$name = sanitize_key( $name );

		if ( ! isset( self::$cache[ $name ] ) ) {
			$file = CRC_RE_PATH . 'assets/icons/icon-' . $name . '.svg';

			self::$cache[ $name ] = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
		}

		if ( '' === self::$cache[ $name ] ) {
			return '';
		}

		$attributes = ' aria-hidden="true" focusable="false"' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' );

		return preg_replace( '/<svg\b/', '<svg' . $attributes, self::$cache[ $name ], 1 );
	}
}
