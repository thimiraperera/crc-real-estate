<?php
/**
 * Views box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Views;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors set a listing's view count, e.g. to bring back the views of a
 * listing that was deleted and published again. Visitors keep adding to it.
 */
final class Views_Box {

	const NONCE    = 'crc_views_nonce';
	const FIELD    = 'crc_views';
	const ORIGINAL = 'crc_views_original';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
	}

	/**
	 * Adds the Views box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_views', __( 'Views', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'side', 'default' );
	}

	/**
	 * Prints the Views box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$views = Views::get( $post->ID );

		wp_nonce_field( 'crc_views_save', self::NONCE );
		?>
		<p class="crc-views-box">
			<label for="crc-views-field"><?php esc_html_e( 'Views', 'crc-real-estate' ); ?></label>
			<input type="number" id="crc-views-field" class="small-text" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( $views ); ?>" min="0" step="1">
			<input type="hidden" name="<?php echo esc_attr( self::ORIGINAL ); ?>" value="<?php echo esc_attr( $views ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Goes up with each visit.', 'crc-real-estate' ); ?></p>
		<?php
	}

	/**
	 * Saves the view count, but only when it was changed here, so views
	 * counted while the listing was open for editing aren't lost.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_views_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::FIELD ], $_POST[ self::ORIGINAL ] ) ) {
			return;
		}

		$views    = absint( wp_unslash( $_POST[ self::FIELD ] ) );
		$original = absint( wp_unslash( $_POST[ self::ORIGINAL ] ) );

		if ( $views !== $original ) {
			update_post_meta( $post_id, Views::META, $views );
		}
	}
}
