<?php
/**
 * Search tab of the Widgets page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Listing_Archive;
use CRC\RealEstate\Listing_Filters;
use CRC\RealEstate\Listing_Results;
use CRC\RealEstate\Listing_Search;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Widgets → Search: the search's shortcodes, and which page is
 * the listings page. Saved on its own, so saving another tab never touches it.
 */
final class Search_Tab {

	const GROUP = 'crc_re_widgets_search';

	/**
	 * Registers the setting.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			Listing_Archive::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Listing_Archive::class, 'sanitize' ),
				'default'           => array( 'page' => 0 ),
			)
		);
	}

	/**
	 * Prints a shortcode with its Copy button.
	 *
	 * @param string $tag Shortcode, with its brackets.
	 */
	private function shortcode( $tag ) {
		?>
		<p class="crc-info-code">
			<code><?php echo esc_html( $tag ); ?></code>
			<button type="button" class="button button-small crc-copy" data-copy="<?php echo esc_attr( $tag ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'crc-real-estate' ); ?>"><?php esc_html_e( 'Copy', 'crc-real-estate' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Which page is the listings page now, in words.
	 */
	private function status() {
		$id = Listing_Archive::page_id();

		if ( $id ) {
			printf(
				'<p class="crc-search-status">%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: page title. */
						__( 'Your listings page now is "%s".', 'crc-real-estate' ),
						get_the_title( get_post( $id ) )
					)
				),
				esc_url( get_permalink( $id ) ),
				esc_html__( 'View it', 'crc-real-estate' )
			);
			return;
		}

		printf(
			'<p class="crc-search-status crc-warning">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: page address, e.g. "/listing/". */
					__( 'There is no listings page yet. Publish a page at %s with the filters and results on it, or choose a page below.', 'crc-real-estate' ),
					'/' . Listing_Archive::path() . '/'
				)
			)
		);
	}

	/**
	 * Prints the tab.
	 */
	public function render() {
		$settings = Listing_Archive::settings();
		?>
		<section class="crc-info-card crc-search-tab" id="<?php echo esc_attr( Listing_Search::SHORTCODE ); ?>">
			<h2 class="crc-info-title"><?php esc_html_e( 'Search', 'crc-real-estate' ); ?></h2>
			<p><?php esc_html_e( 'The search has two parts. The search box goes on any page, such as the home page. The filters and the results go on your listings page, which the search box leads to. Paste each shortcode into an Elementor Shortcode widget.', 'crc-real-estate' ); ?></p>

			<h3><?php esc_html_e( 'Search box, for any page', 'crc-real-estate' ); ?></h3>
			<?php $this->shortcode( '[' . Listing_Search::SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'A tab for each category, a place box that suggests towns and districts with listings as people type, and a Search button, with the chosen category\'s own choices under it: land size, the highest price per perch and the property type for land; bedrooms, the highest price or rent and the property type for homes.', 'crc-real-estate' ); ?></p>

			<h3><?php esc_html_e( 'For your listings page', 'crc-real-estate' ); ?></h3>
			<?php $this->shortcode( '[' . Listing_Filters::SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'The filters, for a narrow column beside the results or above them. On phones they fold away behind a Filters bar.', 'crc-real-estate' ); ?></p>
			<?php $this->shortcode( '[' . Listing_Results::SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'The matching listings in the listing carousel\'s cards, with how many there are, a Sort by choice and numbered pages. Add columns="2" when the filters sit beside it.', 'crc-real-estate' ); ?></p>
			<?php $this->shortcode( '[' . Listing_Results::TITLE_SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'Optional: a heading that says what is being looked at, such as "Land to buy in Galle".', 'crc-real-estate' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="crc-search-page"><?php esc_html_e( 'Listings page', 'crc-real-estate' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => Listing_Archive::OPTION . '[page]',
									'id'                => 'crc-search-page',
									'selected'          => (int) $settings['page'],
									'show_option_none'  => sprintf(
										/* translators: %s: page address, e.g. "/listing/". */
										__( 'The page at %s', 'crc-real-estate' ),
										'/' . Listing_Archive::path() . '/'
									),
									'option_none_value' => '0',
								)
							);
							$this->status();
							?>
							<p class="description"><?php esc_html_e( 'The page with the filters and the results on it, which the search box leads to. Leave it as the page at /listing/ when your page has that address. Category links, such as /listings/lands/, show this page with that category chosen, like a normal WordPress archive, and so do district links (/district/galle/) and town links (/town/hikkaduwa/). Each keeps its own address and title for search engines.', 'crc-real-estate' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</section>
		<?php
	}
}
