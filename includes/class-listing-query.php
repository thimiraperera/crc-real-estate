<?php
/**
 * What the search asks for.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Overview;
use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * What a search asks for, read from the web address (the category, the place,
 * the price, the land size, the bedrooms and so on, and the order), the
 * choices the search offers for each, and how WordPress is asked for the
 * matching listings.
 */
final class Listing_Query {

	const MAX_PER_PAGE = 48;

	/**
	 * Every filter, empty.
	 *
	 * - category:      category slug.
	 * - location:      place as typed: a town, a district or a Colombo zone.
	 * - district:      district slug, on a district's page such as /district/galle/.
	 * - town:          town slug, on a town's page such as /town/hikkaduwa/.
	 * - type:          property type, e.g. Bare Land.
	 * - price_min:     lowest price, in rupees.
	 * - price_max:     highest price (the rent for rentals), in rupees.
	 * - per_perch_max: highest price per perch, in rupees.
	 * - size_min:      smallest land extent, in perches.
	 * - size_max:      largest land extent, in perches.
	 * - beds:          fewest bedrooms.
	 * - baths:         fewest bathrooms.
	 * - furnishing:    furnished, semi_furnished or unfurnished.
	 * - sort:          order, see sorts().
	 * - page:          page of results, from 1.
	 *
	 * @return array
	 */
	public static function blank() {
		return array(
			'category'      => '',
			'location'      => '',
			'district'      => '',
			'town'          => '',
			'type'          => '',
			'price_min'     => '',
			'price_max'     => '',
			'per_perch_max' => '',
			'size_min'      => '',
			'size_max'      => '',
			'beds'          => 0,
			'baths'         => 0,
			'furnishing'    => '',
			'sort'          => 'newest',
			'page'          => 1,
		);
	}

	/**
	 * Whether a category's listings are homes (bedrooms, no price per perch).
	 *
	 * @param string $category Category slug.
	 * @return bool
	 */
	public static function homes( $category ) {
		return in_array( $category, array( 'properties-for-sale', 'properties-for-rent' ), true );
	}

	/**
	 * The filters that fit a category. With every category, only the place
	 * and the property type do, as prices for sale and for rent don't mix.
	 *
	 * @param string $category Category slug, or empty for every category.
	 * @return string[]
	 */
	public static function fields_for( $category ) {
		if ( '' === $category ) {
			$fields = array( 'location', 'type' );
		} elseif ( self::homes( $category ) ) {
			$fields = array( 'location', 'type', 'price_min', 'price_max', 'beds', 'baths', 'furnishing' );
		} else {
			$fields = array( 'location', 'type', 'price_min', 'price_max', 'per_perch_max', 'size_min', 'size_max' );
		}

		/**
		 * Filters which filters a category's search offers.
		 *
		 * @param string[] $fields   Filter names, see Listing_Query::blank().
		 * @param string   $category Category slug, or empty for every category.
		 */
		return (array) apply_filters( 'crc_re_search_fields', $fields, $category );
	}

	/**
	 * Prices to choose from, in rupees: monthly rents for rentals.
	 *
	 * @param string $category Category slug.
	 * @return int[]
	 */
	public static function prices( $category ) {
		if ( 'properties-for-rent' === $category ) {
			$prices = array( 15000, 25000, 50000, 75000, 100000, 150000, 200000, 300000, 500000, 1000000 );
		} elseif ( 'properties-for-sale' === $category ) {
			$prices = array( 5000000, 10000000, 15000000, 20000000, 30000000, 40000000, 50000000, 75000000, 100000000, 150000000, 200000000, 300000000, 500000000 );
		} else {
			$prices = array( 1000000, 2500000, 5000000, 7500000, 10000000, 15000000, 20000000, 30000000, 50000000, 75000000, 100000000, 200000000 );
		}

		/**
		 * Filters the prices the search offers.
		 *
		 * @param int[]  $prices   Amounts in rupees.
		 * @param string $category Category slug.
		 */
		return array_map( 'intval', (array) apply_filters( 'crc_re_search_prices', $prices, $category ) );
	}

	/**
	 * Prices per perch to choose from, in rupees.
	 *
	 * @return int[]
	 */
	public static function per_perch_prices() {
		/**
		 * Filters the prices per perch the search offers.
		 *
		 * @param int[] $prices Amounts in rupees.
		 */
		return array_map( 'intval', (array) apply_filters( 'crc_re_search_per_perch_prices', array( 100000, 250000, 500000, 750000, 1000000, 1500000, 2000000, 3000000, 5000000, 7500000, 10000000 ) ) );
	}

	/**
	 * Land sizes to choose from, in perches.
	 *
	 * @return int[]
	 */
	public static function sizes() {
		/**
		 * Filters the land sizes the filters offer.
		 *
		 * @param int[] $sizes Sizes in perches.
		 */
		return array_map( 'intval', (array) apply_filters( 'crc_re_search_sizes', array( 5, 10, 15, 20, 30, 40, 60, 80, 120, 160, 320, 800, 1600 ) ) );
	}

	/**
	 * Land size ranges for the search box: "smallest-largest" in perches =>
	 * label. Either end can be left open.
	 *
	 * @return string[]
	 */
	public static function size_ranges() {
		/**
		 * Filters the land size ranges the search box offers.
		 *
		 * @param string[] $ranges "smallest-largest" in perches => label.
		 */
		return (array) apply_filters(
			'crc_re_search_size_ranges',
			array(
				'-10'     => __( 'Up to 10 perches', 'crc-real-estate' ),
				'10-20'   => __( '10 to 20 perches', 'crc-real-estate' ),
				'20-40'   => __( '20 to 40 perches', 'crc-real-estate' ),
				'40-80'   => __( '40 to 80 perches', 'crc-real-estate' ),
				'80-160'  => __( '80 perches to 1 acre', 'crc-real-estate' ),
				'160-800' => __( '1 to 5 acres', 'crc-real-estate' ),
				'800-'    => __( 'More than 5 acres', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * A land size written for people: "20 perches", or acres from one acre up.
	 *
	 * @param int|float $perches Size in perches.
	 * @return string
	 */
	public static function size_label( $perches ) {
		$perches = (float) $perches;

		if ( $perches >= 160 && 0.0 === fmod( $perches, 160 ) ) {
			$acres = (int) ( $perches / 160 );

			/* translators: %s: number of acres. */
			return sprintf( _n( '%s acre', '%s acres', $acres, 'crc-real-estate' ), number_format_i18n( $acres ) );
		}

		/* translators: %s: number of perches. */
		return sprintf( _n( '%s perch', '%s perches', (int) ceil( $perches ), 'crc-real-estate' ), number_format_i18n( $perches, floor( $perches ) === $perches ? 0 : 2 ) );
	}

	/**
	 * Furnishing choices: key => label, as on the listing screen.
	 *
	 * @return string[]
	 */
	public static function furnishings() {
		$items = Overview::common_items();

		return isset( $items['furnishing']['options'] ) ? (array) $items['furnishing']['options'] : array();
	}

	/**
	 * The orders a category's results can have: key => label.
	 *
	 * @param string $category Category slug, or empty.
	 * @return string[]
	 */
	public static function sorts( $category ) {
		$sorts = array(
			'newest'     => __( 'Newest first', 'crc-real-estate' ),
			'price-asc'  => __( 'Price: low to high', 'crc-real-estate' ),
			'price-desc' => __( 'Price: high to low', 'crc-real-estate' ),
			'popular'    => __( 'Most viewed', 'crc-real-estate' ),
		);

		if ( '' !== $category && ! self::homes( $category ) ) {
			$sorts['per-perch-asc'] = __( 'Price per perch: low to high', 'crc-real-estate' );
			$sorts['size-desc']     = __( 'Largest land first', 'crc-real-estate' );
		}

		/**
		 * Filters the orders the results offer.
		 *
		 * @param string[] $sorts    Key => label.
		 * @param string   $category Category slug, or empty.
		 */
		return (array) apply_filters( 'crc_re_search_sorts', $sorts, $category );
	}

	/**
	 * Text from the address, at most so long.
	 *
	 * @param array  $get Address values.
	 * @param string $key Name.
	 * @param int    $max Most characters.
	 * @return string
	 */
	private static function text( array $get, $key, $max = 100 ) {
		if ( ! isset( $get[ $key ] ) || ! is_scalar( $get[ $key ] ) ) {
			return '';
		}

		$value = trim( sanitize_text_field( (string) $get[ $key ] ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * The filters in a web address, such as ?location=Galle&size=10-20. On a
	 * category, district or town page, that page's category, district or town
	 * counts too. Filters that don't fit the category are left out.
	 *
	 * @param array $get     Address values, e.g. $_GET.
	 * @param array $context From the page's own address: 'category', 'district', 'town' and 'page'.
	 * @return array Filters, as blank().
	 */
	public static function read( array $get, array $context = array() ) {
		$get     = wp_unslash( $get );
		$filters = self::blank();

		$category = array_key_exists( 'category', $get ) ? sanitize_title( self::text( $get, 'category' ) ) : ( isset( $context['category'] ) ? (string) $context['category'] : '' );

		$filters['category'] = isset( Taxonomy::terms()[ $category ] ) ? $category : '';
		$filters['district'] = isset( $context['district'] ) && isset( District::districts()[ $context['district'] ] ) ? (string) $context['district'] : '';
		$filters['town']     = isset( $context['town'] ) ? sanitize_title( (string) $context['town'] ) : '';
		$filters['location'] = self::text( $get, 'location', 60 );
		$filters['type']     = self::text( $get, 'type', 60 );

		foreach ( array( 'price_min', 'price_max', 'per_perch_max' ) as $key ) {
			$filters[ $key ] = Price_Card::sanitize_amount( self::text( $get, $key, 20 ) );
		}

		// Land size: "10-20" from the search box, or the filters' smallest and largest.
		if ( preg_match( '/^(\d*\.?\d*)-(\d*\.?\d*)$/', self::text( $get, 'size', 30 ), $range ) ) {
			$filters['size_min'] = Overview::sanitize_number( $range[1] );
			$filters['size_max'] = Overview::sanitize_number( $range[2] );
		}

		foreach ( array( 'size_min', 'size_max' ) as $key ) {
			if ( array_key_exists( $key, $get ) ) {
				$filters[ $key ] = Overview::sanitize_number( self::text( $get, $key, 20 ) );
			}
		}

		$filters['beds']  = min( 10, absint( self::text( $get, 'beds', 3 ) ) );
		$filters['baths'] = min( 10, absint( self::text( $get, 'baths', 3 ) ) );

		$furnishing            = sanitize_key( self::text( $get, 'furnishing', 30 ) );
		$filters['furnishing'] = isset( self::furnishings()[ $furnishing ] ) ? $furnishing : '';

		$sort            = sanitize_key( self::text( $get, 'sort', 30 ) );
		$filters['sort'] = isset( self::sorts( $filters['category'] )[ $sort ] ) ? $sort : 'newest';

		$page            = absint( self::text( $get, 'pg', 6 ) );
		$filters['page'] = max( 1, $page ? $page : ( isset( $context['page'] ) ? (int) $context['page'] : 1 ) );

		$fields = self::fields_for( $filters['category'] );
		$blank  = self::blank();

		foreach ( array( 'type', 'price_min', 'price_max', 'per_perch_max', 'size_min', 'size_max', 'beds', 'baths', 'furnishing' ) as $key ) {
			if ( ! in_array( $key, $fields, true ) ) {
				$filters[ $key ] = $blank[ $key ];
			}
		}

		// Smallest and largest typed the wrong way round.
		foreach ( array( array( 'price_min', 'price_max' ), array( 'size_min', 'size_max' ) ) as $pair ) {
			if ( '' !== $filters[ $pair[0] ] && '' !== $filters[ $pair[1] ] && (float) $filters[ $pair[0] ] > (float) $filters[ $pair[1] ] ) {
				$low                  = $filters[ $pair[1] ];
				$filters[ $pair[1] ] = $filters[ $pair[0] ];
				$filters[ $pair[0] ] = $low;
			}
		}

		return $filters;
	}

	/**
	 * The address values for filters, without empty ones: the category is
	 * left out, as it is in the address itself (/listings/lands/).
	 *
	 * @param array    $filters Filters, as blank().
	 * @param string[] $skip    Filter names to leave out.
	 * @return string[] Name => value.
	 */
	public static function params( array $filters, array $skip = array() ) {
		$params = array();
		$blank  = self::blank();

		foreach ( array( 'location', 'type', 'price_min', 'price_max', 'per_perch_max', 'size_min', 'size_max', 'beds', 'baths', 'furnishing', 'sort', 'page' ) as $key ) {
			if ( in_array( $key, $skip, true ) || ! isset( $filters[ $key ] ) || $blank[ $key ] === $filters[ $key ] || '' === (string) $filters[ $key ] ) {
				continue;
			}

			$params[ 'page' === $key ? 'pg' : $key ] = (string) $filters[ $key ];
		}

		return $params;
	}

	/**
	 * The towns and districts a place as typed means, for the query: exact
	 * names first (a town, a Colombo zone or the area it is known by, a
	 * district), then names that have what was typed in them. A place that
	 * matches none is looked for in the listings' titles.
	 *
	 * @param string $text Place as typed.
	 * @return array 'clause' (tax query, or null), 'search' (text for the titles), 'towns' and 'districts'.
	 */
	public static function place( $text ) {
		$text   = trim( (string) $text );
		$result = array(
			'clause'    => null,
			'search'    => '',
			'towns'     => array(),
			'districts' => array(),
		);

		if ( '' === $text ) {
			return $result;
		}

		$towns     = array_keys( Town::matching( $text ) );
		$district  = District::find( $text );
		$districts = '' !== $district ? array( $district ) : array();

		// Part of a name, such as "Hikka", from three letters on.
		if ( ! $towns && ! $districts && strlen( District::normalize( $text ) ) >= 3 ) {
			foreach ( Town::search( $text, 50, true ) as $term ) {
				$towns[] = (int) $term->term_id;
			}

			$districts = District::search( $text );
		}

		if ( ! $towns && ! $districts ) {
			$result['search'] = $text;
			return $result;
		}

		$clause = array( 'relation' => 'OR' );

		if ( $towns ) {
			$clause[] = array(
				'taxonomy' => Town::NAME,
				'field'    => 'term_id',
				'terms'    => array_values( array_unique( array_map( 'intval', $towns ) ) ),
			);
		}

		if ( $districts ) {
			$clause[] = array(
				'taxonomy' => District::NAME,
				'field'    => 'slug',
				'terms'    => array_values( array_unique( $districts ) ),
			);
		}

		$result['clause']    = $clause;
		$result['towns']     = array_values( array_unique( array_map( 'intval', $towns ) ) );
		$result['districts'] = array_values( array_unique( $districts ) );

		return $result;
	}

	/**
	 * What to ask WordPress for: published listings matching the filters,
	 * in the chosen order, one page of them.
	 *
	 * @param array $filters  Filters, as blank().
	 * @param int   $per_page Listings a page.
	 * @return array WP_Query arguments.
	 */
	public static function args( array $filters, $per_page ) {
		$filters = array_merge( self::blank(), $filters );
		$args    = array(
			'post_type'           => Post_Type::NAME,
			'post_status'         => 'publish',
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'posts_per_page'      => max( 1, min( self::MAX_PER_PAGE, (int) $per_page ) ),
			'paged'               => max( 1, (int) $filters['page'] ),
			'orderby'             => 'date',
			'order'               => 'DESC',
		);
		$tax     = array();
		$meta    = array();

		foreach ( array(
			'category' => Taxonomy::NAME,
			'district' => District::NAME,
			'town'     => Town::NAME,
		) as $key => $taxonomy ) {
			if ( '' !== $filters[ $key ] ) {
				$tax[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( $filters[ $key ] ),
				);
			}
		}

		if ( '' !== $filters['location'] ) {
			$place = self::place( $filters['location'] );

			if ( $place['clause'] ) {
				$tax[] = $place['clause'];
			} else {
				// Looked for in the titles, without making the page a search page.
				$args['crc_title'] = $place['search'];
			}
		}

		$number = function ( $key, $value, $compare, $type = 'NUMERIC' ) use ( &$meta ) {
			$meta[] = array(
				'key'     => $key,
				'value'   => $value,
				'compare' => $compare,
				'type'    => $type,
			);
		};

		if ( '' !== $filters['type'] ) {
			$meta[] = array(
				'key'   => '_crc_property_type',
				'value' => $filters['type'],
			);
		}

		if ( '' !== $filters['price_min'] ) {
			$number( Price_Card::PRICE_META, $filters['price_min'], '>=' );
		}

		if ( '' !== $filters['price_max'] ) {
			$number( Price_Card::PRICE_META, $filters['price_max'], '<=' );
		}

		if ( '' !== $filters['per_perch_max'] ) {
			$number( Listing_Index::PER_PERCH, $filters['per_perch_max'], '<=' );
		}

		if ( '' !== $filters['size_min'] ) {
			$number( Listing_Index::PERCHES, $filters['size_min'], '>=', 'DECIMAL(12,2)' );
		}

		if ( '' !== $filters['size_max'] ) {
			$number( Listing_Index::PERCHES, $filters['size_max'], '<=', 'DECIMAL(12,2)' );
		}

		if ( $filters['beds'] ) {
			$number( '_crc_bedrooms', (int) $filters['beds'], '>=' );
		}

		if ( $filters['baths'] ) {
			$number( '_crc_bathrooms', (int) $filters['baths'], '>=' );
		}

		if ( '' !== $filters['furnishing'] ) {
			$meta[] = array(
				'key'   => '_crc_furnishing',
				'value' => $filters['furnishing'],
			);
		}

		if ( $tax ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- One page of results.
		}

		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- One page of results.
		}

		$orders = array(
			'price-asc'     => array( Price_Card::PRICE_META, 'ASC' ),
			'price-desc'    => array( Price_Card::PRICE_META, 'DESC' ),
			'popular'       => array( Views::META, 'DESC' ),
			'per-perch-asc' => array( Listing_Index::PER_PERCH, 'ASC' ),
			'size-desc'     => array( Listing_Index::PERCHES, 'DESC' ),
		);

		if ( isset( $orders[ $filters['sort'] ] ) ) {
			$args['crc_order'] = array(
				'key'   => $orders[ $filters['sort'] ][0],
				'order' => $orders[ $filters['sort'] ][1],
			);
		}

		/**
		 * Filters what the search asks WordPress for.
		 *
		 * @param array $args    WP_Query arguments.
		 * @param array $filters Filters, see Listing_Query::blank().
		 */
		return (array) apply_filters( 'crc_re_search_query_args', $args, $filters );
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'posts_clauses', array( __CLASS__, 'clauses' ), 10, 2 );
	}

	/**
	 * The search's own parts of a query: a place looked for in the titles,
	 * and an order by a number, with listings that don't have it last
	 * whichever way round, then newest first.
	 *
	 * @param string[]  $clauses Query clauses.
	 * @param \WP_Query $query   Query.
	 * @return string[]
	 */
	public static function clauses( $clauses, $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return $clauses;
		}

		global $wpdb;

		$title = $query->get( 'crc_title' );
		$order = $query->get( 'crc_order' );

		if ( is_string( $title ) && '' !== $title ) {
			$clauses['where'] = ( isset( $clauses['where'] ) ? $clauses['where'] : '' ) . $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $title ) . '%' );
		}

		if ( is_array( $order ) && ! empty( $order['key'] ) ) {
			$direction          = isset( $order['order'] ) && 'DESC' === strtoupper( (string) $order['order'] ) ? 'DESC' : 'ASC';
			$clauses['join']    = ( isset( $clauses['join'] ) ? $clauses['join'] : '' ) . $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS crc_sort ON ( crc_sort.post_id = {$wpdb->posts}.ID AND crc_sort.meta_key = %s )", (string) $order['key'] );
			$clauses['orderby'] = "( crc_sort.meta_value IS NULL OR crc_sort.meta_value = '' ) ASC, CAST( crc_sort.meta_value AS DECIMAL(20,2) ) {$direction}, {$wpdb->posts}.post_date DESC";
		}

		return $clauses;
	}
}
