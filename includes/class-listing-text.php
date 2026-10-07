<?php
/**
 * Free search text.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Overview;

defined( 'ABSPATH' ) || exit;

/**
 * What people type in the search, understood the way a property site would:
 * places (towns, districts, Colombo zones and the areas they are known by),
 * property types, bedrooms, prices, land sizes and furnishing, wherever they
 * are in the text, and the other words as keywords that a listing's title,
 * description, features, details, place or type must all have.
 *
 * "3 bedroom villa with pool in Galle under 50m" is a villa in Galle with at
 * least three bedrooms for up to Rs. 50,000,000 that has a pool.
 */
final class Listing_Text {

	/**
	 * Words that say nothing about which listing is wanted.
	 */
	const SKIP = array( 'a', 'an', 'the', 'in', 'at', 'on', 'near', 'nearby', 'around', 'close', 'closer', 'to', 'for', 'of', 'with', 'and', 'or', 'by', 'from', 'my', 'me', 'i', 'we', 'want', 'need', 'looking', 'find', 'search', 'show', 'any', 'all', 'some', 'sale', 'sell', 'selling', 'buy', 'buying', 'rent', 'rental', 'rentals', 'renting', 'lease', 'leasing', 'property', 'properties', 'listing', 'listings', 'real', 'estate', 'sri', 'lanka', 'srilanka', 'lk', 'land', 'lands', 'plot', 'plots', 'available', 'new', 'good', 'nice', 'best', 'cheap', 'low', 'price', 'prices', 'rs', 'lkr', 'per', 'month', 'monthly', 'area', 'side', 'town', 'city', 'district' );

	/**
	 * Other words for the usual property types.
	 */
	const TYPE_WORDS = array(
		'house'       => 'House',
		'houses'      => 'House',
		'home'        => 'House',
		'homes'       => 'House',
		'bungalow'    => 'House',
		'bungalows'   => 'House',
		'villa'       => 'Villa',
		'villas'      => 'Villa',
		'apartment'   => 'Apartment',
		'apartments'  => 'Apartment',
		'flat'        => 'Apartment',
		'flats'       => 'Apartment',
		'condo'       => 'Apartment',
		'condos'      => 'Apartment',
		'condominium' => 'Apartment',
		'annex'       => 'Annex',
		'annexe'      => 'Annex',
		'annexes'     => 'Annex',
		'room'        => 'Room',
		'rooms'       => 'Room',
		'boarding'    => 'Room',
	);

	/**
	 * An amount as typed, such as 50, 50m, 2.5 million or 45 lakhs.
	 */
	const AMOUNT = '(\d+(?:\.\d+)?)\s*(k|thousand|l|lakh|lakhs|lac|lacs|m|mn|mil|million|millions|b|bn|billion)?\b';

	/**
	 * Texts understood already in this request.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * The text understood.
	 *
	 * @param string $text     What was typed.
	 * @param string $category Category slug, or empty: property types are only taken from its listings.
	 * @return array 'clause' (tax query for the places, or null), 'place' (the first place's name),
	 *               'towns', 'districts', 'type', 'beds', 'beds_max', 'price_min', 'price_max',
	 *               'size_min', 'size_max', 'furnishing' and 'words' (keywords).
	 */
	public static function read( $text, $category = '' ) {
		$text = trim( (string) $text );
		$key  = $category . '|' . $text;

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$found = array(
			'clause'     => null,
			'place'      => '',
			'towns'      => array(),
			'districts'  => array(),
			'type'       => '',
			'beds'       => 0,
			'beds_max'   => 0,
			'price_min'  => '',
			'price_max'  => '',
			'size_min'   => '',
			'size_max'   => '',
			'furnishing' => '',
			'words'      => array(),
		);

		if ( '' !== $text ) {
			$plain = self::numbers_and_ranges( $found, self::plain( $text ) );
			$words = self::places_and_types( $found, $plain, $category );
			$found = self::keywords( $found, $words );
		}

		self::$cache[ $key ] = $found;

		return $found;
	}

	/**
	 * The text in small letters, with commas in numbers and other marks taken out.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain( $text ) {
		$text = strtolower( remove_accents( wp_strip_all_tags( $text ) ) );
		$text = preg_replace( '/(?<=\d),(?=\d)/', '', $text );
		$text = preg_replace( '/[^a-z0-9\.\-+<>\s]/', ' ', $text );

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * An amount from a number and its word: 2.5 and "m" is 2,500,000.
	 *
	 * @param string $number Number.
	 * @param string $unit   k, lakh, m, b and the like, or empty.
	 * @return string Whole rupees, or empty.
	 */
	private static function amount( $number, $unit ) {
		$times = array(
			'k'        => 1e3,
			'thousand' => 1e3,
			'l'        => 1e5,
			'lakh'     => 1e5,
			'lakhs'    => 1e5,
			'lac'      => 1e5,
			'lacs'     => 1e5,
			'm'        => 1e6,
			'mn'       => 1e6,
			'mil'      => 1e6,
			'million'  => 1e6,
			'millions' => 1e6,
			'b'        => 1e9,
			'bn'       => 1e9,
			'billion'  => 1e9,
		);
		$value = (float) $number * ( isset( $times[ $unit ] ) ? $times[ $unit ] : 1 );

		return $value > 0 ? (string) (int) round( $value ) : '';
	}

	/**
	 * Takes bedrooms, prices, land sizes and furnishing out of the text.
	 *
	 * @param array  $found Found so far; filled in.
	 * @param string $text  Plain text.
	 * @return string What is left.
	 */
	private static function numbers_and_ranges( array &$found, $text ) {
		$rooms = '(?:bed|beds|bedroom|bedrooms|br|bhk|bd|bdr|room|rooms)';

		// Bedrooms: "2-4 bedrooms", "3 bed", "3br".
		if ( preg_match( '/\b(\d{1,2})\s*(?:-|to)\s*(\d{1,2})\s*' . $rooms . '\b/', $text, $match ) ) {
			$found['beds']     = min( 10, min( (int) $match[1], (int) $match[2] ) );
			$found['beds_max'] = min( 10, max( (int) $match[1], (int) $match[2] ) );
			$text              = str_replace( $match[0], ' ', $text );
		} elseif ( preg_match( '/\b(\d{1,2})\s*\+?\s*' . $rooms . '\b/', $text, $match ) ) {
			$found['beds'] = min( 10, (int) $match[1] );
			$text          = str_replace( $match[0], ' ', $text );
		}

		$money = '(?:rs\.?\s*|lkr\s*)?' . self::AMOUNT;

		// Prices: "between 10m and 20m", "under 50 lakhs", "over 20 million".
		if ( preg_match( '/\bbetween\s*' . $money . '\s*(?:and|to|-)\s*' . $money . '/', $text, $match ) ) {
			$found['price_min'] = self::amount( $match[1], isset( $match[2] ) ? $match[2] : '' );
			$found['price_max'] = self::amount( $match[3], isset( $match[4] ) ? $match[4] : '' );
			$text               = str_replace( $match[0], ' ', $text );
		}

		if ( preg_match( '/(?:\b(?:under|below|less\s+than|max|maximum|up\s*to|upto|within|budget(?:\s+of)?)|<)\s*' . $money . '/', $text, $match ) ) {
			$found['price_max'] = self::amount( $match[1], isset( $match[2] ) ? $match[2] : '' );
			$text               = str_replace( $match[0], ' ', $text );
		}

		if ( preg_match( '/(?:\b(?:over|above|more\s+than|min|minimum|at\s+least)|>)\s*' . $money . '/', $text, $match ) ) {
			$found['price_min'] = self::amount( $match[1], isset( $match[2] ) ? $match[2] : '' );
			$text               = str_replace( $match[0], ' ', $text );
		}

		// An amount with lakhs or millions on its own is the most to pay: "house kandy 50m".
		if ( '' === $found['price_max'] && preg_match( '/\b(\d+(?:\.\d+)?)\s*(k|lakh|lakhs|lac|lacs|m|mn|mil|million|millions|b|bn|billion)\b/', $text, $match ) ) {
			$found['price_max'] = self::amount( $match[1], $match[2] );
			$text               = str_replace( $match[0], ' ', $text );
		}

		// Land size: about so many perches or acres.
		if ( preg_match( '/\b(\d+(?:\.\d+)?)\s*(perch|perches|acre|acres|ac)\b/', $text, $match ) ) {
			$perches            = (float) $match[1] * ( 'p' === $match[2][0] ? 1 : 160 );
			$found['size_min']  = (string) floor( $perches * 0.75 );
			$found['size_max']  = (string) ceil( $perches * 1.25 );
			$text               = str_replace( $match[0], ' ', $text );
		}

		// Furnishing.
		if ( preg_match( '/\bun-?\s*furnished\b/', $text, $match ) ) {
			$found['furnishing'] = 'unfurnished';
		} elseif ( preg_match( '/\bsemi[\s-]*furnished\b/', $text, $match ) ) {
			$found['furnishing'] = 'semi_furnished';
		} elseif ( preg_match( '/\b(?:fully\s+)?furnished\b/', $text, $match ) ) {
			$found['furnishing'] = 'furnished';
		}

		if ( '' !== $found['furnishing'] ) {
			$text = str_replace( $match[0], ' ', $text );
		}

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Every town's name, small and squeezed together: name => term ID.
	 *
	 * @return int[]
	 */
	private static function town_names() {
		static $names = null;

		if ( null === $names ) {
			$names = array();
			$terms = get_terms(
				array(
					'taxonomy'   => Town::NAME,
					'hide_empty' => false,
					'fields'     => 'id=>name',
				)
			);

			if ( is_array( $terms ) ) {
				foreach ( $terms as $id => $term ) {
					$name = is_object( $term ) ? (string) $term->name : (string) $term;
					$id   = is_object( $term ) ? (int) $term->term_id : (int) $id;

					$names[ District::normalize( Town::tidy( $name ) ) ] = $id;
				}
			}
		}

		return $names;
	}

	/**
	 * The property types there are: small and squeezed together => name.
	 *
	 * @param string $category Category slug, or empty.
	 * @return string[]
	 */
	private static function type_names( $category ) {
		$names = array();

		foreach ( Listing_Search::property_types( $category ) as $type ) {
			$names[ District::normalize( $type ) ] = $type;
		}

		return $names;
	}

	/**
	 * Takes places and property types out of the text, a phrase of three
	 * words, two or one at a time.
	 *
	 * @param array  $found    Found so far; filled in.
	 * @param string $text     Text without the numbers.
	 * @param string $category Category slug, or empty.
	 * @return string[] Words left.
	 */
	private static function places_and_types( array &$found, $text, $category ) {
		$words = '' === $text ? array() : explode( ' ', $text );
		$used  = array();
		$towns = self::town_names();
		$types = self::type_names( $category );
		$all   = District::districts();

		for ( $size = 3; $size >= 1; $size-- ) {
			for ( $at = 0; $at + $size <= count( $words ); $at++ ) {
				$spots = range( $at, $at + $size - 1 );

				if ( array_intersect( $spots, $used ) ) {
					continue;
				}

				$phrase = implode( ' ', array_slice( $words, $at, $size ) );
				$key    = District::normalize( Town::tidy( $phrase ) );

				if ( strlen( $key ) < 3 || in_array( $phrase, self::SKIP, true ) ) {
					continue;
				}

				$district = District::find( $phrase );
				$matched  = false;

				if ( '' !== $district ) {
					$found['districts'][] = $district;
					$matched              = true;
				} elseif ( isset( $towns[ $key ] ) ) {
					$found['towns'][] = $towns[ $key ];
					$matched          = true;
				} elseif ( Town::zone( $phrase ) && Town::matching( $phrase ) ) {
					foreach ( array_keys( Town::matching( $phrase ) ) as $id ) {
						$found['towns'][] = (int) $id;
					}

					$matched = true;
				} elseif ( '' === $found['type'] && isset( $types[ $key ] ) ) {
					$found['type'] = $types[ $key ];
					$matched       = true;
				} elseif ( '' === $found['type'] && 1 === $size && array_key_exists( $phrase, self::TYPE_WORDS ) && in_array( self::TYPE_WORDS[ $phrase ], $types, true ) ) {
					$found['type'] = self::TYPE_WORDS[ $phrase ];
					$matched       = true;
				}

				if ( $matched ) {
					$used = array_merge( $used, $spots );
				}
			}
		}

		$left = array();

		foreach ( $words as $at => $word ) {
			if ( ! in_array( $at, $used, true ) ) {
				$left[] = $word;
			}
		}

		// The first place's name, as written on the site, e.g. for a heading.
		if ( $found['districts'] && ! $found['towns'] ) {
			$found['place'] = $all[ $found['districts'][0] ]['name'];
		} elseif ( $found['towns'] ) {
			$term           = get_term( $found['towns'][0], Town::NAME );
			$found['place'] = ( $term && ! is_wp_error( $term ) ) ? (string) $term->name : '';
		}

		return $left;
	}

	/**
	 * The words left become keywords. One word that is part of a place's
	 * name, such as "Hikka", stands for those places instead.
	 *
	 * @param array    $found Found so far.
	 * @param string[] $words Words left.
	 * @return array
	 */
	private static function keywords( array $found, array $words ) {
		$keywords = array();

		foreach ( $words as $word ) {
			$word = trim( $word, ".-+<> \t" );

			if ( strlen( $word ) < 2 || ctype_digit( $word ) || in_array( $word, self::SKIP, true ) ) {
				continue;
			}

			// Plurals find the singular too: "views" finds "view".
			if ( strlen( $word ) > 4 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
				$word = substr( $word, 0, -1 );
			}

			$keywords[] = $word;
		}

		$keywords = array_slice( array_values( array_unique( $keywords ) ), 0, 6 );
		$nothing  = ! $found['districts'] && ! $found['towns'] && '' === $found['type'];

		if ( $nothing && 1 === count( $keywords ) && strlen( $keywords[0] ) >= 3 ) {
			$part = Listing_Query::place( $keywords[0] );

			if ( $part['clause'] ) {
				$found['towns']     = $part['towns'];
				$found['districts'] = $part['districts'];
				$keywords           = array();
			}
		}

		$found['towns']     = array_values( array_unique( array_map( 'intval', $found['towns'] ) ) );
		$found['districts'] = array_values( array_unique( $found['districts'] ) );
		$found['words']     = $keywords;

		if ( $found['towns'] || $found['districts'] ) {
			$clause = array( 'relation' => 'OR' );

			if ( $found['towns'] ) {
				$clause[] = array(
					'taxonomy' => Town::NAME,
					'field'    => 'term_id',
					'terms'    => $found['towns'],
				);
			}

			if ( $found['districts'] ) {
				$clause[] = array(
					'taxonomy' => District::NAME,
					'field'    => 'slug',
					'terms'    => $found['districts'],
				);
			}

			$found['clause'] = $clause;

			if ( '' === $found['place'] ) {
				$all            = District::districts();
				$found['place'] = $found['districts'] ? $all[ $found['districts'][0] ]['name'] : '';

				if ( '' === $found['place'] && $found['towns'] ) {
					$term           = get_term( $found['towns'][0], Town::NAME );
					$found['place'] = ( $term && ! is_wp_error( $term ) ) ? (string) $term->name : '';
				}
			}
		}

		return $found;
	}

	/**
	 * Where a keyword is looked for: the meta of the property type,
	 * furnishing, features and details.
	 *
	 * @return string[] Meta keys.
	 */
	public static function meta_keys() {
		return array( '_crc_property_type', '_crc_furnishing', Sections\Features::META, Sections\Features::EXTRA_META, Sections\Features::GROUPS_META, Overview::MORE_META, Overview::EXTRA_META );
	}
}
