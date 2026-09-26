<?php
/**
 * Listing categories.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * A fixed set of WordPress categories for listings. Editors pick one per
 * listing; nobody can add, rename or delete categories from the admin.
 */
final class Taxonomy {

	const NAME = 'crc_listing_category';

	/**
	 * Whether the plugin itself is creating its categories.
	 *
	 * @var bool
	 */
	private static $creating = false;

	/**
	 * The categories, as slug => name, in display order.
	 *
	 * @return string[]
	 */
	public static function terms() {
		/**
		 * Filters the fixed listing categories, e.g. to rename them on another site.
		 *
		 * @param string[] $terms Slug => name.
		 */
		return apply_filters(
			'crc_re_listing_categories',
			array(
				'lands'               => __( 'Lands', 'crc-real-estate' ),
				'properties-for-sale' => __( 'Properties for sale', 'crc-real-estate' ),
				'properties-for-rent' => __( 'Properties for rent', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'pre_insert_term', array( $this, 'block_new_terms' ), 10, 2 );
		add_action( 'load-edit-tags.php', array( $this, 'repair_terms' ) );
		add_action( 'after-' . self::NAME . '-table', array( $this, 'fixed_note' ) );
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
					'name'          => _x( 'Categories', 'taxonomy general name', 'crc-real-estate' ),
					'singular_name' => _x( 'Category', 'taxonomy singular name', 'crc-real-estate' ),
					'menu_name'     => __( 'Categories', 'crc-real-estate' ),
					'all_items'     => __( 'All Categories', 'crc-real-estate' ),
					'edit_item'     => __( 'Edit Category', 'crc-real-estate' ),
					'view_item'     => __( 'View Category', 'crc-real-estate' ),
					'search_items'  => __( 'Search Categories', 'crc-real-estate' ),
					'not_found'     => __( 'No categories found.', 'crc-real-estate' ),
					'back_to_items' => __( '&larr; Go to Categories', 'crc-real-estate' ),
				),
				'hierarchical'       => true,
				'public'             => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_quick_edit' => false,
				'show_in_rest'       => true,
				'meta_box_cb'        => array( $this, 'meta_box' ),
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
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Creates any of the categories that don't exist yet.
	 */
	public static function create_terms() {
		self::$creating = true;

		foreach ( self::terms() as $slug => $name ) {
			if ( ! term_exists( $slug, self::NAME ) ) {
				wp_insert_term( $name, self::NAME, array( 'slug' => $slug ) );
			}
		}

		self::$creating = false;
	}

	/**
	 * Puts back a missing category when the Categories screen is opened.
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
	 * The Category box on the listing screen: pick one.
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
			<p class="description crc-category-note"><?php esc_html_e( 'Required. Choose one.', 'crc-real-estate' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Explains on the Categories screen why there is no "Add" form.
	 */
	public function fixed_note() {
		printf( '<p class="description">%s</p>', esc_html__( 'These categories are fixed by CRC Real Estate. Choose one on each listing.', 'crc-real-estate' ) );
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
