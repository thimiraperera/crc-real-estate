<?php
/**
 * Numbers and lists kept ready for the search.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Overview;
use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps two numbers ready on each listing for the search: the land extent in
 * perches, whatever unit it was typed in, and the price per perch (from the
 * Price box, or worked out from the price and the land extent). Also keeps,
 * for each category, which towns, districts and property types published
 * listings have, for the search's suggestions and choices. Everything is
 * worked out again when a listing changes, so there is nothing to type.
 */
final class Listing_Index {

	const PERCHES   = '_crc_search_perches';
	const PER_PERCH = '_crc_search_per_perch';
	const FACETS    = 'crc_re_facets_';
	const VERSION   = 1;

	/**
	 * Whether the lists are cleared at the end of this request.
	 *
	 * @var bool
	 */
	private static $dirty = false;

	/**
	 * The saved details the two numbers come from.
	 *
	 * @return string[]
	 */
	public static function sources() {
		return array( '_crc_land_extent', '_crc_land_extent_unit', '_crc_extent_perches', Price_Card::PRICE_META, Price_Card::PER_PERCH_META );
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'added_post_meta', array( $this, 'meta_changed' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'meta_changed' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'meta_changed' ), 10, 3 );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'saved' ), 99 );
		add_action( 'set_object_terms', array( $this, 'terms_changed' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'deleted' ) );
		add_action( 'edited_' . Town::NAME, array( __CLASS__, 'changed' ) );
		add_action( 'delete_' . Town::NAME, array( __CLASS__, 'changed' ) );
	}

	/**
	 * A listing's detail changed: works the numbers out again when it is one they come from.
	 *
	 * @param int|int[] $meta_id  Meta ID.
	 * @param int       $post_id  Post ID.
	 * @param string    $meta_key Meta key.
	 */
	public function meta_changed( $meta_id, $post_id, $meta_key ) {
		$from = in_array( $meta_key, self::sources(), true );

		if ( ( ! $from && '_crc_property_type' !== $meta_key ) || Post_Type::NAME !== get_post_type( $post_id ) ) {
			return;
		}

		if ( $from ) {
			self::refresh( $post_id );
		}

		self::changed();
	}

	/**
	 * A listing was saved.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function saved( $post_id ) {
		self::refresh( $post_id );
		self::changed();
	}

	/**
	 * A listing's category, district or town changed.
	 *
	 * @param int    $object_id Post ID.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 */
	public function terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( Taxonomy::NAME, District::NAME, Town::NAME ), true ) && Post_Type::NAME === get_post_type( $object_id ) ) {
			self::changed();
		}
	}

	/**
	 * A listing went live or was taken off the site.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function status_changed( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && isset( $post->post_type ) && Post_Type::NAME === $post->post_type && in_array( 'publish', array( $new_status, $old_status ), true ) ) {
			self::changed();
		}
	}

	/**
	 * A listing is about to be deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function deleted( $post_id ) {
		if ( Post_Type::NAME === get_post_type( $post_id ) ) {
			self::changed();
		}
	}

	/**
	 * Clears the lists once, at the end of the request, however much changed in it.
	 */
	public static function changed() {
		if ( ! self::$dirty ) {
			self::$dirty = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Clears the lists, and the listings page from the LiteSpeed cache.
	 */
	public static function flush() {
		foreach ( array_merge( array( 'all' ), array_keys( Taxonomy::terms() ) ) as $slug ) {
			delete_transient( self::FACETS . $slug );
		}

		self::$dirty = false;
		$page        = Listing_Archive::page_id();

		if ( $page ) {
			do_action( 'litespeed_purge_post', $page );
		}
	}

	/**
	 * Works a listing's two numbers out again.
	 *
	 * @param int $post_id Listing ID.
	 */
	public static function refresh( $post_id ) {
		$perches = Overview::land_perches( $post_id );

		if ( $perches <= 0 ) {
			$typed   = Overview::sanitize_number( get_post_meta( $post_id, '_crc_extent_perches', true ) );
			$perches = '' !== $typed ? (float) $typed : 0.0;
		}

		self::put( $post_id, self::PERCHES, $perches > 0 ? rtrim( rtrim( number_format( $perches, 2, '.', '' ), '0' ), '.' ) : '' );

		$per_perch = Price_Card::price_per_perch( $post_id );

		if ( '' === $per_perch ) {
			$price     = Price_Card::price( $post_id );
			$per_perch = ( '' !== $price && $perches > 0 ) ? number_format( round( (float) $price / $perches ), 0, '.', '' ) : '';
		}

		self::put( $post_id, self::PER_PERCH, '0' === $per_perch ? '' : $per_perch );
	}

	/**
	 * Saves a number, or removes it when it is empty, only when it changed.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $key     Meta key.
	 * @param string $value   Number.
	 */
	private static function put( $post_id, $key, $value ) {
		$saved = (string) get_post_meta( $post_id, $key, true );

		if ( '' === $value ) {
			if ( '' !== $saved ) {
				delete_post_meta( $post_id, $key );
			}
		} elseif ( $saved !== $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Works out every listing's numbers, after an update that adds them.
	 */
	public static function backfill() {
		$ids = get_posts(
			array(
				'post_type'        => Post_Type::NAME,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		foreach ( array_chunk( array_map( 'intval', (array) $ids ), 100 ) as $chunk ) {
			update_meta_cache( 'post', $chunk );

			foreach ( $chunk as $post_id ) {
				self::refresh( $post_id );
			}
		}

		self::flush();
	}

	/**
	 * What a category's published listings have: how many listings each
	 * town (by term ID), district (by slug) and property type has.
	 *
	 * @param string $category Category slug, or empty for every listing.
	 * @return array 'total', 'towns', 'districts' and 'types' (lower case => 'name' and 'count').
	 */
	public static function facets( $category = '' ) {
		$key    = self::FACETS . ( '' !== $category ? $category : 'all' );
		$cached = get_transient( $key );

		if ( is_array( $cached ) && isset( $cached['version'] ) && self::VERSION === $cached['version'] ) {
			return $cached;
		}

		$facets = array(
			'version'   => self::VERSION,
			'total'     => 0,
			'towns'     => array(),
			'districts' => array(),
			'types'     => array(),
		);
		$args   = array(
			'post_type'        => Post_Type::NAME,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'has_password'     => false,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		);

		if ( '' !== $category ) {
			$term = get_term_by( 'slug', $category, Taxonomy::NAME );

			if ( ! $term || is_wp_error( $term ) ) {
				return $facets;
			}

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- Kept for 12 hours.
			$args['tax_query'] = array(
				array(
					'taxonomy' => Taxonomy::NAME,
					'field'    => 'term_id',
					'terms'    => array( (int) $term->term_id ),
				),
			);
		}

		$ids               = array_map( 'intval', (array) get_posts( $args ) );
		$facets['total'] = count( $ids );

		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			update_object_term_cache( $chunk, Post_Type::NAME );
			update_meta_cache( 'post', $chunk );

			foreach ( $chunk as $post_id ) {
				$district = District::of( $post_id );
				$town     = Town::of( $post_id );
				$type     = trim( (string) get_post_meta( $post_id, '_crc_property_type', true ) );

				if ( $district ) {
					$facets['districts'][ $district['slug'] ] = ( isset( $facets['districts'][ $district['slug'] ] ) ? $facets['districts'][ $district['slug'] ] : 0 ) + 1;
				}

				if ( $town ) {
					$id                       = (int) $town['term']->term_id;
					$facets['towns'][ $id ] = ( isset( $facets['towns'][ $id ] ) ? $facets['towns'][ $id ] : 0 ) + 1;
				}

				if ( '' !== $type ) {
					$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $type ) : strtolower( $type );

					if ( ! isset( $facets['types'][ $lower ] ) ) {
						$facets['types'][ $lower ] = array(
							'name'  => $type,
							'count' => 0,
						);
					}

					++$facets['types'][ $lower ]['count'];
				}
			}
		}

		set_transient( $key, $facets, 12 * HOUR_IN_SECONDS );

		return $facets;
	}
}
