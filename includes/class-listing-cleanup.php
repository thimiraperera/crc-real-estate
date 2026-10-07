<?php
/**
 * Deleting listings.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Gallery;

defined( 'ABSPATH' ) || exit;

/**
 * When a listing is deleted for good (the Trash emptied, or Delete
 * Permanently), its photos go too: the main photo, the gallery photos and
 * any file uploaded to it. A photo used somewhere else is kept. Listings →
 * Settings can turn this off.
 */
final class Listing_Cleanup {

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'before_delete_post', array( $this, 'delete_media' ), 5, 2 );
	}

	/**
	 * Deletes a listing's photos as it is deleted for good.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post, from WordPress 5.5.
	 */
	public function delete_media( $post_id, $post = null ) {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );

		if ( ! $post || Post_Type::NAME !== $post->post_type || ! Settings::cleanup( 'media' ) ) {
			return;
		}

		foreach ( self::media( $post->ID ) as $id ) {
			if ( ! self::used_elsewhere( $id, $post->ID ) ) {
				wp_delete_attachment( $id, true );
			}
		}
	}

	/**
	 * A listing's files: its main photo, its gallery photos and every file
	 * uploaded to it.
	 *
	 * @param int $post_id Listing ID.
	 * @return int[]
	 */
	public static function media( $post_id ) {
		$uploaded = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'post_parent'      => (int) $post_id,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);
		$ids      = array_merge( array( (int) get_post_thumbnail_id( $post_id ) ), Gallery::gallery_ids( $post_id ), array_map( 'intval', (array) $uploaded ) );

		return array_values(
			array_filter(
				array_unique( array_map( 'intval', $ids ) ),
				function ( $id ) {
					return $id > 0 && 'attachment' === get_post_type( $id );
				}
			)
		);
	}

	/**
	 * Whether a file is also used somewhere else: as another post's main
	 * photo, in another listing's gallery, or on a category carousel card.
	 *
	 * @param int $id         Attachment ID.
	 * @param int $listing_id The listing being deleted.
	 * @return bool
	 */
	public static function used_elsewhere( $id, $listing_id ) {
		$id    = (int) $id;
		$other = array(
			'post_type'        => 'any',
			'post_status'      => 'any',
			'post__not_in'     => array( (int) $listing_id ),
			'fields'           => 'ids',
			'suppress_filters' => true,
		);

		// phpcs:disable WordPress.DB.SlowDBQuery -- Only while a listing is deleted.
		$featured = get_posts(
			$other + array(
				'meta_key'    => '_thumbnail_id',
				'meta_value'  => $id,
				'numberposts' => 1,
			)
		);

		if ( $featured ) {
			return true;
		}

		$galleries = get_posts(
			array_merge(
				$other,
				array(
					'post_type'    => Post_Type::NAME,
					'meta_key'     => Gallery::META,
					'meta_value'   => 'i:' . $id . ';',
					'meta_compare' => 'LIKE',
					'numberposts'  => -1,
				)
			)
		);
		// phpcs:enable

		foreach ( (array) $galleries as $listing ) {
			if ( in_array( $id, Gallery::gallery_ids( (int) $listing ), true ) ) {
				return true;
			}
		}

		foreach ( Category_Carousel::cards() as $card ) {
			if ( (int) $card['image'] === $id ) {
				return true;
			}
		}

		return false;
	}
}
