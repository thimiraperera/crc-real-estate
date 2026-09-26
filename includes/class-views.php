<?php
/**
 * Listing view counter.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Counts how many times each listing is viewed.
 *
 * Views are counted by a small script on the listing page, so the count keeps
 * working when pages are cached. Each browser counts once a day per listing,
 * and people who can edit the listing aren't counted.
 */
final class Views {

	const META       = '_crc_views';
	const REST_SPACE = 'crc-real-estate/v1';
	const WINDOW     = 86400; // Seconds before the same browser counts again.

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_' . Post_Type::NAME . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::NAME . '_sortable_columns', array( $this, 'sortable_column' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_views' ) );
	}

	/**
	 * Registers the view count field.
	 */
	public function register_meta() {
		register_post_meta(
			Post_Type::NAME,
			self::META,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * A listing's view count.
	 *
	 * @param int $post_id Listing ID.
	 * @return int
	 */
	public static function get( $post_id ) {
		return (int) get_post_meta( $post_id, self::META, true );
	}

	/**
	 * View count as text, e.g. "1,000 views".
	 *
	 * @param int $count View count.
	 * @return string
	 */
	public static function label( $count ) {
		/* translators: %s: number of views. */
		return sprintf( _n( '%s view', '%s views', $count, 'crc-real-estate' ), number_format_i18n( $count ) );
	}

	/**
	 * Registers the endpoint the listing page calls to count a view.
	 */
	public function register_route() {
		register_rest_route(
			self::REST_SPACE,
			'/listings/(?P<id>\d+)/views',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'record' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Adds one view to a published listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function record( $request ) {
		global $wpdb;

		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || Post_Type::NAME !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'crc_listing_not_found', __( 'Listing not found.', 'crc-real-estate' ), array( 'status' => 404 ) );
		}

		// Add one in the database directly, so views arriving together aren't lost.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
				$post_id,
				self::META
			)
		);

		wp_cache_delete( $post_id, 'post_meta' );

		if ( ! $updated ) {
			add_post_meta( $post_id, self::META, 1, true );
		}

		$count = self::get( $post_id );

		return array(
			'views' => $count,
			'label' => self::label( $count ),
		);
	}

	/**
	 * Loads the counting script on listing pages.
	 */
	public function enqueue() {
		if ( ! is_singular( Post_Type::NAME ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();

		if ( current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wp_enqueue_script(
			'crc-re-views',
			CRC_RE_URL . 'assets/js/views.js',
			array(),
			CRC_RE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			'crc-re-views',
			'window.crcReViews = ' . wp_json_encode(
				array(
					'id'     => $post_id,
					'url'    => rest_url( self::REST_SPACE . '/listings/' . $post_id . '/views' ),
					'window' => self::WINDOW,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Adds a Views column to the Listings list.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function add_column( $columns ) {
		$result = array();

		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;

			if ( 'title' === $key ) {
				$result['crc_views'] = __( 'Views', 'crc-real-estate' );
			}
		}

		return $result;
	}

	/**
	 * Prints the Views column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Listing ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'crc_views' === $column ) {
			echo esc_html( number_format_i18n( self::get( $post_id ) ) );
		}
	}

	/**
	 * Lets the Views column be sorted.
	 *
	 * @param string[] $columns Sortable columns.
	 * @return string[]
	 */
	public function sortable_column( $columns ) {
		$columns['crc_views'] = 'crc_views';

		return $columns;
	}

	/**
	 * Sorts the Listings list by views, keeping listings that have none.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function sort_by_views( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'crc_views' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set(
			'meta_query',
			array(
				'relation'  => 'OR',
				'crc_views' => array(
					'key'  => self::META,
					'type' => 'NUMERIC',
				),
				'crc_none'  => array(
					'key'     => self::META,
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set( 'orderby', 'crc_views' );
	}
}
