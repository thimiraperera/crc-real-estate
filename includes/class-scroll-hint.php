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
 * a title on phones. Arrows that light up one after another, a mouse with a
 * moving wheel, or a line with a light running down it, in white fading to
 * clear. In an Elementor widget it sits at the bottom of its section, in the
 * middle. A click or tap scrolls smoothly to what is under the section, and
 * it fades away once the page has been scrolled.
 */
final class Scroll_Hint {

	const SHORTCODE = 'crc_scroll_hint';

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
				'description' => __( 'A small moving sign without words that tells visitors there is more below, for example at the bottom of a hero section that only has a title and a subtitle on phones. It can be arrows that light up one after another, a mouse with its wheel moving down, or a line with a light running down it, in white that fades to clear. Put it in a Shortcode widget anywhere in the section: it sits at the bottom of the section, in the middle, by itself. A click or tap scrolls smoothly to what is under the section, and it fades away once the page has been scrolled. To show it on phones only, hide the widget on desktop and tablet in Elementor (Advanced, then Responsive). Visitors whose device asks for less motion see it standing still.', 'crc-real-estate' ),
				'attributes'  => array(
					'style'  => array(
						'default'     => 'arrows',
						'description' => __( 'arrows (three arrows lighting up one after another, downwards), mouse (a mouse with its wheel moving down) or line (a thin line with a light running down it).', 'crc-real-estate' ),
					),
					'color'  => array(
						'default'     => 'light',
						'description' => __( 'light for white, on dark photos and colours, or dark for a dark grey, on light backgrounds.', 'crc-real-estate' ),
					),
					'pin'    => array(
						'default'     => 'yes',
						'description' => __( 'yes to sit at the bottom of its section, in the middle, wherever the widget is placed in it; no to stay where it is placed.', 'crc-real-estate' ),
					),
					'bottom' => array(
						'default'     => '24',
						'description' => __( 'How far above the bottom of the section it sits, in pixels.', 'crc-real-estate' ),
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
					'[' . self::SHORTCODE . ' style="mouse"]',
					'[' . self::SHORTCODE . ' style="line" bottom="32"]',
					'[' . self::SHORTCODE . ' target="#search" offset="80"]',
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
		$atts   = Shortcodes::atts( self::SHORTCODE, $atts );
		$style  = in_array( $atts['style'], array( 'arrows', 'mouse', 'line' ), true ) ? $atts['style'] : 'arrows';
		$bottom = max( 0, min( 400, (int) $atts['bottom'] ) );
		$offset = max( 0, min( 400, (int) $atts['offset'] ) );

		$this->enqueue_script();

		if ( 'mouse' === $style ) {
			$inner = '<span class="crc-scroll-hint-mouse"><span class="crc-scroll-hint-wheel"></span></span>';
		} elseif ( 'line' === $style ) {
			$inner = '<span class="crc-scroll-hint-track"><span class="crc-scroll-hint-light"></span></span>';
		} else {
			$inner = str_repeat( '<span class="crc-scroll-hint-arrow"></span>', 3 );
		}

		return sprintf(
			'<div class="crc-scroll-hint-wrap%1$s%2$s" style="--crc-scroll-hint-bottom: %3$dpx;" data-target="%4$s" data-offset="%5$d"><div class="crc-scroll-hint crc-scroll-hint-%6$s" role="button" tabindex="0" aria-label="%7$s" data-crc-scroll-hint>%8$s</div></div>',
			Shortcodes::is_on( $atts['pin'] ) ? ' is-pinned' : '',
			'dark' === strtolower( trim( (string) $atts['color'] ) ) ? ' is-dark' : '',
			$bottom,
			esc_attr( trim( (string) $atts['target'] ) ),
			$offset,
			esc_attr( $style ),
			esc_attr( $atts['label'] ),
			$inner
		);
	}
}
