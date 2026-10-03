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
				/* translators: %s: card number. */
				'card'        => __( 'Card %s', 'crc-real-estate' ),
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
			<p class="crc-info-intro">
				<?php esc_html_e( 'Ready-made pieces you can place on any page. Copy a widget\'s shortcode, add a Shortcode widget in Elementor where you want it, and paste the shortcode in. What the widget shows is set here.', 'crc-real-estate' ); ?>
			</p>

			<section class="crc-info-card" id="<?php echo esc_attr( Category_Carousel::SHORTCODE ); ?>">
				<h2 class="crc-info-title"><?php esc_html_e( 'Category carousel', 'crc-real-estate' ); ?></h2>
				<p class="crc-info-code">
					<code><?php echo esc_html( $tag ); ?></code>
					<button type="button" class="button button-small crc-copy" data-copy="<?php echo esc_attr( $tag ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'crc-real-estate' ); ?>"><?php esc_html_e( 'Copy', 'crc-real-estate' ); ?></button>
				</p>
				<p><?php esc_html_e( 'A row of square cards, each with a picture, a title and a button. People move through them with the arrows, by swiping on a phone, or by dragging with the mouse. The cards line up with the container on the left and run on to the edge of the window on the right, so people can see there are more. The picture zooms in a little when the mouse is over a card, and the bottom of each card is shaded so the title and button are easy to read.', 'crc-real-estate' ); ?></p>
				<p><?php esc_html_e( 'Computers show three cards across, tablets two, and phones one with the next one peeking in. Add the cards below in the order they should show; drag a card by its handle, or use Move up and Move down, to change the order. Press Save Changes when you are done.', 'crc-real-estate' ); ?></p>

				<form action="options.php" method="post">
					<?php settings_fields( self::GROUP ); ?>

					<p class="crc-cards-empty"<?php echo $cards ? ' hidden' : ''; ?>><?php esc_html_e( 'No cards yet. Press Add a card to make the first one. Until there are cards, the carousel shows nothing on the website.', 'crc-real-estate' ); ?></p>

					<ol class="crc-cards-list">
						<?php
						foreach ( $cards as $index => $card ) {
							$this->row( $index, $card );
						}
						?>
					</ol>

					<p class="crc-cards-tools">
						<button type="button" class="button crc-cards-add"><?php esc_html_e( 'Add a card', 'crc-real-estate' ); ?></button>
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
	 * Prints one card's fields.
	 *
	 * @param int   $index Card number, from 0.
	 * @param array $card  Saved card.
	 */
	private function row( $index, array $card ) {
		$card    = wp_parse_args( $card, array( 'image' => 0, 'title' => '', 'button' => '', 'link' => '' ) );
		$name    = Category_Carousel::OPTION . '[cards][' . (int) $index . ']';
		$preview = $card['image'] ? wp_get_attachment_image_url( (int) $card['image'], 'medium' ) : '';
		$id      = 'crc-card-' . (int) $index;
		?>
		<li class="crc-cards-row">
			<div class="crc-cards-row-side">
				<span class="crc-cards-row-handle" title="<?php esc_attr_e( 'Drag to change the order', 'crc-real-estate' ); ?>" aria-hidden="true"><span class="dashicons dashicons-move"></span></span>
				<strong class="crc-cards-row-number">
					<?php
					/* translators: %s: card number. */
					echo esc_html( sprintf( __( 'Card %s', 'crc-real-estate' ), $index + 1 ) );
					?>
				</strong>
			</div>

			<div class="crc-cards-row-picture">
				<div class="crc-cards-row-preview">
					<img src="<?php echo esc_url( (string) $preview ); ?>" alt=""<?php echo $preview ? '' : ' hidden'; ?>>
					<span class="crc-cards-row-nopicture"<?php echo $preview ? ' hidden' : ''; ?>><?php esc_html_e( 'No picture yet', 'crc-real-estate' ); ?></span>
				</div>
				<input type="hidden" class="crc-cards-row-image" data-name="image" name="<?php echo esc_attr( $name ); ?>[image]" value="<?php echo esc_attr( $card['image'] ? (string) (int) $card['image'] : '' ); ?>">
				<p class="crc-cards-row-picture-tools">
					<button type="button" class="button crc-cards-row-choose"><?php echo $preview ? esc_html__( 'Change picture', 'crc-real-estate' ) : esc_html__( 'Choose picture', 'crc-real-estate' ); ?></button>
					<button type="button" class="button-link button-link-delete crc-cards-row-clear"<?php echo $preview ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove picture', 'crc-real-estate' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'The picture fills the whole card. Square pictures work best, at least 800 × 800 pixels; other shapes are trimmed to a square from the middle.', 'crc-real-estate' ); ?></p>
			</div>

			<div class="crc-cards-row-fields">
				<p>
					<label for="<?php echo esc_attr( $id ); ?>-title"><?php esc_html_e( 'Title', 'crc-real-estate' ); ?></label>
					<input type="text" class="widefat" id="<?php echo esc_attr( $id ); ?>-title" data-name="title" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $card['title'] ); ?>">
					<span class="description"><?php esc_html_e( 'Shown at the bottom of the card, over the shading, for example Beachfront Properties.', 'crc-real-estate' ); ?></span>
				</p>
				<p>
					<label for="<?php echo esc_attr( $id ); ?>-button"><?php esc_html_e( 'Button text', 'crc-real-estate' ); ?></label>
					<input type="text" class="widefat" id="<?php echo esc_attr( $id ); ?>-button" data-name="button" name="<?php echo esc_attr( $name ); ?>[button]" value="<?php echo esc_attr( $card['button'] ); ?>" placeholder="<?php esc_attr_e( 'View Properties', 'crc-real-estate' ); ?>">
					<span class="description"><?php esc_html_e( 'The words on the button, under the title. Leave it empty to use View Properties.', 'crc-real-estate' ); ?></span>
				</p>
				<p>
					<label for="<?php echo esc_attr( $id ); ?>-link"><?php esc_html_e( 'Button link', 'crc-real-estate' ); ?></label>
					<input type="text" class="widefat" id="<?php echo esc_attr( $id ); ?>-link" data-name="link" name="<?php echo esc_attr( $name ); ?>[link]" value="<?php echo esc_attr( $card['link'] ); ?>" list="crc-cards-links" inputmode="url" spellcheck="false" placeholder="https://">
					<span class="description"><?php esc_html_e( 'The page the button opens, such as one of your listing categories: click in the box to pick one, or paste any web address. Leave it empty to show the card without a button.', 'crc-real-estate' ); ?></span>
				</p>
			</div>

			<div class="crc-cards-row-actions">
				<button type="button" class="button-link crc-cards-row-up"><?php esc_html_e( 'Move up', 'crc-real-estate' ); ?></button>
				<button type="button" class="button-link crc-cards-row-down"><?php esc_html_e( 'Move down', 'crc-real-estate' ); ?></button>
				<button type="button" class="button-link button-link-delete crc-cards-row-remove"><?php esc_html_e( 'Remove card', 'crc-real-estate' ); ?></button>
			</div>
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
