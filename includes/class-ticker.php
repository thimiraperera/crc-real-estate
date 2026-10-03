<?php
/**
 * Keyword ticker.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_ticker]: a row of keywords, such as Houses · Villas · Apartments,
 * that moves across the page on its own, over the full width. It slows down
 * and stops while the mouse is over it, and can be dragged and thrown.
 *
 * The keywords, the speed and the direction are set in Listings → Settings,
 * where the keywords can also be imported and exported as a JSON file.
 */
final class Ticker {

	const SHORTCODE = 'crc_ticker';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Keyword ticker', 'crc-real-estate' ),
				'description' => __( 'A row of keywords, such as Houses · Villas · Apartments, that moves across the page on its own, over the full width of the window. It slows down and stops while the mouse is over it, and people can drag it and throw it. The keywords, the speed and the direction are set in Listings → Settings, where they can also be imported from a JSON file. The words use the site\'s H3 style.', 'crc-real-estate' ),
				'attributes'  => array(
					'speed'     => array(
						'default'     => '',
						'description' => __( 'How fast it moves here, in pixels a second, from 5 to 400. Leave it out to use the speed from Settings.', 'crc-real-estate' ),
					),
					'direction' => array(
						'default'     => '',
						'description' => __( 'Which way it moves here: left (right to left) or right (left to right). Leave it out to use the direction from Settings.', 'crc-real-estate' ),
					),
					'width'     => array(
						'default'     => 'full',
						'description' => __( 'full to span the whole width of the window, even inside a narrower container, or container to fill only the container it is in.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' speed="80"]',
					'[' . self::SHORTCODE . ' direction="right"]',
					'[' . self::SHORTCODE . ' width="container"]',
				),
			)
		);
	}

	/**
	 * Registers the script. The styles are in frontend.css, which every page
	 * loads, so the ticker never shows unstyled while the page loads.
	 */
	public function register_assets() {
		wp_register_script( 'crc-re-ticker', CRC_RE_URL . 'assets/js/ticker.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the script in Elementor's editor, so a ticker added there moves straight away.
	 */
	public function enqueue_script() {
		$this->register_assets();
		wp_enqueue_script( 'crc-re-ticker' );
	}

	/**
	 * Renders [crc_ticker].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts     = Shortcodes::atts( self::SHORTCODE, $atts );
		$keywords = Settings::sanitize_keywords( Settings::ticker( 'keywords' ) );

		if ( ! $keywords ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'Add the keywords in Listings → Settings, under Keyword ticker.', 'crc-real-estate' ) );
		}

		$speed     = Settings::sanitize_speed( $atts['speed'], Settings::sanitize_speed( Settings::ticker( 'speed' ) ) );
		$direction = in_array( strtolower( trim( (string) $atts['direction'] ) ), array( 'left', 'right' ), true ) ? strtolower( trim( (string) $atts['direction'] ) ) : ( 'right' === Settings::ticker( 'direction' ) ? 'right' : 'left' );
		$full      = 'container' !== strtolower( trim( (string) $atts['width'] ) );
		$items     = '';

		foreach ( $keywords as $keyword ) {
			$items .= sprintf(
				'<h3 class="crc-ticker-item"><span class="crc-ticker-dot" aria-hidden="true">&middot;</span><span class="crc-ticker-text">%s</span></h3>',
				esc_html( $keyword )
			);
		}

		wp_enqueue_script( 'crc-re-ticker' );

		return sprintf(
			'<div class="crc-ticker%1$s" data-crc-ticker data-speed="%2$d" data-direction="%3$s" role="region" aria-label="%4$s" tabindex="0"><div class="crc-ticker-track"><div class="crc-ticker-group">%5$s</div></div></div>',
			$full ? ' crc-ticker-full' : '',
			$speed,
			esc_attr( $direction ),
			esc_attr__( 'Keywords', 'crc-real-estate' ),
			$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);
	}
}
