<?php
/**
 * Inquiries sent with the inquiry form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Emails each inquiry and keeps it in Inquiries, its own menu in wp-admin,
 * so none is lost when an email doesn't arrive. A new inquiry counts as new
 * until it is opened.
 */
final class Inquiries {

	const NAME     = 'crc_inquiry';
	const NEW_META = '_crc_inquiry_new';

	/**
	 * Details kept with each inquiry: key => field name.
	 */
	const META = array(
		'first_name' => '_crc_inquiry_first_name',
		'last_name'  => '_crc_inquiry_last_name',
		'phone'      => '_crc_inquiry_phone',
		'country'    => '_crc_inquiry_country',
		'email'      => '_crc_inquiry_email',
		'message'    => '_crc_inquiry_message',
		'listing'    => '_crc_inquiry_listing',
		'page'       => '_crc_inquiry_page',
		'note'       => '_crc_inquiry_note',
		'mailed'     => '_crc_inquiry_mailed',
	);

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * The Inquiries menu's icon: a speech bubble with a house in it. WordPress
	 * colours it to match the admin menu.
	 *
	 * @return string
	 */
	public static function icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M4 2h12a3 3 0 0 1 3 3v7a3 3 0 0 1-3 3H9.5L5 18.5V15H4a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3zM10 4.6 5.8 8.2H7V12h2.2V9.8h1.6V12H13V8.2h1.2z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A menu icon, as WordPress asks for.
	}

	/**
	 * How many inquiries haven't been opened yet.
	 *
	 * @return int
	 */
	public static function new_count() {
		$query = new \WP_Query(
			array(
				'post_type'              => self::NAME,
				'post_status'            => 'publish',
				'meta_key'               => self::NEW_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Once a page in wp-admin, for the menu.
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Registers inquiries: only in wp-admin, for editors and administrators,
	 * and never added by hand. They have a menu of their own.
	 */
	public function register() {
		register_post_type(
			self::NAME,
			array(
				'labels'              => array(
					'name'               => __( 'Inquiries', 'crc-real-estate' ),
					'singular_name'      => __( 'Inquiry', 'crc-real-estate' ),
					'menu_name'          => __( 'Inquiries', 'crc-real-estate' ),
					'all_items'          => __( 'All Inquiries', 'crc-real-estate' ),
					'edit_item'          => __( 'Inquiry', 'crc-real-estate' ),
					'view_item'          => __( 'View inquiry', 'crc-real-estate' ),
					'search_items'       => __( 'Search inquiries', 'crc-real-estate' ),
					'not_found'          => __( 'No inquiries yet. Inquiries sent with the inquiry form show here.', 'crc-real-estate' ),
					'not_found_in_trash' => __( 'No inquiries in the Trash.', 'crc-real-estate' ),
					'item_updated'       => __( 'Inquiry updated.', 'crc-real-estate' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 6,
				'menu_icon'           => self::icon(),
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'supports'            => false,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Keeps an inquiry.
	 *
	 * @param array $inquiry Checked inquiry: the keys of META except "mailed".
	 * @return int Inquiry ID, or 0 when it couldn't be kept.
	 */
	public static function add( array $inquiry ) {
		$meta = array();

		foreach ( self::META as $key => $meta_key ) {
			$value = isset( $inquiry[ $key ] ) ? (string) $inquiry[ $key ] : '';

			// Empty details, and "no listing", aren't kept.
			if ( 'mailed' !== $key && '' !== $value && ! ( 'listing' === $key && '0' === $value ) ) {
				$meta[ $meta_key ] = $value;
			}
		}

		// New until it is opened.
		$meta[ self::NEW_META ] = '1';

		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => self::NAME,
					'post_status'    => 'publish',
					'post_title'     => self::name( $inquiry ),
					'post_content'   => isset( $inquiry['message'] ) ? (string) $inquiry['message'] : '',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
					'meta_input'     => $meta,
				)
			),
			true
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * A kept inquiry's details.
	 *
	 * @param int $post_id Inquiry ID.
	 * @return array The keys of META.
	 */
	public static function get( $post_id ) {
		$inquiry = array();

		foreach ( self::META as $key => $meta_key ) {
			$inquiry[ $key ] = (string) get_post_meta( $post_id, $meta_key, true );
		}

		$inquiry['listing'] = (int) $inquiry['listing'];

		return $inquiry;
	}

	/**
	 * The name of the person who asked.
	 *
	 * @param array $inquiry Inquiry.
	 * @return string
	 */
	public static function name( array $inquiry ) {
		$first = isset( $inquiry['first_name'] ) ? (string) $inquiry['first_name'] : '';
		$last  = isset( $inquiry['last_name'] ) ? (string) $inquiry['last_name'] : '';

		return trim( $first . ' ' . $last );
	}

	/**
	 * The listing an inquiry is about, if it still exists.
	 *
	 * @param array $inquiry Inquiry.
	 * @return \WP_Post|null
	 */
	public static function listing( array $inquiry ) {
		$post = empty( $inquiry['listing'] ) ? null : get_post( (int) $inquiry['listing'] );

		return $post && Post_Type::NAME === $post->post_type ? $post : null;
	}

	/**
	 * A WhatsApp chat link for a phone number.
	 *
	 * @param string $number Number in full international form, e.g. +94771234567.
	 * @return string
	 */
	public static function whatsapp_url( $number ) {
		return 'https://wa.me/' . preg_replace( '/\D/', '', (string) $number );
	}

	/**
	 * Emails an inquiry to the addresses in Settings. Replying answers the
	 * person who asked.
	 *
	 * @param array $inquiry Checked inquiry: the keys of META except "mailed".
	 * @param int   $post_id Kept inquiry's ID, for a link to it; 0 when it wasn't kept.
	 * @return bool Whether the email was handed over for sending.
	 */
	public static function mail( array $inquiry, $post_id = 0 ) {
		$to = Settings::inquiry_recipients();

		if ( ! $to ) {
			return false;
		}

		$listing = self::listing( $inquiry );
		$name    = self::name( $inquiry );
		$first   = (string) $inquiry['first_name'];
		$title   = $listing ? self::plain( get_the_title( $listing ) ) : '';
		$subject = $listing
			/* translators: %s: listing title. */
			? sprintf( __( 'New inquiry: %s', 'crc-real-estate' ), $title )
			/* translators: %s: site name. */
			: sprintf( __( 'New inquiry from %s', 'crc-real-estate' ), self::plain( get_option( 'blogname' ) ) );

		$intro = $listing
			/* translators: 1: first name, 2: listing title. */
			? __( '%1$s sent an inquiry about %2$s.', 'crc-real-estate' )
			/* translators: 1: first name. */
			: __( '%1$s sent an inquiry from your website.', 'crc-real-estate' );

		$html  = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#ffffff;">';
		$html .= '<div style="max-width:600px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#121212;">';
		$html .= '<p style="margin:0 0 16px;">' . sprintf( esc_html( $intro ), esc_html( $first ), $listing ? '<a href="' . esc_url( get_permalink( $listing ) ) . '">' . esc_html( $title ) . '</a>' : '' ) . '</p>';
		$html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
		$text  = sprintf( $intro, $first, $title ) . "\n\n";

		foreach ( self::rows( $inquiry, $listing ) as $row ) {
			$html .= '<tr><th scope="row" style="width:120px;padding:10px 16px 10px 0;border-top:1px solid #e5e5e5;text-align:left;vertical-align:top;font-weight:600;">' . esc_html( $row[0] ) . '</th>';
			$html .= '<td style="padding:10px 0;border-top:1px solid #e5e5e5;vertical-align:top;">' . $row[1] . '</td></tr>';
			$text .= $row[0] . ': ' . $row[2] . "\n";
		}

		/* translators: %s: first name. */
		$reply = sprintf( __( 'Reply to this email to answer %s directly.', 'crc-real-estate' ), $first );
		$kept  = __( 'This inquiry is also kept in wp-admin under Inquiries.', 'crc-real-estate' );
		$link  = $post_id ? admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ) : admin_url( 'edit.php?post_type=' . self::NAME );

		$html .= '</table>';
		$html .= '<p style="margin:24px 0 0;color:#6d6d6d;font-size:14px;">' . esc_html( $reply ) . ' <a href="' . esc_url( $link ) . '" style="color:#6d6d6d;">' . esc_html( $kept ) . '</a></p>';
		$html .= '</div></body></html>';
		$text .= "\n" . $reply . "\n" . $kept . "\n" . $link . "\n";

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( '' !== (string) $inquiry['email'] ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '"', '<', '>', ',', ';' ), '', $name ) . ' <' . $inquiry['email'] . '>';
		}

		// A plain text copy too, for email apps that don't show HTML.
		$plain = function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property.
		};

		add_action( 'phpmailer_init', $plain );
		$sent = wp_mail( $to, $subject, $html, $headers );
		remove_action( 'phpmailer_init', $plain );

		return (bool) $sent;
	}

	/**
	 * The details in an inquiry email.
	 *
	 * @param array         $inquiry Inquiry.
	 * @param \WP_Post|null $listing The listing it's about.
	 * @return array[] Rows of array( label, HTML, plain text ).
	 */
	private static function rows( array $inquiry, $listing ) {
		$link  = function ( $url, $text ) {
			return '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		};
		$phone = Phone::display( $inquiry['phone'], $inquiry['country'] );
		$rows  = array(
			array( __( 'Name', 'crc-real-estate' ), esc_html( self::name( $inquiry ) ), self::name( $inquiry ) ),
			array( __( 'Phone', 'crc-real-estate' ), $link( 'tel:' . $inquiry['phone'], $phone ) . ' &middot; ' . $link( self::whatsapp_url( $inquiry['phone'] ), __( 'WhatsApp', 'crc-real-estate' ) ), $phone ),
			array( __( 'Email', 'crc-real-estate' ), $link( 'mailto:' . $inquiry['email'], $inquiry['email'] ), $inquiry['email'] ),
		);

		if ( $listing ) {
			$title  = self::plain( get_the_title( $listing ) );
			$rows[] = array( __( 'Listing', 'crc-real-estate' ), $link( get_permalink( $listing ), $title ), $title . ' - ' . get_permalink( $listing ) );
		}

		$message = '' !== (string) $inquiry['message'] ? (string) $inquiry['message'] : __( 'No message.', 'crc-real-estate' );
		$rows[]  = array( __( 'Message', 'crc-real-estate' ), nl2br( esc_html( $message ) ), $message );

		if ( '' !== (string) $inquiry['page'] ) {
			$rows[] = array( __( 'Sent from', 'crc-real-estate' ), $link( $inquiry['page'], $inquiry['page'] ), $inquiry['page'] );
		}

		if ( ! empty( $inquiry['note'] ) ) {
			$rows[] = array( __( 'Note', 'crc-real-estate' ), esc_html( $inquiry['note'] ), $inquiry['note'] );
		}

		return $rows;
	}

	/**
	 * Text without HTML tags or entities, for email subjects and plain text.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	private static function plain( $text ) {
		return trim( wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
	}
}
