<?php
/**
 * Main plugin class.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Starts the plugin and loads its features.
 */
final class Plugin {

	/**
	 * The single instance of the plugin.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the plugin instance, starting it on the first call.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the plugin's hooks.
	 */
	private function __construct() {
		( new Updater( CRC_RE_FILE ) )->register();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads translations from the plugin's languages folder.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'crc-real-estate', false, dirname( CRC_RE_BASENAME ) . '/languages' );
	}
}
