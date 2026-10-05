<?php
/**
 * Testimonials.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * What clients say, with a star rating, kept as their own posts under the
 * Testimonials menu. [crc_testimonials] shows a random choice of them in a
 * carousel that goes round and round: three at a time on computers, two on
 * tablets and one on phones. It moves on by itself and can be dragged.
 */
final class Testimonials {

	const POST_TYPE  = 'crc_testimonial';
	const SHORTCODE  = 'crc_testimonials';
	const RATING     = '_crc_rating';
	const OPTION     = 'crc_re_testimonials';
	const COUNT_MAX  = 50;
	const POOL_MAX   = 50;
	const DELAY_MAX  = 30;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'saved' ), 20, 2 );
		add_action( 'trashed_post', array( $this, 'removed' ) );
		add_action( 'deleted_post', array( $this, 'removed' ) );
		add_action( 'add_option_' . self::OPTION, array( $this, 'clear_cache' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'clear_cache' ) );
	}

	/**
	 * The settings: how many testimonials to show, and how often the
	 * carousel moves on by itself.
	 *
	 * @return array 'count' and 'delay' (seconds; 0 for never).
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );

		return self::sanitize_settings( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Cleans the settings.
	 *
	 * @param mixed $input Submitted or saved settings.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$count = isset( $input['count'] ) && is_numeric( $input['count'] ) ? (int) $input['count'] : 10;
		$delay = isset( $input['delay'] ) && is_numeric( $input['delay'] ) ? (int) round( (float) $input['delay'] ) : 5;

		return array(
			'count' => max( 1, min( self::COUNT_MAX, $count ) ),
			'delay' => max( 0, min( self::DELAY_MAX, $delay ) ),
		);
	}

	/**
	 * A star rating from 0.5 to 5 in halves, e.g. "4.5". Anything that isn't
	 * a number is 5.
	 *
	 * @param mixed $value Rating as typed or saved.
	 * @return string
	 */
	public static function sanitize_rating( $value ) {
		$value = is_scalar( $value ) ? str_replace( ',', '.', trim( (string) $value ) ) : '';

		if ( ! is_numeric( $value ) ) {
			return '5';
		}

		$rating = max( 0.5, min( 5, round( (float) $value * 2 ) / 2 ) );

		return rtrim( rtrim( number_format( $rating, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * A testimonial's star rating.
	 *
	 * @param int $post_id Testimonial ID.
	 * @return string
	 */
	public static function rating( $post_id ) {
		$saved = get_post_meta( $post_id, self::RATING, true );

		return self::sanitize_rating( '' === $saved ? '5' : $saved );
	}

	/**
	 * Five stars for a rating: full, half or empty.
	 *
	 * @param string $rating Rating from sanitize_rating().
	 * @return string[] Each 'full', 'half' or 'empty'.
	 */
	public static function stars( $rating ) {
		$rating = (float) $rating;
		$stars  = array();

		for ( $i = 1; $i <= 5; $i++ ) {
			$stars[] = $rating >= $i ? 'full' : ( $rating >= $i - 0.5 ? 'half' : 'empty' );
		}

		return $stars;
	}

	/**
	 * Registers the post type, its rating and the shortcode.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => _x( 'Testimonials', 'post type general name', 'crc-real-estate' ),
					'singular_name'         => _x( 'Testimonial', 'post type singular name', 'crc-real-estate' ),
					'menu_name'             => _x( 'Testimonials', 'admin menu', 'crc-real-estate' ),
					'add_new'               => __( 'Add New Testimonial', 'crc-real-estate' ),
					'add_new_item'          => __( 'Add New Testimonial', 'crc-real-estate' ),
					'edit_item'             => __( 'Edit Testimonial', 'crc-real-estate' ),
					'new_item'              => __( 'New Testimonial', 'crc-real-estate' ),
					'view_item'             => __( 'View Testimonial', 'crc-real-estate' ),
					'search_items'          => __( 'Search Testimonials', 'crc-real-estate' ),
					'not_found'             => __( 'No testimonials yet.', 'crc-real-estate' ),
					'not_found_in_trash'    => __( 'No testimonials in the Trash.', 'crc-real-estate' ),
					'all_items'             => __( 'All Testimonials', 'crc-real-estate' ),
					'item_published'        => __( 'Testimonial published.', 'crc-real-estate' ),
					'item_updated'          => __( 'Testimonial updated.', 'crc-real-estate' ),
					'item_reverted_to_draft' => __( 'Testimonial reverted to draft.', 'crc-real-estate' ),
					'filter_items_list'     => __( 'Filter testimonials list', 'crc-real-estate' ),
					'items_list'            => __( 'Testimonials list', 'crc-real-estate' ),
				),
				// Shown only through [crc_testimonials]: no pages of their own.
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'menu_position'       => 6,
				'menu_icon'           => 'dashicons-format-quote',
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::RATING,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_rating' ),
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Testimonials', 'crc-real-estate' ),
				'description' => __( 'What clients say, each in a card with their name and star rating, in a carousel that goes round and round: three at a time on computers, two on tablets and one on phones. It shows a new random choice each time the page opens, moves on by itself and stops while the mouse is over it, and people can drag it or swipe it. Add testimonials under Testimonials in the menu; how many to show and how often it moves are in Testimonials → Settings.', 'crc-real-estate' ),
				'attributes'  => array(
					'count' => array(
						'default'     => '',
						/* translators: %d: most testimonials. */
						'description' => sprintf( __( 'How many to show here, from 1 to %d. Leave it out to use the number from Settings.', 'crc-real-estate' ), self::COUNT_MAX ),
					),
					'delay' => array(
						'default'     => '',
						'description' => __( 'Seconds before it moves on by itself here; 0 keeps it still. Leave it out to use the time from Settings.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' count="6"]',
					'[' . self::SHORTCODE . ' delay="0"]',
				),
			)
		);
	}

	/**
	 * Registers the script. The styles are in frontend.css, which every page loads.
	 */
	public function register_assets() {
		wp_register_script( 'crc-re-testimonials', CRC_RE_URL . 'assets/js/testimonials.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the script in Elementor's editor, so a carousel added there works straight away.
	 */
	public function enqueue_script() {
		$this->register_assets();
		wp_enqueue_script( 'crc-re-testimonials' );
	}

	/**
	 * Asks page speed plugins not to hold the script back, so the carousel
	 * works as soon as the page shows.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( 'crc-re-testimonials' !== $handle || false !== strpos( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
	}

	/**
	 * Clears the LiteSpeed page cache, so visitors see the change straight
	 * away. Does nothing without LiteSpeed Cache.
	 */
	public function clear_cache() {
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Clears the cache when a testimonial is saved, but not for autosaves.
	 *
	 * @param int      $post_id Testimonial ID.
	 * @param \WP_Post $post    Testimonial.
	 */
	public function saved( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$this->clear_cache();
	}

	/**
	 * Clears the cache when a testimonial goes to the Trash or is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function removed( $post_id ) {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Five stars for a rating, for screen readers as "Rated 4.5 out of 5".
	 * Each star is drawn by frontend.css from one SVG star shape, so the page
	 * stays light however many testimonials it has.
	 *
	 * @param string $rating Rating from sanitize_rating().
	 * @return string
	 */
	public static function stars_html( $rating ) {
		$html = '';

		foreach ( self::stars( $rating ) as $kind ) {
			$html .= '<span class="crc-star is-' . $kind . '"></span>';
		}

		return sprintf(
			'<div class="crc-testimonial-stars" role="img" aria-label="%1$s">%2$s</div>',
			/* translators: %s: rating, e.g. "4.5". */
			esc_attr( sprintf( __( 'Rated %s out of 5', 'crc-real-estate' ), $rating ) ),
			$html
		);
	}

	/**
	 * Renders [crc_testimonials]. The page gets up to POOL_MAX testimonials
	 * in a random order with the first ones showing; the script then picks a
	 * new random choice on every visit, even when the page comes from a cache.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts     = Shortcodes::atts( self::SHORTCODE, $atts );
		$settings = self::settings();
		$count    = '' !== trim( (string) $atts['count'] ) ? self::sanitize_settings( array( 'count' => $atts['count'] ) )['count'] : $settings['count'];
		$delay    = '' !== trim( (string) $atts['delay'] ) ? self::sanitize_settings( array( 'delay' => $atts['delay'] ) )['delay'] : $settings['delay'];
		$posts    = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => self::POOL_MAX,
				'orderby'          => 'rand',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		$posts    = array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return '' !== trim( (string) $post->post_content );
				}
			)
		);

		if ( ! $posts ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'Add testimonials under Testimonials → Add New Testimonial.', 'crc-real-estate' ) );
		}

		$items = '';
		$shown = min( $count, count( $posts ) );

		foreach ( $posts as $i => $post ) {
			$text = trim( wp_strip_all_tags( str_replace( array( "\r\n", "\r" ), "\n", (string) $post->post_content ) ) );

			$items .= sprintf(
				'<div class="crc-testimonial" role="group" aria-roledescription="%1$s" aria-label="%2$s"%3$s><h6 class="crc-testimonial-name">%4$s</h6>%5$s<div class="crc-testimonial-text">%6$s</div></div>',
				esc_attr__( 'testimonial', 'crc-real-estate' ),
				/* translators: 1: number, 2: how many. */
				esc_attr( sprintf( __( '%1$s of %2$s', 'crc-real-estate' ), min( $i + 1, $shown ), $shown ) ),
				$i < $shown ? '' : ' hidden',
				esc_html( get_the_title( $post ) ),
				self::stars_html( self::rating( $post->ID ) ),
				trim( wpautop( esc_html( $text ) ) )
			);
		}

		wp_enqueue_script( 'crc-re-testimonials' );

		return sprintf(
			'<div class="crc-testimonials" data-crc-testimonials data-count="%1$d" data-delay="%2$d" data-label="%3$s" role="region" aria-roledescription="%4$s" aria-label="%5$s" tabindex="0"><div class="crc-testimonials-viewport"><div class="crc-testimonials-track">%6$s</div></div></div>',
			$count,
			$delay,
			/* translators: 1: number, 2: how many. Keep %1$s and %2$s. */
			esc_attr__( '%1$s of %2$s', 'crc-real-estate' ),
			esc_attr__( 'carousel', 'crc-real-estate' ),
			esc_attr__( 'Testimonials', 'crc-real-estate' ),
			$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);
	}
}
