<?php
/**
 * Testimonials settings page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Testimonials → Settings: the shortcode, how many testimonials to show and
 * how often the carousel moves on by itself.
 */
final class Testimonials_Settings {

	const SLUG  = 'crc-testimonials-settings';
	const GROUP = 'crc_re_testimonials_settings';

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
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds Settings under Testimonials.
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Testimonials::POST_TYPE,
			__( 'Testimonials Settings', 'crc-real-estate' ),
			__( 'Settings', 'crc-real-estate' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the settings and their fields.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			Testimonials::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Testimonials::class, 'sanitize_settings' ),
				'default'           => Testimonials::sanitize_settings( array() ),
			)
		);

		add_settings_section( 'crc_testimonials_carousel', __( 'Carousel', 'crc-real-estate' ), '__return_false', self::SLUG );

		add_settings_field(
			'crc_testimonials_count',
			__( 'Testimonials to show', 'crc-real-estate' ),
			array( $this, 'field' ),
			self::SLUG,
			'crc_testimonials_carousel',
			array(
				'label_for' => 'crc-testimonials-count',
				'key'       => 'count',
				'min'       => 1,
				'max'       => Testimonials::COUNT_MAX,
				'unit'      => __( 'testimonials', 'crc-real-estate' ),
				/* translators: %d: most testimonials. */
				'help'      => sprintf( __( 'Each time someone opens the page, this many are picked at random from all your published testimonials, so visitors see a different mix. From 1 to %d.', 'crc-real-estate' ), Testimonials::COUNT_MAX ),
			)
		);

		add_settings_field(
			'crc_testimonials_delay',
			__( 'Moves on every', 'crc-real-estate' ),
			array( $this, 'field' ),
			self::SLUG,
			'crc_testimonials_carousel',
			array(
				'label_for' => 'crc-testimonials-delay',
				'key'       => 'delay',
				'min'       => 0,
				'max'       => Testimonials::DELAY_MAX,
				'unit'      => __( 'seconds', 'crc-real-estate' ),
				'help'      => __( 'The carousel moves to the next testimonial by itself after this many seconds, and waits while the mouse is over it or someone is dragging it. Use 0 to keep it still; people can still drag it or swipe it.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints a number field.
	 *
	 * @param array $args Field details.
	 */
	public function field( $args ) {
		$settings = Testimonials::settings();

		printf(
			'<input type="number" id="%1$s" name="%2$s[%3$s]" value="%4$d" min="%5$d" max="%6$d" step="1" class="small-text" aria-describedby="%1$s-help"> %7$s<p class="description" id="%1$s-help">%8$s</p>',
			esc_attr( $args['label_for'] ),
			esc_attr( Testimonials::OPTION ),
			esc_attr( $args['key'] ),
			(int) $settings[ $args['key'] ],
			(int) $args['min'],
			(int) $args['max'],
			esc_html( $args['unit'] ),
			esc_html( $args['help'] )
		);
	}

	/**
	 * Loads the copy button's script.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		wp_enqueue_script( 'crc-re-admin-info', CRC_RE_URL . 'assets/js/admin-info.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$tag = '[' . Testimonials::SHORTCODE . ']';
		?>
		<div class="wrap crc-info">
			<h1><?php esc_html_e( 'Testimonials Settings', 'crc-real-estate' ); ?></h1>
			<?php settings_errors(); ?>
			<p class="crc-info-code">
				<code><?php echo esc_html( $tag ); ?></code>
				<button type="button" class="button button-small crc-copy" data-copy="<?php echo esc_attr( $tag ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'crc-real-estate' ); ?>"><?php esc_html_e( 'Copy', 'crc-real-estate' ); ?></button>
			</p>
			<p class="crc-info-intro"><?php esc_html_e( 'Paste the shortcode into an Elementor Shortcode widget on any page. It shows your published testimonials in a carousel that goes round and round: three at a time on computers, two on tablets and one on phones.', 'crc-real-estate' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
