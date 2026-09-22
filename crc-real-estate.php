<?php
/**
 * Plugin Name:       CRC Real Estate
 * Plugin URI:        https://github.com/thimiraperera/crc-real-estate
 * Description:       Real estate listing tools for WordPress.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            Thimira Perera
 * Author URI:        https://github.com/thimiraperera
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crc-real-estate
 * Domain Path:       /languages
 * Update URI:        https://github.com/thimiraperera/crc-real-estate
 *
 * @package CRC_Real_Estate
 */

defined( 'ABSPATH' ) || exit;

define( 'CRC_RE_FILE', __FILE__ );
define( 'CRC_RE_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ) )['Version'] );
define( 'CRC_RE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRC_RE_URL', plugin_dir_url( __FILE__ ) );
define( 'CRC_RE_BASENAME', plugin_basename( __FILE__ ) );

require_once CRC_RE_PATH . 'includes/class-updater.php';
require_once CRC_RE_PATH . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'CRC\RealEstate\Plugin', 'instance' ) );
