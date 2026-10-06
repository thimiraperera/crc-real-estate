<?php
/**
 * Where the listings are listed.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Where the search's filters and results show, and how they know what to show.
 *
 * Usually on the listing archives, designed as archive templates in a theme
 * builder: All Listings Archive at /listing/, and each category's
 * (/listings/lands/), district's (/district/galle/) and town's
 * (/town/hikkaduwa/) own archive. There the archive's own list of listings
 * follows the search in the address, so its numbered pages
 * (/listings/lands/page/2/) work as WordPress's always do.
 *
 * Or on a page chosen on Listings → Widgets → Search: then the archives show
 * that page with their category, district or town chosen, keeping their own
 * address and title for search engines.
 */
final class Listing_Archive {

	const OPTION   = 'crc_re_search';
	const PER_PAGE = 12;
	const TOP      = 32;

	/**
	 * The chosen page's ID, once found.
	 *
	 * @var int|null
	 */
	private static $page = null;

	/**
	 * With a chosen page: the archive shown on it, from its address.
	 *
	 * @var array|null 'category', 'district' or 'town' (a slug) and 'term', or 'archive'; and 'page'.
	 */
	private static $mapped = null;

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
		add_action( 'pre_get_posts', array( $this, 'filter_main_query' ) );
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
		add_action( 'wp_enqueue_scripts', array( $this, 'sticky_space' ), 21 );
	}

	/**
	 * The space above the filters while they stay in view, when it isn't the usual 32px.
	 */
	public function sticky_space() {
		$top = self::settings()['sticky_top'];

		if ( self::TOP !== $top && wp_style_is( 'crc-re-frontend', 'enqueued' ) ) {
			wp_add_inline_style( 'crc-re-frontend', 'body{--crc-filters-top:' . $top . 'px}' );
		}
	}

	/**
	 * The saved settings.
	 *
	 * @return array 'page': the chosen page's ID, or 0 for the listing archives;
	 *               'per_page': listings a page; 'sticky_top': the space above
	 *               the filters while they stay in view, in pixels.
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$per   = isset( $saved['per_page'] ) ? absint( $saved['per_page'] ) : 0;

		return array(
			'page'       => isset( $saved['page'] ) ? absint( $saved['page'] ) : 0,
			'per_page'   => $per ? min( Listing_Query::MAX_PER_PAGE, $per ) : self::PER_PAGE,
			'sticky_top' => isset( $saved['sticky_top'] ) && is_numeric( $saved['sticky_top'] ) ? min( 400, absint( $saved['sticky_top'] ) ) : self::TOP,
		);
	}

	/**
	 * Cleans the settings for saving.
	 *
	 * @param mixed $value Submitted settings.
	 * @return array
	 */
	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$page  = isset( $value['page'] ) ? absint( $value['page'] ) : 0;
		$per   = isset( $value['per_page'] ) ? absint( $value['per_page'] ) : 0;

		return array(
			'page'       => ( $page && 'page' === get_post_type( $page ) ) ? $page : 0,
			'per_page'   => $per ? min( Listing_Query::MAX_PER_PAGE, $per ) : self::PER_PAGE,
			'sticky_top' => isset( $value['sticky_top'] ) && is_numeric( $value['sticky_top'] ) ? min( 400, absint( $value['sticky_top'] ) ) : self::TOP,
		);
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
		self::$mapped  = null;
		self::$filters = null;
	}

	/**
	 * Listings a page.
	 *
	 * @return int
	 */
	public static function per_page() {
		return self::settings()['per_page'];
	}

	/**
	 * The chosen page's ID, while it is published.
	 *
	 * @return int 0 when the listing archives are used.
	 */
	public static function page_id() {
		if ( null === self::$page ) {
			$id = self::settings()['page'];
			$id = ( $id && 'publish' === get_post_status( $id ) && 'page' === get_post_type( $id ) ) ? $id : 0;

			/**
			 * Filters the page the listings show on; 0 for the listing archives.
			 *
			 * @param int $id Page ID, or 0.
			 */
			self::$page = (int) apply_filters( 'crc_re_listings_page', $id );
		}

		return self::$page;
	}

	/**
	 * The archive of every listing, /listing/.
	 *
	 * @return string
	 */
	public static function archive_url() {
		$link = get_post_type_archive_link( Post_Type::NAME );

		return $link ? (string) $link : home_url( '/listing/' );
	}

	/**
	 * Where every listing is listed: the chosen page, or the archive of every listing.
	 *
	 * @return string
	 */
	public static function page_url() {
		$id = self::page_id();

		return $id ? (string) get_permalink( $id ) : self::archive_url();
	}

	/**
	 * A category's listings: its own archive, such as /listings/lands/.
	 *
	 * @param string $slug Category slug, or empty for every listing.
	 * @return string
	 */
	public static function category_url( $slug ) {
		if ( '' === (string) $slug ) {
			return self::page_url();
		}

		$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );

			if ( ! is_wp_error( $link ) ) {
				return (string) $link;
			}
		}

		return add_query_arg( 'category', rawurlencode( $slug ), self::page_url() );
	}

	/**
	 * The taxonomies with archives the search knows, and what each one is.
	 *
	 * @return string[] Taxonomy => 'category', 'district' or 'town'.
	 */
	private static function taxonomies() {
		return array(
			Taxonomy::NAME => 'category',
			District::NAME => 'district',
			Town::NAME     => 'town',
		);
	}

	/**
	 * Which listing archive a query is for.
	 *
	 * @param \WP_Query|null $query Query.
	 * @return array|null 'archive' for every listing, or 'category', 'district' or
	 *                    'town' (a slug) with its 'term'; and 'page'. Null for anything else.
	 */
	public static function archive_context( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'is_post_type_archive' ) ) {
			return null;
		}

		$page = max( 1, (int) $query->get( 'paged' ) );

		if ( $query->is_post_type_archive( Post_Type::NAME ) ) {
			return array(
				'archive' => true,
				'page'    => $page,
			);
		}

		foreach ( self::taxonomies() as $taxonomy => $key ) {
			if ( $query->is_tax( $taxonomy ) ) {
				$term = $query->get_queried_object();

				if ( is_object( $term ) && isset( $term->slug, $term->taxonomy ) && $taxonomy === $term->taxonomy ) {
					return array(
						$key   => (string) $term->slug,
						'term' => $term,
						'page' => $page,
					);
				}
			}
		}

		return null;
	}

	/**
	 * The main query of the page being shown.
	 *
	 * @return \WP_Query|null
	 */
	private static function main_query() {
		return isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : null;
	}

	/**
	 * Whether the page being shown is a listing archive, as the listings show
	 * there (no page is chosen).
	 *
	 * @return bool
	 */
	public static function on_archive() {
		return ! self::page_id() && null !== self::archive_context( self::main_query() );
	}

	/**
	 * Whether the page being shown lists listings: a listing archive, or the chosen page.
	 *
	 * @return bool
	 */
	public static function is_listings_page() {
		$page = self::page_id();

		return $page ? is_page( $page ) : self::on_archive();
	}

	/**
	 * The archive being shown: from its main query, or, with a chosen page,
	 * from the address the page is shown on.
	 *
	 * @return array Empty on any other page.
	 */
	public static function context() {
		if ( self::page_id() ) {
			return is_array( self::$mapped ) ? self::$mapped : array();
		}

		$context = self::archive_context( self::main_query() );

		return is_array( $context ) ? $context : array();
	}

	/**
	 * With a chosen page, shows it on the listing archives (and their next
	 * pages), keeping their address. Feeds and anything else asked of them
	 * stay as WordPress has them.
	 *
	 * @param array $vars Query variables from the address.
	 * @return array
	 */
	public function map_request( $vars ) {
		$page = is_admin() || ! is_array( $vars ) ? 0 : self::page_id();

		if ( ! $page ) {
			return $vars;
		}

		$keys = self::taxonomies();

		if ( isset( $vars['post_type'] ) && Post_Type::NAME === $vars['post_type'] && ! array_diff( array_keys( $vars ), array( 'post_type', 'paged' ) ) ) {
			self::$filters = null;
			self::$mapped  = array(
				'archive' => true,
				'page'    => isset( $vars['paged'] ) ? max( 1, (int) $vars['paged'] ) : 1,
			);

			return array( 'page_id' => $page );
		}

		$found = array_intersect_key( $vars, $keys );

		if ( 1 !== count( $found ) || array_diff( array_keys( $vars ), array_merge( array_keys( $keys ), array( 'paged' ) ) ) || ! is_string( reset( $found ) ) ) {
			return $vars;
		}

		$taxonomy = key( $found );
		$path     = explode( '/', trim( (string) reset( $found ), '/' ) );
		$term     = get_term_by( 'slug', sanitize_title( end( $path ) ), $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return $vars;
		}

		self::$filters = null;
		self::$mapped  = array(
			$keys[ $taxonomy ] => (string) $term->slug,
			'term'             => $term,
			'page'             => isset( $vars['paged'] ) ? max( 1, (int) $vars['paged'] ) : 1,
		);

		return array( 'page_id' => $page );
	}

	/**
	 * On a listing archive, its own list of listings follows the search in
	 * the address: the place, the choices, the order and how many a page.
	 * Its own category, district or town stays as WordPress has it.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filter_main_query( $query ) {
		if ( is_admin() || ! is_object( $query ) || ! $query->is_main_query() || $query->is_feed() || self::page_id() ) {
			return;
		}

		$context = self::archive_context( $query );

		if ( null === $context ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A search, read only.
		$filters       = Listing_Query::read( $_GET, $context );
		self::$filters = $filters;
		$own           = $filters;

		foreach ( array( 'category', 'district', 'town' ) as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$own[ $key ] = '';
			}
		}

		foreach ( Listing_Query::args( $own, self::per_page() ) as $key => $value ) {
			if ( 'paged' !== $key ) {
				$query->set( $key, $value );
			}
		}
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
	 * archive being shown, or the page being viewed.
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

		if ( ! empty( $context['archive'] ) ) {
			return self::archive_url();
		}

		$id = (int) get_queried_object_id();

		return ( $id && is_singular() ) ? (string) get_permalink( $id ) : self::page_url();
	}

	/**
	 * The address of the page being shown with some filters changed. On a
	 * listing archive the pages are WordPress's own (/page/2/); elsewhere
	 * they are ?pg=2.
	 *
	 * @param array $changes Filter name => new value.
	 * @return string
	 */
	public static function link( array $changes = array() ) {
		global $wp_rewrite;

		$filters = array_merge( self::filters(), $changes );
		$base    = self::base_url();

		if ( ! self::on_archive() ) {
			return add_query_arg( array_map( 'rawurlencode', Listing_Query::params( $filters ) ), $base );
		}

		$page = max( 1, (int) $filters['page'] );

		if ( $page > 1 ) {
			$base = ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() )
				? user_trailingslashit( trailingslashit( $base ) . $wp_rewrite->pagination_base . '/' . $page, 'paged' )
				: add_query_arg( 'paged', $page, $base );
		}

		return add_query_arg( array_map( 'rawurlencode', Listing_Query::params( $filters, array( 'page' ) ) ), $base );
	}

	/**
	 * The address the search leads to for some filters: the category's
	 * archive with the other filters after it.
	 *
	 * @param array $filters Filters, see Listing_Query::blank().
	 * @return string
	 */
	public static function search_url( array $filters ) {
		$params = Listing_Query::params( $filters );

		// A district's or town's archive searched again keeps its place.
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
	 * to that category's own archive with the same filters.
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
	 * Whether the chosen page is being shown. Archives have their own titles
	 * and addresses, so only the chosen page needs them changed.
	 *
	 * @return bool
	 */
	private static function on_chosen_page() {
		$page = self::page_id();

		return $page && is_page( $page );
	}

	/**
	 * The browser tab's title on the chosen page: what is being looked at.
	 *
	 * @param string[] $parts Title parts.
	 * @return string[]
	 */
	public function title_parts( $parts ) {
		if ( ! self::on_chosen_page() ) {
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
	 * The address search engines are given for an archive shown on the
	 * chosen page: the archive's own, not the page's.
	 *
	 * @return string An empty string on other pages.
	 */
	private static function own_url() {
		$context = self::context();

		if ( ! self::on_chosen_page() || ( ! isset( $context['term'] ) && empty( $context['archive'] ) ) ) {
			return '';
		}

		$link = isset( $context['term'] ) ? get_term_link( $context['term'] ) : self::archive_url();

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
	 * Yoast SEO's and Rank Math's title on the chosen page: the page's title
	 * becomes what is being looked at.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function seo_title( $title ) {
		if ( ! self::on_chosen_page() ) {
			return $title;
		}

		$heading = self::heading( self::filters() );
		$page    = get_post( self::page_id() );
		$own     = $page ? get_the_title( $page ) : '';

		return ( '' !== $heading && '' !== $own && false !== strpos( (string) $title, $own ) ) ? str_replace( $own, $heading, (string) $title ) : $title;
	}
}
