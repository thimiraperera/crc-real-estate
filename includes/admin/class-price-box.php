<?php
/**
 * Price box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Price_Card;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors set a listing's price, and the price per perch for land.
 */
final class Price_Box {

	const NONCE = 'crc_price_nonce';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
	}

	/**
	 * Adds the Price box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_price', __( 'Price', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Prints the Price box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$price     = Price_Card::price( $post->ID );
		$per_perch = Price_Card::price_per_perch( $post->ID );
		$lands     = get_term_by( 'slug', 'lands', Taxonomy::NAME );
		$currency  = Price_Card::currency();

		wp_nonce_field( 'crc_price_save', self::NONCE );
		?>
		<div class="crc-price-box crc-fields" data-lands-term="<?php echo esc_attr( $lands ? $lands->term_id : 0 ); ?>">
			<div class="crc-field-group">
				<p class="crc-field">
					<label for="crc-price-field"><?php esc_html_e( 'Price', 'crc-real-estate' ); ?></label>
					<span class="crc-money">
						<span class="crc-money-currency"><?php echo esc_html( $currency ); ?></span>
						<input type="text" inputmode="numeric" id="crc-price-field" name="crc_price" value="<?php echo esc_attr( '' !== $price ? number_format_i18n( (float) $price ) : '' ); ?>" placeholder="10,000,000">
					</span>
				</p>
				<p class="description"><?php esc_html_e( 'The full price of the listing. For properties for rent, enter the monthly rent; "/month" is added after it on the site.', 'crc-real-estate' ); ?></p>
			</div>

			<div class="crc-field-group crc-price-box-per-perch">
				<p class="crc-field">
					<label for="crc-per-perch-field"><?php esc_html_e( 'Price per perch', 'crc-real-estate' ); ?></label>
					<span class="crc-money">
						<span class="crc-money-currency"><?php echo esc_html( $currency ); ?></span>
						<input type="text" inputmode="numeric" id="crc-per-perch-field" name="crc_price_per_perch" value="<?php echo esc_attr( '' !== $per_perch ? number_format_i18n( (float) $per_perch ) : '' ); ?>" placeholder="3,125">
					</span>
				</p>
				<p class="description"><?php esc_html_e( 'Shown under the price on land listings, for example "Rs. 3,125 per perch". Leave it empty to hide it.', 'crc-real-estate' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves the prices.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_price_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'crc_price'           => Price_Card::PRICE_META,
			'crc_price_per_perch' => Price_Card::PER_PERCH_META,
		);

		foreach ( $fields as $field => $key ) {
			$value = isset( $_POST[ $field ] ) ? Price_Card::sanitize_amount( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : '';

			if ( '' !== $value ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}
}
