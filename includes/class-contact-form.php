<?php
/**
 * Contact form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_contact_form]: a contact form for any page, such as a Contact Us
 * page. First and last name, phone number with its country, email, an
 * optional subject and the message. Messages are emailed to the site and
 * kept in Contact Messages in wp-admin.
 */
final class Contact_Form extends Form {

	const TYPE           = 'contact';
	const SHORTCODE      = 'crc_contact_form';
	const ACTION         = 'crc_re_contact';
	const PREFIX         = 'crc-contact-form';
	const RESULT         = 'crc_contact';
	const LIMIT_KEY      = 'contact_messages';
	const STORE          = Contact_Messages::class;
	const SUBJECT_LENGTH = 150;

	/**
	 * Contact forms on the page so far, for unique field IDs.
	 *
	 * @var int
	 */
	protected static $count = 0;

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		Shortcodes::add(
			self::SHORTCODE,
			array( $this, 'render' ),
			array(
				'title'       => __( 'Contact form', 'crc-real-estate' ),
				'description' => __( 'A contact form for any page, such as your Contact Us page: First Name, Last Name, Phone Number with a country code (Sri Lanka unless changed), E-Mail, Subject and Your Message. First Name, Phone Number, E-Mail and Your Message must be filled in; Last Name and Subject can be left empty. Everything is checked while people type and again when the message arrives, with clear messages under each field, and the fields and labels take the site\'s own Elementor styles. Messages are emailed to the address in Listings → Settings, and replying to that email answers the person who wrote. They are also kept in Contact Messages, its own menu in wp-admin, where new ones are counted on the menu. When hCaptcha is turned on in Listings → Settings, its "I am human" box shows on this form too.', 'crc-real-estate' ),
				'attributes'  => array(
					'button'  => array(
						'default'     => __( 'Send Message', 'crc-real-estate' ),
						'description' => __( 'The text on the send button.', 'crc-real-estate' ),
					),
					'country' => array(
						'default'     => Phone::DEFAULT_COUNTRY,
						'description' => __( 'The country chosen at first for phone numbers, as its two-letter code: LK for Sri Lanka, GB for the United Kingdom, AU for Australia and so on. People can still choose any country.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' button="Send"]',
					'[' . self::SHORTCODE . ' country="GB"]',
				),
			)
		);
	}

	/**
	 * The form's fields.
	 *
	 * @return array[]
	 */
	public static function fields() {
		return array(
			'first_name' => array(
				'rule'        => 'name',
				'label'       => __( 'First Name', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::NAME_LENGTH,
				'placeholder' => __( 'e.g. Nimal', 'crc-real-estate' ),
				'attributes'  => ' autocomplete="given-name" autocapitalize="words"',
				'row'         => 'names',
				'messages'    => array(
					'empty'   => __( 'Please enter your first name.', 'crc-real-estate' ),
					'invalid' => __( 'Please use only letters in your first name.', 'crc-real-estate' ),
				),
			),
			'last_name'  => array(
				'rule'        => 'name',
				'label'       => __( 'Last Name', 'crc-real-estate' ),
				'max'         => self::NAME_LENGTH,
				'placeholder' => __( 'e.g. Perera', 'crc-real-estate' ),
				'attributes'  => ' autocomplete="family-name" autocapitalize="words"',
				'row'         => 'names',
				'messages'    => array( 'invalid' => __( 'Please use only letters in your last name.', 'crc-real-estate' ) ),
			),
			'phone'      => array(
				'rule'       => 'phone',
				'type'       => 'tel',
				'label'      => __( 'Phone Number', 'crc-real-estate' ),
				'required'   => true,
				'max'        => 24,
				'attributes' => ' autocomplete="tel" inputmode="tel"',
			),
			'email'      => array(
				'rule'        => 'email',
				'type'        => 'email',
				'label'       => __( 'E-Mail', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::EMAIL_LENGTH,
				'placeholder' => __( 'e.g. nimal.perera@gmail.com', 'crc-real-estate' ),
				'attributes'  => ' autocomplete="email" autocapitalize="off" spellcheck="false"',
				'hint'        => true,
			),
			'subject'    => array(
				'label'       => __( 'Subject', 'crc-real-estate' ),
				'max'         => self::SUBJECT_LENGTH,
				'placeholder' => __( 'e.g. Selling my land in Galle', 'crc-real-estate' ),
			),
			'message'    => array(
				'type'        => 'textarea',
				'label'       => __( 'Your Message', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::MESSAGE_LENGTH,
				'placeholder' => __( 'e.g. I\'d like to know more about selling my land with you.', 'crc-real-estate' ),
				'messages'    => array(
					'empty' => __( 'Please write your message.', 'crc-real-estate' ),
					/* translators: %s: the most characters allowed, e.g. 2,000. */
					'long'  => __( 'Please keep your message to %s characters or fewer.', 'crc-real-estate' ),
				),
			),
		);
	}

	/**
	 * The contact form's own messages. The browser shows the same ones.
	 *
	 * @return string[]
	 */
	public static function texts() {
		return array(
			'busy'       => __( 'You\'ve sent a few messages in a short time. Please wait a few minutes, then try again.', 'crc-real-estate' ),
			'failed'     => self::with_phone(
				/* translators: %s: phone number. */
				__( 'Sorry, your message couldn\'t be sent. Please try again in a moment, or call us on %s.', 'crc-real-estate' ),
				__( 'Sorry, your message couldn\'t be sent. Please try again in a moment.', 'crc-real-estate' )
			),
			'network'    => __( 'Your message couldn\'t be sent. Please check your internet connection and try again.', 'crc-real-estate' ),
			/* translators: %s: names of the fields to check, e.g. "Phone Number, E-Mail". */
			'fields'     => __( 'Your message wasn\'t sent. Please check these and try again: %s.', 'crc-real-estate' ),
			/* translators: %s: first name. */
			'sent'       => __( 'Thank you, %s! We\'ve got your message and will reply soon.', 'crc-real-estate' ),
			'sent_plain' => __( 'Thank you, we\'ve got your message and will reply soon.', 'crc-real-estate' ),
		);
	}

	/**
	 * Wording only the site uses.
	 *
	 * @return string[]
	 */
	protected static function notes() {
		return array(
			'button'            => __( 'Send Message', 'crc-real-estate' ),
			'noscript'          => __( 'Please turn on JavaScript in your browser to send a message.', 'crc-real-estate' ),
			'captcha_keys'      => __( 'hCaptcha couldn\'t check this message because its keys aren\'t right. Please copy both keys again into Listings → Settings.', 'crc-real-estate' ),
			'captcha_unreached' => __( 'hCaptcha couldn\'t be reached to check this message, so it came through without the check.', 'crc-real-estate' ),
		);
	}
}
