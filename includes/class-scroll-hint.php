<?php
/**
 * Scroll down hint.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_scroll_hint]: a small moving sign without words that tells visitors
 * there is more below, such as at the bottom of a hero section that only has
 * a title on phones. Three arrows light up one after another, downwards,
 * inside one line shaped like a U along the bottom of the section: it draws
 * itself from the bottom middle out to both sides and up, in white fading to
 * clear. [crc_scroll_arrows] is the arrows on their own. In an Elementor
 * widget either sits at the bottom of its section. A click or tap scrolls
 * smoothly to what is under the section, and it fades away once the page has
 * been scrolled.
 */
final class Scroll_Hint {

	const SHORTCODE = 'crc_scroll_hint';
	const ARROWS    = 'crc_scroll_arrows';

	/**
	 * Hints on the page so far, for their ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Scroll down hint', 'crc-real-estate' ),
				'description' => __( 'A small moving sign without words that tells visitors there is more below, for example at the bottom of a hero section that only has a title and a subtitle on phones. Three arrows light up one after another, downwards, inside one line shaped like a U along the bottom of the section: the line draws itself from the bottom middle out to both sides and up them, in white that fades to clear at the top, in time with the arrows. Put it in a Shortcode widget anywhere in the section: it sits at the bottom of the section by itself, 10px in from its edges, with its corners rounded to run evenly inside the section\'s own rounded corners, a little see-through (70%). For the arrows without the line, use [crc_scroll_arrows]. A click or tap scrolls smoothly to what is under the section, and it fades away once the page has been scrolled. To show it on phones only, hide the widget on desktop and tablet in Elementor (Advanced, then Responsive). Visitors whose device asks for less motion see it standing still.', 'crc-real-estate' ),
				'attributes'  => array(
					'gap'    => array(
						'default'     => '10',
						'description' => __( 'How far in from the left, right and bottom edges of the section the line runs, in pixels.', 'crc-real-estate' ),
					),
					'height' => array(
						'default'     => '48',
						'description' => __( 'How tall the line is at the sides, in pixels.', 'crc-real-estate' ),
					),
					'radius'  => array(
						'default'     => 'auto',
						'description' => __( 'How round the line\'s two bottom corners are. auto (to start with) takes the section\'s own corner rounding less the gap, so the line stays the same distance from the section\'s edge all the way round its corners, on every screen size; a section with 32px corners and a 10px gap gives 22px. Or a number of pixels.', 'crc-real-estate' ),
					),
					'opacity' => array(
						'default'     => '70',
						'description' => __( 'How see-through the line and the arrows are together, from 10 (faint) to 100 (solid white).', 'crc-real-estate' ),
					),
					'frame'  => array(
						'default'     => 'yes',
						'description' => __( 'no to show the arrows on their own, without the line.', 'crc-real-estate' ),
					),
					'color'  => array(
						'default'     => 'light',
						'description' => __( 'light for white, on dark photos and colours, or dark for a dark grey, on light backgrounds.', 'crc-real-estate' ),
					),
					'pin'    => array(
						'default'     => 'yes',
						'description' => __( 'yes to sit at the bottom of its section, wherever the widget is placed in it; no to stay where it is placed.', 'crc-real-estate' ),
					),
					'target' => array(
						'default'     => '',
						'description' => __( 'Where a click scrolls to, such as #search for the part of the page with the ID search. Leave it out to scroll to what is under the section.', 'crc-real-estate' ),
					),
					'offset' => array(
						'default'     => '0',
						'description' => __( 'How many pixels to stop short, for a header that stays at the top of the window, so it does not cover what was scrolled to.', 'crc-real-estate' ),
					),
					'label'  => array(
						'default'     => __( 'Scroll down', 'crc-real-estate' ),
						'description' => __( 'What screen readers say for it. It is not shown on the page.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' gap="16" radius="48"]',
					'[' . self::SHORTCODE . ' opacity="50"]',
					'[' . self::SHORTCODE . ' target="#search" offset="80"]',
				),
			)
		);

		Shortcodes::add(
			self::ARROWS,
			array( $this, 'render_arrows' ),
			array(
				'title'       => __( 'Scroll down arrows', 'crc-real-estate' ),
				'description' => __( 'The scroll down hint\'s three arrows on their own, without the line: they light up one after another, downwards, in white that fades to clear. Put it in a Shortcode widget anywhere in a section: it sits at the bottom of the section, in the middle, by itself. A click or tap scrolls smoothly to what is under the section, and it fades away once the page has been scrolled. To show it on phones only, hide the widget on desktop and tablet in Elementor (Advanced, then Responsive). Visitors whose device asks for less motion see it standing still.', 'crc-real-estate' ),
				'attributes'  => array(
					'gap'     => array(
						'default'     => '24',
						'description' => __( 'How far up from the bottom of the section the arrows sit, in pixels.', 'crc-real-estate' ),
					),
					'opacity' => array(
						'default'     => '70',
						'description' => __( 'How see-through the arrows are, from 10 (faint) to 100 (solid white).', 'crc-real-estate' ),
					),
					'color'   => array(
						'default'     => 'light',
						'description' => __( 'light for white, on dark photos and colours, or dark for a dark grey, on light backgrounds.', 'crc-real-estate' ),
					),
					'pin'     => array(
						'default'     => 'yes',
						'description' => __( 'yes to sit at the bottom of its section, wherever the widget is placed in it; no to stay where it is placed.', 'crc-real-estate' ),
					),
					'target'  => array(
						'default'     => '',
						'description' => __( 'Where a click scrolls to, such as #search for the part of the page with the ID search. Leave it out to scroll to what is under the section.', 'crc-real-estate' ),
					),
					'offset'  => array(
						'default'     => '0',
						'description' => __( 'How many pixels to stop short, for a header that stays at the top of the window, so it does not cover what was scrolled to.', 'crc-real-estate' ),
					),
					'label'   => array(
						'default'     => __( 'Scroll down', 'crc-real-estate' ),
						'description' => __( 'What screen readers say for it. It is not shown on the page.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::ARROWS . ']',
					'[' . self::ARROWS . ' gap="32" opacity="100"]',
				),
			)
		);
	}

	/**
	 * Registers the script. The styles are in frontend.css, which every page loads.
	 */
	public function register_assets() {
		wp_register_script( 'crc-re-scroll-hint', CRC_RE_URL . 'assets/js/scroll-hint.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the script, also in Elementor's editor.
	 */
	public function enqueue_script() {
		if ( ! wp_script_is( 'crc-re-scroll-hint', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_script( 'crc-re-scroll-hint' );
	}

	/**
	 * Asks page speed plugins not to hold the script back.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( 'crc-re-scroll-hint' !== $handle || false !== strpos( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
	}

	/**
	 * Renders [crc_scroll_hint].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		return $this->hint( Shortcodes::atts( self::SHORTCODE, $atts ) );
	}

	/**
	 * Renders [crc_scroll_arrows]: the arrows without the line.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_arrows( $atts ) {
		$atts          = Shortcodes::atts( self::ARROWS, $atts );
		$atts['frame'] = 'no';

		return $this->hint( $atts );
	}

	/**
	 * A hint: the arrows, in the U-shaped line or on their own.
	 *
	 * @param array $atts Attributes, as the shortcodes' own.
	 * @return string
	 */
	private function hint( array $atts ) {
		$atts += array(
			'gap'     => '10',
			'height'  => '48',
			'radius'  => 'auto',
			'frame'   => 'yes',
			'opacity' => '70',
			'color'   => 'light',
			'pin'     => 'yes',
			'target'  => '',
			'offset'  => '0',
			'label'   => __( 'Scroll down', 'crc-real-estate' ),
		);
		$gap     = max( 0, min( 200, (int) $atts['gap'] ) );
		$height  = max( 24, min( 200, (int) $atts['height'] ) );
		$radius  = trim( strtolower( (string) $atts['radius'] ) );
		$radius  = '' === $radius || 'auto' === $radius || ! is_numeric( $radius ) ? 'auto' : (string) max( 0, min( 200, (int) $radius ) );
		$offset  = max( 0, min( 400, (int) $atts['offset'] ) );
		$frame   = Shortcodes::is_on( $atts['frame'] );
		$opacity = max( 10, min( 100, (int) $atts['opacity'] ) ) / 100;
		$id      = 'crc-scroll-hint-' . ( ++self::$count );

		$this->enqueue_script();

		// One line in two halves, each from the bottom middle out and up a side; scroll-hint.js draws them to the width.
		$lines = $frame ? sprintf(
			'<svg class="crc-scroll-hint-lines" aria-hidden="true" focusable="false"><defs><linearGradient id="%1$s-fade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity="0"/><stop offset="0.7" stop-color="currentColor" stop-opacity="0.85"/><stop offset="1" stop-color="currentColor"/></linearGradient></defs><path class="crc-scroll-hint-line" pathLength="1" stroke="url(#%1$s-fade)"/><path class="crc-scroll-hint-line" pathLength="1" stroke="url(#%1$s-fade)"/></svg>',
			esc_attr( $id )
		) : '';

		return sprintf(
			'<div class="crc-scroll-hint-wrap%1$s%2$s%3$s" id="%4$s" style="--crc-scroll-hint-gap: %5$dpx; --crc-scroll-hint-height: %6$dpx; --crc-scroll-hint-opacity: %13$s;" data-radius="%7$s" data-target="%8$s" data-offset="%9$d"><div class="crc-scroll-hint-frame">%10$s<div class="crc-scroll-hint" role="button" tabindex="0" aria-label="%11$s" data-crc-scroll-hint>%12$s</div></div></div>',
			Shortcodes::is_on( $atts['pin'] ) ? ' is-pinned' : '',
			'dark' === strtolower( trim( (string) $atts['color'] ) ) ? ' is-dark' : '',
			$frame ? ' has-frame' : '',
			esc_attr( $id ),
			$gap,
			$height,
			esc_attr( $radius ),
			esc_attr( trim( (string) $atts['target'] ) ),
			$offset,
			$lines, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr( $atts['label'] ),
			str_repeat( '<span class="crc-scroll-hint-arrow"></span>', 3 ),
			esc_attr( number_format( $opacity, 2, '.', '' ) )
		);
	}
}
