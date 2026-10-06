<?php
/**
 * Activation, deactivation and version upgrades.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the listing categories and districts, keeps permalinks in step
 * with the plugin's post types, and works out the search's numbers.
 */
final class Installer {

	const OPTION = 'crc_re_version';

	/**
	 * Runs when the plugin is activated.
	 */
	public static function activate() {
		( new Post_Type() )->register();
		( new Taxonomy() )->register();
		( new District() )->register();
		( new Town() )->register();
		Taxonomy::create_terms();
		District::create_terms();
		Listing_Index::backfill();
		flush_rewrite_rules();
		update_option( self::OPTION, CRC_RE_VERSION );
	}

	/**
	 * Runs when the plugin is deactivated.
	 */
	public static function deactivate() {
		Importer::stop_all();
		unregister_post_type( Post_Type::NAME );
		flush_rewrite_rules();
	}

	/**
	 * Runs once after each update, because updates from GitHub don't run the
	 * activation hook: adds any new categories and districts, works out the
	 * search's numbers and refreshes permalinks.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) === CRC_RE_VERSION ) {
			return;
		}

		Taxonomy::create_terms();
		District::create_terms();
		Listing_Index::backfill();
		flush_rewrite_rules();
		update_option( self::OPTION, CRC_RE_VERSION );
	}
}
