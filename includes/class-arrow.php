<?php
/**
 * Round arrows.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The round previous and next arrows, so every part of the site that needs
 * them looks the same. Their look is .crc-arrow in frontend.css.
 *
 * They are not <button> elements, because Elementor's button styles would
 * restyle them. The script that uses them makes them work with a click, Enter
 * and Space, and sets aria-disabled="true" when there is nothing more that
 * way, which fades them.
 */
final class Arrow {

	/**
	 * Markup for one arrow.
	 *
	 * @param string $direction prev or next.
	 * @param string $label     What it does, for screen readers, e.g. "Next".
	 * @param string $class     More CSS classes.
	 * @param bool   $disabled  Starts faded, e.g. Previous at the first card.
	 * @return string
	 */
	public static function html( $direction, $label, $class = '', $disabled = false ) {
		$next = 'next' === $direction;

		return sprintf(
			'<div class="crc-arrow crc-arrow-%1$s%2$s" role="button" tabindex="0" aria-label="%3$s" aria-disabled="%4$s">%5$s</div>',
			$next ? 'next' : 'prev',
			'' !== $class ? ' ' . esc_attr( $class ) : '',
			esc_attr( $label ),
			$disabled ? 'true' : 'false',
			Icons::svg( $next ? 'arrow-right' : 'arrow-left', 'crc-arrow-icon' )
		);
	}
}
