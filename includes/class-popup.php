<?php
/**
 * Popups.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The popup used on listing pages, e.g. by See More: a top bar with the title
 * on the left and a close button on the right, and content that scrolls
 * below it. Every section uses this one popup, so all popups look and move
 * the same.
 *
 * A section makes one with new_id(), render() and a button carrying opener().
 */
final class Popup {

	/**
	 * Popups made on this page so far, for unique ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Registers the popup files.
	 */
	public static function register_assets() {
		wp_register_style( 'crc-re-popup', CRC_RE_URL . 'assets/css/popup.css', array(), CRC_RE_VERSION );
		wp_register_script(
			'crc-re-popup',
			CRC_RE_URL . 'assets/js/popup.js',
			array(),
			CRC_RE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Loads the popup styles in the page head on listing pages.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) ) {
			wp_enqueue_style( 'crc-re-popup' );
		}
	}

	/**
	 * Loads the popup styles and script.
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'crc-re-popup', 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( 'crc-re-popup' );
		wp_enqueue_script( 'crc-re-popup' );
	}

	/**
	 * A new popup id, unique on the page.
	 *
	 * @param string $name What the popup shows, e.g. "overview".
	 * @return string
	 */
	public static function new_id( $name ) {
		return 'crc-popup-' . sanitize_key( $name ) . '-' . ( ++self::$count );
	}

	/**
	 * Attributes that make a button open the popup.
	 *
	 * @param string $id Popup id.
	 * @return string
	 */
	public static function opener( $id ) {
		return sprintf( ' data-crc-popup="%1$s" aria-haspopup="dialog" aria-controls="%1$s"', esc_attr( $id ) );
	}

	/**
	 * The popup. It stays hidden until its button is clicked.
	 *
	 * @param string $id    Popup id from new_id().
	 * @param string $title Title in the top bar.
	 * @param string $body  Content, already escaped.
	 * @return string
	 */
	public static function render( $id, $title, $body ) {
		self::enqueue();

		return sprintf(
			'<dialog class="crc-popup" id="%1$s" aria-labelledby="%1$s-title">'
				. '<div class="crc-popup-backdrop" data-crc-popup-close></div>'
				. '<div class="crc-popup-dialog">'
					. '<div class="crc-popup-header">'
						. '<h6 class="crc-popup-title" id="%1$s-title">%2$s</h6>'
						. '<button type="button" class="crc-popup-close" data-crc-popup-close aria-label="%3$s">%4$s</button>'
					. '</div>'
					. '<div class="crc-popup-body">%5$s</div>'
				. '</div>'
			. '</dialog>',
			esc_attr( $id ),
			esc_html( $title ),
			esc_attr__( 'Close', 'crc-real-estate' ),
			Icons::svg( 'close', 'crc-popup-close-icon' ),
			$body
		);
	}
}
