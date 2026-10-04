<?php
/**
 * Widgets page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Category_Carousel;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Widgets: ready-made pieces for any page, with their shortcodes
 * and what goes in them. For now, the category carousel and its cards.
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
	 * Registers the saved cards.
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
				'option'      => Category_Carousel::OPTION,
				'max'         => Category_Carousel::CARDS_MAX,
				'frameTitle'  => __( 'Choose a picture for this card', 'crc-real-estate' ),
				'frameButton' => __( 'Use this picture', 'crc-real-estate' ),
				'choose'      => __( 'Choose picture', 'crc-real-estate' ),
				'change'      => __( 'Change picture', 'crc-real-estate' ),
				'remove'      => __( 'Remove this card? It goes from the website when you press Save Changes.', 'crc-real-estate' ),
				/* translators: %s: most cards. */
				'full'        => __( 'The carousel can have up to %s cards.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$cards = Category_Carousel::cards();
		$tag   = '[' . Category_Carousel::SHORTCODE . ']';
		?>
		<div class="wrap crc-info crc-widgets">
			<h1><?php esc_html_e( 'Widgets', 'crc-real-estate' ); ?></h1>
			<?php settings_errors(); ?>

			<section class="crc-info-card" id="<?php echo esc_attr( Category_Carousel::SHORTCODE ); ?>">
				<h2 class="crc-info-title"><?php esc_html_e( 'Category carousel', 'crc-real-estate' ); ?></h2>
				<p class="crc-info-code">
					<code><?php echo esc_html( $tag ); ?></code>
					<button type="button" class="button button-small crc-copy" data-copy="<?php echo esc_attr( $tag ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'crc-real-estate' ); ?>"><?php esc_html_e( 'Copy', 'crc-real-estate' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Paste the shortcode into an Elementor Shortcode widget. Click a picture to change it. Drag a card by its handle, or use the arrows, to change the order.', 'crc-real-estate' ); ?></p>

				<form action="options.php" method="post">
					<?php settings_fields( self::GROUP ); ?>

					<ol class="crc-cards-list">
						<?php
						foreach ( $cards as $index => $card ) {
							$this->row( $index, $card );
						}
						?>
					</ol>

					<p class="crc-cards-empty"<?php echo $cards ? ' hidden' : ''; ?>><?php esc_html_e( 'No cards yet.', 'crc-real-estate' ); ?></p>

					<p class="crc-cards-tools">
						<button type="button" class="button crc-cards-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add a card', 'crc-real-estate' ); ?></button>
						<span class="crc-cards-note" role="status"></span>
					</p>

					<template id="crc-cards-template">
						<?php $this->row( 0, array() ); ?>
					</template>

					<datalist id="crc-cards-links">
						<?php foreach ( $this->category_links() as $name => $url ) : ?>
							<option value="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</datalist>

					<?php submit_button(); ?>
				</form>
			</section>
		</div>
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
		<li class="crc-cards-row">
			<span class="crc-cards-row-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to change the order', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<button type="button" class="crc-cards-row-choose" aria-label="<?php echo $preview ? esc_attr__( 'Change picture', 'crc-real-estate' ) : esc_attr__( 'Choose picture', 'crc-real-estate' ); ?>" title="<?php echo $preview ? esc_attr__( 'Change picture', 'crc-real-estate' ) : esc_attr__( 'Choose picture', 'crc-real-estate' ); ?>">
				<img src="<?php echo esc_url( (string) $preview ); ?>" alt=""<?php echo $preview ? '' : ' hidden'; ?>>
				<span class="dashicons dashicons-format-image" aria-hidden="true"<?php echo $preview ? ' hidden' : ''; ?>></span>
			</button>
			<input type="hidden" class="crc-cards-row-image" data-name="image" name="<?php echo esc_attr( $name ); ?>[image]" value="<?php echo esc_attr( $card['image'] ? (string) (int) $card['image'] : '' ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-title"><?php esc_html_e( 'Title', 'crc-real-estate' ); ?></label>
			<input type="text" class="crc-cards-row-title" id="<?php echo esc_attr( $id ); ?>-title" data-name="title" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $card['title'] ); ?>" placeholder="<?php esc_attr_e( 'Title', 'crc-real-estate' ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-link"><?php esc_html_e( 'Link', 'crc-real-estate' ); ?></label>
			<input type="text" class="crc-cards-row-link" id="<?php echo esc_attr( $id ); ?>-link" data-name="link" name="<?php echo esc_attr( $name ); ?>[link]" value="<?php echo esc_attr( $card['link'] ); ?>" list="crc-cards-links" inputmode="url" spellcheck="false" placeholder="<?php esc_attr_e( 'Link', 'crc-real-estate' ); ?>">
			<span class="crc-cards-row-actions">
				<button type="button" class="crc-cards-row-up" aria-label="<?php esc_attr_e( 'Move up', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Move up', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="crc-cards-row-down" aria-label="<?php esc_attr_e( 'Move down', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Move down', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				<button type="button" class="crc-cards-row-remove" aria-label="<?php esc_attr_e( 'Remove card', 'crc-real-estate' ); ?>" title="<?php esc_attr_e( 'Remove card', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
			</span>
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
