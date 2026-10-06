<?php
/**
 * Search tab of the Widgets page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Listing_Archive;
use CRC\RealEstate\Listing_Filters;
use CRC\RealEstate\Listing_Query;
use CRC\RealEstate\Listing_Results;
use CRC\RealEstate\Listing_Search;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Widgets → Search: the search's shortcodes, where they go, how
 * many listings show on a page, and where the listings show. Saved on its
 * own, so saving another tab never touches it.
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
				'default'           => array(
					'page'     => 0,
					'per_page' => Listing_Archive::PER_PAGE,
				),
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
	 * Where the listings show now, in words.
	 */
	private function status() {
		$id = Listing_Archive::page_id();

		if ( $id ) {
			printf(
				'<p class="crc-search-status">%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: page title. */
						__( 'The listings show on the page "%s", and the listing archives show that page.', 'crc-real-estate' ),
						get_the_title( get_post( $id ) )
					)
				),
				esc_url( get_permalink( $id ) ),
				esc_html__( 'View it', 'crc-real-estate' )
			);
			return;
		}

		printf(
			'<p class="crc-search-status">%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
			esc_html__( 'The listings show on the listing archives, designed with your archive templates.', 'crc-real-estate' ),
			esc_url( Listing_Archive::archive_url() ),
			esc_html__( 'View All Listings', 'crc-real-estate' )
		);
	}

	/**
	 * Prints the tab.
	 */
	public function render() {
		$settings = Listing_Archive::settings();
		$archives = array(
			__( 'All Listings Archive', 'crc-real-estate' )            => '/listing/',
			__( 'All Listing Categories Archive', 'crc-real-estate' ) => '/listings/lands/',
			__( 'All Districts Archive', 'crc-real-estate' )          => '/district/galle/',
			__( 'All Towns Archive', 'crc-real-estate' )              => '/town/hikkaduwa/',
		);
		?>
		<section class="crc-info-card crc-search-tab" id="<?php echo esc_attr( Listing_Search::SHORTCODE ); ?>">
			<h2 class="crc-info-title"><?php esc_html_e( 'Search', 'crc-real-estate' ); ?></h2>
			<p><?php esc_html_e( 'The search has two parts. The search box goes on any page, such as the home page. The filters and the results go in the archive templates you design for the listing archives, which the search box leads to. Paste each shortcode into an Elementor Shortcode widget.', 'crc-real-estate' ); ?></p>

			<h3><?php esc_html_e( 'Search box, for any page', 'crc-real-estate' ); ?></h3>
			<?php $this->shortcode( '[' . Listing_Search::SHORTCODE . ']' ); ?>
			<p class="description"><?php esc_html_e( 'A tab for each category, a place box that suggests towns and districts with listings as people type, and a Search button, with the chosen category\'s own choices under it: land size, the highest price per perch and the property type for land; bedrooms, the highest price or rent and the property type for homes.', 'crc-real-estate' ); ?></p>

			<h3><?php esc_html_e( 'For your archive templates', 'crc-real-estate' ); ?></h3>
			<p><?php esc_html_e( 'Make an archive template with your theme builder and show it on these archives. One template can show on all four, or each can have its own. On each archive the results show that archive\'s listings: every listing, one category\'s, one district\'s or one town\'s, following the search in the address.', 'crc-real-estate' ); ?></p>
			<ul class="crc-search-archives">
				<?php foreach ( $archives as $name => $example ) : ?>
					<li><strong><?php echo esc_html( $name ); ?></strong> <span class="description"><?php echo esc_html( $example ); ?></span></li>
				<?php endforeach; ?>
			</ul>
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
						<th scope="row"><label for="crc-search-per-page"><?php esc_html_e( 'Listings a page', 'crc-real-estate' ); ?></label></th>
						<td>
							<input type="number" id="crc-search-per-page" name="<?php echo esc_attr( Listing_Archive::OPTION ); ?>[per_page]" value="<?php echo esc_attr( (string) $settings['per_page'] ); ?>" min="1" max="<?php echo esc_attr( (string) Listing_Query::MAX_PER_PAGE ); ?>" step="1" class="small-text">
							<p class="description"><?php esc_html_e( 'How many listings the results show before the numbered pages, from 1 to 48. 12 fills four rows of three.', 'crc-real-estate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crc-search-page"><?php esc_html_e( 'Where the listings show', 'crc-real-estate' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => Listing_Archive::OPTION . '[page]',
									'id'                => 'crc-search-page',
									'selected'          => (int) $settings['page'],
									'show_option_none'  => __( 'The listing archives (recommended)', 'crc-real-estate' ),
									'option_none_value' => '0',
								)
							);
							$this->status();
							?>
							<p class="description"><?php esc_html_e( 'Keep the listing archives when you design them with archive templates, as above. Choose a page only if your listings are on a normal page made with the filters and results; then the listing archives show that page instead, each with its category, district or town chosen.', 'crc-real-estate' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</section>
		<?php
	}
}
