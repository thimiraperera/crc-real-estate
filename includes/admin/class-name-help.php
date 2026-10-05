<?php
/**
 * Suggested name and short link on the listing screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Listing_Name;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Under the listing's title: the name made from what is filled in, such as
 * "Bare land for sale in Galle", and a short link from it, each with a button
 * to use it. Both change as the boxes are filled in.
 */
final class Name_Help {

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'edit_form_after_title', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Loads the script that keeps the suggestion up to date.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( ! Boxes::on_listing_screen( $hook ) ) {
			return;
		}

		$categories = array();

		foreach ( Taxonomy::get_terms() as $term ) {
			$categories[ (string) $term->term_id ] = $term->slug;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		wp_enqueue_script( 'crc-re-admin-name', CRC_RE_URL . 'assets/js/admin-name.js', array( 'jquery' ), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-name',
			'crcReName',
			array_merge(
				Listing_Name::texts(),
				array(
					'categories' => $categories,
					'linkSet'    => __( 'Short link set. It is kept when you save the listing.', 'crc-real-estate' ),
				)
			)
		);
	}

	/**
	 * Prints the suggestion under the title.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public function render( $post ) {
		if ( Post_Type::NAME !== $post->post_type ) {
			return;
		}

		$name = Listing_Name::for_listing( $post->ID );

		/** This filter is documented in includes/class-post-type.php */
		$base = trim( (string) apply_filters( 'crc_re_listing_slug', 'listing' ), '/' );

		wp_nonce_field( 'crc_listing_name', Listing_Name::NONCE );
		?>
		<div class="crc-name-help">
			<p class="crc-name-help-row">
				<span class="crc-name-help-label"><?php esc_html_e( 'Suggested name', 'crc-real-estate' ); ?></span>
				<strong class="crc-name-help-name"><?php echo esc_html( $name ); ?></strong>
				<button type="button" class="button button-small crc-name-help-use-name"><?php esc_html_e( 'Use this name', 'crc-real-estate' ); ?></button>
			</p>
			<p class="crc-name-help-row">
				<span class="crc-name-help-label"><?php esc_html_e( 'Short link', 'crc-real-estate' ); ?></span>
				<code class="crc-name-help-link">/<?php echo esc_html( '' !== $base ? $base . '/' : '' ); ?><span class="crc-name-help-slug"><?php echo esc_html( Listing_Name::slug( $name ) ); ?></span>/</code>
				<button type="button" class="button button-small crc-name-help-use-link"><?php esc_html_e( 'Use this link', 'crc-real-estate' ); ?></button>
			</p>
			<p class="crc-name-help-note" role="status"></p>
			<p class="description"><?php esc_html_e( 'Made from the category, property type, bedrooms and district, and it changes as you fill them in. If the title is left empty, this name and link are used when you save.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}
}
