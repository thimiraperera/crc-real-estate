<?php
/**
 * Widgets page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Category_Carousel;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Widgets: ready-made pieces for any page, each on a tab with its
 * shortcode and what goes in it: the category carousel's cards, the keyword
 * ticker, and the search with its listings page. The shortcodes stay off the
 * tabs while Settings → Developer options hides them in wp-admin. FAQs have
 * a menu of their own (see Faqs).
 */
final class Widgets_Page {

	const SLUG  = 'crc-real-estate-widgets';
	const GROUP = 'crc_re_widgets';

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
	 * The Search tab.
	 *
	 * @var Search_Tab|null
	 */
	private $search = null;

	/**
	 * The Search tab.
	 *
	 * @return Search_Tab
	 */
	private function search() {
		if ( ! $this->search ) {
			$this->search = new Search_Tab();
		}

		return $this->search;
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		// After the Listings menu's own items, which WordPress adds at the usual time.
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

		$this->ticker()->register();
		$this->search()->register();
	}

	/**
	 * The tabs, in order: name => title.
	 *
	 * @return string[]
	 */
	private function tabs() {
		return array(
			'carousel' => __( 'Category carousel', 'crc-real-estate' ),
			'ticker'   => __( 'Keyword ticker', 'crc-real-estate' ),
			'search'   => __( 'Search', 'crc-real-estate' ),
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
			if ( 'ticker' === $current ) {
				$this->ticker()->render();
			} elseif ( 'search' === $current ) {
				$this->search()->render();
			} else {
				$this->carousel_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Prints a shortcode with its Copy button, unless the shortcodes are hidden in wp-admin.
	 *
	 * @param string $tag Shortcode, with its brackets.
	 */
	private function shortcode( $tag ) {
		if ( ! Settings::shortcodes_shown() ) {
			return;
		}
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
		$help  = __( 'Click a picture to change it. Drag a card by its handle, or use the arrows, to change the order. Under the cards, set how fast they slide and how often they move on by themselves.', 'crc-real-estate' );

		if ( Settings::shortcodes_shown() ) {
			$help = __( 'Paste the shortcode into an Elementor Shortcode widget.', 'crc-real-estate' ) . ' ' . $help;
		}
		?>
		<section class="crc-info-card" id="<?php echo esc_attr( Category_Carousel::SHORTCODE ); ?>">
			<h2 class="crc-info-title"><?php esc_html_e( 'Category carousel', 'crc-real-estate' ); ?></h2>
			<?php $this->shortcode( '[' . Category_Carousel::SHORTCODE . ']' ); ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>

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

				<h3><?php esc_html_e( 'Movement', 'crc-real-estate' ); ?></h3>
				<table class="form-table crc-carousel-movement" role="presentation">
					<tr>
						<th scope="row"><label for="crc-carousel-speed"><?php esc_html_e( 'Slide speed', 'crc-real-estate' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="crc-carousel-speed" name="<?php echo esc_attr( Category_Carousel::OPTION ); ?>[speed]" value="<?php echo esc_attr( (string) Category_Carousel::setting( 'speed' ) ); ?>" min="0.1" max="3" step="0.1" inputmode="decimal">
							<?php esc_html_e( 'seconds', 'crc-real-estate' ); ?>
							<p class="description"><?php esc_html_e( 'How long the cards take to slide over when someone uses the arrows, or when they move on by themselves. 0.3 is quick, 0.6 is gentle (the usual) and 1.5 is slow. Swiping on phones and touch pads keeps the device\'s own speed.', 'crc-real-estate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crc-carousel-autoplay"><?php esc_html_e( 'Move on by itself', 'crc-real-estate' ); ?></label></th>
						<td>
							<?php esc_html_e( 'every', 'crc-real-estate' ); ?>
							<input type="number" class="small-text" id="crc-carousel-autoplay" name="<?php echo esc_attr( Category_Carousel::OPTION ); ?>[autoplay]" value="<?php echo esc_attr( (string) Category_Carousel::setting( 'autoplay' ) ); ?>" min="0" max="60" step="0.5" inputmode="decimal">
							<?php esc_html_e( 'seconds', 'crc-real-estate' ); ?>
							<p class="description"><?php esc_html_e( 'The cards slide on to the next one by themselves this often, and back to the first after the last. They wait while the mouse is over the carousel, while someone is using it, and while it is off the screen. 0 keeps them still until someone uses the arrows. Visitors whose device asks for less motion are never moved.', 'crc-real-estate' ); ?></p>
						</td>
					</tr>
				</table>

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
