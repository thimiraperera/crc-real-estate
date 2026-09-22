<?php
/**
 * Plugin updates from GitHub.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Offers an update whenever the version on the repository's main branch is
 * higher than the installed one, and installs it from the branch archive.
 *
 * Works through the plugin's "Update URI" header, so WordPress.org never
 * offers updates for this plugin.
 */
final class Updater {

	const OWNER  = 'thimiraperera';
	const REPO   = 'crc-real-estate';
	const BRANCH = 'main';

	const CACHE_KEY     = 'crc_re_update_data';
	const CHANGELOG_KEY = 'crc_re_changelog';
	const CACHE_TTL     = 21600; // 6 hours.
	const RETRY_TTL     = 1800;  // 30 minutes after a failed check.

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Plugin basename, e.g. crc-real-estate/crc-real-estate.php.
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Sets up the updater for a plugin file.
	 *
	 * @param string $file Absolute path to the main plugin file.
	 */
	public function __construct( $file ) {
		$this->file     = $file;
		$this->basename = plugin_basename( $file );
	}

	/**
	 * Registers the update hooks.
	 */
	public function register() {
		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Tells WordPress which version is available on GitHub.
	 *
	 * @param array|false $update      Update data from other handlers.
	 * @param array       $plugin_data Installed plugin headers.
	 * @param string      $plugin_file Plugin basename being checked.
	 * @return array|false
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}

		$remote = $this->get_remote_data();

		if ( empty( $remote['Version'] ) ) {
			return $update;
		}

		return array(
			'slug'         => $this->slug(),
			'version'      => $remote['Version'],
			'url'          => $this->repo_url(),
			'package'      => $this->package_url(),
			'requires'     => isset( $remote['RequiresWP'] ) ? $remote['RequiresWP'] : '',
			'requires_php' => isset( $remote['RequiresPHP'] ) ? $remote['RequiresPHP'] : '',
			'tested'       => isset( $remote['Tested'] ) ? $remote['Tested'] : '',
		);
	}

	/**
	 * Fills the "View details" window for this plugin.
	 *
	 * @param false|object|array $result Result from other handlers.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->slug() !== $args->slug ) {
			return $result;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$local  = get_plugin_data( $this->file, false, false );
		$remote = $this->get_remote_data();

		return (object) array(
			'name'          => $local['Name'],
			'slug'          => $this->slug(),
			'version'       => ! empty( $remote['Version'] ) ? $remote['Version'] : $local['Version'],
			'author'        => sprintf( '<a href="%s">%s</a>', esc_url( $local['AuthorURI'] ), esc_html( $local['Author'] ) ),
			'homepage'      => $this->repo_url(),
			'requires'      => isset( $remote['RequiresWP'] ) ? $remote['RequiresWP'] : $local['RequiresWP'],
			'requires_php'  => isset( $remote['RequiresPHP'] ) ? $remote['RequiresPHP'] : $local['RequiresPHP'],
			'tested'        => isset( $remote['Tested'] ) ? $remote['Tested'] : '',
			'download_link' => $this->package_url(),
			'sections'      => array(
				'description' => wpautop( esc_html( $local['Description'] ) ),
				'changelog'   => $this->get_changelog(),
			),
		);
	}

	/**
	 * Renames the unpacked GitHub folder (crc-real-estate-main) to the
	 * installed plugin folder, so the update replaces the right plugin.
	 *
	 * @param string|\WP_Error $source        Unpacked source folder.
	 * @param string           $remote_source Folder the package was unpacked into.
	 * @param \WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array            $hook_extra    Extra details about the upgrade.
	 * @return string|\WP_Error
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] ) || $this->basename !== $hook_extra['plugin'] ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . $this->slug() . '/';

		if ( trailingslashit( $source ) === $target ) {
			return $source;
		}

		if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $target, true ) ) {
			return new \WP_Error( 'crc_re_update_folder', __( 'The update could not be moved into the plugin folder.', 'crc-real-estate' ) );
		}

		return $target;
	}

	/**
	 * Forgets the cached GitHub data after plugins are updated.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Details about the finished upgrade.
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_site_transient( self::CACHE_KEY );
			delete_site_transient( self::CHANGELOG_KEY );
		}
	}

	/**
	 * Adds a "Check for updates" link under the plugin on the Plugins screen.
	 *
	 * @param string[] $links Links shown under the plugin description.
	 * @param string   $file  Plugin basename of the row.
	 * @return string[]
	 */
	public function row_meta( $links, $file ) {
		if ( $file === $this->basename && current_user_can( 'update_plugins' ) ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( self_admin_url( 'update-core.php?force-check=1' ) ),
				esc_html__( 'Check for updates', 'crc-real-estate' )
			);
		}

		return $links;
	}

	/**
	 * Reads the plugin headers from the main branch, with caching.
	 *
	 * @return array Header values, or an empty array when GitHub can't be reached.
	 */
	private function get_remote_data() {
		$data = get_site_transient( self::CACHE_KEY );

		if ( is_array( $data ) && ! $this->is_forced_check() ) {
			return $data;
		}

		$data = array();
		$body = $this->fetch( basename( $this->file ) );

		if ( '' !== $body ) {
			$headers = $this->parse_headers( $body );

			if ( ! empty( $headers['Version'] ) ) {
				$data = $headers;
			}
		}

		set_site_transient( self::CACHE_KEY, $data, $data ? self::CACHE_TTL : self::RETRY_TTL );

		return $data;
	}

	/**
	 * Builds the changelog HTML from the main branch readme.txt.
	 *
	 * @return string
	 */
	private function get_changelog() {
		$html = get_site_transient( self::CHANGELOG_KEY );

		if ( false === $html || $this->is_forced_check() ) {
			$html = '';
			$text = $this->fetch( 'readme.txt' );

			if ( preg_match( '/==\s*Changelog\s*==\s*(.*?)(?=\n==\s|\z)/s', $text, $match ) ) {
				$html = $this->format_changelog( $match[1] );
			}

			set_site_transient( self::CHANGELOG_KEY, $html, $html ? self::CACHE_TTL : self::RETRY_TTL );
		}

		if ( '' === $html ) {
			$html = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $this->repo_url() . '/commits/' . self::BRANCH ),
				esc_html__( 'See the latest changes on GitHub.', 'crc-real-estate' )
			);
		}

		return $html;
	}

	/**
	 * Turns readme.txt changelog entries into HTML.
	 *
	 * @param string $text Changelog section of readme.txt.
	 * @return string
	 */
	private function format_changelog( $text ) {
		$html    = '';
		$in_list = false;

		foreach ( preg_split( '/\R/', trim( $text ) ) as $line ) {
			$line = trim( $line );

			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $match ) ) {
				$html   .= ( $in_list ? '</ul>' : '' ) . '<h4>' . esc_html( $match[1] ) . '</h4><ul>';
				$in_list = true;
			} elseif ( $in_list && preg_match( '/^[*-]\s*(.+)$/', $line, $match ) ) {
				$html .= '<li>' . esc_html( $match[1] ) . '</li>';
			}
		}

		return $in_list ? $html . '</ul>' : $html;
	}

	/**
	 * Reads plugin headers from the text of a plugin file.
	 *
	 * @param string $text File contents.
	 * @return array
	 */
	private function parse_headers( $text ) {
		$fields = array(
			'Version'     => 'Version',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
			'Tested'      => 'Tested up to',
		);

		$text   = substr( $text, 0, 8192 );
		$values = array();

		foreach ( $fields as $key => $label ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $text, $match ) ) {
				$value = trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) );

				if ( '' !== $value ) {
					$values[ $key ] = $value;
				}
			}
		}

		return $values;
	}

	/**
	 * Downloads a text file from the main branch.
	 *
	 * @param string $path File path inside the repository.
	 * @return string File contents, or an empty string on failure.
	 */
	private function fetch( $path ) {
		$url      = sprintf( 'https://raw.githubusercontent.com/%s/%s/%s/%s', self::OWNER, self::REPO, self::BRANCH, $path );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Whether the user asked WordPress to check for updates right now.
	 *
	 * @return bool
	 */
	private function is_forced_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by WordPress's own "Check again" link.
		return is_admin() && ! empty( $_GET['force-check'] );
	}

	/**
	 * Installed plugin folder name.
	 *
	 * @return string
	 */
	private function slug() {
		return dirname( $this->basename );
	}

	/**
	 * Repository page on GitHub.
	 *
	 * @return string
	 */
	private function repo_url() {
		return sprintf( 'https://github.com/%s/%s', self::OWNER, self::REPO );
	}

	/**
	 * Zip archive of the main branch.
	 *
	 * @return string
	 */
	private function package_url() {
		return sprintf( '%s/archive/refs/heads/%s.zip', $this->repo_url(), self::BRANCH );
	}
}
