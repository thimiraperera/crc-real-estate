<?php
/**
 * The emails' look.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The look of the emails the forms send: the logo on top, a white card with
 * a line in the site's main colour, what it is and who it's from, the
 * details in rows, a button to open it in wp-admin, and the business's name
 * and address at the bottom. It is built from tables with the styles written
 * on each part, as email apps expect, with a plain text copy for the ones
 * that don't show HTML.
 *
 * An email is made from these parts:
 *
 * - preheader:  the line email apps show after the subject in the inbox.
 * - label:      what it is, e.g. "New free ad", above the heading.
 * - heading:    e.g. the ad's title or the person's name.
 * - intro:      the first lines, as HTML; intro_text, as plain text.
 * - rows:       the details, each array( label, HTML, plain text ).
 * - button:     array( address, words ), e.g. to open it in wp-admin.
 * - note:       small words under the button.
 * - footer:     small words at the very bottom, under the business's name.
 */
final class Mail {

	const DEFAULT_COLOUR = '#0a6b0a';
	const FONT           = 'Arial, Helvetica, sans-serif';
	const LOGO_HEIGHT    = 56;
	const LOGO_WIDTH     = 200;

	/**
	 * The site's main colour, from Elementor's Site Settings, for the line
	 * on top, the label, the links and the button.
	 *
	 * @return string Hex colour, e.g. #0a6b0a.
	 */
	public static function colour() {
		$colour   = self::DEFAULT_COLOUR;
		$kit      = (int) get_option( 'elementor_active_kit' );
		$settings = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
		$colours  = is_array( $settings ) && isset( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ? $settings['system_colors'] : array();

		foreach ( $colours as $item ) {
			if ( is_array( $item ) && isset( $item['_id'], $item['color'] ) && 'primary' === $item['_id'] ) {
				// Elementor may keep the transparency after the colour (#0a6b0aff).
				$hex = sanitize_hex_color( substr( (string) $item['color'], 0, 7 ) );

				if ( $hex ) {
					$colour = $hex;
				}
			}
		}

		/**
		 * Filters the colour of the emails' line on top, label, links and button.
		 *
		 * @param string $colour Hex colour.
		 */
		$colour = sanitize_hex_color( (string) apply_filters( 'crc_re_email_colour', $colour ) );

		return $colour ? $colour : self::DEFAULT_COLOUR;
	}

	/**
	 * The logo at the top: the one chosen on the Emails tab of Listings →
	 * Settings, or else the site's own logo, sized to fit 200 × 56 pixels.
	 * SVG images are skipped, as most email apps don't show them.
	 *
	 * @return array 'id', 'url', 'width' and 'height'; empty when there is none, and the business name shows instead.
	 */
	public static function logo() {
		foreach ( array( (int) Settings::emails( 'logo' ), (int) get_theme_mod( 'custom_logo' ) ) as $id ) {
			if ( ! $id || ! wp_attachment_is_image( $id ) || 'image/svg+xml' === get_post_mime_type( $id ) ) {
				continue;
			}

			$image = wp_get_attachment_image_src( $id, 'medium' );

			if ( ! $image || empty( $image[1] ) || empty( $image[2] ) ) {
				continue;
			}

			$height = min( self::LOGO_HEIGHT, (int) $image[2] );
			$width  = (int) round( $image[1] * $height / $image[2] );

			if ( $width > self::LOGO_WIDTH ) {
				$width  = self::LOGO_WIDTH;
				$height = (int) round( $image[2] * self::LOGO_WIDTH / $image[1] );
			}

			return array(
				'id'     => $id,
				'url'    => (string) $image[0],
				'width'  => max( 1, $width ),
				'height' => max( 1, $height ),
			);
		}

		return array();
	}

	/**
	 * A link in the email, in the main colour.
	 *
	 * @param string $url  Address.
	 * @param string $text Words.
	 * @return string
	 */
	public static function link( $url, $text ) {
		return '<a href="' . esc_url( $url ) . '" style="color:' . esc_attr( self::colour() ) . ';text-decoration:underline;">' . esc_html( $text ) . '</a>';
	}

	/**
	 * The parts, with every one there.
	 *
	 * @param array $email Parts, see the class.
	 * @return array
	 */
	private static function parts( array $email ) {
		return array_merge(
			array(
				'preheader'  => '',
				'label'      => '',
				'heading'    => '',
				'intro'      => '',
				'intro_text' => '',
				'rows'       => array(),
				'button'     => array(),
				'note'       => '',
				'footer'     => '',
			),
			$email
		);
	}

	/**
	 * The email as HTML.
	 *
	 * @param array $email Parts, see the class.
	 * @return string
	 */
	public static function html( array $email ) {
		$email  = self::parts( $email );
		$colour = esc_attr( self::colour() );
		$font   = esc_attr( self::FONT );
		$name   = (string) Legal::business( 'name' );
		$logo   = self::logo();
		$text   = 'font-family:' . $font . ';';

		$top = $logo
			? sprintf( '<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s" style="display:block;width:%2$dpx;height:%3$dpx;max-width:100%%;border:0;outline:none;text-decoration:none;">', esc_url( $logo['url'] ), $logo['width'], $logo['height'], esc_attr( $name ) )
			: '<span style="' . $text . 'font-size:20px;line-height:28px;font-weight:700;color:#1d1d1d;">' . esc_html( $name ) . '</span>';

		$rows = '';

		foreach ( array_values( $email['rows'] ) as $i => $row ) {
			$line  = $i ? 'border-top:1px solid #ececec;' : '';
			$rows .= '<tr><td valign="top" style="width:128px;padding:12px 16px 12px 0;' . $line . $text . 'font-size:12px;line-height:22px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#7a7a7a;">' . esc_html( $row[0] ) . '</td>'
				. '<td valign="top" style="padding:12px 0;' . $line . $text . 'font-size:15px;line-height:22px;color:#1d1d1d;word-break:break-word;">' . $row[1] . '</td></tr>';
		}

		$button = '';

		if ( ! empty( $email['button'][0] ) ) {
			$button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;"><tr><td style="border-radius:10px;background:' . $colour . ';">'
				. '<a href="' . esc_url( $email['button'][0] ) . '" style="display:inline-block;padding:13px 24px;' . $text . 'font-size:15px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">' . esc_html( $email['button'][1] ) . '</a>'
				. '</td></tr></table>';
		}

		$address = (string) Legal::business( 'address' );
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$bottom  = esc_html( $name ) . ( '' !== $address ? ' &middot; ' . esc_html( $address ) : '' )
			. '<br><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#7a7a7a;text-decoration:underline;">' . esc_html( '' !== $host ? $host : home_url() ) . '</a>'
			. ( '' !== $email['footer'] ? '<br><br>' . esc_html( $email['footer'] ) : '' );

		return '<!DOCTYPE html><html lang="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . esc_html( $email['heading'] ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f2f4f2;-webkit-text-size-adjust:100%;">'
			. ( '' !== $email['preheader'] ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">' . esc_html( $email['preheader'] ) . '</div>' : '' )
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f2f4f2;"><tr><td align="center" style="padding:32px 12px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">'
			. '<tr><td style="padding:0 8px 20px;">' . $top . '</td></tr>'
			. '<tr><td style="background:#ffffff;border-top:4px solid ' . $colour . ';border-radius:16px;padding:32px 28px;">'
			. ( '' !== $email['label'] ? '<p style="margin:0 0 8px;' . $text . 'font-size:12px;line-height:16px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:' . $colour . ';">' . esc_html( $email['label'] ) . '</p>' : '' )
			. ( '' !== $email['heading'] ? '<h1 style="margin:0 0 12px;' . $text . 'font-size:22px;line-height:30px;font-weight:700;color:#1d1d1d;">' . esc_html( $email['heading'] ) . '</h1>' : '' )
			. ( '' !== $email['intro'] ? '<p style="margin:0 0 20px;' . $text . 'font-size:16px;line-height:24px;color:#3d3d3d;">' . $email['intro'] . '</p>' : '' )
			. ( '' !== $rows ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border-top:1px solid #ececec;border-bottom:1px solid #ececec;">' . $rows . '</table>' : '' )
			. $button
			. ( '' !== $email['note'] ? '<p style="margin:20px 0 0;' . $text . 'font-size:13px;line-height:20px;color:#7a7a7a;">' . esc_html( $email['note'] ) . '</p>' : '' )
			. '</td></tr>'
			. '<tr><td align="center" style="padding:24px 16px 0;' . $text . 'font-size:12px;line-height:18px;color:#7a7a7a;">' . $bottom . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * The plain text copy, for email apps that don't show HTML.
	 *
	 * @param array $email Parts, see the class.
	 * @return string
	 */
	public static function text( array $email ) {
		$email = self::parts( $email );
		$lines = array_filter( array( $email['label'], $email['heading'] ), 'strlen' );
		$lines = array( implode( "\n", $lines ), '', $email['intro_text'], '' );

		foreach ( $email['rows'] as $row ) {
			$lines[] = $row[0] . ': ' . $row[2];
		}

		$lines[] = '';

		if ( '' !== $email['note'] ) {
			$lines[] = $email['note'];
		}

		if ( ! empty( $email['button'][0] ) ) {
			$lines[] = $email['button'][1] . ': ' . $email['button'][0];
		}

		$name    = (string) Legal::business( 'name' );
		$address = (string) Legal::business( 'address' );

		$lines[] = '';
		$lines[] = '--';
		$lines[] = $name . ( '' !== $address ? ' - ' . $address : '' );
		$lines[] = home_url( '/' );

		if ( '' !== $email['footer'] ) {
			$lines[] = '';
			$lines[] = $email['footer'];
		}

		return trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) ) ) . "\n";
	}

	/**
	 * Sends an email: the HTML, with the plain text copy beside it.
	 *
	 * @param string[] $to      Addresses.
	 * @param string   $subject Subject.
	 * @param array    $email   Parts, see the class.
	 * @param string[] $headers More headers, such as Reply-To.
	 * @return bool Whether it was handed over for sending.
	 */
	public static function send( array $to, $subject, array $email, array $headers = array() ) {
		$text  = self::text( $email );
		$plain = function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property.
		};

		add_action( 'phpmailer_init', $plain );
		$sent = wp_mail( $to, $subject, self::html( $email ), array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers ) );
		remove_action( 'phpmailer_init', $plain );

		return (bool) $sent;
	}
}
