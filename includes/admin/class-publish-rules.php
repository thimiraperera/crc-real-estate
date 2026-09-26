<?php
/**
 * What a listing needs before it can go live.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * A listing needs a featured image (the gallery's large photo) and a category
 * before it can be published. Without them it is kept as a draft, with a
 * notice saying what is missing. Saving a draft is always allowed.
 */
final class Publish_Rules {

	const NOTICE = 'crc_missing';

	/**
	 * What was missing on the current save.
	 *
	 * @var string[]
	 */
	private $missing = array();

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'wp_insert_post_data', array( $this, 'check_save' ), 10, 2 );
		add_filter( 'rest_pre_insert_' . Post_Type::NAME, array( $this, 'check_rest' ), 10, 2 );
		add_filter( 'redirect_post_location', array( $this, 'redirect' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_args' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'box_note' ), 10, 2 );
	}

	/**
	 * Statuses that make a listing visible or send it for review.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private static function goes_live( $status ) {
		return in_array( $status, array( 'publish', 'future', 'pending' ), true );
	}

	/**
	 * Whether any of the submitted category IDs is a real choice.
	 *
	 * @param mixed $ids Submitted IDs.
	 * @return bool
	 */
	private static function has_choice( $ids ) {
		return (bool) array_filter( array_map( 'absint', (array) $ids ) );
	}

	/**
	 * Whether a listing already has a category.
	 *
	 * @param int $post_id Listing ID.
	 * @return bool
	 */
	private static function saved_category( $post_id ) {
		if ( ! $post_id ) {
			return false;
		}

		$terms = wp_get_object_terms( $post_id, Taxonomy::NAME, array( 'fields' => 'ids' ) );

		return ! is_wp_error( $terms ) && ! empty( $terms );
	}

	/**
	 * Keeps a listing as a draft when something required is missing.
	 *
	 * @param array $data    Post data about to be saved.
	 * @param array $postarr Submitted post data.
	 * @return array
	 */
	public function check_save( $data, $postarr ) {
		if ( Post_Type::NAME !== $data['post_type'] || ! self::goes_live( $data['post_status'] ) ) {
			return $data;
		}

		// REST saves add the image and category after this point, so check_rest() handles them.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $data;
		}

		$post_id = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( isset( $postarr['_thumbnail_id'] ) ) {
			$has_image = (int) $postarr['_thumbnail_id'] > 0;
		} else {
			$has_image = $post_id && (int) get_post_thumbnail_id( $post_id ) > 0;
		}

		if ( isset( $postarr['tax_input'][ Taxonomy::NAME ] ) ) {
			$has_category = self::has_choice( $postarr['tax_input'][ Taxonomy::NAME ] );
		} else {
			$has_category = self::saved_category( $post_id );
		}

		$this->missing = array_keys( array_filter( array( 'image' => ! $has_image, 'category' => ! $has_category ) ) );

		if ( $this->missing ) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}

	/**
	 * Refuses REST requests that publish a listing with something missing.
	 *
	 * @param \stdClass        $prepared Post about to be saved.
	 * @param \WP_REST_Request $request  Request.
	 * @return \stdClass|\WP_Error
	 */
	public function check_rest( $prepared, $request ) {
		$post_id = ! empty( $prepared->ID ) ? (int) $prepared->ID : 0;
		$status  = ! empty( $prepared->post_status ) ? $prepared->post_status : ( $post_id ? get_post_status( $post_id ) : 'draft' );

		if ( ! self::goes_live( $status ) ) {
			return $prepared;
		}

		$image    = isset( $request['featured_media'] ) ? (int) $request['featured_media'] : ( $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0 );
		$category = isset( $request[ Taxonomy::NAME ] ) ? self::has_choice( $request[ Taxonomy::NAME ] ) : self::saved_category( $post_id );
		$missing  = array_keys( array_filter( array( 'image' => $image < 1, 'category' => ! $category ) ) );

		if ( ! $missing ) {
			return $prepared;
		}

		return new \WP_Error( 'crc_listing_incomplete', self::message( $missing ), array( 'status' => 400 ) );
	}

	/**
	 * Explains what is missing.
	 *
	 * @param string[] $missing "image" and/or "category".
	 * @return string
	 */
	private static function message( $missing ) {
		if ( array( 'image', 'category' ) === $missing ) {
			return __( 'Add a main photo and choose a category to publish this listing.', 'crc-real-estate' );
		}

		if ( array( 'category' ) === $missing ) {
			return __( 'Choose a category to publish this listing.', 'crc-real-estate' );
		}

		return __( 'Add a main photo to publish this listing.', 'crc-real-estate' );
	}

	/**
	 * Carries what was missing to the page shown after saving.
	 *
	 * @param string $location Address to return to after saving.
	 * @return string
	 */
	public function redirect( $location ) {
		return $this->missing ? add_query_arg( self::NOTICE, implode( ',', $this->missing ), $location ) : $location;
	}

	/**
	 * Lets WordPress tidy the notice flag out of the address bar.
	 *
	 * @param string[] $args Removable query arguments.
	 * @return string[]
	 */
	public function removable_args( $args ) {
		$args[] = self::NOTICE;

		return $args;
	}

	/**
	 * Explains why the listing wasn't published.
	 */
	public function notice() {
		$screen = get_current_screen();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
		if ( ! $screen || 'post' !== $screen->base || Post_Type::NAME !== $screen->post_type || empty( $_GET[ self::NOTICE ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
		$missing = array_values( array_intersect( array( 'image', 'category' ), explode( ',', sanitize_text_field( wp_unslash( $_GET[ self::NOTICE ] ) ) ) ) );

		if ( ! $missing ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p>%1$s %2$s</p></div>',
			esc_html__( 'The listing was saved as a draft.', 'crc-real-estate' ),
			esc_html( self::message( $missing ) )
		);
	}

	/**
	 * Adds a hint under the Featured image box.
	 *
	 * @param string $html    Box contents.
	 * @param int    $post_id Post being edited.
	 * @return string
	 */
	public function box_note( $html, $post_id ) {
		if ( Post_Type::NAME !== get_post_type( $post_id ) ) {
			return $html;
		}

		return $html . '<p class="description crc-featured-note">' . esc_html__( 'Required. This is the large photo at the top of the listing.', 'crc-real-estate' ) . '</p>';
	}
}
