<?php
/**
 * Contact numbers box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors give a listing its own phone and WhatsApp numbers instead of
 * the default ones from Listings → Settings.
 */
final class Contact_Box {

	const NONCE = 'crc_contact_nonce';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
	}

	/**
	 * Adds the Contact numbers box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_contact', __( 'Contact numbers', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Prints the Contact numbers box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$fields = array(
			'phone'    => array(
				'meta'  => Settings::PHONE_META,
				'label' => __( 'Phone number', 'crc-real-estate' ),
				/* translators: %s: default phone number. */
				'help'  => __( 'Shown on the Call button of this listing. Leave it empty to use the default number from Settings (%s).', 'crc-real-estate' ),
			),
			'whatsapp' => array(
				'meta'  => Settings::WHATSAPP_META,
				'label' => __( 'WhatsApp number', 'crc-real-estate' ),
				/* translators: %s: default WhatsApp number. */
				'help'  => __( 'Used for the Message button of this listing. Leave it empty to use the default WhatsApp number from Settings (%s).', 'crc-real-estate' ),
			),
		);

		wp_nonce_field( 'crc_contact_save', self::NONCE );

		foreach ( $fields as $key => $field ) {
			$default = Settings::get( $key );
			$id      = 'crc-' . $key . '-field';
			?>
			<p class="crc-field">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<input type="tel" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( 'crc_' . $key ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, $field['meta'], true ) ); ?>" placeholder="<?php echo esc_attr( $default ); ?>" class="regular-text">
			</p>
			<p class="description">
				<?php echo esc_html( sprintf( $field['help'], '' !== $default ? $default : __( 'none set', 'crc-real-estate' ) ) ); ?>
				<a href="<?php echo esc_url( Settings_Page::url() ); ?>"><?php esc_html_e( 'Change the default numbers', 'crc-real-estate' ); ?></a>
			</p>
			<?php
		}
	}

	/**
	 * Saves the listing's own numbers. Empty fields fall back to the defaults.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_contact_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'crc_phone'    => Settings::PHONE_META,
			'crc_whatsapp' => Settings::WHATSAPP_META,
		);

		foreach ( $fields as $field => $key ) {
			$value = isset( $_POST[ $field ] ) ? Settings::sanitize_number( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : '';

			if ( '' !== $value ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}
}
