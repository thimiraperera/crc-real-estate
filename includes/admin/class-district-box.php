<?php
/**
 * District box.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\District;
use CRC\RealEstate\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * The District box on the listing screen: a search box that finds districts
 * as you type, from the site, and fills itself in from the place chosen in
 * the Location box while it is empty.
 */
final class District_Box {

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the box under the Category box.
	 */
	public function add() {
		add_meta_box( District::NAME . 'div', __( 'District', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'side', 'default' );
	}

	/**
	 * Loads the box's script and styles.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( ! Boxes::on_listing_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		wp_enqueue_script( 'crc-re-admin-district', CRC_RE_URL . 'assets/js/admin-district.js', array(), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-district',
			'crcReDistrict',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => District::AJAX,
				'nonce'   => wp_create_nonce( District::AJAX ),
				'none'    => __( 'No district matches that. Try another spelling.', 'crc-real-estate' ),
				'failed'  => __( 'The districts couldn\'t be loaded. Check the connection and try again.', 'crc-real-estate' ),
				/* translators: %s: number of districts. */
				'count'   => __( '%s districts', 'crc-real-estate' ),
				/* translators: %s: district, e.g. "Galle". */
				'filled'  => __( 'Filled in from the map: %s.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints the box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$current = District::of( $post->ID );
		$field   = 'tax_input[' . District::NAME . '][]';
		?>
		<div class="crc-district-box">
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="0">
			<input type="hidden" class="crc-district-id" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $current ? (string) $current['term']->term_id : '' ); ?>" data-name="<?php echo esc_attr( $current ? $current['name'] : '' ); ?>">
			<div class="crc-combo">
				<label class="screen-reader-text" for="crc-district-input"><?php esc_html_e( 'District', 'crc-real-estate' ); ?></label>
				<input type="text" id="crc-district-input" class="crc-combo-input" value="<?php echo esc_attr( $current ? $current['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search, for example Galle', 'crc-real-estate' ); ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="crc-district-options" autocomplete="off" spellcheck="false">
				<button type="button" class="crc-combo-toggle" tabindex="-1" aria-label="<?php esc_attr_e( 'Show all districts', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				<ul id="crc-district-options" class="crc-combo-list" role="listbox" aria-label="<?php esc_attr_e( 'Districts', 'crc-real-estate' ); ?>" hidden></ul>
			</div>
			<p class="crc-district-note" role="status"></p>
			<p class="description"><?php esc_html_e( 'Type to search, or press the arrow to see all 25. The district shows under the title with a pin and goes into the suggested name. Choosing a place in the Location box fills it in while it is empty.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}
}
