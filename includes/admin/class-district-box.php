<?php
/**
 * District box.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\District;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Town;

defined( 'ABSPATH' ) || exit;

/**
 * The District and town box on the listing screen: a search box that finds
 * districts as you type, from the site, and a Town field that suggests the
 * towns already used. Both fill themselves in from the place chosen in the
 * Location box while they are empty.
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
		add_meta_box( District::NAME . 'div', __( 'District and town', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'side', 'default' );
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
		wp_enqueue_script( 'crc-re-admin-town', CRC_RE_URL . 'assets/js/admin-town.js', array(), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-town',
			'crcReTown',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => Town::AJAX,
				'nonce'   => wp_create_nonce( Town::AJAX ),
				/* translators: %s: number of listings. */
				'used'    => __( '%s listings', 'crc-real-estate' ),
				'usedOne' => __( '1 listing', 'crc-real-estate' ),
				'isNew'   => __( 'A new town. It is added when you save the listing.', 'crc-real-estate' ),
				/* translators: %s: town, e.g. "Hikkaduwa". */
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
		$this->town_field( $post );
	}

	/**
	 * The Town field, under the district.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	private function town_field( $post ) {
		$town = Town::of( $post->ID );
		?>
		<div class="crc-town-box">
			<?php wp_nonce_field( 'crc_town_save', Town::NONCE ); ?>
			<label class="crc-town-label" for="crc-town-input"><?php esc_html_e( 'Town', 'crc-real-estate' ); ?></label>
			<div class="crc-combo">
				<input type="text" id="crc-town-input" class="crc-combo-input" name="<?php echo esc_attr( Town::FIELD ); ?>" value="<?php echo esc_attr( $town ? $town['name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'For example Hikkaduwa or Colombo 7', 'crc-real-estate' ); ?>" maxlength="<?php echo esc_attr( (string) Town::MAX_LENGTH ); ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="crc-town-options" aria-describedby="crc-town-description" autocomplete="off" spellcheck="false">
				<ul id="crc-town-options" class="crc-combo-list" role="listbox" aria-label="<?php esc_attr_e( 'Towns', 'crc-real-estate' ); ?>" hidden></ul>
			</div>
			<p class="crc-town-note" role="status"></p>
			<p class="description" id="crc-town-description"><?php esc_html_e( 'The town or area people would search for, for example Hikkaduwa, Kandy or Colombo 7. As you type, the towns already used on other listings are suggested, so each town is written the same way every time; pick one, or keep typing for a new town, which is added when the listing is saved. The search box on the site finds the listing by its town and by its district. Choosing a place in the Location box fills it in while it is empty. Listings → Towns lists every town, where a spelling can be put right for every listing at once.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}
}
