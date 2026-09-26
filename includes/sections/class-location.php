<?php
/**
 * Location section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * A map with the area around the listing, never its exact place.
 *
 * The exact place is marked on the listing screen and stays there. The site
 * shows a circle whose centre sits a secret distance away from the property
 * (the same for every listing at that spot), shifts a little each day, and
 * changes size a little each day. The property is always inside the circle.
 * Watching it over time only leads to the secret centre, not the property,
 * and the circles don't close in on it either, as each is about as large.
 */
final class Location {

	const SHORTCODE   = 'crc_listing_location';
	const LAT_META    = '_crc_location_lat';
	const LNG_META    = '_crc_location_lng';
	const HIDDEN_META = '_crc_map_hidden';
	const GOOGLE_META = '_crc_google_maps';
	const KEY_OPTION  = 'crc_re_location_key';
	const BODY_CLASS  = 'crc-no-location';

	/**
	 * Leaflet, the free map library for OpenStreetMap, and its files' fingerprints.
	 */
	const LEAFLET     = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/';
	const LEAFLET_JS  = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
	const LEAFLET_CSS = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'fingerprint' ), 10, 2 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'fingerprint' ), 10, 2 );
	}

	/**
	 * Registers the fields and the shortcode.
	 */
	public function register() {
		$auth = function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		$fields = array(
			self::LAT_META    => function ( $value ) {
				return self::sanitize_coordinate( $value, 90 );
			},
			self::LNG_META    => function ( $value ) {
				return self::sanitize_coordinate( $value, 180 );
			},
			self::HIDDEN_META => function ( $value ) {
				return $value ? '1' : '';
			},
			self::GOOGLE_META => function ( $value ) {
				return esc_url_raw( (string) $value, array( 'http', 'https' ) );
			},
		);

		// Never in the REST API: the exact place stays on the listing screen.
		foreach ( $fields as $key => $sanitize ) {
			register_post_meta(
				Post_Type::NAME,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => $auth,
				)
			);
		}

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Location', 'crc-real-estate' ),
				/* translators: %s: area size in km, e.g. "15". */
				'description' => sprintf( __( 'A map with the area within about %s km of the property, never its exact place. The area moves a little each day, so the place can\'t be worked out. When a listing\'s map is turned off, or it has no location, nothing shows and the container with the class crc-listing-location is hidden. Mark the place in the Location box on the listing screen.', 'crc-real-estate' ), Settings::map( 'radius' ) ),
				'attributes'  => array(
					'id' => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' id="123"]',
				),
			)
		);
	}

	/**
	 * Registers Leaflet. The listing screen uses it too.
	 */
	public static function register_leaflet() {
		if ( wp_script_is( 'crc-re-leaflet', 'registered' ) ) {
			return;
		}

		wp_register_style( 'crc-re-leaflet', self::leaflet_url() . 'leaflet.css', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The version is in the address.
		wp_register_script( 'crc-re-leaflet', self::leaflet_url() . 'leaflet.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The version is in the address.
	}

	/**
	 * Where Leaflet loads from.
	 *
	 * @return string Folder address ending in a slash.
	 */
	private static function leaflet_url() {
		/**
		 * Filters where Leaflet loads from, e.g. to load it from the site itself.
		 *
		 * @param string $url Folder with leaflet.js and leaflet.css, ending in a slash.
		 */
		return (string) apply_filters( 'crc_re_leaflet_url', self::LEAFLET );
	}

	/**
	 * Adds Leaflet's fingerprint to its tags, so the browser only runs the
	 * exact files the plugin was built with.
	 *
	 * @param string $tag    Script or link tag.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function fingerprint( $tag, $handle ) {
		if ( 'crc-re-leaflet' !== $handle || self::LEAFLET !== self::leaflet_url() ) {
			return $tag;
		}

		$hash = false !== strpos( $tag, '<link' ) ? self::LEAFLET_CSS : self::LEAFLET_JS;

		return preg_replace( '/\s(src|href)=/', ' integrity="' . $hash . '" crossorigin="anonymous" $1=', $tag, 1 );
	}

	/**
	 * Registers the front-end files.
	 */
	public function register_assets() {
		self::register_leaflet();

		wp_register_style( 'crc-re-location', CRC_RE_URL . 'assets/css/location.css', array( 'crc-re-leaflet' ), CRC_RE_VERSION );
		wp_register_script( 'crc-re-location', CRC_RE_URL . 'assets/js/location.js', array( 'crc-re-leaflet' ), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the map styles in the page head on a listing page with a map.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) && self::shows_map( get_queried_object_id() ) ) {
			wp_enqueue_style( 'crc-re-location' );
		}
	}

	/**
	 * Hides the location section of a listing page when there's no map: the
	 * container with the class crc-listing-location. Not in Elementor's
	 * editor, where the section has to stay visible to be designed.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for Elementor's editor.
		$editor = isset( $_GET['elementor-preview'] );

		if ( ! $editor && is_singular( Post_Type::NAME ) && ! self::shows_map( get_queried_object_id() ) ) {
			$classes[] = self::BODY_CLASS;
		}

		return $classes;
	}

	/**
	 * A listing's exact place, marked on the listing screen.
	 *
	 * @param int $post_id Listing ID.
	 * @return array|null 'lat' and 'lng', or null when it has none.
	 */
	public static function exact( $post_id ) {
		$lat = self::sanitize_coordinate( get_post_meta( $post_id, self::LAT_META, true ), 90 );
		$lng = self::sanitize_coordinate( get_post_meta( $post_id, self::LNG_META, true ), 180 );

		if ( '' === $lat || '' === $lng ) {
			return null;
		}

		return array(
			'lat' => (float) $lat,
			'lng' => (float) $lng,
		);
	}

	/**
	 * Whether a listing page shows its map: it has a place and the map isn't turned off.
	 *
	 * @param int $post_id Listing ID.
	 * @return bool
	 */
	public static function shows_map( $post_id ) {
		return $post_id && '1' !== (string) get_post_meta( $post_id, self::HIDDEN_META, true ) && null !== self::exact( $post_id );
	}

	/**
	 * Keeps a latitude or longitude: a number within range, to 6 decimals
	 * (about 10 cm).
	 *
	 * @param mixed $value Typed value.
	 * @param int   $max   90 for latitude, 180 for longitude.
	 * @return string An empty string when it isn't one.
	 */
	public static function sanitize_coordinate( $value, $max ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( ! is_numeric( $value ) || abs( (float) $value ) > $max ) {
			return '';
		}

		return (string) round( (float) $value, 6 );
	}

	/**
	 * The area shown for a listing on a given day.
	 *
	 * @param int      $post_id Listing ID.
	 * @param int|null $time    When, as a timestamp; now by default.
	 * @return array|null 'lat', 'lng' and 'radius' (metres) of the circle, or null without a place.
	 */
	public static function area( $post_id, $time = null ) {
		$exact = self::exact( $post_id );

		if ( ! $exact ) {
			return null;
		}

		$radius = (float) Settings::map( 'radius' ) * 1000;
		$day    = (int) floor( ( null === $time ? time() : (int) $time ) / DAY_IN_SECONDS );
		$key    = self::key();

		// A secret offset, the same for every listing at this spot (to about a kilometre).
		$spot   = hash_hmac( 'sha256', 'spot|' . round( $exact['lat'], 2 ) . '|' . round( $exact['lng'], 2 ), $key );
		$center = self::move( $exact, self::fraction( $spot, 0 ) * 2 * M_PI, ( 0.2 + 0.25 * self::fraction( $spot, 1 ) ) * $radius );

		// A small shift and a slightly different size each day.
		$today  = hash_hmac( 'sha256', 'day|' . (int) $post_id . '|' . $day, $key );
		$center = self::move( $center, self::fraction( $today, 0 ) * 2 * M_PI, 0.1 * $radius * self::fraction( $today, 1 ) );

		return array(
			'lat'    => round( $center['lat'], 5 ),
			'lng'    => round( $center['lng'], 5 ),
			'radius' => (int) round( $radius * ( 0.85 + 0.3 * self::fraction( $today, 2 ) ) ),
		);
	}

	/**
	 * The map tiles address: the one from Settings, or OpenStreetMap's.
	 *
	 * @return string
	 */
	public static function tiles() {
		$tiles = (string) Settings::map( 'tiles' );

		return '' !== $tiles ? $tiles : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
	}

	/**
	 * The credit line under the map, as HTML.
	 *
	 * @return string
	 */
	public static function credit() {
		$credit = (string) Settings::map( 'credit' );

		if ( '' !== $credit && '' !== (string) Settings::map( 'tiles' ) ) {
			return esc_html( $credit );
		}

		return '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
	}

	/**
	 * Renders [crc_listing_location].
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

		// No map: nothing here, and the section itself is hidden (see body_class()).
		if ( ! self::shows_map( $post->ID ) ) {
			return '';
		}

		$area = self::area( $post->ID );

		if ( ! wp_style_is( 'crc-re-location', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-location' );
		wp_enqueue_script( 'crc-re-location' );

		$config = array(
			'lat'    => $area['lat'],
			'lng'    => $area['lng'],
			'radius' => $area['radius'],
			'tiles'  => self::tiles(),
			'credit' => self::credit(),
		);

		return sprintf(
			'<div class="crc-location"><div class="crc-location-map" data-crc-map="%1$s" role="region" aria-label="%2$s"></div></div>',
			esc_attr( wp_json_encode( $config ) ),
			esc_attr__( 'Map of the area around the property', 'crc-real-estate' )
		);
	}

	/**
	 * The site's secret for the offsets, made once. It never leaves the site.
	 *
	 * @return string
	 */
	private static function key() {
		$key = get_option( self::KEY_OPTION );

		if ( ! is_string( $key ) || strlen( $key ) < 32 ) {
			$key = wp_generate_password( 64, true, true );
			update_option( self::KEY_OPTION, $key, false );
		}

		return $key;
	}

	/**
	 * A number from 0 to 1 taken from part of a hash.
	 *
	 * @param string $hash Hex hash.
	 * @param int    $part Which 8 characters.
	 * @return float
	 */
	private static function fraction( $hash, $part ) {
		return hexdec( substr( $hash, $part * 8, 8 ) ) / 4294967295;
	}

	/**
	 * A point moved some metres in a direction.
	 *
	 * @param array $point  'lat' and 'lng'.
	 * @param float $angle  Direction, in radians from north.
	 * @param float $metres Distance.
	 * @return array
	 */
	private static function move( array $point, $angle, $metres ) {
		$earth = 6371000;
		$lat   = $point['lat'] + rad2deg( $metres * cos( $angle ) / $earth );
		$lng   = $point['lng'] + rad2deg( $metres * sin( $angle ) / ( $earth * max( 0.01, cos( deg2rad( $point['lat'] ) ) ) ) );

		return array(
			'lat' => max( -85, min( 85, $lat ) ),
			'lng' => fmod( $lng + 540, 360 ) - 180,
		);
	}
}
