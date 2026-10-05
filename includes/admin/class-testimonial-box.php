<?php
/**
 * Testimonial screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * The testimonial screen: the name goes where a title usually does, then a
 * box with the star rating and what they said. The list shows each one's
 * name, stars and the start of the testimonial.
 */
final class Testimonial_Box {

	const NONCE = 'crc_testimonial_nonce';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'enter_title_here', array( $this, 'name_placeholder' ), 10, 2 );
		add_action( 'add_meta_boxes_' . Testimonials::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Testimonials::POST_TYPE, array( $this, 'save' ) );
		add_filter( 'manage_' . Testimonials::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Testimonials::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Says "Name" in the title box.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post being edited.
	 * @return string
	 */
	public function name_placeholder( $text, $post ) {
		return Testimonials::POST_TYPE === $post->post_type ? __( 'Name, for example John Smith', 'crc-real-estate' ) : $text;
	}

	/**
	 * Adds the box under the name.
	 */
	public function add() {
		add_meta_box( 'crc_testimonial', __( 'Testimonial', 'crc-real-estate' ), array( $this, 'render' ), Testimonials::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Loads the admin styles on the testimonial screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		if ( $screen && Testimonials::POST_TYPE === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		}
	}

	/**
	 * Prints the box: the star rating, then the testimonial. The testimonial
	 * is the post's content, saved by WordPress from the field called content.
	 *
	 * @param \WP_Post $post Testimonial being edited.
	 */
	public function render( $post ) {
		$saved  = get_post_meta( $post->ID, Testimonials::RATING, true );
		$rating = '' === $saved ? '5' : Testimonials::sanitize_rating( $saved );

		wp_nonce_field( 'crc_testimonial_save', self::NONCE );
		?>
		<div class="crc-testimonial-box">
			<p>
				<label for="crc-testimonial-rating"><strong><?php esc_html_e( 'Star rating', 'crc-real-estate' ); ?></strong></label><br>
				<input type="number" id="crc-testimonial-rating" name="crc_rating" value="<?php echo esc_attr( $rating ); ?>" min="0.5" max="5" step="0.5" class="small-text" aria-describedby="crc-testimonial-rating-help">
				<span class="description" id="crc-testimonial-rating-help"><?php esc_html_e( 'Out of 5. The arrows go up or down half a star at a time, so 4.5 shows four and a half stars.', 'crc-real-estate' ); ?></span>
			</p>
			<p>
				<label for="crc-testimonial-text"><strong><?php esc_html_e( 'Testimonial', 'crc-real-estate' ); ?></strong></label>
				<textarea id="crc-testimonial-text" name="content" rows="8" class="large-text" aria-describedby="crc-testimonial-text-help"><?php echo esc_textarea( $post->post_content ); ?></textarea>
				<span class="description" id="crc-testimonial-text-help"><?php esc_html_e( 'What they said, in their words. Leave an empty line between paragraphs. A testimonial without any text doesn\'t show on the site.', 'crc-real-estate' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Saves the star rating. WordPress saves the name and the testimonial.
	 *
	 * @param int $post_id Testimonial ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_testimonial_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$rating = isset( $_POST['crc_rating'] ) ? wp_unslash( $_POST['crc_rating'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_rating().

		update_post_meta( $post_id, Testimonials::RATING, Testimonials::sanitize_rating( $rating ) );
	}

	/**
	 * The list's columns: name, rating, testimonial and date.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		$list = array();

		if ( isset( $columns['cb'] ) ) {
			$list['cb'] = $columns['cb'];
		}

		$list['title']           = __( 'Name', 'crc-real-estate' );
		$list['crc_rating']      = __( 'Rating', 'crc-real-estate' );
		$list['crc_testimonial'] = __( 'Testimonial', 'crc-real-estate' );

		if ( isset( $columns['date'] ) ) {
			$list['date'] = $columns['date'];
		}

		return $list;
	}

	/**
	 * Fills the Rating and Testimonial columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Testimonial ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'crc_rating' === $column ) {
			$rating = Testimonials::rating( $post_id );
			$icons  = array(
				'full'  => 'dashicons-star-filled',
				'half'  => 'dashicons-star-half',
				'empty' => 'dashicons-star-empty',
			);

			echo '<span class="crc-testimonial-rating" aria-hidden="true">';

			foreach ( Testimonials::stars( $rating ) as $kind ) {
				printf( '<span class="dashicons %s"></span>', esc_attr( $icons[ $kind ] ) );
			}

			/* translators: %s: rating, e.g. "4.5". */
			echo '</span><span class="crc-testimonial-rating-number">' . esc_html( sprintf( __( '%s out of 5', 'crc-real-estate' ), $rating ) ) . '</span>';
		} elseif ( 'crc_testimonial' === $column ) {
			$post = get_post( $post_id );
			$text = $post ? trim( wp_strip_all_tags( (string) $post->post_content ) ) : '';

			echo '' !== $text ? esc_html( wp_trim_words( $text, 24 ) ) : '<span class="crc-warning">' . esc_html__( 'No text yet, so it doesn\'t show on the site.', 'crc-real-estate' ) . '</span>';
		}
	}
}
