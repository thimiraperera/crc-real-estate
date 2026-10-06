<?php
/**
 * Search filters.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_listing_filters]: the filters for the listings page, beside or above
 * the results. What people are looking for (a category, or every listing),
 * the place with the same suggestions as the search box, and the chosen
 * category's own choices: price, price per perch and land size for land;
 * price or rent, bedrooms, bathrooms and furnishing for homes; and the
 * property type. Show listings opens the category's page with the choices
 * made. On phones the filters fold away behind a Filters bar.
 *
 * The fields and their labels take the site's form styles from Elementor;
 * the filters only lay them out.
 */
final class Listing_Filters {

	const SHORTCODE = 'crc_listing_filters';

	/**
	 * Filters on the page so far, for their ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Search filters', 'crc-real-estate' ),
				'description' => __( 'The filters for your listings page, beside or above the results: what people are looking for (a category, or every listing), the place, with the same suggestions as the search box, and the chosen category\'s own choices. Land has the price, the price per perch and the land size; homes have the price or rent, bedrooms, bathrooms and furnishing; every category has the property type. Show listings opens the category\'s page with the choices made, and Clear all takes them off. On phones the filters fold away behind a Filters bar. The fields and labels take your form styles from Elementor\'s Site Settings.', 'crc-real-estate' ),
				'attributes'  => array(
					'categories' => array(
						'default'     => 'lands,properties-for-rent,properties-for-sale',
						'description' => __( 'Which categories people can choose, in order, separated by commas.', 'crc-real-estate' ),
					),
					'title'      => array(
						'default'     => __( 'Filters', 'crc-real-estate' ),
						'description' => __( 'The heading at the top.', 'crc-real-estate' ),
					),
					'button'     => array(
						'default'     => __( 'Show listings', 'crc-real-estate' ),
						'description' => __( 'The text on the button.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' title="Refine your search"]',
				),
			)
		);
	}

	/**
	 * A labelled dropdown.
	 *
	 * @param string $id       Field ID.
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $any      First choice, for no choice.
	 * @param array  $options  Value => label.
	 * @param string $selected Selected value.
	 * @param bool   $hidden   Whether the label is for screen readers only.
	 * @return string
	 */
	private static function select( $id, $name, $label, $any, array $options, $selected, $hidden = false ) {
		return sprintf(
			'<label class="%1$s" for="%2$s">%3$s</label><select class="crc-filter-select" id="%2$s" name="%4$s"><option value="">%5$s</option>%6$s</select>',
			$hidden ? 'crc-sr' : 'crc-filter-label',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $name ),
			esc_html( $any ),
			Listing_Search::options( $options, $selected )
		);
	}

	/**
	 * A group of fields for some categories, off while another is chosen. A
	 * pair of fields (lowest and highest) has a heading that is a label, so
	 * it looks like the other fields' labels.
	 *
	 * @param string $for     Category slugs separated by spaces, or "all".
	 * @param bool   $on      Whether it is for the category chosen now.
	 * @param string $content Fields.
	 * @param string $heading Heading for a pair of fields, or empty.
	 * @param string $id      For a pair: the start of its fields' IDs (…-min and …-max).
	 * @return string
	 */
	private static function group( $for, $on, $content, $heading = '', $id = '' ) {
		$pair = '' !== $heading;

		return sprintf(
			'<fieldset class="crc-filter%1$s" data-for="%2$s"%3$s%4$s>%5$s%6$s</fieldset>',
			$pair ? ' crc-filter-range' : '',
			esc_attr( $for ),
			$pair ? ' aria-labelledby="' . esc_attr( $id . '-label' ) . '"' : '',
			$on ? '' : ' hidden disabled',
			$pair ? '<label class="crc-filter-label" id="' . esc_attr( $id . '-label' ) . '" for="' . esc_attr( $id . '-min' ) . '">' . esc_html( $heading ) . '</label><div class="crc-filter-pair">' : '',
			$content . ( $pair ? '</div>' : '' )
		);
	}

	/**
	 * The fields for one category, or for every listing.
	 *
	 * @param string $id       Filters ID.
	 * @param string $category Category slug, or empty for every listing.
	 * @param array  $filters  Filters chosen now, or blank ones for another category.
	 * @param bool   $on       Whether the category is chosen now.
	 * @return string
	 */
	private static function fields( $id, $category, array $filters, $on ) {
		$fields = Listing_Query::fields_for( $category );
		$slug   = '' !== $category ? $category : 'all';
		$prefix = $id . '-' . $slug;
		$html   = '';

		if ( in_array( 'price_min', $fields, true ) ) {
			$prices = Listing_Search::money_options( Listing_Query::prices( $category ) );
			$html  .= self::group(
				$slug,
				$on,
				self::select( $prefix . '-price-min', 'price_min', __( 'Lowest', 'crc-real-estate' ), __( 'No min', 'crc-real-estate' ), $prices, $filters['price_min'], true )
				. self::select( $prefix . '-price-max', 'price_max', __( 'Highest', 'crc-real-estate' ), __( 'No max', 'crc-real-estate' ), $prices, $filters['price_max'], true ),
				'properties-for-rent' === $category ? __( 'Rent per month', 'crc-real-estate' ) : __( 'Price', 'crc-real-estate' ),
				$prefix . '-price'
			);
		}

		if ( in_array( 'per_perch_max', $fields, true ) ) {
			$html .= self::group(
				$slug,
				$on,
				self::select( $prefix . '-per-perch', 'per_perch_max', __( 'Max price per perch', 'crc-real-estate' ), __( 'Any', 'crc-real-estate' ), Listing_Search::money_options( Listing_Query::per_perch_prices() ), $filters['per_perch_max'] )
			);
		}

		if ( in_array( 'size_min', $fields, true ) ) {
			$sizes = array();

			foreach ( Listing_Query::sizes() as $size ) {
				$sizes[ (string) $size ] = Listing_Query::size_label( $size );
			}

			$html .= self::group(
				$slug,
				$on,
				self::select( $prefix . '-size-min', 'size_min', __( 'Smallest', 'crc-real-estate' ), __( 'No min', 'crc-real-estate' ), $sizes, $filters['size_min'], true )
				. self::select( $prefix . '-size-max', 'size_max', __( 'Largest', 'crc-real-estate' ), __( 'No max', 'crc-real-estate' ), $sizes, $filters['size_max'], true ),
				__( 'Land size', 'crc-real-estate' ),
				$prefix . '-size'
			);
		}

		foreach ( array(
			'beds'  => array( __( 'Bedrooms', 'crc-real-estate' ), /* translators: %s: number of bedrooms. */ _n_noop( '%s+ bedroom', '%s+ bedrooms', 'crc-real-estate' ) ),
			'baths' => array( __( 'Bathrooms', 'crc-real-estate' ), /* translators: %s: number of bathrooms. */ _n_noop( '%s+ bathroom', '%s+ bathrooms', 'crc-real-estate' ) ),
		) as $key => $field ) {
			if ( ! in_array( $key, $fields, true ) ) {
				continue;
			}

			$options = array();

			foreach ( range( 1, 5 ) as $number ) {
				$options[ $number ] = sprintf( translate_nooped_plural( $field[1], $number, 'crc-real-estate' ), number_format_i18n( $number ) );
			}

			$html .= self::group( $slug, $on, self::select( $prefix . '-' . $key, $key, $field[0], __( 'Any', 'crc-real-estate' ), $options, $filters[ $key ] ? $filters[ $key ] : '' ) );
		}

		if ( in_array( 'furnishing', $fields, true ) && Listing_Query::furnishings() ) {
			$html .= self::group( $slug, $on, self::select( $prefix . '-furnishing', 'furnishing', __( 'Furnishing', 'crc-real-estate' ), __( 'Any', 'crc-real-estate' ), Listing_Query::furnishings(), $filters['furnishing'] ) );
		}

		if ( in_array( 'type', $fields, true ) ) {
			$types = Listing_Search::property_types( $category );

			if ( '' !== $filters['type'] && ! in_array( $filters['type'], $types, true ) ) {
				$types[] = $filters['type'];
			}

			if ( $types ) {
				$html .= self::group( $slug, $on, self::select( $prefix . '-type', 'type', __( 'Property type', 'crc-real-estate' ), __( 'Any', 'crc-real-estate' ), array_combine( $types, $types ), $filters['type'] ) );
			}
		}

		return $html;
	}

	/**
	 * How many filters are chosen, for the Filters bar on phones.
	 *
	 * @param array $filters Filters.
	 * @return int
	 */
	public static function chosen( array $filters ) {
		return count( Listing_Query::params( $filters, array( 'sort', 'page' ) ) );
	}

	/**
	 * Renders [crc_listing_filters].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts  = Shortcodes::atts( self::SHORTCODE, $atts );
		$terms = Listing_Search::categories( $atts['categories'] );

		Listing_Search::enqueue();

		$id      = 'crc-filters-' . ( ++self::$count );
		$filters = Listing_Archive::filters();
		$choices =sprintf( '<option value="" data-url="%1$s"%2$s>%3$s</option>', esc_url( Listing_Archive::page_url() ), '' === $filters['category'] ? ' selected' : '', esc_html__( 'All listings', 'crc-real-estate' ) );
		$fields  = self::fields( $id, '', '' === $filters['category'] ? $filters : Listing_Query::blank(), '' === $filters['category'] );

		foreach ( $terms as $term ) {
			$on       = $term->slug === $filters['category'];
			$choices .= sprintf( '<option value="%1$s" data-url="%2$s"%3$s>%4$s</option>', esc_attr( $term->slug ), esc_url( Listing_Archive::category_url( $term->slug ) ), $on ? ' selected' : '', esc_html( $term->name ) );
			$fields  .= self::fields( $id, $term->slug, $on ? $filters : Listing_Query::blank(), $on );
		}

		$count = self::chosen( $filters );
		$place = '' !== $filters['location'] ? $filters['location'] : Listing_Archive::place_name( $filters );

		return sprintf(
			'<form class="crc-filters" id="%1$s" method="get" action="%2$s" aria-label="%3$s" data-crc-filters><div class="crc-filters-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="%1$s-body" data-crc-filters-toggle>%4$s<span class="crc-filters-toggle-text">%5$s</span>%6$s%7$s</div><div class="crc-filters-body" id="%1$s-body"><div class="crc-filters-head"><h6 class="crc-filters-title">%5$s</h6>%8$s</div><div class="crc-filter"><label class="crc-filter-label" for="%1$s-category">%9$s</label><select class="crc-filter-select" id="%1$s-category" name="category">%10$s</select></div><div class="crc-filter"><label class="crc-filter-label" for="%1$s-location">%11$s</label><div class="crc-place">%12$s<ul class="crc-places" id="%1$s-places" role="listbox" aria-label="%13$s" hidden></ul></div></div>%14$s<button type="submit" class="crc-filters-submit">%15$s</button></div></form>',
			esc_attr( $id ),
			esc_url( Listing_Archive::base_url() ),
			esc_attr__( 'Filter listings', 'crc-real-estate' ),
			Icons::svg( 'filter', 'crc-filters-toggle-icon' ),
			esc_html( $atts['title'] ),
			$count ? '<span class="crc-filters-toggle-count">' . esc_html( number_format_i18n( $count ) ) . '</span>' : '',
			Icons::svg( 'chevron-down', 'crc-filters-toggle-arrow' ),
			$count ? '<a class="crc-filters-clear" href="' . esc_url( Listing_Archive::base_url() ) . '">' . esc_html__( 'Clear all', 'crc-real-estate' ) . '</a>' : '',
			esc_html__( 'Looking for', 'crc-real-estate' ),
			$choices, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html__( 'Location', 'crc-real-estate' ),
			Listing_Search::place_field( $id . '-location', $place, __( 'Town, district or Colombo zone', 'crc-real-estate' ), $id . '-places' ),
			esc_attr__( 'Places', 'crc-real-estate' ),
			$fields, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in fields().
			esc_html( $atts['button'] )
		);
	}
}
