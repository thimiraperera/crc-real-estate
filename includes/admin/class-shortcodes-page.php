<?php
/**
 * Shortcodes page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Shortcodes: every shortcode the plugin offers, with its options
 * and ready-to-copy examples.
 */
final class Shortcodes_Page {

	const SLUG = 'crc-real-estate-shortcodes';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the page under Listings.
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'Shortcodes', 'crc-real-estate' ),
			__( 'Shortcodes', 'crc-real-estate' ),
			'edit_posts',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Loads the page's files.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		wp_enqueue_script( 'crc-re-admin-info', CRC_RE_URL . 'assets/js/admin-info.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$copy   = __( 'Copy', 'crc-real-estate' );
		$copied = __( 'Copied', 'crc-real-estate' );
		?>
		<div class="wrap crc-info">
			<h1><?php esc_html_e( 'Shortcodes', 'crc-real-estate' ); ?></h1>
			<p class="crc-info-intro">
				<?php esc_html_e( 'Place a shortcode in a Shortcode widget in Elementor, or anywhere WordPress accepts shortcodes. The listing shortcodes show the listing being viewed on a listing page; anywhere else, add id="…" with the listing ID, which you can see by hovering over a listing in All Listings. The keyword ticker, the category carousel, the listing carousel, the FAQs for any page and the testimonials work on any page and need no ID.', 'crc-real-estate' ); ?>
			</p>

			<?php foreach ( Shortcodes::all() as $tag => $info ) : ?>
				<section class="crc-info-card" id="<?php echo esc_attr( $tag ); ?>">
					<h3 class="crc-info-title"><?php echo esc_html( $info['title'] ); ?></h3>
					<p class="crc-info-code">
						<code>[<?php echo esc_html( $tag ); ?>]</code>
						<?php $this->copy_button( '[' . $tag . ']', $copy, $copied ); ?>
					</p>
					<?php if ( $info['description'] ) : ?>
						<p><?php echo esc_html( $info['description'] ); ?></p>
					<?php endif; ?>

					<?php if ( $info['attributes'] ) : ?>
						<table class="widefat striped crc-info-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Option', 'crc-real-estate' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Default', 'crc-real-estate' ); ?></th>
									<th scope="col"><?php esc_html_e( 'What it does', 'crc-real-estate' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $info['attributes'] as $name => $attribute ) : ?>
									<tr>
										<td><code><?php echo esc_html( $name ); ?></code></td>
										<td><?php echo '' === (string) $attribute['default'] ? '—' : '<code>' . esc_html( $attribute['default'] ) . '</code>'; ?></td>
										<td><?php echo esc_html( $attribute['description'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<?php if ( $info['examples'] ) : ?>
						<h4 class="crc-info-subtitle"><?php esc_html_e( 'Examples', 'crc-real-estate' ); ?></h4>
						<ul class="crc-info-examples">
							<?php foreach ( $info['examples'] as $example ) : ?>
								<li>
									<code><?php echo esc_html( $example ); ?></code>
									<?php $this->copy_button( $example, $copy, $copied ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Prints a button that copies text.
	 *
	 * @param string $text   Text to copy.
	 * @param string $label  Button label.
	 * @param string $copied Label after copying.
	 */
	private function copy_button( $text, $label, $copied ) {
		printf(
			'<button type="button" class="button button-small crc-copy" data-copy="%1$s" data-copied="%2$s">%3$s</button>',
			esc_attr( $text ),
			esc_attr( $copied ),
			esc_html( $label )
		);
	}
}
