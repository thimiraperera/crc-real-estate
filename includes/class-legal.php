<?php
/**
 * Legal pages.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * [crc_privacy_policy], [crc_terms_and_conditions], [crc_disclaimer] and
 * [crc_listing_terms]: the website's legal pages, written for a property
 * business in Sri Lanka, with the business details from Listings → Settings.
 *
 * The texts use {words in braces} for the business details and the links to
 * the other legal pages; translations must keep them as they are.
 */
final class Legal {

	const OPTION = 'crc_re_business';

	/**
	 * When the texts last changed. Move it on whenever a text changes.
	 */
	const UPDATED = '2026-10-24';

	/**
	 * The legal pages: shortcode tag => page slug and title.
	 *
	 * @return array[]
	 */
	public static function pages() {
		return array(
			'crc_privacy_policy'       => array(
				'slug'  => 'privacy-policy',
				'title' => __( 'Privacy Policy', 'crc-real-estate' ),
			),
			'crc_terms_and_conditions' => array(
				'slug'  => 'terms-and-conditions',
				'title' => __( 'Terms and Conditions', 'crc-real-estate' ),
			),
			'crc_disclaimer'           => array(
				'slug'  => 'disclaimer',
				'title' => __( 'Disclaimer', 'crc-real-estate' ),
			),
			'crc_listing_terms'        => array(
				'slug'  => 'listing-terms',
				'title' => __( 'Listing Terms', 'crc-real-estate' ),
			),
		);
	}

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Registers the shortcodes.
	 */
	public function register_shortcodes() {
		$about = array(
			'crc_privacy_policy'       => __( 'The Privacy Policy: what the forms collect, the owner details kept private, the identity checks the law asks for when a sale goes ahead, the services the website uses, how long things are kept, people\'s rights under Sri Lanka\'s Personal Data Protection Act and cookies.', 'crc-real-estate' ),
			'crc_terms_and_conditions' => __( 'The Terms and Conditions for using the website: what the business does, what people may not do, listings and inquiries, fees, the website\'s content, responsibility and Sri Lankan law.', 'crc-real-estate' ),
			'crc_disclaimer'           => __( 'The Disclaimer: where listing details come from, what "checked" and Verified by CRC mean and don\'t mean, what to check before paying, prices, the map\'s area, buyers from abroad and how to stay safe from fraud.', 'crc-real-estate' ),
			'crc_listing_terms'        => __( 'The Listing Terms for owners who send a property to list: who can list, what to tell, the checks, how the listing is shown, permission to use the photos, fees, what is not allowed and taking a listing down.', 'crc-real-estate' ),
		);

		foreach ( self::pages() as $tag => $page ) {
			Shortcodes::add(
				$tag,
				array( $this, 'render' ),
				array(
					'title'       => $page['title'],
					/* translators: 1: what the page says, 2: suggested page address, e.g. /privacy-policy/. */
					'description' => sprintf( __( '%1$s The business details come from Listings → Settings → Business details. Put it on a page at %2$s, so the legal pages can link to each other.', 'crc-real-estate' ), $about[ $tag ], '/' . $page['slug'] . '/' ),
				)
			);
		}
	}

	/**
	 * Renders a legal page's shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Content between the tags (not used).
	 * @param string       $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts, $content = null, $tag = '' ) {
		switch ( $tag ) {
			case 'crc_privacy_policy':
				$blocks = self::privacy();
				break;

			case 'crc_terms_and_conditions':
				$blocks = self::terms();
				break;

			case 'crc_disclaimer':
				$blocks = self::disclaimer();
				break;

			case 'crc_listing_terms':
				$blocks = self::listing();
				break;

			default:
				return '';
		}

		// Noon, so the date is the same day in every time zone the site may use.
		$updated = wp_date( (string) get_option( 'date_format' ), (int) strtotime( self::UPDATED . ' 12:00:00' ) );

		array_unshift( $blocks, array( 'p', '<strong>' . __( 'Last updated:', 'crc-real-estate' ) . '</strong> ' . esc_html( $updated ) ) );

		return sprintf(
			'<div class="crc-legal crc-legal-%1$s">%2$s</div>',
			esc_attr( self::pages()[ $tag ]['slug'] ),
			self::html( $blocks ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Kept to a few tags in html().
		);
	}

	/**
	 * Business details used until they are saved: the company behind the
	 * website, as on its certificate of incorporation.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'name'    => 'CRC Properties',
			'company' => 'CRC Engineering (Pvt) Ltd',
			'number'  => 'PV 00339321',
			'website' => 'https://www.crcengineering.lk/',
			'address' => 'No 167/A, Richmond Hill Road, Galle, Sri Lanka',
			'email'   => 'info@crcproperties.lk',
			'phones'  => array( '+94 (0) 777 64 32 64', '+94 (0) 777 54 70 53', '+94 (0) 11 728 9 728' ),
		);
	}

	/**
	 * A saved business detail. An empty one goes back to its default, so the
	 * legal pages never have a gap.
	 *
	 * @param string $key "name", "company", "number", "website", "address", "email" or "phones".
	 * @return string|string[]
	 */
	public static function business( $key ) {
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? self::sanitize( $saved ) : array();
		$defaults = self::defaults();

		if ( ! empty( $saved[ $key ] ) ) {
			return $saved[ $key ];
		}

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	/**
	 * Cleans the business details before they are saved.
	 *
	 * @param mixed $input Submitted details.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$text   = function ( $key ) use ( $input ) {
			return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? trim( sanitize_text_field( (string) $input[ $key ] ) ) : '';
		};
		$phones = isset( $input['phones'] ) ? $input['phones'] : array();
		$phones = is_array( $phones ) ? $phones : preg_split( '/\r\n|\r|\n/', is_scalar( $phones ) ? (string) $phones : '' );
		$email  = sanitize_email( $text( 'email' ) );
		$site   = esc_url_raw( $text( 'website' ), array( 'http', 'https' ) );

		return array(
			'name'    => $text( 'name' ),
			'company' => $text( 'company' ),
			'number'  => $text( 'number' ),
			'website' => $site,
			'address' => $text( 'address' ),
			'email'   => is_email( $email ) ? $email : '',
			'phones'  => array_slice( array_values( array_filter( array_map( array( Settings::class, 'sanitize_number' ), $phones ) ) ), 0, 5 ),
		);
	}

	/**
	 * Where a legal page is: the page with its slug, or for the Privacy
	 * Policy the one chosen in Settings → Privacy.
	 *
	 * @param string $tag Shortcode tag.
	 * @return string
	 */
	public static function url( $tag ) {
		$slug = self::pages()[ $tag ]['slug'];

		if ( 'crc_privacy_policy' === $tag && function_exists( 'get_privacy_policy_url' ) && '' !== get_privacy_policy_url() ) {
			return get_privacy_policy_url();
		}

		$page = get_page_by_path( $slug );

		return $page && 'publish' === $page->post_status ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
	}

	/**
	 * A web address without https:// and the slash at the end, e.g. www.crcengineering.lk.
	 *
	 * @param string $url Address.
	 * @return string
	 */
	private static function host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return $host ? $host : untrailingslashit( preg_replace( '#^https?://#', '', $url ) );
	}

	/**
	 * Puts the business details and the links to the other pages in place of
	 * the {words in braces}.
	 *
	 * @param string $text Translated text.
	 * @return string HTML.
	 */
	private static function fill( $text ) {
		static $words = null;

		if ( null === $words ) {
			$phones  = (array) self::business( 'phones' );
			$email   = self::business( 'email' );
			$website = self::business( 'website' );
			$domains = array_values( array_unique( array( '@' . substr( strrchr( $email, '@' ), 1 ), '@' . preg_replace( '/^www\./', '', self::host( $website ) ) ) ) );
			$words   = array(
				'{name}'       => esc_html( self::business( 'name' ) ),
				'{company}'    => esc_html( self::business( 'company' ) ),
				'{number}'     => esc_html( self::business( 'number' ) ),
				'{address}'    => esc_html( self::business( 'address' ) ),
				'{site}'       => esc_html( self::host( home_url( '/' ) ) ),
				'{website}'    => '<a href="' . esc_url( $website ) . '">' . esc_html( self::host( $website ) ) . '</a>',
				'{email}'      => '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>',
				'{phone}'      => esc_html( (string) reset( $phones ) ),
				'{phones}'     => esc_html( wp_sprintf_l( '%l', $phones ) ),
				/* translators: 1: e-mail ending, e.g. @crcproperties.lk, 2: another one. */
				'{domains}'    => esc_html( 1 < count( $domains ) ? sprintf( __( '%1$s or %2$s', 'crc-real-estate' ), $domains[0], $domains[1] ) : $domains[0] ),
				'{privacy}'    => esc_url( self::url( 'crc_privacy_policy' ) ),
				'{terms}'      => esc_url( self::url( 'crc_terms_and_conditions' ) ),
				'{disclaimer}' => esc_url( self::url( 'crc_disclaimer' ) ),
				'{listing}'    => esc_url( self::url( 'crc_listing_terms' ) ),
			);
		}

		return strtr( $text, $words );
	}

	/**
	 * Turns a page's blocks into HTML: headings, paragraphs and lists, with
	 * only links, bold words and line breaks inside them.
	 *
	 * @param array[] $blocks Each array( tag, text ), or array( 'ul', texts ).
	 * @return string
	 */
	private static function html( array $blocks ) {
		$allowed = array(
			'a'      => array( 'href' => true ),
			'strong' => array(),
			'br'     => array(),
		);
		$clean   = function ( $text ) use ( $allowed ) {
			return wp_kses( self::fill( $text ), $allowed );
		};
		$html    = '';

		foreach ( $blocks as $block ) {
			if ( 'ul' === $block[0] ) {
				$html .= "<ul>\n<li>" . implode( "</li>\n<li>", array_map( $clean, $block[1] ) ) . "</li>\n</ul>\n";
			} else {
				$html .= sprintf( "<%1\$s>%2\$s</%1\$s>\n", tag_escape( $block[0] ), $clean( $block[1] ) );
			}
		}

		return "\n" . $html;
	}

	/**
	 * The contact details at the end of a page.
	 *
	 * @return array
	 */
	private static function contact() {
		return array( 'p', '{name}<br>{company}<br>{address}<br>' . __( 'E-mail:', 'crc-real-estate' ) . ' {email}<br>' . __( 'Phone:', 'crc-real-estate' ) . ' {phone}' );
	}

	/**
	 * The Privacy Policy.
	 *
	 * @return array[]
	 */
	private static function privacy() {
		return array(
			array( 'p', __( 'This Privacy Policy explains what personal information {name} collects through {site}, why we collect it, who we share it with, how long we keep it and the choices you have. We are a small team based in Galle, and we only deal with land and property in Sri Lanka.', 'crc-real-estate' ) ),

			array( 'h4', __( '1. Who we are', 'crc-real-estate' ) ),
			array( 'p', __( '{name} is the real estate service of {company}, a private limited company incorporated in Sri Lanka under the Companies Act, No. 7 of 2007, with company number {number}. {company} also offers engineering and construction services at {website}.', 'crc-real-estate' ) ),
			array( 'p', __( 'In this policy, "we", "us" and "our" mean {company}. We decide how your personal information is used, so we are responsible for it. Our office is at {address}.', 'crc-real-estate' ) ),
			array( 'p', __( 'For anything about your personal information, contact us at {email} or {phone}.', 'crc-real-estate' ) ),

			array( 'h4', __( '2. The information we collect', 'crc-real-estate' ) ),
			array( 'h5', __( 'When you send an inquiry about a listing', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'Your first and last name', 'crc-real-estate' ),
					__( 'Your phone number and its country code', 'crc-real-estate' ),
					__( 'Your e-mail address', 'crc-real-estate' ),
					__( 'Your message', 'crc-real-estate' ),
					__( 'The listing you asked about', 'crc-real-estate' ),
				),
			),
			array( 'h5', __( 'When you use our contact form', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'Your first and last name, phone number and e-mail address', 'crc-real-estate' ),
					__( 'Your message', 'crc-real-estate' ),
				),
			),
			array( 'h5', __( 'When you send us a property to list (Post Your Ad)', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'Your name, phone number and e-mail address', 'crc-real-estate' ),
					__( 'Details of the property: its category, type, district or town, price, land size, bedrooms, a title and a description', 'crc-real-estate' ),
					__( 'Later, when we contact you: photos of the property, and documents such as the deed, survey plan and your National Identity Card or passport, so we can check the listing before it goes live', 'crc-real-estate' ),
				),
			),
			array( 'h5', __( 'When you own or offer a property we list', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'The owner\'s name, phone number, e-mail address and address, and notes our team makes', 'crc-real-estate' ),
					__( 'The exact location of the property', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'We keep these details private. They are never shown on the website. The website only ever shows a wide area around the property, never its exact place.', 'crc-real-estate' ) ),
			array( 'h5', __( 'When a sale or rental goes ahead', 'crc-real-estate' ) ),
			array( 'p', __( 'Under the Financial Transactions Reporting Act, No. 6 of 2006, and the rules made under it, real estate agents in Sri Lanka must identify the people they deal with. When we help with a sale, we may ask buyers and sellers for their full name, address, National Identity Card or passport, date of birth, nationality, occupation, the source of the funds and the purpose of the purchase. We only collect this when the law requires it.', 'crc-real-estate' ) ),
			array( 'h5', __( 'When you phone, WhatsApp or e-mail us', 'crc-real-estate' ) ),
			array( 'p', __( 'We keep a record of your number or address and what we discuss, so we can help you and follow up.', 'crc-real-estate' ) ),
			array( 'h5', __( 'Information collected automatically', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( '<strong>Spam protection:</strong> when you send a form, we use a scrambled form of your internet (IP) address to stop the same visitor sending too many messages in a short time. It is deleted after 10 minutes. Some forms may also use hCaptcha, a service that checks you are a person.', 'crc-real-estate' ),
					__( '<strong>Listing views:</strong> we count how many times each listing is viewed. Your browser remembers the day it last counted a view, so each listing is counted once a day for each visitor. We don\'t record who viewed it.', 'crc-real-estate' ),
					__( '<strong>Your display choices:</strong> your browser remembers whether you prefer search results as a grid or a list.', 'crc-real-estate' ),
					__( '<strong>Website security and statistics:</strong> our website is protected and delivered by Cloudflare, which processes technical information such as your IP address and browser type to keep the site secure and fast. We use Cloudflare Web Analytics to see overall visitor numbers. It doesn\'t use cookies and doesn\'t identify you.', 'crc-real-estate' ),
				),
			),

			array( 'h4', __( '3. Why we use your information', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'To answer your inquiry, arrange viewings and help you buy, sell or rent a property', 'crc-real-estate' ),
					__( 'To review, check and publish property listings', 'crc-real-estate' ),
					__( 'To pass your inquiry to the owner, or the owner\'s details to you, when you want to move forward', 'crc-real-estate' ),
					__( 'To meet our legal duties, including identifying customers under the Financial Transactions Reporting Act', 'crc-real-estate' ),
					__( 'To keep the website and our forms safe from spam and misuse', 'crc-real-estate' ),
					__( 'To understand, in total numbers, how the website is used, so we can improve it', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'We use your information because you asked us to help you, because you agreed to it when you sent a form, because the law requires it, or because we have a fair business reason (such as keeping the website secure) that doesn\'t override your rights.', 'crc-real-estate' ) ),
			array( 'p', __( 'If you ask us about design, construction or other engineering services, our engineering team at {company} may contact you about them. Otherwise, we only use your information for the property you contacted us about.', 'crc-real-estate' ) ),
			array( 'p', __( 'We don\'t sell your personal information. We don\'t send you newsletters or marketing messages unless you ask for them.', 'crc-real-estate' ) ),

			array( 'h4', __( '4. Who we share it with', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( '<strong>Property owners, buyers and tenants:</strong> only what is needed to arrange a viewing or move a deal forward, such as your name and phone number.', 'crc-real-estate' ),
					__( '<strong>Service providers who run our website and e-mail:</strong> our web hosting company, our e-mail provider, Cloudflare (security and delivery), and hCaptcha when a form uses it. They may only use your information to provide their service to us.', 'crc-real-estate' ),
					__( '<strong>Services your browser contacts when you open our pages:</strong> Google Fonts (the website\'s lettering), OpenStreetMap (map pictures) and jsDelivr (the map\'s code). They receive your IP address, as any website you visit does.', 'crc-real-estate' ),
					__( '<strong>WhatsApp:</strong> when you tap a Message button, WhatsApp opens and its own privacy policy applies.', 'crc-real-estate' ),
					__( '<strong>Lawyers, notaries and surveyors:</strong> when you ask us to involve them in a sale or rental.', 'crc-real-estate' ),
					__( '<strong>Authorities:</strong> when the law requires it, such as the Financial Intelligence Unit, the police or a court.', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'Some of these providers store information on servers outside Sri Lanka. We only use well-known providers that protect information properly.', 'crc-real-estate' ) ),

			array( 'h4', __( '5. How long we keep it', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( '<strong>Inquiries, contact messages and our notes:</strong> up to 2 years after our last contact with you.', 'crc-real-estate' ),
					__( '<strong>Properties sent to us that we don\'t list:</strong> up to 12 months.', 'crc-real-estate' ),
					__( '<strong>Listings and owner details:</strong> while the property is listed, and up to 2 years after it comes down.', 'crc-real-estate' ),
					__( '<strong>Identity and transaction records for a sale:</strong> for as long as the law requires.', 'crc-real-estate' ),
					__( '<strong>Spam protection records:</strong> 10 minutes.', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'After that, we delete the information or remove anything that identifies you.', 'crc-real-estate' ) ),

			array( 'h4', __( '6. How we protect it', 'crc-real-estate' ) ),
			array( 'p', __( 'Our website uses a secure (HTTPS) connection. Only our team can see inquiries, messages and owner details, and the parts of the website that hold them need a password. We remove the GPS location that phones store inside photos before a photo is published. No method is completely safe, but we work to protect your information and will tell you if something goes wrong that affects you.', 'crc-real-estate' ) ),

			array( 'h4', __( '7. Your rights', 'crc-real-estate' ) ),
			array( 'p', __( 'You can ask us to:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'tell you what personal information we hold about you and give you a copy', 'crc-real-estate' ),
					__( 'correct or complete information that is wrong or out of date', 'crc-real-estate' ),
					__( 'delete your information, unless we must keep it by law', 'crc-real-estate' ),
					__( 'stop using your information where you gave consent, or where you object to how we use it', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'Send your request to {email}. We may ask you to confirm who you are. We will reply within 21 working days. If you are not happy with our answer, you can contact the Data Protection Authority of Sri Lanka.', 'crc-real-estate' ) ),

			array( 'h4', __( '8. Cookies and similar technology', 'crc-real-estate' ) ),
			array( 'p', __( 'We don\'t use advertising or tracking cookies. Your browser stores a few small items for the website to work: the date it last counted a listing view, and your grid or list choice for search results. Cloudflare may set a short-lived cookie that helps tell people from automated programs. If we add analytics or advertising tools that use cookies in the future, we will update this policy and ask for your consent where needed.', 'crc-real-estate' ) ),

			array( 'h4', __( '9. Children', 'crc-real-estate' ) ),
			array( 'p', __( 'Our services are for adults. We don\'t knowingly collect information from anyone under 18. If you believe a child has sent us their details, please contact us and we will delete them.', 'crc-real-estate' ) ),

			array( 'h4', __( '10. Sri Lankan law', 'crc-real-estate' ) ),
			array( 'p', __( 'We handle personal information in line with the Personal Data Protection Act, No. 9 of 2022, as amended, and other laws of Sri Lanka.', 'crc-real-estate' ) ),

			array( 'h4', __( '11. Changes to this policy', 'crc-real-estate' ) ),
			array( 'p', __( 'We may update this policy as our services or the law change. The date at the top shows when it last changed. If we make an important change, we will say so on the website.', 'crc-real-estate' ) ),

			array( 'h4', __( '12. Contact us', 'crc-real-estate' ) ),
			self::contact(),
		);
	}

	/**
	 * The Terms and Conditions.
	 *
	 * @return array[]
	 */
	private static function terms() {
		return array(
			array( 'p', __( 'These Terms and Conditions apply to everyone who uses {site}. Please read them before you use the website. By using the website, sending an inquiry or sending us a property, you agree to them. If you don\'t agree, please don\'t use the website.', 'crc-real-estate' ) ),

			array( 'h4', __( '1. Who we are', 'crc-real-estate' ) ),
			array( 'p', __( '{name} is the real estate service of {company}, a private limited company incorporated in Sri Lanka under the Companies Act, No. 7 of 2007, with company number {number}. {company} also offers engineering and construction services at {website}. In these terms, "we", "us" and "our" mean {company}. Our office is at {address}.', 'crc-real-estate' ) ),

			array( 'h4', __( '2. What we do', 'crc-real-estate' ) ),
			array( 'p', __( 'We list land and property for sale and for rent in Sri Lanka. We review listings before they go live, answer inquiries, arrange viewings and help buyers, tenants and owners deal with each other. At present we only list property located in Sri Lanka. People living abroad are welcome to use the website, but every property and deal is in Sri Lanka.', 'crc-real-estate' ) ),
			array( 'p', __( 'We are not a law firm, notary, bank, surveyor or valuer, and nothing on the website is legal, financial or valuation advice. Please read our <a href="{disclaimer}">Disclaimer</a>.', 'crc-real-estate' ) ),

			array( 'h4', __( '3. Using the website', 'crc-real-estate' ) ),
			array( 'p', __( 'You must be 18 or older to send an inquiry or a property. When you use the website, you agree not to:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'give false details, pretend to be someone else, or send inquiries you don\'t mean', 'crc-real-estate' ),
					__( 'copy, download or collect listings, photos or contact details in bulk, by hand or with automated tools, or republish them on another website', 'crc-real-estate' ),
					__( 'contact owners or people who send inquiries to harass them, sell to them or send spam', 'crc-real-estate' ),
					__( 'upload anything unlawful, misleading, offensive, or that you don\'t have the right to share', 'crc-real-estate' ),
					__( 'try to get into parts of the website that aren\'t meant for you, or interfere with how it works', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'We may block anyone who breaks these terms.', 'crc-real-estate' ) ),

			array( 'h4', __( '4. Listings', 'crc-real-estate' ) ),
			array( 'p', __( 'Listing details, photos and prices come from property owners or the people they authorise. We check listings before they go live, but we can\'t guarantee that every detail is correct, complete or up to date. Prices are in Sri Lankan rupees and may change. A property may be sold, rented or withdrawn before we take its listing down or mark it Sold or Rented.', 'crc-real-estate' ) ),
			array( 'p', __( 'The map on a listing shows a wide area around the property on purpose, not its exact place. We share the exact location with serious buyers or tenants when we arrange a viewing.', 'crc-real-estate' ) ),
			array( 'p', __( 'Before you pay anything or sign anything, check the property and its papers yourself, with your own lawyer or notary. Our <a href="{disclaimer}">Disclaimer</a> explains what our checks cover and what they don\'t.', 'crc-real-estate' ) ),

			array( 'h4', __( '5. Inquiries', 'crc-real-estate' ) ),
			array( 'p', __( 'When you send an inquiry, we may contact you by phone, WhatsApp or e-mail about that property and similar ones. Sending an inquiry doesn\'t reserve the property and doesn\'t create an agreement to buy or rent it. A sale or rental is only agreed when the buyer or tenant and the owner sign the proper documents.', 'crc-real-estate' ) ),

			array( 'h4', __( '6. Payments and fees', 'crc-real-estate' ) ),
			array( 'p', __( 'Browsing the website, sending inquiries and posting a property are free. We don\'t take payments through the website. Any commission or service fee for helping with a sale or rental will be agreed in writing before we start that work. We will never ask you to pay a deposit or advance to "hold" a property through WhatsApp or into a personal bank account.', 'crc-real-estate' ) ),

			array( 'h4', __( '7. Sending us a property', 'crc-real-estate' ) ),
			array( 'p', __( 'If you send us a property to list, our <a href="{listing}">Listing Terms</a> also apply.', 'crc-real-estate' ) ),

			array( 'h4', __( '8. Our content', 'crc-real-estate' ) ),
			array( 'p', __( 'The website\'s design, text, logos and the way listings are presented belong to {company}. Listing photos and descriptions belong to us or to the owners who gave us permission to use them. You may view and share links to our pages, and print a listing for your own use. You may not copy, change or reuse our content for any other purpose without our written permission.', 'crc-real-estate' ) ),

			array( 'h4', __( '9. Other websites and services', 'crc-real-estate' ) ),
			array( 'p', __( 'The website uses or links to services run by others, such as WhatsApp, OpenStreetMap and Google Fonts. We are not responsible for their content or how they work, and their own terms apply.', 'crc-real-estate' ) ),

			array( 'h4', __( '10. The website itself', 'crc-real-estate' ) ),
			array( 'p', __( 'We work to keep the website running and accurate, but it may sometimes be unavailable or contain mistakes. We may change, add or remove any part of it at any time.', 'crc-real-estate' ) ),

			array( 'h4', __( '11. Our responsibility to you', 'crc-real-estate' ) ),
			array( 'p', __( 'As far as the law of Sri Lanka allows:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'we provide the website as it is, without any promise that it will always be available or free of errors', 'crc-real-estate' ),
					__( 'we are not responsible for decisions you make based on information on the website, or for any loss that comes from relying on a listing\'s details instead of checking them yourself', 'crc-real-estate' ),
					__( 'we are not a party to any sale or rental between an owner and a buyer or tenant', 'crc-real-estate' ),
					__( 'we are not responsible for indirect losses, such as lost profits or lost opportunities', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'Nothing in these terms limits any responsibility that cannot be limited under Sri Lankan law, including responsibility for fraud.', 'crc-real-estate' ) ),

			array( 'h4', __( '12. Your responsibility', 'crc-real-estate' ) ),
			array( 'p', __( 'If you misuse the website or break these terms and someone makes a claim against us because of it, you agree to cover our reasonable costs of dealing with that claim.', 'crc-real-estate' ) ),

			array( 'h4', __( '13. Your privacy', 'crc-real-estate' ) ),
			array( 'p', __( 'Our <a href="{privacy}">Privacy Policy</a> explains how we handle your personal information.', 'crc-real-estate' ) ),

			array( 'h4', __( '14. Changes to these terms', 'crc-real-estate' ) ),
			array( 'p', __( 'We may update these terms from time to time. The date at the top shows when they last changed. If you keep using the website after a change, the new terms apply.', 'crc-real-estate' ) ),

			array( 'h4', __( '15. Law and disputes', 'crc-real-estate' ) ),
			array( 'p', __( 'These terms are governed by the law of Sri Lanka, and the courts of Sri Lanka will decide any dispute about them. If you have a problem, please contact us first so we can try to put it right.', 'crc-real-estate' ) ),

			array( 'h4', __( '16. Contact us', 'crc-real-estate' ) ),
			self::contact(),
		);
	}

	/**
	 * The Disclaimer.
	 *
	 * @return array[]
	 */
	private static function disclaimer() {
		return array(
			array( 'p', __( 'We work hard to make every listing on {site} honest and accurate. This page explains what our checks cover, what they don\'t, and what you should check yourself before you buy or rent a property. {name} is the real estate service of {company} (company number {number}).', 'crc-real-estate' ) ),

			array( 'h4', __( '1. General information only', 'crc-real-estate' ) ),
			array( 'p', __( 'Everything on this website, including listings, prices, FAQs and guides, is general information. It isn\'t legal, financial, tax or valuation advice. For advice about a particular property or deal, speak to a lawyer, notary, licensed surveyor, valuer or financial adviser.', 'crc-real-estate' ) ),

			array( 'h4', __( '2. Where listing details come from', 'crc-real-estate' ) ),
			array( 'p', __( 'Listing details, photos and prices come from property owners or the people they authorise. We review them before they go live, but we can\'t guarantee that every detail is correct, complete or current. In particular:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'Land extents, floor areas and distances are as given to us and may be approximate.', 'crc-real-estate' ),
					__( 'Photos may not show the property\'s current condition, and may be taken from the best angle.', 'crc-real-estate' ),
					__( 'Features, facilities and nearby places are as described by the owner.', 'crc-real-estate' ),
				),
			),

			array( 'h4', __( '3. What "checked" and "Verified by CRC" mean', 'crc-real-estate' ) ),
			array( 'p', __( 'When we say a listing is checked or Verified by CRC, it means:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'we have seen the documents the owner gave us, such as the deed and survey plan for land, and checked that the main details in the listing match them', 'crc-real-estate' ),
					__( 'we have spoken to the person listing the property and confirmed they are the owner or have the owner\'s authority to list it', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'It does not mean:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'a title search at the Land Registry, or a legal opinion that the title is clear', 'crc-real-estate' ),
					__( 'that the property is free of disputes, court cases, mortgages, leases or other claims', 'crc-real-estate' ),
					__( 'a survey of the boundaries, or a valuation of the property', 'crc-real-estate' ),
					__( 'that buildings have the required approvals or are in good condition', 'crc-real-estate' ),
				),
			),

			array( 'h4', __( '4. Check before you pay or sign', 'crc-real-estate' ) ),
			array( 'p', __( 'Before you pay a deposit or sign anything, please:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'have your own lawyer or notary do a title search and check the deeds', 'crc-real-estate' ),
					__( 'check the survey plan and the boundaries on site, with a licensed surveyor if needed', 'crc-real-estate' ),
					__( 'get the street line and non-vesting certificates, and any approvals, from the local authority (Municipal Council, Urban Council or Pradeshiya Sabha)', 'crc-real-estate' ),
					__( 'for buildings, check the building plan approval and certificate of conformity', 'crc-real-estate' ),
					__( 'check that the seller\'s National Identity Card matches the name on the deed', 'crc-real-estate' ),
					__( 'for rentals, sign a written lease and get a receipt for every payment', 'crc-real-estate' ),
				),
			),

			array( 'h4', __( '5. Prices', 'crc-real-estate' ) ),
			array( 'p', __( 'Prices are in Sri Lankan rupees and are the owner\'s asking price. They may change, and some are negotiable. Prices per perch are worked out from the price and the land extent and are rounded. Unit conversions on the website are approximate (1 acre is 160 perches, and 1 square metre is about 10.76 square feet). We don\'t promise that any price reflects the property\'s market value.', 'crc-real-estate' ) ),

			array( 'h4', __( '6. Maps and locations', 'crc-real-estate' ) ),
			array( 'p', __( 'To protect owners, the map on each listing shows a wide area around the property, not its exact place. The circle is moved on purpose and doesn\'t show where the property is inside it. We share the exact location when we arrange a viewing.', 'crc-real-estate' ) ),

			array( 'h4', __( '7. Availability', 'crc-real-estate' ) ),
			array( 'p', __( 'We mark a listing Sold or Rented, or take it down, as soon as we know a property has gone. Sometimes a property goes before we hear about it. Please send an inquiry to confirm a property is still available.', 'crc-real-estate' ) ),

			array( 'h4', __( '8. Buyers from abroad', 'crc-real-estate' ) ),
			array( 'p', __( 'Sri Lankans living abroad are welcome to use our services. Foreign nationals, and companies with foreign shareholders, face legal restrictions on buying land in Sri Lanka. Please get legal advice before you start.', 'crc-real-estate' ) ),

			array( 'h4', __( '9. Testimonials', 'crc-real-estate' ) ),
			array( 'p', __( 'Testimonials are the personal experiences of our clients. Every property and deal is different, and your experience may differ.', 'crc-real-estate' ) ),

			array( 'h4', __( '10. Stay safe from fraud', 'crc-real-estate' ) ),
			array( 'p', __( 'Property fraud happens in Sri Lanka, sometimes using the names of real companies. Please remember:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'We will never ask you to pay a deposit or advance to "hold" a property before you have seen it and your lawyer has checked the papers.', 'crc-real-estate' ),
					__( 'We will never ask you to send money to a personal bank account through WhatsApp, SMS or social media. Payments for a sale go to the owner as agreed in the signed documents, and any fee of ours goes to {company}.', 'crc-real-estate' ),
					__( 'Our e-mail addresses end in {domains}. Our phone numbers are {phones}.', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'If someone contacts you in our name and something doesn\'t feel right, call us on one of these numbers before you do anything. If you see a listing you think is false, please tell us.', 'crc-real-estate' ) ),

			array( 'h4', __( '11. Other websites', 'crc-real-estate' ) ),
			array( 'p', __( 'Links to other websites are for your convenience. We are not responsible for their content.', 'crc-real-estate' ) ),

			array( 'h4', __( '12. Contact us', 'crc-real-estate' ) ),
			array( 'p', __( 'If you spot a mistake in a listing, please tell us at {email} or {phone}, and we will look into it.', 'crc-real-estate' ) ),
			array( 'p', __( 'See also our <a href="{terms}">Terms and Conditions</a> and <a href="{privacy}">Privacy Policy</a>.', 'crc-real-estate' ) ),
		);
	}

	/**
	 * The Listing Terms.
	 *
	 * @return array[]
	 */
	private static function listing() {
		return array(
			array( 'p', __( 'These Listing Terms apply when you send us a property to list on {site}, through the Post Your Ad form, by phone, WhatsApp or e-mail, or in person. They sit alongside our <a href="{terms}">Terms and Conditions</a>. By sending us a property, you agree to them. {name} is the real estate service of {company} (company number {number}), and "we", "us" and "our" mean {company}.', 'crc-real-estate' ) ),

			array( 'h4', __( '1. Who can list a property', 'crc-real-estate' ) ),
			array( 'p', __( 'You can list a property with us if:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'you own it, and every co-owner agrees to it being listed, or', 'crc-real-estate' ),
					__( 'the owner has given you written authority to list it, such as a power of attorney or a signed letter', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'The property must be in Sri Lanka, and you must be 18 or older.', 'crc-real-estate' ) ),

			array( 'h4', __( '2. What you tell us', 'crc-real-estate' ) ),
			array( 'p', __( 'Everything you send us must be true, complete and up to date. You must tell us about anything a buyer or tenant would want to know, such as:', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'disputes, court cases or claims about the property', 'crc-real-estate' ),
					__( 'mortgages, leases or other rights others have over it', 'crc-real-estate' ),
					__( 'access problems, road reservations or plans to acquire the land', 'crc-real-estate' ),
					__( 'buildings or extensions without approval', 'crc-real-estate' ),
				),
			),
			array( 'p', __( 'Photos must be of the property itself, and you must own them or have permission to use them.', 'crc-real-estate' ) ),

			array( 'h4', __( '3. Our checks', 'crc-real-estate' ) ),
			array( 'p', __( 'We check every property before it goes live. We may ask for the deed, the survey plan, your National Identity Card or passport, approvals and recent utility bills, and we may visit the property. We keep copies of your documents privately. They are never published.', 'crc-real-estate' ) ),
			array( 'p', __( 'We may refuse a listing, or take one down, for any reason, including when we can\'t verify it. A listing only goes live once we have checked it.', 'crc-real-estate' ) ),

			array( 'h4', __( '4. How we present your listing', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'We may write or edit the title and description, choose the main photo and the order of photos, and crop or resize photos so the listing is clear and consistent.', 'crc-real-estate' ),
					__( 'We remove the GPS location that phones store inside photos before they are published.', 'crc-real-estate' ),
					__( 'The map shows a wide area around the property, never its exact place. The district and town are shown.', 'crc-real-estate' ),
					__( 'The price is shown as you give it to us. For land, we also show the price per perch, worked out from the price and the extent.', 'crc-real-estate' ),
					__( 'The Call and Message buttons on the listing use our numbers, unless we agree otherwise with you. We pass serious inquiries to you.', 'crc-real-estate' ),
					__( 'Your name, phone number, e-mail address and address are kept private and are never shown on the website.', 'crc-real-estate' ),
				),
			),

			array( 'h4', __( '5. Permission to use your photos and details', 'crc-real-estate' ) ),
			array( 'p', __( 'You allow us to use the photos, text and details you send us, free of charge, to advertise your property on our website, on social media, in online ads and in printed material, for as long as it is listed with us. When the listing comes down, we stop using them in new advertising, but we may keep copies in our records.', 'crc-real-estate' ) ),

			array( 'h4', __( '6. Keeping your listing up to date', 'crc-real-estate' ) ),
			array( 'p', __( 'Please tell us straight away if the property is sold or rented, the price changes, or you no longer want to sell or rent it. We will mark it Sold or Rented, update it, or take it down.', 'crc-real-estate' ) ),

			array( 'h4', __( '7. Fees', 'crc-real-estate' ) ),
			array( 'p', __( 'Posting your property on our website is free. If you would like us to help find a buyer or tenant, any commission or service fee will be agreed with you in writing before your listing goes live. We will not charge you anything you have not agreed to. Where a fee has been agreed, please don\'t deal directly with a buyer or tenant we introduced in order to avoid it.', 'crc-real-estate' ) ),

			array( 'h4', __( '8. No guarantees', 'crc-real-estate' ) ),
			array( 'p', __( 'We can\'t promise how many inquiries your listing will get, or that your property will sell or rent, or at what price. Any sale or rental is between you and the buyer or tenant. You are responsible for the deed, the agreement, any taxes and duties, and any approvals the sale or rental needs.', 'crc-real-estate' ) ),

			array( 'h4', __( '9. What is not allowed', 'crc-real-estate' ) ),
			array(
				'ul',
				array(
					__( 'Listing a property you have no right to sell or rent', 'crc-real-estate' ),
					__( 'False prices, false details, or "bait" listings for properties that aren\'t really available', 'crc-real-estate' ),
					__( 'Listing the same property more than once', 'crc-real-estate' ),
					__( 'Photos of a different property, or edited to hide problems', 'crc-real-estate' ),
					__( 'Asking people who inquire through us for advances or deposits without telling us', 'crc-real-estate' ),
				),
			),

			array( 'h4', __( '10. Taking your listing down', 'crc-real-estate' ) ),
			array( 'p', __( 'You can ask us to take your listing down at any time by phone, WhatsApp or e-mail. We will remove it within 2 working days. We may also take a listing down, or put it on hold, at any time, for example if we can\'t contact you or something in it turns out to be wrong.', 'crc-real-estate' ) ),

			array( 'h4', __( '11. Your responsibility', 'crc-real-estate' ) ),
			array( 'p', __( 'You are responsible for what you send us. If someone makes a claim against us because information or photos you gave us were false, or you didn\'t have the right to share them, you agree to cover our reasonable costs of dealing with that claim.', 'crc-real-estate' ) ),

			array( 'h4', __( '12. Your personal information', 'crc-real-estate' ) ),
			array( 'p', __( 'Our <a href="{privacy}">Privacy Policy</a> explains how we handle your personal information and the owner details we keep.', 'crc-real-estate' ) ),

			array( 'h4', __( '13. Changes, law and contact', 'crc-real-estate' ) ),
			array( 'p', __( 'We may update these terms from time to time. The date at the top shows when they last changed. These terms are governed by the law of Sri Lanka, and the courts of Sri Lanka will decide any dispute about them.', 'crc-real-estate' ) ),
			self::contact(),
		);
	}
}
