<?php
/**
 * Screens for what the plugin's forms keep.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Ad_Requests;
use CRC\RealEstate\Contact_Messages;
use CRC\RealEstate\Inquiries;
use CRC\RealEstate\Phone;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * A menu of its own for one of the plugin's forms: everything sent with it,
 * with who sent it and how to reach them, and each one in full with buttons
 * to reply. The menu shows how many are new, and new ones are marked New
 * until they are opened.
 *
 * Each screen sets STORE (the class that keeps what was sent) and BOX (the
 * start of its boxes' IDs).
 */
abstract class Submissions_Screen {

	const STORE = '';
	const BOX   = '';

	/**
	 * Whether the plugin's menus were put together.
	 *
	 * @var bool
	 */
	private static $ordered = false;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		$name = static::name();

		add_action( 'admin_menu', array( $this, 'badge' ), 99 );
		add_action( 'load-post.php', array( $this, 'opened' ) );
		add_filter( 'display_post_states', array( $this, 'states' ), 10, 2 );
		add_filter( 'manage_' . $name . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . $name . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_date_column_status', array( $this, 'date_status' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . $name, array( $this, 'bulk_actions' ) );
		add_filter( 'views_edit-' . $name, array( $this, 'views' ) );
		add_action( 'add_meta_boxes_' . $name, array( $this, 'boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		if ( ! self::$ordered ) {
			self::$ordered = true;

			add_filter( 'custom_menu_order', '__return_true' );
			add_filter( 'menu_order', array( __CLASS__, 'menu_order' ) );
		}
	}

	/**
	 * The post type of what this screen shows.
	 *
	 * @return string
	 */
	protected static function name() {
		$store = static::STORE;

		return $store::NAME;
	}

	/**
	 * The list's columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	abstract public function columns( $columns );

	/**
	 * Prints a column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id ID.
	 */
	abstract public function column( $column, $post_id );

	/**
	 * Prints everything that was sent.
	 *
	 * @param \WP_Post $post Post.
	 */
	abstract public function details( $post );

	/**
	 * Prints the Reply buttons.
	 *
	 * @param \WP_Post $post Post.
	 */
	abstract public function reply( $post );

	/**
	 * The title of the box with everything that was sent.
	 *
	 * @return string
	 */
	abstract protected static function box_title();

	/**
	 * The number of new ones, for screen readers, e.g. "3 new inquiries".
	 *
	 * @param int $count Number of new ones.
	 * @return string
	 */
	abstract protected static function badge_text( $count );

	/**
	 * Keeps the plugin's menus together: Contact Messages, Free Ads and
	 * Testimonials come straight after Inquiries, above Media. People who
	 * can't open Inquiries (such as Authors) still see Testimonials straight
	 * after Listings, above Media.
	 *
	 * @param string[] $order Menu addresses in order.
	 * @return string[]
	 */
	public static function menu_order( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}

		$after = 'edit.php?post_type=' . Inquiries::NAME;

		if ( ! in_array( $after, $order, true ) ) {
			$after = 'edit.php?post_type=' . Post_Type::NAME;
		}

		$move = array_values( array_intersect( array( 'edit.php?post_type=' . Contact_Messages::NAME, 'edit.php?post_type=' . Ad_Requests::NAME, 'edit.php?post_type=' . Testimonials::POST_TYPE ), $order ) );

		if ( ! $move || ! in_array( $after, $order, true ) ) {
			return $order;
		}

		$order = array_values( array_diff( $order, $move ) );

		array_splice( $order, array_search( $after, $order, true ) + 1, 0, $move );

		return $order;
	}

	/**
	 * The number of new ones on the menu, like new comments.
	 */
	public function badge() {
		global $menu;

		$store  = static::STORE;
		$object = get_post_type_object( static::name() );

		// People who can't open the menu don't see it, so there is nothing to count.
		if ( ! is_array( $menu ) || ( $object && isset( $object->cap->edit_posts ) && ! current_user_can( $object->cap->edit_posts ) ) ) {
			return;
		}

		$count = $store::new_count();

		if ( ! $count ) {
			return;
		}

		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . static::name() === $item[2] ) {
				$menu[ $key ][0] .= sprintf( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Adding the count, as WordPress does for comments.
					' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
					$count,
					esc_html( number_format_i18n( $count ) ),
					esc_html( static::badge_text( $count ) )
				);
				break;
			}
		}
	}

	/**
	 * One that is opened is no longer new.
	 */
	public function opened() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which one is open.
		$id = isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( $id && static::name() === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
			$store = static::STORE;

			delete_post_meta( $id, $store::NEW_META );
		}
	}

	/**
	 * New next to a new one's name in the list.
	 *
	 * @param string[] $states States shown after the name.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public function states( $states, $post ) {
		$store = static::STORE;

		if ( $post instanceof \WP_Post && static::name() === $post->post_type && get_post_meta( $post->ID, $store::NEW_META, true ) ) {
			$states['crc_new'] = __( 'New', 'crc-real-estate' );
		}

		return $states;
	}

	/**
	 * Loads the admin styles on these screens.
	 */
	public function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && static::name() === $screen->post_type ) {
			wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		}
	}

	/**
	 * Shows only the date in the Received column, without "Published".
	 *
	 * @param string   $status Status text.
	 * @param \WP_Post $post   Post.
	 * @return string
	 */
	public function date_status( $status, $post ) {
		return $post && static::name() === $post->post_type ? '' : $status;
	}

	/**
	 * Links under each one: Open and Trash. They aren't edited.
	 *
	 * @param string[] $actions Links.
	 * @param \WP_Post $post    Post.
	 * @return string[]
	 */
	public function row_actions( $actions, $post ) {
		if ( static::name() !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['inline hide-if-no-js'] );

		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_edit_post_link( $post->ID ) ), esc_html__( 'Open', 'crc-real-estate' ) );
		}

		return $actions;
	}

	/**
	 * Bulk actions: only Move to Trash.
	 *
	 * @param string[] $actions Bulk actions.
	 * @return string[]
	 */
	public function bulk_actions( $actions ) {
		unset( $actions['edit'] );

		return $actions;
	}

	/**
	 * The list's filters: All and Trash. "Published" would only repeat All.
	 *
	 * @param string[] $views Filters.
	 * @return string[]
	 */
	public function views( $views ) {
		unset( $views['publish'] );

		return $views;
	}

	/**
	 * The screen of one: everything that was sent, and buttons to reply.
	 */
	public function boxes() {
		$name = static::name();

		remove_meta_box( 'submitdiv', $name, 'side' );
		remove_meta_box( 'slugdiv', $name, 'normal' );
		add_meta_box( static::BOX . '-details', static::box_title(), array( $this, 'details' ), $name, 'normal', 'high' );
		add_meta_box( static::BOX . '-reply', __( 'Reply', 'crc-real-estate' ), array( $this, 'reply' ), $name, 'side', 'high' );
	}

	/**
	 * A phone number to tap, with a WhatsApp link.
	 *
	 * @param array $item Details with 'phone' and 'country'.
	 * @return string
	 */
	protected static function phone_links( array $item ) {
		$store = static::STORE;

		return sprintf(
			'<a href="%1$s">%2$s</a> &middot; <a href="%3$s" target="_blank" rel="noopener">%4$s</a>',
			esc_url( 'tel:' . $item['phone'] ),
			esc_html( Phone::display( $item['phone'], $item['country'] ) ),
			esc_url( $store::whatsapp_url( $item['phone'] ) ),
			esc_html__( 'WhatsApp', 'crc-real-estate' )
		);
	}

	/**
	 * A phone number to tap, for the list.
	 *
	 * @param array $item Details with 'phone' and 'country'.
	 * @return string
	 */
	protected static function phone_link( array $item ) {
		return '' !== $item['phone'] ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'tel:' . $item['phone'] ), esc_html( Phone::display( $item['phone'], $item['country'] ) ) ) : '';
	}

	/**
	 * An email address to tap.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	protected static function email_link( $email ) {
		return '' !== $email ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) ) : '';
	}

	/**
	 * Text with several lines, or a grey note when there is none.
	 *
	 * @param string $text Text.
	 * @param string $none What to say when it is empty.
	 * @return string
	 */
	protected static function text_cell( $text, $none ) {
		return '' !== $text ? nl2br( esc_html( $text ) ) : '<span class="description">' . esc_html( $none ) . '</span>';
	}

	/**
	 * The first rows: who sent it and how to reach them.
	 *
	 * @param array $item Details.
	 * @return string[] Label => HTML.
	 */
	protected static function person_rows( array $item ) {
		$store = static::STORE;
		$rows  = array(
			__( 'Name', 'crc-real-estate' ) => esc_html( $store::name( $item ) ),
		);

		if ( '' !== $item['phone'] ) {
			$rows[ __( 'Phone', 'crc-real-estate' ) ] = static::phone_links( $item );
		}

		if ( '' !== $item['email'] ) {
			$rows[ __( 'Email', 'crc-real-estate' ) ] = static::email_link( $item['email'] );
		}

		return $rows;
	}

	/**
	 * The last rows: where it was sent from, when, whether the email to the
	 * site went, and any note.
	 *
	 * @param array    $item      Details.
	 * @param \WP_Post $post      Post.
	 * @param string   $unmailed  What to say when the email couldn't be sent.
	 * @return string[] Label => HTML.
	 */
	protected static function end_rows( array $item, $post, $unmailed ) {
		$rows = array();

		if ( '' !== $item['page'] ) {
			$rows[ __( 'Sent from', 'crc-real-estate' ) ] = sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $item['page'] ), esc_html( $item['page'] ) );
		}

		/* translators: 1: date, 2: time. */
		$rows[ __( 'Received', 'crc-real-estate' ) ] = esc_html( sprintf( __( '%1$s at %2$s', 'crc-real-estate' ), get_the_date( '', $post ), get_the_time( '', $post ) ) );

		if ( '0' === $item['mailed'] ) {
			$rows[ __( 'Email to you', 'crc-real-estate' ) ] = '<span class="crc-warning">' . esc_html( $unmailed ) . '</span>';
		} elseif ( '1' === $item['mailed'] ) {
			$rows[ __( 'Email to you', 'crc-real-estate' ) ] = esc_html__( 'Sent to the address in Listings → Settings.', 'crc-real-estate' );
		}

		if ( '' !== $item['note'] ) {
			$rows[ __( 'Note', 'crc-real-estate' ) ] = '<span class="crc-warning">' . esc_html( $item['note'] ) . '</span>';
		}

		return $rows;
	}

	/**
	 * Prints the details table.
	 *
	 * @param string[] $rows Label => HTML, already escaped.
	 */
	protected static function table( array $rows ) {
		echo '<table class="form-table crc-inquiry-details" role="presentation">';

		foreach ( $rows as $label => $value ) {
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.
		}

		echo '</table>';
	}

	/**
	 * Prints the Reply buttons: email, call, WhatsApp, and Move to Trash.
	 *
	 * @param \WP_Post $post    Post.
	 * @param array    $item    Details.
	 * @param string   $subject Subject for the reply email.
	 * @param string   $note    A reminder under the buttons, or an empty string.
	 */
	protected static function reply_buttons( $post, array $item, $subject, $note = '' ) {
		$store = static::STORE;

		echo '<div class="crc-inquiry-reply">';

		if ( '' !== $item['email'] ) {
			printf( '<a class="button button-primary" href="%1$s">%2$s</a>', esc_url( 'mailto:' . $item['email'] . '?subject=' . rawurlencode( $subject ) ), esc_html__( 'Reply by email', 'crc-real-estate' ) );
		}

		if ( '' !== $item['phone'] ) {
			printf( '<a class="button" href="%1$s">%2$s</a>', esc_url( 'tel:' . $item['phone'] ), esc_html__( 'Call', 'crc-real-estate' ) );
			printf( '<a class="button" href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $store::whatsapp_url( $item['phone'] ) ), esc_html__( 'Message on WhatsApp', 'crc-real-estate' ) );
		}

		echo '</div>';

		if ( '' !== $note ) {
			echo '<p class="description">' . esc_html( $note ) . '</p>';
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			printf( '<p class="crc-inquiry-trash"><a class="submitdelete" href="%1$s">%2$s</a></p>', esc_url( (string) get_delete_post_link( $post->ID ) ), esc_html__( 'Move to Trash', 'crc-real-estate' ) );
		}
	}
}
