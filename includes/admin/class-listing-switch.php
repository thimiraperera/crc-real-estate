<?php
/**
 * All Listings: the On the website switch and Sold or Rented.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Listing_Status;
use CRC\RealEstate\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Two columns on All Listings:
 *
 * - On the website: a switch. Off unlists the listing: it disappears from
 *   the website (search, listing pages, carousels and its own page) and stays
 *   here with everything in it. On brings it back, in the same place.
 * - Availability: what it is now, with a button that marks it Sold, or
 *   Rented for properties for rent (the category decides), and back.
 *
 * Both change straight away, without reloading, and the same choices are
 * in Bulk actions for many listings at once.
 */
final class Listing_Switch {

	const NONCE = 'crc_re_listing_list';

	const AJAX_LIVE = 'crc_re_listing_live';

	const AJAX_GONE = 'crc_re_listing_gone';

	const DONE_ARG = 'crc_done';

	const SKIPPED_ARG = 'crc_skipped';

	const DID_ARG = 'crc_did';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . Post_Type::NAME . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_LIVE, array( $this, 'ajax_live' ) );
		add_action( 'wp_ajax_' . self::AJAX_GONE, array( $this, 'ajax_gone' ) );
		add_filter( 'bulk_actions-edit-' . Post_Type::NAME, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . Post_Type::NAME, array( $this, 'bulk' ), 10, 3 );
		add_filter( 'removable_query_args', array( $this, 'removable_args' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'publish_box' ) );
	}

	/**
	 * Adds On the website and Availability after the title.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function add_columns( $columns ) {
		$result = array();

		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;

			if ( 'title' === $key ) {
				$result['crc_live']         = __( 'On the website', 'crc-real-estate' );
				$result['crc_availability'] = __( 'Availability', 'crc-real-estate' );
			}
		}

		return $result;
	}

	/**
	 * The switch for one listing.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function switch_html( $post_id ) {
		$status = get_post_status( $post_id );

		if ( 'future' === $status ) {
			return '<span class="description">' . esc_html__( 'Scheduled', 'crc-real-estate' ) . '</span>';
		}

		if ( ! Listing_Status::can_switch( $post_id ) ) {
			return '<span class="description">' . esc_html__( 'Not published yet', 'crc-real-estate' ) . '</span>';
		}

		$on = Listing_Status::is_on( $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return '<span class="description">' . ( $on ? esc_html__( 'On', 'crc-real-estate' ) : esc_html__( 'Off', 'crc-real-estate' ) ) . '</span>';
		}

		return sprintf(
			'<button type="button" class="crc-switch" role="switch" aria-checked="%1$s" data-id="%2$d" aria-label="%3$s" title="%4$s"><span class="crc-switch-track" aria-hidden="true"><span class="crc-switch-thumb"></span></span><span class="crc-switch-text">%5$s</span></button>',
			$on ? 'true' : 'false',
			(int) $post_id,
			/* translators: %s: listing title. */
			esc_attr( sprintf( __( 'Show “%s” on the website', 'crc-real-estate' ), get_the_title( $post_id ) ) ),
			esc_attr__( 'Turn off to unlist it: it disappears from the website and stays here with everything in it. Turn on to bring it back.', 'crc-real-estate' ),
			$on ? esc_html__( 'On', 'crc-real-estate' ) : esc_html__( 'Off', 'crc-real-estate' )
		);
	}

	/**
	 * A listing's Availability, with the button that marks it Sold or Rented, or available again.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function availability_html( $post_id ) {
		$value = (string) get_post_meta( $post_id, Listing_Status::AVAILABILITY_META, true );
		$gone  = Listing_Status::gone( $post_id );
		$words = Listing_Status::gone_words();
		$word  = $words[ Listing_Status::gone_key( $post_id ) ];
		$html  = sprintf(
			'<span class="crc-availability%1$s">%2$s</span>',
			$gone ? ' is-gone' : '',
			'' !== $value ? esc_html( $value ) : '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not chosen', 'crc-real-estate' ) . '</span>'
		);

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $html;
		}

		return $html . sprintf(
			'<button type="button" class="button button-small crc-gone" data-id="%1$d" data-gone="%2$s" title="%3$s">%4$s</button>',
			(int) $post_id,
			$gone ? '0' : '1',
			$gone
				? esc_attr__( 'Sets Availability back to what it was before, or to Available Now.', 'crc-real-estate' )
				/* translators: %s: "Sold" or "Rented". */
				: esc_attr( sprintf( __( 'Sets Availability to %s, chosen from the category. The listing stays on the website, marked as gone, so people can see what you have done. To take it off the website, turn off On the website.', 'crc-real-estate' ), $word ) ),
			$gone
				? esc_html__( 'Mark as available', 'crc-real-estate' )
				/* translators: %s: "Sold" or "Rented". */
				: esc_html( sprintf( __( 'Mark as %s', 'crc-real-estate' ), $word ) )
		);
	}

	/**
	 * Prints the two columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Listing ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'crc_live' === $column ) {
			echo '<div class="crc-live-cell">' . self::switch_html( $post_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in switch_html().
		} elseif ( 'crc_availability' === $column ) {
			echo '<div class="crc-availability-cell">' . self::availability_html( $post_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in availability_html().
		}
	}

	/**
	 * Loads the switch's script on All Listings.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		if ( 'edit.php' !== $hook || ! $screen || Post_Type::NAME !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'crc-re-admin-list', CRC_RE_URL . 'assets/js/admin-list.js', array(), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-list',
			'crcReList',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'live'      => self::AJAX_LIVE,
				'gone'      => self::AJAX_GONE,
				'on'        => __( 'On', 'crc-real-estate' ),
				'off'       => __( 'Off', 'crc-real-estate' ),
				'unlisted'  => __( 'Unlisted', 'crc-real-estate' ),
				'failed'    => __( 'That didn\'t work. Check your internet connection and try again.', 'crc-real-estate' ),
				'loggedOut' => __( 'You have been logged out. Log in again in another tab, then try again.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Checks an AJAX request: the page's nonce and the right to change the listing.
	 *
	 * @return int Listing ID.
	 */
	private static function checked_listing() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page is out of date. Please reload it.', 'crc-real-estate' ) ), 403 );
		}

		$post_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked above.

		if ( ! $post_id || Post_Type::NAME !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That listing isn\'t here any more. Please reload the page.', 'crc-real-estate' ) ), 404 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You can\'t change this listing.', 'crc-real-estate' ) ), 403 );
		}

		return $post_id;
	}

	/**
	 * Whether the person may put listings on the website.
	 *
	 * @return bool
	 */
	private static function can_publish() {
		$type = get_post_type_object( Post_Type::NAME );

		return $type && isset( $type->cap->publish_posts ) && current_user_can( $type->cap->publish_posts );
	}

	/**
	 * The switch, by AJAX: on or off the website.
	 */
	public function ajax_live() {
		$post_id = self::checked_listing();
		$on      = ! empty( $_POST['on'] ) && '1' === sanitize_key( wp_unslash( $_POST['on'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in checked_listing().

		if ( $on && ! self::can_publish() ) {
			wp_send_json_error( array( 'message' => __( 'You can\'t put listings on the website. Ask an administrator.', 'crc-real-estate' ) ), 403 );
		}

		$done = Listing_Status::turn( $post_id, $on );

		if ( is_wp_error( $done ) ) {
			wp_send_json_error( array( 'message' => $done->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'on'   => Listing_Status::is_on( $post_id ),
				'html' => self::switch_html( $post_id ),
			)
		);
	}

	/**
	 * Sold or Rented, by AJAX, or available again.
	 */
	public function ajax_gone() {
		$post_id = self::checked_listing();
		$gone    = ! empty( $_POST['gone'] ) && '1' === sanitize_key( wp_unslash( $_POST['gone'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in checked_listing().

		Listing_Status::mark( $post_id, $gone );

		wp_send_json_success( array( 'html' => self::availability_html( $post_id ) ) );
	}

	/**
	 * Adds the switch's and Sold or Rented's choices to Bulk actions.
	 *
	 * @param string[] $actions Bulk actions.
	 * @return string[]
	 */
	public function bulk_actions( $actions ) {
		$actions['crc_show']      = __( 'Show on the website', 'crc-real-estate' );
		$actions['crc_hide']      = __( 'Unlist (hide from the website)', 'crc-real-estate' );
		$actions['crc_gone']      = __( 'Mark as Sold or Rented', 'crc-real-estate' );
		$actions['crc_available'] = __( 'Mark as available', 'crc-real-estate' );

		return $actions;
	}

	/**
	 * Does a bulk action. WordPress has already checked the list's nonce.
	 *
	 * @param string $redirect Where to go afterwards.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Chosen listings.
	 * @return string
	 */
	public function bulk( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'crc_show', 'crc_hide', 'crc_gone', 'crc_available' ), true ) ) {
			return $redirect;
		}

		$done    = 0;
		$skipped = 0;

		foreach ( array_map( 'absint', (array) $ids ) as $post_id ) {
			if ( ! $post_id || Post_Type::NAME !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				++$skipped;
				continue;
			}

			if ( 'crc_available' === $action && ! Listing_Status::gone( $post_id ) ) {
				++$skipped;
				continue;
			}

			if ( 'crc_gone' === $action || 'crc_available' === $action ) {
				Listing_Status::mark( $post_id, 'crc_gone' === $action );
				++$done;
				continue;
			}

			$on = 'crc_show' === $action;

			if ( $on && ! self::can_publish() ) {
				++$skipped;
				continue;
			}

			if ( is_wp_error( Listing_Status::turn( $post_id, $on ) ) ) {
				++$skipped;
			} else {
				++$done;
			}
		}

		return add_query_arg(
			array(
				self::DID_ARG     => $action,
				self::DONE_ARG    => $done,
				self::SKIPPED_ARG => $skipped,
			),
			remove_query_arg( array( self::DID_ARG, self::DONE_ARG, self::SKIPPED_ARG ), $redirect )
		);
	}

	/**
	 * Leaves the bulk action's report out of the address once it is shown.
	 *
	 * @param string[] $args Query arguments.
	 * @return string[]
	 */
	public function removable_args( $args ) {
		$args[] = self::DID_ARG;
		$args[] = self::DONE_ARG;
		$args[] = self::SKIPPED_ARG;

		return $args;
	}

	/**
	 * Says what a bulk action did.
	 */
	public function notice() {
		$screen = get_current_screen();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads the report.
		if ( ! $screen || 'edit-' . Post_Type::NAME !== $screen->id || ! isset( $_GET[ self::DID_ARG ] ) ) {
			return;
		}

		$did     = sanitize_key( wp_unslash( $_GET[ self::DID_ARG ] ) );
		$done    = isset( $_GET[ self::DONE_ARG ] ) ? absint( $_GET[ self::DONE_ARG ] ) : 0;
		$skipped = isset( $_GET[ self::SKIPPED_ARG ] ) ? absint( $_GET[ self::SKIPPED_ARG ] ) : 0;
		// phpcs:enable

		$texts = array(
			/* translators: %s: number of listings. */
			'crc_show'      => _n( '%s listing is on the website again.', '%s listings are on the website again.', $done, 'crc-real-estate' ),
			/* translators: %s: number of listings. */
			'crc_hide'      => _n( '%s listing was unlisted. It is hidden from the website and kept here, with everything in it.', '%s listings were unlisted. They are hidden from the website and kept here, with everything in them.', $done, 'crc-real-estate' ),
			/* translators: %s: number of listings. */
			'crc_gone'      => _n( '%s listing was marked Sold or Rented.', '%s listings were marked Sold or Rented.', $done, 'crc-real-estate' ),
			/* translators: %s: number of listings. */
			'crc_available' => _n( '%s listing was marked as available.', '%s listings were marked as available.', $done, 'crc-real-estate' ),
		);

		if ( ! isset( $texts[ $did ] ) ) {
			return;
		}

		$message = sprintf( $texts[ $did ], number_format_i18n( $done ) );

		if ( $skipped ) {
			$message .= ' ' . sprintf(
				/* translators: %s: number of listings. */
				_n( '%s was left as it was: it isn\'t published yet, needs a main photo and a category, has a date in the future, wasn\'t marked Sold or Rented, or you can\'t change it.', '%s were left as they were: they aren\'t published yet, need a main photo and a category, have a date in the future, weren\'t marked Sold or Rented, or you can\'t change them.', $skipped, 'crc-real-estate' ),
				number_format_i18n( $skipped )
			);
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', $skipped && ! $done ? 'warning' : 'success', esc_html( $message ) );
	}

	/**
	 * On the listing's own screen: says when it is off the website, and how to bring it back.
	 *
	 * @param \WP_Post $post Post being edited.
	 */
	public function publish_box( $post ) {
		if ( ! $post || Post_Type::NAME !== $post->post_type || Listing_Status::OFF !== $post->post_status ) {
			return;
		}

		printf(
			'<div class="misc-pub-section crc-unlisted-note"><span class="dashicons dashicons-hidden" aria-hidden="true"></span> %1$s <strong>%2$s</strong><p class="description">%3$s</p></div>',
			esc_html__( 'On the website:', 'crc-real-estate' ),
			esc_html__( 'Off (unlisted)', 'crc-real-estate' ),
			esc_html__( 'This listing is hidden from visitors: search, listing pages, carousels and its own page. While you are logged in you can still open its page, with "Unlisted:" before its title. Everything in it is kept. To bring it back, turn on its switch in All Listings, or set Visibility above to Public and press Update.', 'crc-real-estate' )
		);
	}
}
