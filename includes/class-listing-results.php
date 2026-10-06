<?php
/**
 * Search results.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_listing_results]: the listings that match the search, in the listing
 * carousel's cards, with how many there are, a choice of order and numbered
 * pages. [crc_search_heading]: a heading that says what is being looked at,
 * such as "Land to buy in Galle". Both read the search from the address, so
 * they work on the listings page and on every category's, district's and
 * town's page.
 */
final class Listing_Results {

	const SHORTCODE       = 'crc_listing_results';
	const TITLE_SHORTCODE = 'crc_search_heading';

	/**
	 * Results on the page so far, for their ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the shortcodes.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Search results', 'crc-real-estate' ),
				'description' => __( 'The listings that match the search, for your listings page: the same cards as the listing carousel, how many listings were found, a Sort by choice (newest, price, most viewed, and for land the price per perch and the largest land) and numbered pages. It reads the search from the page\'s address, so category links such as /listings/lands/ show that category\'s listings, like a normal WordPress archive. Put the page at /listing/, or choose it on Listings → Widgets → Search.', 'crc-real-estate' ),
				'attributes'  => array(
					'per_page' => array(
						'default'     => '12',
						/* translators: %d: most listings a page. */
						'description' => sprintf( __( 'How many listings a page shows, from 1 to %d.', 'crc-real-estate' ), Listing_Query::MAX_PER_PAGE ),
					),
					'columns'  => array(
						'default'     => '3',
						'description' => __( 'Cards side by side on computers, from 1 to 4. Laptops show at most 3, tablets 2 and phones 1.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' columns="2" per_page="10"]',
				),
			)
		);

		Shortcodes::add(
			self::TITLE_SHORTCODE,
			array( $this, 'render_title' ),
			array(
				'title'       => __( 'Search heading', 'crc-real-estate' ),
				'description' => __( 'A heading for your listings page that says what is being looked at, such as "Land to buy", "Land to buy in Galle" or "Listings in Galle". It changes with the search and with category links, and it takes the site\'s heading style. The browser tab\'s title changes the same way on its own.', 'crc-real-estate' ),
				'attributes'  => array(
					'tag' => array(
						'default'     => 'h1',
						'description' => __( 'Which heading it is: h1 to h6, or p for plain text.', 'crc-real-estate' ),
					),
					'all' => array(
						'default'     => __( 'All listings', 'crc-real-estate' ),
						'description' => __( 'The heading when nothing is chosen.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::TITLE_SHORTCODE . ']',
					'[' . self::TITLE_SHORTCODE . ' tag="h2" all="Properties in Sri Lanka"]',
				),
			)
		);
	}

	/**
	 * Asks WordPress for the listings, with their main photos.
	 *
	 * @param array $args WP_Query arguments.
	 * @return \WP_Query
	 */
	public static function find( array $args ) {
		$query = new \WP_Query( $args );

		if ( function_exists( 'update_post_thumbnail_cache' ) ) {
			update_post_thumbnail_cache( $query );
		}

		return $query;
	}

	/**
	 * The page numbers to show: the first and last, and the ones around the
	 * current one, with 0 where a gap goes, e.g. 1 0 9 10 11 0 20.
	 *
	 * @param int $current Current page.
	 * @param int $total   Number of pages.
	 * @return int[]
	 */
	public static function page_numbers( $current, $total ) {
		if ( $total < 2 ) {
			return array();
		}

		$show = array( 1, $total, $current - 1, $current, $current + 1 );

		// Near either end, a few more, so a gap never hides a single page.
		if ( $current <= 3 ) {
			$show = array_merge( $show, range( 1, min( $total, 4 ) ) );
		}

		if ( $current >= $total - 2 ) {
			$show = array_merge( $show, range( max( 1, $total - 3 ), $total ) );
		}

		$show = array_unique(
			array_filter(
				$show,
				function ( $number ) use ( $total ) {
					return $number >= 1 && $number <= $total;
				}
			)
		);
		sort( $show );

		$items = array();
		$last  = 0;

		foreach ( $show as $number ) {
			if ( 2 === $number - $last ) {
				$items[] = $last + 1;
			} elseif ( $number - $last > 2 ) {
				$items[] = 0;
			}

			$items[] = $number;
			$last    = $number;
		}

		return $items;
	}

	/**
	 * The numbered pages, with the round arrows for the previous and next.
	 *
	 * @param int $current Current page.
	 * @param int $total   Number of pages.
	 * @return string
	 */
	public static function pagination( $current, $total ) {
		$numbers = self::page_numbers( $current, $total );

		if ( ! $numbers ) {
			return '';
		}

		$arrow = function ( $direction, $page, $label ) use ( $total ) {
			$icon = Icons::svg( 'prev' === $direction ? 'arrow-left' : 'arrow-right', 'crc-arrow-icon' );

			if ( $page < 1 || $page > $total ) {
				return sprintf( '<span class="crc-arrow crc-arrow-%1$s crc-pages-%1$s" role="link" aria-label="%2$s" aria-disabled="true">%3$s</span>', $direction, esc_attr( $label ), $icon );
			}

			return sprintf( '<a class="crc-arrow crc-arrow-%1$s crc-pages-%1$s" href="%2$s" rel="%1$s" aria-label="%3$s">%4$s</a>', $direction, esc_url( Listing_Archive::link( array( 'page' => $page ) ) ), esc_attr( $label ), $icon );
		};

		$html = $arrow( 'prev', $current - 1, __( 'Previous page', 'crc-real-estate' ) );

		foreach ( $numbers as $number ) {
			if ( 0 === $number ) {
				$html .= '<span class="crc-page-gap" aria-hidden="true">&hellip;</span>';
			} elseif ( $number === $current ) {
				/* translators: %s: page number. */
				$html .= sprintf( '<span class="crc-page is-current" aria-current="page" aria-label="%1$s">%2$s</span>', esc_attr( sprintf( __( 'Page %s', 'crc-real-estate' ), number_format_i18n( $number ) ) ), esc_html( number_format_i18n( $number ) ) );
			} else {
				/* translators: %s: page number. */
				$html .= sprintf( '<a class="crc-page" href="%1$s" aria-label="%2$s">%3$s</a>', esc_url( Listing_Archive::link( array( 'page' => $number ) ) ), esc_attr( sprintf( __( 'Page %s', 'crc-real-estate' ), number_format_i18n( $number ) ) ), esc_html( number_format_i18n( $number ) ) );
			}
		}

		$html .= $arrow( 'next', $current + 1, __( 'Next page', 'crc-real-estate' ) );

		return '<nav class="crc-pages" aria-label="' . esc_attr__( 'Pages of listings', 'crc-real-estate' ) . '">' . $html . '</nav>';
	}

	/**
	 * The Sort by choice. It keeps the other filters and goes back to the first page.
	 *
	 * @param string $id      Results ID.
	 * @param array  $filters Filters.
	 * @return string
	 */
	private static function sort_form( $id, array $filters ) {
		$hidden = '';

		foreach ( Listing_Query::params( $filters, array( 'sort', 'page' ) ) as $name => $value ) {
			$hidden .= sprintf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( $value ) );
		}

		return sprintf(
			'<form class="crc-results-sort" method="get" action="%1$s" data-crc-sort>%2$s<label class="crc-results-sort-label" for="%3$s-sort">%4$s</label><select class="crc-results-sort-select" id="%3$s-sort" name="sort">%5$s</select><noscript><button type="submit" class="crc-results-sort-button">%6$s</button></noscript></form>',
			esc_url( Listing_Archive::base_url() ),
			$hidden, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_attr( $id ),
			esc_html__( 'Sort by', 'crc-real-estate' ),
			Listing_Search::options( Listing_Query::sorts( $filters['category'] ), $filters['sort'] ),
			esc_html__( 'Sort', 'crc-real-estate' )
		);
	}

	/**
	 * Renders [crc_listing_results].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts     = Shortcodes::atts( self::SHORTCODE, $atts );
		$per_page = is_numeric( $atts['per_page'] ) ? max( 1, min( Listing_Query::MAX_PER_PAGE, (int) $atts['per_page'] ) ) : 12;
		$columns  = is_numeric( $atts['columns'] ) ? max( 1, min( 4, (int) $atts['columns'] ) ) : 3;
		$filters  = Listing_Archive::filters();
		$query    = self::find( Listing_Query::args( $filters, $per_page ) );
		$id       = 'crc-results-' . ( ++self::$count );
		$total    = (int) $query->found_posts;
		$pages    = max( 1, (int) $query->max_num_pages );
		$cards    = '';

		Listing_Search::enqueue();

		foreach ( (array) $query->posts as $post ) {
			$post_id  = is_object( $post ) ? (int) $post->ID : (int) $post;
			$category = Taxonomy::listing_category( $post_id );
			$cards   .= Listing_Carousel::card(
				$post_id,
				$category ? $category : array(
					'slug'      => '',
					'period'    => '',
					'per_perch' => false,
				)
			);
		}

		if ( '' === $cards ) {
			$chosen = Listing_Filters::chosen( $filters ) > 0;
			$body   = sprintf(
				'<div class="crc-results-empty" role="status"><h6 class="crc-results-empty-title">%1$s</h6><p class="crc-results-empty-text">%2$s</p>%3$s</div>',
				esc_html__( 'No listings match your search', 'crc-real-estate' ),
				$chosen ? esc_html__( 'Try another place, or fewer filters.', 'crc-real-estate' ) : esc_html__( 'There are no listings here yet. Please come back soon.', 'crc-real-estate' ),
				$chosen ? '<div class="crc-results-empty-button custom-btn-1-lite"><a class="elementor-button elementor-button-link" href="' . esc_url( Listing_Archive::base_url() ) . '"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">' . esc_html__( 'Clear all filters', 'crc-real-estate' ) . '</span></span></a></div>' : ''
			);
		} else {
			$first = ( $filters['page'] - 1 ) * $per_page + 1;
			$last  = $first + count( (array) $query->posts ) - 1;
			$found = $total > $per_page
				/* translators: 1: first listing shown, 2: last listing shown, 3: listings found. */
				? sprintf( _n( 'Showing %1$s–%2$s of %3$s listing', 'Showing %1$s–%2$s of %3$s listings', $total, 'crc-real-estate' ), number_format_i18n( $first ), number_format_i18n( $last ), number_format_i18n( $total ) )
				/* translators: %s: listings found. */
				: sprintf( _n( '%s listing', '%s listings', $total, 'crc-real-estate' ), number_format_i18n( $total ) );
			$body  = sprintf(
				'<div class="crc-results-bar"><p class="crc-results-count" role="status">%1$s</p>%2$s</div><ul class="crc-results-grid">%3$s</ul>%4$s',
				esc_html( $found ),
				self::sort_form( $id, $filters ),
				$cards, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in card().
				self::pagination( min( $filters['page'], $pages ), $pages )
			);
		}

		return sprintf( '<div class="crc-results crc-results-cols-%1$d" id="%2$s" data-crc-results>%3$s</div>', $columns, esc_attr( $id ), $body );
	}

	/**
	 * Renders [crc_search_heading].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_title( $atts ) {
		$atts = Shortcodes::atts( self::TITLE_SHORTCODE, $atts );
		$tag  = strtolower( trim( (string) $atts['tag'] ) );
		$tag  = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? $tag : 'h1';
		$text = Listing_Archive::heading( Listing_Archive::filters(), $atts['all'] );

		return '' !== $text ? sprintf( '<%1$s class="crc-search-heading">%2$s</%1$s>', $tag, esc_html( $text ) ) : '';
	}
}
