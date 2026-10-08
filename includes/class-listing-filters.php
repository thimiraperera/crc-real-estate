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
 * pages, staying in view while the listings scroll. The place or keywords,
 * with the same suggestions as the search box, at the top; what people are
 * looking for (a category, or every listing) as rounded radio buttons; and
 * the chosen category's own choices:
 *
 * - land: the price (two boxes and a slider), the price per perch, the land
 *   size and the property type;
 * - homes: the price or rent per month (two boxes and a slider), bedrooms,
 *   bathrooms and furnishing (each a box with − and +) and the property type.
 *
 * Under More filters, a bar that slides open: ticks for features (legal
 * papers, the category's own features, nearby places) and for the price and
 * terms; the floor area and parking for homes; the road type and width,
 * electricity and water; the availability, who listed it and how recently.
 * It opens by itself when some of them are chosen, with how many on the bar.
 *
 * Show Listings opens the category's archive with the choices made; Clear All
 * takes them off. On phones the filters fold away behind a Filters bar.
 *
 * The boxes and their labels take the site's form styles from Elementor, and
 * both buttons the site's button styles; the dropdowns are the plugin's own
 * soft grey boxes, with a list that falls open under them.
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
				'description' => __( 'The filters for your archive templates (All Listings Archive, All Listing Categories Archive, All Districts Archive and All Towns Archive), beside or above the results, in the same white card as the price box. Beside the results they stay in view while the listings scroll. People type the place or keywords at the top, with the same suggestions as the search box, and choose what they are looking for (a category, or every listing) from rounded buttons, then the category\'s own choices. Land has the price, with two boxes and a slider, the price per perch and the land size; homes have the price or rent per month, with two boxes and a slider, and bedrooms, bathrooms and furnishing, each a box with − and +; every category has the property type. Under More filters, a bar that slides open, people can tick features (such as a clear deed, a garden or close to schools) and choose a price that can be negotiated, a bank loan for homes for sale or bills included for rentals, the floor area and parking for homes, the road type and width, water and electricity, the availability, who listed it and how recently. It opens by itself when some of them are chosen, and the bar shows how many. Show Listings opens the category\'s archive with the choices made, and Clear All takes them off. On phones the filters fold away behind a Filters bar. The boxes and labels take your form styles from Elementor\'s Site Settings, and the buttons your button styles. The dropdowns are soft grey boxes that turn white while open, with a list that falls open under them and slim scrollbars.', 'crc-real-estate' ),
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
					'more'       => array(
						'default'     => __( 'More filters', 'crc-real-estate' ),
						'description' => __( 'The text on the bar that opens the extra filters (features, road, water, who listed it and more). Leave it empty, more="", to hide the extra filters.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' title="Refine your search"]',
					'[' . self::SHORTCODE . ' more="Advanced filters"]',
				),
			)
		);
	}

	/**
	 * A labelled dropdown: a box that opens the plugin's own list, with
	 * slim scrollbars, in place of the browser's. search.js makes the list
	 * from the select and then hides the select, which keeps the choice for
	 * the address; without the script the browser's own list still works.
	 *
	 * @param string $id       Field ID.
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $any      First choice, for no choice.
	 * @param array  $options  Value => label.
	 * @param string $selected Selected value.
	 * @param bool   $hidden   Whether the label is for screen readers only.
	 * @param string $heading  For one of a pair: the ID of the pair's heading, read out first.
	 * @return string
	 */
	private static function select( $id, $name, $label, $any, array $options, $selected, $hidden = false, $heading = '' ) {
		$shown = $any;

		foreach ( $options as $value => $text ) {
			if ( '' !== (string) $selected && (string) $value === (string) $selected ) {
				$shown = $text;
			}
		}

		return sprintf(
			'<label class="%1$s" id="%2$s-label" for="%2$s">%3$s</label><div class="crc-dropdown crc-filter-dropdown" data-crc-dropdown><div class="crc-filter-dropdown-button" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" aria-controls="%2$s-list" aria-labelledby="%4$s%2$s-label %2$s-text" data-crc-toggle><span class="crc-filter-dropdown-text" id="%2$s-text" data-crc-dropdown-text>%5$s</span>%6$s</div><ul class="crc-dropdown-list" id="%2$s-list" role="listbox" tabindex="-1" aria-labelledby="%4$s%2$s-label" hidden data-crc-list></ul><select class="crc-filter-select" id="%2$s" name="%7$s"><option value="">%8$s</option>%9$s</select></div>',
			$hidden ? 'crc-sr' : 'crc-filter-label',
			esc_attr( $id ),
			esc_html( $label ),
			'' !== $heading ? esc_attr( $heading ) . ' ' : '',
			esc_html( $shown ),
			Icons::svg( 'chevron-down', 'crc-filter-dropdown-icon' ),
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
				self::select( $prefix . '-size-min', 'size_min', __( 'Smallest', 'crc-real-estate' ), __( 'No min', 'crc-real-estate' ), $sizes, $filters['size_min'], true, $prefix . '-size-label' )
				. self::select( $prefix . '-size-max', 'size_max', __( 'Largest', 'crc-real-estate' ), __( 'No max', 'crc-real-estate' ), $sizes, $filters['size_max'], true, $prefix . '-size-label' ),
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
	 * One rounded choice: a tick box, or a radio button for Looking for.
	 *
	 * @param string $type  "checkbox" or "radio".
	 * @param string $name  Field name.
	 * @param string $value Field value.
	 * @param string $label Words on it.
	 * @param bool   $on    Whether it is chosen.
	 * @param string $url   For Looking for: the category's archive.
	 * @return string
	 */
	private static function chip( $type, $name, $value, $label, $on, $url = '' ) {
		return sprintf(
			'<label class="crc-chip"><input type="%1$s" class="crc-chip-input" name="%2$s" value="%3$s"%4$s%5$s><span class="crc-chip-face">%6$s<span class="crc-chip-text">%7$s</span></span></label>',
			'radio' === $type ? 'radio' : 'checkbox',
			esc_attr( $name ),
			esc_attr( $value ),
			'' !== $url ? ' data-url="' . esc_url( $url ) . '"' : '',
			$on ? ' checked' : '',
			Icons::svg( 'check', 'crc-chip-icon' ),
			esc_html( $label )
		);
	}

	/**
	 * Rounded choices that are ticked on and off, under a heading: features,
	 * or the price and terms. The heading is a label, so it looks like the
	 * other fields' labels.
	 *
	 * @param string $for     Category slug, or "all".
	 * @param bool   $on      Whether it is for the category chosen now.
	 * @param string $id      Heading ID.
	 * @param string $heading Heading.
	 * @param array  $chips   Each 'name', 'value', 'label' and 'on' (ticked).
	 * @return string
	 */
	private static function chips( $for, $on, $id, $heading, array $chips ) {
		$html = '';

		foreach ( $chips as $chip ) {
			$html .= self::chip( 'checkbox', $chip['name'], $chip['value'], $chip['label'], $chip['on'] );
		}

		return sprintf(
			'<fieldset class="crc-filter crc-filter-wide" data-for="%1$s" aria-labelledby="%2$s"%3$s><label class="crc-filter-label" id="%2$s">%4$s</label><div class="crc-chips">%5$s</div></fieldset>',
			esc_attr( $for ),
			esc_attr( $id ),
			$on ? '' : ' hidden disabled',
			esc_html( $heading ),
			$html
		);
	}

	/**
	 * The More filters for one category, or for every listing: the price and
	 * terms, the floor area and parking for homes, the road, the water and
	 * electricity, the features, and the availability, who listed it and when.
	 *
	 * @param string $id       Filters ID.
	 * @param string $category Category slug, or empty for every listing.
	 * @param array  $filters  Filters chosen now, or blank ones for another category.
	 * @param bool   $on       Whether the category is chosen now.
	 * @return string
	 */
	private static function more_fields( $id, $category, array $filters, $on ) {
		$fields  = Listing_Query::fields_for( $category );
		$slug    = '' !== $category ? $category : 'all';
		$prefix  = $id . '-' . $slug;
		$choices = Listing_Query::more_choices();
		$any     = __( 'Any', 'crc-real-estate' );
		$html    = '';
		$terms   = array();

		foreach ( array(
			'negotiable' => __( 'Price negotiable', 'crc-real-estate' ),
			'bank_loan'  => __( 'Bank loan available', 'crc-real-estate' ),
			'bills'      => __( 'Bills included in the rent', 'crc-real-estate' ),
		) as $key => $label ) {
			if ( in_array( $key, $fields, true ) ) {
				$terms[] = array(
					'name'  => $key,
					'value' => '1',
					'label' => $label,
					'on'    => '' !== $filters[ $key ],
				);
			}
		}

		if ( $terms ) {
			$html .= self::chips( $slug, $on, $prefix . '-terms-label', __( 'Price and terms', 'crc-real-estate' ), $terms );
		}

		$list = function ( $key, $label, $first, $name = '' ) use ( $fields, $choices, $slug, $on, $prefix, $filters ) {
			$name = '' !== $name ? $name : $key;

			if ( ! in_array( $key, $fields, true ) || empty( $choices[ $name ] ) ) {
				return '';
			}

			return self::group( $slug, $on, self::select( $prefix . '-' . str_replace( '_', '-', $key ), $key, $label, $first, (array) $choices[ $name ], 0 !== $filters[ $key ] ? (string) $filters[ $key ] : '' ) );
		};

		$html .= $list( 'advance_max', __( 'Advance payment', 'crc-real-estate' ), $any );

		if ( in_array( 'floor_min', $fields, true ) && ! empty( $choices['floor'] ) ) {
			$html .= self::group(
				$slug,
				$on,
				self::select( $prefix . '-floor-min', 'floor_min', __( 'Smallest', 'crc-real-estate' ), __( 'No min', 'crc-real-estate' ), (array) $choices['floor'], $filters['floor_min'] ? (string) $filters['floor_min'] : '', true, $prefix . '-floor-label' )
				. self::select( $prefix . '-floor-max', 'floor_max', __( 'Largest', 'crc-real-estate' ), __( 'No max', 'crc-real-estate' ), (array) $choices['floor'], $filters['floor_max'] ? (string) $filters['floor_max'] : '', true, $prefix . '-floor-label' ),
				__( 'Floor area', 'crc-real-estate' ),
				$prefix . '-floor'
			);
		}

		if ( in_array( 'parking', $fields, true ) ) {
			$spaces = array( '' => $any );

			foreach ( range( 1, Listing_Query::MOST_PARKING ) as $number ) {
				/* translators: %s: number of parking spaces, e.g. "2+" means 2 or more. */
				$spaces[ $number ] = sprintf( __( '%s+', 'crc-real-estate' ), number_format_i18n( $number ) );
			}

			$html .= self::group( $slug, $on, self::stepper( $prefix . '-parking', 'parking', __( 'Parking spaces', 'crc-real-estate' ), $spaces, $filters['parking'] ? (string) $filters['parking'] : '', __( 'Fewer parking spaces', 'crc-real-estate' ), __( 'More parking spaces', 'crc-real-estate' ) ) );
		}

		$html .= $list( 'road_type', __( 'Road type', 'crc-real-estate' ), $any )
			. $list( 'road_width', __( 'Road width', 'crc-real-estate' ), $any )
			. $list( 'electricity', __( 'Electricity', 'crc-real-estate' ), $any )
			. $list( 'water', __( 'Water supply', 'crc-real-estate' ), $any );

		if ( in_array( 'features', $fields, true ) ) {
			$ticked = '' !== $filters['features'] ? explode( ',', $filters['features'] ) : array();

			foreach ( Listing_Query::feature_groups( $category ) as $name => $group ) {
				$chips = array();

				foreach ( $group['features'] as $feature => $label ) {
					$chips[] = array(
						'name'  => 'features[]',
						'value' => $feature,
						'label' => $label,
						'on'    => in_array( (string) $feature, $ticked, true ),
					);
				}

				$html .= self::chips( $slug, $on, $prefix . '-' . sanitize_html_class( $name ) . '-label', '' !== $group['title'] ? $group['title'] : __( 'Features', 'crc-real-estate' ), $chips );
			}
		}

		return $html
			. $list( 'availability', __( 'Availability', 'crc-real-estate' ), $any )
			. $list( 'listed_by', __( 'Listed by', 'crc-real-estate' ), $any )
			. $list( 'posted', __( 'Date listed', 'crc-real-estate' ), __( 'Any time', 'crc-real-estate' ) );
	}

	/**
	 * How many filters are chosen, for the Filters bar on phones. Each
	 * ticked feature counts.
	 *
	 * @param array $filters Filters.
	 * @param array $only    Filter names to count, or empty for all.
	 * @return int
	 */
	public static function chosen( array $filters, array $only = array() ) {
		$count = 0;

		foreach ( Listing_Query::params( $filters, array( 'sort', 'page' ) ) as $key => $value ) {
			if ( ! $only || in_array( $key, $only, true ) ) {
				$count += 'features' === $key ? count( explode( ',', $value ) ) : 1;
			}
		}

		return $count;
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
		$all     = '' === $filters['category'] || ! in_array( $filters['category'], wp_list_pluck( $terms, 'slug' ), true );
		$choices = self::chip( 'radio', 'category', '', __( 'All listings', 'crc-real-estate' ), $all, Listing_Archive::page_url() );
		$fields  = self::fields( $id, '', '' === $filters['category'] ? $filters : Listing_Query::blank(), '' === $filters['category'] );
		$show    = '' !== trim( $atts['more'] );
		$more    = $show ? self::more_fields( $id, '', '' === $filters['category'] ? $filters : Listing_Query::blank(), '' === $filters['category'] ) : '';

		foreach ( $terms as $term ) {
			$on       = $term->slug === $filters['category'];
			$choices .= self::chip( 'radio', 'category', $term->slug, $term->name, $on, Listing_Archive::category_url( $term->slug ) );
			$fields  .= self::fields( $id, $term->slug, $on ? $filters : Listing_Query::blank(), $on );
			$more    .= $show ? self::more_fields( $id, $term->slug, $on ? $filters : Listing_Query::blank(), $on ) : '';
		}

		// More filters: open when some are chosen, with how many on the bar.
		if ( '' !== $more ) {
			$extra = self::chosen( $filters, Listing_Query::MORE );
			$more  = sprintf(
				'<details class="crc-filters-more"%1$s data-crc-more><summary class="crc-filters-more-toggle">%2$s<span class="crc-filters-more-text">%3$s</span><span class="crc-filters-more-count"%4$s>%5$s</span>%6$s</summary><div class="crc-filters-more-body"><div class="crc-filters-more-inner">%7$s</div></div></details>',
				$extra ? ' open' : '',
				Icons::svg( 'filter', 'crc-filters-more-icon' ),
				esc_html( $atts['more'] ),
				$extra ? '' : ' hidden',
				esc_html( number_format_i18n( $extra ) ),
				Icons::svg( 'chevron-down', 'crc-filters-more-arrow' ),
				$more
			);
		}

		$count = self::chosen( $filters );
		$place = '' !== $filters['location'] ? $filters['location'] : Listing_Archive::place_name( $filters );
		$label = '<span class="elementor-button-content-wrapper"><span class="elementor-button-text">%s</span></span>';

		return sprintf(
			'<form class="crc-filters crc-card crc-listing-price" id="%1$s" method="get" action="%2$s" aria-label="%3$s" data-crc-filters><div class="crc-filters-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="%1$s-body" data-crc-filters-toggle>%4$s<span class="crc-filters-toggle-text">%5$s</span>%6$s%7$s</div><div class="crc-filters-body" id="%1$s-body"><div class="crc-filters-head"><h6 class="crc-filters-title">%5$s</h6></div><div class="crc-filter"><label class="crc-filter-label" for="%1$s-location">%10$s</label><div class="crc-place">%11$s<ul class="crc-places" id="%1$s-places" role="listbox" aria-label="%12$s" hidden></ul></div></div><fieldset class="crc-filter crc-filter-looking" aria-labelledby="%1$s-category-label"><label class="crc-filter-label" id="%1$s-category-label">%8$s</label><div class="crc-chips">%9$s</div></fieldset>%13$s%17$s<div class="crc-filters-buttons"><button type="submit" class="elementor-button crc-filters-submit">%14$s</button><div class="crc-filters-clear custom-btn-1-lite"><a class="elementor-button elementor-button-link" href="%15$s">%16$s</a></div></div></div></form>',
			esc_attr( $id ),
			esc_url( Listing_Archive::base_url() ),
			esc_attr__( 'Filter listings', 'crc-real-estate' ),
			Icons::svg( 'filter', 'crc-filters-toggle-icon' ),
			esc_html( $atts['title'] ),
			$count ? '<span class="crc-filters-toggle-count">' . esc_html( number_format_i18n( $count ) ) . '</span>' : '',
			Icons::svg( 'chevron-down', 'crc-filters-toggle-arrow' ),
			esc_html__( 'Looking for', 'crc-real-estate' ),
			$choices, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html__( 'Location or keyword', 'crc-real-estate' ),
			Listing_Search::place_field( $id . '-location', $place, __( 'Town, property type or keyword', 'crc-real-estate' ), $id . '-places' ),
			esc_attr__( 'Places', 'crc-real-estate' ),
			$fields, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in fields().
			'<span class="elementor-button-content-wrapper"><span class="elementor-button-text">' . esc_html( $atts['button'] ) . '</span><span class="crc-search-spinner" aria-hidden="true"></span></span>',
			esc_url( Listing_Archive::base_url() ),
			sprintf( $label, esc_html( $atts['clear'] ) ),
			$more // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in more_fields() and above.
		);
	}
}
