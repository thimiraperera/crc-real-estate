<?php
/**
 * Listing carousel.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Price_Card;
use CRC\RealEstate\Sections\Tags;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_listing_carousel]: listings in cards, with a tab for each listing
 * category. Each tab is a carousel like the category carousel: the cards line
 * up with the container on the left and run on to the edge of the window on
 * the right, the arrows sit on the container's edges, and phones show one
 * card at a time with dots. show="latest" has the newest listings and
 * show="popular" the most viewed.
 */
final class Listing_Carousel {

	const SHORTCODE = 'crc_listing_carousel';
	const MAX       = 20;

	/**
	 * Carousels on the page so far, for their ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Listing carousel', 'crc-real-estate' ),
				'description' => __( 'Listings in cards, with a tab for each listing category, named as the categories are. Each card shows the main photo with the district on it, the land extent or bedrooms and the property type, the title, the price (and the price per perch for land) and a View Details button. Each tab is a carousel like the category carousel: it can be dragged, the arrows sit on the container\'s edges and fade at the ends, the cards run on to the edge of the window on the right, and phones show one card at a time with dots. A tab without listings doesn\'t show.', 'crc-real-estate' ),
				'attributes'  => array(
					'show'       => array(
						'default'     => 'latest',
						'description' => __( 'latest for the newest listings, for example for Latest Verified Listings, or popular for the most viewed, for example for Popular Listings.', 'crc-real-estate' ),
					),
					'count'      => array(
						'default'     => '10',
						/* translators: %d: most listings. */
						'description' => sprintf( __( 'How many listings each tab shows, from 1 to %d.', 'crc-real-estate' ), self::MAX ),
					),
					'categories' => array(
						'default'     => 'lands,properties-for-rent,properties-for-sale',
						'description' => __( 'Which categories have tabs, in order, separated by commas.', 'crc-real-estate' ),
					),
					'width'      => array(
						'default'     => 'bleed',
						'description' => __( 'bleed to let the cards run on to the right edge of the window on computers and tablets, or container to keep them inside the container.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ' show="latest"]',
					'[' . self::SHORTCODE . ' show="popular"]',
					'[' . self::SHORTCODE . ' show="latest" categories="lands"]',
				),
			)
		);
	}

	/**
	 * Registers the scripts: the carousel's, shared with the category carousel, and the tabs'.
	 */
	public function register_assets() {
		if ( ! wp_script_is( 'crc-re-carousel', 'registered' ) ) {
			wp_register_script( 'crc-re-carousel', CRC_RE_URL . 'assets/js/carousel.js', array(), CRC_RE_VERSION, true );
		}

		wp_register_script( 'crc-re-listings', CRC_RE_URL . 'assets/js/listings.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the scripts in Elementor's editor, so a carousel added there works straight away.
	 */
	public function enqueue_scripts() {
		$this->register_assets();
		wp_enqueue_script( 'crc-re-carousel' );
		wp_enqueue_script( 'crc-re-listings' );
	}

	/**
	 * Asks page speed plugins not to hold the tabs' script back.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( 'crc-re-listings' !== $handle || false !== strpos( $tag, 'data-no-optimize' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
	}

	/**
	 * What to ask WordPress for: a category's published listings, newest
	 * first, or most viewed first (listings never viewed come last, newest first).
	 *
	 * @param int  $term_id Category ID.
	 * @param bool $popular Most viewed first.
	 * @param int  $count   How many.
	 * @return array
	 */
	public static function query_args( $term_id, $popular, $count ) {
		$args = array(
			'post_type'        => Post_Type::NAME,
			'post_status'      => 'publish',
			'numberposts'      => $count,
			'fields'           => 'ids',
			'has_password'     => false,
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- Small, limited list.
				array(
					'taxonomy' => Taxonomy::NAME,
					'field'    => 'term_id',
					'terms'    => array( (int) $term_id ),
				),
			),
		);

		if ( $popular ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Small, limited list.
			$args['meta_query'] = array(
				'relation'     => 'OR',
				'crc_views'    => array(
					'key'     => Views::META,
					'compare' => 'EXISTS',
					'type'    => 'NUMERIC',
				),
				'crc_no_views' => array(
					'key'     => Views::META,
					'compare' => 'NOT EXISTS',
				),
			);
			$args['orderby']    = array(
				'crc_views' => 'DESC',
				'date'      => 'DESC',
			);
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		return $args;
	}

	/**
	 * The tags a card shows: land extent for land, bedrooms for homes, then the property type.
	 *
	 * @param string $category Category slug.
	 * @return string[]
	 */
	public static function card_tags( $category ) {
		$homes = in_array( $category, array( 'properties-for-sale', 'properties-for-rent' ), true );

		/**
		 * Filters which tags a listing card shows, in order.
		 *
		 * @param string[] $tags     Tag names, see Sections\Tags::kinds().
		 * @param string   $category Category slug.
		 */
		return (array) apply_filters( 'crc_re_listing_card_tags', $homes ? array( 'bedrooms', 'property_type' ) : array( 'land_extent', 'property_type' ), $category );
	}

	/**
	 * One listing's card.
	 *
	 * @param int   $post_id  Listing ID.
	 * @param array $category Category details from Taxonomy::listing_category().
	 * @param int   $number   Card number, from 1.
	 * @param int   $total    How many cards.
	 * @return string
	 */
	public static function card( $post_id, array $category, $number, $total ) {
		$url      = (string) get_permalink( $post_id );
		$post     = get_post( $post_id );
		$title    = $post ? get_the_title( $post ) : '';
		$photo    = (int) get_post_thumbnail_id( $post_id );
		$district = District::of( $post_id );
		$price    = Price_Card::price( $post_id );
		$perch    = Price_Card::price_per_perch( $post_id );
		$image    = $photo ? wp_get_attachment_image(
			$photo,
			'large',
			false,
			array(
				'class'     => 'crc-listing-card-img',
				'alt'       => '',
				'sizes'     => '(max-width: 767px) 100vw, (max-width: 1024px) 50vw, 33vw',
				'draggable' => 'false',
			)
		) : '';
		$place    = $district ? sprintf(
			'<span class="crc-listing-card-place">%1$s<span class="crc-sr">%2$s: </span>%3$s</span>',
			Icons::svg( 'location', 'crc-listing-card-place-icon' ),
			esc_html__( 'District', 'crc-real-estate' ),
			esc_html( $district['name'] )
		) : '';
		$amount   = '';

		if ( '' !== $price ) {
			$period = '' !== $category['period'] ? '<span class="crc-listing-card-period">' . esc_html( $category['period'] ) . '</span>' : '';
			$amount = '<h3 class="crc-listing-card-amount">' . esc_html( Price_Card::money( $price ) ) . $period . '</h3>';
		}

		if ( $category['per_perch'] && '' !== $perch ) {
			/* translators: %s: price per perch, e.g. "Rs. 3,125". */
			$amount .= '<h6 class="crc-listing-card-per">' . esc_html( sprintf( __( '%s per perch', 'crc-real-estate' ), Price_Card::money( $perch ) ) ) . '</h6>';
		}

		return sprintf(
			'<li class="crc-listing-card" role="group" aria-roledescription="%1$s" aria-label="%2$s"><div class="crc-listing-card-media"><a class="crc-listing-card-photo" href="%3$s" tabindex="-1" aria-hidden="true">%4$s</a>%5$s</div><div class="crc-listing-card-body">%6$s<h6 class="crc-listing-card-title"><a href="%3$s">%7$s</a></h6>%8$s<div class="crc-listing-card-button custom-btn-1-lite"><a class="elementor-button elementor-button-link" href="%3$s"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">%9$s</span><span class="elementor-button-icon">%10$s</span></span></a></div></div></li>',
			esc_attr__( 'slide', 'crc-real-estate' ),
			/* translators: 1: card number, 2: number of cards. */
			esc_attr( sprintf( __( '%1$s of %2$s', 'crc-real-estate' ), $number, $total ) ),
			esc_url( $url ),
			$image,
			$place,
			Tags::list_html( $post_id, self::card_tags( $category['slug'] ) ),
			esc_html( $title ),
			'' !== $amount ? '<div class="crc-listing-card-price">' . $amount . '</div>' : '',
			esc_html__( 'View Details', 'crc-real-estate' ),
			Icons::svg( 'arrow-right' )
		);
	}

	/**
	 * Renders [crc_listing_carousel].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts    = Shortcodes::atts( self::SHORTCODE, $atts );
		$popular = 'popular' === strtolower( trim( (string) $atts['show'] ) );
		$count   = is_numeric( $atts['count'] ) ? max( 1, min( self::MAX, (int) $atts['count'] ) ) : 10;
		$bleed   = 'container' !== strtolower( trim( (string) $atts['width'] ) );
		$panels  = array();

		foreach ( array_unique( array_filter( array_map( 'sanitize_title', explode( ',', (string) $atts['categories'] ) ) ) ) as $slug ) {
			$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$ids = get_posts( self::query_args( $term->term_id, $popular, $count ) );

			if ( $ids ) {
				$panels[] = array(
					'term' => $term,
					'ids'  => array_map( 'intval', $ids ),
				);
			}
		}

		if ( ! $panels ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'There are no published listings in these categories yet.', 'crc-real-estate' ) );
		}

		$this->register_assets();
		wp_enqueue_script( 'crc-re-carousel' );
		wp_enqueue_script( 'crc-re-listings' );

		$id      = 'crc-listings-' . ( ++self::$count );
		$details = Taxonomy::terms();
		$tabs    = '';
		$html    = '';

		foreach ( $panels as $i => $panel ) {
			$term     = $panel['term'];
			$category = array_merge(
				array(
					'period'    => '',
					'per_perch' => false,
				),
				isset( $details[ $term->slug ] ) ? $details[ $term->slug ] : array(),
				array( 'slug' => $term->slug )
			);
			$cards    = '';
			$dots     = '';
			$total    = count( $panel['ids'] );

			foreach ( $panel['ids'] as $n => $post_id ) {
				$cards .= self::card( $post_id, $category, $n + 1, $total );
				$dots  .= $total < 2 ? '' : sprintf(
					'<span class="crc-carousel-dot%1$s" role="button" tabindex="0" aria-label="%2$s"%3$s></span>',
					0 === $n ? ' is-active' : '',
					/* translators: 1: card number, 2: number of cards. */
					esc_attr( sprintf( __( 'Show card %1$s of %2$s', 'crc-real-estate' ), $n + 1, $total ) ),
					0 === $n ? ' aria-current="true"' : ''
				);
			}

			$tabs .= sprintf(
				'<span class="crc-listing-tab" role="tab" id="%1$s-tab-%2$d" aria-controls="%1$s-panel-%2$d" aria-selected="%3$s" tabindex="%4$d">%5$s</span>',
				esc_attr( $id ),
				$i,
				0 === $i ? 'true' : 'false',
				0 === $i ? 0 : -1,
				esc_html( $term->name )
			);

			// With one category there are no tabs, so its panel is just a box.
			$html .= sprintf(
				'<div class="crc-listing-panel" id="%1$s-panel-%2$d"%11$s%3$s><div class="crc-carousel crc-carousel-listings%4$s" data-crc-carousel role="region" aria-roledescription="%5$s" aria-label="%6$s"><div class="crc-carousel-stage"><div class="crc-carousel-viewport"><ul class="crc-carousel-track">%7$s</ul></div>%8$s%9$s</div>%10$s</div></div>',
				esc_attr( $id ),
				$i,
				0 === $i ? '' : ' hidden',
				$bleed ? ' crc-carousel-bleed' : '',
				esc_attr__( 'carousel', 'crc-real-estate' ),
				esc_attr( $term->name ),
				$cards, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in card().
				Arrow::html( 'prev', __( 'Previous', 'crc-real-estate' ), 'crc-carousel-arrow crc-carousel-prev', true ),
				Arrow::html( 'next', __( 'Next', 'crc-real-estate' ), 'crc-carousel-arrow crc-carousel-next' ),
				'' !== $dots ? '<div class="crc-carousel-dots">' . $dots . '</div>' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				count( $panels ) > 1 ? ' role="tabpanel" aria-labelledby="' . esc_attr( $id . '-tab-' . $i ) . '"' : ''
			);
		}

		return sprintf(
			'<div class="crc-listings" id="%1$s" data-crc-listings>%2$s%3$s</div>',
			esc_attr( $id ),
			count( $panels ) > 1 ? '<div class="crc-listing-tabs" role="tablist" aria-label="' . esc_attr__( 'Listing categories', 'crc-real-estate' ) . '">' . $tabs . '</div>' : '',
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);
	}
}
