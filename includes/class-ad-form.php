<?php
/**
 * Post a free ad form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Overview;
use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_post_ad_form]: a simple form where people send a property to
 * advertise for free. Their name and how to reach them, the category,
 * property type, place, price, size, bedrooms, a title and a description.
 * Photos are collected afterwards, by the site's team. Ads are emailed to
 * the site and kept in Free Ads in wp-admin; nothing is published by itself.
 */
final class Ad_Form extends Form {

	const TYPE               = 'ad';
	const SHORTCODE          = 'crc_post_ad_form';
	const ACTION             = 'crc_re_ad';
	const PREFIX             = 'crc-ad-form';
	const RESULT             = 'crc_ad';
	const LIMIT_KEY          = 'ad_requests';
	const STORE              = Ad_Requests::class;
	const FULL_NAME_LENGTH   = 100;
	const TITLE_LENGTH       = 120;
	const DESCRIPTION_LENGTH = 3000;

	/**
	 * Free ad forms on the page so far, for unique field IDs.
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
				'title'       => __( 'Post a free ad form', 'crc-real-estate' ),
				'description' => __( 'A simple form where people send you a property to advertise for free: Your Name, Phone Number with a country code (Sri Lanka unless changed), E-Mail, the category (Lands, Properties for Sale or Properties for Rent), Property Type, District or Town, Price, Land Size, Bedrooms, Ad Title and Description. Price, Land Size and Bedrooms can be left empty; everything else must be filled in. Photos aren\'t sent with the form: a note under the fields says you\'ll contact them to collect the photos and check the details before the ad goes on the website. Everything is checked while people type and again when the ad arrives, and the fields and labels take the site\'s own Elementor styles. Ads are emailed to the address in Listings → Settings and kept in Free Ads, its own menu in wp-admin, where new ones are counted on the menu. Nothing goes on the website by itself: you add the listing yourself when the details are ready. When hCaptcha is turned on in Listings → Settings, its "I am human" box shows on this form too.', 'crc-real-estate' ),
				'attributes'  => array(
					'button'  => array(
						'default'     => __( 'Send My Ad', 'crc-real-estate' ),
						'description' => __( 'The text on the send button.', 'crc-real-estate' ),
					),
					'country' => array(
						'default'     => Phone::DEFAULT_COUNTRY,
						'description' => __( 'The country chosen at first for phone numbers, as its two-letter code: LK for Sri Lanka, GB for the United Kingdom, AU for Australia and so on. People can still choose any country.', 'crc-real-estate' ),
					),
				),
				'examples'    => array(
					'[' . self::SHORTCODE . ']',
					'[' . self::SHORTCODE . ' button="Post My Free Ad"]',
					'[' . self::SHORTCODE . ' country="AE"]',
				),
			)
		);
	}

	/**
	 * The categories people can choose: slug => name.
	 *
	 * @return string[]
	 */
	public static function categories() {
		$categories = array();

		foreach ( Taxonomy::terms() as $slug => $term ) {
			$categories[ $slug ] = $term['name'];
		}

		return $categories;
	}

	/**
	 * The form's fields.
	 *
	 * @return array[]
	 */
	public static function fields() {
		$overview = Overview::fields();
		$types    = isset( $overview['property_type']['suggestions'] ) ? (array) $overview['property_type']['suggestions'] : array();

		return array(
			'name'          => array(
				'rule'        => 'name',
				'label'       => __( 'Your Name', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::FULL_NAME_LENGTH,
				'placeholder' => __( 'e.g. Nimal Perera', 'crc-real-estate' ),
				'attributes'  => ' autocomplete="name" autocapitalize="words"',
				'messages'    => array(
					'empty'   => __( 'Please enter your name.', 'crc-real-estate' ),
					'invalid' => __( 'Please use only letters in your name.', 'crc-real-estate' ),
				),
			),
			'phone'         => array(
				'rule'       => 'phone',
				'type'       => 'tel',
				'label'      => __( 'Phone Number', 'crc-real-estate' ),
				'required'   => true,
				'max'        => 24,
				'attributes' => ' autocomplete="tel" inputmode="tel"',
			),
			'email'         => array(
				'rule'        => 'email',
				'type'        => 'email',
				'label'       => __( 'E-Mail', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::EMAIL_LENGTH,
				'placeholder' => __( 'e.g. nimal.perera@gmail.com', 'crc-real-estate' ),
				'attributes'  => ' autocomplete="email" autocapitalize="off" spellcheck="false"',
				'hint'        => true,
			),
			'category'      => array(
				'rule'     => 'choice',
				'type'     => 'radio',
				'label'    => __( 'Category', 'crc-real-estate' ),
				'required' => true,
				'options'  => self::categories(),
				'messages' => array(
					'empty'   => __( 'Please choose a category for your ad.', 'crc-real-estate' ),
					'invalid' => __( 'Please choose one of these categories.', 'crc-real-estate' ),
				),
			),
			'property_type' => array(
				'label'       => __( 'Property Type', 'crc-real-estate' ),
				'required'    => true,
				'max'         => 50,
				'placeholder' => __( 'e.g. Bare Land, House or Apartment', 'crc-real-estate' ),
				'suggestions' => $types,
				'row'         => 'place',
				'messages'    => array( 'empty' => __( 'Please enter the property type, for example Bare Land or House.', 'crc-real-estate' ) ),
			),
			'location'      => array(
				'label'       => __( 'District or Town', 'crc-real-estate' ),
				'required'    => true,
				'max'         => 100,
				'placeholder' => __( 'e.g. Galle', 'crc-real-estate' ),
				// Where the property is, not the visitor's own address: a word browsers don't
				// know keeps their saved city out of it ("off" is ignored by Chrome's address filling).
				'attributes'  => ' autocomplete="property-location"',
				'row'         => 'place',
				'messages'    => array( 'empty' => __( 'Please enter the district or town.', 'crc-real-estate' ) ),
			),
			'price'         => array(
				'rule'        => 'amount',
				/* translators: %s: currency, e.g. Rs. */
				'label'       => sprintf( __( 'Price (%s)', 'crc-real-estate' ), Price_Card::currency() ),
				'max'         => 20,
				'placeholder' => __( 'e.g. 25,000,000', 'crc-real-estate' ),
				'attributes'  => ' inputmode="numeric"',
				'row'         => 'size',
				'messages'    => array( 'invalid' => __( 'Please enter the price in numbers only, for example 25,000,000.', 'crc-real-estate' ) ),
			),
			'land_size'     => array(
				'label'       => __( 'Land Size', 'crc-real-estate' ),
				'max'         => 50,
				'placeholder' => __( 'e.g. 20 perches', 'crc-real-estate' ),
				'row'         => 'size',
			),
			'bedrooms'      => array(
				'rule'        => 'count',
				'label'       => __( 'Bedrooms', 'crc-real-estate' ),
				'max'         => 2,
				'placeholder' => __( 'e.g. 3', 'crc-real-estate' ),
				'attributes'  => ' inputmode="numeric"',
				'row'         => 'size',
				'messages'    => array( 'invalid' => __( 'Please enter the number of bedrooms, for example 3.', 'crc-real-estate' ) ),
			),
			'title'         => array(
				'label'       => __( 'Ad Title', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::TITLE_LENGTH,
				'placeholder' => __( 'e.g. Bare land for sale in Galle', 'crc-real-estate' ),
				'messages'    => array( 'empty' => __( 'Please give your ad a title.', 'crc-real-estate' ) ),
			),
			'description'   => array(
				'type'        => 'textarea',
				'label'       => __( 'Description', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::DESCRIPTION_LENGTH,
				'rows'        => 6,
				'placeholder' => __( 'e.g. Flat land with a 20 ft road, water and electricity, 5 minutes from Galle town.', 'crc-real-estate' ),
				'messages'    => array(
					'empty' => __( 'Please describe your property.', 'crc-real-estate' ),
					/* translators: %s: the most characters allowed, e.g. 3,000. */
					'long'  => __( 'Please keep the description to %s characters or fewer.', 'crc-real-estate' ),
				),
			),
		);
	}

	/**
	 * The note under the fields: photos come later.
	 *
	 * @return string
	 */
	protected static function after_fields() {
		return '<p class="crc-inquiry-note">' . esc_html__( 'After you send this, we\'ll contact you to collect the photos and check the details before your ad goes on the website.', 'crc-real-estate' ) . '</p>';
	}

	/**
	 * The free ad form's own messages. The browser shows the same ones.
	 *
	 * @return string[]
	 */
	public static function texts() {
		return array(
			'busy'       => __( 'You\'ve sent a few ads in a short time. Please wait a few minutes, then try again.', 'crc-real-estate' ),
			'failed'     => self::with_phone(
				/* translators: %s: phone number. */
				__( 'Sorry, your ad couldn\'t be sent. Please try again in a moment, or call us on %s.', 'crc-real-estate' ),
				__( 'Sorry, your ad couldn\'t be sent. Please try again in a moment.', 'crc-real-estate' )
			),
			'network'    => __( 'Your ad couldn\'t be sent. Please check your internet connection and try again.', 'crc-real-estate' ),
			/* translators: %s: names of the fields to check, e.g. "Phone Number, Ad Title". */
			'fields'     => __( 'Your ad wasn\'t sent. Please check these and try again: %s.', 'crc-real-estate' ),
			/* translators: %s: the name given on the form. */
			'sent'       => __( 'Thank you, %s! We\'ve got your ad. We\'ll contact you soon to collect the photos and check the details.', 'crc-real-estate' ),
			'sent_plain' => __( 'Thank you! We\'ve got your ad. We\'ll contact you soon to collect the photos and check the details.', 'crc-real-estate' ),
		);
	}

	/**
	 * Wording only the site uses.
	 *
	 * @return string[]
	 */
	protected static function notes() {
		return array(
			'button'            => __( 'Send My Ad', 'crc-real-estate' ),
			'noscript'          => __( 'Please turn on JavaScript in your browser to send your ad.', 'crc-real-estate' ),
			'captcha_keys'      => __( 'hCaptcha couldn\'t check this ad because its keys aren\'t right. Please copy both keys again into Listings → Settings.', 'crc-real-estate' ),
			'captcha_unreached' => __( 'hCaptcha couldn\'t be reached to check this ad, so it came through without the check.', 'crc-real-estate' ),
		);
	}
}
