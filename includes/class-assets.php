<?php
/**
 * Site-wide front-end styles.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the small stylesheet with the plugin's shared classes (crc-card,
 * crc-description) on every page, so they work anywhere they are used.
 */
final class Assets {

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue' ) );
	}

	/**
	 * Loads the stylesheet.
	 */
	public function enqueue() {
		wp_enqueue_style( 'crc-re-frontend', CRC_RE_URL . 'assets/css/frontend.css', array(), CRC_RE_VERSION );
	}
}
