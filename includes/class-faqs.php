<?php
/**
 * FAQs.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Sets of frequently asked questions, kept as their own posts under the FAQs
 * menu: each has a name for wp-admin and its questions with their answers,
 * kept like a listing's own questions (Faq::META). [crc_faqs id="…"] shows
 * one on any page in the FAQ cards, with its questions given to search
 * engines as FAQ structured data (see Sections\Faq).
 */
final class Faqs {

	const POST_TYPE = 'crc_faq';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'saved' ), 20, 2 );
		add_action( 'trashed_post', array( $this, 'removed' ) );
		add_action( 'deleted_post', array( $this, 'removed' ) );
	}

	/**
	 * Registers the post type.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                   => _x( 'FAQs', 'post type general name', 'crc-real-estate' ),
					'singular_name'          => _x( 'FAQ', 'post type singular name', 'crc-real-estate' ),
					'menu_name'              => _x( 'FAQs', 'admin menu', 'crc-real-estate' ),
					'add_new'                => __( 'Add New FAQ', 'crc-real-estate' ),
					'add_new_item'           => __( 'Add New FAQ', 'crc-real-estate' ),
					'edit_item'              => __( 'Edit FAQ', 'crc-real-estate' ),
					'new_item'               => __( 'New FAQ', 'crc-real-estate' ),
					'view_item'              => __( 'View FAQ', 'crc-real-estate' ),
					'search_items'           => __( 'Search FAQs', 'crc-real-estate' ),
					'not_found'              => __( 'No FAQs yet.', 'crc-real-estate' ),
					'not_found_in_trash'     => __( 'No FAQs in the Trash.', 'crc-real-estate' ),
					'all_items'              => __( 'All FAQs', 'crc-real-estate' ),
					'item_published'         => __( 'FAQ published.', 'crc-real-estate' ),
					'item_updated'           => __( 'FAQ updated.', 'crc-real-estate' ),
					'item_reverted_to_draft' => __( 'FAQ reverted to draft.', 'crc-real-estate' ),
					'filter_items_list'      => __( 'Filter FAQs list', 'crc-real-estate' ),
					'items_list'             => __( 'FAQs list', 'crc-real-estate' ),
				),
				// Shown only through [crc_faqs id="…"]: no pages of their own.
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'menu_position'       => 6,
				'menu_icon'           => 'dashicons-editor-help',
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/**
	 * An FAQ's questions, in order.
	 *
	 * @param int $post_id FAQ ID.
	 * @return array[] Items with 'question', 'answer', 'link_text' and 'link_url'.
	 */
	public static function items( $post_id ) {
		return Faq::sanitize_items( get_post_meta( $post_id, Faq::META, true ) );
	}

	/**
	 * The FAQ a shortcode asks for: a published one, or one that isn't
	 * published yet for people who can edit it. Never one in the Trash.
	 *
	 * @param int|string $id FAQ ID.
	 * @return \WP_Post|null
	 */
	public static function find( $id ) {
		$post = absint( $id ) ? get_post( absint( $id ) ) : null;

		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * The shortcode that shows an FAQ, e.g. [crc_faqs id="123"].
	 *
	 * @param int $post_id FAQ ID.
	 * @return string
	 */
	public static function shortcode( $post_id ) {
		return '[' . Faq::GENERAL_SHORTCODE . ' id="' . (int) $post_id . '"]';
	}

	/**
	 * Whether any FAQ is published, so pages load the FAQ styles in their head.
	 *
	 * @return bool
	 */
	public static function any_published() {
		$counts = wp_count_posts( self::POST_TYPE );

		return isset( $counts->publish ) && (int) $counts->publish > 0;
	}

	/**
	 * Clears the LiteSpeed page cache, so visitors see a changed FAQ straight
	 * away. Does nothing without LiteSpeed Cache.
	 */
	public function clear_cache() {
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Clears the cache when an FAQ is saved, but not for autosaves.
	 *
	 * @param int      $post_id FAQ ID.
	 * @param \WP_Post $post    FAQ.
	 */
	public function saved( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$this->clear_cache();
	}

	/**
	 * Clears the cache when an FAQ goes to the Trash or is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function removed( $post_id ) {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			$this->clear_cache();
		}
	}
}
