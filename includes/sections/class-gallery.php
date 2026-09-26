<?php
/**
 * Gallery section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Icons;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;
use CRC\RealEstate\Views;

defined( 'ABSPATH' ) || exit;

/**
 * The photo grid at the top of a listing: the featured image as the large
 * photo, gallery images beside it, the view count and a full-screen viewer.
 */
final class Gallery {

	const META      = '_crc_gallery';
	const SHORTCODE = 'crc_listing_gallery';
	const GRID      = 5; // Photos shown in the grid: the large one and four small ones.

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Registers the gallery field and the shortcode.
	 */
	public function register() {
		register_post_meta(
			Post_Type::NAME,
			self::META,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_ids' ),
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Gallery', 'crc-real-estate' ),
				'description' => __( 'The main photo as the large photo, with the other photos beside it. Shows the view count and "+N photos" when there are more photos than fit. Clicking any photo opens the full-screen photo viewer with every photo.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'    => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
					'views' => array(
						'default'     => 'yes',
						'description' => __( 'Show the view count. Use "no" to hide it.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' id="123"]',
					'[' . self::SHORTCODE . ' views="no"]',
				),
			)
		);
	}

	/**
	 * Registers the front-end files.
	 */
	public function register_assets() {
		wp_register_style( 'crc-re-gallery', CRC_RE_URL . 'assets/css/gallery.css', array(), CRC_RE_VERSION );
		wp_register_script(
			'crc-re-gallery',
			CRC_RE_URL . 'assets/js/gallery.js',
			array(),
			CRC_RE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'crc-re-gallery',
			'window.crcReGallery = ' . wp_json_encode(
				array(
					'dialog'  => __( 'Photo viewer', 'crc-real-estate' ),
					'close'   => __( 'Close', 'crc-real-estate' ),
					'prev'    => __( 'Previous photo', 'crc-real-estate' ),
					'next'    => __( 'Next photo', 'crc-real-estate' ),
					/* translators: 1: current photo number, 2: number of photos. */
					'counter' => __( '%1$s / %2$s', 'crc-real-estate' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Loads the styles in the page head on listing pages, so the grid never
	 * appears unstyled.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) ) {
			$this->enqueue_style();
		}
	}

	/**
	 * Loads the gallery styles.
	 */
	public function enqueue_style() {
		if ( ! wp_style_is( 'crc-re-gallery', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-gallery' );
	}

	/**
	 * Cleans a list of attachment IDs: whole numbers, images only, no repeats.
	 *
	 * @param mixed $value Comma-separated IDs or an array of IDs.
	 * @return int[]
	 */
	public static function sanitize_ids( $value ) {
		if ( ! is_array( $value ) ) {
			$value = explode( ',', (string) $value );
		}

		$ids = array();

		foreach ( $value as $id ) {
			$id = absint( $id );

			if ( $id && ! in_array( $id, $ids, true ) && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Gallery images saved on a listing, in order, without the featured image.
	 *
	 * @param int $post_id Listing ID.
	 * @return int[]
	 */
	public static function gallery_ids( $post_id ) {
		$ids = get_post_meta( $post_id, self::META, true );

		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Every photo of a listing, in display order: the featured image first,
	 * then the gallery images. Missing or deleted images are skipped.
	 *
	 * @param int $post_id Listing ID.
	 * @return int[]
	 */
	public static function image_ids( $post_id ) {
		$featured = (int) get_post_thumbnail_id( $post_id );
		$ids      = array_merge( $featured ? array( $featured ) : array(), self::gallery_ids( $post_id ) );

		if ( ! $ids ) {
			return array();
		}

		// Load all the attachments in one query instead of one per photo.
		get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'no_found_rows'  => true,
			)
		);

		$result = array();

		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $result, true ) && wp_attachment_is_image( $id ) ) {
				$result[] = $id;
			}
		}

		return $result;
	}

	/**
	 * Renders [crc_listing_gallery].
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

		$ids = self::image_ids( $post->ID );

		if ( ! $ids ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'This listing has no main photo yet.', 'crc-real-estate' ) );
		}

		$show_views = Shortcodes::is_on( $atts['views'] );
		$shown      = array_slice( $ids, 0, self::GRID );
		$hidden     = count( $ids ) - count( $shown );
		$title      = wp_strip_all_tags( get_post_field( 'post_title', $post ) );

		$this->enqueue_style();
		wp_enqueue_script( 'crc-re-gallery' );

		$html = sprintf( '<div class="crc-gallery crc-gallery-count-%d" data-crc-lightbox>', count( $shown ) );

		if ( $show_views ) {
			$views = Views::get( $post->ID );
			$html .= sprintf(
				'<span class="crc-gallery-badge" data-crc-views="%1$d"%2$s>%3$s<span class="crc-gallery-badge-label">%4$s</span></span>',
				$post->ID,
				$views ? '' : ' hidden',
				Icons::svg( 'eye', 'crc-gallery-badge-icon' ),
				esc_html( Views::label( $views ) )
			);
		}

		foreach ( $shown as $index => $id ) {
			$is_main = 0 === $index;
			$number  = $index + 1;

			$attributes = array(
				'class'    => 'crc-gallery-img',
				'alt'      => $this->alt( $id, $title, $number ),
				'sizes'    => $is_main ? '(max-width: 767px) 100vw, 60vw' : '(max-width: 767px) 25vw, 20vw',
				'loading'  => $is_main ? 'eager' : 'lazy',
				'decoding' => 'async',
			);

			// The large photo is usually the first thing on the page, so load it first.
			if ( $is_main ) {
				$attributes['fetchpriority'] = 'high';
			}

			$image = wp_get_attachment_image( $id, $is_main ? 'large' : 'medium_large', false, $attributes );

			$more = '';

			if ( $hidden > 0 && count( $shown ) === $number ) {
				/* translators: %s: number of photos not shown in the grid. */
				$more = '<span class="crc-gallery-more">' . esc_html( sprintf( _n( '+%s photo', '+%s photos', $hidden, 'crc-real-estate' ), number_format_i18n( $hidden ) ) ) . '</span>';
			}

			$html .= sprintf(
				'<a class="crc-gallery-item crc-gallery-item-%1$d" href="%2$s" data-index="%3$d">%4$s%5$s</a>',
				$number,
				esc_url( wp_get_attachment_image_url( $id, 'full' ) ),
				$index,
				$image,
				$more
			);
		}

		$html .= '<script type="application/json" class="crc-gallery-data">' . wp_json_encode( $this->lightbox_items( $ids, $title ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';

		return $html . '</div>';
	}

	/**
	 * Photo details for the full-screen viewer, covering every photo.
	 *
	 * @param int[]  $ids   Attachment IDs in display order.
	 * @param string $title Listing title.
	 * @return array[]
	 */
	private function lightbox_items( $ids, $title ) {
		$items = array();

		foreach ( $ids as $index => $id ) {
			$items[] = array(
				'src'    => (string) wp_get_attachment_image_url( $id, 'large' ),
				'srcset' => (string) wp_get_attachment_image_srcset( $id, 'full' ),
				'alt'    => $this->alt( $id, $title, $index + 1 ),
			);
		}

		return $items;
	}

	/**
	 * Alternative text for a photo, falling back to the listing title.
	 *
	 * @param int    $id     Attachment ID.
	 * @param string $title  Listing title.
	 * @param int    $number Photo number.
	 * @return string
	 */
	private function alt( $id, $title, $number ) {
		$alt = trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) );

		if ( '' !== $alt ) {
			return $alt;
		}

		/* translators: 1: listing title, 2: photo number. */
		return sprintf( __( '%1$s, photo %2$d', 'crc-real-estate' ), $title, $number );
	}
}
