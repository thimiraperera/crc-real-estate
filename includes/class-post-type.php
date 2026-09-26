<?php
/**
 * Listings post type.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Listings post type and its admin basics.
 */
final class Post_Type {

	const NAME = 'crc_listing';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'after_setup_theme', array( $this, 'add_thumbnail_support' ), 100 );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'use_block_editor' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'current_screen', array( $this, 'remove_media_button' ) );
	}

	/**
	 * Registers the post type.
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Listings', 'post type general name', 'crc-real-estate' ),
			'singular_name'         => _x( 'Listing', 'post type singular name', 'crc-real-estate' ),
			'menu_name'             => _x( 'Listings', 'admin menu', 'crc-real-estate' ),
			'add_new'               => __( 'Add New Listing', 'crc-real-estate' ),
			'add_new_item'          => __( 'Add New Listing', 'crc-real-estate' ),
			'edit_item'             => __( 'Edit Listing', 'crc-real-estate' ),
			'new_item'              => __( 'New Listing', 'crc-real-estate' ),
			'view_item'             => __( 'View Listing', 'crc-real-estate' ),
			'view_items'            => __( 'View Listings', 'crc-real-estate' ),
			'search_items'          => __( 'Search Listings', 'crc-real-estate' ),
			'not_found'             => __( 'No listings found.', 'crc-real-estate' ),
			'not_found_in_trash'    => __( 'No listings found in Trash.', 'crc-real-estate' ),
			'all_items'             => __( 'All Listings', 'crc-real-estate' ),
			'archives'              => __( 'Listing Archives', 'crc-real-estate' ),
			'attributes'            => __( 'Listing Attributes', 'crc-real-estate' ),
			'insert_into_item'      => __( 'Insert into listing', 'crc-real-estate' ),
			'uploaded_to_this_item' => __( 'Uploaded to this listing', 'crc-real-estate' ),
			'featured_image'        => __( 'Main photo', 'crc-real-estate' ),
			'set_featured_image'    => __( 'Set main photo', 'crc-real-estate' ),
			'remove_featured_image' => __( 'Remove main photo', 'crc-real-estate' ),
			'use_featured_image'    => __( 'Use as main photo', 'crc-real-estate' ),
			'filter_items_list'     => __( 'Filter listings list', 'crc-real-estate' ),
			'items_list_navigation' => __( 'Listings list navigation', 'crc-real-estate' ),
			'items_list'            => __( 'Listings list', 'crc-real-estate' ),
			'item_published'        => __( 'Listing published.', 'crc-real-estate' ),
			'item_updated'          => __( 'Listing updated.', 'crc-real-estate' ),
			'item_scheduled'        => __( 'Listing scheduled.', 'crc-real-estate' ),
			'item_reverted_to_draft' => __( 'Listing reverted to draft.', 'crc-real-estate' ),
		);

		register_post_type(
			self::NAME,
			array(
				'labels'        => $labels,
				'public'        => true,
				'show_in_rest'  => true,
				'menu_position' => 5,
				'menu_icon'     => 'dashicons-admin-home',
				'supports'      => array( 'title', 'editor', 'thumbnail', 'revisions' ),
				'has_archive'   => false,
				'rewrite'       => array(
					/**
					 * Filters the URL base of single listings, e.g. example.com/listing/my-listing/.
					 *
					 * @param string $slug URL base.
					 */
					'slug'       => apply_filters( 'crc_re_listing_slug', 'listing' ),
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Makes sure listings can have a featured image, whatever the theme supports.
	 */
	public function add_thumbnail_support() {
		add_theme_support( 'post-thumbnails', array( self::NAME ) );
	}

	/**
	 * Uses the classic edit screen for listings, where the listing boxes sit.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post type being edited.
	 * @return bool
	 */
	public function use_block_editor( $use_block_editor, $post_type ) {
		return self::NAME === $post_type ? false : $use_block_editor;
	}

	/**
	 * Removes "Add Media" above the listing's text box; photos have their own boxes.
	 *
	 * @param \WP_Screen $screen Current admin screen.
	 */
	public function remove_media_button( $screen ) {
		if ( 'post' === $screen->base && self::NAME === $screen->post_type ) {
			remove_action( 'media_buttons', 'media_buttons' );
		}
	}

	/**
	 * Shows each listing's ID in the Listings list, for shortcodes that take id="…".
	 *
	 * @param string[] $actions Row actions.
	 * @param \WP_Post $post    Post in the row.
	 * @return string[]
	 */
	public function row_actions( $actions, $post ) {
		if ( self::NAME !== $post->post_type ) {
			return $actions;
		}

		/* translators: %d: listing ID. */
		$id = sprintf( __( 'ID: %d', 'crc-real-estate' ), $post->ID );

		return array( 'crc_id' => '<span class="crc-listing-id">' . esc_html( $id ) . '</span>' ) + $actions;
	}
}
