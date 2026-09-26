<?php
/**
 * Owner box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Owner;
use CRC\RealEstate\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the property owner's details with the listing, for the team only.
 */
final class Owner_Box {

	const NONCE = 'crc_owner_nonce';
	const FIELD = 'crc_owner';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
	}

	/**
	 * Adds the Owner box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_owner', __( 'Owner (private)', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'default' );
	}

	/**
	 * Prints the Owner box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$owner  = Owner::get( $post->ID );
		$labels = Owner::labels();
		$types  = array(
			'first_name' => 'text',
			'last_name'  => 'text',
			'phone'      => 'tel',
			'email'      => 'email',
		);

		wp_nonce_field( 'crc_owner_save', self::NONCE );
		?>
		<p class="description"><?php esc_html_e( 'The owner of this property and how to reach them. Only people who can edit listings see these details. They never show on the website, and they are kept with the listing when it is exported.', 'crc-real-estate' ); ?></p>
		<div class="crc-fields crc-owner-box">
			<?php foreach ( $types as $key => $type ) : ?>
				<p class="crc-field">
					<label for="<?php echo esc_attr( 'crc-owner-' . str_replace( '_', '-', $key ) ); ?>"><?php echo esc_html( $labels[ $key ] ); ?></label>
					<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( 'crc-owner-' . str_replace( '_', '-', $key ) ); ?>" name="<?php echo esc_attr( self::FIELD . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $owner[ $key ] ); ?>" class="regular-text" autocomplete="off">
				</p>
			<?php endforeach; ?>
			<p class="crc-field crc-field-wide">
				<label for="crc-owner-address"><?php echo esc_html( $labels['address'] ); ?></label>
				<textarea id="crc-owner-address" name="<?php echo esc_attr( self::FIELD . '[address]' ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $owner['address'] ); ?></textarea>
			</p>
			<p class="crc-field crc-field-wide">
				<label for="crc-owner-notes"><?php echo esc_html( $labels['notes'] ); ?></label>
				<textarea id="crc-owner-notes" name="<?php echo esc_attr( self::FIELD . '[notes]' ); ?>" rows="4" class="large-text" aria-describedby="crc-owner-notes-help"><?php echo esc_textarea( $owner['notes'] ); ?></textarea>
				<span class="description" id="crc-owner-notes-help"><?php esc_html_e( 'Anything worth remembering, for example the best time to call, the lowest price they will accept, or who holds the keys.', 'crc-real-estate' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Saves the owner's details.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_owner_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each detail is cleaned by Owner::save().
		$posted = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();
		$values = array();

		foreach ( array_keys( Owner::FIELDS ) as $key ) {
			$values[ $key ] = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
		}

		Owner::save( $post_id, $values );
	}
}
