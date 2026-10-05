<?php
/**
 * Widgets page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Category_Carousel;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Faq;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Widgets: ready-made pieces for any page, each on a tab with its
 * shortcode and what goes in it: the category carousel's cards, FAQs, and the
 * keyword ticker.
 */
final class Widgets_Page {

	const SLUG      = 'crc-real-estate-widgets';
	const GROUP     = 'crc_re_widgets';
	const FAQ_GROUP = 'crc_re_widgets_faqs';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * The Keyword ticker tab.
	 *
	 * @var Ticker_Tab|null
	 */
	private $ticker = null;

	/**
	 * The Keyword ticker tab.
	 *
	 * @return Ticker_Tab
	 */
	private function ticker() {
		if ( ! $this->ticker ) {
			$this->ticker = new Ticker_Tab();
		}

		return $this->ticker;
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		// After Listings → Inquiries, which WordPress adds at the usual time.
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the page under Listings.
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'Widgets', 'crc-real-estate' ),
			__( 'Widgets', 'crc-real-estate' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers what the tabs save. Each tab has its own group, so saving
	 * one tab never touches another's.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			Category_Carousel::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Category_Carousel::class, 'sanitize' ),
				'default'           => array( 'cards' => array() ),
			)
		);

		register_setting(
			self::FAQ_GROUP,
			Faq::GENERAL_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Faq::class, 'sanitize_general' ),
				'default'           => array( 'items' => array() ),
			)
		);

		$this->ticker()->register();
	}

	/**
	 * The tabs, in order: name => title.
	 *
	 * @return string[]
	 */
	private function tabs() {
		return array(
			'carousel' => __( 'Category carousel', 'crc-real-estate' ),
			'faqs'     => __( 'FAQs', 'crc-real-estate' ),
			'ticker'   => __( 'Keyword ticker', 'crc-real-estate' ),
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

		return isset( $this->tabs()[ $tab ] ) ? $tab : 'carousel';
	}

	/**
	 * Loads the page's files.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		wp_enqueue_script( 'crc-re-admin-info', CRC_RE_URL . 'assets/js/admin-info.js', array(), CRC_RE_VERSION, true );
		wp_enqueue_script( 'crc-re-admin-widgets', CRC_RE_URL . 'assets/js/admin-widgets.js', array( 'jquery', 'jquery-ui-sortable' ), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-widgets',
			'crcWidgets',
			array(
				'frameTitle'  => __( 'Choose a picture for this card', 'crc-real-estate' ),
				'frameButton' => __( 'Use this picture', 'crc-real-estate' ),
				'choose'      => __( 'Choose picture', 'crc-real-estate' ),
				'change'      => __( 'Change picture', 'crc-real-estate' ),
			)
		);

		$this->ticker()->enqueue();
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$current = $this->tab();
		?>
		<div class="wrap crc-info crc-widgets">
			<h1><?php esc_html_e( 'Widgets', 'crc-real-estate' ); ?></h1>
			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper crc-widgets-tabs" aria-label="<?php esc_attr_e( 'Widgets', 'crc-real-estate' ); ?>">
				<?php foreach ( $this->tabs() as $tab => $title ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab, admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG ) ) ); ?>" class="nav-tab<?php echo $tab === $current ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $title ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'faqs' === $current ) {
				$this->faqs_tab();
			} elseif ( 'ticker' === $current ) {
				$this->ticker()->render();
			} else {
				$this->carousel_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Prints a shortcode with its Copy button.
	 *
	 * @param string $tag Shortcode, with its brackets.
	 */
	private function shortcode( $tag ) {
		?>
		<p class="crc-info-code">
			<code><?php echo esc_html( $tag ); ?></code>
			<button type="button" class="button button-small crc-copy" data-copy="<?php echo esc_attr( $tag ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'crc-real-estate' ); ?>"><?php esc_html_e( 'Copy', 'crc-real-estate' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Prints the start of a list editor: what the script needs to know.
	 *
	 * @param string $option  Option the list is saved in.
	 * @param string $key     Key of the list in the option.
	 * @param string $prefix  Start of the fields' ids.
	 * @param int    $max     Most rows.
	 * @param string $confirm Question before a filled-in row is removed.
	 * @param string $full    Note when the list is full; %s is the most rows.
	 */
	private function editor_start( $option, $key, $prefix, $max, $confirm, $full ) {
		printf(
			'<div class="crc-list-editor" data-crc-list data-option="%1$s" data-key="%2$s" data-prefix="%3$s" data-max="%4$d" data-confirm="%5$s" data-full="%6$s">',
			esc_attr( $option ),
			esc_attr( $key ),
			esc_attr( $prefix ),
			(int) $max,
			esc_attr( $confirm ),
			esc_attr( $full )
		);
	}

	/**
	 * Prints the Category carousel tab.
	 */
	private function carousel_tab() {
		$cards = Category_Carousel::cards();
		?>
		<section class="crc-info-card" id="<?php echo esc_attr( Category_Carousel::SHORTCODE ); ?>">
			<h2 class="crc-info-title"><?php esc_html_e( 'Category carousel', 'crc-real-estate' ); ?></h2>
			<?php $this->shortcode( '[' . Category_Carousel::SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'Paste the shortcode into an Elementor Shortcode widget. Click a picture to change it. Drag a card by its handle, or use the arrows, to change the order.', 'crc-real-estate' ); ?></p>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				/* translators: %s: most cards. */
				$this->editor_start( Category_Carousel::OPTION, 'cards', 'crc-card-', Category_Carousel::CARDS_MAX, __( 'Remove this card? It goes from the website when you press Save Changes.', 'crc-real-estate' ), __( 'The carousel can have up to %s cards.', 'crc-real-estate' ) );
				?>
					<ol class="crc-cards-list" data-crc-items>
						<?php
						foreach ( $cards as $index => $card ) {
							$this->row( $index, $card );
						}
						?>
					</ol>

					<p class="crc-cards-empty" data-crc-empty<?php echo $cards ? ' hidden' : ''; ?>><?php esc_html_e( 'No cards yet.', 'crc-real-estate' ); ?></p>

					<p class="crc-cards-tools">
						<button type="button" class="button crc-cards-add" data-crc-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a card', 'crc-real-estate' ); ?></button>
						<span class="crc-cards-note" data-crc-note role="status"></span>
					</p>

					<template id="crc-cards-template" data-crc-template>
						<?php $this->row( 0, array() ); ?>
					</template>
				</div>

				<datalist id="crc-cards-links">
					<?php foreach ( $this->category_links() as $name => $url ) : ?>
						<option value="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</datalist>

				<?php submit_button(); ?>
			</form>
		</section>
		<?php
	}

	/**
	 * Prints the move and remove buttons of a row.
	 *
	 * @param string $remove What the remove button says.
	 */
	private function row_actions( $remove ) {
		?>
		<span class="crc-cards-row-actions">
			<button type="button" class="crc-cards-row-up" data-crc-up aria-label="<?php esc_attr_e( 'Move up', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Move up', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
			<button type="button" class="crc-cards-row-down" data-crc-down aria-label="<?php esc_attr_e( 'Move down', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Move down', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
			<button type="button" class="crc-cards-row-remove" data-crc-remove aria-label="<?php echo esc_attr( $remove ); ?>" title="<?php echo esc_attr( $remove ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
		</span>
		<?php
	}

	/**
	 * Prints one card: its picture, title and link on one line.
	 *
	 * @param int   $index Card number, from 0.
	 * @param array $card  Saved card.
	 */
	private function row( $index, array $card ) {
		$card    = wp_parse_args( $card, array( 'image' => 0, 'title' => '', 'link' => '' ) );
		$name    = Category_Carousel::OPTION . '[cards][' . (int) $index . ']';
		$preview = $card['image'] ? wp_get_attachment_image_url( (int) $card['image'], 'thumbnail' ) : '';
		$id      = 'crc-card-' . (int) $index;
		?>
		<li class="crc-cards-row" data-crc-row>
			<span class="crc-cards-row-handle dashicons dashicons-menu" data-crc-handle title="<?php esc_attr_e( 'Drag to change the order', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<button type="button" class="crc-cards-row-choose" aria-label="<?php echo $preview ? esc_attr__( 'Change picture', 'crc-real-estate' ) : esc_attr__( 'Choose picture', 'crc-real-estate' ); ?>" title="<?php echo $preview ? esc_attr__( 'Change picture', 'crc-real-estate' ) : esc_attr__( 'Choose picture', 'crc-real-estate' ); ?>">
				<img src="<?php echo esc_url( (string) $preview ); ?>" alt=""<?php echo $preview ? '' : ' hidden'; ?>>
				<span class="dashicons dashicons-format-image" aria-hidden="true"<?php echo $preview ? ' hidden' : ''; ?>></span>
			</button>
			<input type="hidden" class="crc-cards-row-image" data-name="image" name="<?php echo esc_attr( $name ); ?>[image]" value="<?php echo esc_attr( $card['image'] ? (string) (int) $card['image'] : '' ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-title"><?php esc_html_e( 'Title', 'crc-real-estate' ); ?></label>
			<input type="text" class="crc-cards-row-title" id="<?php echo esc_attr( $id ); ?>-title" data-name="title" data-crc-focus name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $card['title'] ); ?>" placeholder="<?php esc_attr_e( 'Title', 'crc-real-estate' ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-link"><?php esc_html_e( 'Link', 'crc-real-estate' ); ?></label>
			<input type="text" class="crc-cards-row-link" id="<?php echo esc_attr( $id ); ?>-link" data-name="link" name="<?php echo esc_attr( $name ); ?>[link]" value="<?php echo esc_attr( $card['link'] ); ?>" list="crc-cards-links" inputmode="url" spellcheck="false" placeholder="<?php esc_attr_e( 'Link', 'crc-real-estate' ); ?>">
			<?php $this->row_actions( __( 'Remove card', 'crc-real-estate' ) ); ?>
		</li>
		<?php
	}

	/**
	 * Prints the FAQs tab.
	 */
	private function faqs_tab() {
		$items = Faq::general_items();
		?>
		<section class="crc-info-card" id="<?php echo esc_attr( Faq::GENERAL_SHORTCODE ); ?>">
			<h2 class="crc-info-title"><?php esc_html_e( 'FAQs', 'crc-real-estate' ); ?></h2>
			<?php $this->shortcode( '[' . Faq::GENERAL_SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'Paste the shortcode into an Elementor Shortcode widget on any page. One question opens at a time, and the questions are given to search engines as FAQ schema. Leave an empty line between paragraphs of an answer. Drag a question by its handle, or use the arrows, to change the order.', 'crc-real-estate' ); ?></p>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::FAQ_GROUP );
				/* translators: %s: most questions. */
				$this->editor_start( Faq::GENERAL_OPTION, 'items', 'crc-faq-', Faq::MAX, __( 'Remove this question? It goes from the website when you press Save Changes.', 'crc-real-estate' ), __( 'There can be up to %s questions.', 'crc-real-estate' ) );
				?>
					<ol class="crc-faqs-list" data-crc-items>
						<?php
						foreach ( $items as $index => $item ) {
							$this->faq_row( $index, $item );
						}
						?>
					</ol>

					<p class="crc-cards-empty" data-crc-empty<?php echo $items ? ' hidden' : ''; ?>><?php esc_html_e( 'No questions yet.', 'crc-real-estate' ); ?></p>

					<p class="crc-cards-tools">
						<button type="button" class="button crc-cards-add" data-crc-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a question', 'crc-real-estate' ); ?></button>
						<span class="crc-cards-note" data-crc-note role="status"></span>
					</p>

					<template id="crc-faqs-template" data-crc-template>
						<?php $this->faq_row( 0, array() ); ?>
					</template>
				</div>

				<?php submit_button(); ?>
			</form>
		</section>
		<?php
	}

	/**
	 * Prints one question: the question, and its answer under it.
	 *
	 * @param int   $index Question number, from 0.
	 * @param array $item  Saved question.
	 */
	private function faq_row( $index, array $item ) {
		$item = wp_parse_args( $item, array( 'question' => '', 'answer' => '' ) );
		$name = Faq::GENERAL_OPTION . '[items][' . (int) $index . ']';
		$id   = 'crc-faq-' . (int) $index;
		?>
		<li class="crc-faqs-row" data-crc-row>
			<span class="crc-cards-row-handle dashicons dashicons-menu" data-crc-handle title="<?php esc_attr_e( 'Drag to change the order', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<span class="crc-faqs-row-fields">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-question"><?php esc_html_e( 'Question', 'crc-real-estate' ); ?></label>
				<input type="text" class="crc-faqs-row-question" id="<?php echo esc_attr( $id ); ?>-question" data-name="question" data-crc-focus name="<?php echo esc_attr( $name ); ?>[question]" value="<?php echo esc_attr( $item['question'] ); ?>" placeholder="<?php esc_attr_e( 'Question', 'crc-real-estate' ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-answer"><?php esc_html_e( 'Answer', 'crc-real-estate' ); ?></label>
				<textarea class="crc-faqs-row-answer" id="<?php echo esc_attr( $id ); ?>-answer" data-name="answer" name="<?php echo esc_attr( $name ); ?>[answer]" rows="3" placeholder="<?php esc_attr_e( 'Answer', 'crc-real-estate' ); ?>"><?php echo esc_textarea( $item['answer'] ); ?></textarea>
			</span>
			<?php $this->row_actions( __( 'Remove question', 'crc-real-estate' ) ); ?>
		</li>
		<?php
	}

	/**
	 * Links to the listing categories, offered in the Button link boxes.
	 *
	 * @return string[] Category name => address.
	 */
	private function category_links() {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
			)
		);
		$links = array();

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $links;
		}

		foreach ( $terms as $term ) {
			$url = get_term_link( $term );

			if ( ! is_wp_error( $url ) ) {
				$links[ $term->name ] = $url;
			}
		}

		return $links;
	}
}
