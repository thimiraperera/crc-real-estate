<?php
/**
 * Property overview section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Icons;
use CRC\RealEstate\Popup;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * The listing's four main details in boxes (property type, offered for,
 * availability and listed by), and See More, which opens a popup with those
 * boxes and the other details in groups with check marks: first the
 * ready-made groups for the listing's category, then the listing's own.
 */
final class Overview {

	const SHORTCODE = 'crc_listing_overview';
	const MORE_META  = '_crc_overview';
	const EXTRA_META = '_crc_overview_extra';

	/**
	 * The main details, in order, keyed by name.
	 *
	 * - label:       name on the site and on the listing screen.
	 * - meta:        where the value is saved.
	 * - icon:        icon from assets/icons.
	 * - help:        help text on the listing screen.
	 * - type:        text (typed, with suggestions) or select (chosen from 'options').
	 * - suggestions: for text, values offered while typing; any other value can be typed.
	 * - options:     for select, the values to choose from.
	 *
	 * @return array[]
	 */
	public static function fields() {
		/**
		 * Filters the main overview details, e.g. to rename them or change the suggestions on another site.
		 *
		 * @param array[] $fields Name => details.
		 */
		$fields = apply_filters(
			'crc_re_overview_fields',
			array(
				'property_type' => array(
					'label'       => __( 'Property type', 'crc-real-estate' ),
					'meta'        => '_crc_property_type',
					'icon'        => 'land-plots',
					'help'        => __( 'What the property is, for example Bare Land, House or Apartment.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Bare Land', 'crc-real-estate' ),
						__( 'Agricultural Land', 'crc-real-estate' ),
						__( 'Commercial Land', 'crc-real-estate' ),
						__( 'House', 'crc-real-estate' ),
						__( 'Apartment', 'crc-real-estate' ),
						__( 'Villa', 'crc-real-estate' ),
						__( 'Annex', 'crc-real-estate' ),
						__( 'Room', 'crc-real-estate' ),
						__( 'Commercial Building', 'crc-real-estate' ),
					),
				),
				'offered_for'   => array(
					'label'       => __( 'Offered for', 'crc-real-estate' ),
					'meta'        => '_crc_offered_for',
					'icon'        => 'cart',
					'help'        => __( 'How it is offered, for example Sale, Rent or Lease.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Sale', 'crc-real-estate' ),
						__( 'Rent', 'crc-real-estate' ),
						__( 'Lease', 'crc-real-estate' ),
					),
				),
				'availability'  => array(
					'label'   => __( 'Availability', 'crc-real-estate' ),
					'meta'    => '_crc_availability',
					'icon'    => 'land-plots',
					'type'    => 'select',
					'help'    => __( 'Whether it can be bought or rented now. Choose Sold or Rented once it has gone.', 'crc-real-estate' ),
					'options' => array(
						__( 'Available Now', 'crc-real-estate' ),
						__( 'Available Soon', 'crc-real-estate' ),
						__( 'Under Offer', 'crc-real-estate' ),
						__( 'Sold', 'crc-real-estate' ),
						__( 'Rented', 'crc-real-estate' ),
					),
				),
				'listed_by'     => array(
					'label'       => __( 'Listed by', 'crc-real-estate' ),
					'meta'        => '_crc_listed_by',
					'icon'        => 'road',
					'help'        => __( 'Who is offering it, for example Owner or Agent.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Owner', 'crc-real-estate' ),
						__( 'Agent', 'crc-real-estate' ),
						__( 'Developer', 'crc-real-estate' ),
					),
				),
			)
		);

		// Details added through the filter only need a label.
		foreach ( (array) $fields as $name => $field ) {
			$fields[ $name ] = wp_parse_args(
				(array) $field,
				array(
					'label'       => $name,
					'meta'        => '_crc_' . $name,
					'icon'        => 'check',
					'help'        => '',
					'type'        => 'text',
					'suggestions' => array(),
					'options'     => array(),
				)
			);
		}

		return (array) $fields;
	}

	/**
	 * The ready-made groups of details listings fill in. They show in the See
	 * More popup, each detail only when it has a value, and each group only
	 * for its categories.
	 *
	 * Each group has a 'title', 'categories' (category slugs; empty for every
	 * category) and 'items', keyed by name. A detail in more than one group,
	 * such as Land extent, is one value. Each item has:
	 * - label:    name on the site and on the listing screen.
	 * - type:     number, money, select, text, or linked (the value comes from 'value').
	 * - meta:     where the value is saved (default _crc_{name}).
	 * - unit:     for numbers, how the number is written, from _n_noop().
	 * - units:    for numbers, units to choose from: key => _n_noop().
	 * - decimals: for numbers, whether decimals are allowed.
	 * - format:   for money and linked amounts, how the amount is written, e.g. "%s per month".
	 * - options:  for selects, key => label.
	 * - amount:   for linked items, a callback that gets the listing ID and returns an
	 *             amount in digits, written as money with 'format'.
	 * - value:    for linked items without an amount, a callback that returns the text.
	 * - note:     for linked items, help text under their locked field on the listing screen.
	 *
	 * @return array[]
	 */
	public static function common_groups() {
		$area = array(
			/* translators: %s: number. */
			'perches'  => _n_noop( '%s perch', '%s perches', 'crc-real-estate' ),
			/* translators: %s: number. */
			'acres'    => _n_noop( '%s Acre', '%s Acres', 'crc-real-estate' ),
			/* translators: %s: number. */
			'hectares' => _n_noop( '%s Hectare', '%s Hectares', 'crc-real-estate' ),
		);

		/* translators: %s: number of feet. */
		$feet = _n_noop( '%s ft', '%s ft', 'crc-real-estate' );

		/* translators: %s: number of months. */
		$months = _n_noop( '%s month', '%s months', 'crc-real-estate' );

		$land_extent = array(
			'label' => __( 'Land extent', 'crc-real-estate' ),
			'type'  => 'number',
			'units' => $area,
		);

		$price_type = array(
			'label'   => __( 'Price type', 'crc-real-estate' ),
			'type'    => 'select',
			'options' => array(
				'negotiable' => __( 'Negotiable', 'crc-real-estate' ),
				'fixed'      => __( 'Fixed', 'crc-real-estate' ),
			),
		);

		/* translators: %s: amount, e.g. "Rs. 25,000". */
		$per_month = __( '%s per month', 'crc-real-estate' );

		$maintenance_fee = array(
			'label'  => __( 'Maintenance fee', 'crc-real-estate' ),
			'type'   => 'money',
			'format' => $per_month,
		);

		$from_price_box = __( 'Locked. It comes from the Price box, so you only type it once.', 'crc-real-estate' );

		/**
		 * Filters the ready-made groups of details, e.g. to add a group or change the choices on another site.
		 *
		 * @param array[] $groups Group name => 'title', 'categories' and 'items'.
		 */
		$groups = apply_filters(
			'crc_re_overview_common_groups',
			array(
				'size_price'  => array(
					'title'      => __( 'Size and price', 'crc-real-estate' ),
					'categories' => array( 'lands' ),
					'items'      => array(
						'land_extent'      => $land_extent,
						'extent_perches'   => array(
							'label' => __( 'Extent in perches', 'crc-real-estate' ),
							'type'  => 'number',
							'unit'  => $area['perches'],
						),
						'price_per_perch'  => array(
							'label'  => __( 'Price per perch', 'crc-real-estate' ),
							'type'   => 'linked',
							'amount' => array( Price_Card::class, 'price_per_perch' ),
							'note'   => __( 'Locked. It comes from Price per perch in the Price box, so you only type it once.', 'crc-real-estate' ),
						),
						'price_basis'      => array(
							'label'   => __( 'Price basis', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'per_perch' => __( 'Per perch', 'crc-real-estate' ),
								'per_acre'  => __( 'Per acre', 'crc-real-estate' ),
								'total'     => __( 'Total price', 'crc-real-estate' ),
							),
						),
						'price_type'       => $price_type,
						'plots'            => array(
							'label'    => __( 'Number of plots', 'crc-real-estate' ),
							'type'     => 'number',
							/* translators: %s: number of plots. */
							'unit'     => _n_noop( '%s plot', '%s plots', 'crc-real-estate' ),
							'decimals' => false,
						),
						'minimum_purchase' => array(
							'label' => __( 'Minimum purchase', 'crc-real-estate' ),
							'type'  => 'number',
							'units' => $area,
						),
					),
				),
				'size_layout' => array(
					'title'      => __( 'Size and layout', 'crc-real-estate' ),
					'categories' => array( 'properties-for-sale', 'properties-for-rent' ),
					'items'      => array(
						'bedrooms'          => array(
							'label'    => __( 'Bedrooms', 'crc-real-estate' ),
							'type'     => 'number',
							'decimals' => false,
						),
						'bathrooms'         => array(
							'label'    => __( 'Bathrooms', 'crc-real-estate' ),
							'type'     => 'number',
							'decimals' => false,
						),
						'ensuite_bathrooms' => array(
							'label'    => __( 'Ensuite bathrooms', 'crc-real-estate' ),
							'type'     => 'number',
							'decimals' => false,
						),
						'floor_area'        => array(
							'label' => __( 'Floor area', 'crc-real-estate' ),
							'type'  => 'number',
							'units' => array(
								/* translators: %s: number of square feet. */
								'sq_ft' => _n_noop( '%s sq ft', '%s sq ft', 'crc-real-estate' ),
								/* translators: %s: number of square metres. */
								'sq_m'  => _n_noop( '%s sq m', '%s sq m', 'crc-real-estate' ),
							),
						),
						'land_extent'       => $land_extent,
						'storeys'           => array(
							'label'    => __( 'Storeys', 'crc-real-estate' ),
							'type'     => 'number',
							'decimals' => false,
						),
						'parking'           => array(
							'label'    => __( 'Parking', 'crc-real-estate' ),
							'type'     => 'number',
							/* translators: %s: number of cars. */
							'unit'     => _n_noop( '%s car', '%s cars', 'crc-real-estate' ),
							'decimals' => false,
						),
						'furnishing'        => array(
							'label'   => __( 'Furnishing', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'furnished'      => __( 'Furnished', 'crc-real-estate' ),
								'semi_furnished' => __( 'Semi-furnished', 'crc-real-estate' ),
								'unfurnished'    => __( 'Unfurnished', 'crc-real-estate' ),
							),
						),
					),
				),
				'price_terms' => array(
					'title'      => __( 'Price and terms', 'crc-real-estate' ),
					'categories' => array( 'properties-for-sale' ),
					'items'      => array(
						'price'                => array(
							'label'  => __( 'Price', 'crc-real-estate' ),
							'type'   => 'linked',
							'amount' => array( Price_Card::class, 'price' ),
							'note'   => $from_price_box,
						),
						'price_per_perch_sale' => array(
							'label'  => __( 'Price per perch', 'crc-real-estate' ),
							'type'   => 'linked',
							'amount' => array( __CLASS__, 'worked_out_price_per_perch' ),
							'note'   => __( 'Locked. It is worked out from the price and the land extent when the listing is saved, so there is nothing to type.', 'crc-real-estate' ),
						),
						'price_type'           => $price_type,
						'maintenance_fee'      => $maintenance_fee,
						'bank_loan'            => array(
							'label'   => __( 'Bank loan', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'pre_approved'  => __( 'Pre-approved', 'crc-real-estate' ),
								'available'     => __( 'Available', 'crc-real-estate' ),
								'not_available' => __( 'Not available', 'crc-real-estate' ),
							),
						),
					),
				),
				'rent_terms'  => array(
					'title'      => __( 'Rent and terms', 'crc-real-estate' ),
					'categories' => array( 'properties-for-rent' ),
					'items'      => array(
						'rent'            => array(
							'label'  => __( 'Rent', 'crc-real-estate' ),
							'type'   => 'linked',
							'amount' => array( Price_Card::class, 'price' ),
							'format' => $per_month,
							'note'   => $from_price_box,
						),
						'advance_payment' => array(
							'label'    => __( 'Advance payment', 'crc-real-estate' ),
							'type'     => 'number',
							'unit'     => $months,
							'decimals' => false,
						),
						'minimum_lease'   => array(
							'label'    => __( 'Minimum lease', 'crc-real-estate' ),
							'type'     => 'number',
							'units'    => array(
								'months' => $months,
								/* translators: %s: number of years. */
								'years'  => _n_noop( '%s year', '%s years', 'crc-real-estate' ),
							),
							'decimals' => false,
						),
						'price_type'      => $price_type,
						'utility_bills'   => array(
							'label'   => __( 'Utility bills', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'separate' => __( 'Paid separately', 'crc-real-estate' ),
								'included' => __( 'Included in the rent', 'crc-real-estate' ),
							),
						),
						'maintenance_fee' => $maintenance_fee,
					),
				),
				'access_road' => array(
					'title'      => __( 'Access and road', 'crc-real-estate' ),
					'categories' => array(),
					'items'      => array(
						'road_frontage'      => array(
							'label' => __( 'Road frontage', 'crc-real-estate' ),
							'type'  => 'number',
							'unit'  => $feet,
						),
						'road_width'         => array(
							'label' => __( 'Approach road width', 'crc-real-estate' ),
							'type'  => 'number',
							'unit'  => $feet,
						),
						'road_type'          => array(
							'label'   => __( 'Approach road type', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'carpeted' => __( 'Carpeted', 'crc-real-estate' ),
								'tarred'   => __( 'Tarred', 'crc-real-estate' ),
								'concrete' => __( 'Concrete', 'crc-real-estate' ),
								'gravel'   => __( 'Gravel', 'crc-real-estate' ),
								'unpaved'  => __( 'Unpaved', 'crc-real-estate' ),
							),
						),
						'main_road_distance' => array(
							'label' => __( 'Distance to main road', 'crc-real-estate' ),
							'type'  => 'number',
							'units' => array(
								/* translators: %s: number of metres. */
								'm'  => _n_noop( '%s m', '%s m', 'crc-real-estate' ),
								/* translators: %s: number of kilometres. */
								'km' => _n_noop( '%s km', '%s km', 'crc-real-estate' ),
							),
						),
						'access'             => array(
							'label'   => __( 'Access', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'direct'       => __( 'Direct road access', 'crc-real-estate' ),
								'private_road' => __( 'Through a private road', 'crc-real-estate' ),
								'right_of_way' => __( 'Right of way', 'crc-real-estate' ),
							),
						),
						'facing'             => array(
							'label'   => __( 'Facing direction', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'north'      => __( 'North', 'crc-real-estate' ),
								'north_east' => __( 'North-East', 'crc-real-estate' ),
								'east'       => __( 'East', 'crc-real-estate' ),
								'south_east' => __( 'South-East', 'crc-real-estate' ),
								'south'      => __( 'South', 'crc-real-estate' ),
								'south_west' => __( 'South-West', 'crc-real-estate' ),
								'west'       => __( 'West', 'crc-real-estate' ),
								'north_west' => __( 'North-West', 'crc-real-estate' ),
							),
						),
					),
				),
				'utilities'   => array(
					'title'      => __( 'Utilities', 'crc-real-estate' ),
					'categories' => array(),
					'items'      => array(
						'electricity'  => array(
							'label'   => __( 'Electricity', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'available'   => __( 'Available', 'crc-real-estate' ),
								'three_phase' => __( 'Three-phase', 'crc-real-estate' ),
								'nearby'      => __( 'Nearby', 'crc-real-estate' ),
								'none'        => __( 'Not available', 'crc-real-estate' ),
							),
						),
						'water_supply' => array(
							'label'   => __( 'Water supply', 'crc-real-estate' ),
							'type'    => 'select',
							'options' => array(
								'pipe_borne'      => __( 'Pipe-borne', 'crc-real-estate' ),
								'well'            => __( 'Well', 'crc-real-estate' ),
								'pipe_borne_well' => __( 'Pipe-borne and well', 'crc-real-estate' ),
								'none'            => __( 'Not available', 'crc-real-estate' ),
							),
						),
					),
				),
			)
		);

		// Groups and details added through the filter only need a title or a label.
		foreach ( (array) $groups as $key => $group ) {
			$group = wp_parse_args(
				(array) $group,
				array(
					'title'      => '',
					'categories' => array(),
					'items'      => array(),
				)
			);

			foreach ( (array) $group['items'] as $name => $item ) {
				$group['items'][ $name ] = wp_parse_args(
					(array) $item,
					array(
						'label'    => $name,
						'type'     => 'text',
						'meta'     => '_crc_' . $name,
						'unit'     => null,
						'units'    => array(),
						'decimals' => true,
						'format'   => '%s',
						'options'  => array(),
						'amount'   => null,
						'value'    => null,
						'note'     => '',
					)
				);
			}

			$groups[ $key ] = $group;
		}

		return (array) $groups;
	}

	/**
	 * Every ready-made detail once, keyed by name, including those in more
	 * than one group, such as Land extent.
	 *
	 * @return array[]
	 */
	public static function common_items() {
		$items = array();

		foreach ( self::common_groups() as $group ) {
			foreach ( $group['items'] as $name => $item ) {
				if ( ! isset( $items[ $name ] ) ) {
					$items[ $name ] = $item;
				}
			}
		}

		return $items;
	}

	/**
	 * A listing's filled-in ready-made details, as text, in the groups for
	 * its category: each group's own details, then the ones the listing added
	 * to it. Details without a value and groups without details are left out.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each group has a 'title' and 'items', each item a 'label' and a 'value'.
	 */
	public static function common_details( $post_id ) {
		$category = Taxonomy::listing_category( $post_id );
		$slug     = $category ? $category['slug'] : '';
		$extras   = self::extras( $post_id );
		$groups   = array();

		foreach ( self::common_groups() as $key => $group ) {
			if ( $group['categories'] && ! in_array( $slug, $group['categories'], true ) ) {
				continue;
			}

			$items = array();

			foreach ( $group['items'] as $item ) {
				$text = self::detail_text( $post_id, $item );

				if ( '' !== $text ) {
					$items[] = array(
						'label' => $item['label'],
						'value' => $text,
					);
				}
			}

			if ( isset( $extras[ $key ] ) ) {
				$items = array_merge( $items, $extras[ $key ] );
			}

			if ( $items ) {
				$groups[] = array(
					'title' => $group['title'],
					'items' => $items,
				);
			}
		}

		return $groups;
	}

	/**
	 * A ready-made detail as it shows on the site, e.g. "20 Acres", or an
	 * empty string when it has no value.
	 *
	 * @param int   $post_id Listing ID.
	 * @param array $item    Detail from common_groups().
	 * @return string
	 */
	public static function detail_text( $post_id, array $item ) {
		if ( 'linked' === $item['type'] ) {
			if ( is_callable( $item['amount'] ) ) {
				$amount = self::linked_amount( $post_id, $item );

				return '' !== $amount ? sprintf( $item['format'], Price_Card::money( $amount ) ) : '';
			}

			return is_callable( $item['value'] ) ? trim( (string) call_user_func( $item['value'], $post_id ) ) : '';
		}

		$value = get_post_meta( $post_id, $item['meta'], true );

		if ( 'number' === $item['type'] ) {
			$number = self::sanitize_number( $value, $item['decimals'] );

			return '' !== $number ? self::format_number( $number, self::unit_of( $post_id, $item ) ) : '';
		}

		if ( 'money' === $item['type'] ) {
			$amount = Price_Card::sanitize_amount( $value );

			return '' !== $amount ? sprintf( $item['format'], Price_Card::money( $amount ) ) : '';
		}

		$value = trim( (string) $value );

		if ( 'select' === $item['type'] && isset( $item['options'][ $value ] ) ) {
			return (string) $item['options'][ $value ];
		}

		return $value;
	}

	/**
	 * How a number is written: with the unit chosen on the listing, or the
	 * detail's own unit.
	 *
	 * @param int   $post_id Listing ID.
	 * @param array $item    Detail from common_groups().
	 * @return array|null A unit from _n_noop(), or null for none.
	 */
	public static function unit_of( $post_id, array $item ) {
		if ( $item['units'] ) {
			$key = (string) get_post_meta( $post_id, $item['meta'] . '_unit', true );

			return isset( $item['units'][ $key ] ) ? $item['units'][ $key ] : reset( $item['units'] );
		}

		return $item['unit'];
	}

	/**
	 * A unit's name on its own, e.g. "Acres".
	 *
	 * @param array $unit Unit from _n_noop().
	 * @return string
	 */
	public static function unit_name( $unit ) {
		return trim( sprintf( translate_nooped_plural( $unit, 2, 'crc-real-estate' ), '' ) );
	}

	/**
	 * A number written for people, e.g. "3,200 perches" or "1.5 Acres".
	 *
	 * @param string     $number Number from sanitize_number().
	 * @param array|null $unit   Unit from _n_noop(), or null for the number alone.
	 * @return string
	 */
	public static function format_number( $number, $unit ) {
		$dot      = strpos( $number, '.' );
		$decimals = false === $dot ? 0 : strlen( $number ) - $dot - 1;
		$text     = number_format_i18n( (float) $number, $decimals );

		if ( ! $unit ) {
			return $text;
		}

		// A number with decimals reads as more than one, e.g. "1.5 Acres".
		$count = false === $dot ? (int) $number : 2;

		return sprintf( translate_nooped_plural( $unit, $count, 'crc-real-estate' ), $text );
	}

	/**
	 * Keeps a number as typed without commas or spaces: "3,200" becomes 3200
	 * and "1.50" becomes 1.5, with at most two decimals. Zero, negative
	 * numbers and anything else count as empty.
	 *
	 * @param mixed $value    Typed number.
	 * @param bool  $decimals Whether decimals are allowed; without, "5.7" becomes 5.
	 * @return string
	 */
	public static function sanitize_number( $value, $decimals = true ) {
		$value = is_scalar( $value ) ? preg_replace( '/[,\s]/', '', (string) $value ) : '';

		if ( ! preg_match( '/^\d*\.?\d*$/', $value ) || ! preg_match( '/\d/', $value ) ) {
			return '';
		}

		$number = $decimals ? round( (float) $value, 2 ) : floor( (float) $value );

		if ( $number <= 0 ) {
			return '';
		}

		return $decimals ? rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' ) : (string) (int) $number;
	}

	/**
	 * Cleans a ready-made detail's value for saving.
	 *
	 * @param array $item  Detail from common_groups().
	 * @param mixed $value Value as typed or chosen.
	 * @return string An empty string when there is nothing to save.
	 */
	public static function sanitize_detail( array $item, $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( 'number' === $item['type'] ) {
			return self::sanitize_number( $value, $item['decimals'] );
		}

		if ( 'money' === $item['type'] ) {
			return Price_Card::sanitize_amount( $value );
		}

		if ( 'select' === $item['type'] ) {
			return '' !== $value && isset( $item['options'][ $value ] ) ? $value : '';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Cleans a chosen unit for saving: one of the detail's units, or empty.
	 *
	 * @param array $item  Detail from common_groups().
	 * @param mixed $value Chosen unit.
	 * @return string
	 */
	public static function sanitize_unit( array $item, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return '' !== $value && isset( $item['units'][ $value ] ) ? $value : '';
	}

	/**
	 * A linked detail's amount, in digits.
	 *
	 * @param int   $post_id Listing ID.
	 * @param array $item    Linked detail from common_groups().
	 * @return string An empty string when there is none.
	 */
	public static function linked_amount( $post_id, array $item ) {
		return is_callable( $item['amount'] ) ? Price_Card::sanitize_amount( call_user_func( $item['amount'], $post_id ) ) : '';
	}

	/**
	 * Price per perch worked out from the price and the land extent, in
	 * digits: Rs. 70,000,000 for 12 perches is 5833333.
	 *
	 * @param int $post_id Listing ID.
	 * @return string An empty string without a price or a land extent.
	 */
	public static function worked_out_price_per_perch( $post_id ) {
		$price   = Price_Card::price( $post_id );
		$perches = self::land_perches( $post_id );

		return ( '' !== $price && $perches > 0 ) ? number_format( round( (float) $price / $perches ), 0, '.', '' ) : '';
	}

	/**
	 * The land extent in perches: 1 acre is 160 perches and 1 hectare is
	 * about 395.37 perches.
	 *
	 * @param int $post_id Listing ID.
	 * @return float 0 when there is no land extent.
	 */
	public static function land_perches( $post_id ) {
		$number = self::sanitize_number( get_post_meta( $post_id, '_crc_land_extent', true ) );

		if ( '' === $number ) {
			return 0.0;
		}

		$perches = array(
			'perches'  => 1,
			'acres'    => 160,
			'hectares' => 395.3686,
		);
		$unit    = (string) get_post_meta( $post_id, '_crc_land_extent_unit', true );

		return (float) $number * ( isset( $perches[ $unit ] ) ? $perches[ $unit ] : 1 );
	}

	/**
	 * Group titles and detail labels offered while typing in the listing's
	 * own groups. Ones used on other listings are offered too.
	 *
	 * @return array[] 'titles' and 'labels'.
	 */
	public static function suggestions() {
		/**
		 * Filters the suggested group titles and detail labels.
		 *
		 * @param array[] $suggestions 'titles' and 'labels'.
		 */
		return apply_filters(
			'crc_re_overview_suggestions',
			array(
				'titles' => array(),
				'labels' => array(),
			)
		);
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Registers the fields and the shortcode.
	 */
	public function register() {
		$auth = function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		foreach ( self::fields() as $field ) {
			register_post_meta(
				Post_Type::NAME,
				$field['meta'],
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
		}

		foreach ( self::common_items() as $item ) {
			if ( 'linked' === $item['type'] ) {
				continue;
			}

			register_post_meta(
				Post_Type::NAME,
				$item['meta'],
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => function ( $value ) use ( $item ) {
						return self::sanitize_detail( $item, $value );
					},
					'auth_callback'     => $auth,
				)
			);

			if ( $item['units'] ) {
				register_post_meta(
					Post_Type::NAME,
					$item['meta'] . '_unit',
					array(
						'type'              => 'string',
						'single'            => true,
						'sanitize_callback' => function ( $value ) use ( $item ) {
							return self::sanitize_unit( $item, $value );
						},
						'auth_callback'     => $auth,
					)
				);
			}
		}

		register_post_meta(
			Post_Type::NAME,
			self::MORE_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_groups' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			Post_Type::NAME,
			self::EXTRA_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_extras' ),
				'auth_callback'     => $auth,
			)
		);

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Property overview', 'crc-real-estate' ),
				'description' => __( 'The four main details in boxes: property type, offered for, availability and listed by. Below them, See More opens a popup with the same boxes, then the ready-made groups for the listing\'s category and any groups of the listing\'s own, each detail with a check mark. Lands have Size and price; properties for sale have Size and layout and Price and terms; properties for rent have Size and layout and Rent and terms; all have Access and road and Utilities. Each ready-made group can also take details of the listing\'s own. Details and groups without a value don\'t show, and See More shows once there is something for the popup. Fill them in the Property overview box on the listing screen.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'    => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
					'title' => array(
						'default'     => __( 'Property overview', 'crc-real-estate' ),
						'description' => __( 'Title at the top of the popup.', 'crc-real-estate' ),
					),
					'more'  => array(
						'default'     => __( 'See More', 'crc-real-estate' ),
						'description' => __( 'Text of the link that opens the popup.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' id="123"]',
					'[' . self::SHORTCODE . ' title="Overview" more="See all details"]',
				),
			)
		);
	}

	/**
	 * Registers the front-end styles. The popup's own styles come with them.
	 */
	public function register_assets() {
		if ( ! wp_style_is( 'crc-re-popup', 'registered' ) ) {
			Popup::register_assets();
		}

		wp_register_style( 'crc-re-overview', CRC_RE_URL . 'assets/css/overview.css', array( 'crc-re-popup' ), CRC_RE_VERSION );
	}

	/**
	 * Loads the styles in the page head on listing pages.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) ) {
			$this->enqueue_style();
		}
	}

	/**
	 * Loads the overview styles.
	 */
	public function enqueue_style() {
		if ( ! wp_style_is( 'crc-re-overview', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-overview' );
	}

	/**
	 * Cleans a main detail's value. A list's value must be one of its choices,
	 * written as in the list; a value saved before the detail became a list is
	 * kept while it isn't changed.
	 *
	 * @param array  $field   Main detail from fields().
	 * @param mixed  $value   Value as typed or chosen.
	 * @param string $current Value saved now.
	 * @return string An empty string when there is nothing to save.
	 */
	public static function sanitize_main( array $field, $value, $current = '' ) {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

		if ( 'select' !== $field['type'] || '' === $value ) {
			return $value;
		}

		$choice = self::choice( $field, $value );

		if ( '' !== $choice ) {
			return $choice;
		}

		return $value === (string) $current ? $value : '';
	}

	/**
	 * The list choice a value stands for, whatever its capitals, e.g.
	 * "available now" is "Available Now".
	 *
	 * @param array  $field Main detail from fields().
	 * @param string $value Value.
	 * @return string The choice, or an empty string when it isn't one.
	 */
	public static function choice( array $field, $value ) {
		foreach ( (array) $field['options'] as $option ) {
			if ( 0 === strcasecmp( (string) $option, trim( (string) $value ) ) ) {
				return (string) $option;
			}
		}

		return '';
	}

	/**
	 * A listing's main details that have a value, in order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Name => 'label', 'value' and 'icon'.
	 */
	public static function main_details( $post_id ) {
		$details = array();

		foreach ( self::fields() as $name => $field ) {
			$value = trim( (string) get_post_meta( $post_id, $field['meta'], true ) );

			if ( '' !== $value ) {
				$details[ $name ] = array(
					'label' => $field['label'],
					'value' => $value,
					'icon'  => $field['icon'],
				);
			}
		}

		return $details;
	}

	/**
	 * A listing's other details, in groups, in their saved order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each group has a 'title' and 'items', each item a 'label' and a 'value'.
	 */
	public static function groups( $post_id ) {
		return self::sanitize_groups( get_post_meta( $post_id, self::MORE_META, true ) );
	}

	/**
	 * Cleans groups of details: plain text only, empty details dropped, and
	 * a group kept when it has a title or a detail.
	 *
	 * @param mixed $groups Groups as typed or saved.
	 * @return array[]
	 */
	public static function sanitize_groups( $groups ) {
		$clean = array();

		if ( ! is_array( $groups ) ) {
			return $clean;
		}

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
			$items = self::sanitize_items( isset( $group['items'] ) ? $group['items'] : array() );

			if ( '' !== $title || $items ) {
				$clean[] = array(
					'title' => $title,
					'items' => $items,
				);
			}
		}

		return $clean;
	}

	/**
	 * Cleans a list of details: plain text only, and details with neither a
	 * label nor a value dropped.
	 *
	 * @param mixed $items Details as typed or saved.
	 * @return array[] Each with a 'label' and a 'value'.
	 */
	public static function sanitize_items( $items ) {
		$clean = array();

		if ( ! is_array( $items ) ) {
			return $clean;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$label = isset( $item['label'] ) && is_scalar( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
			$value = isset( $item['value'] ) && is_scalar( $item['value'] ) ? sanitize_text_field( (string) $item['value'] ) : '';

			if ( '' !== $label || '' !== $value ) {
				$clean[] = array(
					'label' => $label,
					'value' => $value,
				);
			}
		}

		return $clean;
	}

	/**
	 * The details a listing added to the ready-made groups, in their saved
	 * order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Group name => details, each with a 'label' and a 'value'.
	 */
	public static function extras( $post_id ) {
		return self::sanitize_extras( get_post_meta( $post_id, self::EXTRA_META, true ) );
	}

	/**
	 * Cleans the details added to the ready-made groups: only groups that
	 * exist, plain text only, and empty details and groups dropped.
	 *
	 * @param mixed $extras Group name => details, as typed or saved.
	 * @return array[]
	 */
	public static function sanitize_extras( $extras ) {
		$clean = array();

		if ( ! is_array( $extras ) ) {
			return $clean;
		}

		$groups = self::common_groups();

		foreach ( $extras as $key => $items ) {
			$items = isset( $groups[ $key ] ) ? self::sanitize_items( $items ) : array();

			if ( $items ) {
				$clean[ $key ] = $items;
			}
		}

		return $clean;
	}

	/**
	 * Renders [crc_listing_overview].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = Shortcodes::atts( self::SHORTCODE, $atts );
		$post = Shortcodes::listing( $atts['id'] );

		if ( ! $post ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'Place this on a listing page, or add id="…" with a listing ID.', 'crc-real-estate' ) );
		}

		$main   = self::main_details( $post->ID );
		$groups = array_merge(
			self::common_details( $post->ID ),
			array_values(
				array_filter(
					self::groups( $post->ID ),
					function ( $group ) {
						return ! empty( $group['items'] );
					}
				)
			)
		);

		if ( ! $main && ! $groups ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'This listing has no overview details yet. Fill them in the Property overview box on the listing screen.', 'crc-real-estate' ) );
		}

		$this->enqueue_style();

		$html = '<div class="crc-overview">';

		if ( $main ) {
			$html .= $this->cards( $main );
		}

		if ( $groups ) {
			$id = Popup::new_id( 'overview' );

			$html .= sprintf(
				'<button type="button" class="crc-overview-more"%1$s><span class="crc-overview-more-text">%2$s</span>%3$s</button>',
				Popup::opener( $id ),
				esc_html( $atts['more'] ),
				Icons::svg( 'arrow-right', 'crc-overview-more-icon' )
			);

			$html .= Popup::render( $id, $atts['title'], '<div class="crc-overview-all">' . ( $main ? $this->cards( $main ) : '' ) . $this->groups_html( $groups ) . '</div>' );
		}

		return $html . '</div>';
	}

	/**
	 * The main details as boxes.
	 *
	 * @param array[] $details Main details from main_details().
	 * @return string
	 */
	private function cards( array $details ) {
		$html = '<div class="crc-overview-cards">';

		foreach ( $details as $name => $detail ) {
			$html .= sprintf(
				'<div class="crc-overview-card crc-overview-card-%1$s">%2$s<div class="crc-overview-text"><h6 class="crc-overview-label">%3$s</h6><p class="crc-overview-value">%4$s</p></div></div>',
				esc_attr( str_replace( '_', '-', sanitize_key( $name ) ) ),
				Icons::svg( $detail['icon'], 'crc-overview-icon' ),
				esc_html( $detail['label'] ),
				esc_html( $detail['value'] )
			);
		}

		return $html . '</div>';
	}

	/**
	 * The popup's groups: each group's title and its details with check marks.
	 *
	 * @param array[] $groups Groups that have details.
	 * @return string
	 */
	private function groups_html( array $groups ) {
		$html = '';

		foreach ( $groups as $group ) {
			$html .= '<div class="crc-overview-group">';

			if ( '' !== $group['title'] ) {
				$html .= '<h6 class="crc-overview-group-title">' . esc_html( $group['title'] ) . '</h6>';
			}

			$html .= '<ul class="crc-overview-list" role="list">';

			foreach ( $group['items'] as $item ) {
				$html .= '<li class="crc-overview-item">' . Icons::svg( 'check', 'crc-overview-icon' ) . '<div class="crc-overview-text">';

				if ( '' !== $item['label'] ) {
					$html .= '<p class="crc-overview-item-label">' . esc_html( $item['label'] ) . '</p>';
				}

				if ( '' !== $item['value'] ) {
					$html .= '<p class="crc-overview-item-value">' . esc_html( $item['value'] ) . '</p>';
				}

				$html .= '</div></li>';
			}

			$html .= '</ul></div>';
		}

		return $html;
	}
}
