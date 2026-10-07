<?php
/**
 * Category carousel.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_category_carousel]: a row of square cards, each with a picture, a title
 * and a View Properties button, that people move through with the arrows or
 * by dragging with the mouse. On computers and tablets the cards line up with
 * the container on the left and run on to the edge of the window on the right;
 * phones show one card at a time, with dots under it instead of arrows.
 *
 * The cards are set in Listings → Widgets.
 */
final class Category_Carousel {

	const SHORTCODE = 'crc_category_carousel';
	const OPTION    = 'crc_re_carousel';
	const CARDS_MAX = 50;
	const SPEED     = 0.6;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION, array( $this, 'clear_cache' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'clear_cache' ) );
	}

	/**
	 * Asks page speed plugins not to hold the script back, so the arrows work
	 * as soon as the page shows.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( 'crc-re-carousel' !== $handle || false !== strpos( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
	}

	/**
	 * Clears the LiteSpeed page cache when the cards change, so visitors see
	 * them straight away. Does nothing without LiteSpeed Cache.
	 */
	public function clear_cache() {
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Category carousel', 'crc-real-estate' ),
				'description' => __( 'A row of square cards, each with a picture, a title and a View Properties button, that people move through with the arrows or by dragging with the mouse, and that can move on by themselves. On computers and tablets the cards line up with the container on the left and run on to the edge of the window on the right, so people can see there are more. Phones show one card at a time: people swipe, or tap the dots under it. The cards, how fast they slide and how often they move on by themselves are set in Listings → Widgets.', 'crc-real-estate' ),
				'attributes'  => array(
					'width'    => array(
						'default'     => 'bleed',
						'description' => __( 'bleed to let the cards run on to the right edge of the window on computers and tablets, or container to keep them inside the container.', 'crc-real-estate' ),
					),
					'speed'    => array(
						'default'     => '',
						'description' => __( 'How long the cards take to slide over here, in seconds, from 0.1 to 3. Leave it out to use the slide speed from the Category carousel tab in Listings → Widgets.', 'crc-real-estate' ),
					),
					'autoplay' => array(
						'default'     => '',
						'description' => __( 'How often the cards move on by themselves here, in seconds; 0 keeps them still. Leave it out to use the setting from the Category carousel tab in Listings → Widgets.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' width="container"]',
					'[' . self::SHORTCODE . ' speed="1" autoplay="5"]',
				),
			)
		);
	}

	/**
	 * Registers the script. The styles are in frontend.css, which every page loads.
	 */
	public function register_assets() {
		wp_register_script( 'crc-re-carousel', CRC_RE_URL . 'assets/js/carousel.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the script in Elementor's editor, so a carousel added there works straight away.
	 */
	public function enqueue_script() {
		$this->register_assets();
		wp_enqueue_script( 'crc-re-carousel' );
	}

	/**
	 * Cleans the cards sent from Listings → Widgets.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$rows  = is_array( $input ) && isset( $input['cards'] ) && is_array( $input['cards'] ) ? $input['cards'] : array();
		$cards = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || count( $cards ) >= self::CARDS_MAX ) {
				continue;
			}

			$text = function ( $key ) use ( $row ) {
				return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? trim( sanitize_text_field( (string) $row[ $key ] ) ) : '';
			};

			$image = isset( $row['image'] ) && is_scalar( $row['image'] ) ? absint( $row['image'] ) : 0;
			$card  = array(
				'image' => $image && wp_attachment_is_image( $image ) ? $image : 0,
				'title' => $text( 'title' ),
				'link'  => esc_url_raw( $text( 'link' ) ),
			);

			if ( $card['image'] || '' !== $card['title'] || '' !== $card['link'] ) {
				$cards[] = $card;
			}
		}

		return array(
			'cards'    => $cards,
			'speed'    => self::sanitize_speed( isset( $input['speed'] ) ? $input['speed'] : '' ),
			'autoplay' => self::sanitize_autoplay( isset( $input['autoplay'] ) ? $input['autoplay'] : '' ),
		);
	}

	/**
	 * A slide speed: seconds from 0.1 to 3, or the usual 0.6.
	 *
	 * @param mixed $value Seconds.
	 * @return float
	 */
	public static function sanitize_speed( $value ) {
		$value = is_scalar( $value ) ? str_replace( ',', '.', trim( (string) $value ) ) : '';

		return is_numeric( $value ) && (float) $value > 0 ? round( max( 0.1, min( 3, (float) $value ) ), 2 ) : self::SPEED;
	}

	/**
	 * How often the cards move on by themselves: seconds from 1 to 60, or 0 for never.
	 *
	 * @param mixed $value Seconds.
	 * @return float
	 */
	public static function sanitize_autoplay( $value ) {
		$value = is_scalar( $value ) ? str_replace( ',', '.', trim( (string) $value ) ) : '';

		return is_numeric( $value ) && (float) $value > 0 ? round( max( 1, min( 60, (float) $value ) ), 1 ) : 0.0;
	}

	/**
	 * A saved movement setting.
	 *
	 * @param string $key "speed" (seconds a slide takes) or "autoplay" (seconds between moves; 0 for never).
	 * @return float
	 */
	public static function setting( $key ) {
		$saved = get_option( self::OPTION, array() );
		$value = is_array( $saved ) && isset( $saved[ $key ] ) ? $saved[ $key ] : '';

		return 'autoplay' === $key ? self::sanitize_autoplay( $value ) : self::sanitize_speed( $value );
	}

	/**
	 * The saved cards.
	 *
	 * @return array[]
	 */
	public static function cards() {
		$saved = get_option( self::OPTION, array() );
		$cards = is_array( $saved ) && isset( $saved['cards'] ) && is_array( $saved['cards'] ) ? $saved['cards'] : array();

		return array_values(
			array_filter(
				array_map(
					function ( $card ) {
						return is_array( $card ) ? wp_parse_args( $card, array( 'image' => 0, 'title' => '', 'link' => '' ) ) : null;
					},
					$cards
				)
			)
		);
	}

	/**
	 * Renders [crc_category_carousel].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts  = Shortcodes::atts( self::SHORTCODE, $atts );
		$cards = self::cards();

		if ( ! $cards ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'Add the cards in Listings → Widgets, under Category carousel.', 'crc-real-estate' ) );
		}

		$items = '';
		$dots  = '';
		$count = count( $cards );

		foreach ( $cards as $index => $card ) {
			$image  = $card['image'] ? wp_get_attachment_image(
				(int) $card['image'],
				'large',
				false,
				array(
					'class'     => 'crc-carousel-image',
					'alt'       => '',
					'sizes'     => '(max-width: 767px) 100vw, (max-width: 1024px) 50vw, 33vw',
					'draggable' => 'false',
				)
			) : '';
			$button = '';

			if ( '' !== $card['link'] ) {
				$button = sprintf(
					'<div class="crc-carousel-button custom-btn-2-lite"><a class="elementor-button elementor-button-link" href="%1$s"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">%2$s</span><span class="elementor-button-icon">%3$s</span></span></a></div>',
					esc_url( $card['link'] ),
					esc_html__( 'View Properties', 'crc-real-estate' ),
					Icons::svg( 'arrow-right' )
				);
			}

			$items .= sprintf(
				'<li class="crc-carousel-card" role="group" aria-roledescription="%1$s" aria-label="%2$s">%3$s<div class="crc-carousel-content">%4$s%5$s</div></li>',
				esc_attr__( 'slide', 'crc-real-estate' ),
				/* translators: 1: card number, 2: number of cards. */
				esc_attr( sprintf( __( '%1$s of %2$s', 'crc-real-estate' ), $index + 1, $count ) ),
				$image,
				'' !== $card['title'] ? '<h6 class="crc-carousel-title">' . esc_html( $card['title'] ) . '</h6>' : '',
				$button
			);

			$dots .= $count < 2 ? '' : sprintf(
				'<span class="crc-carousel-dot%1$s" role="button" tabindex="0" aria-label="%2$s"%3$s></span>',
				0 === $index ? ' is-active' : '',
				/* translators: 1: card number, 2: number of cards. */
				esc_attr( sprintf( __( 'Show card %1$s of %2$s', 'crc-real-estate' ), $index + 1, $count ) ),
				0 === $index ? ' aria-current="true"' : ''
			);
		}

		wp_enqueue_script( 'crc-re-carousel' );

		$speed    = '' !== trim( (string) $atts['speed'] ) ? self::sanitize_speed( $atts['speed'] ) : self::setting( 'speed' );
		$autoplay = '' !== trim( (string) $atts['autoplay'] ) ? self::sanitize_autoplay( $atts['autoplay'] ) : self::setting( 'autoplay' );

		return sprintf(
			'<div class="crc-carousel%1$s" data-crc-carousel data-speed="%8$d" data-autoplay="%9$d" role="region" aria-roledescription="%2$s" aria-label="%3$s"><div class="crc-carousel-stage"><div class="crc-carousel-viewport"><ul class="crc-carousel-track">%4$s</ul></div>%5$s%6$s</div>%7$s</div>',
			'container' === strtolower( trim( (string) $atts['width'] ) ) ? '' : ' crc-carousel-bleed',
			esc_attr__( 'carousel', 'crc-real-estate' ),
			esc_attr__( 'Property categories', 'crc-real-estate' ),
			$items, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			Arrow::html( 'prev', __( 'Previous', 'crc-real-estate' ), 'crc-carousel-arrow crc-carousel-prev', true ),
			Arrow::html( 'next', __( 'Next', 'crc-real-estate' ), 'crc-carousel-arrow crc-carousel-next' ),
			'' !== $dots ? '<div class="crc-carousel-dots">' . $dots . '</div>' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			(int) round( $speed * 1000 ),
			(int) round( $autoplay * 1000 )
		);
	}
}
