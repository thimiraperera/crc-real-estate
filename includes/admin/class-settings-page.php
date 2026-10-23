<?php
/**
 * Settings page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Legal;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Inquiry;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Updater;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Settings, on two tabs. General: the default phone and WhatsApp
 * numbers and button texts, the map, the inquiry form, deleting listings, the
 * business details for the legal pages, and plugin updates. Developer
 * options: whether wp-admin shows the shortcodes. Each tab saves on its own.
 */
final class Settings_Page {

	const SLUG            = 'crc-real-estate-settings';
	const GROUP           = 'crc_re_settings';
	const DEVELOPER_PAGE  = 'crc-real-estate-settings-developer';
	const DEVELOPER_GROUP = 'crc_re_settings_developer';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		// After the Listings menu's own items, which WordPress adds at the usual time.
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
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
		$this->register_inquiry();
		$this->register_cleanup();
		$this->register_business();
		$this->register_developer();
	}

	/**
	 * Registers the developer options and their field. They have their own
	 * group, so saving the General tab never touches them.
	 */
	private function register_developer() {
		register_setting(
			self::DEVELOPER_GROUP,
			Settings::DEVELOPER_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_developer' ),
				'default'           => Settings::developer_defaults(),
			)
		);

		add_settings_section(
			'crc_re_developer',
			__( 'Shortcodes', 'crc-real-estate' ),
			function () {
				echo '<p>' . esc_html__( 'Shortcodes place the plugin\'s pieces on the website\'s pages in Elementor. Once the pages are built, they can be hidden from wp-admin, so the people who add listings only see what they need.', 'crc-real-estate' ) . '</p>';
			},
			self::DEVELOPER_PAGE
		);

		add_settings_field(
			'crc_re_developer_shortcodes',
			__( 'In wp-admin', 'crc-real-estate' ),
			array( $this, 'developer_field' ),
			self::DEVELOPER_PAGE,
			'crc_re_developer',
			array( 'label_for' => 'crc-developer-shortcodes' )
		);
	}

	/**
	 * Prints the tick box for showing the shortcodes in wp-admin.
	 */
	public function developer_field() {
		printf(
			'<input type="hidden" name="%1$s[shortcodes]" value="0"><label for="crc-developer-shortcodes"><input type="checkbox" id="crc-developer-shortcodes" name="%1$s[shortcodes]" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( Settings::DEVELOPER_OPTION ),
			checked( Settings::shortcodes_shown(), true, false ),
			esc_html__( 'Show the shortcodes', 'crc-real-estate' ),
			esc_html__( 'Shows the Shortcodes page under Listings, and the shortcodes on the Widgets page and on Testimonials → Settings, with their Copy buttons and the notes on where to paste them. Untick this to hide them all. The shortcodes keep working on the website either way, and ticking this again brings them back.', 'crc-real-estate' )
		);
	}

	/**
	 * Registers the business details used on the legal pages, and their fields.
	 */
	private function register_business() {
		register_setting(
			self::GROUP,
			Legal::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Legal::class, 'sanitize' ),
				'default'           => Legal::defaults(),
			)
		);

		add_settings_section(
			'crc_re_business',
			__( 'Business details', 'crc-real-estate' ),
			function () {
				echo '<p>' . esc_html__( 'Who runs this website, as the legal pages say it: the Privacy Policy, Terms and Conditions, Disclaimer and Listing Terms. An empty box goes back to the detail shown in it.', 'crc-real-estate' ) . '</p>';
			},
			self::SLUG
		);

		$fields = array(
			'name'    => array( __( 'Business name', 'crc-real-estate' ), __( 'The name people know the website by.', 'crc-real-estate' ) ),
			'company' => array( __( 'Company', 'crc-real-estate' ), __( 'The registered company that runs it, as on its certificate of incorporation.', 'crc-real-estate' ) ),
			'number'  => array( __( 'Company number', 'crc-real-estate' ), __( 'From the certificate of incorporation, for example PV 00339321.', 'crc-real-estate' ) ),
			'website' => array( __( 'Company website', 'crc-real-estate' ), __( 'The company\'s own website, mentioned with its other services.', 'crc-real-estate' ) ),
			'address' => array( __( 'Address', 'crc-real-estate' ), __( 'The office address shown on the legal pages.', 'crc-real-estate' ) ),
			'email'   => array( __( 'E-mail', 'crc-real-estate' ), __( 'Where people write about the legal pages and their personal information.', 'crc-real-estate' ) ),
			'phones'  => array( __( 'Phone numbers', 'crc-real-estate' ), __( 'One a line, up to 5. The first is the one to call; all of them are listed as the business\'s only numbers in the Disclaimer\'s fraud warning.', 'crc-real-estate' ) ),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'crc_re_business_' . $key,
				$field[0],
				array( $this, 'business_field' ),
				self::SLUG,
				'crc_re_business',
				array(
					'label_for'   => 'crc-business-' . $key,
					'key'         => $key,
					'description' => $field[1],
				)
			);
		}
	}

	/**
	 * Prints a business detail's box, with the detail in use as its hint.
	 *
	 * @param array $args Field details.
	 */
	public function business_field( $args ) {
		$saved    = get_option( Legal::OPTION, array() );
		$saved    = is_array( $saved ) ? Legal::sanitize( $saved ) : array();
		$defaults = Legal::defaults();
		$key      = $args['key'];

		if ( 'phones' === $key ) {
			printf(
				'<textarea id="crc-business-phones" name="%1$s[phones]" rows="3" class="regular-text" placeholder="%2$s">%3$s</textarea>',
				esc_attr( Legal::OPTION ),
				esc_attr( implode( "\n", $defaults['phones'] ) ),
				esc_textarea( implode( "\n", isset( $saved['phones'] ) ? $saved['phones'] : array() ) )
			);
		} else {
			printf(
				'<input type="%1$s" id="crc-business-%2$s" name="%3$s[%2$s]" value="%4$s" class="regular-text" placeholder="%5$s">',
				esc_attr( 'email' === $key ? 'email' : ( 'website' === $key ? 'url' : 'text' ) ),
				esc_attr( $key ),
				esc_attr( Legal::OPTION ),
				esc_attr( isset( $saved[ $key ] ) ? $saved[ $key ] : '' ),
				esc_attr( $defaults[ $key ] )
			);
		}

		echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
	}

	/**
	 * Registers the setting for deleting listings.
	 */
	private function register_cleanup() {
		register_setting(
			self::GROUP,
			Settings::CLEANUP_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_cleanup' ),
				'default'           => Settings::cleanup_defaults(),
			)
		);

		add_settings_section(
			'crc_re_cleanup',
			__( 'Deleting listings', 'crc-real-estate' ),
			function () {
				echo '<p>' . esc_html__( 'What happens to a listing\'s photos when the listing is deleted for good, by emptying the Trash or with Delete Permanently. Moving a listing to the Trash keeps everything, so it can still be restored.', 'crc-real-estate' ) . '</p>';
			},
			self::SLUG
		);

		add_settings_field(
			'crc_re_cleanup_media',
			__( 'Photos', 'crc-real-estate' ),
			array( $this, 'cleanup_field' ),
			self::SLUG,
			'crc_re_cleanup',
			array( 'label_for' => 'crc-cleanup-media' )
		);
	}

	/**
	 * Prints the tick box for deleting a listing's photos with it.
	 */
	public function cleanup_field() {
		printf(
			'<input type="hidden" name="%1$s[media]" value="0"><label for="crc-cleanup-media"><input type="checkbox" id="crc-cleanup-media" name="%1$s[media]" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( Settings::CLEANUP_OPTION ),
			checked( (int) Settings::cleanup( 'media' ), 1, false ),
			esc_html__( 'Delete photos with the listing', 'crc-real-estate' ),
			esc_html__( 'When a listing is deleted for good, its main photo, its gallery photos and any other files uploaded to it are deleted from the Media Library too, so they do not take up space. A photo that is also used somewhere else (as another listing\'s or page\'s photo, or on a category carousel card) is kept. Untick this to keep every photo.', 'crc-real-estate' )
		);
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
	 * Registers the settings of the forms (inquiry, contact and free ad) and their fields.
	 */
	private function register_inquiry() {
		register_setting(
			self::GROUP,
			Settings::INQUIRY_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_inquiry' ),
				'default'           => Settings::inquiry_defaults(),
			)
		);

		add_settings_section( 'crc_re_inquiry', __( 'Forms', 'crc-real-estate' ), array( $this, 'inquiry_guide' ), self::SLUG );

		$fields = array(
			'email'      => __( 'Send form emails to', 'crc-real-estate' ),
			'captcha'    => __( 'Spam protection', 'crc-real-estate' ),
			'site_key'   => __( 'hCaptcha site key', 'crc-real-estate' ),
			'secret_key' => __( 'hCaptcha secret key', 'crc-real-estate' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'crc_re_inquiry_' . $key,
				$label,
				array( $this, 'inquiry_field' ),
				self::SLUG,
				'crc_re_inquiry',
				array(
					'label_for' => 'crc-inquiry-' . str_replace( '_', '-', $key ),
					'key'       => $key,
				)
			);
		}
	}

	/**
	 * The short guide at the top of the Forms settings.
	 */
	public function inquiry_guide() {
		$link = function ( $url, $text ) {
			return sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $url ), esc_html( $text ) );
		};

		echo '<p>' . esc_html__( 'These settings are shared by the inquiry form, the contact form and the post a free ad form. Everything people send with them is emailed to you and also kept in wp-admin, each form in its own menu: Inquiries, Contact Messages and Free Ads. That way nothing is lost if an email doesn\'t arrive, and new ones are counted on each menu until you open them.', 'crc-real-estate' ) . '</p>';

		printf(
			/* translators: 1: hCaptcha link, 2: hCaptcha Sites link, 3: hCaptcha Settings link. */
			'<p>' . esc_html__( 'To stop spam robots, add hCaptcha\'s free "I am human" box to all three forms: sign up at %1$s, add your website under %2$s to get its site key, and copy your secret key from %3$s. Then paste both keys below and tick Use hCaptcha.', 'crc-real-estate' ) . '</p>',
			$link( 'https://www.hcaptcha.com/', 'hCaptcha' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
			$link( 'https://dashboard.hcaptcha.com/sites', __( 'Sites', 'crc-real-estate' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
			$link( 'https://dashboard.hcaptcha.com/settings', __( 'Settings', 'crc-real-estate' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $link.
		);
	}

	/**
	 * Prints a Forms setting.
	 *
	 * @param array $args Field details.
	 */
	public function inquiry_field( $args ) {
		$name = Settings::INQUIRY_OPTION;

		switch ( $args['key'] ) {
			case 'email':
				$admin = (string) get_option( 'admin_email' );

				printf(
					'<input type="text" id="crc-inquiry-email" name="%1$s[email]" value="%2$s" class="regular-text" placeholder="%3$s" autocomplete="off" spellcheck="false"><p class="description">%4$s</p>',
					esc_attr( $name ),
					esc_attr( Settings::inquiry( 'email' ) ),
					esc_attr( $admin ),
					/* translators: %s: the site's admin email address. */
					esc_html( sprintf( __( 'Every inquiry, contact message and free ad is emailed here. To send them to more than one person, put commas between the addresses. Leave it empty to use the site\'s admin email (%s). Replying to one of these emails answers the person who sent it, so you can write back straight away.', 'crc-real-estate' ), $admin ) )
				);
				break;

			case 'captcha':
				$notes = '';

				if ( Settings::inquiry( 'captcha' ) && ! Settings::captcha_on() ) {
					$notes .= '<p class="crc-warning">' . esc_html__( 'Use hCaptcha is ticked, but the box only shows on the form once both keys below are saved.', 'crc-real-estate' ) . '</p>';
				}

				$issue = get_option( Inquiry::CAPTCHA_ISSUE );

				if ( Settings::captcha_on() && is_array( $issue ) && ! empty( $issue['time'] ) ) {
					/* translators: %s: date. */
					$notes .= '<p class="crc-warning">' . esc_html( sprintf( __( 'On %s hCaptcha said the keys aren\'t right, so what people sent with the forms came through without the check. Please copy both keys again from your hCaptcha dashboard and save. This note goes away after the next inquiry, message or ad that passes the check.', 'crc-real-estate' ), wp_date( (string) get_option( 'date_format' ), (int) $issue['time'] ) ) ) . '</p>';
				}

				printf(
					'<label><input type="checkbox" id="crc-inquiry-captcha" name="%1$s[captcha]" value="1"%2$s> %3$s</label><p class="description">%4$s</p>%5$s',
					esc_attr( $name ),
					checked( (bool) Settings::inquiry( 'captcha' ), true, false ),
					esc_html__( 'Use hCaptcha', 'crc-real-estate' ),
					esc_html__( 'Adds an "I am human" box above the send button of the inquiry form, the contact form and the post a free ad form. People tick it before sending, which stops spam robots from filling them in. It needs both keys below.', 'crc-real-estate' ),
					$notes // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				);
				break;

			case 'site_key':
				printf(
					'<input type="text" id="crc-inquiry-site-key" name="%1$s[site_key]" value="%2$s" class="regular-text code" autocomplete="off" spellcheck="false"><p class="description">%3$s</p>',
					esc_attr( $name ),
					esc_attr( Settings::inquiry( 'site_key' ) ),
					esc_html__( 'Find it under Sites in your hCaptcha dashboard. It looks like 10000000-ffff-ffff-ffff-000000000001. It shows on the form, so it doesn\'t need to be kept secret.', 'crc-real-estate' )
				);
				break;

			case 'secret_key':
				$saved = (string) Settings::inquiry( 'secret_key' );
				$help  = __( 'Find it under Settings in your hCaptcha dashboard. It stays private on this site and is only used to ask hCaptcha whether the box was ticked.', 'crc-real-estate' );

				if ( '' !== $saved ) {
					/* translators: %s: last 4 characters of the saved key. */
					$help .= ' ' . sprintf( __( 'For safety the saved key isn\'t shown here; it ends in %s. Leave the box empty to keep it.', 'crc-real-estate' ), substr( $saved, -4 ) );
				}

				printf(
					'<input type="text" id="crc-inquiry-secret-key" name="%1$s[secret_key]" value="" class="regular-text code" placeholder="%2$s" autocomplete="off" spellcheck="false"><p class="description">%3$s</p>',
					esc_attr( $name ),
					esc_attr( '' !== $saved ? __( 'Saved. Paste a new key only to change it.', 'crc-real-estate' ) : '' ),
					esc_html( $help )
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
	 * The tabs, in order: name => title.
	 *
	 * @return string[]
	 */
	private function tabs() {
		return array(
			'general'   => __( 'General', 'crc-real-estate' ),
			'developer' => __( 'Developer options', 'crc-real-estate' ),
		);
	}

	/**
	 * The tab being shown.
	 *
	 * @return string
	 */
	private function tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which tab shows.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( $this->tabs()[ $tab ] ) ? $tab : 'general';
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$current = $this->tab();
		?>
		<div class="wrap crc-settings">
			<h1><?php esc_html_e( 'Settings', 'crc-real-estate' ); ?></h1>
			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper crc-settings-tabs" aria-label="<?php esc_attr_e( 'Settings', 'crc-real-estate' ); ?>">
				<?php foreach ( $this->tabs() as $tab => $title ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab, self::url() ) ); ?>" class="nav-tab<?php echo $tab === $current ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $title ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'developer' === $current ) : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( self::DEVELOPER_GROUP );
					do_settings_sections( self::DEVELOPER_PAGE );
					submit_button();
					?>
				</form>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( self::GROUP );
					do_settings_sections( self::SLUG );
					submit_button();
					?>
				</form>
				<?php $this->updates(); ?>
			<?php endif; ?>
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
