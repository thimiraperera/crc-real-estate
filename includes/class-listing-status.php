<?php
/**
 * Listings on or off the website, and listings that have gone.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Overview;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a listing is on the website, and whether it has gone.
 *
 * A listing switched off is kept as a private post: WordPress then leaves it
 * out of every page, search, carousel and sitemap for visitors, and keeps
 * everything in it. In wp-admin it is called Unlisted, not Private.
 *
 * A listing that has gone is still on the website, marked Sold, or Rented for
 * properties for rent (the category's 'gone' word, see Taxonomy::terms()). It
 * is kept as the listing's Availability.
 */
final class Listing_Status {

	/**
	 * The status of a listing that is off the website.
	 */
	const OFF = 'private';

	/**
	 * Where a listing's Availability is kept.
	 */
	const AVAILABILITY_META = '_crc_availability';

	/**
	 * Its Availability from before it was marked Sold or Rented, to go back to.
	 */
	const BEFORE_META = '_crc_availability_before';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'private_title_format', array( $this, 'title_format' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'states' ), 10, 2 );
		add_filter( 'views_edit-' . Post_Type::NAME, array( $this, 'views' ) );
		add_action( 'added_post_meta', array( $this, 'forget_before' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'forget_before' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'forget_before' ), 10, 3 );
	}

	/**
	 * Writes "Unlisted:" instead of "Private:" before an unlisted listing's
	 * title. Only people logged in who can see unlisted listings see it, on
	 * the listing's own page.
	 *
	 * @param string   $format Title format.
	 * @param \WP_Post $post   Post.
	 * @return string
	 */
	public function title_format( $format, $post = null ) {
		/* translators: %s: listing title. */
		return is_object( $post ) && isset( $post->post_type ) && Post_Type::NAME === $post->post_type ? __( 'Unlisted: %s', 'crc-real-estate' ) : $format;
	}

	/**
	 * Calls the list's Private link Unlisted, for the listings switched off.
	 *
	 * @param string[] $views Links above the list.
	 * @return string[]
	 */
	public function views( $views ) {
		if ( isset( $views['private'] ) ) {
			$views['private'] = preg_replace( '/>[^<]+<span class="count">/', '>' . esc_html__( 'Unlisted', 'crc-real-estate' ) . ' <span class="count">', $views['private'], 1 );
		}

		return $views;
	}

	/**
	 * Forgets the Availability from before a listing was marked Sold or
	 * Rented once it is changed to something else, e.g. on its own screen.
	 *
	 * @param int|int[] $meta_id  Meta ID.
	 * @param int       $post_id  Listing ID.
	 * @param string    $meta_key Meta key.
	 */
	public function forget_before( $meta_id, $post_id, $meta_key ) {
		if ( self::AVAILABILITY_META === $meta_key && ! self::gone( $post_id ) ) {
			delete_post_meta( $post_id, self::BEFORE_META );
		}
	}

	/**
	 * Calls an unlisted listing Unlisted in the Listings list.
	 *
	 * @param string[] $states Post states.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public function states( $states, $post ) {
		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return $states;
		}

		if ( isset( $states['private'] ) ) {
			unset( $states['private'] );
			$states['crc_unlisted'] = __( 'Unlisted', 'crc-real-estate' );
		}

		return $states;
	}

	/**
	 * Whether a listing is on the website.
	 *
	 * @param int|\WP_Post $post Listing.
	 * @return bool
	 */
	public static function is_on( $post ) {
		return 'publish' === get_post_status( $post );
	}

	/**
	 * Whether a listing can be switched on or off: one that is on the
	 * website or unlisted. Drafts and scheduled listings can't be yet.
	 *
	 * @param int|\WP_Post $post Listing.
	 * @return bool
	 */
	public static function can_switch( $post ) {
		return in_array( get_post_status( $post ), array( 'publish', self::OFF ), true );
	}

	/**
	 * The words for a listing that has gone, by the category's 'gone' word.
	 *
	 * @return string[] "sold" and "rented" => words.
	 */
	public static function gone_words() {
		return array(
			'sold'   => __( 'Sold', 'crc-real-estate' ),
			'rented' => __( 'Rented', 'crc-real-estate' ),
		);
	}

	/**
	 * Which word a listing gets when it has gone: "rented" for properties for
	 * rent, otherwise "sold".
	 *
	 * @param int $post_id Listing ID.
	 * @return string "sold" or "rented".
	 */
	public static function gone_key( $post_id ) {
		$category = Taxonomy::listing_category( $post_id );

		return $category && isset( $category['gone'] ) && 'rented' === $category['gone'] ? 'rented' : 'sold';
	}

	/**
	 * A word as the Availability list writes it, e.g. "Sold".
	 *
	 * @param string $word Word.
	 * @return string
	 */
	private static function availability( $word ) {
		$fields = Overview::fields();
		$chosen = isset( $fields['availability'] ) ? Overview::choice( $fields['availability'], $word ) : '';

		return '' !== $chosen ? $chosen : $word;
	}

	/**
	 * What a listing's Availability becomes when it is marked as gone: Sold, or Rented.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function gone_label( $post_id ) {
		$words = self::gone_words();

		return self::availability( $words[ self::gone_key( $post_id ) ] );
	}

	/**
	 * Whether a listing has gone: its Availability is Sold or Rented.
	 *
	 * @param int $post_id Listing ID.
	 * @return array|null 'key' ("sold" or "rented") and 'label', or null.
	 */
	public static function gone( $post_id ) {
		$value = trim( (string) get_post_meta( $post_id, self::AVAILABILITY_META, true ) );

		if ( '' === $value ) {
			return null;
		}

		foreach ( self::gone_words() as $key => $word ) {
			if ( 0 === strcasecmp( $value, $word ) ) {
				return array(
					'key'   => $key,
					'label' => $value,
				);
			}
		}

		return null;
	}

	/**
	 * Marks a listing as gone (Sold or Rented by its category), or as
	 * available again: what it was before it was marked, or Available Now.
	 *
	 * @param int  $post_id Listing ID.
	 * @param bool $gone    Whether it has gone.
	 * @return string Its Availability now.
	 */
	public static function mark( $post_id, $gone ) {
		$now = (string) get_post_meta( $post_id, self::AVAILABILITY_META, true );

		if ( $gone ) {
			$label = self::gone_label( $post_id );

			if ( ! self::gone( $post_id ) ) {
				if ( '' !== $now ) {
					update_post_meta( $post_id, self::BEFORE_META, wp_slash( $now ) );
				} else {
					delete_post_meta( $post_id, self::BEFORE_META );
				}
			}
		} else {
			// Only a listing that has gone comes back; any other keeps its Availability.
			if ( ! self::gone( $post_id ) ) {
				return $now;
			}

			$before = (string) get_post_meta( $post_id, self::BEFORE_META, true );
			$label  = '' !== $before ? $before : self::availability( __( 'Available Now', 'crc-real-estate' ) );

			delete_post_meta( $post_id, self::BEFORE_META );
		}

		if ( $label !== $now ) {
			update_post_meta( $post_id, self::AVAILABILITY_META, wp_slash( $label ) );
		}

		// The listing's own page shows Sold under its price.
		do_action( 'litespeed_purge_post', $post_id );

		return $label;
	}

	/**
	 * Switches a listing on the website or off it. A listing needs a main
	 * photo and a category to be on the website.
	 *
	 * @param int  $post_id Listing ID.
	 * @param bool $on      Whether it should be on the website.
	 * @return true|\WP_Error
	 */
	public static function turn( $post_id, $on ) {
		if ( ! self::can_switch( $post_id ) ) {
			return new \WP_Error( 'crc_not_live', __( 'This listing isn\'t published yet. Publish it from its own screen first.', 'crc-real-estate' ) );
		}

		if ( $on === self::is_on( $post_id ) ) {
			return true;
		}

		if ( $on ) {
			$missing = Admin\Publish_Rules::missing( $post_id );

			if ( $missing ) {
				return new \WP_Error( 'crc_listing_incomplete', Admin\Publish_Rules::message( $missing ) );
			}

			if ( (int) get_post_time( 'U', true, $post_id ) - time() >= MINUTE_IN_SECONDS ) {
				return new \WP_Error( 'crc_future_date', __( 'This listing\'s date is in the future, so WordPress would schedule it for that date instead of showing it now. Open the listing, change its date to today, then turn it on.', 'crc-real-estate' ) );
			}
		}

		$saved = wp_update_post(
			array(
				'ID'          => (int) $post_id,
				'post_status' => $on ? 'publish' : self::OFF,
			),
			true
		);

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( $on !== self::is_on( $post_id ) ) {
			return new \WP_Error( 'crc_not_changed', __( 'The listing couldn\'t be changed. Reload the page and try again.', 'crc-real-estate' ) );
		}

		// Back on the website: its page, if it was kept in the cache as missing while it was off, goes.
		if ( $on ) {
			do_action( 'litespeed_purge_url', get_permalink( $post_id ) );
		}

		return true;
	}
}
