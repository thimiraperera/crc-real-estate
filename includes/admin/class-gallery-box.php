<?php
/**
 * Gallery box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Gallery;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors add, reorder and remove a listing's gallery images, and shows
 * the featured image in the Listings list.
 */
final class Gallery_Box {

	const NONCE = 'crc_gallery_nonce';
	const FIELD = 'crc_gallery';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_' . Post_Type::NAME . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Adds the Gallery box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_gallery', __( 'Gallery', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Prints the Gallery box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$ids = Gallery::gallery_ids( $post->ID );

		wp_nonce_field( 'crc_gallery_save', self::NONCE );
		?>
		<div class="crc-gallery-admin<?php echo $ids ? ' has-images' : ''; ?>">
			<p class="description">
				<?php esc_html_e( 'The featured image is the large photo. Add the other photos here. They appear beside it in this order, and all of them open in the photo viewer. Drag to reorder.', 'crc-real-estate' ); ?>
			</p>
			<ul class="crc-gallery-admin__list">
				<?php foreach ( $ids as $id ) : ?>
					<?php
					$thumb = wp_get_attachment_image( $id, 'thumbnail' );

					if ( ! $thumb ) {
						continue;
					}
					?>
					<li class="crc-gallery-admin__item" data-id="<?php echo esc_attr( $id ); ?>">
						<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup. ?>
						<button type="button" class="crc-gallery-admin__remove" aria-label="<?php esc_attr_e( 'Remove image', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="crc-gallery-admin__empty"><?php esc_html_e( 'No gallery images yet.', 'crc-real-estate' ); ?></p>
			<input type="hidden" name="<?php echo esc_attr( self::FIELD ); ?>" class="crc-gallery-admin__ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
			<p>
				<button type="button" class="button crc-gallery-admin__add"><?php esc_html_e( 'Add images', 'crc-real-estate' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Saves the gallery images.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_gallery_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$ids = isset( $_POST[ self::FIELD ] ) ? Gallery::sanitize_ids( sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) ) : array();

		if ( $ids ) {
			update_post_meta( $post_id, Gallery::META, $ids );
		} else {
			delete_post_meta( $post_id, Gallery::META );
		}
	}

	/**
	 * Loads the admin files on listing screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || Post_Type::NAME !== $screen->post_type ) {
			return;
		}

		if ( 'edit.php' === $hook || 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		}

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$post = get_post();

		wp_enqueue_media( $post ? array( 'post' => $post->ID ) : array() );
		wp_enqueue_script( 'crc-re-admin-listing', CRC_RE_URL . 'assets/js/admin-listing.js', array( 'jquery', 'jquery-ui-sortable' ), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-listing',
			'crcReListing',
			array(
				'frameTitle'       => __( 'Add gallery images', 'crc-real-estate' ),
				'frameButton'      => __( 'Add to gallery', 'crc-real-estate' ),
				'remove'           => __( 'Remove image', 'crc-real-estate' ),
				'featuredRequired' => __( 'Add a featured image to publish this listing.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Adds a Photo column to the Listings list.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function add_column( $columns ) {
		$result = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$result['crc_photo'] = __( 'Photo', 'crc-real-estate' );
			}

			$result[ $key ] = $label;
		}

		return $result;
	}

	/**
	 * Prints the Photo column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Listing ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'crc_photo' !== $column ) {
			return;
		}

		$image = get_the_post_thumbnail( $post_id, 'thumbnail', array( 'class' => 'crc-list-photo' ) );

		if ( $image ) {
			echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
		} else {
			printf( '<span class="crc-list-photo crc-list-photo--none" title="%1$s">%2$s</span>', esc_attr__( 'No featured image', 'crc-real-estate' ), '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>' );
		}
	}
}
