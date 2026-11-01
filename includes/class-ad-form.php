<?php
/**
 * Post a free ad form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_post_ad_form]: a short form where people send a property to
 * advertise. Their name and how to reach them, the category, the place, the
 * price if they like, a description and up to a few photos (see Ad_Photos).
 * The site's team calls them for anything else. Ads are emailed to the site
 * and kept in Free Ads in wp-admin; nothing is published by itself.
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
				'description' => __( 'A short form where people send you a property to advertise: Your Name, Phone Number with a country code (Sri Lanka unless changed) and E-Mail side by side, the category (Lands, Properties for Sale or Properties for Rent), District or Town, Price, Description and Photos, added with an Upload Photos button. E-Mail, Price and Photos can be left out; everything else must be filled in. Photos are made smaller in the browser (no side bigger than the largest size in Listings → Settings), turned into WebP and sent in small pieces while the rest of the form is filled in, so big photos don\'t stop halfway; they are kept in a private folder, not in the Media Library. Everything is checked while people type and again when the ad arrives, and the fields and labels take the site\'s own Elementor styles. Ads are emailed to the address in Listings → Settings and kept in Free Ads, its own menu in wp-admin, with their photos; new ones are counted on the menu. Nothing goes on the website by itself: you call them for the other details and add the listing yourself. When hCaptcha is turned on in Listings → Settings, its "I am human" box shows on this form too.', 'crc-real-estate' ),
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
				'row'        => 'contact',
			),
			// Not everyone has one, and the team calls anyway.
			'email'         => array(
				'rule'        => 'email',
				'type'        => 'email',
				'label'       => __( 'E-Mail', 'crc-real-estate' ),
				'max'         => self::EMAIL_LENGTH,
				'placeholder' => __( 'e.g. nimal.perera@gmail.com', 'crc-real-estate' ),
				'attributes'  => ' autocomplete="email" autocapitalize="off" spellcheck="false"',
				'row'         => 'contact',
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
				'row'         => 'place',
				'messages'    => array( 'invalid' => __( 'Please enter the price in numbers only, for example 25,000,000.', 'crc-real-estate' ) ),
			),
			'description'   => array(
				'type'        => 'textarea',
				'label'       => __( 'Description', 'crc-real-estate' ),
				'required'    => true,
				'max'         => self::DESCRIPTION_LENGTH,
				'rows'        => 6,
				'placeholder' => __( 'e.g. 20 perches of flat land with a 20 ft road, water and electricity, 5 minutes from Galle town.', 'crc-real-estate' ),
				'messages'    => array(
					'empty' => __( 'Please describe your property.', 'crc-real-estate' ),
					/* translators: %s: the most characters allowed, e.g. 3,000. */
					'long'  => __( 'Please keep the description to %s characters or fewer.', 'crc-real-estate' ),
				),
			),
		);
	}

	/**
	 * The photos box, when the form takes photos, and the note under it:
	 * the team calls before anything goes on the website.
	 *
	 * @param string $id Form ID.
	 * @return string
	 */
	protected static function after_fields( $id = '' ) {
		$html = Ad_Photos::enabled() ? Ad_Photos::field( $id ) : '';

		return $html . '<p class="crc-inquiry-note">' . esc_html__( 'After you send this, we\'ll call you to talk about your property and check the details before it goes on the website.', 'crc-real-estate' ) . '</p>';
	}

	/**
	 * Loads the form's files, with the photos box's script when the form takes photos.
	 */
	protected function enqueue() {
		parent::enqueue();

		if ( Ad_Photos::enabled() ) {
			Ad_Photos::enqueue();
		}
	}

	/**
	 * The fields' names, with the photos box.
	 *
	 * @return string[]
	 */
	public static function labels() {
		$labels           = parent::labels();
		$labels['photos'] = __( 'Photos', 'crc-real-estate' );

		return $labels;
	}

	/**
	 * Reads the submitted form, with the visit its photos were sent in and
	 * the photos, in the order they were on the form.
	 *
	 * @param array $posted Submitted form, unslashed.
	 * @return array
	 */
	public static function read( array $posted ) {
		$form    = parent::read( $posted );
		$session = isset( $posted['crc_photo_session'] ) && is_scalar( $posted['crc_photo_session'] ) ? sanitize_key( (string) $posted['crc_photo_session'] ) : '';
		$photos  = isset( $posted['crc_photos'] ) && is_array( $posted['crc_photos'] ) ? array_slice( $posted['crc_photos'], 0, 50 ) : array();

		$form['photo_session'] = Ad_Photos::id( $session );
		$form['photos']        = array_values(
			array_filter(
				array_map(
					function ( $photo ) {
						return is_scalar( $photo ) ? Ad_Photos::id( sanitize_key( (string) $photo ) ) : '';
					},
					$photos
				)
			)
		);

		return $form;
	}

	/**
	 * Checks each field, and that the photos sent with the form all arrived.
	 *
	 * @param array $form Form from read().
	 * @return string[] Field key => message, for the fields that aren't right.
	 */
	public static function check( array $form ) {
		$errors = parent::check( $form );

		if ( ! empty( $form['photos'] ) && Ad_Photos::enabled() ) {
			$photos = Ad_Photos::ready( $form['photo_session'], $form['photos'] );

			if ( is_wp_error( $photos ) ) {
				$errors['photos'] = 'count' === $photos->get_error_code()
					/* translators: %s: most photos, e.g. 10. */
					? sprintf( __( 'You can add up to %s photos.', 'crc-real-estate' ), number_format_i18n( (int) Settings::photos( 'count' ) ) )
					: __( 'Some photos didn\'t arrive. Please remove them and add them again.', 'crc-real-estate' );
			}
		}

		return $errors;
	}

	/**
	 * Moves the photos sent with the form to the ad just kept, and counts
	 * them for the email.
	 *
	 * @param int   $post_id The ad, or 0 when it couldn't be kept.
	 * @param array $form    Checked form from read().
	 * @param array $data    Details from data().
	 * @return array
	 */
	protected static function attach( $post_id, array $form, array $data ) {
		$data['photos'] = $post_id && ! empty( $form['photos'] ) && Ad_Photos::enabled() ? count( Ad_Photos::attach( $form['photo_session'], $form['photos'], $post_id ) ) : 0;

		return $data;
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
			/* translators: %s: names of the fields to check, e.g. "Phone Number, Description". */
			'fields'     => __( 'Your ad wasn\'t sent. Please check these and try again: %s.', 'crc-real-estate' ),
			/* translators: %s: the name given on the form. */
			'sent'       => __( 'Thank you, %s! We\'ve got your ad. We\'ll call you soon to talk about your property and check the details.', 'crc-real-estate' ),
			'sent_plain' => __( 'Thank you! We\'ve got your ad. We\'ll call you soon to talk about your property and check the details.', 'crc-real-estate' ),
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
