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
 * Search button, and under it the category's own filters as rounded boxes
 * to choose from or type in. Search opens the category's archive (such as
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
				'description' => __( 'A search for any page: a tab for each listing category, named as the categories are, a place box that suggests towns and districts with listings from three letters, and a Search button in the site\'s button style. Under it are the chosen category\'s own choices as rounded boxes: land size, the highest price per perch and the property type for land; bedrooms, the highest price or rent and the property type for homes. Each opens a list to choose from, and people can also type in it: an amount such as 25,000,000 or 25m, a size such as 25 perches, or bedrooms such as 2-4. On phones the categories are a dropdown and the Search button takes the whole width. Search opens the category\'s archive, such as /listings/lands/, with those choices made.', 'crc-real-estate' ),
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
						'description' => __( 'no to leave out the rounded boxes under the search.', 'crc-real-estate' ),
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
				'check'   => Icons::svg( 'check', 'crc-dropdown-option-icon' ),
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
				'<li class="crc-dropdown-option%1$s" id="%2$s" role="option" aria-selected="%3$s" data-value="%4$s" data-index="%5$d"><span class="crc-dropdown-option-text">%6$s</span>%7$s</li>',
				$on ? ' is-selected' : '',
				esc_attr( $id . '-' . $i ),
				$on ? 'true' : 'false',
				esc_attr( (string) $value ),
				$i,
				esc_html( $text ),
				Icons::svg( 'check', 'crc-dropdown-option-icon' )
			);
			++$i;
		}

		return $html;
	}

	/**
	 * What a box to type in shows for a choice that isn't in its list, such
	 * as an amount someone typed: "Up to Rs. 25,000,000", "From 25 perches"
	 * or "2 – 4 bedrooms". search.js writes them the same way.
	 *
	 * @param string   $kind   money, size, beds or text.
	 * @param string[] $values The field values: one, or the least and the most.
	 * @param string[] $texts  Words, as in combo().
	 * @return string
	 */
	private static function combo_text( $kind, array $values, array $texts ) {
		$least = isset( $values[0] ) ? (string) $values[0] : '';
		$most  = isset( $values[1] ) ? (string) $values[1] : '';

		if ( 'money' === $kind ) {
			return '' !== $least ? sprintf( $texts['upTo'], Price_Card::money( $least ) ) : '';
		}

		if ( 'size' === $kind ) {
			if ( '' !== $least && '' !== $most ) {
				return sprintf( $texts['between'], Listing_Query::size_label( $least ), Listing_Query::size_label( $most ) );
			}

			return '' !== $most ? sprintf( $texts['upTo'], Listing_Query::size_label( $most ) ) : sprintf( $texts['from'], Listing_Query::size_label( $least ) );
		}

		if ( 'beds' === $kind ) {
			$rooms = function ( $number ) {
				/* translators: %s: number of bedrooms. */
				return sprintf( _n( '%s bedroom', '%s bedrooms', (int) $number, 'crc-real-estate' ), number_format_i18n( (int) $number ) );
			};

			if ( '' !== $least && '' !== $most ) {
				return $least === $most ? $rooms( $least ) : sprintf( $texts['between'], $least, $most );
			}

			return '' !== $most ? sprintf( $texts['upTo'], $rooms( $most ) ) : sprintf( $texts['from'], $least );
		}

		return $least;
	}

	/**
	 * A rounded box to type in, with a list to choose from under it. A click
	 * empties the box for typing, with a hint such as "Type an amount, e.g.
	 * 25m" and the blinking cursor: a number is offered as a choice of its
	 * own (an amount, a size or a number of bedrooms), and other words narrow
	 * the list down. What is chosen shows in the box.
	 *
	 * @param string   $id      Start of the IDs.
	 * @param string   $label   What it is, shown until something is chosen.
	 * @param string   $kind    money, size, beds or text (text only narrows the list down).
	 * @param string[] $names   Field names: one, or the least's and the most's.
	 * @param string[] $values  Values chosen now, as $names.
	 * @param array    $options Value => text, "Any" first with an empty value. With two fields a value is "least-most".
	 * @param string[] $texts   Words for what is typed, with %s where the amount, size or number goes,
	 *                          and 'hint', shown while the box is typed in.
	 * @return string
	 */
	private static function combo( $id, $label, $kind, array $names, array $values, array $options, array $texts = array() ) {
		$values = array_map( 'strval', array_values( $values ) );
		$list   = $id . '-list';
		$key    = 1 === count( $names ) ? $values[0] : ( '' === $values[0] && '' === $values[1] ? '' : $values[0] . '-' . $values[1] );
		$text   = '' === $key ? '' : ( isset( $options[ $key ] ) ? (string) $options[ $key ] : self::combo_text( $kind, $values, $texts ) );
		$texts += array(
			'hint'     => __( 'Type or choose', 'crc-real-estate' ),
			'none'     => __( 'No matches', 'crc-real-estate' ),
			'currency' => Price_Card::currency(),
		);
		$fields = '';
		$ids    = array();

		foreach ( array_values( $names ) as $i => $name ) {
			$ids[]   = $id . '-' . $i;
			$fields .= sprintf( '<input type="hidden" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id . '-' . $i ), esc_attr( $name ), esc_attr( isset( $values[ $i ] ) ? $values[ $i ] : '' ) );
		}

		return sprintf(
			'<div class="crc-dropdown crc-combo" data-crc-combo data-kind="%1$s" data-fields="%2$s" data-texts="%3$s"><div class="crc-pill crc-combo-pill%4$s"><input type="text" class="crc-combo-input" id="%5$s" value="%6$s" placeholder="%7$s" aria-label="%7$s" role="combobox" aria-expanded="false" aria-controls="%8$s" aria-autocomplete="list" autocomplete="off" autocapitalize="off" spellcheck="false" data-value="%9$s" data-hint="%13$s">%10$s</div><ul class="crc-dropdown-list" id="%8$s" role="listbox" aria-label="%7$s" hidden>%11$s</ul>%12$s</div>',
			esc_attr( $kind ),
			esc_attr( wp_json_encode( $ids ) ),
			esc_attr( wp_json_encode( $texts ) ),
			'' !== $text ? ' has-value' : '',
			esc_attr( $id ),
			esc_attr( $text ),
			esc_attr( $label ),
			esc_attr( $list ),
			esc_attr( $key ),
			Icons::svg( 'chevron-down', 'crc-pill-icon' ),
			self::list_options( $list, $options, $key ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped there.
			$fields, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr( $texts['hint'] )
		);
	}

	/**
	 * A category's choices as rounded boxes with lists: land size, the
	 * highest price per perch and the property type for land; bedrooms, the
	 * highest price or rent and the property type for homes. People can
	 * choose from the list or type their own.
	 *
	 * @param string $category Category slug.
	 * @param array  $filters  Filters chosen now.
	 * @param string $id       Start of the IDs.
	 * @return string
	 */
	public static function pills( $category, array $filters, $id = 'crc-search' ) {
		$fields   = Listing_Query::fields_for( $category );
		$filters += Listing_Query::blank();
		$html     = '';
		/* translators: %s: amount, e.g. "Rs. 5,000,000", size, e.g. "40 perches", or bedrooms, e.g. "4 bedrooms". */
		$up_to = __( 'Up to %s', 'crc-real-estate' );

		if ( in_array( 'size_min', $fields, true ) ) {
			$html .= self::combo(
				$id . '-size',
				__( 'Land size', 'crc-real-estate' ),
				'size',
				array( 'size_min', 'size_max' ),
				array( $filters['size_min'], $filters['size_max'] ),
				array( '' => __( 'Any size', 'crc-real-estate' ) ) + Listing_Query::size_ranges(),
				array(
					'hint'    => __( 'Type perches, e.g. 20', 'crc-real-estate' ),
					'upTo'    => $up_to,
					/* translators: %s: size, e.g. "20 perches". */
					'from'    => __( 'From %s', 'crc-real-estate' ),
					/* translators: 1: smallest size, 2: largest size, e.g. "10 perches – 1 acre". */
					'between' => __( '%1$s – %2$s', 'crc-real-estate' ),
					/* translators: %s: 1. */
					'one'     => __( '%s perch', 'crc-real-estate' ),
					/* translators: %s: number of perches. */
					'many'    => __( '%s perches', 'crc-real-estate' ),
					/* translators: %s: 1. */
					'acre'    => __( '%s acre', 'crc-real-estate' ),
					/* translators: %s: number of acres. */
					'acres'   => __( '%s acres', 'crc-real-estate' ),
				)
			);
		}

		if ( in_array( 'beds', $fields, true ) ) {
			$beds = array( '' => __( 'Any number', 'crc-real-estate' ) );

			foreach ( range( 1, 5 ) as $number ) {
				/* translators: %s: number of bedrooms; "3+" means 3 or more. */
				$beds[ $number . '-' ] = sprintf( _n( '%s+ bedroom', '%s+ bedrooms', $number, 'crc-real-estate' ), number_format_i18n( $number ) );
			}

			$html .= self::combo(
				$id . '-beds',
				__( 'Bedrooms', 'crc-real-estate' ),
				'beds',
				array( 'beds', 'beds_max' ),
				array( $filters['beds'] ? $filters['beds'] : '', $filters['beds_max'] ? $filters['beds_max'] : '' ),
				$beds,
				array(
					'hint'    => __( 'Type a number, e.g. 2-4', 'crc-real-estate' ),
					'upTo'    => $up_to,
					/* translators: %s: number of bedrooms; "2+ bedrooms" means 2 or more. */
					'from'    => __( '%s+ bedrooms', 'crc-real-estate' ),
					/* translators: 1: fewest bedrooms, 2: most bedrooms. */
					'between' => __( '%1$s – %2$s bedrooms', 'crc-real-estate' ),
					/* translators: %s: 1. */
					'one'     => __( '%s bedroom', 'crc-real-estate' ),
					/* translators: %s: number of bedrooms. */
					'many'    => __( '%s bedrooms', 'crc-real-estate' ),
				)
			);
		}

		if ( in_array( 'per_perch_max', $fields, true ) ) {
			$html .= self::combo(
				$id . '-per-perch',
				__( 'Max price per perch', 'crc-real-estate' ),
				'money',
				array( 'per_perch_max' ),
				array( $filters['per_perch_max'] ),
				array( '' => __( 'Any price', 'crc-real-estate' ) ) + self::money_options( Listing_Query::per_perch_prices(), $up_to ),
				array(
					'hint' => __( 'Type an amount, e.g. 5 lakhs', 'crc-real-estate' ),
					'upTo' => $up_to,
				)
			);
		} elseif ( in_array( 'price_max', $fields, true ) ) {
			$rent  = 'properties-for-rent' === $category;
			$html .= self::combo(
				$id . '-price',
				$rent ? __( 'Max rent per month', 'crc-real-estate' ) : __( 'Max price', 'crc-real-estate' ),
				'money',
				array( 'price_max' ),
				array( $filters['price_max'] ),
				array( '' => $rent ? __( 'Any rent', 'crc-real-estate' ) : __( 'Any price', 'crc-real-estate' ) ) + self::money_options( Listing_Query::prices( $category ), $up_to ),
				array(
					'hint' => $rent ? __( 'Type an amount, e.g. 75,000', 'crc-real-estate' ) : __( 'Type an amount, e.g. 25m', 'crc-real-estate' ),
					'upTo' => $up_to,
				)
			);
		}

		if ( in_array( 'type', $fields, true ) ) {
			$types = self::property_types( $category );

			if ( '' !== $filters['type'] && ! in_array( $filters['type'], $types, true ) ) {
				$types[] = $filters['type'];
			}

			if ( $types ) {
				$html .= self::combo(
					$id . '-type',
					__( 'Property type', 'crc-real-estate' ),
					'text',
					array( 'type' ),
					array( $filters['type'] ),
					array( '' => __( 'Any type', 'crc-real-estate' ) ) + array_combine( $types, $types ),
					array( 'hint' => __( 'Type to search, e.g. House', 'crc-real-estate' ) )
				);
			}
		}

		return '' === $html ? '' : '<div class="crc-pills">' . $html . '</div>';
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
