<?php
/**
 * Shared parts of the listing screen boxes with ready-made groups.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * What the Property overview and Property features boxes share: the listing's
 * category and which ready-made groups are for it, suggestion lists, and the
 * script that switches groups with the category and adds, removes and drags
 * groups and their rows.
 */
final class Boxes {

	/**
	 * Whether the script's texts are already on the page.
	 *
	 * @var bool
	 */
	private static $texts = false;

	/**
	 * Whether the listing edit screen is showing.
	 *
	 * @param string $hook Current admin page.
	 * @return bool
	 */
	public static function on_listing_screen( $hook ) {
		$screen = get_current_screen();

		return $screen && Post_Type::NAME === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );
	}

	/**
	 * Loads the boxes' script.
	 */
	public static function enqueue_script() {
		wp_enqueue_script( 'crc-re-admin-box', CRC_RE_URL . 'assets/js/admin-box.js', array( 'jquery', 'jquery-ui-sortable' ), CRC_RE_VERSION, true );

		if ( ! self::$texts ) {
			self::$texts = true;

			wp_localize_script(
				'crc-re-admin-box',
				'crcReBox',
				array(
					'confirmRemove' => __( 'Remove this group and everything in it?', 'crc-real-estate' ),
				)
			);
		}
	}

	/**
	 * The listing's category, as a term ID.
	 *
	 * @param \WP_Post $post Listing being edited.
	 * @return int 0 when none is chosen.
	 */
	public static function category( $post ) {
		$terms = wp_get_object_terms( $post->ID, Taxonomy::NAME, array( 'fields' => 'ids' ) );

		return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
	}

	/**
	 * Term IDs for category slugs.
	 *
	 * @param string[] $slugs Category slugs.
	 * @return int[]
	 */
	public static function term_ids( array $slugs ) {
		$ids = array();

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * The categories a ready-made group is for, as the box's script reads
	 * them: term IDs, or "all".
	 *
	 * @param string[] $slugs The group's category slugs; none for every category.
	 * @return string
	 */
	public static function categories_attr( array $slugs ) {
		return $slugs ? implode( ' ', self::term_ids( $slugs ) ) : 'all';
	}

	/**
	 * Whether a ready-made group is for the listing's category.
	 *
	 * @param string[] $slugs    The group's category slugs; none for every category.
	 * @param int      $category The listing's category, as a term ID.
	 * @return bool
	 */
	public static function is_for( array $slugs, $category ) {
		return ! $slugs || in_array( (int) $category, self::term_ids( $slugs ), true );
	}

	/**
	 * Prints a list of suggestions for text fields.
	 *
	 * @param string   $id     List id.
	 * @param string[] $values Suggestions.
	 */
	public static function datalist( $id, array $values ) {
		echo '<datalist id="' . esc_attr( $id ) . '">';

		foreach ( $values as $value ) {
			echo '<option value="' . esc_attr( $value ) . '"></option>';
		}

		echo '</datalist>';
	}

	/**
	 * Recently edited listings, to suggest what was typed on them before.
	 *
	 * @param int $count How many.
	 * @return int[]
	 */
	public static function recent_listings( $count ) {
		$ids = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'any',
				'posts_per_page' => $count,
				'orderby'        => 'modified',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}

		return $ids;
	}

	/**
	 * Suggestions without blanks or repeats.
	 *
	 * @param string[] $values Suggestions.
	 * @return string[]
	 */
	public static function unique( array $values ) {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $values ) ), 'strlen' ) ) );
	}
}
