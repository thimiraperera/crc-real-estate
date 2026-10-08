<?php
/**
 * Inquiry form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Sections;

use CRC\RealEstate\Form;
use CRC\RealEstate\Inquiries;
use CRC\RealEstate\Listing_Status;
use CRC\RealEstate\Phone;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * The "Send an inquiry" form: first and last name, phone number with its
 * country, email and message. It is checked in the browser as people type,
 * and again when it arrives, optionally with hCaptcha. Then it is emailed
 * and kept in Inquiries in wp-admin.
 *
 * Its markup, messages and addresses stay as they were before the contact
 * and free ad forms came, so the site's own styles and links to
 * #crc-inquiry-1 keep working. The browser checks of its fields are built
 * into inquiry.js.
 */
final class Inquiry extends Form {

	const TYPE      = 'inquiry';
	const SHORTCODE = 'crc_listing_inquiry';
	const ACTION    = 'crc_re_inquiry';
	const PREFIX    = 'crc-inquiry';
	const RESULT    = 'crc_inquiry';
	const LIMIT_KEY = 'inquiries';
	const STORE     = Inquiries::class;

	/**
	 * Inquiry forms on the page so far, for unique field IDs.
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
				'title'       => __( 'Inquiry form', 'crc-real-estate' ),
				'description' => __( 'The "Send an inquiry" form: First Name, Last Name, Phone Number with a country code (Sri Lanka unless changed), E-Mail and Your Message. First Name, Phone Number and E-Mail must be filled in. Everything is checked while people type and again when the inquiry arrives, with clear messages under each field. The fields and labels take the site\'s own Elementor styles. On a listing page the inquiry says which listing it is about; on any other page it is a general inquiry. Inquiries are emailed to the address in Listings → Settings and kept in Inquiries, its own menu in wp-admin, where new ones are counted on the menu. Turn on hCaptcha in Listings → Settings to stop spam robots.', 'crc-real-estate' ),
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
	 * Whether the form's styles load in the page head, so it never shows
	 * unstyled: on listing pages, and on other pages that have the form.
	 *
	 * @return bool
	 */
	protected static function in_head() {
		return is_singular( Post_Type::NAME ) || parent::in_head();
	}

	/**
	 * The form's fields.
	 *
	 * @return array[]
	 */
	public static function fields() {
		$texts    = self::texts();
		$examples = self::placeholders();

		return array(
			'first_name' => array(
				'rule'        => 'name',
				'label'       => __( 'First Name', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::NAME_LENGTH,
				'placeholder' => $examples['first_name'],
				'attributes'  => ' autocomplete="given-name" autocapitalize="words"',
				'row'         => 'names',
				'messages'    => array(
					'empty'   => $texts['first_name'],
					'invalid' => $texts['first_letters'],
				),
			),
			'last_name'  => array(
				'rule'        => 'name',
				'label'       => __( 'Last Name', 'crc-real-estate' ),
				'max'         => self::NAME_LENGTH,
				'placeholder' => $examples['last_name'],
				'attributes'  => ' autocomplete="family-name" autocapitalize="words"',
				'row'         => 'names',
				'messages'    => array( 'invalid' => $texts['last_letters'] ),
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
				'placeholder' => $examples['email'],
				'attributes'  => ' autocomplete="email" autocapitalize="off" spellcheck="false"',
				'hint'        => true,
			),
			'message'    => array(
				'type'        => 'textarea',
				'label'       => __( 'Your Message', 'crc-real-estate' ),
				'max'         => self::MESSAGE_LENGTH,
				'placeholder' => $examples['message'],
				'messages'    => array( 'long' => $texts['message_long'] ),
			),
		);
	}

	/**
	 * The inquiry form's own messages. The browser shows the same ones.
	 *
	 * @return string[]
	 */
	public static function texts() {
		return array(
			'first_name'    => __( 'Please enter your first name.', 'crc-real-estate' ),
			'first_letters' => __( 'Please use only letters in your first name.', 'crc-real-estate' ),
			'last_letters'  => __( 'Please use only letters in your last name.', 'crc-real-estate' ),
			/* translators: %s: the most characters allowed, e.g. 2,000. */
			'message_long'  => __( 'Please keep your message to %s characters or fewer.', 'crc-real-estate' ),
			'busy'          => __( 'You\'ve sent a few inquiries in a short time. Please wait a few minutes, then try again.', 'crc-real-estate' ),
			'failed'        => self::with_phone(
				/* translators: %s: phone number. */
				__( 'Sorry, your inquiry couldn\'t be sent. Please try again in a moment, or call us on %s.', 'crc-real-estate' ),
				__( 'Sorry, your inquiry couldn\'t be sent. Please try again in a moment.', 'crc-real-estate' )
			),
			'network'       => __( 'Your inquiry couldn\'t be sent. Please check your internet connection and try again.', 'crc-real-estate' ),
			/* translators: %s: names of the fields to check, e.g. "Phone Number, E-Mail". */
			'fields'        => __( 'Your inquiry wasn\'t sent. Please check these and try again: %s.', 'crc-real-estate' ),
			/* translators: %s: first name. */
			'sent'          => __( 'Thank you, %s! Your inquiry has been sent. We\'ll get back to you soon.', 'crc-real-estate' ),
			'sent_plain'    => __( 'Thank you! Your inquiry has been sent. We\'ll get back to you soon.', 'crc-real-estate' ),
		);
	}

	/**
	 * Wording only the site uses.
	 *
	 * @return string[]
	 */
	protected static function notes() {
		return array(
			'button'            => __( 'Send Inquiry', 'crc-real-estate' ),
			'noscript'          => __( 'Please turn on JavaScript in your browser to send an inquiry.', 'crc-real-estate' ),
			'captcha_keys'      => __( 'hCaptcha couldn\'t check this inquiry because its keys aren\'t right. Please copy both keys again into Listings → Settings.', 'crc-real-estate' ),
			'captcha_unreached' => __( 'hCaptcha couldn\'t be reached to check this inquiry, so it came through without the check.', 'crc-real-estate' ),
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
	 * The form's class, as it has always been.
	 *
	 * @return string
	 */
	protected static function form_class() {
		return 'crc-inquiry';
	}

	/**
	 * No more attributes on the form: the script knows it as the inquiry form.
	 *
	 * @return string
	 */
	protected static function form_data() {
		return '';
	}

	/**
	 * No checking rules on the fields' boxes: the script has them built in.
	 *
	 * @param array $field Field details.
	 * @return string
	 */
	protected static function rules( array $field ) {
		return '';
	}

	/**
	 * Hidden fields: the listing the inquiry is about, and the page.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string[]
	 */
	protected static function hidden( array $atts ) {
		$listing = Shortcodes::listing( $atts['id'] );

		return array(
			'crc_listing' => esc_attr( $listing ? $listing->ID : 0 ),
			'crc_page'    => esc_url( $listing ? (string) get_permalink( $listing ) : self::page_url() ),
		);
	}

	/**
	 * The address an inquiry sent without JavaScript goes back to stays as
	 * it was, so links already shared keep working.
	 *
	 * @param array $form Form from read().
	 * @return string[]
	 */
	protected static function back_args( array $form ) {
		return array();
	}

	/**
	 * Reads the submitted form, with the listing it is about.
	 *
	 * @param array $posted Submitted form, unslashed.
	 * @return array
	 */
	public static function read( array $posted ) {
		$form            = parent::read( $posted );
		$form['listing'] = isset( $posted['crc_listing'] ) && is_scalar( $posted['crc_listing'] ) ? absint( trim( sanitize_text_field( (string) $posted['crc_listing'] ) ) ) : 0;

		return $form;
	}

	/**
	 * What is kept and emailed, with the listing the inquiry is about.
	 *
	 * @param array  $form Checked form from read().
	 * @param string $note Note about the check, or an empty string.
	 * @return array
	 */
	protected static function data( array $form, $note ) {
		$data            = parent::data( $form, $note );
		$data['listing'] = self::valid_listing( $form['listing'] );

		return $data;
	}

	/**
	 * The listing an inquiry is about, if it's on the website or switched
	 * off (unlisted): someone may send the form from a page opened just before.
	 *
	 * @param int $id Listing ID.
	 * @return int Listing ID, or 0.
	 */
	private static function valid_listing( $id ) {
		$post = $id ? get_post( (int) $id ) : null;

		return $post && Post_Type::NAME === $post->post_type && in_array( $post->post_status, array( 'publish', Listing_Status::OFF ), true ) ? (int) $post->ID : 0;
	}
}
