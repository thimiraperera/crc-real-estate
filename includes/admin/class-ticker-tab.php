<?php
/**
 * Keyword ticker tab of the Widgets page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Widgets → Keyword ticker: the keywords (with import and export
 * as JSON), the speed and the direction of [crc_ticker]. Saved on their own,
 * so saving another tab or the Settings page never touches them.
 */
final class Ticker_Tab {

	const PAGE  = 'crc-real-estate-widgets-ticker';
	const GROUP = 'crc_re_widgets_ticker';

	/**
	 * Registers the ticker's settings and their fields, saved on their own.
	 */
	public function register() {
		register_setting(
			self::GROUP,
			Settings::TICKER_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize_ticker' ),
				'default'           => Settings::ticker_defaults(),
			)
		);

		add_settings_section( 'crc_re_ticker', __( 'Keyword ticker', 'crc-real-estate' ), array( $this, 'guide' ), self::PAGE );

		$fields = array(
			'keywords'  => __( 'Keywords', 'crc-real-estate' ),
			'speed'     => __( 'Speed', 'crc-real-estate' ),
			'direction' => __( 'Direction', 'crc-real-estate' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'crc_re_ticker_' . $key,
				$label,
				array( $this, 'field' ),
				self::PAGE,
				'crc_re_ticker',
				array(
					'label_for' => 'crc-ticker-' . $key,
					'key'       => $key,
				)
			);
		}
	}

	/**
	 * The short guide at the top of the Keyword ticker settings.
	 */
	public function guide() {
		echo '<p>' . esc_html__( 'A row of keywords, such as Houses · Villas · Apartments, that moves across the page on its own, over the full width of the window. It slows down and stops while the mouse is over it, and people can drag it and throw it. To show it, put the shortcode [crc_ticker] in a Shortcode widget in Elementor, or anywhere WordPress accepts shortcodes.', 'crc-real-estate' ) . '</p>';
		echo '<p>' . esc_html__( 'Saving clears the LiteSpeed page cache, so visitors see the changes straight away. If the site also uses a Cloudflare page cache, clear that too.', 'crc-real-estate' ) . '</p>';
	}

	/**
	 * Prints a Keyword ticker setting.
	 *
	 * @param array $args Field details.
	 */
	public function field( $args ) {
		$name = Settings::TICKER_OPTION;

		switch ( $args['key'] ) {
			case 'keywords':
				$keywords = Settings::sanitize_keywords( Settings::ticker( 'keywords' ) );

				printf(
					'<textarea id="crc-ticker-keywords" name="%1$s[keywords]" rows="10" class="large-text crc-ticker-keywords">%2$s</textarea>' .
					'<p class="crc-ticker-tools">' .
					'<button type="button" class="button crc-ticker-import">%3$s</button> ' .
					'<input type="file" class="crc-ticker-file" accept=".json,application/json" hidden> ' .
					'<button type="button" class="button crc-ticker-export">%4$s</button> ' .
					'<a class="crc-ticker-sample" href="%5$s" download="ticker-keywords.json">%6$s</a>' .
					'</p>' .
					'<p class="crc-ticker-note" role="status" aria-live="polite"></p>' .
					'<p class="description">%7$s</p>',
					esc_attr( $name ),
					esc_textarea( implode( "\n", $keywords ) ),
					esc_html__( 'Import from a JSON file', 'crc-real-estate' ),
					esc_html__( 'Export as a JSON file', 'crc-real-estate' ),
					esc_url( CRC_RE_URL . 'assets/samples/ticker-keywords.json' ),
					esc_html__( 'Download the sample file (40 keywords)', 'crc-real-estate' ),
					/* translators: %s: most keywords. */
					esc_html( sprintf( __( 'One keyword on each line, in the order they should show; up to %s. Import from a JSON file puts the keywords from a file in this box, in place of what is in it; then press Save Changes to keep them. Export as a JSON file downloads the keywords in the box as a file you can keep, edit, and import here or on another site. The sample file shows how a keywords file looks.', 'crc-real-estate' ), number_format_i18n( Settings::TICKER_MAX ) ) )
				);
				break;

			case 'speed':
				printf(
					'<input type="number" id="crc-ticker-speed" name="%1$s[speed]" value="%2$s" min="5" max="400" step="1" class="small-text"> %3$s<p class="description">%4$s</p>',
					esc_attr( $name ),
					esc_attr( (string) Settings::sanitize_speed( Settings::ticker( 'speed' ) ) ),
					esc_html__( 'pixels a second', 'crc-real-estate' ),
					esc_html__( 'How fast the keywords move, from 5 to 400. About 30 is calm, 50 is the usual, and 100 is quick. A page can have its own speed by adding speed="…" to the shortcode, for example [crc_ticker speed="80"].', 'crc-real-estate' )
				);
				break;

			case 'direction':
				$direction = 'right' === Settings::ticker( 'direction' ) ? 'right' : 'left';

				printf(
					'<select id="crc-ticker-direction" name="%1$s[direction]"><option value="left"%2$s>%3$s</option><option value="right"%4$s>%5$s</option></select><p class="description">%6$s</p>',
					esc_attr( $name ),
					selected( $direction, 'left', false ),
					esc_html__( 'Right to left', 'crc-real-estate' ),
					selected( $direction, 'right', false ),
					esc_html__( 'Left to right', 'crc-real-estate' ),
					esc_html__( 'Which way the keywords move. Right to left is the usual way.', 'crc-real-estate' )
				);
				break;
		}
	}


	/**
	 * Loads the import and export script with its words.
	 */
	public function enqueue() {
		wp_enqueue_script( 'crc-re-admin-settings', CRC_RE_URL . 'assets/js/admin-settings.js', array(), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-settings',
			'crcSettings',
			array(
				'file'     => 'ticker-keywords.json',
				'max'      => Settings::TICKER_MAX,
				'chars'    => Settings::KEYWORD_MAX,
				'size'     => 1048576,
				'tooBig'   => __( 'This file is too big for a list of keywords. Please choose a keywords file like the sample file.', 'crc-real-estate' ),
				/* translators: 1: keywords in the file, 2: most keywords. */
				'cut'      => __( 'The file has %1$s keywords, but the ticker can show up to %2$s, so the first %2$s were put in the box. Press Save Changes to keep them.', 'crc-real-estate' ),
				'notJson'  => __( 'This file isn\'t a JSON file that can be read. Please choose a keywords file like the sample file.', 'crc-real-estate' ),
				'noWords'  => __( 'This file has no keywords in it. A keywords file looks like the sample file: {"keywords": ["Houses", "Villas"]}.', 'crc-real-estate' ),
				/* translators: %s: number of keywords. */
				'replace'  => __( 'Replace the keywords in the box with the %s keywords from the file?', 'crc-real-estate' ),
				/* translators: %s: number of keywords. */
				'imported' => __( '%s keywords were put in the box. Press Save Changes to keep them.', 'crc-real-estate' ),
				'empty'    => __( 'There are no keywords in the box to export yet.', 'crc-real-estate' ),
				/* translators: %s: number of keywords. */
				'exported' => __( '%s keywords were exported to the file ticker-keywords.json in your downloads.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints the tab's form.
	 */
	public function render() {
		?>
		<section class="crc-info-card crc-ticker-tab" id="crc_ticker">
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</section>
		<?php
	}
}
