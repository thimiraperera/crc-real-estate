<?php
/**
 * FAQ screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Faqs;
use CRC\RealEstate\Sections\Faq;
use CRC\RealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The FAQ screen: a name for wp-admin where a title usually goes, then a box
 * with the questions, each with its answer under it, to drag into order. The
 * list shows each FAQ's name, how many questions it shows and its shortcode.
 */
final class Faqs_Box {

	const NONCE = 'crc_faq_nonce';
	const FIELD = 'crc_faq';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'enter_title_here', array( $this, 'name_placeholder' ), 10, 2 );
		add_action( 'add_meta_boxes_' . Faqs::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Faqs::POST_TYPE, array( $this, 'save' ) );
		add_filter( 'manage_' . Faqs::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Faqs::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
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
		return Faqs::POST_TYPE === $post->post_type ? __( 'Name, for example Home page FAQs', 'crc-real-estate' ) : $text;
	}

	/**
	 * Adds the box under the name.
	 */
	public function add() {
		add_meta_box( 'crc_faq_questions', __( 'Questions and answers', 'crc-real-estate' ), array( $this, 'render' ), Faqs::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Loads the styles, the copy buttons and the list's script on the FAQ screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || Faqs::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );

		if ( Settings::shortcodes_shown() ) {
			wp_enqueue_script( 'crc-re-admin-info', CRC_RE_URL . 'assets/js/admin-info.js', array(), CRC_RE_VERSION, true );
		}

		if ( 'edit.php' !== $hook ) {
			wp_enqueue_script( 'crc-re-admin-widgets', CRC_RE_URL . 'assets/js/admin-widgets.js', array( 'jquery', 'jquery-ui-sortable' ), CRC_RE_VERSION, true );
		}
	}

	/**
	 * Prints the box: the FAQ's shortcode, then its questions.
	 *
	 * @param \WP_Post $post FAQ being edited.
	 */
	public function render( $post ) {
		$items = Faqs::items( $post->ID );
		$shown = Settings::shortcodes_shown();
		$help  = __( 'One question opens at a time, and the questions are given to search engines as FAQ schema. Leave an empty line between paragraphs of an answer. Drag a question by its handle, or use the arrows, to change the order.', 'crc-real-estate' );

		if ( $shown ) {
			$help = __( 'Paste the shortcode into an Elementor Shortcode widget on any page; the questions show there once the FAQ is published.', 'crc-real-estate' ) . ' ' . $help;
		}

		wp_nonce_field( 'crc_faq_save', self::NONCE );

		if ( $shown ) {
			$tag = Faqs::shortcode( $post->ID );
			?>
			<p class="crc-info-code">
				<code><?php echo esc_html( $tag ); ?></code>
				<button type="button" class="button button-small crc-copy" data-copy="<?php echo esc_attr( $tag ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'crc-real-estate' ); ?>"><?php esc_html_e( 'Copy', 'crc-real-estate' ); ?></button>
			</p>
			<?php
		}
		?>
		<p class="description"><?php echo esc_html( $help ); ?></p>

		<?php
		printf(
			'<div class="crc-list-editor" data-crc-list data-option="%1$s" data-key="items" data-prefix="crc-faq-" data-max="%2$d" data-confirm="%3$s" data-full="%4$s">',
			esc_attr( self::FIELD ),
			(int) Faq::MAX,
			esc_attr__( 'Remove this question? It goes from the website when the FAQ is saved.', 'crc-real-estate' ),
			/* translators: %s: most questions. */
			esc_attr__( 'There can be up to %s questions.', 'crc-real-estate' )
		);
		?>
			<ol class="crc-faqs-list" data-crc-items>
				<?php
				foreach ( $items as $index => $item ) {
					$this->row( $index, $item );
				}
				?>
			</ol>

			<p class="crc-cards-empty" data-crc-empty<?php echo $items ? ' hidden' : ''; ?>><?php esc_html_e( 'No questions yet.', 'crc-real-estate' ); ?></p>

			<p class="crc-cards-tools">
				<button type="button" class="button crc-cards-add" data-crc-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a question', 'crc-real-estate' ); ?></button>
				<span class="crc-cards-note" data-crc-note role="status"></span>
			</p>

			<template data-crc-template>
				<?php $this->row( 0, array() ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * Prints one question: the question, its answer under it, and buttons to
	 * move it or remove it.
	 *
	 * @param int   $index Question number, from 0.
	 * @param array $item  Saved question.
	 */
	private function row( $index, array $item ) {
		$item   = wp_parse_args( $item, array( 'question' => '', 'answer' => '' ) );
		$name   = self::FIELD . '[items][' . (int) $index . ']';
		$id     = 'crc-faq-' . (int) $index;
		$remove = __( 'Remove question', 'crc-real-estate' );
		?>
		<li class="crc-faqs-row" data-crc-row>
			<span class="crc-cards-row-handle dashicons dashicons-menu" data-crc-handle title="<?php esc_attr_e( 'Drag to change the order', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<span class="crc-faqs-row-fields">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-question"><?php esc_html_e( 'Question', 'crc-real-estate' ); ?></label>
				<input type="text" class="crc-faqs-row-question" id="<?php echo esc_attr( $id ); ?>-question" data-name="question" data-crc-focus name="<?php echo esc_attr( $name ); ?>[question]" value="<?php echo esc_attr( $item['question'] ); ?>" placeholder="<?php esc_attr_e( 'Question', 'crc-real-estate' ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-answer"><?php esc_html_e( 'Answer', 'crc-real-estate' ); ?></label>
				<textarea class="crc-faqs-row-answer" id="<?php echo esc_attr( $id ); ?>-answer" data-name="answer" name="<?php echo esc_attr( $name ); ?>[answer]" rows="3" placeholder="<?php esc_attr_e( 'Answer', 'crc-real-estate' ); ?>"><?php echo esc_textarea( $item['answer'] ); ?></textarea>
			</span>
			<span class="crc-cards-row-actions">
				<button type="button" class="crc-cards-row-up" data-crc-up aria-label="<?php esc_attr_e( 'Move up', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Move up', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="crc-cards-row-down" data-crc-down aria-label="<?php esc_attr_e( 'Move down', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Move down', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				<button type="button" class="crc-cards-row-remove" data-crc-remove aria-label="<?php echo esc_attr( $remove ); ?>" title="<?php echo esc_attr( $remove ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
			</span>
		</li>
		<?php
	}

	/**
	 * Saves the questions. WordPress saves the name.
	 *
	 * @param int $post_id FAQ ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_faq_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_items().
		$items = Faq::sanitize_items( isset( $_POST[ self::FIELD ]['items'] ) ? wp_unslash( $_POST[ self::FIELD ]['items'] ) : array() );

		if ( $items ) {
			update_post_meta( $post_id, Faq::META, wp_slash( $items ) );
		} else {
			delete_post_meta( $post_id, Faq::META );
		}
	}

	/**
	 * The list's columns: name, questions, shortcode (unless the shortcodes
	 * are hidden in wp-admin) and date.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		$list = array();

		if ( isset( $columns['cb'] ) ) {
			$list['cb'] = $columns['cb'];
		}

		$list['title']         = __( 'Name', 'crc-real-estate' );
		$list['crc_questions'] = __( 'Questions', 'crc-real-estate' );

		if ( Settings::shortcodes_shown() ) {
			$list['crc_shortcode'] = __( 'Shortcode', 'crc-real-estate' );
		}

		if ( isset( $columns['date'] ) ) {
			$list['date'] = $columns['date'];
		}

		return $list;
	}

	/**
	 * Fills the Questions and Shortcode columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id FAQ ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'crc_questions' === $column ) {
			$count = count( Faq::with_answers( Faqs::items( $post_id ) ) );

			if ( $count ) {
				/* translators: %s: number of questions. */
				echo esc_html( sprintf( _n( '%s question', '%s questions', $count, 'crc-real-estate' ), number_format_i18n( $count ) ) );
			} else {
				echo '<span class="crc-warning">' . esc_html__( 'No questions with answers yet, so it shows nothing on the site.', 'crc-real-estate' ) . '</span>';
			}
		} elseif ( 'crc_shortcode' === $column ) {
			$tag = Faqs::shortcode( $post_id );

			printf(
				'<code>%1$s</code> <button type="button" class="button button-small crc-copy" data-copy="%2$s" data-copied="%3$s">%4$s</button>',
				esc_html( $tag ),
				esc_attr( $tag ),
				esc_attr__( 'Copied', 'crc-real-estate' ),
				esc_html__( 'Copy', 'crc-real-estate' )
			);
		}
	}
}
