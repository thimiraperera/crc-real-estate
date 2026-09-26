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
	 * Registers the plugin's features.
	 */
	private function __construct() {
		( new Updater( CRC_RE_FILE ) )->register();
		( new Assets() )->hooks();
		( new Post_Type() )->hooks();
		( new Taxonomy() )->hooks();
		( new Views() )->hooks();
		( new Popup() )->hooks();
		( new Sections\Title() )->hooks();
		( new Sections\Gallery() )->hooks();
		( new Sections\Description() )->hooks();
		( new Sections\Price_Card() )->hooks();
		( new Sections\Overview() )->hooks();
		( new Sections\Features() )->hooks();
		( new Admin\Gallery_Box() )->hooks();
		( new Admin\Price_Box() )->hooks();
		( new Admin\Contact_Box() )->hooks();
		( new Admin\Overview_Box() )->hooks();
		( new Admin\Features_Box() )->hooks();
		( new Admin\Views_Box() )->hooks();
		( new Admin\Publish_Rules() )->hooks();
		( new Admin\Info_Page() )->hooks();
		( new Admin\Settings_Page() )->hooks();

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( Installer::class, 'maybe_upgrade' ), 20 );
	}

	/**
	 * Loads translations from the plugin's languages folder.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'crc-real-estate', false, dirname( CRC_RE_BASENAME ) . '/languages' );
	}
}
