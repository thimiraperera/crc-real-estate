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
 * and a button, that people move through with the arrows, by swiping, or by
 * dragging with the mouse. The cards line up with the container on the left
 * and run on to the edge of the window on the right.
 *
 * The cards are set in Listings → Widgets.
 */
final class Category_Carousel {

	const SHORTCODE = 'crc_category_carousel';
	const OPTION    = 'crc_re_carousel';
	const CARDS_MAX = 50;

	const ARROW_LEFT  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 350 350" aria-hidden="true" focusable="false"><path d="M175,350l32.11-32.11L86.92,197.71H350V152.29H86.92L207.11,32.11,175,0,0,175Z"/></svg>';
	const ARROW_RIGHT = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 350 350" aria-hidden="true" focusable="false"><path d="m175 0-32.11 32.11 120.19 120.18H0v45.42h263.08L142.89 317.89 175 350l175-175Z"/></svg>';

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
				'description' => __( 'A row of square cards, each with a picture, a title and a button, that people move through with the arrows, by swiping on a phone, or by dragging with the mouse. The cards line up with the container on the left and run on to the edge of the window on the right, so people can see there are more. The cards are set in Listings → Widgets.', 'crc-real-estate' ),
				'attributes'  => array(
					'width' => array(
						'default'     => 'bleed',
						'description' => __( 'bleed to let the cards run on to the right edge of the window, or container to keep them inside the container.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' width="container"]',
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
				'image'  => $image && wp_attachment_is_image( $image ) ? $image : 0,
				'title'  => $text( 'title' ),
				'button' => $text( 'button' ),
				'link'   => esc_url_raw( $text( 'link' ) ),
			);

			if ( $card['image'] || '' !== $card['title'] || '' !== $card['link'] ) {
				$cards[] = $card;
			}
		}

		return array( 'cards' => $cards );
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
						return is_array( $card ) ? wp_parse_args( $card, array( 'image' => 0, 'title' => '', 'button' => '', 'link' => '' ) ) : null;
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
		$count = count( $cards );

		foreach ( $cards as $index => $card ) {
			$image  = $card['image'] ? wp_get_attachment_image(
				(int) $card['image'],
				'large',
				false,
				array(
					'class'     => 'crc-carousel-image',
					'alt'       => '',
					'sizes'     => '(max-width: 767px) 90vw, (max-width: 1024px) 50vw, 33vw',
					'draggable' => 'false',
				)
			) : '';
			$button = '';

			if ( '' !== $card['link'] ) {
				$button = sprintf(
					'<div class="crc-carousel-button custom-btn-2-lite"><a class="elementor-button elementor-button-link" href="%1$s"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">%2$s</span><span class="elementor-button-icon">%3$s</span></span></a></div>',
					esc_url( $card['link'] ),
					esc_html( '' !== $card['button'] ? $card['button'] : __( 'View Properties', 'crc-real-estate' ) ),
					self::ARROW_RIGHT
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
		}

		wp_enqueue_script( 'crc-re-carousel' );

		return sprintf(
			'<div class="crc-carousel%1$s" data-crc-carousel role="region" aria-roledescription="%2$s" aria-label="%3$s"><div class="crc-carousel-viewport"><ul class="crc-carousel-track">%4$s</ul></div><button type="button" class="crc-carousel-arrow crc-carousel-prev" aria-label="%5$s">%6$s</button><button type="button" class="crc-carousel-arrow crc-carousel-next" aria-label="%7$s">%8$s</button></div>',
			'container' === strtolower( trim( (string) $atts['width'] ) ) ? '' : ' crc-carousel-bleed',
			esc_attr__( 'carousel', 'crc-real-estate' ),
			esc_attr__( 'Property categories', 'crc-real-estate' ),
			$items, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr__( 'Previous', 'crc-real-estate' ),
			self::ARROW_LEFT,
			esc_attr__( 'Next', 'crc-real-estate' ),
			self::ARROW_RIGHT
		);
	}
}
