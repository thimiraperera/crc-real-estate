<?php
/**
 * Listing categories.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * A fixed set of WordPress categories for listings. The normal category
 * screen works for editing names and descriptions; adding, deleting and
 * changing the web address (slug) or parent are locked.
 */
final class Taxonomy {

	const NAME         = 'crc_listing_category';
	const TEXTS_OPTION = 'crc_re_category_texts';

	/**
	 * Whether the plugin itself is creating its categories.
	 *
	 * @var bool
	 */
	private static $creating = false;

	/**
	 * The categories, as slug => name and short description, in display order.
	 *
	 * @return array[]
	 */
	public static function terms() {
		/**
		 * Filters the fixed listing categories, e.g. to rename them on another site.
		 *
		 * @param array[] $terms Slug => array( 'name' => …, 'description' => … ).
		 */
		return apply_filters(
			'crc_re_listing_categories',
			array(
				'lands'               => array(
					'name'        => __( 'Lands', 'crc-real-estate' ),
					'description' => __( 'Land plots for sale.', 'crc-real-estate' ),
				),
				'properties-for-sale' => array(
					'name'        => __( 'Properties for sale', 'crc-real-estate' ),
					'description' => __( 'Houses, apartments and villas for sale.', 'crc-real-estate' ),
				),
				'properties-for-rent' => array(
					'name'        => __( 'Properties for rent', 'crc-real-estate' ),
					'description' => __( 'Houses, annexes, apartments and rooms to rent.', 'crc-real-estate' ),
				),
			)
		);
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'pre_insert_term', array( $this, 'block_new_terms' ), 10, 2 );
		add_filter( 'wp_update_term_data', array( $this, 'keep_slug' ), 10, 3 );
		add_filter( 'wp_update_term_parent', array( $this, 'keep_top_level' ), 10, 3 );
		add_action( 'load-edit-tags.php', array( $this, 'repair_terms' ) );
		add_action( self::NAME . '_pre_add_form', array( $this, 'fixed_note' ) );
		add_filter( 'manage_edit-' . self::NAME . '_columns', array( $this, 'term_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add_meta_box' ) );
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'column_title' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdown' ) );
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
					'name'                   => _x( 'Listing Categories', 'taxonomy general name', 'crc-real-estate' ),
					'singular_name'          => _x( 'Listing Category', 'taxonomy singular name', 'crc-real-estate' ),
					'menu_name'              => __( 'Listing Categories', 'crc-real-estate' ),
					'all_items'              => __( 'All Listing Categories', 'crc-real-estate' ),
					'edit_item'              => __( 'Edit Listing Category', 'crc-real-estate' ),
					'view_item'              => __( 'View Listing Category', 'crc-real-estate' ),
					'update_item'            => __( 'Update Listing Category', 'crc-real-estate' ),
					'search_items'           => __( 'Search Listing Categories', 'crc-real-estate' ),
					'not_found'              => __( 'No listing categories found.', 'crc-real-estate' ),
					'back_to_items'          => __( '&larr; Back to Listing Categories', 'crc-real-estate' ),
					'name_field_description' => __( 'How it appears on the site.', 'crc-real-estate' ),
					'desc_field_description' => __( 'One short line about this category.', 'crc-real-estate' ),
				),
				'hierarchical'       => true,
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_admin_column'  => true,
				'show_in_quick_edit' => false,
				'show_in_rest'       => true,
				'meta_box_cb'        => false, // The Category box below replaces WordPress's checkboxes.
				'rewrite'            => array(
					/**
					 * Filters the URL base of category pages, e.g. example.com/listings/lands/.
					 *
					 * @param string $slug URL base.
					 */
					'slug'       => apply_filters( 'crc_re_category_slug', 'listings' ),
					'with_front' => false,
				),
				'capabilities'       => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Creates any category that doesn't exist yet. The short descriptions are
	 * filled in once; after that, edits made on the Listing Categories screen stay.
	 */
	public static function create_terms() {
		$fill_texts     = ! get_option( self::TEXTS_OPTION );
		self::$creating = true;

		foreach ( self::terms() as $slug => $term ) {
			$existing = get_term_by( 'slug', $slug, self::NAME );

			if ( ! $existing ) {
				wp_insert_term(
					$term['name'],
					self::NAME,
					array(
						'slug'        => $slug,
						'description' => $term['description'],
					)
				);
			} elseif ( $fill_texts && '' === $existing->description && '' !== $term['description'] ) {
				wp_update_term( $existing->term_id, self::NAME, array( 'description' => $term['description'] ) );
			}
		}

		self::$creating = false;

		if ( $fill_texts ) {
			update_option( self::TEXTS_OPTION, 1 );
		}
	}

	/**
	 * Puts back a missing category when the Listing Categories screen is opened.
	 */
	public function repair_terms() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which screen is showing.
		if ( isset( $_GET['taxonomy'] ) && self::NAME === $_GET['taxonomy'] ) {
			self::create_terms();
		}
	}

	/**
	 * Refuses new categories, so the list stays fixed.
	 *
	 * @param string|\WP_Error $term     Term name.
	 * @param string           $taxonomy Taxonomy.
	 * @return string|\WP_Error
	 */
	public function block_new_terms( $term, $taxonomy ) {
		if ( self::NAME === $taxonomy && ! self::$creating ) {
			return new \WP_Error( 'crc_categories_fixed', __( 'Listing categories are fixed, so new ones can\'t be added.', 'crc-real-estate' ) );
		}

		return $term;
	}

	/**
	 * Keeps each category's web address (slug), which the plugin relies on.
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
	 * Keeps every category at the top level.
	 *
	 * @param int    $parent   Parent term ID.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 */
	public function keep_top_level( $parent, $term_id, $taxonomy ) {
		return self::NAME === $taxonomy ? 0 : $parent;
	}

	/**
	 * Shows a note where the "Add new" form usually is.
	 */
	public function fixed_note() {
		?>
		<div class="crc-fixed-note">
			<h2><?php esc_html_e( 'Fixed categories', 'crc-real-estate' ); ?></h2>
			<p><?php esc_html_e( 'You can edit names and descriptions. Adding and deleting are turned off.', 'crc-real-estate' ); ?></p>
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
		unset( $columns['cb'], $columns['slug'] );

		if ( isset( $columns['posts'] ) ) {
			$columns['posts'] = __( 'Listings', 'crc-real-estate' );
		}

		return $columns;
	}

	/**
	 * Loads the styles that hide the locked fields on the category screens.
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
	 * The categories in display order.
	 *
	 * @return \WP_Term[]
	 */
	public static function get_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::NAME,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$order = array_flip( array_keys( self::terms() ) );

		usort(
			$terms,
			function ( $a, $b ) use ( $order ) {
				$a_pos = isset( $order[ $a->slug ] ) ? $order[ $a->slug ] : PHP_INT_MAX;
				$b_pos = isset( $order[ $b->slug ] ) ? $order[ $b->slug ] : PHP_INT_MAX;

				return $a_pos - $b_pos;
			}
		);

		return $terms;
	}

	/**
	 * Adds the Category box to the listing screen.
	 */
	public function add_meta_box() {
		add_meta_box( self::NAME . 'div', __( 'Category', 'crc-real-estate' ), array( $this, 'meta_box' ), Post_Type::NAME, 'side', 'default' );
	}

	/**
	 * The Category box: pick one.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function meta_box( $post ) {
		$current = wp_get_object_terms( $post->ID, self::NAME, array( 'fields' => 'ids' ) );
		$current = ( ! is_wp_error( $current ) && $current ) ? (int) $current[0] : 0;
		$field   = 'tax_input[' . self::NAME . '][]';
		?>
		<div class="crc-category-box">
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="0">
			<?php foreach ( self::get_terms() as $term ) : ?>
				<label class="crc-category-box__option">
					<input type="radio" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( $current, $term->term_id ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Required. Choose one.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Names the list column "Category", since each listing has one.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function column_title( $columns ) {
		$key = 'taxonomy-' . self::NAME;

		if ( isset( $columns[ $key ] ) ) {
			$columns[ $key ] = __( 'Category', 'crc-real-estate' );
		}

		return $columns;
	}

	/**
	 * Adds a category filter above the Listings list.
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
				'show_option_all' => __( 'All categories', 'crc-real-estate' ),
				'taxonomy'        => self::NAME,
				'name'            => self::NAME,
				'value_field'     => 'slug',
				'selected'        => $selected,
				'hide_empty'      => false,
				'hierarchical'    => false,
			)
		);
	}
}
