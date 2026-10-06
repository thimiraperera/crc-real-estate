<?php
/**
 * Towns.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The town or area a listing is in, such as Hikkaduwa or Colombo 7, typed on
 * the listing screen. Each town is a WordPress term, so a town is written the
 * same way on every listing, Listings → Towns can rename or remove one, and
 * the search finds listings by it. A town also remembers its district, which
 * the search's suggestions show beside it.
 */
final class Town {

	const NAME          = 'crc_listing_town';
	const AJAX          = 'crc_re_towns';
	const NONCE         = 'crc_town_nonce';
	const FIELD         = 'crc_town';
	const DISTRICT_META = 'crc_district';
	const MAX_LENGTH    = 60;

	/**
	 * Colombo's postal zones and the areas people know them by, so a search
	 * for Kollupitiya finds Colombo 3 and the other way round.
	 *
	 * @return array[] Zone number => area names.
	 */
	public static function zones() {
		/**
		 * Filters Colombo's zones and the areas people know them by.
		 *
		 * @param array[] $zones Zone number => area names.
		 */
		return (array) apply_filters(
			'crc_re_colombo_zones',
			array(
				1  => array( 'Fort' ),
				2  => array( 'Slave Island', 'Union Place' ),
				3  => array( 'Kollupitiya', 'Colpetty' ),
				4  => array( 'Bambalapitiya' ),
				5  => array( 'Havelock Town', 'Kirulapone', 'Narahenpita' ),
				6  => array( 'Wellawatte', 'Pamankada' ),
				7  => array( 'Cinnamon Gardens' ),
				8  => array( 'Borella' ),
				9  => array( 'Dematagoda' ),
				10 => array( 'Maradana', 'Panchikawatte' ),
				11 => array( 'Pettah' ),
				12 => array( 'Hulftsdorp' ),
				13 => array( 'Kotahena', 'Kochchikade' ),
				14 => array( 'Grandpass' ),
				15 => array( 'Mutwal', 'Modara', 'Mattakkuliya' ),
			)
		);
	}

	/**
	 * A town's name tidied up: spaces trimmed, "colombo 07" or "Col-7"
	 * written as "Colombo 7", and a name typed all in small letters given
	 * capitals, so "hikkaduwa" becomes "Hikkaduwa".
	 *
	 * @param string $name Name as typed.
	 * @return string
	 */
	public static function tidy( $name ) {
		$name = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $name ) ), " \t\n\r\0\x0B,.;" );
		$zone = self::zone_number( $name );

		if ( $zone ) {
			/* translators: %d: zone number, e.g. 7 for Colombo 7. */
			return sprintf( __( 'Colombo %d', 'crc-real-estate' ), $zone );
		}

		if ( '' !== $name && strtolower( $name ) === $name ) {
			$name = ucwords( $name, " -\t" );
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::MAX_LENGTH ) : substr( $name, 0, self::MAX_LENGTH );
	}

	/**
	 * The zone a "Colombo 7" style name stands for.
	 *
	 * @param string $text Text.
	 * @return int Zone number, or 0.
	 */
	private static function zone_number( $text ) {
		if ( ! preg_match( '/^col(?:ombo)?[\s\-]*0*(\d{1,2})$/i', trim( (string) $text ), $match ) ) {
			return 0;
		}

		$zones = self::zones();

		return isset( $zones[ (int) $match[1] ] ) ? (int) $match[1] : 0;
	}

	/**
	 * The Colombo zone a place stands for: "Colombo 7", "Colombo 07" and
	 * "Cinnamon Gardens" all give 7.
	 *
	 * @param string $text Text.
	 * @return int Zone number, or 0.
	 */
	public static function zone( $text ) {
		$number = self::zone_number( $text );

		if ( $number ) {
			return $number;
		}

		$key = District::normalize( $text );

		if ( '' === $key ) {
			return 0;
		}

		foreach ( self::zones() as $zone => $areas ) {
			foreach ( (array) $areas as $area ) {
				if ( District::normalize( $area ) === $key ) {
					return (int) $zone;
				}
			}
		}

		return 0;
	}

	/**
	 * Every name a zone's listings can have: "Colombo 3" and its areas.
	 *
	 * @param int $zone Zone number.
	 * @return string[]
	 */
	public static function zone_names( $zone ) {
		$zones = self::zones();

		if ( ! isset( $zones[ $zone ] ) ) {
			return array();
		}

		/* translators: %d: zone number, e.g. 7 for Colombo 7. */
		return array_merge( array( sprintf( __( 'Colombo %d', 'crc-real-estate' ), $zone ) ), (array) $zones[ $zone ] );
	}

	/**
	 * A listing's town, or null when it has none.
	 *
	 * @param int $post_id Listing ID.
	 * @return array|null 'name', 'slug' and 'term'.
	 */
	public static function of( $post_id ) {
		$terms = get_the_terms( $post_id, self::NAME );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		$term = reset( $terms );

		return array(
			'name' => (string) $term->name,
			'slug' => (string) $term->slug,
			'term' => $term,
		);
	}

	/**
	 * The town with this name, whatever its capitals and spacing.
	 *
	 * @param string $name Town name.
	 * @return \WP_Term|null
	 */
	public static function find( $name ) {
		$name = self::tidy( $name );

		if ( '' === $name ) {
			return null;
		}

		$term = get_term_by( 'name', $name, self::NAME );

		if ( ! $term ) {
			$term = get_term_by( 'slug', sanitize_title( $name ), self::NAME );
		}

		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	/**
	 * The towns a place can mean: the town with that name, and for a Colombo
	 * zone the towns named by its number or its areas.
	 *
	 * @param string $text Place as typed.
	 * @return \WP_Term[] Keyed by term ID.
	 */
	public static function matching( $text ) {
		$names = array( $text );
		$zone  = self::zone( $text );

		if ( $zone ) {
			$names = array_merge( $names, self::zone_names( $zone ) );
		}

		$found = array();

		foreach ( $names as $name ) {
			$term = self::find( $name );

			if ( $term ) {
				$found[ (int) $term->term_id ] = $term;
			}
		}

		return $found;
	}

	/**
	 * Gives a listing its town, adding the town when it is new. An empty
	 * name takes the town off. The town remembers the listing's district.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $name    Town name.
	 * @return bool Whether it worked.
	 */
	public static function set( $post_id, $name ) {
		$name = self::tidy( $name );

		if ( '' === $name ) {
			wp_set_object_terms( $post_id, array(), self::NAME );
			return true;
		}

		$term = self::find( $name );

		if ( ! $term ) {
			$made = wp_insert_term( $name, self::NAME );

			if ( is_wp_error( $made ) ) {
				// Added a moment ago by another save.
				$id   = $made->get_error_data( 'term_exists' );
				$term = $id ? get_term( (int) $id, self::NAME ) : null;
			} else {
				$term = get_term( (int) $made['term_id'], self::NAME );
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		wp_set_object_terms( $post_id, array( (int) $term->term_id ), self::NAME );

		$district = District::of( $post_id );

		if ( $district && (string) get_term_meta( $term->term_id, self::DISTRICT_META, true ) !== $district['slug'] ) {
			update_term_meta( $term->term_id, self::DISTRICT_META, $district['slug'] );
		}

		return true;
	}

	/**
	 * How well a name matches what is typed: 0 when it starts with it, 1 when
	 * a word in it does, 2 when it has it anywhere, null when it doesn't.
	 *
	 * @param string $name Name.
	 * @param string $key  What is typed, from District::normalize().
	 * @return int|null
	 */
	public static function rank( $name, $key ) {
		$whole = District::normalize( $name );

		if ( '' === $key || '' === $whole ) {
			return null;
		}

		if ( 0 === strpos( $whole, $key ) ) {
			return 0;
		}

		foreach ( preg_split( '/[\s\-,]+/', (string) $name ) as $word ) {
			$word = District::normalize( $word );

			if ( '' !== $word && 0 === strpos( $word, $key ) ) {
				return 1;
			}
		}

		return false !== strpos( $whole, $key ) ? 2 : null;
	}

	/**
	 * Towns matching what is typed, best first: names that start with it,
	 * then names with a word that starts with it, then names that have it
	 * anywhere. A Colombo zone also matches the areas it is known by.
	 *
	 * @param string $query What is typed.
	 * @param int    $limit Most towns.
	 * @param bool   $used  Only towns that published listings have.
	 * @return \WP_Term[]
	 */
	public static function search( $query, $limit = 8, $used = false ) {
		$query = trim( (string) $query );
		$key   = District::normalize( $query );

		if ( '' === $key ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::NAME,
				'hide_empty' => $used,
				'name__like' => $query,
				'number'     => 100,
			)
		);
		$terms = is_array( $terms ) ? $terms : array();

		// Colombo zones known by their areas: Kollupitiya finds Colombo 3.
		foreach ( self::zones() as $areas ) {
			foreach ( (array) $areas as $area ) {
				$rank = self::rank( $area, $key );

				if ( null !== $rank && $rank < 2 ) {
					$terms = array_merge( $terms, array_values( self::matching( $area ) ) );
					break;
				}
			}
		}

		$ranked = array();

		foreach ( $terms as $term ) {
			if ( $used && (int) $term->count < 1 ) {
				continue;
			}

			$rank = self::rank( $term->name, $key );
			$zone = self::zone( $term->name );

			if ( $zone ) {
				foreach ( self::zone_names( $zone ) as $area ) {
					$also = self::rank( $area, $key );
					$rank = ( null !== $also && ( null === $rank || $also < $rank ) ) ? $also : $rank;
				}
			}

			if ( null !== $rank && ! isset( $ranked[ (int) $term->term_id ] ) ) {
				$ranked[ (int) $term->term_id ] = array( $rank, $term );
			}
		}

		uasort(
			$ranked,
			function ( $a, $b ) {
				return $a[0] === $b[0] ? strnatcasecmp( $a[1]->name, $b[1]->name ) : $a[0] - $b[0];
			}
		);

		return array_slice(
			array_map(
				function ( $item ) {
					return $item[1];
				},
				array_values( $ranked )
			),
			0,
			max( 1, (int) $limit )
		);
	}

	/**
	 * The district a town remembers.
	 *
	 * @param \WP_Term $term Town.
	 * @return array|null The district from District::districts(), plus 'slug'.
	 */
	public static function district_of( $term ) {
		$slug = (string) get_term_meta( $term->term_id, self::DISTRICT_META, true );
		$all  = District::districts();

		return isset( $all[ $slug ] ) ? array_merge( $all[ $slug ], array( 'slug' => $slug ) ) : null;
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_search' ) );
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'column_title' ) );
		add_filter( 'manage_edit-' . self::NAME . '_columns', array( $this, 'term_columns' ) );
		add_filter( 'manage_' . self::NAME . '_custom_column', array( $this, 'district_column' ), 10, 3 );
		add_action( self::NAME . '_pre_add_form', array( $this, 'note' ) );
	}

	/**
	 * Registers the taxonomy.
	 */
	public function register() {
		register_taxonomy(
			self::NAME,
			Post_Type::NAME,
			array(
				'labels'             => array(
					'name'                   => _x( 'Towns', 'taxonomy general name', 'crc-real-estate' ),
					'singular_name'          => _x( 'Town', 'taxonomy singular name', 'crc-real-estate' ),
					'menu_name'              => __( 'Towns', 'crc-real-estate' ),
					'all_items'              => __( 'All Towns', 'crc-real-estate' ),
					'edit_item'              => __( 'Edit Town', 'crc-real-estate' ),
					'view_item'              => __( 'View Town', 'crc-real-estate' ),
					'update_item'            => __( 'Update Town', 'crc-real-estate' ),
					'add_new_item'           => __( 'Add New Town', 'crc-real-estate' ),
					'new_item_name'          => __( 'New Town Name', 'crc-real-estate' ),
					'search_items'           => __( 'Search Towns', 'crc-real-estate' ),
					'not_found'              => __( 'No towns found.', 'crc-real-estate' ),
					'back_to_items'          => __( '&larr; Back to Towns', 'crc-real-estate' ),
					'name_field_description' => __( 'The town or area as people search for it, for example Hikkaduwa or Colombo 7.', 'crc-real-estate' ),
				),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_quick_edit' => false,
				'show_tagcloud'      => false,
				'show_in_rest'       => true,
				'meta_box_cb'        => false, // Typed in the District box on the listing screen.
				'rewrite'            => array(
					/**
					 * Filters the URL base of town pages, e.g. example.com/town/hikkaduwa/.
					 *
					 * @param string $slug URL base.
					 */
					'slug'       => apply_filters( 'crc_re_town_slug', 'town' ),
					'with_front' => false,
				),
				'capabilities'       => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Saves the town typed in the District box.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_town_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$name = isset( $_POST[ self::FIELD ] ) && is_scalar( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';

		self::set( $post_id, $name );
	}

	/**
	 * The Town field's suggestions: towns already used, as typed.
	 */
	public function ajax_search() {
		if ( ! check_ajax_referer( self::AJAX, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page is out of date. Please reload it.', 'crc-real-estate' ) ), 403 );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You can\'t edit listings.', 'crc-real-estate' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.
		$query = isset( $_POST['q'] ) && is_scalar( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$found = array();

		foreach ( self::search( substr( $query, 0, 100 ), 8 ) as $term ) {
			$district = self::district_of( $term );
			$found[]  = array(
				'name'     => (string) $term->name,
				'district' => $district ? $district['name'] : '',
				'code'     => $district ? $district['code'] : '',
				'count'    => (int) $term->count,
			);
		}

		wp_send_json_success( array( 'towns' => $found ) );
	}

	/**
	 * Names the list column "Town", since each listing has one.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function column_title( $columns ) {
		$key = 'taxonomy-' . self::NAME;

		if ( isset( $columns[ $key ] ) ) {
			$columns[ $key ] = __( 'Town', 'crc-real-estate' );
		}

		return $columns;
	}

	/**
	 * Towns list: a District column, and the count called "Listings".
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function term_columns( $columns ) {
		$listings = isset( $columns['posts'] );

		unset( $columns['description'], $columns['posts'] );

		$columns['crc_district'] = __( 'District', 'crc-real-estate' );

		if ( $listings ) {
			$columns['posts'] = __( 'Listings', 'crc-real-estate' );
		}

		return $columns;
	}

	/**
	 * Fills the District column with the district the town remembers.
	 *
	 * @param string $content Column content.
	 * @param string $column  Column name.
	 * @param int    $term_id Town ID.
	 * @return string
	 */
	public function district_column( $content, $column, $term_id ) {
		if ( 'crc_district' !== $column ) {
			return $content;
		}

		$term     = get_term( $term_id, self::NAME );
		$district = ( $term && ! is_wp_error( $term ) ) ? self::district_of( $term ) : null;

		return $district ? esc_html( $district['name'] ) : '&mdash;';
	}

	/**
	 * A note above the "Add New Town" form.
	 */
	public function note() {
		?>
		<div class="crc-fixed-note">
			<p><?php esc_html_e( 'Towns are usually added from the listing screen: type the town in the District and town box, and it is added here when the listing is saved. Here you can fix a town\'s spelling for every listing at once, or delete a town no listing uses.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}
}
