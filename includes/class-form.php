<?php
/**
 * What the plugin's front-end forms share.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's front-end forms: the listing inquiry form, the contact form
 * and the post a free ad form. Each one lists its fields; this builds them,
 * checks them as people type (with inquiry.js) and again when they arrive,
 * optionally with hCaptcha, then keeps what was sent in wp-admin and emails
 * it to the site.
 *
 * Pages with a form are cached, so the forms carry no security token (one
 * would go stale in the cache). A hidden trap field, a limit per visitor and
 * hCaptcha keep robots out instead.
 *
 * Each form sets these constants: TYPE (a short name, also used in the
 * browser), SHORTCODE, ACTION (the address the form is sent to), PREFIX (the
 * start of its HTML IDs), RESULT (the address part that says how a form sent
 * without JavaScript went), LIMIT_KEY (for counting what each visitor sends)
 * and STORE (the class that keeps and emails what was sent).
 */
abstract class Form {

	const TYPE           = '';
	const SHORTCODE      = '';
	const ACTION         = '';
	const PREFIX         = '';
	const RESULT         = '';
	const LIMIT_KEY      = '';
	const STORE          = '';
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
	 * Forms of this kind on the page so far, for unique field IDs. Each form
	 * declares its own, so each kind counts its own forms: the listing
	 * inquiry form stays #crc-inquiry-1 even after a contact form.
	 *
	 * @var int
	 */
	protected static $count = 0;

	/**
	 * Whether the browser settings were added to the page. One set serves
	 * every form on the page.
	 *
	 * @var bool
	 */
	protected static $localized = false;

	/**
	 * The forms in use: TYPE => class.
	 *
	 * @var string[]
	 */
	protected static $types = array();

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		self::$types[ static::TYPE ] = static::class;

		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 6 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_in_head' ) );
		add_action( 'wp_ajax_' . static::ACTION, array( $this, 'submit' ) );
		add_action( 'wp_ajax_nopriv_' . static::ACTION, array( $this, 'submit' ) );
	}

	/**
	 * Registers the shortcode.
	 */
	abstract public function register();

	/**
	 * The form's fields, in order. Each is key => array with:
	 * - rule:        how it is checked: "name", "phone", "email", "text", "amount" (a price in numbers), "count" (0 to 99) or "choice" (one of the options).
	 * - type:        "text", "email", "tel", "textarea" or "radio".
	 * - label:       its name on the form.
	 * - required:    whether it must be filled in.
	 * - max:         the most characters allowed.
	 * - placeholder: the example shown in the empty field.
	 * - attributes:  more attributes for the field, already escaped, e.g. ' autocomplete="email"'.
	 * - options:     for "radio": value => label.
	 * - suggestions: words offered while typing.
	 * - row:         fields with the same row sit side by side when there is room.
	 * - hint:        whether a "Did you mean …?" email hint can show under it.
	 * - messages:    'empty', 'invalid' and 'long' (with %s for the most characters), when the usual ones don't fit.
	 *
	 * @return array[]
	 */
	abstract public static function fields();

	/**
	 * What people see that is the form's own: 'busy', 'failed', 'network',
	 * 'fields', 'sent' (with %s for the first name) and 'sent_plain'. The
	 * browser shows the same ones.
	 *
	 * @return string[]
	 */
	abstract public static function texts();

	/**
	 * Wording only the site uses: 'button' (the send button's usual text),
	 * 'noscript' (when hCaptcha is on and the browser has no JavaScript),
	 * and the notes kept when hCaptcha couldn't check what was sent:
	 * 'captcha_keys' and 'captcha_unreached'.
	 *
	 * @return string[]
	 */
	abstract protected static function notes();

	/**
	 * Registers the front-end files, shared by every form.
	 */
	public function register_assets() {
		wp_register_style( 'crc-re-inquiry', CRC_RE_URL . 'assets/css/inquiry.css', array(), CRC_RE_VERSION );
		wp_register_script( 'crc-re-inquiry', CRC_RE_URL . 'assets/js/inquiry.js', array(), CRC_RE_VERSION, true );
	}

	/**
	 * Loads the form's styles in the page head on pages that have the form,
	 * so it never shows unstyled.
	 */
	public function enqueue_in_head() {
		if ( static::in_head() ) {
			wp_enqueue_style( 'crc-re-inquiry' );
		}
	}

	/**
	 * Whether the page being viewed has the form: its shortcode is in the
	 * page's text, or in what Elementor keeps for the page.
	 *
	 * @return bool
	 */
	protected static function in_head() {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post( get_queried_object_id() );

		if ( ! $post ) {
			return false;
		}

		$elementor = get_post_meta( $post->ID, '_elementor_data', true );
		$elementor = is_array( $elementor ) ? (string) wp_json_encode( $elementor ) : (string) $elementor;

		return static::has_shortcode( (string) $post->post_content ) || static::has_shortcode( $elementor );
	}

	/**
	 * Whether a text has the form's shortcode, with or without options. The
	 * tag must end there, so a longer shortcode with the same start doesn't
	 * count. In Elementor's data a "/" is written as "\/".
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	protected static function has_shortcode( $text ) {
		return '' !== $text && 1 === preg_match( '/\[' . preg_quote( static::SHORTCODE, '/' ) . '[\s\]\/\\\\]/', $text );
	}

	/**
	 * The fields with every detail filled in.
	 *
	 * @return array[]
	 */
	public static function form_fields() {
		/**
		 * Filters a form's fields, e.g. to change a label or an example on another site.
		 * The hook's name has the form's short name: crc_re_inquiry_fields, crc_re_contact_fields or crc_re_ad_fields.
		 *
		 * @param array[] $fields Key => field details, see Form::fields().
		 */
		$fields = (array) apply_filters( 'crc_re_' . static::TYPE . '_fields', static::fields() );

		foreach ( $fields as $key => $field ) {
			$fields[ $key ] = wp_parse_args(
				(array) $field,
				array(
					'rule'        => 'text',
					'type'        => 'text',
					'label'       => '',
					'required'    => false,
					'max'         => 0,
					'placeholder' => '',
					'attributes'  => '',
					'options'     => array(),
					'suggestions' => array(),
					'row'         => '',
					'hint'        => false,
					'rows'        => 5,
					'messages'    => array(),
				)
			);
		}

		return $fields;
	}

	/**
	 * The messages people see. The browser shows the same ones.
	 *
	 * @return string[]
	 */
	public static function messages() {
		return array_merge(
			array(
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
				'captcha'        => __( 'Please tick the "I am human" box.', 'crc-real-estate' ),
				'captcha_failed' => __( 'The "I am human" check didn\'t go through. Please tick the box again.', 'crc-real-estate' ),
				'captcha_load'   => __( 'The "I am human" box couldn\'t load. Please reload the page and try again.', 'crc-real-estate' ),
				/* translators: 1: country name, 2: its calling code, e.g. 94. */
				'country_button' => __( 'Country code: %1$s (+%2$s). Change the country', 'crc-real-estate' ),
				'country_search' => __( 'Search for a country', 'crc-real-estate' ),
				'country_none'   => __( 'No country found. Try another name or code.', 'crc-real-estate' ),
				'sending'        => __( 'Sending…', 'crc-real-estate' ),
			),
			static::texts()
		);
	}

	/**
	 * The usual message for a field that isn't right, by rule, when the
	 * field has none of its own.
	 *
	 * @param string $rule Field rule.
	 * @param string $kind "empty" or "invalid".
	 * @return string
	 */
	private static function usual_message( $rule, $kind ) {
		$messages = static::messages();

		if ( 'empty' === $kind ) {
			if ( 'phone' === $rule || 'email' === $rule ) {
				return $messages[ $rule ];
			}

			return 'choice' === $rule ? __( 'Please choose one.', 'crc-real-estate' ) : __( 'Please fill this in.', 'crc-real-estate' );
		}

		switch ( $rule ) {
			case 'name':
				return __( 'Please use only letters here.', 'crc-real-estate' );
			case 'email':
				return $messages['email_invalid'];
			case 'amount':
				return __( 'Please enter the amount in numbers only, for example 25,000,000.', 'crc-real-estate' );
			case 'count':
				return __( 'Please enter a number, for example 3.', 'crc-real-estate' );
			case 'choice':
				return __( 'Please choose one of these.', 'crc-real-estate' );
		}

		return '';
	}

	/**
	 * The message for a field that isn't right.
	 *
	 * @param array  $field Field details.
	 * @param string $kind  "empty", "invalid" or "long".
	 * @return string
	 */
	protected static function field_message( array $field, $kind ) {
		$own = isset( $field['messages'][ $kind ] ) ? (string) $field['messages'][ $kind ] : '';

		if ( 'long' === $kind ) {
			$messages = static::messages();

			return $field['max'] ? sprintf( '' !== $own ? $own : $messages['too_long'], number_format_i18n( (int) $field['max'] ) ) : '';
		}

		return '' !== $own ? $own : self::usual_message( $field['rule'], $kind );
	}

	/**
	 * The field labels, for messages that name the fields to check.
	 *
	 * @return string[]
	 */
	public static function labels() {
		$labels = array();

		foreach ( static::form_fields() as $key => $field ) {
			$labels[ $key ] = $field['label'];
		}

		$labels['captcha'] = __( 'I am human', 'crc-real-estate' );

		return $labels;
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
	 * What shows in the empty phone box.
	 *
	 * @return string[] "phone" gets the chosen country's example number in place of %s; "phone_other" is for countries without one.
	 */
	public static function placeholders() {
		/**
		 * Filters what shows in a form's empty phone box. The hook's name has
		 * the form's short name: crc_re_contact_placeholders or crc_re_ad_placeholders.
		 *
		 * @param string[] $placeholders "phone" gets the chosen country's example number in place of %s; "phone_other" is for countries without one.
		 */
		return (array) apply_filters(
			'crc_re_' . static::TYPE . '_placeholders',
			array(
				/* translators: %s: example phone number, e.g. 077 123 4567. */
				'phone'       => __( 'e.g. %s', 'crc-real-estate' ),
				'phone_other' => __( 'Type your phone number here', 'crc-real-estate' ),
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
		$examples     = static::examples();
		$placeholders = static::placeholders();

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
	 * A message for when something couldn't be sent, with the site's phone
	 * number when there is one.
	 *
	 * @param string $with    Message with %s for the phone number.
	 * @param string $without Message without it.
	 * @return string
	 */
	protected static function with_phone( $with, $without ) {
		$phone = Settings::get( 'phone' );

		return '' !== $phone ? sprintf( $with, $phone ) : $without;
	}

	/**
	 * Renders the form's shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts      = Shortcodes::atts( static::SHORTCODE, $atts );
		$countries = Phone::countries();
		$country   = strtoupper( trim( (string) $atts['country'] ) );
		$country   = isset( $countries[ $country ] ) ? $country : Phone::DEFAULT_COUNTRY;
		$notes     = static::notes();
		$button    = trim( (string) $atts['button'] );
		$button    = '' !== $button ? $button : $notes['button'];
		$id        = static::PREFIX . '-' . ( ++static::$count );

		$this->enqueue();

		$html  = sprintf( '<form class="%1$s" id="%2$s" action="%3$s" method="post" data-crc-inquiry%4$s>', esc_attr( static::form_class() ), esc_attr( $id ), esc_url( admin_url( 'admin-ajax.php' ) ), static::form_data() );
		$html .= '<input type="hidden" name="action" value="' . esc_attr( static::ACTION ) . '">';
		$html .= '<input type="hidden" name="crc_form" value="' . esc_attr( $id ) . '">';

		foreach ( static::hidden( $atts ) as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . $value . '">';
		}

		// People never see this field; robots fill it in.
		$html .= '<div class="crc-inquiry-trap" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'crc-real-estate' ) . ' <input type="text" name="crc_website" value="" tabindex="-1" autocomplete="off"></label></div>';

		$row = '';

		foreach ( static::form_fields() as $key => $field ) {
			if ( $field['row'] !== $row ) {
				$html .= '' !== $row ? '</div>' : '';
				$html .= '' !== $field['row'] ? '<div class="' . esc_attr( static::row_class( $field['row'] ) ) . '">' : '';
				$row   = $field['row'];
			}

			$html .= static::field( $id, $key, $field, $countries, $country );
		}

		$html .= '' !== $row ? '</div>' : '';
		$html .= static::after_fields();

		if ( Settings::captcha_on() ) {
			$html .= sprintf(
				'<div class="crc-inquiry-field crc-inquiry-captcha-field" data-crc-field="captcha"><div class="crc-inquiry-captcha" id="%1$s-captcha"></div><p class="crc-inquiry-error" id="%1$s-captcha-error" role="alert" hidden></p></div>',
				esc_attr( $id )
			);
			$html .= '<noscript><p class="crc-inquiry-status is-error">' . esc_html( $notes['noscript'] ) . '</p></noscript>';
		}

		// The site's own button style (custom-btn-3), full width.
		$html .= sprintf(
			'<div class="custom-btn-3 crc-inquiry-submit"><button class="elementor-button crc-inquiry-button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">%1$s</span><span class="elementor-button-icon">%2$s</span></span></button></div>',
			esc_html( $button ),
			Icons::svg( 'arrow-right', 'crc-inquiry-button-icon' )
		);

		$html .= static::notice( $id );
		$html .= '</form>';

		return $html;
	}

	/**
	 * The form's classes: the shared crc-inquiry, and its own.
	 *
	 * @return string
	 */
	protected static function form_class() {
		return 'crc-inquiry ' . static::PREFIX;
	}

	/**
	 * More attributes for the form, already escaped: which form it is, for
	 * the browser.
	 *
	 * @return string
	 */
	protected static function form_data() {
		return ' data-crc-form="' . esc_attr( static::TYPE ) . '"';
	}

	/**
	 * Hidden fields sent with the form: name => value, already escaped.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string[]
	 */
	protected static function hidden( array $atts ) {
		return array( 'crc_page' => esc_url( static::page_url() ) );
	}

	/**
	 * The page the form is on, sent with the form.
	 *
	 * @return string
	 */
	protected static function page_url() {
		return is_singular() ? (string) get_permalink( get_queried_object_id() ) : home_url( '/' );
	}

	/**
	 * The classes of a row of fields that sit side by side.
	 *
	 * @param string $row Row name.
	 * @return string
	 */
	protected static function row_class( $row ) {
		return 'names' === $row ? 'crc-inquiry-names' : 'crc-inquiry-row crc-inquiry-' . sanitize_html_class( $row );
	}

	/**
	 * What comes after the fields, before hCaptcha and the button.
	 *
	 * @return string
	 */
	protected static function after_fields() {
		return '';
	}

	/**
	 * A field with its label and error message.
	 *
	 * @param string  $id        Form ID.
	 * @param string  $key       Field key.
	 * @param array   $field     Field details.
	 * @param array[] $countries Countries, for a phone field.
	 * @param string  $country   Country chosen at first, for a phone field.
	 * @return string
	 */
	protected static function field( $id, $key, array $field, array $countries, $country ) {
		$field_id = $id . '-' . str_replace( '_', '-', $key );
		$required = $field['required'] ? '<span class="crc-inquiry-required" aria-hidden="true">&nbsp;*</span>' : '';

		if ( 'radio' === $field['type'] ) {
			return sprintf(
				'<div class="crc-inquiry-field" data-crc-field="%1$s"%2$s><fieldset class="crc-inquiry-choices" aria-describedby="%3$s-error"><legend class="crc-inquiry-legend"><label class="crc-inquiry-label">%4$s%5$s</label></legend><span class="crc-inquiry-options">%6$s</span></fieldset><p class="crc-inquiry-error" id="%3$s-error" hidden></p></div>',
				esc_attr( $key ),
				static::rules( $field ),
				esc_attr( $field_id ),
				esc_html( $field['label'] ),
				$required,
				static::choices( $key, $field )
			);
		}

		return sprintf(
			'<div class="crc-inquiry-field" data-crc-field="%1$s"%7$s><label class="crc-inquiry-label" for="%2$s">%3$s%4$s</label>%5$s<p class="crc-inquiry-error" id="%2$s-error" hidden></p>%6$s</div>',
			esc_attr( $key ),
			esc_attr( $field_id ),
			esc_html( $field['label'] ),
			$required,
			static::control( $id, $key, $field, $countries, $country ),
			$field['hint'] ? '<p class="crc-inquiry-hint" id="' . esc_attr( $field_id ) . '-hint" hidden></p>' : '',
			static::rules( $field )
		);
	}

	/**
	 * How the browser checks a field, as attributes for the field's box,
	 * already escaped. The messages are the ones the site shows.
	 *
	 * @param array $field Field details.
	 * @return string
	 */
	protected static function rules( array $field ) {
		$rule  = $field['rule'];
		$kinds = $field['required'] ? array( 'empty' ) : array();
		$html  = ' data-crc-rule="' . esc_attr( $rule ) . '"';
		$html .= $field['required'] ? ' data-crc-required' : '';
		$html .= $field['max'] ? ' data-crc-max="' . (int) $field['max'] . '"' : '';

		// Names, e-mails, amounts, numbers and choices can be wrong; names and texts can be too long.
		if ( in_array( $rule, array( 'name', 'email', 'amount', 'count', 'choice' ), true ) ) {
			$kinds[] = 'invalid';
		}

		if ( in_array( $rule, array( 'name', 'text' ), true ) ) {
			$kinds[] = 'long';
		}

		foreach ( $kinds as $kind ) {
			$message = static::field_message( $field, $kind );

			if ( '' !== $message ) {
				$html .= ' data-crc-' . $kind . '="' . esc_attr( $message ) . '"';
			}
		}

		return $html;
	}

	/**
	 * A field's box to type in.
	 *
	 * @param string  $id        Form ID.
	 * @param string  $key       Field key.
	 * @param array   $field     Field details.
	 * @param array[] $countries Countries, for a phone field.
	 * @param string  $country   Country chosen at first, for a phone field.
	 * @return string
	 */
	protected static function control( $id, $key, array $field, array $countries, $country ) {
		$field_id   = $id . '-' . str_replace( '_', '-', $key );
		$attributes = (string) $field['attributes'];
		$list       = '';

		if ( $field['suggestions'] ) {
			$attributes .= ' list="' . esc_attr( $field_id ) . '-list"';

			foreach ( $field['suggestions'] as $suggestion ) {
				$list .= '<option value="' . esc_attr( $suggestion ) . '"></option>';
			}

			$list = '<datalist id="' . esc_attr( $field_id ) . '-list">' . $list . '</datalist>';
		}

		$attributes .= $field['max'] && 'textarea' !== $field['type'] ? ' maxlength="' . (int) $field['max'] . '"' : '';
		$attributes .= $field['required'] ? ' required' : '';

		if ( 'phone' === $field['rule'] ) {
			return static::phone_control( $id, $countries, $country, static::input( $id, $key, 'tel', static::phone_placeholder( $country ), $attributes, false, 'crc-inquiry-input crc-inquiry-number' ) );
		}

		if ( 'textarea' === $field['type'] ) {
			return sprintf(
				'<textarea class="crc-inquiry-input crc-inquiry-message" id="%1$s" name="crc_%2$s" rows="%3$d"%4$s placeholder="%5$s" aria-describedby="%1$s-error"%6$s></textarea>',
				esc_attr( $field_id ),
				esc_attr( $key ),
				(int) $field['rows'],
				$field['max'] ? ' maxlength="' . (int) $field['max'] . '"' : '',
				esc_attr( $field['placeholder'] ),
				$attributes
			);
		}

		return static::input( $id, $key, $field['type'], $field['placeholder'], $attributes, $field['hint'] ) . $list;
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
	 * @param string $class       Classes.
	 * @return string
	 */
	protected static function input( $id, $key, $type, $placeholder, $attributes, $hint = false, $class = 'crc-inquiry-input' ) {
		$field_id = $id . '-' . str_replace( '_', '-', $key );

		return sprintf(
			'<input class="%7$s" id="%1$s" name="crc_%2$s" type="%3$s" placeholder="%4$s" aria-describedby="%5$s"%6$s>',
			esc_attr( $field_id ),
			esc_attr( $key ),
			esc_attr( $type ),
			esc_attr( $placeholder ),
			esc_attr( $field_id . '-error' . ( $hint ? ' ' . $field_id . '-hint' : '' ) ),
			$attributes,
			esc_attr( $class )
		);
	}

	/**
	 * Round buttons to choose one option. They stay the browser's own, so
	 * they don't take the text fields' styles.
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field details.
	 * @return string
	 */
	protected static function choices( $key, array $field ) {
		$html = '';

		foreach ( $field['options'] as $value => $text ) {
			$html .= sprintf(
				'<label class="crc-inquiry-choice"><input class="crc-inquiry-radio" type="radio" name="crc_%1$s" value="%2$s"%3$s><span class="crc-inquiry-choice-text">%4$s</span></label>',
				esc_attr( $key ),
				esc_attr( $value ),
				$field['required'] ? ' required' : '',
				esc_html( $text )
			);
		}

		return $html;
	}

	/**
	 * The phone number box: the country's code, which opens a list of every
	 * country, and the number.
	 *
	 * @param string  $id        Form ID.
	 * @param array[] $countries Countries.
	 * @param string  $country   Country chosen at first.
	 * @param string  $number    The number input's HTML.
	 * @return string
	 */
	protected static function phone_control( $id, array $countries, $country, $number ) {
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
			esc_url( static::flag( $country ) ),
			esc_html( $countries[ $country ]['dial'] ),
			Icons::svg( 'chevron-down', 'crc-inquiry-country-icon' ),
			esc_attr( $id ),
			esc_attr__( 'Country code of the phone number', 'crc-real-estate' ),
			$options,
			$number
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
	 * How a form sent without JavaScript went, after the page comes back.
	 * It shows only in forms of the same kind, and only in the form that
	 * was sent when the address says which one.
	 *
	 * @param string $id Form ID.
	 * @return string
	 */
	protected static function notice( $id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only shows a fixed message.
		$result = isset( $_GET[ static::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ static::RESULT ] ) ) : '';
		$code   = isset( $_GET['crc_error'] ) ? sanitize_key( wp_unslash( $_GET['crc_error'] ) ) : '';
		$fields = isset( $_GET['crc_fields'] ) ? sanitize_text_field( wp_unslash( $_GET['crc_fields'] ) ) : '';
		$form   = isset( $_GET['crc_form'] ) && is_scalar( $_GET['crc_form'] ) ? sanitize_html_class( wp_unslash( $_GET['crc_form'] ) ) : '';
		// phpcs:enable

		$messages = static::messages();

		if ( '' !== $form && $form !== $id ) {
			$result = '';
		}

		if ( 'sent' === $result ) {
			return '<div class="crc-inquiry-status is-success" role="status">' . esc_html( $messages['sent_plain'] ) . '</div>';
		}

		if ( 'failed' === $result ) {
			if ( 'fields' === $code ) {
				$names = array_intersect_key( static::labels(), array_flip( explode( ',', $fields ) ) );
				$text  = sprintf( $messages['fields'], implode( ', ', $names ? $names : array( __( 'your details', 'crc-real-estate' ) ) ) );
			} else {
				$text = in_array( $code, array( 'busy', 'captcha' ), true ) ? $messages[ $code ] : $messages['failed'];
			}

			return '<div class="crc-inquiry-status is-error" role="status">' . esc_html( $text ) . '</div>';
		}

		return '<div class="crc-inquiry-status" role="status" aria-live="polite" hidden></div>';
	}

	/**
	 * Loads the form's files and settings. One set of browser settings
	 * serves every form on the page, with each form's own wording.
	 */
	protected function enqueue() {
		if ( ! wp_style_is( 'crc-re-inquiry', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'crc-re-inquiry' );
		wp_enqueue_script( 'crc-re-inquiry' );

		if ( self::$localized ) {
			return;
		}

		self::$localized = true;

		// Each form's own wording, with what shows in its empty phone box.
		$forms = array();
		$phone = array(
			'phone'       => 1,
			'phone_other' => 1,
		);

		foreach ( self::$types as $type => $class ) {
			$forms[ $type ]                 = $class::texts();
			$forms[ $type ]['placeholders'] = array_intersect_key( (array) $class::placeholders(), $phone );
		}

		$forms[ static::TYPE ]                 = static::texts();
		$forms[ static::TYPE ]['placeholders'] = array_intersect_key( (array) static::placeholders(), $phone );

		wp_localize_script(
			'crc-re-inquiry',
			'crcReInquiry',
			array(
				'countries'    => Phone::script_data(),
				'main'         => Phone::MAIN,
				'examples'     => static::examples(),
				'placeholders' => static::placeholders(),
				'flags'        => static::flags_url(),
				'domains'      => static::email_domains(),
				'labels'       => static::labels(),
				'messages'     => static::messages(),
				'forms'        => $forms,
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
	 * Receives a form. Sent by the browser, it answers with the result;
	 * sent without JavaScript, it goes back to the page with the result.
	 */
	public function submit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cached public form, see the class description.
		$posted = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$result = $this->process( $posted );

		if ( ! empty( $posted['crc_js'] ) ) {
			wp_send_json( $result );
		}

		$form = static::read( $posted );
		$page = static::valid_page( $form['page'] );
		$page = '' !== $page ? $page : static::valid_page( (string) wp_get_referer() );
		$page = '' !== $page ? $page : home_url( '/' );
		$args = array( static::RESULT => $result['success'] ? 'sent' : 'failed' );

		if ( ! $result['success'] ) {
			$args['crc_error'] = $result['code'];

			if ( 'fields' === $result['code'] ) {
				$args['crc_fields'] = implode( ',', array_keys( (array) $result['fields'] ) );
			}
		}

		$args = array_merge( $args, static::back_args( $form ) );
		$page = remove_query_arg( array_merge( static::result_keys(), array( 'crc_error', 'crc_fields', 'crc_form' ) ), $page );

		wp_safe_redirect( add_query_arg( $args, $page ) . '#' . ( '' !== $form['form'] ? $form['form'] : static::PREFIX . '-1' ) );
		exit;
	}

	/**
	 * More address parts for the page the form goes back to: which form was
	 * sent, so only that form shows the result.
	 *
	 * @param array $form Form from read().
	 * @return string[]
	 */
	protected static function back_args( array $form ) {
		return '' !== $form['form'] ? array( 'crc_form' => $form['form'] ) : array();
	}

	/**
	 * The address parts every form uses for its result.
	 *
	 * @return string[]
	 */
	protected static function result_keys() {
		$keys = array( static::RESULT );

		foreach ( self::$types as $class ) {
			$keys[] = $class::RESULT;
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Checks a form and, when it's all right, keeps it and emails it.
	 *
	 * @param array $posted Submitted form, unslashed.
	 * @return array 'success', 'message', 'code' ("fields", "busy", "captcha" or "failed" when it wasn't sent) and 'fields' (field key => message).
	 */
	public function process( array $posted ) {
		$form     = static::read( $posted );
		$messages = static::messages();

		// Robots fill in the trap field. They are told it worked, and nothing is sent.
		if ( '' !== $form['trap'] ) {
			return self::result( true, static::thanks( $form ) );
		}

		$fields = static::check( $form );

		if ( $fields ) {
			return self::result( false, '', 'fields', $fields );
		}

		if ( static::too_many() ) {
			return self::result( false, $messages['busy'], 'busy' );
		}

		$captcha = static::verify_captcha( $form['captcha'] );

		if ( in_array( $captcha, array( 'missing', 'failed' ), true ) ) {
			$message = 'missing' === $captcha ? $messages['captcha'] : $messages['captcha_failed'];

			return self::result( false, '', 'captcha', array( 'captcha' => $message ) );
		}

		$data    = static::data( $form, static::captcha_note( $captcha ) );
		$store   = static::STORE;
		$meta    = $store::META;
		$post_id = $store::add( $data );
		$mailed  = $store::mail( $data, $post_id );

		if ( $post_id ) {
			update_post_meta( $post_id, $meta['mailed'], $mailed ? '1' : '0' );
		}

		if ( ! $post_id && ! $mailed ) {
			return self::result( false, $messages['failed'], 'failed' );
		}

		static::count_visit();

		return self::result( true, static::thanks( $form ) );
	}

	/**
	 * The thank-you message, by name when there is one.
	 *
	 * @param array $form Form from read().
	 * @return string
	 */
	protected static function thanks( array $form ) {
		$messages = static::messages();
		$name     = static::greeting( $form );

		return '' !== $name ? sprintf( $messages['sent'], $name ) : $messages['sent_plain'];
	}

	/**
	 * The name to thank people by: their first name, or their whole name
	 * when the form asks for it in one box (its first word may be an initial
	 * or a title, such as "W." or "Mr.").
	 *
	 * @param array $form Form from read().
	 * @return string
	 */
	protected static function greeting( array $form ) {
		if ( isset( $form['first_name'] ) ) {
			return (string) $form['first_name'];
		}

		return isset( $form['name'] ) ? trim( (string) $form['name'] ) : '';
	}

	/**
	 * A result for the browser.
	 *
	 * @param bool   $success Whether the form was sent.
	 * @param string $message Message for the whole form.
	 * @param string $code    Why it wasn't sent.
	 * @param array  $fields  Field key => message.
	 * @return array
	 */
	protected static function result( $success, $message, $code = '', array $fields = array() ) {
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
	 * @return array Field key => value, plus 'country', 'page', 'form', 'trap' and 'captcha'.
	 */
	public static function read( array $posted ) {
		$text = function ( $key ) use ( $posted ) {
			return isset( $posted[ $key ] ) && is_scalar( $posted[ $key ] ) ? trim( sanitize_text_field( (string) $posted[ $key ] ) ) : '';
		};
		$form = array();

		foreach ( static::form_fields() as $key => $field ) {
			if ( 'textarea' === $field['type'] ) {
				$value = isset( $posted[ 'crc_' . $key ] ) && is_scalar( $posted[ 'crc_' . $key ] ) ? (string) $posted[ 'crc_' . $key ] : '';

				// Line breaks count as one character, as they do in the browser.
				$form[ $key ] = trim( sanitize_textarea_field( str_replace( array( "\r\n", "\r" ), "\n", $value ) ) );
			} else {
				$form[ $key ] = $text( 'crc_' . $key );

				// Spaces such as non-breaking ones count as plain spaces in numbers, as they do in the browser.
				if ( in_array( $field['rule'], array( 'amount', 'count' ), true ) ) {
					$form[ $key ] = trim( (string) preg_replace( '/\s+/u', ' ', $form[ $key ] ) );
				}
			}
		}

		return array_merge(
			$form,
			array(
				'country' => strtoupper( $text( 'crc_country' ) ),
				'page'    => $text( 'crc_page' ),
				'form'    => sanitize_html_class( $text( 'crc_form' ) ),
				'trap'    => $text( 'crc_website' ),
				'captcha' => $text( 'h-captcha-response' ),
			)
		);
	}

	/**
	 * Checks each field.
	 *
	 * @param array $form Form from read().
	 * @return string[] Field key => message, for the fields that aren't right.
	 */
	public static function check( array $form ) {
		$messages = static::messages();
		$errors   = array();

		foreach ( static::form_fields() as $key => $field ) {
			$value = isset( $form[ $key ] ) ? (string) $form[ $key ] : '';
			$rule  = $field['rule'];

			if ( 'phone' === $rule ) {
				$phone = Phone::parse( $form['country'], $value );

				if ( isset( $phone['error'] ) && ( 'empty' !== $phone['error'] || $field['required'] ) ) {
					$errors[ $key ] = 'empty' === $phone['error'] ? static::field_message( $field, 'empty' ) : static::phone_message( $phone['country'] );
				}

				continue;
			}

			if ( '' === $value ) {
				if ( $field['required'] ) {
					$errors[ $key ] = static::field_message( $field, 'empty' );
				}

				continue;
			}

			$long = $field['max'] && self::length( $value ) > (int) $field['max'];

			switch ( $rule ) {
				case 'email':
					$at = strrpos( $value, '@' );

					if ( strlen( $value ) > ( $field['max'] ? (int) $field['max'] : self::EMAIL_LENGTH ) || ! is_email( $value ) ) {
						$errors[ $key ] = static::field_message( $field, 'invalid' );
					} elseif ( ! static::domain_exists( substr( $value, $at + 1 ) ) ) {
						$errors[ $key ] = sprintf( $messages['email_domain'], substr( $value, $at + 1 ) );
					}
					break;

				case 'choice':
					if ( ! array_key_exists( $value, (array) $field['options'] ) ) {
						$errors[ $key ] = static::field_message( $field, 'invalid' );
					}
					break;

				case 'amount':
					if ( $long || ! preg_match( '/^[\d,\s]+(\.\d+)?$/', $value ) || '' === Price_Card::sanitize_amount( $value ) ) {
						$errors[ $key ] = static::field_message( $field, 'invalid' );
					}
					break;

				case 'count':
					if ( ! preg_match( '/^\d{1,2}$/', $value ) ) {
						$errors[ $key ] = static::field_message( $field, 'invalid' );
					}
					break;

				default:
					if ( $long ) {
						$errors[ $key ] = static::field_message( $field, 'long' );
					} elseif ( 'name' === $rule && ! static::is_name( $value ) ) {
						$errors[ $key ] = static::field_message( $field, 'invalid' );
					}
			}
		}

		return $errors;
	}

	/**
	 * What is kept and emailed, from a checked form: each field (a phone
	 * number in full international form with its country, an amount as
	 * digits), the page it came from and a note.
	 *
	 * @param array  $form Checked form from read().
	 * @param string $note Note about the check, or an empty string.
	 * @return array
	 */
	protected static function data( array $form, $note ) {
		$data = array();

		foreach ( static::form_fields() as $key => $field ) {
			$value = isset( $form[ $key ] ) ? (string) $form[ $key ] : '';

			if ( 'phone' === $field['rule'] ) {
				$phone           = Phone::parse( $form['country'], $value );
				$data[ $key ]    = isset( $phone['number'] ) ? $phone['number'] : '';
				$data['country'] = isset( $phone['number'] ) ? $phone['country'] : '';
			} elseif ( 'amount' === $field['rule'] ) {
				$data[ $key ] = Price_Card::sanitize_amount( $value );
			} elseif ( 'count' === $field['rule'] && '' !== $value ) {
				$data[ $key ] = (string) (int) $value;
			} else {
				$data[ $key ] = $value;
			}
		}

		$data['page'] = static::valid_page( $form['page'] );
		$data['note'] = $note;

		return $data;
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
		$examples  = static::examples();
		$messages  = static::messages();

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
	 * @return string "off" (not in use), "ok", "missing" (not ticked), "failed", or "unchecked" (hCaptcha couldn't be asked, or its keys aren't right; the form goes through with a note).
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
	 * A note kept with what was sent when hCaptcha couldn't check it.
	 *
	 * @param string $captcha Result of verify_captcha().
	 * @return string
	 */
	protected static function captcha_note( $captcha ) {
		if ( 'unchecked' !== $captcha ) {
			return '';
		}

		$notes = static::notes();

		return false !== get_option( self::CAPTCHA_ISSUE, false ) ? $notes['captcha_keys'] : $notes['captcha_unreached'];
	}

	/**
	 * A page address on this site.
	 *
	 * @param string $url Address.
	 * @return string The address, or an empty string when it isn't on this site.
	 */
	protected static function valid_page( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );

		return '' !== $url && '' !== wp_validate_redirect( $url, '' ) ? $url : '';
	}

	/**
	 * The number of characters in a text.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	protected static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/**
	 * A private name for the visitor, from their address on the internet,
	 * for counting what they send with this form.
	 *
	 * @return string
	 */
	protected static function visitor() {
		$ip = '';

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $key ) {
			$value = isset( $_SERVER[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) : '';

			if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
				$ip = $value;
				break;
			}
		}

		return 'crc_re_' . static::LIMIT_KEY . '_' . substr( wp_hash( $ip ), 0, 20 );
	}

	/**
	 * Whether the visitor has sent this form the most times allowed for now.
	 *
	 * @return bool
	 */
	protected static function too_many() {
		return (int) get_transient( static::visitor() ) >= static::LIMIT;
	}

	/**
	 * Counts a form sent by the visitor.
	 */
	protected static function count_visit() {
		$key = static::visitor();

		set_transient( $key, (int) get_transient( $key ) + 1, static::LIMIT_MINUTES * MINUTE_IN_SECONDS );
	}
}
