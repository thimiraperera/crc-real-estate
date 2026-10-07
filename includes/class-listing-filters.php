<?php
/**
 * Search filters.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_listing_filters]: the filters for the listing archives, beside or
 * above the results, in the same white card as the price box on listing
 * pages, staying in view while the listings scroll. What people are looking
 * for (a category, or every listing), the place with the same suggestions as
 * the search box, and the chosen category's own choices:
 *
 * - land: the price (two boxes and a slider), the price per perch, the land
 *   size and the property type;
 * - homes: the price or rent per month (two boxes and a slider), bedrooms,
 *   bathrooms and furnishing (each a box with − and +) and the property type.
 *
 * Show Listings opens the category's archive with the choices made; Clear All
 * takes them off. On phones the filters fold away behind a Filters bar.
 *
 * The fields and their labels take the site's form styles from Elementor, and
 * both buttons the site's button styles; the filters only lay them out.
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
				'description' => __( 'The filters for your archive templates (All Listings Archive, All Listing Categories Archive, All Districts Archive and All Towns Archive), beside or above the results, in the same white card as the price box. Beside the results they stay in view while the listings scroll. People choose what they are looking for (a category, or every listing) and the place, with the same suggestions as the search box, then the category\'s own choices. Land has the price, with two boxes and a slider, the price per perch and the land size; homes have the price or rent per month, with two boxes and a slider, and bedrooms, bathrooms and furnishing, each a box with − and +; every category has the property type. Show Listings opens the category\'s archive with the choices made, and Clear All takes them off. On phones the filters fold away behind a Filters bar. The fields and labels take your form styles from Elementor\'s Site Settings, and the buttons your button styles.', 'crc-real-estate' ),
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
						'default'     => __( 'Show Listings', 'crc-real-estate' ),
						'description' => __( 'The text on the button that shows the listings.', 'crc-real-estate' ),
					),
					'clear'      => array(
						'default'     => __( 'Clear All', 'crc-real-estate' ),
						'description' => __( 'The text on the button that takes the choices off.', 'crc-real-estate' ),
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
	 * @param string $after   For a pair: what goes under the two fields, such as a slider.
	 * @return string
	 */
	private static function group( $for, $on, $content, $heading = '', $id = '', $after = '' ) {
		$pair = '' !== $heading;

		return sprintf(
			'<fieldset class="crc-filter%1$s" data-for="%2$s"%3$s%4$s>%5$s%6$s</fieldset>',
			$pair ? ' crc-filter-range' : '',
			esc_attr( $for ),
			$pair ? ' aria-labelledby="' . esc_attr( $id . '-label' ) . '"' : '',
			$on ? '' : ' hidden disabled',
			$pair ? '<label class="crc-filter-label" id="' . esc_attr( $id . '-label' ) . '" for="' . esc_attr( $id . '-min' ) . '">' . esc_html( $heading ) . '</label><div class="crc-filter-pair">' : '',
			$content . ( $pair ? '</div>' . $after : '' )
		);
	}

	/**
	 * The step of a slider an amount is at: the nearest step at or under it
	 * for the lowest, at or over it for the highest.
	 *
	 * @param int[] $steps  Amounts, smallest first.
	 * @param int   $amount Amount.
	 * @param bool  $up     Whether to round up.
	 * @return int Step number.
	 */
	public static function step_of( array $steps, $amount, $up ) {
		$last = count( $steps ) - 1;

		if ( $up ) {
			foreach ( $steps as $i => $step ) {
				if ( $step >= $amount ) {
					return (int) $i;
				}
			}

			return $last;
		}

		$at = 0;

		foreach ( $steps as $i => $step ) {
			if ( $step <= $amount ) {
				$at = (int) $i;
			}
		}

		return $at;
	}

	/**
	 * The price, or the rent per month: a box for the lowest and one for
	 * the highest, with a slider under them that moves with them.
	 *
	 * @param string $prefix  Start of the fields' IDs.
	 * @param string $slug    Category slug.
	 * @param bool   $on      Whether the category is chosen now.
	 * @param string $label   "Price" or "Rent per month".
	 * @param array  $filters Filters chosen now.
	 * @return string
	 */
	private static function price_range( $prefix, $slug, $on, $label, array $filters ) {
		$id    = $prefix . '-price';
		$steps = Listing_Query::price_steps( $slug );
		$last  = count( $steps ) - 1;
		$low   = '' !== $filters['price_min'] ? (int) $filters['price_min'] : 0;
		$high  = '' !== $filters['price_max'] ? (int) $filters['price_max'] : 0;
		$from  = $low ? self::step_of( $steps, $low, false ) : 0;
		$to    = $high ? self::step_of( $steps, $high, true ) : $last;
		$box   = '<label class="crc-sr" for="%1$s">%2$s</label><input type="text" class="crc-filter-input" id="%1$s" name="%3$s" value="%4$s" placeholder="%5$s" inputmode="numeric" autocomplete="off" data-crc-money>';

		$boxes = sprintf( $box, esc_attr( $id . '-min' ), esc_html__( 'Lowest', 'crc-real-estate' ), 'price_min', esc_attr( $low ? number_format_i18n( $low ) : '' ), esc_attr__( 'Min', 'crc-real-estate' ) )
			. sprintf( $box, esc_attr( $id . '-max' ), esc_html__( 'Highest', 'crc-real-estate' ), 'price_max', esc_attr( $high ? number_format_i18n( $high ) : '' ), esc_attr__( 'Max', 'crc-real-estate' ) );

		$slider = sprintf(
			'<div class="crc-range" data-crc-range data-steps="%1$s" style="--crc-range-from: %2$s%%; --crc-range-to: %3$s%%;"><div class="crc-range-track"><div class="crc-range-fill"></div></div><input type="range" class="crc-range-input crc-range-min" min="0" max="%4$d" step="1" value="%5$d" aria-label="%6$s" aria-controls="%7$s"><input type="range" class="crc-range-input crc-range-max" min="0" max="%4$d" step="1" value="%8$d" aria-label="%9$s" aria-controls="%10$s"></div>',
			esc_attr( wp_json_encode( array_values( $steps ) ) ),
			esc_attr( (string) round( $last ? $from / $last * 100 : 0, 2 ) ),
			esc_attr( (string) round( $last ? $to / $last * 100 : 100, 2 ) ),
			$last,
			$from,
			/* translators: %s: "Price" or "Rent per month". */
			esc_attr( sprintf( __( 'Lowest %s', 'crc-real-estate' ), strtolower( $label ) ) ),
			esc_attr( $id . '-min' ),
			$to,
			/* translators: %s: "Price" or "Rent per month". */
			esc_attr( sprintf( __( 'Highest %s', 'crc-real-estate' ), strtolower( $label ) ) ),
			esc_attr( $id . '-max' )
		);

		return self::group( $slug, $on, $boxes, $label, $id, $slider );
	}

	/**
	 * A box with − and + on either side, for bedrooms, bathrooms or furnishing.
	 *
	 * @param string $id       Field ID.
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param array  $choices  Value => text, from the first to the last; the first is "Any".
	 * @param string $selected Value chosen now.
	 * @param string $fewer    What − does, for screen readers.
	 * @param string $more     What + does, for screen readers.
	 * @return string
	 */
	public static function stepper( $id, $name, $label, array $choices, $selected, $fewer, $more ) {
		$values = array_map( 'strval', array_keys( $choices ) );
		$texts  = array_values( $choices );
		$at     = array_search( (string) $selected, $values, true );
		$at     = false === $at ? 0 : (int) $at;
		$last   = count( $values ) - 1;
		$button = '<span class="crc-stepper-button crc-stepper-%1$s" role="button" tabindex="-1" aria-label="%2$s" aria-controls="%3$s"%4$s>%5$s</span>';

		return sprintf(
			'<label class="crc-filter-label" for="%1$s">%2$s</label><div class="crc-stepper" data-crc-stepper>%3$s<input type="text" class="crc-filter-input crc-stepper-input" id="%1$s" value="%4$s" readonly role="spinbutton" aria-valuemin="0" aria-valuemax="%5$d" aria-valuenow="%6$d" aria-valuetext="%4$s" data-values="%7$s" data-texts="%8$s"><input type="hidden" name="%9$s" value="%10$s">%11$s</div>',
			esc_attr( $id ),
			esc_html( $label ),
			sprintf( $button, 'minus', esc_attr( $fewer ), esc_attr( $id ), 0 === $at ? ' aria-disabled="true"' : '', Icons::svg( 'minus', 'crc-stepper-icon' ) ),
			esc_attr( $texts[ $at ] ),
			$last,
			$at,
			esc_attr( wp_json_encode( $values ) ),
			esc_attr( wp_json_encode( $texts ) ),
			esc_attr( $name ),
			esc_attr( $values[ $at ] ),
			sprintf( $button, 'plus', esc_attr( $more ), esc_attr( $id ), $last === $at ? ' aria-disabled="true"' : '', Icons::svg( 'plus', 'crc-stepper-icon' ) )
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
			$html .= self::price_range( $prefix, $category, $on, 'properties-for-rent' === $category ? __( 'Rent per month', 'crc-real-estate' ) : __( 'Price', 'crc-real-estate' ), $filters );
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
			'beds'  => array( __( 'Bedrooms', 'crc-real-estate' ), __( 'Fewer bedrooms', 'crc-real-estate' ), __( 'More bedrooms', 'crc-real-estate' ) ),
			'baths' => array( __( 'Bathrooms', 'crc-real-estate' ), __( 'Fewer bathrooms', 'crc-real-estate' ), __( 'More bathrooms', 'crc-real-estate' ) ),
		) as $key => $field ) {
			if ( ! in_array( $key, $fields, true ) ) {
				continue;
			}

			$choices = array( '' => __( 'Any', 'crc-real-estate' ) );

			foreach ( range( 1, 5 ) as $number ) {
				/* translators: %s: number of rooms, e.g. "3+" means 3 or more. */
				$choices[ $number ] = sprintf( __( '%s+', 'crc-real-estate' ), number_format_i18n( $number ) );
			}

			$html .= self::group( $slug, $on, self::stepper( $prefix . '-' . $key, $key, $field[0], $choices, $filters[ $key ] ? $filters[ $key ] : '', $field[1], $field[2] ) );
		}

		if ( in_array( 'furnishing', $fields, true ) && Listing_Query::furnishings() ) {
			$html .= self::group(
				$slug,
				$on,
				self::stepper( $prefix . '-furnishing', 'furnishing', __( 'Furnishing', 'crc-real-estate' ), array( '' => __( 'Any', 'crc-real-estate' ) ) + Listing_Query::furnishings(), $filters['furnishing'], __( 'Previous furnishing', 'crc-real-estate' ), __( 'Next furnishing', 'crc-real-estate' ) )
			);
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
		$choices = sprintf( '<option value="" data-url="%1$s"%2$s>%3$s</option>', esc_url( Listing_Archive::page_url() ), '' === $filters['category'] ? ' selected' : '', esc_html__( 'All listings', 'crc-real-estate' ) );
		$fields  = self::fields( $id, '', '' === $filters['category'] ? $filters : Listing_Query::blank(), '' === $filters['category'] );

		foreach ( $terms as $term ) {
			$on       = $term->slug === $filters['category'];
			$choices .= sprintf( '<option value="%1$s" data-url="%2$s"%3$s>%4$s</option>', esc_attr( $term->slug ), esc_url( Listing_Archive::category_url( $term->slug ) ), $on ? ' selected' : '', esc_html( $term->name ) );
			$fields  .= self::fields( $id, $term->slug, $on ? $filters : Listing_Query::blank(), $on );
		}

		$count = self::chosen( $filters );
		$place = '' !== $filters['location'] ? $filters['location'] : Listing_Archive::place_name( $filters );
		$label = '<span class="elementor-button-content-wrapper"><span class="elementor-button-text">%s</span></span>';

		return sprintf(
			'<form class="crc-filters crc-card crc-listing-price" id="%1$s" method="get" action="%2$s" aria-label="%3$s" data-crc-filters><div class="crc-filters-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="%1$s-body" data-crc-filters-toggle>%4$s<span class="crc-filters-toggle-text">%5$s</span>%6$s%7$s</div><div class="crc-filters-body" id="%1$s-body"><div class="crc-filters-head"><h6 class="crc-filters-title">%5$s</h6></div><div class="crc-filter"><label class="crc-filter-label" for="%1$s-category">%8$s</label><select class="crc-filter-select" id="%1$s-category" name="category">%9$s</select></div><div class="crc-filter"><label class="crc-filter-label" for="%1$s-location">%10$s</label><div class="crc-place">%11$s<ul class="crc-places" id="%1$s-places" role="listbox" aria-label="%12$s" hidden></ul></div></div>%13$s<div class="crc-filters-buttons"><button type="submit" class="elementor-button crc-filters-submit">%14$s</button><div class="crc-filters-clear custom-btn-1-lite"><a class="elementor-button elementor-button-link" href="%15$s">%16$s</a></div></div></div></form>',
			esc_attr( $id ),
			esc_url( Listing_Archive::base_url() ),
			esc_attr__( 'Filter listings', 'crc-real-estate' ),
			Icons::svg( 'filter', 'crc-filters-toggle-icon' ),
			esc_html( $atts['title'] ),
			$count ? '<span class="crc-filters-toggle-count">' . esc_html( number_format_i18n( $count ) ) . '</span>' : '',
			Icons::svg( 'chevron-down', 'crc-filters-toggle-arrow' ),
			esc_html__( 'Looking for', 'crc-real-estate' ),
			$choices, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html__( 'Location', 'crc-real-estate' ),
			Listing_Search::place_field( $id . '-location', $place, __( 'Town, district or Colombo zone', 'crc-real-estate' ), $id . '-places' ),
			esc_attr__( 'Places', 'crc-real-estate' ),
			$fields, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in fields().
			'<span class="elementor-button-content-wrapper"><span class="elementor-button-text">' . esc_html( $atts['button'] ) . '</span><span class="crc-search-spinner" aria-hidden="true"></span></span>',
			esc_url( Listing_Archive::base_url() ),
			sprintf( $label, esc_html( $atts['clear'] ) )
		);
	}
}
