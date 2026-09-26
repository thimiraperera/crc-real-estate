<?php
/**
 * Inquiry form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Icons;
use CRC\RealEstate\Inquiries;
use CRC\RealEstate\Phone;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * The "Send an inquiry" form: first and last name, phone number with its
 * country, email and message. It is checked in the browser as people type,
 * and again when it arrives, optionally with hCaptcha. Then it is emailed
 * and kept under Listings → Inquiries.
 *
 * Pages with the form are cached, so it carries no security token (one
 * would go stale in the cache). A hidden trap field, a limit per visitor
 * and hCaptcha keep robots out instead.
 */
final class Inquiry {

	const SHORTCODE      = 'crc_listing_inquiry';
	const ACTION         = 'crc_re_inquiry';
	const NAME_LENGTH    = 50;
	const EMAIL_LENGTH   = 254;
	const MESSAGE_LENGTH = 2000;
	const LIMIT          = 5;
	const LIMIT_MINUTES  = 10;
	const CAPTCHA_SCRIPT = 'https://js.hcaptcha.com/1/api.js';
	const CAPTCHA_CHECK  = 'https://api.hcaptcha.com/siteverify';
	const CAPTCHA_ISSUE  = 'crc_re_captcha_issue';
	const FLAGS          = 'https://cdn.jsdelivr.net/npm/flag-icons@7.5.0/flags/4x3/';

	/**
	 * Replies from hCaptcha that mean its keys in Settings aren't right.
	 */
	const CAPTCHA_SETUP_ERRORS = array( 'missing-input-secret', 'invalid-input-secret', 'sitekey-secret-mismatch', 'not-using-dummy-passcode' );

	/**
	 * Forms on the page so far, for unique field IDs.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Whether the browser settings were added to the page.
	 *
	 * @var bool
	 */
	private static $localized = false;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_listing' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'submit' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Inquiry form', 'crc-real-estate' ),
				'description' => __( 'The "Send an inquiry" form: First Name, Last Name, Phone Number with a country code (Sri Lanka unless changed), E-Mail and Your Message. First Name, Phone Number and E-Mail must be filled in. Everything is checked while people type and again when the inquiry arrives, with clear messages under each field. The fields and labels take the site\'s own Elementor styles. On a listing page the inquiry says which listing it is about; on any other page it is a general inquiry. Inquiries are emailed to the address in Listings → Settings and kept under Listings → Inquiries. Turn on hCaptcha in Listings → Settings to stop spam robots.', 'crc-real-estate' ),
				'attributes'  => array(
					'id'      => array(
						'default'     => '',
						'description' => __( 'Listing ID the inquiry is about. Leave it out on a listing page to use that listing, or on any other page for general inquiries.', 'crc-real-estate' ),
					),
					'button'  => array(
						'default'     => __( 'Send Inquiry', 'crc-real-estate' ),
						'description' => __( 'The text on the send button.', 'crc-real-estate' ),
					),
					'country' => array(
						'default'     => Phone::DEFAULT_COUNTRY,
						'description' => __( 'The country chosen at first for phone numbers, as its two-letter code: LK for Sri Lanka, GB for the United Kingdom, AU for Australia and so on. People can still choose any country.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' button="Ask about this land"]',
					'[' . self::SHORTCODE . ' id="123" country="GB"]',
				),
			)
		);
	}

	/**
	 * Registers the front-end files.
	 */
	public function register_assets() {
		wp_register_style( 'crc-re-inquiry', CRC_RE_URL . 'assets/css/inquiry.css', array(), CRC_RE_VERSION );
		wp_register_script( 'crc-re-inquiry', CRC_RE_URL . 'assets/js/inquiry.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the form's styles in the page head on listing pages, so the form
	 * never shows unstyled.
	 */
	public function enqueue_on_listing() {
		if ( is_singular( Post_Type::NAME ) ) {
			wp_enqueue_style( 'crc-re-inquiry' );
		}
	}

	/**
	 * The messages people see. The browser shows the same ones.
	 *
	 * @return string[]
	 */
	public static function messages() {
		return array(
			'first_name'     => __( 'Please enter your first name.', 'crc-real-estate' ),
			'first_letters'  => __( 'Please use only letters in your first name.', 'crc-real-estate' ),
			'last_letters'   => __( 'Please use only letters in your last name.', 'crc-real-estate' ),
			/* translators: %s: the most characters allowed, e.g. 50. */
			'too_long'       => __( 'Please keep this to %s characters or fewer.', 'crc-real-estate' ),
			'phone'          => __( 'Please enter your phone number.', 'crc-real-estate' ),
			/* translators: 1: country name, 2: its calling code, e.g. 94. */
			'phone_invalid'  => __( 'Please enter a valid phone number for %1$s (+%2$s).', 'crc-real-estate' ),
			/* translators: 1: country name, 2: its calling code, e.g. 94, 3: example number. */
			'phone_example'  => __( 'Please enter a valid phone number for %1$s (+%2$s), for example %3$s.', 'crc-real-estate' ),
			'email'          => __( 'Please enter your e-mail address.', 'crc-real-estate' ),
			'email_invalid'  => __( 'Please enter a valid e-mail address, for example nimal.perera@gmail.com.', 'crc-real-estate' ),
			/* translators: %s: the part of the e-mail address after @, e.g. gmial.com. */
			'email_domain'   => __( 'We couldn\'t find "%s". Please check your e-mail address for typing mistakes.', 'crc-real-estate' ),
			/* translators: %s: suggested email address. */
			'email_suggest'  => __( 'Did you mean %s?', 'crc-real-estate' ),
			/* translators: %s: the most characters allowed, e.g. 2,000. */
			'message_long'   => __( 'Please keep your message to %s characters or fewer.', 'crc-real-estate' ),
			'captcha'        => __( 'Please tick the "I am human" box.', 'crc-real-estate' ),
			'captcha_failed' => __( 'The "I am human" check didn\'t go through. Please tick the box again.', 'crc-real-estate' ),
			'captcha_load'   => __( 'The "I am human" box couldn\'t load. Please reload the page and try again.', 'crc-real-estate' ),
			'busy'           => __( 'You\'ve sent a few inquiries in a short time. Please wait a few minutes, then try again.', 'crc-real-estate' ),
			'failed'         => self::failed_message(),
			'network'        => __( 'Your inquiry couldn\'t be sent. Please check your internet connection and try again.', 'crc-real-estate' ),
			/* translators: %s: names of the fields to check, e.g. "Phone Number, E-Mail". */
			'fields'         => __( 'Your inquiry wasn\'t sent. Please check these and try again: %s.', 'crc-real-estate' ),
			/* translators: 1: country name, 2: its calling code, e.g. 94. */
			'country_button' => __( 'Country code: %1$s (+%2$s). Change the country', 'crc-real-estate' ),
			'country_search' => __( 'Search for a country', 'crc-real-estate' ),
			'country_none'   => __( 'No country found. Try another name or code.', 'crc-real-estate' ),
			'sending'        => __( 'Sending…', 'crc-real-estate' ),
			/* translators: %s: first name. */
			'sent'           => __( 'Thank you, %s! Your inquiry has been sent. We\'ll get back to you soon.', 'crc-real-estate' ),
			'sent_plain'     => __( 'Thank you! Your inquiry has been sent. We\'ll get back to you soon.', 'crc-real-estate' ),
		);
	}

	/**
	 * The field labels.
	 *
	 * @return string[]
	 */
	public static function labels() {
		return array(
			'first_name' => __( 'First Name', 'crc-real-estate' ),
			'last_name'  => __( 'Last Name', 'crc-real-estate' ),
			'phone'      => __( 'Phone Number', 'crc-real-estate' ),
			'email'      => __( 'E-Mail', 'crc-real-estate' ),
			'message'    => __( 'Your Message', 'crc-real-estate' ),
			'captcha'    => __( 'I am human', 'crc-real-estate' ),
		);
	}

	/**
	 * Example phone numbers, as dialled in each country. They show in the
	 * empty phone box, and in the message when a number isn't right.
	 *
	 * @return string[] Country code => example.
	 */
	public static function examples() {
		/**
		 * Filters the example phone numbers.
		 *
		 * @param string[] $examples Country code => example, as dialled in that country.
		 */
		return (array) apply_filters(
			'crc_re_phone_examples',
			array(
				'LK' => '077 123 4567',
				'IN' => '081234 56789',
				'MV' => '771 2345',
				'AE' => '050 123 4567',
				'QA' => '3312 3456',
				'SA' => '051 234 5678',
				'KW' => '500 12345',
				'OM' => '9212 3456',
				'BH' => '3600 1234',
				'SG' => '8123 4567',
				'MY' => '012-345 6789',
				'JP' => '090-1234-5678',
				'KR' => '010-2000-0000',
				'AU' => '0412 345 678',
				'NZ' => '021 123 4567',
				'GB' => '07400 123456',
				'IT' => '312 345 6789',
				'DE' => '01512 3456789',
				'FR' => '06 12 34 56 78',
				'CA' => '(506) 234-5678',
				'US' => '(201) 555-0123',
			)
		);
	}

	/**
	 * Examples shown in the empty fields, with Sri Lankan details.
	 *
	 * @return string[] Field key => text. "phone" gets the chosen country's example number in place of %s; "phone_other" is for countries without one.
	 */
	public static function placeholders() {
		/**
		 * Filters the examples shown in the empty inquiry fields.
		 *
		 * @param string[] $placeholders Field key => text. "phone" gets the chosen country's example number in place of %s; "phone_other" is for countries without one.
		 */
		return (array) apply_filters(
			'crc_re_inquiry_placeholders',
			array(
				'first_name'  => __( 'e.g. Nimal', 'crc-real-estate' ),
				'last_name'   => __( 'e.g. Perera', 'crc-real-estate' ),
				/* translators: %s: example phone number, e.g. 077 123 4567. */
				'phone'       => __( 'e.g. %s', 'crc-real-estate' ),
				'phone_other' => __( 'Type your phone number here', 'crc-real-estate' ),
				'email'       => __( 'e.g. nimal.perera@gmail.com', 'crc-real-estate' ),
				'message'     => __( 'e.g. Is this still available? I\'d like to see it this weekend.', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * The example in the empty phone box for a country.
	 *
	 * @param string $country Country code.
	 * @return string
	 */
	public static function phone_placeholder( $country ) {
		$examples     = self::examples();
		$placeholders = self::placeholders();

		return ! empty( $examples[ $country ] ) ? sprintf( $placeholders['phone'], $examples[ $country ] ) : $placeholders['phone_other'];
	}

	/**
	 * Popular email services, most used first, to spot typing mistakes such
	 * as gmial.com. Every real service near another is listed, so neither
	 * is "corrected" to the other (mail.com isn't a typo for gmail.com).
	 *
	 * @return string[]
	 */
	public static function email_domains() {
		return array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'live.com', 'googlemail.com', 'ymail.com', 'msn.com', 'me.com', 'mac.com', 'aol.com', 'proton.me', 'protonmail.com', 'yahoo.co.uk', 'hotmail.co.uk', 'yahoo.co.in', 'rediffmail.com', 'sltnet.lk', 'mail.com', 'gmx.com', 'gmx.net', 'zoho.com', 'yandex.com' );
	}

	/**
	 * The message when an inquiry couldn't be sent, with the site's phone
	 * number when there is one.
	 *
	 * @return string
	 */
	private static function failed_message() {
		$phone = Settings::get( 'phone' );

		return '' !== $phone
			/* translators: %s: phone number. */
			? sprintf( __( 'Sorry, your inquiry couldn\'t be sent. Please try again in a moment, or call us on %s.', 'crc-real-estate' ), $phone )
			: __( 'Sorry, your inquiry couldn\'t be sent. Please try again in a moment.', 'crc-real-estate' );
	}

	/**
	 * Renders [crc_listing_inquiry].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts      = Shortcodes::atts( self::SHORTCODE, $atts );
		$listing   = Shortcodes::listing( $atts['id'] );
		$countries = Phone::countries();
		$country   = strtoupper( trim( (string) $atts['country'] ) );
		$country   = isset( $countries[ $country ] ) ? $country : Phone::DEFAULT_COUNTRY;
		$button    = trim( (string) $atts['button'] );
		$button    = '' !== $button ? $button : __( 'Send Inquiry', 'crc-real-estate' );
		$id        = 'crc-inquiry-' . ( ++self::$count );
		$labels    = self::labels();
		$examples  = self::placeholders();

		$this->enqueue();

		$html  = sprintf( '<form class="crc-inquiry" id="%1$s" action="%2$s" method="post" data-crc-inquiry>', esc_attr( $id ), esc_url( admin_url( 'admin-ajax.php' ) ) );
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		$html .= '<input type="hidden" name="crc_form" value="' . esc_attr( $id ) . '">';
		$html .= '<input type="hidden" name="crc_listing" value="' . esc_attr( $listing ? $listing->ID : 0 ) . '">';
		$html .= '<input type="hidden" name="crc_page" value="' . esc_url( self::page_url( $listing ) ) . '">';

		// People never see this field; robots fill it in.
		$html .= '<div class="crc-inquiry-trap" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'crc-real-estate' ) . ' <input type="text" name="crc_website" value="" tabindex="-1" autocomplete="off"></label></div>';

		$html .= '<div class="crc-inquiry-names">';
		$html .= self::field(
			$id,
			'first_name',
			$labels['first_name'],
			true,
			self::input( $id, 'first_name', 'text', $examples['first_name'], ' autocomplete="given-name" autocapitalize="words" maxlength="' . self::NAME_LENGTH . '" required' )
		);
		$html .= self::field(
			$id,
			'last_name',
			$labels['last_name'],
			false,
			self::input( $id, 'last_name', 'text', $examples['last_name'], ' autocomplete="family-name" autocapitalize="words" maxlength="' . self::NAME_LENGTH . '"' )
		);
		$html .= '</div>';

		$html .= self::field( $id, 'phone', $labels['phone'], true, self::phone_control( $id, $countries, $country ) );
		$html .= self::field(
			$id,
			'email',
			$labels['email'],
			true,
			self::input( $id, 'email', 'email', $examples['email'], ' autocomplete="email" autocapitalize="off" spellcheck="false" maxlength="' . self::EMAIL_LENGTH . '" required', true )
		);
		$html .= self::field(
			$id,
			'message',
			$labels['message'],
			false,
			sprintf(
				'<textarea class="crc-inquiry-input crc-inquiry-message" id="%1$s-message" name="crc_message" rows="5" maxlength="%2$d" placeholder="%3$s" aria-describedby="%1$s-message-error"></textarea>',
				esc_attr( $id ),
				self::MESSAGE_LENGTH,
				esc_attr( $examples['message'] )
			)
		);

		if ( Settings::captcha_on() ) {
			$html .= sprintf(
				'<div class="crc-inquiry-field crc-inquiry-captcha-field" data-crc-field="captcha"><div class="crc-inquiry-captcha" id="%1$s-captcha"></div><p class="crc-inquiry-error" id="%1$s-captcha-error" role="alert" hidden></p></div>',
				esc_attr( $id )
			);
			$html .= '<noscript><p class="crc-inquiry-status is-error">' . esc_html__( 'Please turn on JavaScript in your browser to send an inquiry.', 'crc-real-estate' ) . '</p></noscript>';
		}

		// The site's own button style (custom-btn-3), full width.
		$html .= sprintf(
			'<div class="custom-btn-3 crc-inquiry-submit"><button class="elementor-button crc-inquiry-button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">%1$s</span><span class="elementor-button-icon">%2$s</span></span></button></div>',
			esc_html( $button ),
			Icons::svg( 'arrow-right', 'crc-inquiry-button-icon' )
		);

		$html .= self::notice();
		$html .= '</form>';

		return $html;
	}

	/**
	 * A field with its label and error message.
	 *
	 * @param string $id       Form ID.
	 * @param string $key      Field key.
	 * @param string $label    Label.
	 * @param bool   $required Whether it must be filled in.
	 * @param string $control  The input's HTML.
	 * @return string
	 */
	private static function field( $id, $key, $label, $required, $control ) {
		return sprintf(
			'<div class="crc-inquiry-field" data-crc-field="%1$s"><label class="crc-inquiry-label" for="%2$s">%3$s%4$s</label>%5$s<p class="crc-inquiry-error" id="%2$s-error" hidden></p>%6$s</div>',
			esc_attr( $key ),
			esc_attr( $id . '-' . str_replace( '_', '-', $key ) ),
			esc_html( $label ),
			$required ? '<span class="crc-inquiry-required" aria-hidden="true">&nbsp;*</span>' : '',
			$control,
			'email' === $key ? '<p class="crc-inquiry-hint" id="' . esc_attr( $id ) . '-email-hint" hidden></p>' : ''
		);
	}

	/**
	 * A text input.
	 *
	 * @param string $id          Form ID.
	 * @param string $key         Field key.
	 * @param string $type        Input type.
	 * @param string $placeholder Placeholder.
	 * @param string $attributes  More attributes, already escaped.
	 * @param bool   $hint        Whether it has a hint below it.
	 * @return string
	 */
	private static function input( $id, $key, $type, $placeholder, $attributes, $hint = false ) {
		$field_id = $id . '-' . str_replace( '_', '-', $key );

		return sprintf(
			'<input class="%7$s" id="%1$s" name="crc_%2$s" type="%3$s" placeholder="%4$s" aria-describedby="%5$s"%6$s>',
			esc_attr( $field_id ),
			esc_attr( $key ),
			esc_attr( $type ),
			esc_attr( $placeholder ),
			esc_attr( $field_id . '-error' . ( $hint ? ' ' . $field_id . '-hint' : '' ) ),
			$attributes,
			'phone' === $key ? 'crc-inquiry-input crc-inquiry-number' : 'crc-inquiry-input'
		);
	}

	/**
	 * The phone number box: the country's code, which opens a list of every
	 * country, and the number.
	 *
	 * @param string  $id        Form ID.
	 * @param array[] $countries Countries.
	 * @param string  $country   Country chosen at first.
	 * @return string
	 */
	private static function phone_control( $id, array $countries, $country ) {
		$options = '';

		foreach ( $countries as $code => $details ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s (+%4$s)</option>',
				esc_attr( $code ),
				$code === $country ? ' selected' : '',
				esc_html( $details['name'] ),
				esc_html( $details['dial'] )
			);
		}

		return sprintf(
			'<div class="crc-inquiry-phone"><div class="crc-inquiry-country"><img class="crc-inquiry-country-flag" src="%1$s" alt="" width="20" height="15" loading="lazy" decoding="async"><span class="crc-inquiry-country-code" aria-hidden="true">+%2$s</span>%3$s<select class="crc-inquiry-country-select" id="%4$s-country" name="crc_country" aria-label="%5$s">%6$s</select></div>%7$s</div>',
			esc_url( self::flag( $country ) ),
			esc_html( $countries[ $country ]['dial'] ),
			Icons::svg( 'chevron-down', 'crc-inquiry-country-icon' ),
			esc_attr( $id ),
			esc_attr__( 'Country code of the phone number', 'crc-real-estate' ),
			$options,
			self::input( $id, 'phone', 'tel', self::phone_placeholder( $country ), ' autocomplete="tel" inputmode="tel" maxlength="24" required' )
		);
	}

	/**
	 * Where the country flags load from: flag-icons (MIT license) on
	 * jsDelivr, one small picture per country, named by its two-letter code.
	 *
	 * @return string Folder address ending in a slash.
	 */
	public static function flags_url() {
		/**
		 * Filters where the country flags load from, e.g. to load them from the site itself.
		 *
		 * @param string $url Folder with lk.svg, gb.svg and so on, ending in a slash.
		 */
		return (string) apply_filters( 'crc_re_flags_url', self::FLAGS );
	}

	/**
	 * A country's flag.
	 *
	 * @param string $country Country code, e.g. "LK".
	 * @return string
	 */
	public static function flag( $country ) {
		return self::flags_url() . strtolower( preg_replace( '/[^A-Za-z]/', '', (string) $country ) ) . '.svg';
	}

	/**
	 * The page the form is on, sent with the inquiry.
	 *
	 * @param \WP_Post|null $listing Listing the form is about.
	 * @return string
	 */
	private static function page_url( $listing ) {
		if ( $listing ) {
			return (string) get_permalink( $listing );
		}

		return is_singular() ? (string) get_permalink( get_queried_object_id() ) : home_url( '/' );
	}

	/**
	 * The result of an inquiry sent without JavaScript, after the page comes back.
	 *
	 * @return string
	 */
	private static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only shows a fixed message.
		$result = isset( $_GET['crc_inquiry'] ) ? sanitize_key( wp_unslash( $_GET['crc_inquiry'] ) ) : '';
		$code   = isset( $_GET['crc_error'] ) ? sanitize_key( wp_unslash( $_GET['crc_error'] ) ) : '';
		$fields = isset( $_GET['crc_fields'] ) ? sanitize_text_field( wp_unslash( $_GET['crc_fields'] ) ) : '';
		// phpcs:enable

		$messages = self::messages();

		if ( 'sent' === $result ) {
			return '<div class="crc-inquiry-status is-success" role="status">' . esc_html( $messages['sent_plain'] ) . '</div>';
		}

		if ( 'failed' === $result ) {
			if ( 'fields' === $code ) {
				$names = array_intersect_key( self::labels(), array_flip( explode( ',', $fields ) ) );
				$text  = sprintf( $messages['fields'], implode( ', ', $names ? $names : array( __( 'your details', 'crc-real-estate' ) ) ) );
			} else {
				$text = in_array( $code, array( 'busy', 'captcha' ), true ) ? $messages[ $code ] : $messages['failed'];
			}

			return '<div class="crc-inquiry-status is-error" role="status">' . esc_html( $text ) . '</div>';
		}

		return '<div class="crc-inquiry-status" role="status" aria-live="polite" hidden></div>';
	}

	/**
	 * Loads the form's files and settings.
	 */
	private function enqueue() {
		if ( ! wp_style_is( 'crc-re-inquiry', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-inquiry' );
		wp_enqueue_script( 'crc-re-inquiry' );

		if ( self::$localized ) {
			return;
		}

		self::$localized = true;

		wp_localize_script(
			'crc-re-inquiry',
			'crcReInquiry',
			array(
				'countries'    => Phone::script_data(),
				'main'         => Phone::MAIN,
				'examples'     => self::examples(),
				'placeholders' => self::placeholders(),
				'flags'        => self::flags_url(),
				'domains'      => self::email_domains(),
				'labels'       => self::labels(),
				'messages'     => self::messages(),
				'captcha'      => Settings::captcha_on() ? array(
					'sitekey' => Settings::inquiry( 'site_key' ),
					'script'  => add_query_arg(
						array(
							'render' => 'explicit',
							'onload' => 'crcReInquiryCaptcha',
							'hl'     => substr( (string) get_locale(), 0, 2 ),
						),
						self::CAPTCHA_SCRIPT
					),
				) : null,
			)
		);
	}

	/**
	 * Receives an inquiry. Sent by the browser, it answers with the result;
	 * sent without JavaScript, it goes back to the page with the result.
	 */
	public function submit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cached public form, see the class description.
		$posted = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$result = $this->process( $posted );

		if ( ! empty( $posted['crc_js'] ) ) {
			wp_send_json( $result );
		}

		$form = self::read( $posted );
		$page = self::valid_page( $form['page'] );
		$page = '' !== $page ? $page : self::valid_page( (string) wp_get_referer() );
		$page = '' !== $page ? $page : home_url( '/' );
		$args = array( 'crc_inquiry' => $result['success'] ? 'sent' : 'failed' );

		if ( ! $result['success'] ) {
			$args['crc_error'] = $result['code'];

			if ( 'fields' === $result['code'] ) {
				$args['crc_fields'] = implode( ',', array_keys( (array) $result['fields'] ) );
			}
		}

		$page = remove_query_arg( array( 'crc_inquiry', 'crc_error', 'crc_fields' ), $page );

		wp_safe_redirect( add_query_arg( $args, $page ) . '#' . ( '' !== $form['form'] ? $form['form'] : 'crc-inquiry-1' ) );
		exit;
	}

	/**
	 * Checks an inquiry and, when it's all right, keeps it and emails it.
	 *
	 * @param array $posted Submitted form, unslashed.
	 * @return array 'success', 'message', 'code' ("fields", "busy", "captcha" or "failed" when it wasn't sent) and 'fields' (field key => message).
	 */
	public function process( array $posted ) {
		$form     = self::read( $posted );
		$messages = self::messages();

		// Robots fill in the trap field. They are told it worked, and nothing is sent.
		if ( '' !== $form['trap'] ) {
			return self::result( true, sprintf( $messages['sent'], $form['first_name'] ) );
		}

		$fields = self::check( $form );

		if ( $fields ) {
			return self::result( false, '', 'fields', $fields );
		}

		if ( self::too_many() ) {
			return self::result( false, $messages['busy'], 'busy' );
		}

		$captcha = self::verify_captcha( $form['captcha'] );

		if ( in_array( $captcha, array( 'missing', 'failed' ), true ) ) {
			$message = 'missing' === $captcha ? $messages['captcha'] : $messages['captcha_failed'];

			return self::result( false, '', 'captcha', array( 'captcha' => $message ) );
		}

		$phone   = Phone::parse( $form['country'], $form['phone'] );
		$inquiry = array(
			'first_name' => $form['first_name'],
			'last_name'  => $form['last_name'],
			'phone'      => $phone['number'],
			'country'    => $phone['country'],
			'email'      => $form['email'],
			'message'    => $form['message'],
			'listing'    => self::valid_listing( $form['listing'] ),
			'page'       => self::valid_page( $form['page'] ),
			'note'       => self::captcha_note( $captcha ),
		);

		$post_id = Inquiries::add( $inquiry );
		$mailed  = Inquiries::mail( $inquiry, $post_id );

		if ( $post_id ) {
			update_post_meta( $post_id, Inquiries::META['mailed'], $mailed ? '1' : '0' );
		}

		if ( ! $post_id && ! $mailed ) {
			return self::result( false, $messages['failed'], 'failed' );
		}

		self::count_visit();

		return self::result( true, sprintf( $messages['sent'], $form['first_name'] ) );
	}

	/**
	 * A result for the browser.
	 *
	 * @param bool   $success Whether the inquiry was sent.
	 * @param string $message Message for the whole form.
	 * @param string $code    Why it wasn't sent.
	 * @param array  $fields  Field key => message.
	 * @return array
	 */
	private static function result( $success, $message, $code = '', array $fields = array() ) {
		return array(
			'success' => (bool) $success,
			'message' => $message,
			'code'    => $code,
			'fields'  => (object) $fields,
		);
	}

	/**
	 * Reads the submitted form.
	 *
	 * @param array $posted Submitted form, unslashed.
	 * @return array
	 */
	public static function read( array $posted ) {
		$text = function ( $key ) use ( $posted ) {
			return isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? trim( sanitize_text_field( (string) $posted[ $key ] ) ) : '';
		};

		$message = isset( $posted['crc_message'] ) && is_scalar( $posted['crc_message'] ) ? (string) $posted['crc_message'] : '';

		return array(
			'first_name' => $text( 'crc_first_name' ),
			'last_name'  => $text( 'crc_last_name' ),
			'country'    => strtoupper( $text( 'crc_country' ) ),
			'phone'      => $text( 'crc_phone' ),
			'email'      => $text( 'crc_email' ),
			// Line breaks count as one character, as they do in the browser.
			'message'    => trim( sanitize_textarea_field( str_replace( array( "\r\n", "\r" ), "\n", $message ) ) ),
			'listing'    => absint( $text( 'crc_listing' ) ),
			'page'       => $text( 'crc_page' ),
			'form'       => sanitize_html_class( $text( 'crc_form' ) ),
			'trap'       => $text( 'crc_website' ),
			'captcha'    => $text( 'h-captcha-response' ),
		);
	}

	/**
	 * Checks each field.
	 *
	 * @param array $form Form from read().
	 * @return string[] Field key => message, for the fields that aren't right.
	 */
	public static function check( array $form ) {
		$messages = self::messages();
		$errors   = array();

		foreach ( array( 'first_name', 'last_name' ) as $key ) {
			if ( '' === $form[ $key ] ) {
				if ( 'first_name' === $key ) {
					$errors[ $key ] = $messages['first_name'];
				}
			} elseif ( self::length( $form[ $key ] ) > self::NAME_LENGTH ) {
				$errors[ $key ] = sprintf( $messages['too_long'], number_format_i18n( self::NAME_LENGTH ) );
			} elseif ( ! self::is_name( $form[ $key ] ) ) {
				$errors[ $key ] = $messages[ 'first_name' === $key ? 'first_letters' : 'last_letters' ];
			}
		}

		$phone = Phone::parse( $form['country'], $form['phone'] );

		if ( isset( $phone['error'] ) ) {
			$errors['phone'] = 'empty' === $phone['error'] ? $messages['phone'] : self::phone_message( $phone['country'] );
		}

		$at = strrpos( $form['email'], '@' );

		if ( '' === $form['email'] ) {
			$errors['email'] = $messages['email'];
		} elseif ( strlen( $form['email'] ) > self::EMAIL_LENGTH || ! is_email( $form['email'] ) ) {
			$errors['email'] = $messages['email_invalid'];
		} elseif ( ! self::domain_exists( substr( $form['email'], $at + 1 ) ) ) {
			$errors['email'] = sprintf( $messages['email_domain'], substr( $form['email'], $at + 1 ) );
		}

		if ( self::length( $form['message'] ) > self::MESSAGE_LENGTH ) {
			$errors['message'] = sprintf( $messages['message_long'], number_format_i18n( self::MESSAGE_LENGTH ) );
		}

		return $errors;
	}

	/**
	 * The message for a phone number that isn't right for its country.
	 *
	 * @param string $country Country code.
	 * @return string
	 */
	public static function phone_message( $country ) {
		$countries = Phone::countries();
		$country   = isset( $countries[ $country ] ) ? $country : Phone::DEFAULT_COUNTRY;
		$examples  = self::examples();
		$messages  = self::messages();

		if ( ! empty( $examples[ $country ] ) ) {
			return sprintf( $messages['phone_example'], $countries[ $country ]['name'], $countries[ $country ]['dial'], $examples[ $country ] );
		}

		return sprintf( $messages['phone_invalid'], $countries[ $country ]['name'], $countries[ $country ]['dial'] );
	}

	/**
	 * Whether a name is written in letters (of any language), with spaces,
	 * dots, dashes or apostrophes between them.
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	public static function is_name( $name ) {
		return 1 === preg_match( '/^[\p{L}\p{M}][\p{L}\p{M}\x{200C}\x{200D}\'\x{2019}. \-]*$/u', (string) $name );
	}

	/**
	 * Whether an email domain can receive email: it has a mail server or
	 * an address. When the site can't look it up, it counts as yes.
	 *
	 * @param string $domain Part of the email address after @.
	 * @return bool
	 */
	public static function domain_exists( $domain ) {
		/**
		 * Filters whether an email domain exists, before it is looked up.
		 *
		 * @param bool|null $exists Null to look it up.
		 * @param string    $domain Domain.
		 */
		$exists = apply_filters( 'crc_re_email_domain_exists', null, $domain );

		if ( null !== $exists ) {
			return (bool) $exists;
		}

		if ( ! function_exists( 'checkdnsrr' ) ) {
			return true;
		}

		$host = rtrim( strtolower( (string) $domain ), '.' ) . '.';

		if ( checkdnsrr( $host, 'MX' ) || checkdnsrr( $host, 'A' ) || checkdnsrr( $host, 'AAAA' ) ) {
			return true;
		}

		// Nothing found. If a well-known name can't be found either, the
		// site can't look names up, and nobody should be turned away.
		return ! checkdnsrr( 'wordpress.org.', 'A' );
	}

	/**
	 * Asks hCaptcha whether the box was ticked.
	 *
	 * @param string $token The answer from the box.
	 * @return string "off" (not in use), "ok", "missing" (not ticked), "failed", or "unchecked" (hCaptcha couldn't be asked, or its keys aren't right; the inquiry goes through with a note).
	 */
	public static function verify_captcha( $token ) {
		if ( ! Settings::captcha_on() ) {
			return 'off';
		}

		if ( '' === (string) $token ) {
			return 'missing';
		}

		$response = wp_remote_post(
			self::CAPTCHA_CHECK,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => Settings::inquiry( 'secret_key' ),
					'response' => (string) $token,
					'sitekey'  => Settings::inquiry( 'site_key' ),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return 'unchecked';
		}

		$reply = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $reply ) ) {
			return 'unchecked';
		}

		if ( ! empty( $reply['success'] ) ) {
			if ( false !== get_option( self::CAPTCHA_ISSUE, false ) ) {
				delete_option( self::CAPTCHA_ISSUE );
			}

			return 'ok';
		}

		$codes = isset( $reply['error-codes'] ) ? array_map( 'strval', (array) $reply['error-codes'] ) : array();
		$setup = array_values( array_intersect( $codes, self::CAPTCHA_SETUP_ERRORS ) );

		// The keys aren't right: that's for the site owner to fix, not the visitor.
		if ( $setup ) {
			update_option(
				self::CAPTCHA_ISSUE,
				array(
					'code' => $setup[0],
					'time' => time(),
				),
				false
			);

			return 'unchecked';
		}

		return in_array( 'missing-input-response', $codes, true ) ? 'missing' : 'failed';
	}

	/**
	 * A note on the inquiry when hCaptcha couldn't check it.
	 *
	 * @param string $captcha Result of verify_captcha().
	 * @return string
	 */
	private static function captcha_note( $captcha ) {
		if ( 'unchecked' !== $captcha ) {
			return '';
		}

		return false !== get_option( self::CAPTCHA_ISSUE, false )
			? __( 'hCaptcha couldn\'t check this inquiry because its keys aren\'t right. Please copy both keys again into Listings → Settings.', 'crc-real-estate' )
			: __( 'hCaptcha couldn\'t be reached to check this inquiry, so it came through without the check.', 'crc-real-estate' );
	}

	/**
	 * The listing an inquiry is about, if it's a published listing.
	 *
	 * @param int $id Listing ID.
	 * @return int Listing ID, or 0.
	 */
	private static function valid_listing( $id ) {
		$post = $id ? get_post( (int) $id ) : null;

		return $post && Post_Type::NAME === $post->post_type && 'publish' === $post->post_status ? (int) $post->ID : 0;
	}

	/**
	 * A page address on this site.
	 *
	 * @param string $url Address.
	 * @return string The address, or an empty string when it isn't on this site.
	 */
	private static function valid_page( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );

		return '' !== $url && '' !== wp_validate_redirect( $url, '' ) ? $url : '';
	}

	/**
	 * The number of characters in a text.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/**
	 * A private name for the visitor, from their address on the internet,
	 * for counting their inquiries.
	 *
	 * @return string
	 */
	private static function visitor() {
		$ip = '';

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
			$value = isset( $_SERVER[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) : '';

			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				$ip = $value;
				break;
			}
		}

		return 'crc_re_inquiries_' . substr( wp_hash( $ip ), 0, 20 );
	}

	/**
	 * Whether the visitor has sent the most inquiries allowed for now.
	 *
	 * @return bool
	 */
	private static function too_many() {
		return (int) get_transient( self::visitor() ) >= self::LIMIT;
	}

	/**
	 * Counts an inquiry from the visitor.
	 */
	private static function count_visit() {
		$key = self::visitor();

		set_transient( $key, (int) get_transient( $key ) + 1, self::LIMIT_MINUTES * MINUTE_IN_SECONDS );
	}
}
