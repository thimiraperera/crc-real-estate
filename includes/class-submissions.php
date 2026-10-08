<?php
/**
 * What the plugin's forms keep in wp-admin.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps what people send with one of the plugin's forms in a menu of its own
 * in wp-admin and emails it to the site, so nothing is lost when an email
 * doesn't arrive. Each one counts as new until it is opened.
 *
 * Each kind sets NAME (its post type), NEW_META (the field that marks it
 * new) and META (what is kept: key => field name, always with "mailed").
 */
abstract class Submissions {

	const NAME     = '';
	const NEW_META = '';
	const META     = array();

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * The menu's icon, as WordPress asks for it.
	 *
	 * @return string
	 */
	abstract public static function icon();

	/**
	 * The names WordPress shows for this kind.
	 *
	 * @return string[]
	 */
	abstract protected static function labels();

	/**
	 * Emails what was sent to the addresses in Settings. Replying answers
	 * the person who sent it.
	 *
	 * @param array $item    Checked details: the keys of META except "mailed".
	 * @param int   $post_id Kept item's ID, for a link to it; 0 when it wasn't kept.
	 * @return bool Whether the email was handed over for sending.
	 */
	abstract public static function mail( array $item, $post_id = 0 );

	/**
	 * A menu icon from an SVG drawing. WordPress colours it to match the menu.
	 *
	 * @param string $svg SVG.
	 * @return string
	 */
	protected static function svg_icon( $svg ) {
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A menu icon, as WordPress asks for.
	}

	/**
	 * How many haven't been opened yet.
	 *
	 * @return int
	 */
	public static function new_count() {
		$query = new \WP_Query(
			array(
				'post_type'              => static::NAME,
				'post_status'            => 'publish',
				'meta_key'               => static::NEW_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Once a page in wp-admin, for the menu.
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Registers the kind: only in wp-admin, for editors and administrators,
	 * and never added by hand. It has a menu of its own.
	 */
	public function register() {
		register_post_type(
			static::NAME,
			array(
				'labels'              => static::labels(),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 6,
				'menu_icon'           => static::icon(),
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
	 * Keeps what was sent.
	 *
	 * @param array $item Checked details: the keys of META except "mailed".
	 * @return int Its ID, or 0 when it couldn't be kept.
	 */
	public static function add( array $item ) {
		$meta = array();

		foreach ( static::META as $key => $meta_key ) {
			$value = isset( $item[ $key ] ) ? (string) $item[ $key ] : '';

			// Empty details aren't kept.
			if ( 'mailed' !== $key && '' !== $value && static::keep( $key, $value ) ) {
				$meta[ $meta_key ] = $value;
			}
		}

		// New until it is opened.
		$meta[ static::NEW_META ] = '1';

		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => static::NAME,
					'post_status'    => 'publish',
					'post_title'     => static::title( $item ),
					'post_content'   => static::content( $item ),
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
	 * Whether a filled-in detail is kept.
	 *
	 * @param string $key   Detail key.
	 * @param string $value Its value.
	 * @return bool
	 */
	protected static function keep( $key, $value ) {
		return true;
	}

	/**
	 * Its name in the list in wp-admin: the person's name.
	 *
	 * @param array $item Details.
	 * @return string
	 */
	protected static function title( array $item ) {
		return static::name( $item );
	}

	/**
	 * Its text, which the list's search also looks through: the message.
	 *
	 * @param array $item Details.
	 * @return string
	 */
	protected static function content( array $item ) {
		return isset( $item['message'] ) ? (string) $item['message'] : '';
	}

	/**
	 * Kept details.
	 *
	 * @param int $post_id ID.
	 * @return array The keys of META.
	 */
	public static function get( $post_id ) {
		$item = array();

		foreach ( static::META as $key => $meta_key ) {
			$item[ $key ] = (string) get_post_meta( $post_id, $meta_key, true );
		}

		return $item;
	}

	/**
	 * The name of the person who sent it.
	 *
	 * @param array $item Details.
	 * @return string
	 */
	public static function name( array $item ) {
		if ( isset( $item['name'] ) && '' !== trim( (string) $item['name'] ) ) {
			return trim( (string) $item['name'] );
		}

		$first = isset( $item['first_name'] ) ? (string) $item['first_name'] : '';
		$last  = isset( $item['last_name'] ) ? (string) $item['last_name'] : '';

		return trim( $first . ' ' . $last );
	}

	/**
	 * How to name the person who sent it in an email: their first name, or
	 * their whole name when it was given in one box (its first word may be an
	 * initial or a title, such as "W." or "Mr.").
	 *
	 * @param array $item Details.
	 * @return string
	 */
	public static function greeting( array $item ) {
		if ( isset( $item['first_name'] ) && '' !== (string) $item['first_name'] ) {
			return (string) $item['first_name'];
		}

		return static::name( $item );
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
	 * The address of one in wp-admin, or of the list when it wasn't kept.
	 *
	 * @param int $post_id ID, or 0.
	 * @return string
	 */
	protected static function admin_link( $post_id ) {
		return $post_id ? admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ) : admin_url( 'edit.php?post_type=' . static::NAME );
	}

	/**
	 * A link for an email.
	 *
	 * @param string $url  Address.
	 * @param string $text Text.
	 * @return string
	 */
	protected static function link( $url, $text ) {
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
	}

	/**
	 * The first details in an email: who sent it and how to reach them.
	 *
	 * @param array $item Details.
	 * @return array[] Rows of array( label, HTML, plain text ).
	 */
	protected static function person_rows( array $item ) {
		$phone = Phone::display( $item['phone'], $item['country'] );

		return array(
			array( __( 'Name', 'crc-real-estate' ), esc_html( static::name( $item ) ), static::name( $item ) ),
			array( __( 'Phone', 'crc-real-estate' ), static::link( 'tel:' . $item['phone'], $phone ) . ' &middot; ' . static::link( static::whatsapp_url( $item['phone'] ), __( 'WhatsApp', 'crc-real-estate' ) ), $phone ),
			array( __( 'Email', 'crc-real-estate' ), static::link( 'mailto:' . $item['email'], $item['email'] ), $item['email'] ),
		);
	}

	/**
	 * The last details in an email: the page it was sent from, and a note.
	 *
	 * @param array $item Details.
	 * @return array[] Rows of array( label, HTML, plain text ).
	 */
	protected static function end_rows( array $item ) {
		$rows = array();

		if ( '' !== (string) $item['page'] ) {
			$rows[] = array( __( 'Sent from', 'crc-real-estate' ), static::link( $item['page'], $item['page'] ), $item['page'] );
		}

		if ( ! empty( $item['note'] ) ) {
			$rows[] = array( __( 'Note', 'crc-real-estate' ), esc_html( $item['note'] ), $item['note'] );
		}

		return $rows;
	}

	/**
	 * A detail with several lines, for an email.
	 *
	 * @param string $label Label.
	 * @param string $text  Text, or an empty string.
	 * @param string $none  What to say when it is empty.
	 * @return array Row of array( label, HTML, plain text ).
	 */
	protected static function text_row( $label, $text, $none ) {
		$text = '' !== (string) $text ? (string) $text : $none;

		return array( $label, nl2br( esc_html( $text ) ), $text );
	}

	/**
	 * Sends the email: an HTML table of the details with a plain text copy,
	 * to the addresses in Settings. Replying answers the person who sent it.
	 *
	 * @param string  $subject    Subject.
	 * @param string  $intro_html First line, as HTML.
	 * @param string  $intro_text First line, as plain text.
	 * @param array[] $rows       Rows of array( label, HTML, plain text ).
	 * @param array   $item       Details.
	 * @param int     $post_id    Kept item's ID, or 0.
	 * @param string  $kept       Where it is kept in wp-admin.
	 * @return bool Whether the email was handed over for sending.
	 */
	protected static function send( $subject, $intro_html, $intro_text, array $rows, array $item, $post_id, $kept ) {
		$to = Settings::inquiry_recipients();

		if ( ! $to ) {
			return false;
		}

		$html  = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#ffffff;">';
		$html .= '<div style="max-width:600px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#121212;">';
		$html .= '<p style="margin:0 0 16px;">' . $intro_html . '</p>';
		$html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
		$text  = $intro_text . "\n\n";

		foreach ( $rows as $row ) {
			$html .= '<tr><th scope="row" style="width:120px;padding:10px 16px 10px 0;border-top:1px solid #e5e5e5;text-align:left;vertical-align:top;font-weight:600;">' . esc_html( $row[0] ) . '</th>';
			$html .= '<td style="padding:10px 0;border-top:1px solid #e5e5e5;vertical-align:top;">' . $row[1] . '</td></tr>';
			$text .= $row[0] . ': ' . $row[2] . "\n";
		}

		/* translators: %s: first name, or the whole name when the form asks for it in one box. */
		$reply = sprintf( __( 'Reply to this email to answer %s directly.', 'crc-real-estate' ), static::greeting( $item ) );
		$link  = static::admin_link( $post_id );

		$html .= '</table>';
		$html .= '<p style="margin:24px 0 0;color:#6d6d6d;font-size:14px;">' . esc_html( $reply ) . ' <a href="' . esc_url( $link ) . '" style="color:#6d6d6d;">' . esc_html( $kept ) . '</a></p>';
		$html .= '</div></body></html>';
		$text .= "\n" . $reply . "\n" . $kept . "\n" . $link . "\n";

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( '' !== (string) $item['email'] ) {
			$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '"', '<', '>', ',', ';' ), '', static::name( $item ) ) . ' <' . $item['email'] . '>';
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
	 * Text without HTML tags or entities, for email subjects and plain text.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	protected static function plain( $text ) {
		return trim( wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
	}
}
