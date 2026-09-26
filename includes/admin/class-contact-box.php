<?php
/**
 * Contact buttons box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors give a listing its own phone and WhatsApp numbers, and its own
 * Call and Message button texts, instead of the defaults from Listings → Settings.
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
	 * Adds the Contact buttons box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_contact', __( 'Contact buttons', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * The fields in the box, in order: each button's number, then its text,
	 * so on wide screens each number sits beside its text.
	 *
	 * @return array[]
	 */
	private function fields() {
		return array(
			'phone'        => array(
				'meta'     => Settings::PHONE_META,
				'type'     => 'tel',
				'label'    => __( 'Phone number', 'crc-real-estate' ),
				/* translators: %s: default phone number. */
				'help'     => __( 'Shown on the Call button of this listing. Leave it empty to use the default number from Settings (%s).', 'crc-real-estate' ),
				'sanitize' => array( Settings::class, 'sanitize_number' ),
			),
			'call_text'    => array(
				'meta'     => Settings::CALL_TEXT_META,
				'type'     => 'text',
				'label'    => __( 'Call button text', 'crc-real-estate' ),
				/* translators: %s: default Call button text. */
				'help'     => __( 'The text on this listing\'s Call button. Write {number} where the phone number should appear. Leave it empty to use the text from Settings (%s).', 'crc-real-estate' ),
				'sanitize' => array( Settings::class, 'sanitize_text' ),
			),
			'whatsapp'     => array(
				'meta'     => Settings::WHATSAPP_META,
				'type'     => 'tel',
				'label'    => __( 'WhatsApp number', 'crc-real-estate' ),
				/* translators: %s: default WhatsApp number. */
				'help'     => __( 'Used for the Message button of this listing. Leave it empty to use the default WhatsApp number from Settings (%s).', 'crc-real-estate' ),
				'sanitize' => array( Settings::class, 'sanitize_number' ),
			),
			'message_text' => array(
				'meta'     => Settings::MESSAGE_TEXT_META,
				'type'     => 'text',
				'label'    => __( 'Message button text', 'crc-real-estate' ),
				/* translators: %s: default Message button text. */
				'help'     => __( 'The text on this listing\'s Message button. Write {number} where the WhatsApp number should appear. Leave it empty to use the text from Settings (%s).', 'crc-real-estate' ),
				'sanitize' => array( Settings::class, 'sanitize_text' ),
			),
		);
	}

	/**
	 * Prints the Contact buttons box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		wp_nonce_field( 'crc_contact_save', self::NONCE );
		?>
		<div class="crc-contact-box crc-fields">
			<?php
			foreach ( $this->fields() as $key => $field ) {
				$default = Settings::get( $key );
				$id      = 'crc-' . str_replace( '_', '-', $key ) . '-field';
				?>
				<div class="crc-field-group">
					<p class="crc-field">
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( 'crc_' . $key ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, $field['meta'], true ) ); ?>" placeholder="<?php echo esc_attr( $default ); ?>" class="regular-text">
					</p>
					<p class="description">
						<?php echo esc_html( sprintf( $field['help'], '' !== $default ? $default : __( 'none set', 'crc-real-estate' ) ) ); ?>
					</p>
				</div>
				<?php
			}
			?>
		</div>
		<p><a href="<?php echo esc_url( Settings_Page::url() ); ?>"><?php esc_html_e( 'Change the defaults in Settings', 'crc-real-estate' ); ?></a></p>
		<?php
	}

	/**
	 * Saves the listing's own numbers and texts. Empty fields use the defaults.
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

		foreach ( $this->fields() as $key => $field ) {
			$name  = 'crc_' . $key;
			$value = isset( $_POST[ $name ] ) ? call_user_func( $field['sanitize'], sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) ) : '';

			if ( '' !== $value ) {
				update_post_meta( $post_id, $field['meta'], $value );
			} else {
				delete_post_meta( $post_id, $field['meta'] );
			}
		}
	}
}
