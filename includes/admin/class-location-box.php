<?php
/**
 * Location box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Location;
use CRC\RealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors mark the listing's exact place on a map, find it by searching,
 * keep a Google Maps link, and turn the map on the listing page off. Only
 * people who edit listings see the exact place.
 */
final class Location_Box {

	const NONCE = 'crc_location_nonce';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the Location box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_location', __( 'Location', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Loads the map and its script on the listing screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( ! Boxes::on_listing_screen( $hook ) ) {
			return;
		}

		Location::register_leaflet();
		wp_enqueue_style( 'crc-re-leaflet' );
		wp_enqueue_script( 'crc-re-admin-location', CRC_RE_URL . 'assets/js/admin-location.js', array( 'crc-re-leaflet' ), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-location',
			'crcReLocation',
			array(
				'start'     => array(
					'lat' => (float) Settings::map( 'lat' ),
					'lng' => (float) Settings::map( 'lng' ),
				),
				'tiles'     => Location::tiles(),
				'credit'    => Location::credit(),
				'searching' => __( 'Searching…', 'crc-real-estate' ),
				'noResults' => __( 'Nothing found. Try another name, or click the map to mark the place.', 'crc-real-estate' ),
				'failed'    => __( 'The search didn\'t work just now. Try again in a moment.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints the Location box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$exact  = Location::exact( $post->ID );
		$hidden = '1' === (string) get_post_meta( $post->ID, Location::HIDDEN_META, true );

		wp_nonce_field( 'crc_location_save', self::NONCE );
		?>
		<div class="crc-location-box">
			<p class="crc-location-box-show">
				<label>
					<input type="checkbox" name="crc_location_show" value="1"<?php checked( ! $hidden, true ); ?>>
					<?php esc_html_e( 'Show the map on the listing page', 'crc-real-estate' ); ?>
				</label>
			</p>
			<p class="description">
				<?php
				/* translators: %s: area size in km, e.g. "15". */
				echo esc_html( sprintf( __( 'The listing page shows only the area within about %s km of the property, never its exact place. The area moves a little each day, so the place can\'t be worked out. Untick this to hide the map and its whole section on the listing page.', 'crc-real-estate' ), Settings::map( 'radius' ) ) );
				?>
			</p>

			<div class="crc-location-box-search">
				<input type="search" id="crc-location-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search for a place, for example Galle Fort', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Search for a place', 'crc-real-estate' ); ?>" autocomplete="off">
				<button type="button" class="button crc-location-box-find"><?php esc_html_e( 'Search', 'crc-real-estate' ); ?></button>
			</div>
			<ul class="crc-location-box-results" hidden></ul>
			<div class="crc-location-box-map"></div>
			<p class="description"><?php esc_html_e( 'Click the map or drag the pin to mark the exact place. Only people who edit listings see it.', 'crc-real-estate' ); ?></p>

			<div class="crc-fields crc-location-box-fields">
				<div class="crc-field-group">
					<p class="crc-field">
						<label for="crc-location-lat"><?php esc_html_e( 'Latitude', 'crc-real-estate' ); ?></label>
						<input type="text" inputmode="decimal" id="crc-location-lat" name="crc_location_lat" value="<?php echo esc_attr( $exact ? $exact['lat'] : '' ); ?>" class="regular-text" autocomplete="off">
					</p>
				</div>
				<div class="crc-field-group">
					<p class="crc-field">
						<label for="crc-location-lng"><?php esc_html_e( 'Longitude', 'crc-real-estate' ); ?></label>
						<input type="text" inputmode="decimal" id="crc-location-lng" name="crc_location_lng" value="<?php echo esc_attr( $exact ? $exact['lng'] : '' ); ?>" class="regular-text" autocomplete="off">
					</p>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'These fill in from the pin. You can also paste them, or paste both into Latitude, for example "6.0535, 80.2210".', 'crc-real-estate' ); ?></p>
			<p><button type="button" class="button-link button-link-delete crc-location-box-clear"><?php esc_html_e( 'Clear the place', 'crc-real-estate' ); ?></button></p>

			<div class="crc-field-group crc-location-box-google">
				<p class="crc-field">
					<label for="crc-google-maps"><?php esc_html_e( 'Google Maps link', 'crc-real-estate' ); ?></label>
					<input type="url" id="crc-google-maps" name="crc_google_maps" value="<?php echo esc_attr( get_post_meta( $post->ID, Location::GOOGLE_META, true ) ); ?>" class="regular-text" placeholder="https://maps.app.goo.gl/…" autocomplete="off">
				</p>
				<p class="description">
					<?php esc_html_e( 'Optional. Kept here only, never shown on the site. In Google Maps, open the place, choose Share, then Copy link.', 'crc-real-estate' ); ?>
					<a href="https://support.google.com/maps/answer/144361" target="_blank" rel="noopener"><?php esc_html_e( 'How to share a place', 'crc-real-estate' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves the place, whether the map shows, and the Google Maps link.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_location_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_coordinate() and esc_url_raw().
		$lat  = isset( $_POST['crc_location_lat'] ) ? Location::sanitize_coordinate( wp_unslash( $_POST['crc_location_lat'] ), 90 ) : '';
		$lng  = isset( $_POST['crc_location_lng'] ) ? Location::sanitize_coordinate( wp_unslash( $_POST['crc_location_lng'] ), 180 ) : '';
		$link = isset( $_POST['crc_google_maps'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['crc_google_maps'] ) ), array( 'http', 'https' ) ) : '';
		// phpcs:enable

		// A place needs both numbers.
		if ( '' !== $lat && '' !== $lng ) {
			update_post_meta( $post_id, Location::LAT_META, $lat );
			update_post_meta( $post_id, Location::LNG_META, $lng );
		} else {
			delete_post_meta( $post_id, Location::LAT_META );
			delete_post_meta( $post_id, Location::LNG_META );
		}

		if ( empty( $_POST['crc_location_show'] ) ) {
			update_post_meta( $post_id, Location::HIDDEN_META, '1' );
		} else {
			delete_post_meta( $post_id, Location::HIDDEN_META );
		}

		if ( '' !== $link ) {
			update_post_meta( $post_id, Location::GOOGLE_META, wp_slash( $link ) );
		} else {
			delete_post_meta( $post_id, Location::GOOGLE_META );
		}
	}
}
