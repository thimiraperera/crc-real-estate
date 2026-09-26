<?php
/**
 * Inquiries screens.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Inquiries;
use CRC\RealEstate\Phone;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Inquiries: every inquiry with who sent it and how to reach
 * them, and each inquiry in full with buttons to reply.
 */
final class Inquiries_Screen {

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'manage_' . Inquiries::NAME . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Inquiries::NAME . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_date_column_status', array( $this, 'date_status' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . Inquiries::NAME, array( $this, 'bulk_actions' ) );
		add_filter( 'views_edit-' . Inquiries::NAME, array( $this, 'views' ) );
		add_action( 'add_meta_boxes_' . Inquiries::NAME, array( $this, 'boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Loads the admin styles on the inquiry screens.
	 */
	public function assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && Inquiries::NAME === $screen->post_type ) {
			wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		}
	}

	/**
	 * The list's columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		return array(
			'cb'          => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox">',
			'title'       => __( 'Name', 'crc-real-estate' ),
			'crc_phone'   => __( 'Phone', 'crc-real-estate' ),
			'crc_email'   => __( 'Email', 'crc-real-estate' ),
			'crc_listing' => __( 'Listing', 'crc-real-estate' ),
			'date'        => __( 'Received', 'crc-real-estate' ),
		);
	}

	/**
	 * Prints a column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Inquiry ID.
	 */
	public function column( $column, $post_id ) {
		$inquiry = Inquiries::get( $post_id );

		switch ( $column ) {
			case 'crc_phone':
				if ( '' !== $inquiry['phone'] ) {
					printf( '<a href="%1$s">%2$s</a>', esc_url( 'tel:' . $inquiry['phone'] ), esc_html( Phone::display( $inquiry['phone'], $inquiry['country'] ) ) );
				}
				break;

			case 'crc_email':
				if ( '' !== $inquiry['email'] ) {
					printf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $inquiry['email'] ), esc_html( $inquiry['email'] ) );
				}
				break;

			case 'crc_listing':
				$listing = Inquiries::listing( $inquiry );

				if ( $listing ) {
					printf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_edit_post_link( $listing->ID ) ), esc_html( get_the_title( $listing ) ) );
				} else {
					echo '<span class="description">' . esc_html__( 'General inquiry', 'crc-real-estate' ) . '</span>';
				}
				break;
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
		return $post && Inquiries::NAME === $post->post_type ? '' : $status;
	}

	/**
	 * Links under each inquiry: Open and Trash. Inquiries aren't edited.
	 *
	 * @param string[] $actions Links.
	 * @param \WP_Post $post    Post.
	 * @return string[]
	 */
	public function row_actions( $actions, $post ) {
		if ( Inquiries::NAME !== $post->post_type ) {
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
	 * The inquiry screen: the inquiry, and buttons to reply.
	 */
	public function boxes() {
		remove_meta_box( 'submitdiv', Inquiries::NAME, 'side' );
		remove_meta_box( 'slugdiv', Inquiries::NAME, 'normal' );
		add_meta_box( 'crc-inquiry-details', __( 'Inquiry', 'crc-real-estate' ), array( $this, 'details' ), Inquiries::NAME, 'normal', 'high' );
		add_meta_box( 'crc-inquiry-reply', __( 'Reply', 'crc-real-estate' ), array( $this, 'reply' ), Inquiries::NAME, 'side', 'high' );
	}

	/**
	 * Prints the inquiry.
	 *
	 * @param \WP_Post $post Inquiry.
	 */
	public function details( $post ) {
		$inquiry = Inquiries::get( $post->ID );
		$listing = Inquiries::listing( $inquiry );
		$rows    = array(
			__( 'Name', 'crc-real-estate' ) => esc_html( Inquiries::name( $inquiry ) ),
		);

		if ( '' !== $inquiry['phone'] ) {
			$rows[ __( 'Phone', 'crc-real-estate' ) ] = sprintf(
				'<a href="%1$s">%2$s</a> &middot; <a href="%3$s" target="_blank" rel="noopener">%4$s</a>',
				esc_url( 'tel:' . $inquiry['phone'] ),
				esc_html( Phone::display( $inquiry['phone'], $inquiry['country'] ) ),
				esc_url( Inquiries::whatsapp_url( $inquiry['phone'] ) ),
				esc_html__( 'WhatsApp', 'crc-real-estate' )
			);
		}

		if ( '' !== $inquiry['email'] ) {
			$rows[ __( 'Email', 'crc-real-estate' ) ] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $inquiry['email'] ), esc_html( $inquiry['email'] ) );
		}

		$rows[ __( 'Listing', 'crc-real-estate' ) ] = $listing
			? sprintf(
				'<a href="%1$s">%2$s</a> &middot; <a href="%3$s" target="_blank" rel="noopener">%4$s</a>',
				esc_url( (string) get_edit_post_link( $listing->ID ) ),
				esc_html( get_the_title( $listing ) ),
				esc_url( (string) get_permalink( $listing ) ),
				esc_html__( 'View on the site', 'crc-real-estate' )
			)
			: esc_html__( 'General inquiry, not about a particular listing.', 'crc-real-estate' );

		$rows[ __( 'Message', 'crc-real-estate' ) ] = '' !== $inquiry['message'] ? nl2br( esc_html( $inquiry['message'] ) ) : '<span class="description">' . esc_html__( 'No message.', 'crc-real-estate' ) . '</span>';

		if ( '' !== $inquiry['page'] ) {
			$rows[ __( 'Sent from', 'crc-real-estate' ) ] = sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $inquiry['page'] ), esc_html( $inquiry['page'] ) );
		}

		/* translators: 1: date, 2: time. */
		$rows[ __( 'Received', 'crc-real-estate' ) ] = esc_html( sprintf( __( '%1$s at %2$s', 'crc-real-estate' ), get_the_date( '', $post ), get_the_time( '', $post ) ) );

		if ( '0' === $inquiry['mailed'] ) {
			$rows[ __( 'Email to you', 'crc-real-estate' ) ] = '<span class="crc-warning">' . esc_html__( 'The email about this inquiry couldn\'t be sent, so it is only here. If this keeps happening, ask your host to check the site\'s email, or add an SMTP plugin that sends email through your email account.', 'crc-real-estate' ) . '</span>';
		} elseif ( '1' === $inquiry['mailed'] ) {
			$rows[ __( 'Email to you', 'crc-real-estate' ) ] = esc_html__( 'Sent to the address in Listings → Settings.', 'crc-real-estate' );
		}

		if ( '' !== $inquiry['note'] ) {
			$rows[ __( 'Note', 'crc-real-estate' ) ] = '<span class="crc-warning">' . esc_html( $inquiry['note'] ) . '</span>';
		}

		echo '<table class="form-table crc-inquiry-details" role="presentation">';

		foreach ( $rows as $label => $value ) {
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}

		echo '</table>';
	}

	/**
	 * Prints the Reply buttons: email, call, WhatsApp, and Move to Trash.
	 *
	 * @param \WP_Post $post Inquiry.
	 */
	public function reply( $post ) {
		$inquiry = Inquiries::get( $post->ID );
		$listing = Inquiries::listing( $inquiry );
		$subject = $listing
			/* translators: %s: listing title. */
			? sprintf( __( 'Your inquiry about %s', 'crc-real-estate' ), html_entity_decode( get_the_title( $listing ), ENT_QUOTES, 'UTF-8' ) )
			: __( 'Your inquiry', 'crc-real-estate' );

		echo '<div class="crc-inquiry-reply">';

		if ( '' !== $inquiry['email'] ) {
			printf( '<a class="button button-primary" href="%1$s">%2$s</a>', esc_url( 'mailto:' . $inquiry['email'] . '?subject=' . rawurlencode( $subject ) ), esc_html__( 'Reply by email', 'crc-real-estate' ) );
		}

		if ( '' !== $inquiry['phone'] ) {
			printf( '<a class="button" href="%1$s">%2$s</a>', esc_url( 'tel:' . $inquiry['phone'] ), esc_html__( 'Call', 'crc-real-estate' ) );
			printf( '<a class="button" href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( Inquiries::whatsapp_url( $inquiry['phone'] ) ), esc_html__( 'Message on WhatsApp', 'crc-real-estate' ) );
		}

		echo '</div>';

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			printf( '<p class="crc-inquiry-trash"><a class="submitdelete" href="%1$s">%2$s</a></p>', esc_url( (string) get_delete_post_link( $post->ID ) ), esc_html__( 'Move to Trash', 'crc-real-estate' ) );
		}
	}
}
