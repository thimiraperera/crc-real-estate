<?php
/**
 * Info page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Info: every shortcode the plugin offers, with its options and
 * ready-to-copy examples.
 */
final class Info_Page {

	const SLUG = 'crc-real-estate-info';

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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the page under Listings.
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'CRC Real Estate Info', 'crc-real-estate' ),
			__( 'Info', 'crc-real-estate' ),
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
			<h1><?php esc_html_e( 'CRC Real Estate', 'crc-real-estate' ); ?></h1>
			<p class="crc-info__meta">
				<?php
				/* translators: %s: plugin version. */
				echo esc_html( sprintf( __( 'Version %s', 'crc-real-estate' ), CRC_RE_VERSION ) );
				?>
				<?php if ( current_user_can( 'update_plugins' ) ) : ?>
					· <a href="<?php echo esc_url( \CRC\RealEstate\Updater::check_url() ); ?>"><?php esc_html_e( 'Check for updates', 'crc-real-estate' ); ?></a>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'Shortcodes', 'crc-real-estate' ); ?></h2>
			<p class="crc-info__intro">
				<?php esc_html_e( 'Paste a shortcode into Elementor\'s Shortcode widget. On a listing page it shows that listing. Anywhere else, add id="…" (hover a listing to see its ID).', 'crc-real-estate' ); ?>
			</p>

			<?php foreach ( Shortcodes::all() as $tag => $info ) : ?>
				<section class="crc-info__card" id="<?php echo esc_attr( $tag ); ?>">
					<h3 class="crc-info__title"><?php echo esc_html( $info['title'] ); ?></h3>
					<p class="crc-info__code">
						<code>[<?php echo esc_html( $tag ); ?>]</code>
						<?php $this->copy_button( '[' . $tag . ']', $copy, $copied ); ?>
					</p>
					<?php if ( $info['description'] ) : ?>
						<p><?php echo esc_html( $info['description'] ); ?></p>
					<?php endif; ?>

					<?php if ( $info['attributes'] ) : ?>
						<table class="widefat striped crc-info__table">
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
						<h4 class="crc-info__subtitle"><?php esc_html_e( 'Examples', 'crc-real-estate' ); ?></h4>
						<ul class="crc-info__examples">
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

			<h2><?php esc_html_e( 'Styles', 'crc-real-estate' ); ?></h2>
			<p class="crc-info__intro"><?php esc_html_e( 'Add a class in Elementor under Advanced → CSS Classes.', 'crc-real-estate' ); ?></p>
			<section class="crc-info__card">
				<?php foreach ( $this->styles() as $class => $about ) : ?>
					<h3 class="crc-info__title"><?php echo esc_html( $about ); ?></h3>
					<p class="crc-info__code">
						<code><?php echo esc_html( $class ); ?></code>
						<?php $this->copy_button( $class, $copy, $copied ); ?>
					</p>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * CSS classes to add to Elementor containers, with what each one does.
	 *
	 * @return string[]
	 */
	private function styles() {
		return array(
			'crc-card' => __( 'White box with rounded corners and a soft shadow', 'crc-real-estate' ),
		);
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
