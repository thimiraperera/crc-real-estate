<?php
/**
 * Districts.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Sri Lanka's 25 districts as a fixed list of WordPress terms. A listing's
 * district shows under its title, goes into its suggested name, and is picked
 * on the listing screen from a search box, or filled in from the place found
 * on the map. Like the categories, the list can't be added to or deleted from.
 */
final class District {

	const NAME = 'crc_listing_district';
	const AJAX = 'crc_re_districts';

	/**
	 * Whether the plugin itself is creating its districts.
	 *
	 * @var bool
	 */
	private static $creating = false;

	/**
	 * The districts, in alphabetical order, keyed by slug.
	 *
	 * - name:     district name.
	 * - province: its province.
	 * - code:     its ISO 3166-2 code, which maps use, e.g. LK-31 for Galle.
	 * - aliases:  other spellings and names people type.
	 *
	 * @return array[]
	 */
	public static function districts() {
		$western       = __( 'Western Province', 'crc-real-estate' );
		$central       = __( 'Central Province', 'crc-real-estate' );
		$southern      = __( 'Southern Province', 'crc-real-estate' );
		$northern      = __( 'Northern Province', 'crc-real-estate' );
		$eastern       = __( 'Eastern Province', 'crc-real-estate' );
		$north_western = __( 'North Western Province', 'crc-real-estate' );
		$north_central = __( 'North Central Province', 'crc-real-estate' );
		$uva           = __( 'Uva Province', 'crc-real-estate' );
		$sabaragamuwa  = __( 'Sabaragamuwa Province', 'crc-real-estate' );

		/**
		 * Filters the districts, e.g. to use another country's on another site.
		 *
		 * @param array[] $districts Slug => 'name', 'province', 'code' and 'aliases'.
		 */
		$districts = apply_filters(
			'crc_re_districts',
			array(
				'ampara'       => array( __( 'Ampara', 'crc-real-estate' ), $eastern, 'LK-52', array( 'Digamadulla' ) ),
				'anuradhapura' => array( __( 'Anuradhapura', 'crc-real-estate' ), $north_central, 'LK-71', array( 'Anuradapura' ) ),
				'badulla'      => array( __( 'Badulla', 'crc-real-estate' ), $uva, 'LK-81', array() ),
				'batticaloa'   => array( __( 'Batticaloa', 'crc-real-estate' ), $eastern, 'LK-51', array( 'Madakalapuwa' ) ),
				'colombo'      => array( __( 'Colombo', 'crc-real-estate' ), $western, 'LK-11', array() ),
				'galle'        => array( __( 'Galle', 'crc-real-estate' ), $southern, 'LK-31', array() ),
				'gampaha'      => array( __( 'Gampaha', 'crc-real-estate' ), $western, 'LK-12', array() ),
				'hambantota'   => array( __( 'Hambantota', 'crc-real-estate' ), $southern, 'LK-33', array( 'Hambanthota' ) ),
				'jaffna'       => array( __( 'Jaffna', 'crc-real-estate' ), $northern, 'LK-41', array( 'Yapanaya' ) ),
				'kalutara'     => array( __( 'Kalutara', 'crc-real-estate' ), $western, 'LK-13', array( 'Kaluthara' ) ),
				'kandy'        => array( __( 'Kandy', 'crc-real-estate' ), $central, 'LK-21', array( 'Mahanuwara' ) ),
				'kegalle'      => array( __( 'Kegalle', 'crc-real-estate' ), $sabaragamuwa, 'LK-92', array( 'Kegalla' ) ),
				'kilinochchi'  => array( __( 'Kilinochchi', 'crc-real-estate' ), $northern, 'LK-42', array( 'Kilinochi' ) ),
				'kurunegala'   => array( __( 'Kurunegala', 'crc-real-estate' ), $north_western, 'LK-61', array( 'Kurunagala' ) ),
				'mannar'       => array( __( 'Mannar', 'crc-real-estate' ), $northern, 'LK-43', array() ),
				'matale'       => array( __( 'Matale', 'crc-real-estate' ), $central, 'LK-22', array() ),
				'matara'       => array( __( 'Matara', 'crc-real-estate' ), $southern, 'LK-32', array() ),
				'monaragala'   => array( __( 'Monaragala', 'crc-real-estate' ), $uva, 'LK-82', array( 'Moneragala' ) ),
				'mullaitivu'   => array( __( 'Mullaitivu', 'crc-real-estate' ), $northern, 'LK-45', array( 'Mullativu' ) ),
				'nuwara-eliya' => array( __( 'Nuwara Eliya', 'crc-real-estate' ), $central, 'LK-23', array( 'Nuwaraeliya' ) ),
				'polonnaruwa'  => array( __( 'Polonnaruwa', 'crc-real-estate' ), $north_central, 'LK-72', array( 'Polonaruwa' ) ),
				'puttalam'     => array( __( 'Puttalam', 'crc-real-estate' ), $north_western, 'LK-62', array( 'Puttalama' ) ),
				'ratnapura'    => array( __( 'Ratnapura', 'crc-real-estate' ), $sabaragamuwa, 'LK-91', array( 'Rathnapura' ) ),
				'trincomalee'  => array( __( 'Trincomalee', 'crc-real-estate' ), $eastern, 'LK-53', array( 'Trinco' ) ),
				'vavuniya'     => array( __( 'Vavuniya', 'crc-real-estate' ), $northern, 'LK-44', array() ),
			)
		);

		$list = array();

		// The list above is written short: name, province, code, other spellings.
		foreach ( (array) $districts as $slug => $district ) {
			$district = isset( $district[0] )
				? array(
					'name'     => $district[0],
					'province' => isset( $district[1] ) ? $district[1] : '',
					'code'     => isset( $district[2] ) ? $district[2] : '',
					'aliases'  => isset( $district[3] ) ? (array) $district[3] : array(),
				)
				: wp_parse_args(
					(array) $district,
					array(
						'name'     => $slug,
						'province' => '',
						'code'     => '',
						'aliases'  => array(),
					)
				);

			$list[ sanitize_title( $slug ) ] = $district;
		}

		return $list;
	}

	/**
	 * A listing's district, or null when it has none.
	 *
	 * @param int $post_id Listing ID.
	 * @return array|null 'name', 'province', 'slug' and 'term'.
	 */
	public static function of( $post_id ) {
		$terms = get_the_terms( $post_id, self::NAME );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		$term = reset( $terms );
		$all  = self::districts();

		return array(
			'name'     => (string) $term->name,
			'province' => isset( $all[ $term->slug ] ) ? $all[ $term->slug ]['province'] : '',
			'slug'     => (string) $term->slug,
			'term'     => $term,
		);
	}

	/**
	 * Letters and digits only, in lower case, so "Nuwara-Eliya" and
	 * "nuwara eliya" read the same.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize( $text ) {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( (string) $text ) ) );
	}

	/**
	 * The district a name, other spelling, slug or map code stands for, e.g.
	 * "Galle", "galle district", "Moneragala" or "LK-31".
	 *
	 * @param string $text Text.
	 * @return string District slug, or an empty string.
	 */
	public static function find( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$all = self::districts();

		foreach ( $all as $slug => $district ) {
			if ( '' !== $district['code'] && 0 === strcasecmp( $district['code'], $text ) ) {
				return $slug;
			}
		}

		// "Galle District" is how maps name them.
		$key = self::normalize( preg_replace( '/\s+district$/i', '', $text ) );

		if ( '' === $key ) {
			return '';
		}

		foreach ( $all as $slug => $district ) {
			$names = array_merge( array( $district['name'], $slug ), $district['aliases'] );

			foreach ( $names as $name ) {
				if ( self::normalize( $name ) === $key ) {
					return $slug;
				}
			}
		}

		return '';
	}

	/**
	 * The first district found in a place from the map: its map code, its
	 * "… District" name, or any part of its address.
	 *
	 * @param string[] $texts Parts of the place, most telling first.
	 * @return string District slug, or an empty string.
	 */
	public static function from_place( array $texts ) {
		$parts = array();

		foreach ( $texts as $text ) {
			if ( is_scalar( $text ) ) {
				$parts = array_merge( $parts, array_map( 'trim', explode( ',', (string) $text ) ) );
			}
		}

		// A map code or a "… District" first: a village can share a district's name.
		foreach ( array( true, false ) as $sure ) {
			foreach ( $parts as $part ) {
				if ( $sure && ! preg_match( '/^[a-z]{2}-\d+$|\sdistrict$/i', $part ) ) {
					continue;
				}

				$slug = self::find( $part );

				if ( '' !== $slug ) {
					return $slug;
				}
			}
		}

		return '';
	}

	/**
	 * Districts matching what is typed, best first: names that start with it,
	 * then names with a word or another spelling that starts with it, then
	 * names that have it anywhere, then the districts of a province that
	 * matches. Nothing typed gives every district.
	 *
	 * @param string $query What is typed.
	 * @return string[] District slugs.
	 */
	public static function search( $query ) {
		$key = self::normalize( $query );
		$all = self::districts();

		if ( '' === $key ) {
			return array_keys( $all );
		}

		$ranked = array();

		foreach ( $all as $slug => $district ) {
			$name  = self::normalize( $district['name'] );
			$words = array_map( array( __CLASS__, 'normalize' ), preg_split( '/[\s\-]+/', (string) $district['name'] ) );
			$other = array_map( array( __CLASS__, 'normalize' ), $district['aliases'] );
			$rank  = null;

			if ( 0 === strpos( $name, $key ) ) {
				$rank = 0;
			} else {
				foreach ( array_merge( $words, $other ) as $word ) {
					if ( '' !== $word && 0 === strpos( $word, $key ) ) {
						$rank = 1;
						break;
					}
				}
			}

			if ( null === $rank ) {
				foreach ( array_merge( array( $name ), $other ) as $word ) {
					if ( false !== strpos( $word, $key ) ) {
						$rank = 2;
						break;
					}
				}
			}

			if ( null === $rank && strlen( $key ) > 2 && false !== strpos( self::normalize( $district['province'] ), $key ) ) {
				$rank = 3;
			}

			if ( null !== $rank ) {
				$ranked[ $slug ] = $rank;
			}
		}

		// Stable: equal ranks keep the alphabetical order.
		$order = array_flip( array_keys( $all ) );

		uksort(
			$ranked,
			function ( $a, $b ) use ( $ranked, $order ) {
				return $ranked[ $a ] === $ranked[ $b ] ? $order[ $a ] - $order[ $b ] : $ranked[ $a ] - $ranked[ $b ];
			}
		);

		return array_keys( $ranked );
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'pre_insert_term', array( $this, 'block_new_terms' ), 10, 2 );
		add_filter( 'wp_update_term_data', array( $this, 'keep_slug' ), 10, 3 );
		add_action( 'load-edit-tags.php', array( $this, 'repair_terms' ) );
		add_action( self::NAME . '_pre_add_form', array( $this, 'fixed_note' ) );
		add_filter( 'manage_edit-' . self::NAME . '_columns', array( $this, 'term_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'column_title' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdown' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_search' ) );
	}

	/**
	 * Registers the taxonomy.
	 */
	public function register() {
		register_taxonomy(
			self::NAME,
			Post_Type::NAME,
			array(
				'labels'               => array(
					'name'          => _x( 'Districts', 'taxonomy general name', 'crc-real-estate' ),
					'singular_name' => _x( 'District', 'taxonomy singular name', 'crc-real-estate' ),
					'menu_name'     => __( 'Districts', 'crc-real-estate' ),
					'all_items'     => __( 'All Districts', 'crc-real-estate' ),
					'edit_item'     => __( 'Edit District', 'crc-real-estate' ),
					'view_item'     => __( 'View District', 'crc-real-estate' ),
					'update_item'   => __( 'Update District', 'crc-real-estate' ),
					'search_items'  => __( 'Search Districts', 'crc-real-estate' ),
					'not_found'     => __( 'No districts found.', 'crc-real-estate' ),
					'back_to_items' => __( '&larr; Back to Districts', 'crc-real-estate' ),
				),
				'hierarchical'         => false,
				'public'               => true,
				'show_ui'              => true,
				'show_in_menu'         => true,
				'show_admin_column'    => true,
				'show_in_quick_edit'   => false,
				'show_tagcloud'        => false,
				'show_in_rest'         => true,
				'meta_box_cb'          => false, // The District box on the listing screen replaces WordPress's.
				'meta_box_sanitize_cb' => 'taxonomy_meta_box_sanitize_cb_checkboxes', // The box sends a term ID.
				'rewrite'              => array(
					/**
					 * Filters the URL base of district pages, e.g. example.com/district/galle/.
					 *
					 * @param string $slug URL base.
					 */
					'slug'       => apply_filters( 'crc_re_district_slug', 'district' ),
					'with_front' => false,
				),
				'capabilities'         => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Creates any district that doesn't exist yet, with its province as the
	 * description.
	 */
	public static function create_terms() {
		self::$creating = true;

		foreach ( self::districts() as $slug => $district ) {
			if ( ! get_term_by( 'slug', $slug, self::NAME ) ) {
				wp_insert_term(
					$district['name'],
					self::NAME,
					array(
						'slug'        => $slug,
						'description' => $district['province'],
					)
				);
			}
		}

		self::$creating = false;
	}

	/**
	 * The district terms, keyed by slug.
	 *
	 * @return \WP_Term[]
	 */
	public static function terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::NAME,
				'hide_empty' => false,
			)
		);
		$list  = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$list[ $term->slug ] = $term;
			}
		}

		return $list;
	}

	/**
	 * A district's term from its slug, creating any missing district first.
	 *
	 * @param string $slug District slug.
	 * @return \WP_Term|null
	 */
	public static function term( $slug ) {
		if ( '' === $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, self::NAME );

		if ( ! $term && isset( self::districts()[ $slug ] ) ) {
			self::create_terms();
			$term = get_term_by( 'slug', $slug, self::NAME );
		}

		return $term && ! is_wp_error( $term ) ? $term : null;
	}

	/**
	 * Puts back a missing district when the Districts screen is opened.
	 */
	public function repair_terms() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which screen is showing.
		if ( isset( $_GET['taxonomy'] ) && self::NAME === $_GET['taxonomy'] ) {
			self::create_terms();
		}
	}

	/**
	 * Refuses new districts, so the list stays the 25.
	 *
	 * @param string|\WP_Error $term     Term name.
	 * @param string           $taxonomy Taxonomy.
	 * @return string|\WP_Error
	 */
	public function block_new_terms( $term, $taxonomy ) {
		if ( self::NAME === $taxonomy && ! self::$creating ) {
			return new \WP_Error( 'crc_districts_fixed', __( 'Districts are a fixed list, so new ones can\'t be added.', 'crc-real-estate' ) );
		}

		return $term;
	}

	/**
	 * Keeps each district's web address (slug), which the plugin finds it by.
	 *
	 * @param array  $data     Term data about to be saved.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	public function keep_slug( $data, $term_id, $taxonomy ) {
		if ( self::NAME === $taxonomy ) {
			$term = get_term( $term_id, self::NAME );

			if ( $term && ! is_wp_error( $term ) ) {
				$data['slug'] = $term->slug;
			}
		}

		return $data;
	}

	/**
	 * Shows a note where the "Add new" form usually is.
	 */
	public function fixed_note() {
		?>
		<div class="crc-fixed-note">
			<h2><?php esc_html_e( 'Sri Lanka\'s 25 districts', 'crc-real-estate' ); ?></h2>
			<p><?php esc_html_e( 'Pick a listing\'s district in the District box on the listing screen. You can edit names and descriptions here; adding and deleting are turned off.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Keeps the list simple: no checkboxes or web address column, and the
	 * count is called "Listings".
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function term_columns( $columns ) {
		$listings = isset( $columns['posts'] );

		unset( $columns['cb'], $columns['slug'], $columns['posts'] );

		if ( $listings ) {
			$columns['posts'] = __( 'Listings', 'crc-real-estate' );
		}

		return $columns;
	}

	/**
	 * Loads the styles that hide the add form and locked fields on the district screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function admin_assets( $hook ) {
		$screen = get_current_screen();

		if ( $screen && self::NAME === $screen->taxonomy && in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		}
	}

	/**
	 * Names the list column "District", since each listing has one.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function column_title( $columns ) {
		$key = 'taxonomy-' . self::NAME;

		if ( isset( $columns[ $key ] ) ) {
			$columns[ $key ] = __( 'District', 'crc-real-estate' );
		}

		return $columns;
	}

	/**
	 * Adds a district filter above the Listings list.
	 *
	 * @param string $post_type Post type of the list.
	 */
	public function filter_dropdown( $post_type ) {
		if ( Post_Type::NAME !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$selected = isset( $_GET[ self::NAME ] ) ? sanitize_title( wp_unslash( $_GET[ self::NAME ] ) ) : '';

		wp_dropdown_categories(
			array(
				'show_option_all' => __( 'All districts', 'crc-real-estate' ),
				'taxonomy'        => self::NAME,
				'name'            => self::NAME,
				'value_field'     => 'slug',
				'selected'        => $selected,
				'hide_empty'      => false,
				'hierarchical'    => false,
			)
		);
	}

	/**
	 * The District box's search: the districts matching what is typed, or the
	 * district of a place found on the map.
	 */
	public function ajax_search() {
		if ( ! check_ajax_referer( self::AJAX, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page is out of date. Please reload it.', 'crc-real-estate' ) ), 403 );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You can\'t edit listings.', 'crc-real-estate' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
		if ( isset( $_POST['place'] ) ) {
			$place = array_map( 'sanitize_text_field', array_filter( (array) wp_unslash( $_POST['place'] ), 'is_scalar' ) );
			$slug  = self::from_place( array_slice( $place, 0, 20 ) );
			$slugs = '' !== $slug ? array( $slug ) : array();
		} else {
			$query = isset( $_POST['q'] ) && is_scalar( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
			$slugs = self::search( substr( $query, 0, 100 ) );
		}
		// phpcs:enable

		$all   = self::districts();
		$terms = self::terms();
		$found = array();

		foreach ( $slugs as $slug ) {
			$term = isset( $terms[ $slug ] ) ? $terms[ $slug ] : self::term( $slug );

			if ( $term ) {
				$found[] = array(
					'id'       => (int) $term->term_id,
					'name'     => (string) $term->name,
					'province' => $all[ $slug ]['province'],
				);
			}
		}

		wp_send_json_success( array( 'districts' => $found ) );
	}
}
