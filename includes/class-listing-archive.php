<?php
/**
 * The listings page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The page that lists listings, with the search filters and results on it:
 * the page chosen on Listings → Widgets → Search, or else the page at
 * /listing/. Category pages (/listings/lands/), district pages
 * (/district/galle/) and town pages (/town/hikkaduwa/) show this page with
 * their category, district or town chosen, like a normal WordPress archive,
 * with their own title and their own address for search engines.
 */
final class Listing_Archive {

	const OPTION = 'crc_re_search';

	/**
	 * The listings page's ID, once found.
	 *
	 * @var int|null
	 */
	private static $page = null;

	/**
	 * The category, district or town page being shown, from its address.
	 *
	 * @var array|null 'category', 'district' or 'town' (a slug), 'page' and 'term'.
	 */
	private static $context = null;

	/**
	 * The current request's filters, once read.
	 *
	 * @var array|null
	 */
	private static $filters = null;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'request', array( $this, 'map_request' ) );
		add_action( 'template_redirect', array( $this, 'redirect_category' ), 5 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( $this, 'seo_canonical' ) );
		add_filter( 'wpseo_opengraph_url', array( $this, 'seo_canonical' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'seo_canonical' ) );
		add_filter( 'wpseo_title', array( $this, 'seo_title' ) );
		add_filter( 'wpseo_opengraph_title', array( $this, 'seo_title' ) );
		add_filter( 'rank_math/frontend/title', array( $this, 'seo_title' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'saved' ) );
		add_action( 'add_option_' . self::OPTION, array( $this, 'saved' ) );
	}

	/**
	 * The saved settings.
	 *
	 * @return array 'page': the listings page's ID, or 0 to use the page at /listing/.
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );

		return array( 'page' => is_array( $saved ) && isset( $saved['page'] ) ? absint( $saved['page'] ) : 0 );
	}

	/**
	 * Cleans the settings for saving.
	 *
	 * @param mixed $value Submitted settings.
	 * @return array
	 */
	public static function sanitize( $value ) {
		$page = is_array( $value ) && isset( $value['page'] ) ? absint( $value['page'] ) : 0;

		return array( 'page' => ( $page && 'page' === get_post_type( $page ) ) ? $page : 0 );
	}

	/**
	 * The new settings show straight away, also to visitors of cached pages.
	 */
	public function saved() {
		self::reset();
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Forgets what was found, so it is looked up again.
	 */
	public static function reset() {
		self::$page    = null;
		self::$context = null;
		self::$filters = null;
	}

	/**
	 * Where the listings page is looked for when none is chosen.
	 *
	 * @return string Page path, e.g. "listing".
	 */
	public static function path() {
		/**
		 * Filters where the listings page is looked for when none is chosen.
		 *
		 * @param string $path Page path.
		 */
		return trim( (string) apply_filters( 'crc_re_listings_page_path', 'listing' ), '/' );
	}

	/**
	 * The listings page's ID: the page chosen in the settings, or else the
	 * published page at /listing/.
	 *
	 * @return int 0 when there is none.
	 */
	public static function page_id() {
		if ( null !== self::$page ) {
			return self::$page;
		}

		$id = self::settings()['page'];

		if ( ! $id || 'publish' !== get_post_status( $id ) || 'page' !== get_post_type( $id ) ) {
			$page = get_page_by_path( self::path() );
			$id   = ( $page && 'publish' === $page->post_status ) ? (int) $page->ID : 0;
		}

		/**
		 * Filters the listings page's ID.
		 *
		 * @param int $id Page ID, or 0 for none.
		 */
		self::$page = (int) apply_filters( 'crc_re_listings_page', $id );

		return self::$page;
	}

	/**
	 * The listings page's address.
	 *
	 * @return string
	 */
	public static function page_url() {
		$id = self::page_id();

		return $id ? (string) get_permalink( $id ) : home_url( '/' . self::path() . '/' );
	}

	/**
	 * A category's listings: its own page, such as /listings/lands/, which
	 * shows the listings page with the category chosen.
	 *
	 * @param string $slug Category slug, or empty for every listing.
	 * @return string
	 */
	public static function category_url( $slug ) {
		if ( '' === (string) $slug ) {
			return self::page_url();
		}

		$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

		if ( self::page_id() && $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				return (string) $link;
			}
		}

		return add_query_arg( 'category', rawurlencode( $slug ), self::page_url() );
	}

	/**
	 * Shows the listings page on a category's, district's or town's own page
	 * (and its next pages), keeping the address. Feeds and anything else
	 * asked of those pages stay as WordPress has them.
	 *
	 * @param array $vars Query variables from the address.
	 * @return array
	 */
	public function map_request( $vars ) {
		if ( is_admin() || ! is_array( $vars ) ) {
			return $vars;
		}

		$keys  = array(
			Taxonomy::NAME => 'category',
			District::NAME => 'district',
			Town::NAME     => 'town',
		);
		$found = array_intersect_key( $vars, $keys );

		if ( 1 !== count( $found ) || array_diff( array_keys( $vars ), array_merge( array_keys( $keys ), array( 'paged' ) ) ) || ! is_string( reset( $found ) ) ) {
			return $vars;
		}

		$page = self::page_id();

		if ( ! $page ) {
			return $vars;
		}

		$taxonomy = key( $found );
		$path     = explode( '/', trim( (string) reset( $found ), '/' ) );
		$term     = get_term_by( 'slug', sanitize_title( end( $path ) ), $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return $vars;
		}

		self::$filters = null;
		self::$context = array(
			$keys[ $taxonomy ] => (string) $term->slug,
			'page'             => isset( $vars['paged'] ) ? max( 1, (int) $vars['paged'] ) : 1,
			'term'             => $term,
		);

		return array( 'page_id' => $page );
	}

	/**
	 * The category, district or town page being shown.
	 *
	 * @return array Empty on any other page.
	 */
	public static function context() {
		return is_array( self::$context ) ? self::$context : array();
	}

	/**
	 * Whether the page being shown is the listings page, on its own address
	 * or a category's, district's or town's.
	 *
	 * @return bool
	 */
	public static function is_listings_page() {
		$page = self::page_id();

		return $page && is_page( $page );
	}

	/**
	 * The filters of the page being shown.
	 *
	 * @return array Filters, see Listing_Query::blank().
	 */
	public static function filters() {
		if ( null === self::$filters ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A search, read only.
			self::$filters = Listing_Query::read( $_GET, self::context() );
		}

		return self::$filters;
	}

	/**
	 * Where the page's own links (its pages and its order) start from: the
	 * category's, district's or town's page, or the page being viewed.
	 *
	 * @return string
	 */
	public static function base_url() {
		$context = self::context();

		if ( isset( $context['term'] ) ) {
			$link = get_term_link( $context['term'] );

			if ( ! is_wp_error( $link ) ) {
				return (string) $link;
			}
		}

		$id = (int) get_queried_object_id();

		return ( $id && is_singular() ) ? (string) get_permalink( $id ) : self::page_url();
	}

	/**
	 * The address of the page being shown with some filters changed.
	 *
	 * @param array $changes Filter name => new value.
	 * @return string
	 */
	public static function link( array $changes = array() ) {
		return add_query_arg( array_map( 'rawurlencode', Listing_Query::params( array_merge( self::filters(), $changes ) ) ), self::base_url() );
	}

	/**
	 * The address the search leads to for some filters: the category's
	 * page with the other filters after it.
	 *
	 * @param array $filters Filters, see Listing_Query::blank().
	 * @return string
	 */
	public static function search_url( array $filters ) {
		$params = Listing_Query::params( $filters );

		// A district's or town's page searched again keeps its place.
		if ( ! isset( $params['location'] ) ) {
			$place = self::place_name( $filters );

			if ( '' !== $place ) {
				$params = array_merge( array( 'location' => $place ), $params );
			}
		}

		return add_query_arg( array_map( 'rawurlencode', $params ), self::category_url( $filters['category'] ) );
	}

	/**
	 * A search with ?category=… on it (from a browser without scripts) goes
	 * to that category's own page with the same filters.
	 */
	public function redirect_category() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A search, read only.
		if ( ! isset( $_GET['category'] ) || ! self::is_listings_page() ) {
			return;
		}

		wp_safe_redirect( self::search_url( self::filters() ), 302 );
		exit;
	}

	/**
	 * The place a search is for, as people write it: the district's or the
	 * town's name, or the place as typed, tidied.
	 *
	 * @param array $filters Filters, see Listing_Query::blank().
	 * @return string
	 */
	public static function place_name( array $filters ) {
		if ( '' !== $filters['location'] ) {
			$district = District::find( $filters['location'] );
			$all      = District::districts();

			if ( '' !== $district && ! Town::find( $filters['location'] ) ) {
				return $all[ $district ]['name'];
			}

			$town = Town::find( $filters['location'] );

			return $town ? (string) $town->name : Town::tidy( $filters['location'] );
		}

		if ( '' !== $filters['town'] ) {
			$town = get_term_by( 'slug', $filters['town'], Town::NAME );

			if ( $town && ! is_wp_error( $town ) ) {
				return (string) $town->name;
			}
		}

		if ( '' !== $filters['district'] ) {
			$term = get_term_by( 'slug', $filters['district'], District::NAME );

			return ( $term && ! is_wp_error( $term ) ) ? (string) $term->name : '';
		}

		return '';
	}

	/**
	 * A heading for the results: "Land to buy in Galle", "Listings in
	 * Galle", "Land to buy", or the given text when nothing is chosen.
	 *
	 * @param array  $filters Filters, see Listing_Query::blank().
	 * @param string $all     Text when nothing is chosen.
	 * @return string
	 */
	public static function heading( array $filters, $all = '' ) {
		$name  = '';
		$place = self::place_name( $filters );

		if ( '' !== $filters['category'] ) {
			$term = get_term_by( 'slug', $filters['category'], Taxonomy::NAME );
			$name = ( $term && ! is_wp_error( $term ) ) ? (string) $term->name : '';
		}

		if ( '' === $name ) {
			$name = '' !== $place ? __( 'Listings', 'crc-real-estate' ) : (string) $all;
		}

		if ( '' === $name || '' === $place ) {
			return $name;
		}

		/* translators: 1: category, e.g. "Land to buy", 2: place, e.g. "Galle". */
		return sprintf( __( '%1$s in %2$s', 'crc-real-estate' ), $name, $place );
	}

	/**
	 * The browser tab's title on the listings page: what is being looked at.
	 *
	 * @param string[] $parts Title parts.
	 * @return string[]
	 */
	public function title_parts( $parts ) {
		if ( ! self::is_listings_page() ) {
			return $parts;
		}

		$filters = self::filters();
		$heading = self::heading( $filters );

		if ( '' !== $heading ) {
			$parts['title'] = $heading;
		}

		if ( $filters['page'] > 1 ) {
			/* translators: %s: page number. */
			$parts['page'] = sprintf( __( 'Page %s', 'crc-real-estate' ), number_format_i18n( $filters['page'] ) );
		}

		return $parts;
	}

	/**
	 * The address search engines are given for a category's, district's or
	 * town's page: its own, not the listings page's.
	 *
	 * @return string An empty string on other pages.
	 */
	private static function own_url() {
		$context = self::context();

		if ( ! isset( $context['term'] ) || ! self::is_listings_page() ) {
			return '';
		}

		$link = get_term_link( $context['term'] );

		if ( is_wp_error( $link ) ) {
			return '';
		}

		$page = self::filters()['page'];

		return $page > 1 ? add_query_arg( 'pg', $page, $link ) : (string) $link;
	}

	/**
	 * WordPress's address for search engines.
	 *
	 * @param string   $url  Address.
	 * @param \WP_Post $post Page.
	 * @return string
	 */
	public function canonical( $url, $post ) {
		$own = ( $post && (int) $post->ID === self::page_id() ) ? self::own_url() : '';

		return '' !== $own ? $own : $url;
	}

	/**
	 * Yoast SEO's and Rank Math's address for search engines.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	public function seo_canonical( $url ) {
		$own = self::own_url();

		return '' !== $own ? $own : $url;
	}

	/**
	 * Yoast SEO's and Rank Math's title: the listings page's title becomes
	 * what is being looked at.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function seo_title( $title ) {
		if ( ! self::is_listings_page() ) {
			return $title;
		}

		$heading = self::heading( self::filters() );
		$page    = get_post( self::page_id() );
		$own     = $page ? get_the_title( $page ) : '';

		return ( '' !== $heading && '' !== $own && false !== strpos( (string) $title, $own ) ) ? str_replace( $own, $heading, (string) $title ) : $title;
	}
}
