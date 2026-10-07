<?php
/**
 * What the search asks for.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Features;
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
	 * The most parking spaces the filters ask for: 1+, 2+ or 3+.
	 */
	const MOST_PARKING = 3;

	/**
	 * The filters under More filters, in the order they go in the address.
	 */
	const MORE = array( 'negotiable', 'bank_loan', 'bills', 'advance_max', 'floor_min', 'floor_max', 'parking', 'road_type', 'road_width', 'electricity', 'water', 'features', 'availability', 'listed_by', 'posted' );

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
	 * - beds_max:      most bedrooms.
	 * - baths:         fewest bathrooms.
	 * - furnishing:    furnished, semi_furnished or unfurnished.
	 * - negotiable:    '1' for a price that can be talked down.
	 * - bank_loan:     '1' for homes a bank loan is available for.
	 * - bills:         '1' for rents with the utility bills included.
	 * - advance_max:   most months of rent paid in advance.
	 * - floor_min:     smallest floor area, in square feet.
	 * - floor_max:     largest floor area, in square feet.
	 * - parking:       fewest parking spaces.
	 * - road_type:     approach road, e.g. carpeted; see more_choices().
	 * - road_width:    narrowest approach road, in feet.
	 * - electricity:   available (three-phase too) or three_phase.
	 * - water:         pipe_borne or well (either counts pipe-borne and well).
	 * - features:      ready-made features every listing must have, e.g. "clear_deed,garden".
	 * - availability:  e.g. available-now; see more_choices().
	 * - listed_by:     e.g. owner; see more_choices().
	 * - posted:        listed in the last so many days.
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
			'beds_max'      => 0,
			'baths'         => 0,
			'furnishing'    => '',
			'negotiable'    => '',
			'bank_loan'     => '',
			'bills'         => '',
			'advance_max'   => 0,
			'floor_min'     => 0,
			'floor_max'     => 0,
			'parking'       => 0,
			'road_type'     => '',
			'road_width'    => 0,
			'electricity'   => '',
			'water'         => '',
			'features'      => '',
			'availability'  => '',
			'listed_by'     => '',
			'posted'        => 0,
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
	 * The filters that fit a category. With every category, only the place,
	 * the property type and the More filters every listing has do, as prices
	 * for sale and for rent don't mix.
	 *
	 * @param string $category Category slug, or empty for every category.
	 * @return string[]
	 */
	public static function fields_for( $category ) {
		$more = array( 'negotiable', 'road_type', 'road_width', 'electricity', 'water', 'features', 'availability', 'listed_by', 'posted' );

		if ( '' === $category ) {
			$fields = array_merge( array( 'location', 'type' ), $more );
		} elseif ( self::homes( $category ) ) {
			$fields = array_merge(
				array( 'location', 'type', 'price_min', 'price_max', 'beds', 'beds_max', 'baths', 'furnishing' ),
				'properties-for-rent' === $category ? array( 'bills', 'advance_max' ) : array( 'bank_loan' ),
				array( 'floor_min', 'floor_max', 'parking' ),
				$more
			);
		} else {
			$fields = array_merge( array( 'location', 'type', 'price_min', 'price_max', 'per_perch_max', 'size_min', 'size_max' ), $more );
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
	 * Round amounts, smallest first: 1,000, 1,500, 2,000, 2,500, 3,000,
	 * 4,000, 5,000, 6,000, 7,500, 10,000 and so on up to 10,000,000,000.
	 *
	 * @return int[]
	 */
	public static function round_amounts() {
		$amounts = array();

		for ( $power = 3; $power <= 9; $power++ ) {
			foreach ( array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 7.5 ) as $times ) {
				$amounts[] = (int) round( $times * pow( 10, $power ) );
			}
		}

		$amounts[] = 10000000000;

		return $amounts;
	}

	/**
	 * The steps of a category's price slider: 0 (no lowest), then round
	 * amounts from about the cheapest listing's price to about the dearest's.
	 * The last step means no highest.
	 *
	 * @param string $category Category slug.
	 * @return int[]
	 */
	public static function price_steps( $category ) {
		$prices = Listing_Index::facets( $category )['prices'];
		$low    = (int) $prices['min'];
		$high   = (int) $prices['max'];

		// Without prices yet, the usual range for the category.
		if ( $high <= 0 ) {
			$usual = self::prices( $category );
			$low   = (int) reset( $usual );
			$high  = (int) end( $usual );
		}

		$round = self::round_amounts();
		$from  = 0;
		$to    = count( $round ) - 1;

		foreach ( $round as $i => $amount ) {
			if ( $amount <= max( 1, $low ) ) {
				$from = $i;
			}

			if ( $amount >= $high ) {
				$to = $i;
				break;
			}
		}

		// At least 8 steps, so the slider moves in small enough jumps.
		$from = max( 0, min( $from, $to - 7 ) );

		/**
		 * Filters the steps of a category's price slider.
		 *
		 * @param int[]  $steps    Amounts in rupees, 0 first.
		 * @param string $category Category slug.
		 */
		return array_map( 'intval', (array) apply_filters( 'crc_re_search_price_steps', array_merge( array( 0 ), array_slice( $round, $from, $to - $from + 1 ) ), $category ) );
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
	 * Where a ready-made detail, such as road_type, is kept for each listing.
	 *
	 * @param string $name Detail name.
	 * @return string Meta key.
	 */
	public static function meta_of( $name ) {
		$items = Overview::common_items();

		return isset( $items[ $name ]['meta'] ) ? (string) $items[ $name ]['meta'] : '_crc_' . $name;
	}

	/**
	 * The choices of More filters that are lists: filter name => value => label.
	 * Floor areas are for both the smallest and the largest.
	 *
	 * @return array[]
	 */
	public static function more_choices() {
		$items  = Overview::common_items();
		$fields = Overview::fields();
		$option = function ( $item, $key, $fallback ) use ( $items ) {
			return isset( $items[ $item ]['options'][ $key ] ) ? (string) $items[ $item ]['options'][ $key ] : $fallback;
		};
		$lists  = array(
			'availability' => array(),
			'listed_by'    => array(),
		);

		// Availability is kept as its label ("Available Now"), and Listed by as typed ("Owner").
		foreach ( array(
			'availability' => 'options',
			'listed_by'    => 'suggestions',
		) as $name => $from ) {
			if ( isset( $fields[ $name ][ $from ] ) ) {
				foreach ( array_values( (array) $fields[ $name ][ $from ] ) as $i => $label ) {
					$slug = sanitize_title( (string) $label );

					// Letters an address can't carry plainly, e.g. in Sinhala, make a number instead.
					if ( '' === $slug || false !== strpos( $slug, '%' ) ) {
						$slug = (string) ( $i + 1 );
					}

					$lists[ $name ][ $slug ] = (string) $label;
				}
			}
		}

		$widths = array();

		foreach ( array( 8, 10, 12, 15, 20 ) as $feet ) {
			/* translators: %s: road width in feet. */
			$widths[ $feet ] = sprintf( __( 'At least %s ft', 'crc-real-estate' ), number_format_i18n( $feet ) );
		}

		$floors = array();

		foreach ( array( 500, 750, 1000, 1250, 1500, 2000, 2500, 3000, 4000, 5000 ) as $feet ) {
			/* translators: %s: floor area in square feet. */
			$floors[ $feet ] = sprintf( __( '%s sq ft', 'crc-real-estate' ), number_format_i18n( $feet ) );
		}

		$advances = array();

		foreach ( array( 1, 2, 3, 6, 12 ) as $months ) {
			/* translators: %s: number of months. */
			$advances[ $months ] = sprintf( _n( 'Up to %s month', 'Up to %s months', $months, 'crc-real-estate' ), number_format_i18n( $months ) );
		}

		/**
		 * Filters the choices of More filters that are lists.
		 *
		 * @param array[] $choices Filter name => value => label: availability,
		 *                         listed_by, posted (days), road_type,
		 *                         road_width (feet), electricity, water,
		 *                         floor (square feet) and advance_max (months).
		 */
		return (array) apply_filters(
			'crc_re_search_more_choices',
			array(
				'availability' => $lists['availability'],
				'listed_by'    => $lists['listed_by'],
				'posted'       => array(
					1  => __( 'In the last 24 hours', 'crc-real-estate' ),
					3  => __( 'In the last 3 days', 'crc-real-estate' ),
					7  => __( 'In the last week', 'crc-real-estate' ),
					30 => __( 'In the last month', 'crc-real-estate' ),
					90 => __( 'In the last 3 months', 'crc-real-estate' ),
				),
				'road_type'    => isset( $items['road_type']['options'] ) ? array_map( 'strval', (array) $items['road_type']['options'] ) : array(),
				'road_width'   => $widths,
				'electricity'  => array(
					'available'   => $option( 'electricity', 'available', __( 'Available', 'crc-real-estate' ) ),
					'three_phase' => $option( 'electricity', 'three_phase', __( 'Three-phase', 'crc-real-estate' ) ),
				),
				'water'        => array(
					'pipe_borne' => $option( 'water_supply', 'pipe_borne', __( 'Pipe-borne', 'crc-real-estate' ) ),
					'well'       => $option( 'water_supply', 'well', __( 'Well', 'crc-real-estate' ) ),
				),
				'floor'        => $floors,
				'advance_max'  => $advances,
			)
		);
	}

	/**
	 * The groups of ready-made features that fit a category, e.g. Legal and
	 * documents, Home features and Nearby: name => 'title' and 'features'
	 * (name => label). With every category, only the groups every category has.
	 *
	 * @param string $category Category slug, or empty for every category.
	 * @return array[]
	 */
	public static function feature_groups( $category ) {
		$groups = array();

		foreach ( Features::common_groups() as $name => $group ) {
			$for = (array) $group['categories'];

			if ( $group['features'] && ( ! $for || ( '' !== $category && in_array( $category, $for, true ) ) ) ) {
				$groups[ $name ] = array(
					'title'    => (string) $group['title'],
					'features' => $group['features'],
				);
			}
		}

		return $groups;
	}

	/**
	 * Every ready-made feature that fits a category once: name => label.
	 *
	 * @param string $category Category slug, or empty for every category.
	 * @return string[]
	 */
	public static function features_for( $category ) {
		$features = array();

		foreach ( self::feature_groups( $category ) as $group ) {
			$features += $group['features'];
		}

		return $features;
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
			'oldest'     => __( 'Oldest first', 'crc-real-estate' ),
			'price-asc'  => __( 'Price: low to high', 'crc-real-estate' ),
			'price-desc' => __( 'Price: high to low', 'crc-real-estate' ),
		);

		if ( self::homes( $category ) ) {
			$sorts['beds-desc']  = __( 'Most bedrooms', 'crc-real-estate' );
			$sorts['baths-desc'] = __( 'Most bathrooms', 'crc-real-estate' );
			$sorts['area-desc']  = __( 'Largest floor area', 'crc-real-estate' );
			$sorts['size-desc']  = __( 'Largest land first', 'crc-real-estate' );
		} elseif ( '' !== $category ) {
			$sorts['per-perch-asc']  = __( 'Price per perch: low to high', 'crc-real-estate' );
			$sorts['per-perch-desc'] = __( 'Price per perch: high to low', 'crc-real-estate' );
			$sorts['size-desc']      = __( 'Largest land first', 'crc-real-estate' );
			$sorts['size-asc']       = __( 'Smallest land first', 'crc-real-estate' );
		}

		$sorts['popular']   = __( 'Most viewed', 'crc-real-estate' );
		$sorts['updated']   = __( 'Recently updated', 'crc-real-estate' );
		$sorts['title-asc'] = __( 'Name: A to Z', 'crc-real-estate' );

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

		$filters['beds']     = min( 10, absint( self::text( $get, 'beds', 3 ) ) );
		$filters['beds_max'] = min( 10, absint( self::text( $get, 'beds_max', 3 ) ) );
		$filters['baths']    = min( 10, absint( self::text( $get, 'baths', 3 ) ) );

		$furnishing            = sanitize_key( self::text( $get, 'furnishing', 30 ) );
		$filters['furnishing'] = isset( self::furnishings()[ $furnishing ] ) ? $furnishing : '';

		// More filters: ticks, choices from their lists, and numbers.
		foreach ( array( 'negotiable', 'bank_loan', 'bills' ) as $key ) {
			$filters[ $key ] = self::text( $get, $key, 5 ) ? '1' : '';
		}

		$choices = self::more_choices();

		foreach ( array( 'road_type', 'electricity', 'water', 'availability', 'listed_by' ) as $key ) {
			$value           = self::text( $get, $key, 60 );
			$filters[ $key ] = '' !== $value && isset( $choices[ $key ] ) && array_key_exists( $value, (array) $choices[ $key ] ) ? $value : '';
		}

		foreach ( array( 'advance_max', 'road_width', 'posted' ) as $key ) {
			$value           = absint( self::text( $get, $key, 6 ) );
			$filters[ $key ] = $value && isset( $choices[ $key ] ) && array_key_exists( $value, (array) $choices[ $key ] ) ? $value : 0;
		}

		foreach ( array( 'floor_min', 'floor_max' ) as $key ) {
			$value           = absint( self::text( $get, $key, 7 ) );
			$filters[ $key ] = $value && isset( $choices['floor'] ) && array_key_exists( $value, (array) $choices['floor'] ) ? $value : 0;
		}

		$filters['parking']  = min( self::MOST_PARKING, absint( self::text( $get, 'parking', 3 ) ) );
		$filters['features'] = self::features_in( $get, $filters['category'] );

		$sort            = sanitize_key( self::text( $get, 'sort', 30 ) );
		$filters['sort'] = isset( self::sorts( $filters['category'] )[ $sort ] ) ? $sort : 'newest';

		$page            = absint( self::text( $get, 'pg', 6 ) );
		$filters['page'] = max( 1, $page ? $page : ( isset( $context['page'] ) ? (int) $context['page'] : 1 ) );

		$fields = self::fields_for( $filters['category'] );
		$blank  = self::blank();

		foreach ( array_merge( array( 'type', 'price_min', 'price_max', 'per_perch_max', 'size_min', 'size_max', 'beds', 'beds_max', 'baths', 'furnishing' ), self::MORE ) as $key ) {
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

		foreach ( array( array( 'beds', 'beds_max' ), array( 'floor_min', 'floor_max' ) ) as $pair ) {
			if ( $filters[ $pair[0] ] && $filters[ $pair[1] ] && $filters[ $pair[0] ] > $filters[ $pair[1] ] ) {
				$low                  = $filters[ $pair[1] ];
				$filters[ $pair[1] ] = $filters[ $pair[0] ];
				$filters[ $pair[0] ] = $low;
			}
		}

		return $filters;
	}

	/**
	 * The ready-made features asked for that fit the category, in the order
	 * of their groups, so the same ticks always make the same address:
	 * "clear_deed,garden". They come as features=clear_deed,garden, or as
	 * features[]=… from the filters without their script.
	 *
	 * @param array  $get      Address values.
	 * @param string $category Category slug, or empty.
	 * @return string Names separated by commas.
	 */
	private static function features_in( array $get, $category ) {
		$value = isset( $get['features'] ) ? $get['features'] : '';

		if ( is_array( $value ) ) {
			$names = array_slice( $value, 0, 100 );
		} elseif ( is_scalar( $value ) ) {
			$names = explode( ',', substr( (string) $value, 0, 2000 ) );
		} else {
			$names = array();
		}

		$asked = array();

		foreach ( $names as $name ) {
			if ( is_scalar( $name ) ) {
				$asked[ sanitize_key( (string) $name ) ] = true;
			}
		}

		return implode( ',', array_keys( array_intersect_key( self::features_for( $category ), $asked ) ) );
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

		foreach ( array_merge( array( 'location', 'type', 'price_min', 'price_max', 'per_perch_max', 'size_min', 'size_max', 'beds', 'beds_max', 'baths', 'furnishing' ), self::MORE, array( 'sort', 'page' ) ) as $key ) {
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

		// What was typed, understood: places, and filters not chosen otherwise that fit the category; the other words are keywords.
		if ( '' !== $filters['location'] ) {
			$text   = Listing_Text::read( $filters['location'], $filters['category'] );
			$fields = self::fields_for( $filters['category'] );

			if ( $text['clause'] ) {
				$tax[] = $text['clause'];
			}

			foreach ( array( 'type', 'price_min', 'price_max', 'size_min', 'size_max', 'furnishing' ) as $key ) {
				if ( '' === (string) $filters[ $key ] && '' !== (string) $text[ $key ] && in_array( $key, $fields, true ) ) {
					$filters[ $key ] = $text[ $key ];
				}
			}

			foreach ( array( 'beds', 'beds_max' ) as $key ) {
				if ( ! $filters[ $key ] && $text[ $key ] && in_array( $key, $fields, true ) ) {
					$filters[ $key ] = $text[ $key ];
				}
			}

			// Looked for in the listings, without making the page a search page.
			if ( $text['words'] ) {
				$args['crc_words'] = $text['words'];
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

		if ( ! empty( $filters['beds_max'] ) ) {
			$number( '_crc_bedrooms', (int) $filters['beds_max'], '<=' );
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

		// More filters. Ready-made details keep their option's name, e.g. "carpeted".
		$is = function ( $name, $values ) use ( &$meta ) {
			$meta[] = array(
				'key'     => self::meta_of( $name ),
				'value'   => $values,
				'compare' => 'IN',
			);
		};

		if ( '' !== $filters['negotiable'] ) {
			$is( 'price_type', array( 'negotiable' ) );
		}

		if ( '' !== $filters['bank_loan'] ) {
			$is( 'bank_loan', array( 'pre_approved', 'available' ) );
		}

		if ( '' !== $filters['bills'] ) {
			$is( 'utility_bills', array( 'included' ) );
		}

		if ( $filters['advance_max'] ) {
			$number( self::meta_of( 'advance_payment' ), (int) $filters['advance_max'], '<=' );
		}

		if ( $filters['floor_min'] ) {
			$number( Listing_Index::FLOOR, (int) $filters['floor_min'], '>=' );
		}

		if ( $filters['floor_max'] ) {
			$number( Listing_Index::FLOOR, (int) $filters['floor_max'], '<=' );
		}

		if ( $filters['parking'] ) {
			$number( self::meta_of( 'parking' ), (int) $filters['parking'], '>=' );
		}

		if ( '' !== $filters['road_type'] ) {
			$is( 'road_type', array( $filters['road_type'] ) );
		}

		if ( $filters['road_width'] ) {
			$number( self::meta_of( 'road_width' ), (int) $filters['road_width'], '>=', 'DECIMAL(10,2)' );
		}

		// Three-phase power is available power too, and a listing with pipe-borne water and a well has both.
		$also = array(
			'electricity' => array( 'available' => array( 'available', 'three_phase' ) ),
			'water'       => array(
				'pipe_borne' => array( 'pipe_borne', 'pipe_borne_well' ),
				'well'       => array( 'well', 'pipe_borne_well' ),
			),
		);

		foreach ( array(
			'electricity' => 'electricity',
			'water'       => 'water_supply',
		) as $key => $name ) {
			if ( '' !== $filters[ $key ] ) {
				$is( $name, isset( $also[ $key ][ $filters[ $key ] ] ) ? $also[ $key ][ $filters[ $key ] ] : array( $filters[ $key ] ) );
			}
		}

		// Every ticked feature, looked for in clauses(), so however many are ticked the query stays light.
		if ( '' !== $filters['features'] ) {
			$args['crc_features'] = explode( ',', $filters['features'] );
		}

		// Availability and Listed by are kept as their words, e.g. "Available Now" and "Owner".
		if ( '' !== $filters['availability'] || '' !== $filters['listed_by'] ) {
			$choices = self::more_choices();
			$main    = Overview::fields();

			foreach ( array( 'availability', 'listed_by' ) as $key ) {
				if ( '' !== $filters[ $key ] && isset( $choices[ $key ][ $filters[ $key ] ] ) ) {
					$meta[] = array(
						'key'   => isset( $main[ $key ]['meta'] ) ? $main[ $key ]['meta'] : '_crc_' . $key,
						'value' => $choices[ $key ][ $filters[ $key ] ],
					);
				}
			}
		}

		if ( $filters['posted'] ) {
			// What was listed in the last day changes with the clock, so the page is kept in the cache for an hour at most.
			do_action( 'litespeed_control_set_ttl', HOUR_IN_SECONDS );

			$args['date_query'] = array(
				array(
					'column'    => 'post_date_gmt',
					'after'     => gmdate( 'Y-m-d H:i:s', time() - (int) $filters['posted'] * DAY_IN_SECONDS ),
					'inclusive' => true,
				),
			);
		}

		if ( $tax ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- One page of results.
		}

		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- One page of results.
		}

		// Orders by a number, with listings that don't have it last.
		$orders = array(
			'price-asc'      => array( Price_Card::PRICE_META, 'ASC' ),
			'price-desc'     => array( Price_Card::PRICE_META, 'DESC' ),
			'popular'        => array( Views::META, 'DESC' ),
			'per-perch-asc'  => array( Listing_Index::PER_PERCH, 'ASC' ),
			'per-perch-desc' => array( Listing_Index::PER_PERCH, 'DESC' ),
			'size-desc'      => array( Listing_Index::PERCHES, 'DESC' ),
			'size-asc'       => array( Listing_Index::PERCHES, 'ASC' ),
			'beds-desc'      => array( '_crc_bedrooms', 'DESC' ),
			'baths-desc'     => array( '_crc_bathrooms', 'DESC' ),
			'area-desc'      => array( Listing_Index::FLOOR, 'DESC' ),
		);

		// Orders WordPress knows itself.
		$plain = array(
			'oldest'    => array( 'date', 'ASC' ),
			'updated'   => array( 'modified', 'DESC' ),
			'title-asc' => array( 'title', 'ASC' ),
		);

		if ( isset( $orders[ $filters['sort'] ] ) ) {
			$args['crc_order'] = array(
				'key'   => $orders[ $filters['sort'] ][0],
				'order' => $orders[ $filters['sort'] ][1],
			);
		} elseif ( isset( $plain[ $filters['sort'] ] ) ) {
			$args['orderby'] = $plain[ $filters['sort'] ][0];
			$args['order']   = $plain[ $filters['sort'] ][1];
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
	 * keywords, the ticked features, and an order by a number, with listings
	 * that don't have it last whichever way round, then newest first.
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

		$title    = $query->get( 'crc_title' );
		$words    = $query->get( 'crc_words' );
		$order    = $query->get( 'crc_order' );
		$features = $query->get( 'crc_features' );

		if ( is_string( $title ) && '' !== $title ) {
			$clauses['where'] = ( isset( $clauses['where'] ) ? $clauses['where'] : '' ) . $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $title ) . '%' );
		}

		// Each keyword is in the title, the description, the type, furnishing, features or details, or the town, district or category.
		if ( is_array( $words ) && $words ) {
			$keys       = "'" . implode( "','", array_map( 'esc_sql', Listing_Text::meta_keys() ) ) . "'";
			$taxonomies = "'" . implode( "','", array_map( 'esc_sql', array( Town::NAME, District::NAME, Taxonomy::NAME ) ) ) . "'";

			foreach ( array_slice( $words, 0, 6 ) as $word ) {
				if ( ! is_string( $word ) || '' === $word ) {
					continue;
				}

				$like = '%' . $wpdb->esc_like( $word ) . '%';

				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table names, keys and taxonomies are the plugin's own, escaped above.
				$clauses['where'] = ( isset( $clauses['where'] ) ? $clauses['where'] : '' ) . $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s"
					. " OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS crc_m WHERE crc_m.post_id = {$wpdb->posts}.ID AND crc_m.meta_key IN ( {$keys} ) AND crc_m.meta_value LIKE %s )"
					. " OR EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} AS crc_r INNER JOIN {$wpdb->term_taxonomy} AS crc_tt ON crc_tt.term_taxonomy_id = crc_r.term_taxonomy_id INNER JOIN {$wpdb->terms} AS crc_t ON crc_t.term_id = crc_tt.term_id WHERE crc_r.object_id = {$wpdb->posts}.ID AND crc_tt.taxonomy IN ( {$taxonomies} ) AND crc_t.name LIKE %s ) )",
					$like,
					$like,
					$like,
					$like
				);
				// phpcs:enable
			}
		}

		// Each ticked feature, in quotes so one name can't match part of another, e.g. "garden" in a list of them.
		if ( is_array( $features ) ) {
			foreach ( array_slice( $features, 0, 100 ) as $feature ) {
				if ( ! is_string( $feature ) || '' === $feature ) {
					continue;
				}

				$clauses['where'] = ( isset( $clauses['where'] ) ? $clauses['where'] : '' ) . $wpdb->prepare(
					" AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS crc_f WHERE crc_f.post_id = {$wpdb->posts}.ID AND crc_f.meta_key = %s AND crc_f.meta_value LIKE %s )",
					Features::META,
					'%"' . $wpdb->esc_like( $feature ) . '"%'
				);
			}
		}

		if ( is_array( $order ) && ! empty( $order['key'] ) ) {
			$direction          = isset( $order['order'] ) && 'DESC' === strtoupper( (string) $order['order'] ) ? 'DESC' : 'ASC';
			$clauses['join']    = ( isset( $clauses['join'] ) ? $clauses['join'] : '' ) . $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS crc_sort ON ( crc_sort.post_id = {$wpdb->posts}.ID AND crc_sort.meta_key = %s )", (string) $order['key'] );
			$clauses['orderby'] = "( crc_sort.meta_value IS NULL OR crc_sort.meta_value = '' ) ASC, CAST( crc_sort.meta_value AS DECIMAL(20,2) ) {$direction}, {$wpdb->posts}.post_date DESC";
		}

		return $clauses;
	}
}
