<?php
/**
 * Property overview section.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Icons;
use CRC\RealEstate\Popup;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * The listing's four main details in boxes (property type, offered for,
 * availability and listed by), and See More, which opens a popup with those
 * boxes and every other detail, in groups with check marks.
 */
final class Overview {

	const SHORTCODE = 'crc_listing_overview';
	const MORE_META = '_crc_overview';

	/**
	 * The main details, in order, keyed by name.
	 *
	 * - label:       name on the site and on the listing screen.
	 * - meta:        where the value is saved.
	 * - icon:        icon from assets/icons.
	 * - help:        help text on the listing screen.
	 * - suggestions: values offered while typing; any other value can be typed.
	 *
	 * @return array[]
	 */
	public static function fields() {
		/**
		 * Filters the main overview details, e.g. to rename them or change the suggestions on another site.
		 *
		 * @param array[] $fields Name => details.
		 */
		$fields = apply_filters(
			'crc_re_overview_fields',
			array(
				'property_type' => array(
					'label'       => __( 'Property type', 'crc-real-estate' ),
					'meta'        => '_crc_property_type',
					'icon'        => 'land-plots',
					'help'        => __( 'What the property is, for example Bare Land, House or Apartment.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Bare Land', 'crc-real-estate' ),
						__( 'Agricultural Land', 'crc-real-estate' ),
						__( 'Commercial Land', 'crc-real-estate' ),
						__( 'House', 'crc-real-estate' ),
						__( 'Apartment', 'crc-real-estate' ),
						__( 'Villa', 'crc-real-estate' ),
						__( 'Annex', 'crc-real-estate' ),
						__( 'Room', 'crc-real-estate' ),
						__( 'Commercial Building', 'crc-real-estate' ),
					),
				),
				'offered_for'   => array(
					'label'       => __( 'Offered for', 'crc-real-estate' ),
					'meta'        => '_crc_offered_for',
					'icon'        => 'cart',
					'help'        => __( 'How it is offered, for example Sale, Rent or Lease.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Sale', 'crc-real-estate' ),
						__( 'Rent', 'crc-real-estate' ),
						__( 'Lease', 'crc-real-estate' ),
					),
				),
				'availability'  => array(
					'label'       => __( 'Availability', 'crc-real-estate' ),
					'meta'        => '_crc_availability',
					'icon'        => 'land-plots',
					'help'        => __( 'When it is ready, for example Available Now or Available Soon.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Available Now', 'crc-real-estate' ),
						__( 'Available Soon', 'crc-real-estate' ),
						__( 'Under Offer', 'crc-real-estate' ),
						__( 'Sold', 'crc-real-estate' ),
						__( 'Rented', 'crc-real-estate' ),
					),
				),
				'listed_by'     => array(
					'label'       => __( 'Listed by', 'crc-real-estate' ),
					'meta'        => '_crc_listed_by',
					'icon'        => 'road',
					'help'        => __( 'Who is offering it, for example Owner or Agent.', 'crc-real-estate' ),
					'suggestions' => array(
						__( 'Owner', 'crc-real-estate' ),
						__( 'Agent', 'crc-real-estate' ),
						__( 'Developer', 'crc-real-estate' ),
					),
				),
			)
		);

		// Details added through the filter only need a label.
		foreach ( (array) $fields as $name => $field ) {
			$fields[ $name ] = wp_parse_args(
				(array) $field,
				array(
					'label'       => $name,
					'meta'        => '_crc_' . $name,
					'icon'        => 'check',
					'help'        => '',
					'suggestions' => array(),
				)
			);
		}

		return (array) $fields;
	}

	/**
	 * Group titles and detail labels offered while typing in the See More
	 * part of the Property overview box. Ones used on other listings are
	 * offered too.
	 *
	 * @return array[] 'titles' and 'labels'.
	 */
	public static function suggestions() {
		/**
		 * Filters the suggested group titles and detail labels.
		 *
		 * @param array[] $suggestions 'titles' and 'labels'.
		 */
		return apply_filters(
			'crc_re_overview_suggestions',
			array(
				'titles' => array(
					__( 'Size and price', 'crc-real-estate' ),
				),
				'labels' => array(
					__( 'Land extent', 'crc-real-estate' ),
					__( 'Extent in perches', 'crc-real-estate' ),
					__( 'Price per perch', 'crc-real-estate' ),
					__( 'Price basis', 'crc-real-estate' ),
					__( 'Price type', 'crc-real-estate' ),
					__( 'Number of plots', 'crc-real-estate' ),
				),
			)
		);
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

		foreach ( self::fields() as $field ) {
			register_post_meta(
				Post_Type::NAME,
				$field['meta'],
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
		}

		register_post_meta(
			Post_Type::NAME,
			self::MORE_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_groups' ),
				'auth_callback'     => $auth,
			)
		);

		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Property overview', 'crc-real-estate' ),
				'description' => __( 'The four main details in boxes: property type, offered for, availability and listed by. Below them, See More opens a popup with the same boxes and all the other details, in groups with check marks. See More shows once the listing has other details. Fill them in the Property overview box on the listing screen.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'    => array(
						'default'     => '',
						'description' => __( 'Listing ID. Leave it out on a listing page to use that listing.', 'crc-real-estate' ),
					),
					'title' => array(
						'default'     => __( 'Property overview', 'crc-real-estate' ),
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
					'[' . self::SHORTCODE . ' title="Overview" more="See all details"]',
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

		wp_register_style( 'crc-re-overview', CRC_RE_URL . 'assets/css/overview.css', array( 'crc-re-popup' ), CRC_RE_VERSION );
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
	 * Loads the overview styles.
	 */
	public function enqueue_style() {
		if ( ! wp_style_is( 'crc-re-overview', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-overview' );
	}

	/**
	 * A listing's main details that have a value, in order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Name => 'label', 'value' and 'icon'.
	 */
	public static function main_details( $post_id ) {
		$details = array();

		foreach ( self::fields() as $name => $field ) {
			$value = trim( (string) get_post_meta( $post_id, $field['meta'], true ) );

			if ( '' !== $value ) {
				$details[ $name ] = array(
					'label' => $field['label'],
					'value' => $value,
					'icon'  => $field['icon'],
				);
			}
		}

		return $details;
	}

	/**
	 * A listing's other details, in groups, in their saved order.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each group has a 'title' and 'items', each item a 'label' and a 'value'.
	 */
	public static function groups( $post_id ) {
		return self::sanitize_groups( get_post_meta( $post_id, self::MORE_META, true ) );
	}

	/**
	 * Cleans groups of details: plain text only, empty details dropped, and
	 * a group kept when it has a title or a detail.
	 *
	 * @param mixed $groups Groups as typed or saved.
	 * @return array[]
	 */
	public static function sanitize_groups( $groups ) {
		$clean = array();

		if ( ! is_array( $groups ) ) {
			return $clean;
		}

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$title = isset( $group['title'] ) && is_scalar( $group['title'] ) ? sanitize_text_field( (string) $group['title'] ) : '';
			$items = array();

			if ( isset( $group['items'] ) && is_array( $group['items'] ) ) {
				foreach ( $group['items'] as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$label = isset( $item['label'] ) && is_scalar( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
					$value = isset( $item['value'] ) && is_scalar( $item['value'] ) ? sanitize_text_field( (string) $item['value'] ) : '';

					if ( '' !== $label || '' !== $value ) {
						$items[] = array(
							'label' => $label,
							'value' => $value,
						);
					}
				}
			}

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
	 * Renders [crc_listing_overview].
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

		$main   = self::main_details( $post->ID );
		$groups = array_values(
			array_filter(
				self::groups( $post->ID ),
				function ( $group ) {
					return ! empty( $group['items'] );
				}
			)
		);

		if ( ! $main && ! $groups ) {
			return Shortcodes::placeholder( self::SHORTCODE, __( 'This listing has no overview details yet. Fill them in the Property overview box on the listing screen.', 'crc-real-estate' ) );
		}

		$this->enqueue_style();

		$html = '<div class="crc-overview">';

		if ( $main ) {
			$html .= $this->cards( $main );
		}

		if ( $groups ) {
			$id = Popup::new_id( 'overview' );

			$html .= sprintf(
				'<button type="button" class="crc-overview-more"%1$s><span class="crc-overview-more-text">%2$s</span>%3$s</button>',
				Popup::opener( $id ),
				esc_html( $atts['more'] ),
				Icons::svg( 'arrow-right', 'crc-overview-more-icon' )
			);

			$html .= Popup::render( $id, $atts['title'], '<div class="crc-overview-all">' . ( $main ? $this->cards( $main ) : '' ) . $this->groups_html( $groups ) . '</div>' );
		}

		return $html . '</div>';
	}

	/**
	 * The main details as boxes.
	 *
	 * @param array[] $details Main details from main_details().
	 * @return string
	 */
	private function cards( array $details ) {
		$html = '<div class="crc-overview-cards">';

		foreach ( $details as $name => $detail ) {
			$html .= sprintf(
				'<div class="crc-overview-card crc-overview-card-%1$s">%2$s<div class="crc-overview-text"><h6 class="crc-overview-label">%3$s</h6><p class="crc-overview-value">%4$s</p></div></div>',
				esc_attr( str_replace( '_', '-', sanitize_key( $name ) ) ),
				Icons::svg( $detail['icon'], 'crc-overview-icon' ),
				esc_html( $detail['label'] ),
				esc_html( $detail['value'] )
			);
		}

		return $html . '</div>';
	}

	/**
	 * The other details: each group's title and its details with check marks.
	 *
	 * @param array[] $groups Groups that have details.
	 * @return string
	 */
	private function groups_html( array $groups ) {
		$html = '';

		foreach ( $groups as $group ) {
			$html .= '<div class="crc-overview-group">';

			if ( '' !== $group['title'] ) {
				$html .= '<h6 class="crc-overview-group-title">' . esc_html( $group['title'] ) . '</h6>';
			}

			$html .= '<ul class="crc-overview-list" role="list">';

			foreach ( $group['items'] as $item ) {
				$html .= '<li class="crc-overview-item">' . Icons::svg( 'check', 'crc-overview-icon' ) . '<div class="crc-overview-text">';

				if ( '' !== $item['label'] ) {
					$html .= '<p class="crc-overview-item-label">' . esc_html( $item['label'] ) . '</p>';
				}

				if ( '' !== $item['value'] ) {
					$html .= '<p class="crc-overview-item-value">' . esc_html( $item['value'] ) . '</p>';
				}

				$html .= '</div></li>';
			}

			$html .= '</ul></div>';
		}

		return $html;
	}
}
