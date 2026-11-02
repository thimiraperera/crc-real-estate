<?php
/**
 * The emails' look.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The look of the emails the forms send, in the website's own fonts and
 * colours from Elementor's Site Settings: the logo on top, a card with a
 * line in the main colour, what came and from whom, the details in rows, a
 * button to see it on the website's dashboard, and the business's name and
 * address at the bottom. It is built from tables with the styles written on
 * each part, as email apps expect, with a plain text copy for the ones that
 * don't show HTML. Words keep their own capitals: nothing is in capitals only.
 *
 * An email is made from these parts:
 *
 * - preheader:  the line email apps show after the subject in the inbox.
 * - label:      what it is, e.g. "New free ad", above the heading.
 * - heading:    e.g. the ad's title or the person's name.
 * - intro:      the first lines, as HTML; intro_text, as plain text.
 * - rows:       the details, each array( label, HTML, plain text ).
 * - button:     array( address, words ), e.g. to see it on the dashboard.
 * - note:       small words under the button.
 * - footer:     small words at the very bottom, under the business's name.
 */
final class Mail {

	const SAFE_FONTS  = 'Arial, Helvetica, sans-serif';
	const LOGO_HEIGHT = 56;
	const LOGO_WIDTH  = 200;

	/**
	 * Fonts every computer has, which aren't loaded from Google Fonts.
	 */
	const SYSTEM_FONTS = array( 'arial', 'helvetica', 'verdana', 'tahoma', 'trebuchet ms', 'georgia', 'times new roman', 'courier new', 'segoe ui', 'system-ui', 'sans-serif', 'serif' );

	/**
	 * The colours used when Elementor has none: key => colour.
	 */
	const COLOURS = array(
		'brand'      => '#0a6b0a',
		'heading'    => '#1d1d1d',
		'text'       => '#5f6368',
		'background' => '#f3f3f3',
		'card'       => '#ffffff',
	);

	/**
	 * Elementor's Site Settings: the active kit's settings.
	 *
	 * @return array
	 */
	private static function kit() {
		$kit      = (int) get_option( 'elementor_active_kit' );
		$settings = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * A colour from the Site Settings: a system one (primary, text) or a
	 * custom one by its ID (6e3d619), the same ones the plugin's styles use.
	 *
	 * @param array  $kit     Site Settings.
	 * @param string $id      Colour's ID.
	 * @param string $default Colour when there is none.
	 * @return string Hex colour.
	 */
	private static function kit_colour( array $kit, $id, $default ) {
		foreach ( array( 'system_colors', 'custom_colors' ) as $list ) {
			foreach ( isset( $kit[ $list ] ) && is_array( $kit[ $list ] ) ? $kit[ $list ] : array() as $item ) {
				if ( is_array( $item ) && isset( $item['_id'], $item['color'] ) && $id === $item['_id'] ) {
					// Elementor may keep the transparency after the colour (#0a6b0aff).
					$hex = sanitize_hex_color( substr( (string) $item['color'], 0, 7 ) );

					if ( $hex ) {
						return $hex;
					}
				}
			}
		}

		return $default;
	}

	/**
	 * A colour as red, green and blue.
	 *
	 * @param string $hex Hex colour.
	 * @return int[]
	 */
	private static function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Two colours mixed, as the website's styles mix them for lighter shades.
	 *
	 * @param string $colour Hex colour.
	 * @param string $with   Hex colour it is mixed into.
	 * @param float  $amount How much of the first, from 0 to 1.
	 * @return string Hex colour.
	 */
	private static function mix( $colour, $with, $amount ) {
		$a   = self::rgb( $colour );
		$b   = self::rgb( $with );
		$out = '#';

		for ( $i = 0; $i < 3; $i++ ) {
			$out .= sprintf( '%02x', (int) round( $a[ $i ] * $amount + $b[ $i ] * ( 1 - $amount ) ) );
		}

		return $out;
	}

	/**
	 * The emails' colours, the website's own: brand (the main colour, for
	 * the line on top, the label, links and the button), heading (dark
	 * text), text (body text), background (the soft grey around the card),
	 * card, and line (the light lines between the details).
	 *
	 * @return string[] Key => hex colour.
	 */
	public static function colours() {
		$kit     = self::kit();
		$colours = array(
			'brand'      => self::kit_colour( $kit, 'primary', self::COLOURS['brand'] ),
			'heading'    => self::kit_colour( $kit, '6e3d619', self::COLOURS['heading'] ),
			'text'       => self::kit_colour( $kit, 'text', self::COLOURS['text'] ),
			'background' => self::kit_colour( $kit, 'f5fd1b7', self::COLOURS['background'] ),
			'card'       => self::kit_colour( $kit, '3581a59', self::COLOURS['card'] ),
		);

		/**
		 * Filters the emails' colours.
		 *
		 * @param string[] $colours brand, heading, text, background and card, as hex colours.
		 */
		$colours = (array) apply_filters( 'crc_re_email_colours', $colours );

		foreach ( self::COLOURS as $key => $default ) {
			$colours[ $key ] = isset( $colours[ $key ] ) && sanitize_hex_color( (string) $colours[ $key ] ) ? (string) $colours[ $key ] : $default;
		}

		$colours['line'] = self::mix( $colours['heading'], $colours['card'], 0.1 );

		return $colours;
	}

	/**
	 * The website's main colour, for links.
	 *
	 * @return string Hex colour.
	 */
	public static function colour() {
		return self::colours()['brand'];
	}

	/**
	 * The emails' fonts, the website's own Primary (headings) and Text fonts
	 * from the Site Settings, each with safe fonts after it for email apps
	 * that can't load it (such as Gmail), and the Google Fonts address that
	 * loads them in those that can (such as Apple Mail).
	 *
	 * @return array 'heading' and 'text' (font lists), 'weight' (the headings'), and 'link' (or empty).
	 */
	public static function fonts() {
		$kit   = self::kit();
		$found = array();

		foreach ( isset( $kit['system_typography'] ) && is_array( $kit['system_typography'] ) ? $kit['system_typography'] : array() as $item ) {
			if ( is_array( $item ) && isset( $item['_id'] ) ) {
				$family = isset( $item['typography_font_family'] ) ? trim( preg_replace( '/[^A-Za-z0-9 \-]/', '', (string) $item['typography_font_family'] ) ) : '';

				$found[ $item['_id'] ] = array(
					'family' => $family,
					'weight' => isset( $item['typography_font_weight'] ) && preg_match( '/^[1-9]00$/', (string) $item['typography_font_weight'] ) ? (string) $item['typography_font_weight'] : '',
				);
			}
		}

		$heading = ! empty( $found['primary']['family'] ) ? $found['primary']['family'] : ( ! empty( $found['text']['family'] ) ? $found['text']['family'] : '' );
		$text    = ! empty( $found['text']['family'] ) ? $found['text']['family'] : $heading;
		$weight  = ! empty( $found['primary']['weight'] ) ? $found['primary']['weight'] : '700';
		$stack   = function ( $family ) {
			return '' !== $family ? "'" . $family . "', " . self::SAFE_FONTS : self::SAFE_FONTS;
		};
		$load    = array();

		foreach ( array_unique( array( $heading, $text ) ) as $family ) {
			if ( '' !== $family && ! in_array( strtolower( $family ), self::SYSTEM_FONTS, true ) ) {
				// Google Fonts leaves out weights a font doesn't have.
				$load[] = str_replace( ' ', '+', $family ) . ':400,500,600,700';
			}
		}

		return array(
			'heading' => $stack( $heading ),
			'text'    => $stack( $text ),
			'weight'  => $weight,
			'link'    => $load ? 'https://fonts.googleapis.com/css?family=' . implode( '|', $load ) . '&display=swap' : '',
		);
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
		$email   = self::parts( $email );
		$c       = array_map( 'esc_attr', self::colours() );
		$fonts   = self::fonts();
		$heading = 'font-family:' . esc_attr( $fonts['heading'] ) . ';';
		$text    = 'font-family:' . esc_attr( $fonts['text'] ) . ';';
		$name    = (string) Legal::business( 'name' );
		$logo    = self::logo();

		$top = $logo
			? sprintf( '<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s" style="display:block;width:%2$dpx;height:%3$dpx;max-width:100%%;border:0;outline:none;text-decoration:none;">', esc_url( $logo['url'] ), $logo['width'], $logo['height'], esc_attr( $name ) )
			: '<span style="' . $heading . 'font-size:22px;line-height:30px;font-weight:' . esc_attr( $fonts['weight'] ) . ';color:' . $c['heading'] . ';">' . esc_html( $name ) . '</span>';

		$rows = '';

		foreach ( array_values( $email['rows'] ) as $i => $row ) {
			$line  = $i ? 'border-top:1px solid ' . $c['line'] . ';' : '';
			$rows .= '<tr><td valign="top" class="crc-mail-label" style="width:132px;padding:12px 16px 12px 0;' . $line . $text . 'font-size:14px;line-height:22px;color:' . $c['text'] . ';">' . esc_html( $row[0] ) . '</td>'
				. '<td valign="top" class="crc-mail-value" style="padding:12px 0;' . $line . $text . 'font-size:15px;line-height:22px;color:' . $c['heading'] . ';word-break:break-word;">' . $row[1] . '</td></tr>';
		}

		$button = '';

		if ( ! empty( $email['button'][0] ) ) {
			$button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;"><tr><td style="border-radius:10px;background:' . $c['brand'] . ';">'
				. '<a href="' . esc_url( $email['button'][0] ) . '" style="display:inline-block;padding:14px 26px;' . $heading . 'font-size:15px;line-height:20px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">' . esc_html( $email['button'][1] ) . '</a>'
				. '</td></tr></table>';
		}

		$address = (string) Legal::business( 'address' );
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$bottom  = esc_html( $name ) . ( '' !== $address ? ' &middot; ' . esc_html( $address ) : '' )
			. '<br><a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . $c['text'] . ';text-decoration:underline;">' . esc_html( '' !== $host ? $host : home_url() ) . '</a>'
			. ( '' !== $email['footer'] ? '<br><br>' . esc_html( $email['footer'] ) : '' );

		return '<!DOCTYPE html><html lang="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . esc_html( $email['heading'] ) . '</title>'
			. ( '' !== $fonts['link'] ? '<link href="' . esc_url( $fonts['link'] ) . '" rel="stylesheet">' : '' )
			// Outlook on Windows shows a font it can't load as Times New Roman; it gets the safe fonts instead.
			. '<!--[if mso]><style>body, table, td, p, a, h1, span { font-family: ' . self::SAFE_FONTS . ' !important; }</style><![endif]-->'
			// On phones each detail's name goes above it, where email apps allow; elsewhere they stay side by side.
			. '<style>@media only screen and (max-width: 480px) { .crc-mail-card { padding: 24px 20px !important; } .crc-mail-label, .crc-mail-value { display: block !important; width: auto !important; } .crc-mail-label { padding: 12px 0 2px !important; } .crc-mail-value { padding: 0 0 12px !important; border-top: 0 !important; } }</style>'
			. '</head>'
			. '<body style="margin:0;padding:0;background:' . $c['background'] . ';-webkit-text-size-adjust:100%;">'
			. ( '' !== $email['preheader'] ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">' . esc_html( $email['preheader'] ) . '</div>' : '' )
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $c['background'] . ';"><tr><td align="center" style="padding:32px 12px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">'
			. '<tr><td style="padding:0 8px 20px;">' . $top . '</td></tr>'
			. '<tr><td class="crc-mail-card" style="background:' . $c['card'] . ';border:1px solid ' . $c['line'] . ';border-top:4px solid ' . $c['brand'] . ';border-radius:0 0 16px 16px;padding:32px 28px;">'
			. ( '' !== $email['label'] ? '<p style="margin:0 0 6px;' . $text . 'font-size:15px;line-height:22px;font-weight:600;color:' . $c['brand'] . ';">' . esc_html( $email['label'] ) . '</p>' : '' )
			. ( '' !== $email['heading'] ? '<h1 style="margin:0 0 12px;' . $heading . 'font-size:24px;line-height:32px;font-weight:' . esc_attr( $fonts['weight'] ) . ';color:' . $c['heading'] . ';">' . esc_html( $email['heading'] ) . '</h1>' : '' )
			. ( '' !== $email['intro'] ? '<p style="margin:0 0 20px;' . $text . 'font-size:16px;line-height:26px;color:' . $c['text'] . ';">' . $email['intro'] . '</p>' : '' )
			. ( '' !== $rows ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border-top:1px solid ' . $c['line'] . ';border-bottom:1px solid ' . $c['line'] . ';">' . $rows . '</table>' : '' )
			. $button
			. ( '' !== $email['note'] ? '<p style="margin:20px 0 0;' . $text . 'font-size:14px;line-height:22px;color:' . $c['text'] . ';">' . esc_html( $email['note'] ) . '</p>' : '' )
			. '</td></tr>'
			. '<tr><td align="center" style="padding:24px 16px 0;' . $text . 'font-size:13px;line-height:20px;color:' . $c['text'] . ';">' . $bottom . '</td></tr>'
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
	 * Sends an email: the HTML, with the plain text copy beside it. The
	 * first address gets it; any others get a blind copy (Bcc), so their
	 * addresses aren't shown to anyone.
	 *
	 * @param string[] $to      Addresses.
	 * @param string   $subject Subject.
	 * @param array    $email   Parts, see the class.
	 * @param string[] $headers More headers, such as Reply-To.
	 * @return bool Whether it was handed over for sending.
	 */
	public static function send( array $to, $subject, array $email, array $headers = array() ) {
		$to = array_values( array_filter( array_map( 'trim', $to ), 'strlen' ) );

		if ( ! $to ) {
			return false;
		}

		$first   = array_shift( $to );
		$headers = array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers );

		foreach ( $to as $address ) {
			$headers[] = 'Bcc: ' . $address;
		}

		$text  = self::text( $email );
		$plain = function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property.
		};

		add_action( 'phpmailer_init', $plain );
		$sent = wp_mail( $first, $subject, self::html( $email ), $headers );
		remove_action( 'phpmailer_init', $plain );

		return (bool) $sent;
	}
}
