<?php
/**
 * Required featured image.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Stops a listing going live without a featured image, because the gallery's
 * large photo is the featured image. Saving a draft is still allowed.
 */
final class Featured_Image {

	const NOTICE = 'crc_featured_image';

	/**
	 * Whether the current save was turned into a draft.
	 *
	 * @var bool
	 */
	private $blocked = false;

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
	 * Keeps a listing as a draft when it has no featured image.
	 *
	 * @param array $data    Post data about to be saved.
	 * @param array $postarr Submitted post data.
	 * @return array
	 */
	public function check_save( $data, $postarr ) {
		if ( Post_Type::NAME !== $data['post_type'] || ! self::goes_live( $data['post_status'] ) ) {
			return $data;
		}

		// REST saves set the featured image after this point, so check_rest() handles them.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $data;
		}

		if ( isset( $postarr['_thumbnail_id'] ) ) {
			$has_image = (int) $postarr['_thumbnail_id'] > 0;
		} else {
			$has_image = ! empty( $postarr['ID'] ) && (int) get_post_thumbnail_id( (int) $postarr['ID'] ) > 0;
		}

		if ( ! $has_image ) {
			$data['post_status'] = 'draft';
			$this->blocked       = true;
		}

		return $data;
	}

	/**
	 * Refuses REST requests that publish a listing without a featured image.
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

		$image = isset( $request['featured_media'] ) ? (int) $request['featured_media'] : ( $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0 );

		if ( $image > 0 ) {
			return $prepared;
		}

		return new \WP_Error( 'crc_featured_image_required', __( 'Add a featured image before publishing this listing.', 'crc-real-estate' ), array( 'status' => 400 ) );
	}

	/**
	 * Flags the notice after a save that was kept as a draft.
	 *
	 * @param string $location Address to return to after saving.
	 * @return string
	 */
	public function redirect( $location ) {
		return $this->blocked ? add_query_arg( self::NOTICE, 1, $location ) : $location;
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

		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			esc_html__( 'The listing was saved as a draft. Add a featured image to publish it.', 'crc-real-estate' )
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
