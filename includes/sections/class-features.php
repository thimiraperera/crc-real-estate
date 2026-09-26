<?php
/**
 * Property features section.
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
 * The listing's features with check marks: the first few on the page, and
 * See More, which opens a popup with all of them in groups. Ready-made
 * features are ticked on the listing screen; others are typed in.
 */
final class Features {

	const SHORTCODE   = 'crc_listing_features';
	const META        = '_crc_features';
	const EXTRA_META  = '_crc_features_extra';
	const GROUPS_META = '_crc_features_groups';

	/**
	 * The ready-made groups of features, ticked on the listing screen. Each
	 * group has a 'title', 'categories' (category slugs; empty for every
	 * category) and 'features' (name => label). A feature in more than one
	 * group, such as Boundary wall, is one tick.
	 *
	 * @return array[]
	 */
	public static function common_groups() {
		$boundary_wall = __( 'Boundary wall', 'crc-real-estate' );

		/**
		 * Filters the ready-made groups of features, e.g. to add a feature or rename "Verified by CRC" on another site.
		 *
		 * @param array[] $groups Group name => 'title', 'categories' and 'features'.
		 */
		$groups = apply_filters(
			'crc_re_feature_groups',
			array(
				'legal'    => array(
					'title'      => __( 'Legal and documents', 'crc-real-estate' ),
					'categories' => array(),
					'features'   => array(
						'verified'      => __( 'Verified by CRC', 'crc-real-estate' ),
						'clear_deed'    => __( 'Clear deed', 'crc-real-estate' ),
						'freehold_deed' => __( 'Sinnakkara (freehold) deed', 'crc-real-estate' ),
						'survey_plan'   => __( 'Approved survey plan', 'crc-real-estate' ),
						'street_line'   => __( 'Street line certificate', 'crc-real-estate' ),
						'non_vesting'   => __( 'Non-vesting certificate', 'crc-real-estate' ),
					),
				),
				'land'     => array(
					'title'      => __( 'Land features', 'crc-real-estate' ),
					'categories' => array( 'lands' ),
					'features'   => array(
						'flat_land'     => __( 'Flat land', 'crc-real-estate' ),
						'corner_plot'   => __( 'Corner plot', 'crc-real-estate' ),
						'boundary_wall' => $boundary_wall,
						'not_paddy'     => __( 'Not a paddy field', 'crc-real-estate' ),
						'flood_free'    => __( 'Flood-free area', 'crc-real-estate' ),
						'for_house'     => __( 'Ideal for a house', 'crc-real-estate' ),
						'for_farming'   => __( 'Suitable for farming', 'crc-real-estate' ),
						'for_business'  => __( 'Suitable for commercial use', 'crc-real-estate' ),
					),
				),
				'home'     => array(
					'title'      => __( 'Home features', 'crc-real-estate' ),
					'categories' => array( 'properties-for-sale', 'properties-for-rent' ),
					'features'   => array(
						'garden'         => __( 'Garden', 'crc-real-estate' ),
						'balcony'        => __( 'Balcony', 'crc-real-estate' ),
						'air_con'        => __( 'Air conditioning', 'crc-real-estate' ),
						'hot_water'      => __( 'Hot water', 'crc-real-estate' ),
						'modern_kitchen' => __( 'Modern kitchen', 'crc-real-estate' ),
						'servants_room'  => __( 'Servant\'s room', 'crc-real-estate' ),
						'solar_power'    => __( 'Solar power', 'crc-real-estate' ),
						'swimming_pool'  => __( 'Swimming pool', 'crc-real-estate' ),
					),
				),
				'security' => array(
					'title'      => __( 'Security', 'crc-real-estate' ),
					'categories' => array( 'properties-for-sale', 'properties-for-rent' ),
					'features'   => array(
						'cctv'            => __( 'CCTV cameras', 'crc-real-estate' ),
						'security_guard'  => __( 'Security guard', 'crc-real-estate' ),
						'gated_community' => __( 'Gated community', 'crc-real-estate' ),
						'boundary_wall'   => $boundary_wall,
					),
				),
				'tenants'  => array(
					'title'      => __( 'Tenants', 'crc-real-estate' ),
					'categories' => array( 'properties-for-rent' ),
					'features'   => array(
						'for_families'      => __( 'Suitable for families', 'crc-real-estate' ),
						'for_students'      => __( 'Suitable for students', 'crc-real-estate' ),
						'for_professionals' => __( 'Suitable for working professionals', 'crc-real-estate' ),
						'for_foreigners'    => __( 'Suitable for foreigners', 'crc-real-estate' ),
						'pets_allowed'      => __( 'Pets allowed', 'crc-real-estate' ),
					),
				),
				'nearby'   => array(
					'title'      => __( 'Nearby', 'crc-real-estate' ),
					'categories' => array(),
					'features'   => array(
						'near_town'         => __( 'Close to town', 'crc-real-estate' ),
						'near_schools'      => __( 'Close to schools', 'crc-real-estate' ),
						'near_hospitals'    => __( 'Close to hospitals', 'crc-real-estate' ),
						'near_supermarkets' => __( 'Close to supermarkets', 'crc-real-estate' ),
						'public_transport'  => __( 'Public transport nearby', 'crc-real-estate' ),
						'near_expressway'   => __( 'Close to the expressway', 'crc-real-estate' ),
						'near_beach'        => __( 'Close to the beach', 'crc-real-estate' ),
						'quiet_area'        => __( 'Quiet neighbourhood', 'crc-real-estate' ),
					),
				),
			)
		);

		// Groups added through the filter only need a title and features.
		foreach ( (array) $groups as $key => $group ) {
			$group = wp_parse_args(
				(array) $group,
				array(
					'title'      => '',
					'categories' => array(),
					'features'   => array(),
				)
			);

			$group['features'] = array_map( 'strval', (array) $group['features'] );
			$groups[ $key ]    = $group;
		}

		return (array) $groups;
	}

	/**
	 * Every ready-made feature once, as name => label.
	 *
	 * @return string[]
	 */
	public static function common_features() {
		$features = array();

		foreach ( self::common_groups() as $group ) {
			$features += $group['features'];
		}

		return $features;
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

		$fields = array(
			self::META        => 'sanitize_names',
			self::EXTRA_META  => 'sanitize_extras',
			self::GROUPS_META => 'sanitize_groups',
		);

		foreach ( $fields as $key => $sanitize ) {
			register_post_meta(
				Post_Type::NAME,
				$key,
				array(
					'type'              => 'array',
					'single'            => true,
					'sanitize_callback' => array( __CLASS__, $sanitize ),
					'auth_callback'     => $auth,
				)
			);
		}

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Property features', 'crc-real-estate' ),
				'description' => __( 'The listing\'s features with check marks: the first few on the page, and See More, which opens a popup with all of them in groups. The ready-made groups depend on the listing\'s category: every listing has Legal and documents and Nearby; lands have Land features; properties for sale and for rent have Home features and Security; properties for rent also have Tenants. See More shows when there are more features than fit on the page. Tick and add them in the Property features box on the listing screen.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'    => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
					'show'  => array(
						'default'     => '4',
						'description' => __( 'How many features show on the page, before See More.', 'crc-real-estate' ),
					),
					'title' => array(
						'default'     => __( 'Property Features', 'crc-real-estate' ),
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
					'[' . self::SHORTCODE . ' show="6"]',
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

		wp_register_style( 'crc-re-features', CRC_RE_URL . 'assets/css/features.css', array( 'crc-re-popup' ), CRC_RE_VERSION );
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
	 * Loads the features styles.
	 */
	public function enqueue_style() {
		if ( ! wp_style_is( 'crc-re-features', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-features' );
	}

	/**
	 * The ready-made features ticked on a listing.
	 *
	 * @param int $post_id Listing ID.
	 * @return string[] Feature names.
	 */
	public static function ticked( $post_id ) {
		return self::sanitize_names( get_post_meta( $post_id, self::META, true ) );
	}

	/**
	 * The features a listing added to the ready-made groups, in their saved
	 * order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Group name => features.
	 */
	public static function extras( $post_id ) {
		return self::sanitize_extras( get_post_meta( $post_id, self::EXTRA_META, true ) );
	}

	/**
	 * The listing's own groups of features, in their saved order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each group has a 'title' and 'items' (features).
	 */
	public static function groups( $post_id ) {
		return self::sanitize_groups( get_post_meta( $post_id, self::GROUPS_META, true ) );
	}

	/**
	 * Cleans ticked features: only ready-made ones, each once.
	 *
	 * @param mixed $names Feature names.
	 * @return string[]
	 */
	public static function sanitize_names( $names ) {
		$features = self::common_features();
		$clean    = array();

		foreach ( is_array( $names ) ? $names : array() as $name ) {
			if ( is_scalar( $name ) && isset( $features[ (string) $name ] ) && ! in_array( (string) $name, $clean, true ) ) {
				$clean[] = (string) $name;
			}
		}

		return $clean;
	}

	/**
	 * Cleans a list of typed features: plain text, empty ones dropped.
	 *
	 * @param mixed $labels Features as typed or saved.
	 * @return string[]
	 */
	public static function sanitize_labels( $labels ) {
		$clean = array();

		foreach ( is_array( $labels ) ? $labels : array() as $label ) {
			$label = is_scalar( $label ) ? sanitize_text_field( (string) $label ) : '';

			if ( '' !== $label ) {
				$clean[] = $label;
			}
		}

		return $clean;
	}

	/**
	 * Cleans the features added to the ready-made groups: only groups that
	 * exist, and empty features and groups dropped.
	 *
	 * @param mixed $extras Group name => features, as typed or saved.
	 * @return array[]
	 */
	public static function sanitize_extras( $extras ) {
		$groups = self::common_groups();
		$clean  = array();

		foreach ( is_array( $extras ) ? $extras : array() as $key => $labels ) {
			$labels = isset( $groups[ $key ] ) ? self::sanitize_labels( $labels ) : array();

			if ( $labels ) {
				$clean[ $key ] = $labels;
			}
		}

		return $clean;
	}

	/**
	 * Cleans the listing's own groups: plain text, empty features dropped, and
	 * a group kept when it has a title or a feature.
	 *
	 * @param mixed $groups Groups as typed or saved.
	 * @return array[]
	 */
	public static function sanitize_groups( $groups ) {
		$clean = array();

		foreach ( is_array( $groups ) ? $groups : array() as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
			$items = self::sanitize_labels( isset( $group['items'] ) ? $group['items'] : array() );

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
	 * A listing's features, in groups: the ready-made groups for its category
	 * (the ticked features, then the ones added to the group), then its own
	 * groups. Empty groups are left out.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each group has a 'title' and 'features'.
	 */
	public static function listing_groups( $post_id ) {
		$category = Taxonomy::listing_category( $post_id );
		$slug     = $category ? $category['slug'] : '';
		$ticked   = self::ticked( $post_id );
		$extras   = self::extras( $post_id );
		$groups   = array();

		foreach ( self::common_groups() as $key => $group ) {
			if ( $group['categories'] && ! in_array( $slug, $group['categories'], true ) ) {
				continue;
			}

			$features = array();

			foreach ( $group['features'] as $name => $label ) {
				if ( in_array( (string) $name, $ticked, true ) ) {
					$features[] = $label;
				}
			}

			if ( isset( $extras[ $key ] ) ) {
				$features = array_merge( $features, $extras[ $key ] );
			}

			if ( $features ) {
				$groups[] = array(
					'title'    => $group['title'],
					'features' => $features,
				);
			}
		}

		foreach ( self::groups( $post_id ) as $group ) {
			if ( $group['items'] ) {
				$groups[] = array(
					'title'    => $group['title'],
					'features' => $group['items'],
				);
			}
		}

		return $groups;
	}

	/**
	 * Renders [crc_listing_features].
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

		$groups   = self::listing_groups( $post->ID );
		$features = array();

		foreach ( $groups as $group ) {
			foreach ( $group['features'] as $feature ) {
				$features[] = $feature;
			}
		}

		if ( ! $features ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'This listing has no features yet. Tick or add them in the Property features box on the listing screen.', 'crc-real-estate' ) );
		}

		$this->enqueue_style();

		$show = absint( $atts['show'] );
		$html = '<div class="crc-features">';

		if ( $show > 0 ) {
			$html .= $this->list_html( array_slice( $features, 0, $show ) );
		}

		if ( count( $features ) > $show ) {
			$id = Popup::new_id( 'features' );

			$html .= sprintf(
				'<button type="button" class="crc-features-more"%1$s><span class="crc-features-more-text">%2$s</span>%3$s</button>',
				Popup::opener( $id ),
				esc_html( $atts['more'] ),
				Icons::svg( 'arrow-right', 'crc-features-more-icon' )
			);

			$html .= Popup::render( $id, $atts['title'], '<div class="crc-features-all">' . $this->groups_html( $groups ) . '</div>' );
		}

		return $html . '</div>';
	}

	/**
	 * Features as a list with check marks.
	 *
	 * @param string[] $features Features.
	 * @return string
	 */
	private function list_html( array $features ) {
		$html = '<ul class="crc-features-list" role="list">';

		foreach ( $features as $feature ) {
			$html .= '<li class="crc-features-item">' . Icons::svg( 'check', 'crc-features-icon' ) . '<span class="crc-features-label">' . esc_html( $feature ) . '</span></li>';
		}

		return $html . '</ul>';
	}

	/**
	 * The popup's groups: each group's title and its features.
	 *
	 * @param array[] $groups Groups from listing_groups().
	 * @return string
	 */
	private function groups_html( array $groups ) {
		$html = '';

		foreach ( $groups as $group ) {
			$html .= '<div class="crc-features-group">';

			if ( '' !== $group['title'] ) {
				$html .= '<h6 class="crc-features-group-title">' . esc_html( $group['title'] ) . '</h6>';
			}

			$html .= $this->list_html( $group['features'] ) . '</div>';
		}

		return $html;
	}
}
