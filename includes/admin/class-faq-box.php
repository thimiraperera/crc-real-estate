<?php
/**
 * FAQs on the listing and category screens.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Faq;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors write the questions and answers of each listing category
 * (on the Edit Listing Category screen) and of each listing (in the FAQs
 * box), and choose whether a listing shows its category's questions.
 */
final class Faq_Box {

	const NONCE          = 'crc_faqs_nonce';
	const FIELD          = 'crc_faqs';
	const SHOW_CATEGORY  = 'crc_faqs_category';
	const CATEGORY_NONCE = 'crc_category_faqs_nonce';
	const CATEGORY_FIELD = 'crc_category_faqs';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
		add_action( Taxonomy::NAME . '_edit_form_fields', array( $this, 'category_field' ), 20 );
		add_action( 'edited_' . Taxonomy::NAME, array( $this, 'save_category' ) );
		add_filter( 'manage_edit-' . Taxonomy::NAME . '_columns', array( $this, 'columns' ), 20 );
		add_filter( 'manage_' . Taxonomy::NAME . '_custom_column', array( $this, 'column' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the FAQs box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_faqs', __( 'FAQs', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'default' );
	}

	/**
	 * Loads the boxes' script on the listing screen and the Edit Listing Category screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		if ( Boxes::on_listing_screen( $hook ) || ( 'term.php' === $hook && $screen && Taxonomy::NAME === $screen->taxonomy ) ) {
			Boxes::enqueue_script();
		}
	}

	/**
	 * Prints the FAQs box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$category = Taxonomy::listing_category( $post->ID );
		$count    = $category ? count( Faq::category_items( $category['term']->term_id ) ) : 0;

		wp_nonce_field( 'crc_faqs_save', self::NONCE );
		?>
		<p class="description"><?php esc_html_e( 'Questions and answers about this listing. On the listing page each question opens to show its answer. They show after the questions of the listing\'s category, which every listing in that category shares.', 'crc-real-estate' ); ?></p>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::SHOW_CATEGORY ); ?>" value="1"<?php checked( Faq::shows_category( $post->ID ) ); ?>>
				<?php esc_html_e( 'Show the questions of the listing\'s category first', 'crc-real-estate' ); ?>
			</label>
		</p>
		<p class="description">
			<?php
			if ( $category ) {
				printf(
					/* translators: 1: category name, 2: number of questions. */
					esc_html( _n( '%1$s has %2$s question.', '%1$s has %2$s questions.', $count, 'crc-real-estate' ) ),
					esc_html( $category['term']->name ),
					esc_html( number_format_i18n( $count ) )
				);
				echo ' ';
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( (string) get_edit_term_link( $category['term']->term_id, Taxonomy::NAME, Post_Type::NAME ) ),
					esc_html__( 'Edit the category\'s questions', 'crc-real-estate' )
				);
			} else {
				esc_html_e( 'Choose a category and save the listing to see its questions here. Each category\'s questions are set under Listings → Listing Categories.', 'crc-real-estate' );
			}
			?>
		</p>
		<h4 class="crc-box-subtitle"><?php esc_html_e( 'This listing\'s own questions', 'crc-real-estate' ); ?></h4>
		<?php
		self::editor( self::FIELD, Faq::own_items( $post->ID ) );
	}

	/**
	 * Prints the questions editor: a row for each question, and Add question.
	 *
	 * @param string  $name  Field name for the rows.
	 * @param array[] $items Questions.
	 */
	public static function editor( $name, array $items ) {
		?>
		<div class="crc-box crc-faq-box">
			<div class="crc-box-group" data-group="faq">
				<ul class="crc-box-items crc-faq-rows">
					<?php
					foreach ( $items as $d => $item ) {
						self::row( $name . '[' . $d . ']', $item );
					}
					?>
				</ul>
				<p class="crc-box-group-foot"><button type="button" class="button crc-box-item-add"><?php esc_html_e( 'Add question', 'crc-real-estate' ); ?></button></p>
			</div>
			<p class="description"><?php esc_html_e( 'Leave an empty line in an answer to start a new paragraph. The link is optional and shows under the answer: for example, the text "Enquire about this land" with the link #crc-inquiry-1 takes people to the inquiry form on the same page. A link can also be a web address. Drag the rows to change the order.', 'crc-real-estate' ); ?></p>
			<template class="crc-box-item-template">
				<?php
				self::row(
					$name . '[{d}]',
					array(
						'question'  => '',
						'answer'    => '',
						'link_text' => '',
						'link_url'  => '',
					)
				);
				?>
			</template>
		</div>
		<?php
	}

	/**
	 * Prints a question row: drag handle, question, answer, link and a remove button.
	 *
	 * @param string $name Field name.
	 * @param array  $item Question.
	 */
	private static function row( $name, array $item ) {
		?>
		<li class="crc-box-item crc-faq-row">
			<span class="crc-box-item-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<div class="crc-faq-row-fields">
				<input type="text" name="<?php echo esc_attr( $name . '[question]' ); ?>" value="<?php echo esc_attr( $item['question'] ); ?>" class="crc-faq-row-question" placeholder="<?php esc_attr_e( 'Question, for example How do I arrange a viewing?', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Question', 'crc-real-estate' ); ?>" autocomplete="off">
				<textarea name="<?php echo esc_attr( $name . '[answer]' ); ?>" rows="3" placeholder="<?php esc_attr_e( 'Answer', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Answer', 'crc-real-estate' ); ?>"><?php echo esc_textarea( $item['answer'] ); ?></textarea>
				<div class="crc-faq-row-link">
					<input type="text" name="<?php echo esc_attr( $name . '[link_text]' ); ?>" value="<?php echo esc_attr( $item['link_text'] ); ?>" placeholder="<?php esc_attr_e( 'Link text (optional), for example Enquire about this land', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Link text', 'crc-real-estate' ); ?>" autocomplete="off">
					<input type="text" name="<?php echo esc_attr( $name . '[link_url]' ); ?>" value="<?php echo esc_attr( $item['link_url'] ); ?>" placeholder="<?php esc_attr_e( 'Link (optional), for example #crc-inquiry-1', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Link', 'crc-real-estate' ); ?>" autocomplete="off">
				</div>
			</div>
			<button type="button" class="crc-box-item-remove" aria-label="<?php esc_attr_e( 'Remove this question', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</li>
		<?php
	}

	/**
	 * Saves the listing's own questions, and whether it shows its category's.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_faqs_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_items().
		$items = Faq::sanitize_items( isset( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array() );

		if ( $items ) {
			update_post_meta( $post_id, Faq::META, wp_slash( $items ) );
		} else {
			delete_post_meta( $post_id, Faq::META );
		}

		if ( empty( $_POST[ self::SHOW_CATEGORY ] ) ) {
			update_post_meta( $post_id, Faq::HIDE_META, '1' );
		} else {
			delete_post_meta( $post_id, Faq::HIDE_META );
		}
	}

	/**
	 * Adds the FAQs field to the Edit Listing Category screen.
	 *
	 * @param \WP_Term $term Category being edited.
	 */
	public function category_field( $term ) {
		?>
		<tr class="form-field term-faqs-wrap">
			<th scope="row"><?php esc_html_e( 'FAQs', 'crc-real-estate' ); ?></th>
			<td>
				<?php wp_nonce_field( 'crc_category_faqs_save', self::CATEGORY_NONCE ); ?>
				<p class="description"><?php esc_html_e( 'Questions and answers shown on every listing in this category, in this order, before a listing\'s own questions. On the listing page each question opens to show its answer. A listing can leave these out in its FAQs box.', 'crc-real-estate' ); ?></p>
				<?php self::editor( self::CATEGORY_FIELD, Faq::category_items( $term->term_id ) ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the category's questions.
	 *
	 * @param int $term_id Category ID.
	 */
	public function save_category( $term_id ) {
		if ( ! isset( $_POST[ self::CATEGORY_NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::CATEGORY_NONCE ] ) ), 'crc_category_faqs_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_items().
		$items = Faq::sanitize_items( isset( $_POST[ self::CATEGORY_FIELD ] ) ? wp_unslash( $_POST[ self::CATEGORY_FIELD ] ) : array() );

		if ( $items ) {
			update_term_meta( $term_id, Faq::META, wp_slash( $items ) );
		} else {
			delete_term_meta( $term_id, Faq::META );
		}
	}

	/**
	 * Adds an FAQs column to the Listing Categories list, before the listing count.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		$listings = isset( $columns['posts'] ) ? $columns['posts'] : null;

		unset( $columns['posts'] );

		$columns['crc_faqs'] = __( 'FAQs', 'crc-real-estate' );

		if ( null !== $listings ) {
			$columns['posts'] = $listings;
		}

		return $columns;
	}

	/**
	 * Fills the FAQs column with the number of questions.
	 *
	 * @param string $content Column content.
	 * @param string $column  Column name.
	 * @param int    $term_id Category ID.
	 * @return string
	 */
	public function column( $content, $column, $term_id ) {
		if ( 'crc_faqs' !== $column ) {
			return $content;
		}

		$count = count( Faq::category_items( $term_id ) );

		/* translators: %s: number of questions. */
		return $count ? esc_html( sprintf( _n( '%s question', '%s questions', $count, 'crc-real-estate' ), number_format_i18n( $count ) ) ) : '&mdash;';
	}
}
