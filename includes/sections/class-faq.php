<?php
/**
 * FAQ section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Icons;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Frequently asked questions, each opening smoothly to show its answer.
 *
 * Each listing category has questions of its own, shown on every listing in
 * it; a listing adds its own after them, or leaves the category's out. The
 * site also has questions of its own for any page, [crc_faqs], set in
 * Listings → Widgets. All the questions on a page are also given to search
 * engines as FAQ structured data (schema.org FAQPage), once.
 */
final class Faq {

	const SHORTCODE  = 'crc_listing_faq';
	const META       = '_crc_faqs';
	const HIDE_META  = '_crc_faqs_no_category';
	const BODY_CLASS = 'crc-no-faq';
	const MAX        = 50;

	const GENERAL_SHORTCODE = 'crc_faqs';
	const GENERAL_OPTION    = 'crc_re_faqs';

	/**
	 * Questions for search engines on this page: question => answer HTML.
	 *
	 * @var string[]
	 */
	private static $schema = array();

	/**
	 * FAQ lists on the page so far, so each list's questions open one at a time.
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_general' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_style' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_footer', array( $this, 'print_schema' ) );
		add_action( 'add_option_' . self::GENERAL_OPTION, array( $this, 'clear_cache' ) );
		add_action( 'update_option_' . self::GENERAL_OPTION, array( $this, 'clear_cache' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'FAQs', 'crc-real-estate' ),
				'description' => __( 'Frequently asked questions. Each question opens smoothly to show its answer. The first one is open at the start, and only one is open at a time: opening another closes the one that was open. A listing shows the questions of its category, set under Listings → Listing Categories (edit a category), then its own from the FAQs box on the listing screen. When a listing has no questions, nothing shows and the container with the class crc-listing-faq is hidden. The questions are also given to search engines as FAQ structured data.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'       => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
					'category' => array(
						'default'     => '',
						'description' => __( 'Shows one category\'s questions on any page, for example a general FAQ page: lands, properties-for-sale or properties-for-rent.', 'crc-real-estate' ),
					),
					'open'     => array(
						'default'     => 'first',
						'description' => __( 'Whether the first question is open at the start: first, or none to start with all of them closed.', 'crc-real-estate' ),
					),
					'schema'   => array(
						'default'     => 'yes',
						'description' => __( 'Gives the questions to search engines as FAQ structured data. Use no when an SEO plugin already does this for the page.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' open="none"]',
					'[' . self::SHORTCODE . ' category="lands"]',
				),
			)
		);

		Shortcodes::add(
			self::GENERAL_SHORTCODE,
			array( $this, 'render_general' ),
			array(
				'title'       => __( 'FAQs for any page', 'crc-real-estate' ),
				'description' => __( 'The site\'s own frequently asked questions, for any page, such as the home page or a contact page. Add them in Listings → Widgets, on the FAQs tab. They show as white cards with a soft shadow, up to 800px wide; each question opens smoothly to show its answer, the first one is open at the start, and only one is open at a time. The questions are also given to search engines as FAQ structured data.', 'crc-real-estate' ),
				'attributes'  => array(
					'open'   => array(
						'default'     => 'first',
						'description' => __( 'Whether the first question is open at the start: first, or none to start with all of them closed.', 'crc-real-estate' ),
					),
					'schema' => array(
						'default'     => 'yes',
						'description' => __( 'Gives the questions to search engines as FAQ structured data. Use no when an SEO plugin already does this for the page.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::GENERAL_SHORTCODE . ']',
					'[' . self::GENERAL_SHORTCODE . ' open="none"]',
				),
			)
		);
	}

	/**
	 * Registers the front-end files.
	 */
	public function register_assets() {
		wp_register_style( 'crc-re-faq', CRC_RE_URL . 'assets/css/faq.css', array(), CRC_RE_VERSION );
		wp_register_script( 'crc-re-faq', CRC_RE_URL . 'assets/js/faq.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the FAQ styles in the page head on listing pages.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) ) {
			wp_enqueue_style( 'crc-re-faq' );
		}
	}

	/**
	 * Loads the FAQ styles in the page head on every page once the site has
	 * questions of its own, so [crc_faqs] never shows unstyled while a page loads.
	 */
	public function enqueue_for_general() {
		if ( self::with_answers( self::general_items() ) ) {
			wp_enqueue_style( 'crc-re-faq' );
		}
	}

	/**
	 * Loads the FAQ styles, e.g. in Elementor's editor.
	 */
	public function enqueue_style() {
		if ( ! wp_style_is( 'crc-re-faq', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-faq' );
	}

	/**
	 * Loads the FAQ script in Elementor's editor, so questions added there open smoothly.
	 */
	public function enqueue_script() {
		if ( ! wp_script_is( 'crc-re-faq', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_script( 'crc-re-faq' );
	}

	/**
	 * Clears the LiteSpeed page cache when the site's questions change, so
	 * visitors see them straight away. Does nothing without LiteSpeed Cache.
	 */
	public function clear_cache() {
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * The site's own questions, from Listings → Widgets.
	 *
	 * @return array[] Items with 'question', 'answer', 'link_text' and 'link_url'.
	 */
	public static function general_items() {
		$saved = get_option( self::GENERAL_OPTION, array() );

		return self::sanitize_items( is_array( $saved ) && isset( $saved['items'] ) ? $saved['items'] : array() );
	}

	/**
	 * Cleans the site's questions sent from Listings → Widgets.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize_general( $input ) {
		return array( 'items' => self::sanitize_items( is_array( $input ) && isset( $input['items'] ) ? $input['items'] : array() ) );
	}

	/**
	 * Hides the FAQ section of a listing page with no questions: the
	 * container with the class crc-listing-faq. Not in Elementor's editor,
	 * where the section has to stay visible to be designed.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for Elementor's editor.
		$editor = isset( $_GET['elementor-preview'] );

		if ( ! $editor && is_singular( Post_Type::NAME ) && ! self::listing_items( get_queried_object_id() ) ) {
			$classes[] = self::BODY_CLASS;
		}

		return $classes;
	}

	/**
	 * A category's questions.
	 *
	 * @param int $term_id Category ID.
	 * @return array[] Items with 'question', 'answer', 'link_text' and 'link_url'.
	 */
	public static function category_items( $term_id ) {
		return self::sanitize_items( get_term_meta( $term_id, self::META, true ) );
	}

	/**
	 * A listing's own questions.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[]
	 */
	public static function own_items( $post_id ) {
		return self::sanitize_items( get_post_meta( $post_id, self::META, true ) );
	}

	/**
	 * Whether a listing shows its category's questions.
	 *
	 * @param int $post_id Listing ID.
	 * @return bool
	 */
	public static function shows_category( $post_id ) {
		return '1' !== (string) get_post_meta( $post_id, self::HIDE_META, true );
	}

	/**
	 * The questions on a listing's page: its category's (unless left out),
	 * then its own. Questions with nothing to show are left out.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[]
	 */
	public static function listing_items( $post_id ) {
		$items    = array();
		$category = $post_id && self::shows_category( $post_id ) ? Taxonomy::listing_category( $post_id ) : null;

		if ( $category ) {
			$items = self::category_items( $category['term']->term_id );
		}

		return self::with_answers( array_merge( $items, $post_id ? self::own_items( $post_id ) : array() ) );
	}

	/**
	 * Questions that have an answer or a link to show.
	 *
	 * @param array[] $items Questions.
	 * @return array[]
	 */
	public static function with_answers( array $items ) {
		return array_values(
			array_filter(
				$items,
				function ( $item ) {
					return '' !== $item['answer'] || ( '' !== $item['link_text'] && '' !== $item['link_url'] );
				}
			)
		);
	}

	/**
	 * Cleans a list of questions. Rows without a question are dropped.
	 *
	 * @param mixed $raw Saved or submitted rows.
	 * @return array[] Items with 'question', 'answer', 'link_text' and 'link_url'.
	 */
	public static function sanitize_items( $raw ) {
		$items = array();
		$text  = function ( $row, $key ) {
			return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? trim( sanitize_text_field( (string) $row[ $key ] ) ) : '';
		};

		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			if ( ! is_array( $row ) || '' === $text( $row, 'question' ) ) {
				continue;
			}

			$answer = isset( $row['answer'] ) && is_scalar( $row['answer'] ) ? (string) $row['answer'] : '';

			$items[] = array(
				'question'  => $text( $row, 'question' ),
				'answer'    => trim( sanitize_textarea_field( str_replace( array( "\r\n", "\r" ), "\n", $answer ) ) ),
				'link_text' => $text( $row, 'link_text' ),
				'link_url'  => self::sanitize_link( isset( $row['link_url'] ) ? $row['link_url'] : '' ),
			);

			if ( count( $items ) >= self::MAX ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Keeps a link: a web address, an email or phone link, or # and the ID
	 * of a part of the page, such as #crc-inquiry-1 for the inquiry form.
	 *
	 * @param mixed $url Typed link.
	 * @return string
	 */
	public static function sanitize_link( $url ) {
		$url = is_scalar( $url ) ? trim( (string) $url ) : '';

		if ( '' === $url ) {
			return '';
		}

		if ( '#' === $url[0] ) {
			$id = preg_replace( '/[^A-Za-z0-9_\-:.]/', '', substr( $url, 1 ) );

			return '' !== $id ? '#' . $id : '';
		}

		return esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Renders [crc_listing_faq].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = Shortcodes::atts( self::SHORTCODE, $atts );
		$slug = sanitize_title( (string) $atts['category'] );

		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

			if ( ! $term || is_wp_error( $term ) ) {
				return Shortcodes::placeholder( self::SHORTCODE, __( 'There is no listing category with that name. Use lands, properties-for-sale or properties-for-rent.', 'crc-real-estate' ) );
			}

			$items = self::with_answers( self::category_items( $term->term_id ) );
		} else {
			$post = Shortcodes::listing( $atts['id'] );

			if ( ! $post ) {
				return Shortcodes::placeholder( self::SHORTCODE, __( 'Place this on a listing page, or add id="…" with a listing ID, or category="…" with a category.', 'crc-real-estate' ) );
			}

			$items = self::listing_items( $post->ID );
		}

		// No questions: nothing here, and on a listing page the section itself is hidden (see body_class()).
		if ( ! $items ) {
			return '';
		}

		return $this->list_html( $items, $atts, '' );
	}

	/**
	 * Renders [crc_faqs]: the site's own questions.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_general( $atts ) {
		$atts  = Shortcodes::atts( self::GENERAL_SHORTCODE, $atts );
		$items = self::with_answers( self::general_items() );

		if ( ! $items ) {
			return Shortcodes::placeholder( self::GENERAL_SHORTCODE, __( 'Add questions in Listings → Widgets, on the FAQs tab.', 'crc-real-estate' ) );
		}

		return $this->list_html( $items, $atts, 'crc-faqs' );
	}

	/**
	 * A list of questions, and their place in the page's FAQ structured data.
	 *
	 * @param array[] $items Questions with something to show.
	 * @param array   $atts  Shortcode attributes, with 'open' and 'schema'.
	 * @param string  $class More CSS classes for the list.
	 * @return string
	 */
	private function list_html( array $items, array $atts, $class ) {
		if ( ! wp_style_is( 'crc-re-faq', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-faq' );
		wp_enqueue_script( 'crc-re-faq' );

		// Only the first question may start open; one is open at a time.
		$first = ! in_array( strtolower( trim( (string) $atts['open'] ) ), array( 'none', 'no', '0' ), true );
		$group = 'crc-faq-' . ( ++self::$count );
		$html  = '<div class="crc-faq' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" data-crc-faq>';

		foreach ( $items as $i => $item ) {
			// The shared name lets browsers keep one question open even without the script.
			$html .= sprintf(
				'<details class="crc-faq-item" name="%1$s"%2$s><summary class="crc-faq-question"><span class="crc-faq-question-text">%3$s</span>%4$s%5$s</summary><div class="crc-faq-answer"><div class="crc-faq-answer-inner">%6$s</div></div></details>',
				esc_attr( $group ),
				$first && 0 === $i ? ' open' : '',
				esc_html( $item['question'] ),
				Icons::svg( 'chevron-down', 'crc-faq-icon crc-faq-icon-closed' ),
				Icons::svg( 'chevron-up', 'crc-faq-icon crc-faq-icon-open' ),
				self::answer_html( $item, false )
			);
		}

		$html .= '</div>';

		if ( Shortcodes::is_on( $atts['schema'] ) ) {
			foreach ( $items as $item ) {
				if ( ! isset( self::$schema[ $item['question'] ] ) ) {
					self::$schema[ $item['question'] ] = self::answer_html( $item, true );
				}
			}
		}

		return $html;
	}

	/**
	 * An answer as HTML: its paragraphs, then its link.
	 *
	 * @param array $item       Question.
	 * @param bool  $for_schema For search engines: links to a part of the page get the page's full address.
	 * @return string
	 */
	private static function answer_html( array $item, $for_schema ) {
		$html = '' !== $item['answer'] ? trim( wpautop( esc_html( $item['answer'] ) ) ) : '';

		if ( '' !== $item['link_text'] && '' !== $item['link_url'] ) {
			$url = $item['link_url'];

			if ( $for_schema && '#' === $url[0] ) {
				$url = ( is_singular() ? (string) get_permalink( get_queried_object_id() ) : home_url( '/' ) ) . $url;
			}

			$html .= sprintf(
				'<p><a%1$s href="%2$s">%3$s</a></p>',
				$for_schema ? '' : ' class="crc-faq-link"',
				esc_url( $url ),
				esc_html( $item['link_text'] )
			);
		}

		return $html;
	}

	/**
	 * Gives the page's questions to search engines, once, as FAQ structured data.
	 */
	public function print_schema() {
		if ( ! self::$schema ) {
			return;
		}

		$questions = array();

		foreach ( self::$schema as $question => $answer ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => (string) $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		self::$schema = array();

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $questions,
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}
}
