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
 * [crc_listing_search]: a tab for each category (a dropdown on phones), a
 * place box that suggests towns and districts with listings as you type, a
 * Search button, and under it the category's own filters as rounded buttons
 * that open sliders and lists. Search opens the category's archive (such as
 * /listings/lands/) with the filters chosen.
 * Also loads the search's script and answers the place box's suggestions
 * for every search part.
 */
final class Listing_Search {

	const SHORTCODE   = 'crc_listing_search';
	const AJAX        = 'crc_re_places';
	const MIN_LETTERS = 3;

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
				'description' => __( 'A search for any page: a tab for each listing category, named as the categories are, a place box that suggests towns and districts with listings from three letters, and a Search button in the site\'s button style. Under it are the chosen category\'s own choices as rounded buttons: land size, the highest price per perch and the property type for land; bedrooms, the highest price or rent and the property type for homes. Each opens a slider under the buttons, as wide as the search box (bedrooms also have boxes for the fewest and the most), and the property type opens a list. On phones the categories are a dropdown and the Search button takes the whole width. Search opens the category\'s archive, such as /listings/lands/, with those choices made.', 'crc-real-estate' ),
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
						'description' => __( 'no to leave out the rounded buttons under the box.', 'crc-real-estate' ),
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
				'letters' => self::MIN_LETTERS,
				'noMin'   => __( 'No min', 'crc-real-estate' ),
				'noMax'   => __( 'No max', 'crc-real-estate' ),
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
	 * A rounded button for one of a category's choices: what it is, or what
	 * is chosen, with an arrow. It opens its panel or its list.
	 *
	 * @param string $controls ID of the panel or list it opens.
	 * @param string $label    What it is, shown until something is chosen.
	 * @param string $text     What is chosen, or empty.
	 * @param bool   $list     Whether it opens a list to choose from.
	 * @return string
	 */
	private static function pill( $controls, $label, $text, $list = false ) {
		return sprintf(
			'<div class="crc-pill%1$s" role="button" tabindex="0" aria-expanded="false" aria-controls="%2$s" aria-label="%3$s"%4$s data-crc-toggle><span class="crc-pill-text" data-empty="%5$s">%6$s</span>%7$s</div>',
			'' !== $text ? ' has-value' : '',
			esc_attr( $controls ),
			esc_attr( '' !== $text ? $label . ': ' . $text : $label ),
			$list ? ' aria-haspopup="listbox"' : '',
			esc_attr( $label ),
			esc_html( '' !== $text ? $text : $label ),
			Icons::svg( 'chevron-down', 'crc-pill-icon' )
		);
	}

	/**
	 * What a slider is set to, in words: "Up to Rs. 5,000,000", "From 20
	 * perches" or "2 – 4 bedrooms". Empty when it is set to anything.
	 *
	 * @param array $slider 'range', 'labels', 'short' and 'texts', as in slider().
	 * @param int   $low    The lowest handle's step; 0 is no least.
	 * @param int   $high   The highest handle's step.
	 * @param int   $last   The last step, which is no most.
	 * @return string
	 */
	private static function describe( array $slider, $low, $high, $last ) {
		$no_min = ! $slider['range'] || $low <= 0;
		$no_max = $high >= $last;

		if ( $no_min && $no_max ) {
			return '';
		}

		if ( $no_min ) {
			return sprintf( $slider['texts']['upTo'], $slider['labels'][ $high ] );
		}

		if ( $no_max ) {
			return sprintf( $slider['texts']['from'], $slider['short'][ $low ] );
		}

		if ( $low === $high ) {
			return $slider['labels'][ $low ];
		}

		return sprintf( $slider['texts']['between'], $slider['short'][ $low ], $slider['short'][ $high ] );
	}

	/**
	 * A rounded button that opens a slider in a panel under the buttons, as
	 * wide as the search box. One handle sets the most; two set the least
	 * and the most.
	 *
	 * @param string $id    Start of the IDs.
	 * @param string $label What it is, e.g. "Land size".
	 * @param array  $args  {
	 *     @type bool     $range  Two handles, or one for the most.
	 *     @type array    $steps  Amounts, smallest first. The last is no most; with two handles the first is 0, no least.
	 *     @type string[] $labels Each step in words, e.g. "20 perches".
	 *     @type string[] $short  Shorter words for "from" and "between", e.g. "2". Optional.
	 *     @type string[] $texts  'any', 'from', 'upTo' and 'between', with %s where the words go.
	 *     @type string[] $names  The least's and the most's field names; '' for none.
	 *     @type array    $values The least and the most chosen now, or ''.
	 *     @type string[] $aria   What each handle sets, for screen readers.
	 *     @type string[] $boxes  Labels of the boxes to type the least and the most in, or none.
	 * }
	 * @return string[] 'pill' and 'panel'.
	 */
	private static function slider( $id, $label, array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'range'  => false,
				'steps'  => array(),
				'labels' => array(),
				'short'  => array(),
				'texts'  => array(),
				'names'  => array( '', '' ),
				'values' => array( '', '' ),
				'aria'   => array( '', '' ),
				'boxes'  => array(),
			)
		);

		$args['steps']  = array_values( $args['steps'] );
		$args['labels'] = array_values( $args['labels'] );
		$args['short']  = $args['short'] ? array_values( $args['short'] ) : $args['labels'];
		$args['texts'] += array(
			'any'     => '',
			'from'    => '%s',
			'upTo'    => '%s',
			'between' => '%1$s – %2$s',
		);

		$last   = count( $args['steps'] ) - 1;
		$least  = $args['range'] ? (string) $args['values'][0] : '';
		$most   = (string) $args['values'][1];
		$high   = '' !== $most && (float) $most > 0 ? Listing_Filters::step_of( $args['steps'], (float) $most, true ) : $last;
		$low    = '' !== $least && (float) $least > 0 ? min( $high, Listing_Filters::step_of( $args['steps'], (float) $least, false ) ) : 0;
		$text   = self::describe( $args, $low, $high, $last );
		$panel  = $id . '-panel';
		$fields = '';
		$boxes  = array();

		foreach ( array( 0, 1 ) as $end ) {
			if ( '' === (string) $args['names'][ $end ] ) {
				continue;
			}

			$field = $id . ( $end ? '-max' : '-min' );
			$value = $end ? $most : $least;

			if ( $args['boxes'] ) {
				$boxes[] = sprintf(
					'<div class="crc-pill-panel-field"><label class="crc-pill-panel-label" for="%1$s">%2$s</label><input type="text" class="crc-pill-panel-input" id="%1$s" name="%3$s" value="%4$s" placeholder="%5$s" inputmode="numeric" maxlength="2" autocomplete="off" aria-label="%6$s"></div>',
					esc_attr( $field ),
					esc_html( $args['boxes'][ $end ] ),
					esc_attr( $args['names'][ $end ] ),
					esc_attr( $value ),
					esc_attr__( 'Any', 'crc-real-estate' ),
					esc_attr( $args['aria'][ $end ] )
				);
			} else {
				$fields .= sprintf( '<input type="hidden" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $field ), esc_attr( $args['names'][ $end ] ), esc_attr( $value ) );
			}
		}

		$side = $boxes
			? implode( '<span class="crc-pill-panel-dash" aria-hidden="true">–</span>', $boxes )
			: sprintf( '<span class="crc-pill-panel-value" data-crc-readout>%s</span>', esc_html( '' !== $text ? $text : $args['texts']['any'] ) );

		$input  = '<input type="range" class="crc-slider-input crc-slider-%1$s" min="0" max="%2$d" step="1" value="%3$d" aria-label="%4$s">';
		$inputs = ( $args['range'] ? sprintf( $input, 'min', $last, $low, esc_attr( $args['aria'][0] ) ) : '' ) . sprintf( $input, 'max', $last, $high, esc_attr( $args['aria'][1] ) );

		$slider = sprintf(
			'<div class="crc-slider" data-crc-slider data-mode="%1$s" data-steps="%2$s" data-labels="%3$s" data-short="%4$s" data-texts="%5$s" data-min-field="%6$s" data-max-field="%7$s" style="--crc-slider-from: %8$s%%; --crc-slider-to: %9$s%%;"><div class="crc-slider-track"><div class="crc-slider-fill"></div></div>%10$s</div>',
			$args['range'] ? 'range' : 'max',
			esc_attr( wp_json_encode( $args['steps'] ) ),
			esc_attr( wp_json_encode( $args['labels'] ) ),
			esc_attr( wp_json_encode( $args['short'] ) ),
			esc_attr(
				wp_json_encode(
					$args['texts'] + array(
						'noMin' => __( 'No min', 'crc-real-estate' ),
						'noMax' => __( 'No max', 'crc-real-estate' ),
					)
				)
			),
			esc_attr( $args['range'] ? $id . '-min' : '' ),
			esc_attr( $id . '-max' ),
			esc_attr( (string) round( $last ? $low / $last * 100 : 0, 2 ) ),
			esc_attr( (string) round( $last ? $high / $last * 100 : 100, 2 ) ),
			$inputs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);

		return array(
			'pill'  => self::pill( $panel, $label, $text ),
			'panel' => sprintf(
				'<div class="crc-pill-panel" id="%1$s" role="group" aria-labelledby="%1$s-title" inert data-crc-panel><div class="crc-pill-panel-inner"><div class="crc-pill-panel-box"><div class="crc-pill-panel-head"><span class="crc-pill-panel-title" id="%1$s-title">%2$s</span><div class="crc-pill-panel-side">%3$s</div></div>%4$s%5$s</div></div></div>',
				esc_attr( $panel ),
				esc_html( $label ),
				$side, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				$slider, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				$fields // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			),
		);
	}

	/**
	 * A list's choices, the chosen one ticked.
	 *
	 * @param string $id       The list's ID, the start of its choices' IDs.
	 * @param array  $options  Value => text.
	 * @param string $selected Value chosen now.
	 * @return string
	 */
	private static function list_options( $id, array $options, $selected ) {
		$html = '';
		$i    = 0;

		foreach ( $options as $value => $text ) {
			$on    = (string) $value === (string) $selected;
			$html .= sprintf(
				'<li class="crc-dropdown-option%1$s" id="%2$s" role="option" aria-selected="%3$s" data-value="%4$s"><span class="crc-dropdown-option-text">%5$s</span>%6$s</li>',
				$on ? ' is-selected' : '',
				esc_attr( $id . '-' . $i ),
				$on ? 'true' : 'false',
				esc_attr( (string) $value ),
				esc_html( $text ),
				Icons::svg( 'check', 'crc-dropdown-option-icon' )
			);
			++$i;
		}

		return $html;
	}

	/**
	 * A rounded button that opens a list to choose from under it, for the
	 * property type: any, then each choice.
	 *
	 * @param string $id       Start of the IDs.
	 * @param string $name     Field name.
	 * @param string $label    What it is, shown until something is chosen.
	 * @param string $any      The choice for any.
	 * @param array  $options  Value => text.
	 * @param string $selected Value chosen now.
	 * @return string[] 'pill' and 'panel' (none).
	 */
	private static function dropdown( $id, $name, $label, $any, array $options, $selected ) {
		$selected = isset( $options[ (string) $selected ] ) ? (string) $selected : '';
		$list     = $id . '-list';

		return array(
			'pill'  => sprintf(
				'<div class="crc-dropdown" data-crc-dropdown>%1$s<ul class="crc-dropdown-list" id="%2$s" role="listbox" tabindex="-1" aria-label="%3$s" hidden data-crc-list>%4$s</ul><input type="hidden" name="%5$s" value="%6$s"></div>',
				self::pill( $list, $label, '' !== $selected ? (string) $options[ $selected ] : '', true ),
				esc_attr( $list ),
				esc_attr( $label ),
				self::list_options( $list, array( '' => $any ) + $options, $selected ),
				esc_attr( $name ),
				esc_attr( $selected )
			),
			'panel' => '',
		);
	}

	/**
	 * Amounts written as money, e.g. "Rs. 5,000,000".
	 *
	 * @param int[] $amounts Amounts in rupees.
	 * @return string[]
	 */
	private static function money_labels( array $amounts ) {
		$labels = array();

		foreach ( $amounts as $amount ) {
			$labels[] = Price_Card::money( (string) $amount );
		}

		return $labels;
	}

	/**
	 * A category's choices as rounded buttons: land size, the highest price
	 * per perch and the property type for land; bedrooms, the highest price
	 * or rent and the property type for homes. The sliders open in panels
	 * under the buttons, as wide as the search box; the property type opens
	 * a list.
	 *
	 * @param string $category Category slug.
	 * @param array  $filters  Filters chosen now.
	 * @param string $id       Start of the IDs.
	 * @return string
	 */
	public static function pills( $category, array $filters, $id = 'crc-search' ) {
		$fields   = Listing_Query::fields_for( $category );
		$filters += Listing_Query::blank();
		$parts    = array();
		/* translators: %s: amount, e.g. "Rs. 5,000,000", size, e.g. "40 perches", or bedrooms, e.g. "4 bedrooms". */
		$up_to = __( 'Up to %s', 'crc-real-estate' );

		if ( in_array( 'size_min', $fields, true ) ) {
			$labels = array( '' );

			foreach ( Listing_Query::sizes() as $size ) {
				$labels[] = Listing_Query::size_label( $size );
			}

			$parts[] = self::slider(
				$id . '-size',
				__( 'Land size', 'crc-real-estate' ),
				array(
					'range'  => true,
					'steps'  => array_merge( array( 0 ), Listing_Query::sizes() ),
					'labels' => $labels,
					'texts'  => array(
						'any'     => __( 'Any size', 'crc-real-estate' ),
						/* translators: %s: size, e.g. "20 perches". */
						'from'    => __( 'From %s', 'crc-real-estate' ),
						'upTo'    => $up_to,
						/* translators: 1: smallest size, 2: largest size, e.g. "10 perches – 1 acre". */
						'between' => __( '%1$s – %2$s', 'crc-real-estate' ),
					),
					'names'  => array( 'size_min', 'size_max' ),
					'values' => array( $filters['size_min'], $filters['size_max'] ),
					'aria'   => array( __( 'Smallest land size', 'crc-real-estate' ), __( 'Largest land size', 'crc-real-estate' ) ),
				)
			);
		}

		if ( in_array( 'beds', $fields, true ) ) {
			$labels = array( '' );
			$short  = array( '' );

			foreach ( range( 1, 10 ) as $number ) {
				/* translators: %s: number of bedrooms. */
				$labels[] = sprintf( _n( '%s bedroom', '%s bedrooms', $number, 'crc-real-estate' ), number_format_i18n( $number ) );
				$short[]  = number_format_i18n( $number );
			}

			$parts[] = self::slider(
				$id . '-beds',
				__( 'Bedrooms', 'crc-real-estate' ),
				array(
					'range'  => true,
					'steps'  => range( 0, 10 ),
					'labels' => $labels,
					'short'  => $short,
					'texts'  => array(
						'any'     => __( 'Any number', 'crc-real-estate' ),
						/* translators: %s: number of bedrooms; "2+ bedrooms" means 2 or more. */
						'from'    => __( '%s+ bedrooms', 'crc-real-estate' ),
						'upTo'    => $up_to,
						/* translators: 1: fewest bedrooms, 2: most bedrooms. */
						'between' => __( '%1$s – %2$s bedrooms', 'crc-real-estate' ),
					),
					'names'  => array( 'beds', 'beds_max' ),
					'values' => array( $filters['beds'] ? (string) $filters['beds'] : '', $filters['beds_max'] ? (string) $filters['beds_max'] : '' ),
					'aria'   => array( __( 'Minimum bedrooms', 'crc-real-estate' ), __( 'Maximum bedrooms', 'crc-real-estate' ) ),
					'boxes'  => array( __( 'Minimum', 'crc-real-estate' ), __( 'Maximum', 'crc-real-estate' ) ),
				)
			);
		}

		if ( in_array( 'per_perch_max', $fields, true ) ) {
			$steps   = Listing_Query::per_perch_prices();
			$parts[] = self::slider(
				$id . '-per-perch',
				__( 'Max price per perch', 'crc-real-estate' ),
				array(
					'steps'  => $steps,
					'labels' => self::money_labels( $steps ),
					'texts'  => array(
						'any'  => __( 'Any price', 'crc-real-estate' ),
						'upTo' => $up_to,
					),
					'names'  => array( '', 'per_perch_max' ),
					'values' => array( '', $filters['per_perch_max'] ),
					'aria'   => array( '', __( 'Max price per perch', 'crc-real-estate' ) ),
				)
			);
		} elseif ( in_array( 'price_max', $fields, true ) ) {
			$rent    = 'properties-for-rent' === $category;
			$steps   = array_values( array_slice( Listing_Query::price_steps( $category ), 1 ) );
			$label   = $rent ? __( 'Max rent per month', 'crc-real-estate' ) : __( 'Max price', 'crc-real-estate' );
			$parts[] = self::slider(
				$id . '-price',
				$label,
				array(
					'steps'  => $steps,
					'labels' => self::money_labels( $steps ),
					'texts'  => array(
						'any'  => $rent ? __( 'Any rent', 'crc-real-estate' ) : __( 'Any price', 'crc-real-estate' ),
						'upTo' => $up_to,
					),
					'names'  => array( '', 'price_max' ),
					'values' => array( '', $filters['price_max'] ),
					'aria'   => array( '', $label ),
				)
			);
		}

		if ( in_array( 'type', $fields, true ) ) {
			$types = self::property_types( $category );

			if ( '' !== $filters['type'] && ! in_array( $filters['type'], $types, true ) ) {
				$types[] = $filters['type'];
			}

			if ( $types ) {
				$parts[] = self::dropdown( $id . '-type', 'type', __( 'Property type', 'crc-real-estate' ), __( 'Any type', 'crc-real-estate' ), array_combine( $types, $types ), $filters['type'] );
			}
		}

		if ( ! $parts ) {
			return '';
		}

		return '<div class="crc-pills">' . implode( '', array_column( $parts, 'pill' ) ) . '</div>' . implode( '', array_column( $parts, 'panel' ) );
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
		$names = array();
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

			$names[ $term->slug ] = $term->name;

			if ( Shortcodes::is_on( $atts['filters'] ) ) {
				$group = self::pills( $term->slug, $on ? $filters : Listing_Query::blank(), $id . '-' . $term->slug );

				if ( '' !== $group ) {
					$pills .= sprintf( '<div class="crc-search-filters" data-for="%1$s"%2$s>%3$s</div>', esc_attr( $term->slug ), $on ? '' : ' hidden', $group );
				}
			}
		}

		// On phones the categories are a dropdown instead of tabs; it sets the same tabs.
		$category = sprintf(
			'<div class="crc-dropdown crc-search-category" data-crc-dropdown data-radios="category"><div class="crc-search-category-button" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s" data-crc-toggle><span class="crc-search-category-text" data-crc-dropdown-text>%3$s</span>%4$s</div><ul class="crc-dropdown-list" id="%1$s" role="listbox" tabindex="-1" aria-label="%2$s" hidden data-crc-list>%5$s</ul></div>',
			esc_attr( $id . '-category' ),
			esc_attr__( 'What you are looking for', 'crc-real-estate' ),
			esc_html( $names[ $chosen ] ),
			Icons::svg( 'chevron-down', 'crc-search-category-icon' ),
			self::list_options( $id . '-category', $names, $chosen )
		);

		return sprintf(
			'<form class="crc-search" id="%1$s" role="search" method="get" action="%2$s" aria-label="%3$s" data-crc-search><div class="crc-search-card"><div class="crc-search-tabs" role="radiogroup" aria-label="%4$s">%5$s</div>%6$s<div class="crc-search-row"><div class="crc-search-place">%7$s<label class="crc-sr" for="%1$s-location">%8$s</label>%9$s</div><button type="submit" class="elementor-button crc-search-submit"><span class="elementor-button-content-wrapper"><span class="elementor-button-text crc-search-submit-text">%10$s</span>%11$s</span></button><ul class="crc-places" id="%1$s-places" role="listbox" aria-label="%12$s" hidden></ul></div></div>%13$s</form>',
			esc_attr( $id ),
			esc_url( Listing_Archive::page_url() ),
			esc_attr__( 'Search listings', 'crc-real-estate' ),
			esc_attr__( 'What you are looking for', 'crc-real-estate' ),
			$tabs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			$category, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
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
	 * Nothing until at least 3 letters are typed.
	 *
	 * @param string $query    What is typed.
	 * @param string $category Category slug, or empty.
	 * @return array[] Each with a 'name' and a 'note', e.g. "Galle District".
	 */
	public static function places( $query, $category = '' ) {
		$key = District::normalize( $query );

		if ( strlen( $key ) < self::MIN_LETTERS ) {
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
