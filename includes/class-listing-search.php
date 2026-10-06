<?php
/**
 * Search box.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_listing_search]: a tab for each category, a place box that suggests
 * towns and districts with listings as you type, a Search button, and under
 * it the category's own filters as rounded dropdowns. Search opens the
 * category's page (such as /listings/lands/), which shows the listings page
 * with the filters chosen. Also loads the search's script and answers the
 * place box's suggestions for every search part.
 */
final class Listing_Search {

	const SHORTCODE = 'crc_listing_search';
	const AJAX      = 'crc_re_places';

	/**
	 * Search boxes on the page so far, for their ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 6 );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_places' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX, array( $this, 'ajax_places' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Search box', 'crc-real-estate' ),
				'description' => __( 'A search for any page: a tab for each listing category, named as the categories are, a place box that suggests towns and districts with listings as people type, and a Search button. Under it are the chosen category\'s own choices as rounded dropdowns: land size, the highest price per perch and the property type for land; bedrooms, the highest price or rent and the property type for homes. Search opens the category\'s page, such as /listings/lands/, which shows your listings page with those choices made.', 'crc-real-estate' ),
				'attributes'  => array(
					'categories'  => array(
						'default'     => 'lands,properties-for-rent,properties-for-sale',
						'description' => __( 'Which categories have tabs, in order, separated by commas. The first one is chosen to start with.', 'crc-real-estate' ),
					),
					'placeholder' => array(
						'default'     => __( 'Town, district or Colombo zone', 'crc-real-estate' ),
						'description' => __( 'The grey hint in the place box.', 'crc-real-estate' ),
					),
					'button'      => array(
						'default'     => __( 'Search', 'crc-real-estate' ),
						'description' => __( 'The text on the button.', 'crc-real-estate' ),
					),
					'filters'     => array(
						'default'     => 'yes',
						'description' => __( 'no to leave out the rounded dropdowns under the box.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' categories="lands"]',
					'[' . self::SHORTCODE . ' filters="no"]',
				),
			)
		);
	}

	/**
	 * Registers the search's script, shared by the search box, the filters
	 * and the results.
	 */
	public static function register_assets() {
		if ( wp_script_is( 'crc-re-search', 'registered' ) ) {
			return;
		}

		wp_register_script( 'crc-re-search', CRC_RE_URL . 'assets/js/search.js', array(), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-search',
			'crcReSearch',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX,
				'pin'     => Icons::svg( 'pin', 'crc-place-option-icon' ),
			)
		);
	}

	/**
	 * Loads the search's script, also in Elementor's editor.
	 */
	public static function enqueue() {
		self::register_assets();
		wp_enqueue_script( 'crc-re-search' );
	}

	/**
	 * Asks page speed plugins not to hold the search's script back.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( 'crc-re-search' !== $handle || false !== strpos( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
	}

	/**
	 * Category terms from a list of slugs, in order, skipping unknown ones.
	 *
	 * @param string $list Slugs separated by commas.
	 * @return \WP_Term[]
	 */
	public static function categories( $list ) {
		$terms = array();

		foreach ( array_unique( array_filter( array_map( 'sanitize_title', explode( ',', (string) $list ) ) ) ) as $slug ) {
			$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

			if ( $term && ! is_wp_error( $term ) ) {
				$terms[] = $term;
			}
		}

		return $terms;
	}

	/**
	 * The property types a category's published listings have, most used first.
	 *
	 * @param string $category Category slug, or empty for every listing.
	 * @return string[]
	 */
	public static function property_types( $category ) {
		$types = array_values( Listing_Index::facets( $category )['types'] );

		usort(
			$types,
			function ( $a, $b ) {
				return $a['count'] === $b['count'] ? strnatcasecmp( $a['name'], $b['name'] ) : $b['count'] - $a['count'];
			}
		);

		return array_map(
			function ( $type ) {
				return (string) $type['name'];
			},
			$types
		);
	}

	/**
	 * Option tags.
	 *
	 * @param array  $options  Value => label.
	 * @param string $selected Selected value.
	 * @return string
	 */
	public static function options( array $options, $selected ) {
		$html = '';

		foreach ( $options as $value => $label ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $value ), (string) $value === (string) $selected ? ' selected' : '', esc_html( $label ) );
		}

		return $html;
	}

	/**
	 * Money choices: amount => "Rs. 5,000,000", or with a format such as "Up to %s".
	 *
	 * @param int[]  $amounts Amounts in rupees.
	 * @param string $format  How each is written; %s is the amount.
	 * @return string[]
	 */
	public static function money_options( array $amounts, $format = '%s' ) {
		$options = array();

		foreach ( $amounts as $amount ) {
			$options[ (string) $amount ] = sprintf( $format, Price_Card::money( (string) $amount ) );
		}

		return $options;
	}

	/**
	 * One rounded dropdown: the chosen choice's text with an arrow, over an
	 * invisible dropdown that opens the browser's own list.
	 *
	 * @param string $name     Field name.
	 * @param string $label    What it is, shown until something is chosen.
	 * @param array  $options  Value => label.
	 * @param string $selected Selected value.
	 * @return string
	 */
	private static function pill( $name, $label, array $options, $selected ) {
		$selected = isset( $options[ (string) $selected ] ) ? (string) $selected : '';

		return sprintf(
			'<div class="crc-pill%1$s"><span class="crc-pill-text" aria-hidden="true">%2$s</span>%3$s<select class="crc-pill-select" name="%4$s" aria-label="%5$s"><option value="">%5$s</option>%6$s</select></div>',
			'' !== $selected ? ' has-value' : '',
			esc_html( '' !== $selected ? $options[ $selected ] : $label ),
			Icons::svg( 'chevron-down', 'crc-pill-icon' ),
			esc_attr( $name ),
			esc_attr( $label ),
			self::options( $options, $selected )
		);
	}

	/**
	 * A category's rounded dropdowns: land size, the highest price per
	 * perch and the property type for land; bedrooms, the highest price or
	 * rent and the property type for homes.
	 *
	 * @param string $category Category slug.
	 * @param array  $filters  Filters chosen now.
	 * @return string
	 */
	public static function pills( $category, array $filters ) {
		$fields = Listing_Query::fields_for( $category );
		$html   = '';

		if ( in_array( 'size_min', $fields, true ) ) {
			$html .= self::pill( 'size', __( 'Land size (perches)', 'crc-real-estate' ), Listing_Query::size_ranges(), $filters['size_min'] . '-' . $filters['size_max'] );
		}

		if ( in_array( 'beds', $fields, true ) ) {
			$beds = array();

			foreach ( range( 1, 5 ) as $number ) {
				/* translators: %s: number of bedrooms. */
				$beds[ $number ] = sprintf( _n( '%s+ bedroom', '%s+ bedrooms', $number, 'crc-real-estate' ), number_format_i18n( $number ) );
			}

			$html .= self::pill( 'beds', __( 'Bedrooms', 'crc-real-estate' ), $beds, $filters['beds'] ? $filters['beds'] : '' );
		}

		if ( in_array( 'per_perch_max', $fields, true ) ) {
			/* translators: %s: amount, e.g. "Rs. 500,000". */
			$html .= self::pill( 'per_perch_max', __( 'Max price per perch', 'crc-real-estate' ), self::money_options( Listing_Query::per_perch_prices(), __( 'Up to %s', 'crc-real-estate' ) ), $filters['per_perch_max'] );
		} elseif ( in_array( 'price_max', $fields, true ) ) {
			$label = 'properties-for-rent' === $category ? __( 'Max rent per month', 'crc-real-estate' ) : __( 'Max price', 'crc-real-estate' );
			/* translators: %s: amount, e.g. "Rs. 5,000,000". */
			$html .= self::pill( 'price_max', $label, self::money_options( Listing_Query::prices( $category ), __( 'Up to %s', 'crc-real-estate' ) ), $filters['price_max'] );
		}

		if ( in_array( 'type', $fields, true ) ) {
			$types = self::property_types( $category );

			if ( $types ) {
				$html .= self::pill( 'type', __( 'Property type', 'crc-real-estate' ), array_combine( $types, $types ), $filters['type'] );
			}
		}

		return $html;
	}

	/**
	 * The place box with its pin and its list of suggestions.
	 *
	 * @param string $id          Box ID.
	 * @param string $value       Place typed now.
	 * @param string $placeholder Grey hint.
	 * @param string $list        Where the suggestions go: their ID.
	 * @return string
	 */
	public static function place_field( $id, $value, $placeholder, $list ) {
		return sprintf(
			'<input type="text" class="crc-search-input" id="%1$s" name="location" value="%2$s" placeholder="%3$s" autocomplete="off" autocapitalize="words" spellcheck="false" maxlength="60" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%4$s" data-crc-place>',
			esc_attr( $id ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			esc_attr( $list )
		);
	}

	/**
	 * Renders [crc_listing_search].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts  = Shortcodes::atts( self::SHORTCODE, $atts );
		$terms = self::categories( $atts['categories'] );

		if ( ! $terms ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'None of these categories exist. Use lands, properties-for-rent or properties-for-sale.', 'crc-real-estate' ) );
		}

		self::enqueue();

		$id      = 'crc-search-' . ( ++self::$count );
		$filters = Listing_Archive::is_listings_page() ? Listing_Archive::filters() : Listing_Query::blank();
		$chosen  = $terms[0]->slug;

		foreach ( $terms as $term ) {
			if ( $term->slug === $filters['category'] ) {
				$chosen = $term->slug;
			}
		}

		$place = '' !== $filters['location'] ? $filters['location'] : Listing_Archive::place_name( $filters );
		$tabs  = '';
		$pills = '';

		foreach ( $terms as $term ) {
			$on = $term->slug === $chosen;

			$tabs .= sprintf(
				'<label class="crc-search-tab"><input type="radio" class="crc-search-tab-input" name="category" value="%1$s" data-url="%2$s"%3$s><span class="crc-search-tab-text">%4$s</span></label>',
				esc_attr( $term->slug ),
				esc_url( Listing_Archive::category_url( $term->slug ) ),
				$on ? ' checked' : '',
				esc_html( $term->name )
			);

			if ( Shortcodes::is_on( $atts['filters'] ) ) {
				$group = self::pills( $term->slug, $on ? $filters : Listing_Query::blank() );

				if ( '' !== $group ) {
					$pills .= sprintf( '<div class="crc-search-filters" data-for="%1$s"%2$s>%3$s</div>', esc_attr( $term->slug ), $on ? '' : ' hidden', $group );
				}
			}
		}

		return sprintf(
			'<form class="crc-search" id="%1$s" role="search" method="get" action="%2$s" aria-label="%3$s" data-crc-search><div class="crc-search-card"><div class="crc-search-tabs" role="radiogroup" aria-label="%4$s">%5$s</div><div class="crc-search-row"><div class="crc-search-place">%6$s<label class="crc-sr" for="%1$s-location">%7$s</label>%8$s</div><button type="submit" class="crc-search-submit"><span class="crc-search-submit-text">%9$s</span>%10$s</button><ul class="crc-places" id="%1$s-places" role="listbox" aria-label="%11$s" hidden></ul></div></div>%12$s</form>',
			esc_attr( $id ),
			esc_url( Listing_Archive::page_url() ),
			esc_attr__( 'Search listings', 'crc-real-estate' ),
			esc_attr__( 'What you are looking for', 'crc-real-estate' ),
			$tabs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			Icons::svg( 'pin', 'crc-search-place-icon' ),
			esc_html__( 'Town, district or Colombo zone', 'crc-real-estate' ),
			self::place_field( $id . '-location', $place, $atts['placeholder'], $id . '-places' ),
			esc_html( $atts['button'] ),
			Icons::svg( 'search', 'crc-search-submit-icon' ),
			esc_attr__( 'Places', 'crc-real-estate' ),
			$pills // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in pills().
		);
	}

	/**
	 * Places that match what is typed and have published listings (in the
	 * chosen category, if any): districts and towns, best first, at most 8.
	 *
	 * @param string $query    What is typed.
	 * @param string $category Category slug, or empty.
	 * @return array[] Each with a 'name' and a 'note', e.g. "Galle District".
	 */
	public static function places( $query, $category = '' ) {
		$key = District::normalize( $query );

		if ( '' === $key ) {
			return array();
		}

		$facets = Listing_Index::facets( isset( Taxonomy::terms()[ $category ] ) ? $category : '' );
		$all    = District::districts();
		$found  = array();

		foreach ( District::search( $query ) as $slug ) {
			if ( empty( $facets['districts'][ $slug ] ) ) {
				continue;
			}

			$rank    = Town::rank( $all[ $slug ]['name'], $key );
			$found[] = array(
				'name' => $all[ $slug ]['name'],
				'note' => __( 'District', 'crc-real-estate' ),
				'rank' => null === $rank ? 3 : $rank,
				'kind' => 0,
			);
		}

		foreach ( Town::search( $query, 20, true ) as $term ) {
			if ( empty( $facets['towns'][ (int) $term->term_id ] ) ) {
				continue;
			}

			$zone     = Town::zone( $term->name );
			$district = Town::district_of( $term );
			$rank     = Town::rank( $term->name, $key );

			if ( $zone ) {
				$areas = Town::zones()[ $zone ];
				$note  = implode( ', ', (array) $areas );

				foreach ( Town::zone_names( $zone ) as $area ) {
					$also = Town::rank( $area, $key );
					$rank = ( null !== $also && ( null === $rank || $also < $rank ) ) ? $also : $rank;
				}
			} elseif ( $district ) {
				/* translators: %s: district, e.g. "Galle". */
				$note = sprintf( __( '%s District', 'crc-real-estate' ), $district['name'] );
			} else {
				$note = __( 'Town', 'crc-real-estate' );
			}

			$found[] = array(
				'name' => (string) $term->name,
				'note' => $note,
				'rank' => null === $rank ? 3 : $rank,
				'kind' => 1,
			);
		}

		usort(
			$found,
			function ( $a, $b ) {
				if ( $a['rank'] !== $b['rank'] ) {
					return $a['rank'] - $b['rank'];
				}

				return $a['kind'] === $b['kind'] ? strnatcasecmp( $a['name'], $b['name'] ) : $a['kind'] - $b['kind'];
			}
		);

		$places = array();
		$seen   = array();

		foreach ( $found as $place ) {
			$lower = strtolower( $place['name'] );

			if ( ! isset( $seen[ $lower ] ) && count( $places ) < 8 ) {
				$seen[ $lower ] = true;
				$places[]       = array(
					'name' => $place['name'],
					'note' => $place['note'],
				);
			}
		}

		return $places;
	}

	/**
	 * Answers the place box: the places matching what is typed.
	 */
	public function ajax_places() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public, read only, works on cached pages.
		$query    = isset( $_GET['q'] ) && is_scalar( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$category = isset( $_GET['category'] ) && is_scalar( $_GET['category'] ) ? sanitize_title( wp_unslash( $_GET['category'] ) ) : '';
		// phpcs:enable

		wp_send_json_success( array( 'places' => self::places( substr( $query, 0, 60 ), $category ) ) );
	}
}
