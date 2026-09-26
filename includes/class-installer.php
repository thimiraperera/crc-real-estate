<?php
/**
 * Activation, deactivation and version upgrades.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps permalinks in step with the plugin's post types.
 */
final class Installer {

	const OPTION = 'crc_re_version';

	/**
	 * Runs when the plugin is activated.
	 */
	public static function activate() {
		( new Post_Type() )->register();
		flush_rewrite_rules();
		update_option( self::OPTION, CRC_RE_VERSION );
	}

	/**
	 * Runs when the plugin is deactivated.
	 */
	public static function deactivate() {
		unregister_post_type( Post_Type::NAME );
		flush_rewrite_rules();
	}

	/**
	 * Refreshes permalinks once after an update, because updates from GitHub
	 * don't run the activation hook.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) === CRC_RE_VERSION ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::OPTION, CRC_RE_VERSION );
	}
}
