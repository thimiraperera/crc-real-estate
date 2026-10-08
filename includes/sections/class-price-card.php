<?php
/**
 * Price card section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Icons;
use CRC\RealEstate\Listing_Status;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Shortcodes;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * The card beside a listing: Share button, what the listing is ("Land for
 * sale"), its price, the price per perch for land, and Call and WhatsApp
 * buttons.
 */
final class Price_Card {

	const SHORTCODE      = 'crc_listing_price_card';
	const PRICE_META     = '_crc_price';
	const PER_PERCH_META = '_crc_price_per_perch';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Registers the fields and the shortcode.
	 */
	public function register() {
		$fields = array(
			self::PRICE_META            => array( __CLASS__, 'sanitize_amount' ),
			self::PER_PERCH_META        => array( __CLASS__, 'sanitize_amount' ),
			Settings::PHONE_META        => array( Settings::class, 'sanitize_number' ),
			Settings::WHATSAPP_META     => array( Settings::class, 'sanitize_number' ),
			Settings::CALL_TEXT_META    => array( Settings::class, 'sanitize_text' ),
			Settings::MESSAGE_TEXT_META => array( Settings::class, 'sanitize_text' ),
		);

		foreach ( $fields as $key => $sanitize ) {
			register_post_meta(
				Post_Type::NAME,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Price card', 'crc-real-estate' ),
				'description' => __( 'The Share button, the category caption (for example "Land for sale", changed on the Listing Categories screen), the price, the price per perch for land, and the Call and WhatsApp buttons. The numbers come from the listing, or from Settings when the listing has none of its own.', 'crc-real-estate' ),
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
	 * Registers the front-end files.
	 */
	public function register_assets() {
		wp_register_style( 'crc-re-price-card', CRC_RE_URL . 'assets/css/price-card.css', array(), CRC_RE_VERSION );
		wp_register_script(
			'crc-re-share',
			CRC_RE_URL . 'assets/js/share.js',
			array(),
			CRC_RE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'crc-re-share',
			'window.crcReShare = ' . wp_json_encode( array( 'copied' => __( 'Link copied', 'crc-real-estate' ) ) ) . ';',
			'before'
		);
	}

	/**
	 * Loads the styles in the page head on listing pages.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) ) {
			$this->enqueue_style();
		}
	}

	/**
	 * Loads the price card styles.
	 */
	public function enqueue_style() {
		if ( ! wp_style_is( 'crc-re-price-card', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-price-card' );
	}

	/**
	 * Keeps whole numbers only: "10,000,000" becomes 10000000 and
	 * "3,125.50" becomes 3125. Zero counts as empty.
	 *
	 * @param mixed $value Typed amount.
	 * @return string Digits, or an empty string.
	 */
	public static function sanitize_amount( $value ) {
		$value = preg_replace( '/[,\s]/', '', (string) $value );
		$value = preg_replace( '/\..*$/', '', $value );

		return ltrim( preg_replace( '/\D/', '', $value ), '0' );
	}

	/**
	 * A listing's price in rupees, as digits.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function price( $post_id ) {
		return self::sanitize_amount( get_post_meta( $post_id, self::PRICE_META, true ) );
	}

	/**
	 * A listing's price per perch, as digits.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function price_per_perch( $post_id ) {
		return self::sanitize_amount( get_post_meta( $post_id, self::PER_PERCH_META, true ) );
	}

	/**
	 * Currency shown before prices.
	 *
	 * @return string
	 */
	public static function currency() {
		/**
		 * Filters the currency shown before prices.
		 *
		 * @param string $currency Currency, "Rs." by default.
		 */
		return apply_filters( 'crc_re_currency', 'Rs.' );
	}

	/**
	 * An amount written out, e.g. "Rs. 10,000,000".
	 *
	 * @param string $digits Amount as digits.
	 * @return string
	 */
	public static function money( $digits ) {
		return self::currency() . ' ' . number_format_i18n( (float) $digits );
	}

	/**
	 * Renders [crc_listing_price_card].
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

		$this->enqueue_style();
		wp_enqueue_script( 'crc-re-share' );

		$title    = wp_strip_all_tags( get_post_field( 'post_title', $post ) );
		$url      = get_permalink( $post );
		$category = Taxonomy::listing_category( $post->ID );

		$html  = '<div class="crc-price' . ( Listing_Status::gone( $post->ID ) ? ' is-gone' : '' ) . '">';
		$html .= sprintf(
			'<button type="button" class="crc-share" data-url="%1$s" data-title="%2$s">%3$s<span class="crc-share-label" aria-live="polite">%4$s</span></button>',
			esc_url( $url ),
			esc_attr( $title ),
			Icons::svg( 'share', 'crc-share-icon' ),
			esc_html__( 'Share', 'crc-real-estate' )
		);

		$main = $this->price_lines( $post->ID, $category );

		if ( '' !== $main ) {
			$html .= '<div class="crc-price-main">' . $main . '</div>';
		}

		$html .= $this->contact( $post->ID, $title, $url );

		return $html . '</div>';
	}

	/**
	 * The category caption, the price, Sold or Rented when it has gone, and the price per perch.
	 *
	 * @param int        $post_id  Listing ID.
	 * @param array|null $category Listing category details.
	 * @return string
	 */
	private function price_lines( $post_id, $category ) {
		$html  = '';
		$price = self::price( $post_id );

		if ( $category ) {
			$html .= '<h6 class="crc-price-label">' . esc_html( $category['label'] ) . '</h6>';
		}

		if ( '' !== $price ) {
			$period = ( $category && '' !== $category['period'] ) ? '<span class="crc-price-period">' . esc_html( $category['period'] ) . '</span>' : '';
			$html  .= '<h2 class="crc-price-amount">' . esc_html( self::money( $price ) ) . $period . '</h2>';
		}

		// Sold, or Rented: the listing stays on the website, marked as gone.
		$gone = Listing_Status::gone( $post_id );

		if ( $gone ) {
			$html .= '<p class="crc-price-status crc-price-status-' . esc_attr( $gone['key'] ) . '">' . esc_html( $gone['label'] ) . '</p>';
		}

		$per_perch = self::price_per_perch( $post_id );

		if ( $category && $category['per_perch'] && '' !== $per_perch ) {
			/* translators: %s: price per perch, e.g. "Rs. 3,125". */
			$html .= '<h6 class="crc-price-per-perch">' . esc_html( sprintf( __( '%s per perch', 'crc-real-estate' ), self::money( $per_perch ) ) ) . '</h6>';
		}

		return $html;
	}

	/**
	 * "Contact for inquiries" with the Call and WhatsApp buttons.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $title   Listing title.
	 * @param string $url     Listing address.
	 * @return string
	 */
	private function contact( $post_id, $title, $url ) {
		$phone    = Settings::number_for( $post_id, 'phone' );
		$whatsapp = Settings::number_for( $post_id, 'whatsapp' );
		$buttons  = '';

		if ( '' !== $phone ) {
			$buttons .= '<div class="custom-btn-1-lite">' . $this->button( 'tel:' . self::dial( $phone ), Settings::button_text( $post_id, 'call' ), 'phone', false ) . '</div>';
		}

		if ( '' !== $whatsapp ) {
			/* translators: 1: listing title, 2: listing address. */
			$message  = sprintf( __( 'Hi, I\'m interested in this listing: %1$s %2$s', 'crc-real-estate' ), $title, $url );
			$link     = 'https://wa.me/' . preg_replace( '/\D/', '', $whatsapp ) . '?text=' . rawurlencode( $message );
			$buttons .= $this->button( $link, Settings::button_text( $post_id, 'message' ), 'whatsapp', true );
		}

		if ( '' === $buttons ) {
			return '';
		}

		return '<div class="crc-price-contact"><h6 class="crc-price-contact-title">' . esc_html__( 'Contact for inquiries', 'crc-real-estate' ) . '</h6><div class="crc-price-buttons">' . $buttons . '</div></div>';
	}

	/**
	 * A button in Elementor's button markup, so the site's button styles apply.
	 *
	 * @param string $href    Link.
	 * @param string $text    Button text.
	 * @param string $icon    Icon name.
	 * @param bool   $new_tab Open in a new tab.
	 * @return string
	 */
	private function button( $href, $text, $icon, $new_tab ) {
		return sprintf(
			'<a class="elementor-button crc-price-button" href="%1$s"%2$s><span class="elementor-button-content-wrapper"><span class="elementor-button-text">%3$s</span><span class="elementor-button-icon">%4$s</span></span></a>',
			esc_url( $href ),
			$new_tab ? ' target="_blank" rel="noopener"' : '',
			esc_html( $text ),
			Icons::svg( $icon )
		);
	}

	/**
	 * A number ready for a tel: link: digits, with + kept in front.
	 *
	 * @param string $number Phone number as typed.
	 * @return string
	 */
	private static function dial( $number ) {
		return ( 0 === strpos( trim( $number ), '+' ) ? '+' : '' ) . preg_replace( '/\D/', '', $number );
	}
}
