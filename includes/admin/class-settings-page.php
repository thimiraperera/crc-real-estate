<?php
/**
 * Settings page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Updater;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Settings: the default phone and WhatsApp numbers and button
 * texts, the map, and plugin updates.
 */
final class Settings_Page {

	const SLUG  = 'crc-real-estate-settings';
	const GROUP = 'crc_re_settings';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . CRC_RE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Address of the page.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG );
	}

	/**
	 * Adds a Settings link under the plugin on the Plugins screen.
	 *
	 * @param string[] $links Links under the plugin name.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( current_user_can( 'manage_options' ) ) {
			array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'crc-real-estate' ) ) );
		}

		return $links;
	}

	/**
	 * Loads the page's styles, and the window that shows what's new in an update.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( Post_Type::NAME . '_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		add_thickbox();
		wp_enqueue_script( 'plugin-install' );
	}

	/**
	 * Adds the page under Listings.
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'Settings', 'crc-real-estate' ),
			__( 'Settings', 'crc-real-estate' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the settings and their fields.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section(
			'crc_re_contact',
			__( 'Contact buttons', 'crc-real-estate' ),
			function () {
				echo '<p>' . esc_html__( 'These numbers and button texts are used on every listing, unless a listing has its own.', 'crc-real-estate' ) . '</p>';
			},
			self::SLUG
		);

		$fields = array(
			'phone'        => array(
				'type'        => 'tel',
				'label'       => __( 'Phone number', 'crc-real-estate' ),
				'description' => __( 'Shown on the Call button of every listing. People can tap it to call this number.', 'crc-real-estate' ),
			),
			'call_text'    => array(
				'type'        => 'text',
				'label'       => __( 'Call button text', 'crc-real-estate' ),
				'description' => __( 'The text on the Call button. Write {number} where the phone number should appear, for example "Call {number}".', 'crc-real-estate' ),
			),
			'whatsapp'     => array(
				'type'        => 'tel',
				'label'       => __( 'WhatsApp number', 'crc-real-estate' ),
				'description' => __( 'Used for the Message button of every listing, which opens a WhatsApp chat with this number. Include the country code, for example +94.', 'crc-real-estate' ),
			),
			'message_text' => array(
				'type'        => 'text',
				'label'       => __( 'Message button text', 'crc-real-estate' ),
				'description' => __( 'The text on the Message button. Write {number} where the WhatsApp number should appear, for example "Message {number}".', 'crc-real-estate' ),
			),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'crc_re_' . $key,
				$field['label'],
				array( $this, 'field' ),
				self::SLUG,
				'crc_re_contact',
				array(
					'label_for'   => 'crc-default-' . str_replace( '_', '-', $key ),
					'key'         => $key,
					'type'        => $field['type'],
					'description' => $field['description'],
				)
			);
		}

		$this->register_map();
	}

	/**
	 * Registers the Map settings and their fields.
	 */
	private function register_map() {
		register_setting(
			self::GROUP,
			Settings::MAP_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_map' ),
				'default'           => Settings::map_defaults(),
			)
		);

		add_settings_section( 'crc_re_map', __( 'Map', 'crc-real-estate' ), array( $this, 'map_guide' ), self::SLUG );

		$fields = array(
			'radius' => __( 'Area on listing pages', 'crc-real-estate' ),
			'start'  => __( 'Starting point', 'crc-real-estate' ),
			'tiles'  => __( 'Map style', 'crc-real-estate' ),
			'credit' => __( 'Map credit', 'crc-real-estate' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'crc_re_map_' . $key,
				$label,
				array( $this, 'map_field' ),
				self::SLUG,
				'crc_re_map',
				array(
					'label_for' => 'crc-map-' . ( 'start' === $key ? 'lat' : $key ),
					'key'       => $key,
				)
			);
		}
	}

	/**
	 * The short guide at the top of the Map settings.
	 */
	public function map_guide() {
		$link = function ( $url, $text ) {
			return sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $url ), esc_html( $text ) );
		};

		printf(
			/* translators: 1: OpenStreetMap link, 2: Nominatim link, 3: tile policy link, 4: search policy link, 5: MapTiler link. */
			'<p>' . esc_html__( 'Maps come from %1$s and place search from %2$s. Both are free and need no key; just keep to their %3$s and %4$s rules. For another map style, paste a tiles address from a service such as %5$s.', 'crc-real-estate' ) . '</p>',
			$link( 'https://www.openstreetmap.org/', 'OpenStreetMap' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
			$link( 'https://nominatim.org/', 'Nominatim' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
			$link( 'https://operations.osmfoundation.org/policies/tiles/', __( 'map', 'crc-real-estate' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
			$link( 'https://operations.osmfoundation.org/policies/nominatim/', __( 'search', 'crc-real-estate' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
			$link( 'https://www.maptiler.com/', 'MapTiler' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
		);
	}

	/**
	 * Prints a Map setting.
	 *
	 * @param array $args Field details.
	 */
	public function map_field( $args ) {
		$name = Settings::MAP_OPTION;

		switch ( $args['key'] ) {
			case 'radius':
				printf(
					'<input type="number" id="crc-map-radius" name="%1$s[radius]" value="%2$s" min="1" max="100" step="0.5" class="small-text"> %3$s<p class="description">%4$s</p>',
					esc_attr( $name ),
					esc_attr( Settings::map( 'radius' ) ),
					esc_html__( 'km', 'crc-real-estate' ),
					esc_html__( 'How far around the property the area on the listing page reaches. The exact place is never shown there.', 'crc-real-estate' )
				);
				break;

			case 'start':
				printf(
					'<label for="crc-map-lat">%1$s</label> <input type="text" inputmode="decimal" id="crc-map-lat" name="%2$s[lat]" value="%3$s" class="regular-text crc-map-coordinate"> <label for="crc-map-lng">%4$s</label> <input type="text" inputmode="decimal" id="crc-map-lng" name="%2$s[lng]" value="%5$s" class="regular-text crc-map-coordinate"><p class="description">%6$s</p>',
					esc_html__( 'Latitude', 'crc-real-estate' ),
					esc_attr( $name ),
					esc_attr( Settings::map( 'lat' ) ),
					esc_html__( 'Longitude', 'crc-real-estate' ),
					esc_attr( Settings::map( 'lng' ) ),
					esc_html__( 'Where the map starts for a new listing. Galle, Sri Lanka is 6.0535, 80.221.', 'crc-real-estate' )
				);
				break;

			case 'tiles':
				printf(
					'<input type="text" id="crc-map-tiles" name="%1$s[tiles]" value="%2$s" class="large-text" placeholder="https://tile.openstreetmap.org/{z}/{x}/{y}.png"><p class="description">%3$s</p>',
					esc_attr( $name ),
					esc_attr( Settings::map( 'tiles' ) ),
					esc_html__( 'Optional. Leave it empty for OpenStreetMap. Another style\'s address must start with https:// and contain {z}, {x} and {y}.', 'crc-real-estate' )
				);
				break;

			case 'credit':
				printf(
					'<input type="text" id="crc-map-credit" name="%1$s[credit]" value="%2$s" class="large-text"><p class="description">%3$s</p>',
					esc_attr( $name ),
					esc_attr( Settings::map( 'credit' ) ),
					esc_html__( 'Only with another map style: the credit line its provider asks for, for example "© MapTiler © OpenStreetMap contributors".', 'crc-real-estate' )
				);
				break;
		}
	}

	/**
	 * Prints a settings field.
	 *
	 * @param array $args Field details.
	 */
	public function field( $args ) {
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s[%4$s]" value="%5$s" class="regular-text"><p class="description">%6$s</p>',
			esc_attr( $args['type'] ),
			esc_attr( $args['label_for'] ),
			esc_attr( Settings::OPTION ),
			esc_attr( $args['key'] ),
			esc_attr( Settings::get( $args['key'] ) ),
			esc_html( $args['description'] )
		);
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'crc-real-estate' ); ?></h1>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
			<?php $this->updates(); ?>
		</div>
		<?php
	}

	/**
	 * Prints the Updates part: the installed and latest versions, and buttons
	 * to look for a new version and install it.
	 */
	private function updates() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$updater = new Updater( CRC_RE_FILE );
		$status  = $updater->status();
		?>
		<h2><?php esc_html_e( 'Updates', 'crc-real-estate' ); ?></h2>
		<p><?php esc_html_e( 'New versions come from GitHub and install like any other plugin update. WordPress looks for them twice a day; Check for updates looks straight away.', 'crc-real-estate' ); ?></p>
		<table class="form-table crc-updates" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Installed version', 'crc-real-estate' ); ?></th>
				<td><?php echo esc_html( $status['installed'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Latest version', 'crc-real-estate' ); ?></th>
				<td>
					<?php
					if ( '' === $status['latest'] ) {
						esc_html_e( 'Not checked yet.', 'crc-real-estate' );
					} else {
						echo esc_html( $status['latest'] );

						if ( $status['checked'] ) {
							/* translators: %s: how long ago, e.g. "5 mins". */
							echo ' <span class="description">' . esc_html( sprintf( __( '(checked %s ago)', 'crc-real-estate' ), human_time_diff( $status['checked'] ) ) ) . '</span>';
						}
					}
					?>
				</td>
			</tr>
		</table>
		<p class="crc-updates-actions">
			<?php if ( $status['available'] ) : ?>
				<?php /* translators: %s: new version number. */ ?>
				<a class="button button-primary" href="<?php echo esc_url( $updater->update_url() ); ?>"><?php echo esc_html( sprintf( __( 'Update to %s', 'crc-real-estate' ), $status['latest'] ) ); ?></a>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( Updater::check_url() ); ?>"><?php esc_html_e( 'Check for updates', 'crc-real-estate' ); ?></a>
			<a class="thickbox open-plugin-details-modal" href="<?php echo esc_url( $updater->details_url() ); ?>"><?php esc_html_e( 'What\'s new', 'crc-real-estate' ); ?></a>
		</p>
		<?php
	}
}
