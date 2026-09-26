<?php
/**
 * Settings page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Settings: the default phone and WhatsApp numbers.
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
			__( 'Contact numbers', 'crc-real-estate' ),
			function () {
				echo '<p>' . esc_html__( 'These numbers are used on every listing, unless a listing has its own numbers.', 'crc-real-estate' ) . '</p>';
			},
			self::SLUG
		);

		add_settings_field(
			'crc_re_phone',
			__( 'Phone number', 'crc-real-estate' ),
			array( $this, 'number_field' ),
			self::SLUG,
			'crc_re_contact',
			array(
				'label_for'   => 'crc-default-phone',
				'key'         => 'phone',
				'description' => __( 'Shown on the Call button of every listing. People can tap it to call this number.', 'crc-real-estate' ),
			)
		);

		add_settings_field(
			'crc_re_whatsapp',
			__( 'WhatsApp number', 'crc-real-estate' ),
			array( $this, 'number_field' ),
			self::SLUG,
			'crc_re_contact',
			array(
				'label_for'   => 'crc-default-whatsapp',
				'key'         => 'whatsapp',
				'description' => __( 'Used for the Message button of every listing, which opens a WhatsApp chat with this number. Include the country code, for example +94.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints a number field.
	 *
	 * @param array $args Field details.
	 */
	public function number_field( $args ) {
		printf(
			'<input type="tel" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text"><p class="description">%5$s</p>',
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
		</div>
		<?php
	}
}
