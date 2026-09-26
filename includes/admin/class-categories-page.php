<?php
/**
 * Categories page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Categories: the fixed categories with how many listings each
 * has. Replaces WordPress's category screen, which is built for adding and
 * editing categories.
 */
final class Categories_Page {

	const SLUG = 'crc-real-estate-categories';

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
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'load-edit-tags.php', array( $this, 'redirect_old_screen' ) );
		add_action( 'load-term.php', array( $this, 'redirect_old_screen' ) );
	}

	/**
	 * Address of the page.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG );
	}

	/**
	 * Adds the page under Listings.
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'Categories', 'crc-real-estate' ),
			__( 'Categories', 'crc-real-estate' ),
			'edit_posts',
			self::SLUG,
			array( $this, 'render' )
		);

		// Puts back a category if one was removed some other way.
		add_action( 'load-' . $this->hook, array( Taxonomy::class, 'create_terms' ) );
	}

	/**
	 * Sends WordPress's category screen for listings to this page.
	 */
	public function redirect_old_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which screen to show.
		if ( isset( $_GET['taxonomy'] ) && Taxonomy::NAME === $_GET['taxonomy'] ) {
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/**
	 * Loads the page's styles.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( $hook === $this->hook ) {
			wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		}
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$list = admin_url( 'edit.php?post_type=' . Post_Type::NAME );
		?>
		<div class="wrap crc-categories">
			<h1><?php esc_html_e( 'Categories', 'crc-real-estate' ); ?></h1>
			<p class="crc-categories__intro"><?php esc_html_e( 'Every listing goes in one of these.', 'crc-real-estate' ); ?></p>

			<table class="widefat striped crc-categories__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Category', 'crc-real-estate' ); ?></th>
						<th scope="col"><?php esc_html_e( 'About', 'crc-real-estate' ); ?></th>
						<th scope="col" class="crc-categories__count"><?php esc_html_e( 'Published listings', 'crc-real-estate' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Links', 'crc-real-estate' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( Taxonomy::get_terms() as $term ) : ?>
						<tr>
							<td class="crc-categories__name"><?php echo esc_html( $term->name ); ?></td>
							<td><?php echo esc_html( $term->description ); ?></td>
							<td class="crc-categories__count"><?php echo esc_html( number_format_i18n( $term->count ) ); ?></td>
							<td class="crc-categories__link"><a href="<?php echo esc_url( add_query_arg( Taxonomy::NAME, $term->slug, $list ) ); ?>"><?php esc_html_e( 'See listings', 'crc-real-estate' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
