<?php
/**
 * Phone numbers: countries, their codes and how long their numbers are.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Reads phone numbers typed in forms, with a country chosen beside them, and
 * writes them in full international form, e.g. "077 123 4567" in Sri Lanka
 * is +94771234567. The same rules are sent to the browser, so both check a
 * number the same way.
 */
final class Phone {

	const DEFAULT_COUNTRY = 'LK';

	/**
	 * The main country for a code several countries share, used when a number
	 * is typed with "+" and its country isn't the one chosen.
	 */
	const MAIN = array(
		'1'   => 'US',
		'7'   => 'RU',
		'39'  => 'IT',
		'44'  => 'GB',
		'47'  => 'NO',
		'61'  => 'AU',
		'212' => 'MA',
		'262' => 'RE',
		'358' => 'FI',
		'590' => 'GP',
		'599' => 'CW',
	);

	/**
	 * Countries in name order: code => array( name, calling code ).
	 *
	 * @return array[]
	 */
	private static function raw() {
		return array(
			'AF' => array( 'Afghanistan', '93' ),
			'AX' => array( 'Åland Islands', '358' ),
			'AL' => array( 'Albania', '355' ),
			'DZ' => array( 'Algeria', '213' ),
			'AS' => array( 'American Samoa', '1' ),
			'AD' => array( 'Andorra', '376' ),
			'AO' => array( 'Angola', '244' ),
			'AI' => array( 'Anguilla', '1' ),
			'AG' => array( 'Antigua and Barbuda', '1' ),
			'AR' => array( 'Argentina', '54' ),
			'AM' => array( 'Armenia', '374' ),
			'AW' => array( 'Aruba', '297' ),
			'AU' => array( 'Australia', '61' ),
			'AT' => array( 'Austria', '43' ),
			'AZ' => array( 'Azerbaijan', '994' ),
			'BS' => array( 'Bahamas', '1' ),
			'BH' => array( 'Bahrain', '973' ),
			'BD' => array( 'Bangladesh', '880' ),
			'BB' => array( 'Barbados', '1' ),
			'BY' => array( 'Belarus', '375' ),
			'BE' => array( 'Belgium', '32' ),
			'BZ' => array( 'Belize', '501' ),
			'BJ' => array( 'Benin', '229' ),
			'BM' => array( 'Bermuda', '1' ),
			'BT' => array( 'Bhutan', '975' ),
			'BO' => array( 'Bolivia', '591' ),
			'BA' => array( 'Bosnia and Herzegovina', '387' ),
			'BW' => array( 'Botswana', '267' ),
			'BR' => array( 'Brazil', '55' ),
			'IO' => array( 'British Indian Ocean Territory', '246' ),
			'VG' => array( 'British Virgin Islands', '1' ),
			'BN' => array( 'Brunei', '673' ),
			'BG' => array( 'Bulgaria', '359' ),
			'BF' => array( 'Burkina Faso', '226' ),
			'BI' => array( 'Burundi', '257' ),
			'KH' => array( 'Cambodia', '855' ),
			'CM' => array( 'Cameroon', '237' ),
			'CA' => array( 'Canada', '1' ),
			'CV' => array( 'Cape Verde', '238' ),
			'BQ' => array( 'Caribbean Netherlands', '599' ),
			'KY' => array( 'Cayman Islands', '1' ),
			'CF' => array( 'Central African Republic', '236' ),
			'TD' => array( 'Chad', '235' ),
			'CL' => array( 'Chile', '56' ),
			'CN' => array( 'China', '86' ),
			'CX' => array( 'Christmas Island', '61' ),
			'CC' => array( 'Cocos (Keeling) Islands', '61' ),
			'CO' => array( 'Colombia', '57' ),
			'KM' => array( 'Comoros', '269' ),
			'CG' => array( 'Congo', '242' ),
			'CD' => array( 'Congo (DRC)', '243' ),
			'CK' => array( 'Cook Islands', '682' ),
			'CR' => array( 'Costa Rica', '506' ),
			'CI' => array( 'Côte d\'Ivoire', '225' ),
			'HR' => array( 'Croatia', '385' ),
			'CU' => array( 'Cuba', '53' ),
			'CW' => array( 'Curaçao', '599' ),
			'CY' => array( 'Cyprus', '357' ),
			'CZ' => array( 'Czechia', '420' ),
			'DK' => array( 'Denmark', '45' ),
			'DJ' => array( 'Djibouti', '253' ),
			'DM' => array( 'Dominica', '1' ),
			'DO' => array( 'Dominican Republic', '1' ),
			'EC' => array( 'Ecuador', '593' ),
			'EG' => array( 'Egypt', '20' ),
			'SV' => array( 'El Salvador', '503' ),
			'GQ' => array( 'Equatorial Guinea', '240' ),
			'ER' => array( 'Eritrea', '291' ),
			'EE' => array( 'Estonia', '372' ),
			'SZ' => array( 'Eswatini', '268' ),
			'ET' => array( 'Ethiopia', '251' ),
			'FK' => array( 'Falkland Islands', '500' ),
			'FO' => array( 'Faroe Islands', '298' ),
			'FJ' => array( 'Fiji', '679' ),
			'FI' => array( 'Finland', '358' ),
			'FR' => array( 'France', '33' ),
			'GF' => array( 'French Guiana', '594' ),
			'PF' => array( 'French Polynesia', '689' ),
			'GA' => array( 'Gabon', '241' ),
			'GM' => array( 'Gambia', '220' ),
			'GE' => array( 'Georgia', '995' ),
			'DE' => array( 'Germany', '49' ),
			'GH' => array( 'Ghana', '233' ),
			'GI' => array( 'Gibraltar', '350' ),
			'GR' => array( 'Greece', '30' ),
			'GL' => array( 'Greenland', '299' ),
			'GD' => array( 'Grenada', '1' ),
			'GP' => array( 'Guadeloupe', '590' ),
			'GU' => array( 'Guam', '1' ),
			'GT' => array( 'Guatemala', '502' ),
			'GG' => array( 'Guernsey', '44' ),
			'GN' => array( 'Guinea', '224' ),
			'GW' => array( 'Guinea-Bissau', '245' ),
			'GY' => array( 'Guyana', '592' ),
			'HT' => array( 'Haiti', '509' ),
			'HN' => array( 'Honduras', '504' ),
			'HK' => array( 'Hong Kong', '852' ),
			'HU' => array( 'Hungary', '36' ),
			'IS' => array( 'Iceland', '354' ),
			'IN' => array( 'India', '91' ),
			'ID' => array( 'Indonesia', '62' ),
			'IR' => array( 'Iran', '98' ),
			'IQ' => array( 'Iraq', '964' ),
			'IE' => array( 'Ireland', '353' ),
			'IM' => array( 'Isle of Man', '44' ),
			'IL' => array( 'Israel', '972' ),
			'IT' => array( 'Italy', '39' ),
			'JM' => array( 'Jamaica', '1' ),
			'JP' => array( 'Japan', '81' ),
			'JE' => array( 'Jersey', '44' ),
			'JO' => array( 'Jordan', '962' ),
			'KZ' => array( 'Kazakhstan', '7' ),
			'KE' => array( 'Kenya', '254' ),
			'KI' => array( 'Kiribati', '686' ),
			'XK' => array( 'Kosovo', '383' ),
			'KW' => array( 'Kuwait', '965' ),
			'KG' => array( 'Kyrgyzstan', '996' ),
			'LA' => array( 'Laos', '856' ),
			'LV' => array( 'Latvia', '371' ),
			'LB' => array( 'Lebanon', '961' ),
			'LS' => array( 'Lesotho', '266' ),
			'LR' => array( 'Liberia', '231' ),
			'LY' => array( 'Libya', '218' ),
			'LI' => array( 'Liechtenstein', '423' ),
			'LT' => array( 'Lithuania', '370' ),
			'LU' => array( 'Luxembourg', '352' ),
			'MO' => array( 'Macao', '853' ),
			'MG' => array( 'Madagascar', '261' ),
			'MW' => array( 'Malawi', '265' ),
			'MY' => array( 'Malaysia', '60' ),
			'MV' => array( 'Maldives', '960' ),
			'ML' => array( 'Mali', '223' ),
			'MT' => array( 'Malta', '356' ),
			'MH' => array( 'Marshall Islands', '692' ),
			'MQ' => array( 'Martinique', '596' ),
			'MR' => array( 'Mauritania', '222' ),
			'MU' => array( 'Mauritius', '230' ),
			'YT' => array( 'Mayotte', '262' ),
			'MX' => array( 'Mexico', '52' ),
			'FM' => array( 'Micronesia', '691' ),
			'MD' => array( 'Moldova', '373' ),
			'MC' => array( 'Monaco', '377' ),
			'MN' => array( 'Mongolia', '976' ),
			'ME' => array( 'Montenegro', '382' ),
			'MS' => array( 'Montserrat', '1' ),
			'MA' => array( 'Morocco', '212' ),
			'MZ' => array( 'Mozambique', '258' ),
			'MM' => array( 'Myanmar', '95' ),
			'NA' => array( 'Namibia', '264' ),
			'NR' => array( 'Nauru', '674' ),
			'NP' => array( 'Nepal', '977' ),
			'NL' => array( 'Netherlands', '31' ),
			'NC' => array( 'New Caledonia', '687' ),
			'NZ' => array( 'New Zealand', '64' ),
			'NI' => array( 'Nicaragua', '505' ),
			'NE' => array( 'Niger', '227' ),
			'NG' => array( 'Nigeria', '234' ),
			'NU' => array( 'Niue', '683' ),
			'NF' => array( 'Norfolk Island', '672' ),
			'KP' => array( 'North Korea', '850' ),
			'MK' => array( 'North Macedonia', '389' ),
			'MP' => array( 'Northern Mariana Islands', '1' ),
			'NO' => array( 'Norway', '47' ),
			'OM' => array( 'Oman', '968' ),
			'PK' => array( 'Pakistan', '92' ),
			'PW' => array( 'Palau', '680' ),
			'PS' => array( 'Palestine', '970' ),
			'PA' => array( 'Panama', '507' ),
			'PG' => array( 'Papua New Guinea', '675' ),
			'PY' => array( 'Paraguay', '595' ),
			'PE' => array( 'Peru', '51' ),
			'PH' => array( 'Philippines', '63' ),
			'PL' => array( 'Poland', '48' ),
			'PT' => array( 'Portugal', '351' ),
			'PR' => array( 'Puerto Rico', '1' ),
			'QA' => array( 'Qatar', '974' ),
			'RE' => array( 'Réunion', '262' ),
			'RO' => array( 'Romania', '40' ),
			'RU' => array( 'Russia', '7' ),
			'RW' => array( 'Rwanda', '250' ),
			'BL' => array( 'Saint Barthélemy', '590' ),
			'SH' => array( 'Saint Helena', '290' ),
			'KN' => array( 'Saint Kitts and Nevis', '1' ),
			'LC' => array( 'Saint Lucia', '1' ),
			'MF' => array( 'Saint Martin', '590' ),
			'PM' => array( 'Saint Pierre and Miquelon', '508' ),
			'VC' => array( 'Saint Vincent and the Grenadines', '1' ),
			'WS' => array( 'Samoa', '685' ),
			'SM' => array( 'San Marino', '378' ),
			'ST' => array( 'São Tomé and Príncipe', '239' ),
			'SA' => array( 'Saudi Arabia', '966' ),
			'SN' => array( 'Senegal', '221' ),
			'RS' => array( 'Serbia', '381' ),
			'SC' => array( 'Seychelles', '248' ),
			'SL' => array( 'Sierra Leone', '232' ),
			'SG' => array( 'Singapore', '65' ),
			'SX' => array( 'Sint Maarten', '1' ),
			'SK' => array( 'Slovakia', '421' ),
			'SI' => array( 'Slovenia', '386' ),
			'SB' => array( 'Solomon Islands', '677' ),
			'SO' => array( 'Somalia', '252' ),
			'ZA' => array( 'South Africa', '27' ),
			'KR' => array( 'South Korea', '82' ),
			'SS' => array( 'South Sudan', '211' ),
			'ES' => array( 'Spain', '34' ),
			'LK' => array( 'Sri Lanka', '94' ),
			'SD' => array( 'Sudan', '249' ),
			'SR' => array( 'Suriname', '597' ),
			'SJ' => array( 'Svalbard and Jan Mayen', '47' ),
			'SE' => array( 'Sweden', '46' ),
			'CH' => array( 'Switzerland', '41' ),
			'SY' => array( 'Syria', '963' ),
			'TW' => array( 'Taiwan', '886' ),
			'TJ' => array( 'Tajikistan', '992' ),
			'TZ' => array( 'Tanzania', '255' ),
			'TH' => array( 'Thailand', '66' ),
			'TL' => array( 'Timor-Leste', '670' ),
			'TG' => array( 'Togo', '228' ),
			'TK' => array( 'Tokelau', '690' ),
			'TO' => array( 'Tonga', '676' ),
			'TT' => array( 'Trinidad and Tobago', '1' ),
			'TN' => array( 'Tunisia', '216' ),
			'TR' => array( 'Turkey', '90' ),
			'TM' => array( 'Turkmenistan', '993' ),
			'TC' => array( 'Turks and Caicos Islands', '1' ),
			'TV' => array( 'Tuvalu', '688' ),
			'UG' => array( 'Uganda', '256' ),
			'UA' => array( 'Ukraine', '380' ),
			'AE' => array( 'United Arab Emirates', '971' ),
			'GB' => array( 'United Kingdom', '44' ),
			'US' => array( 'United States', '1' ),
			'UY' => array( 'Uruguay', '598' ),
			'VI' => array( 'US Virgin Islands', '1' ),
			'UZ' => array( 'Uzbekistan', '998' ),
			'VU' => array( 'Vanuatu', '678' ),
			'VA' => array( 'Vatican City', '39' ),
			'VE' => array( 'Venezuela', '58' ),
			'VN' => array( 'Vietnam', '84' ),
			'WF' => array( 'Wallis and Futuna', '681' ),
			'EH' => array( 'Western Sahara', '212' ),
			'YE' => array( 'Yemen', '967' ),
			'ZM' => array( 'Zambia', '260' ),
			'ZW' => array( 'Zimbabwe', '263' ),
		);
	}

	/**
	 * How long numbers are in countries where it is known well, counted
	 * after the country code and without the leading 0 dialled at home:
	 * code => array( fewest digits, most digits, pattern, keeps its leading 0 ).
	 * Other countries take any number of 4 to 15 digits in all.
	 *
	 * @return array[]
	 */
	public static function rules() {
		$nanp  = array( 10, 10, '[2-9]\d{2}[2-9]\d{6}', 0 );
		$rules = array(
			'LK' => array( 9, 9, '[1-9]\d{8}', 0 ),
			'IN' => array( 10, 10, '[1-9]\d{9}', 0 ),
			'GB' => array( 9, 10, '', 0 ),
			'GG' => array( 9, 10, '', 0 ),
			'JE' => array( 9, 10, '', 0 ),
			'IM' => array( 9, 10, '', 0 ),
			'AU' => array( 9, 9, '', 0 ),
			'NZ' => array( 8, 10, '', 0 ),
			'AE' => array( 8, 9, '', 0 ),
			'SA' => array( 8, 9, '', 0 ),
			'QA' => array( 8, 8, '', 0 ),
			'KW' => array( 8, 8, '', 0 ),
			'OM' => array( 8, 8, '', 0 ),
			'BH' => array( 8, 8, '', 0 ),
			'MV' => array( 7, 7, '', 0 ),
			'SG' => array( 8, 8, '', 0 ),
			'MY' => array( 8, 10, '', 0 ),
			'JP' => array( 9, 10, '', 0 ),
			'KR' => array( 8, 10, '', 0 ),
			'CN' => array( 10, 11, '', 0 ),
			'HK' => array( 8, 8, '', 0 ),
			'MO' => array( 8, 8, '', 0 ),
			'TW' => array( 8, 9, '', 0 ),
			'TH' => array( 8, 9, '', 0 ),
			'VN' => array( 9, 10, '', 0 ),
			'PH' => array( 8, 10, '', 0 ),
			'ID' => array( 8, 12, '', 0 ),
			'PK' => array( 9, 10, '', 0 ),
			'BD' => array( 10, 10, '', 0 ),
			'NP' => array( 8, 10, '', 0 ),
			'DE' => array( 6, 13, '', 0 ),
			'FR' => array( 9, 9, '', 0 ),
			'IT' => array( 6, 11, '', 1 ),
			'SM' => array( 6, 10, '', 1 ),
			'VA' => array( 6, 11, '', 1 ),
			'ES' => array( 9, 9, '', 0 ),
			'PT' => array( 9, 9, '', 0 ),
			'NL' => array( 9, 9, '', 0 ),
			'BE' => array( 8, 9, '', 0 ),
			'CH' => array( 9, 9, '', 0 ),
			'AT' => array( 7, 13, '', 0 ),
			'SE' => array( 7, 10, '', 0 ),
			'NO' => array( 8, 8, '', 0 ),
			'DK' => array( 8, 8, '', 0 ),
			'FI' => array( 5, 12, '', 0 ),
			'IE' => array( 7, 9, '', 0 ),
			'GR' => array( 10, 10, '', 0 ),
			'PL' => array( 9, 9, '', 0 ),
			'CZ' => array( 9, 9, '', 0 ),
			'HU' => array( 8, 9, '', 0 ),
			'RO' => array( 9, 9, '', 0 ),
			'RU' => array( 10, 10, '', 0 ),
			'KZ' => array( 10, 10, '', 0 ),
			'UA' => array( 9, 9, '', 0 ),
			'TR' => array( 10, 10, '', 0 ),
			'IL' => array( 8, 9, '', 0 ),
			'EG' => array( 9, 10, '', 0 ),
			'ZA' => array( 9, 9, '', 0 ),
			'NG' => array( 8, 10, '', 0 ),
			'KE' => array( 9, 9, '', 0 ),
			'BR' => array( 10, 11, '', 0 ),
			'MX' => array( 10, 10, '', 0 ),
			'AR' => array( 10, 10, '', 0 ),
			'CL' => array( 9, 9, '', 0 ),
			'CO' => array( 10, 10, '', 0 ),
		);

		foreach ( self::raw() as $code => $country ) {
			if ( '1' === $country[1] ) {
				$rules[ $code ] = $nanp;
			}
		}

		return $rules;
	}

	/**
	 * Every country with its calling code and number rules.
	 *
	 * @return array[] Code => 'name', 'dial', 'min', 'max', 'pattern' and 'zero'.
	 */
	public static function countries() {
		$rules     = self::rules();
		$countries = array();

		foreach ( self::raw() as $code => $country ) {
			$rule               = isset( $rules[ $code ] ) ? $rules[ $code ] : array( 4, 15 - strlen( $country[1] ), '', 0 );
			$countries[ $code ] = array(
				'name'    => $country[0],
				'dial'    => $country[1],
				'min'     => $rule[0],
				'max'     => $rule[1],
				'pattern' => $rule[2],
				'zero'    => (bool) $rule[3],
			);
		}

		/**
		 * Filters the countries offered beside phone numbers, e.g. to change a number rule.
		 *
		 * @param array[] $countries Code => 'name', 'dial', 'min', 'max', 'pattern' and 'zero'.
		 */
		return apply_filters( 'crc_re_phone_countries', $countries );
	}

	/**
	 * Reads a typed phone number. "077 123 4567", "77 123 4567",
	 * "94 77 123 4567", "+94 77 123 4567" and "0094 77 123 4567" with Sri
	 * Lanka chosen are all +94771234567. A number typed with "+" or "00"
	 * keeps its own country, whatever is chosen.
	 *
	 * @param string $country Chosen country code, e.g. "LK".
	 * @param string $typed   Number as typed.
	 * @return array 'number' (e.g. +94771234567) and 'country'; or 'error' ("empty" or "invalid") and 'country'.
	 */
	public static function parse( $country, $typed ) {
		$countries = self::countries();
		$country   = is_string( $country ) && isset( $countries[ $country ] ) ? $country : self::DEFAULT_COUNTRY;
		$typed     = is_scalar( $typed ) ? trim( str_replace( "\xC2\xA0", ' ', (string) $typed ) ) : '';

		if ( '' === $typed ) {
			return array(
				'error'   => 'empty',
				'country' => $country,
			);
		}

		$invalid = array(
			'error'   => 'invalid',
			'country' => $country,
		);

		// Only digits, spaces, brackets, dots, dashes, slashes and a leading +.
		if ( ! preg_match( '/^\+?[\d\s().\/-]+$/', $typed ) ) {
			return $invalid;
		}

		$digits = preg_replace( '/\D/', '', $typed );

		if ( '+' === $typed[0] || 0 === strpos( $digits, '00' ) ) {
			$digits = '+' === $typed[0] ? $digits : substr( $digits, 2 );
			$found  = self::country_for( $digits, $country, $countries );

			if ( '' === $found ) {
				return $invalid;
			}

			$country  = $found;
			$national = (string) substr( $digits, strlen( $countries[ $country ]['dial'] ) );

			// "+94 (0)77…" or "+94 077…": the 0 dialled at home doesn't belong after the country code.
			if ( ! $countries[ $country ]['zero'] && '0' === substr( $national, 0, 1 ) && ! self::fits( $countries[ $country ], $national ) ) {
				$national = (string) substr( $national, 1 );
			}
		} else {
			$national = self::national( $countries[ $country ], $digits );
		}

		if ( ! self::fits( $countries[ $country ], $national ) ) {
			$invalid['country'] = $country;

			return $invalid;
		}

		return array(
			'number'  => '+' . $countries[ $country ]['dial'] . $national,
			'country' => $country,
		);
	}

	/**
	 * A full number written for reading: +94 77 123 4567 in Sri Lanka,
	 * +1 212 555 0199 in North America, otherwise the country code and the
	 * rest, e.g. +44 7911123456.
	 *
	 * @param string $number  Number in full international form, e.g. +94771234567.
	 * @param string $country Country code, e.g. "LK".
	 * @return string
	 */
	public static function display( $number, $country ) {
		$countries = self::countries();
		$number    = (string) $number;

		if ( ! isset( $countries[ $country ] ) || 0 !== strpos( $number, '+' . $countries[ $country ]['dial'] ) ) {
			return $number;
		}

		$dial     = $countries[ $country ]['dial'];
		$national = substr( $number, strlen( $dial ) + 1 );

		if ( 'LK' === $country && 9 === strlen( $national ) ) {
			$national = substr( $national, 0, 2 ) . ' ' . substr( $national, 2, 3 ) . ' ' . substr( $national, 5 );
		} elseif ( '1' === $dial && 10 === strlen( $national ) ) {
			$national = substr( $national, 0, 3 ) . ' ' . substr( $national, 3, 3 ) . ' ' . substr( $national, 6 );
		}

		return '+' . $dial . ' ' . $national;
	}

	/**
	 * The countries for the browser, which checks numbers the same way:
	 * code => array( name, calling code, fewest digits, most digits,
	 * pattern, keeps its leading 0 ).
	 *
	 * @return array[]
	 */
	public static function script_data() {
		$data = array();

		foreach ( self::countries() as $code => $country ) {
			$data[ $code ] = array( $country['name'], $country['dial'], $country['min'], $country['max'], $country['pattern'], $country['zero'] ? 1 : 0 );
		}

		return $data;
	}

	/**
	 * Whether a number, after the country code, fits a country's rules.
	 *
	 * @param array  $country  Country from countries().
	 * @param string $national Digits after the country code.
	 * @return bool
	 */
	public static function fits( array $country, $national ) {
		$length = strlen( $national );

		return '' !== $national && ctype_digit( $national ) && $length >= $country['min'] && $length <= $country['max']
			&& ( '' === $country['pattern'] || 1 === preg_match( '/^(?:' . $country['pattern'] . ')$/', $national ) );
	}

	/**
	 * A number typed the way it's dialled at home, without its country code:
	 * the leading 0 (1 in North America, 8 in Russia) goes; a country code
	 * typed without "+" goes too.
	 *
	 * @param array  $country Country from countries().
	 * @param string $digits  Digits typed.
	 * @return string
	 */
	private static function national( array $country, $digits ) {
		$home = $digits;

		if ( ! $country['zero'] && '0' === substr( $digits, 0, 1 ) ) {
			$home = substr( $digits, 1 );
		} elseif ( 11 === strlen( $digits ) && ( ( '1' === $country['dial'] && '1' === $digits[0] ) || ( '7' === $country['dial'] && '8' === $digits[0] ) ) ) {
			$home = substr( $digits, 1 );
		}

		$dial = $country['dial'];

		if ( ! self::fits( $country, $home ) && 0 === strpos( $digits, $dial ) && self::fits( $country, substr( $digits, strlen( $dial ) ) ) ) {
			return substr( $digits, strlen( $dial ) );
		}

		return $home;
	}

	/**
	 * The country of a number typed with "+": the longest calling code it
	 * starts with. With a code several countries share, the chosen country
	 * if it's one of them, or the main one.
	 *
	 * @param string  $digits    Digits after "+".
	 * @param string  $chosen    Chosen country code.
	 * @param array[] $countries Countries.
	 * @return string Country code, or an empty string.
	 */
	private static function country_for( $digits, $chosen, array $countries ) {
		for ( $length = 3; $length >= 1; $length-- ) {
			$dial = substr( $digits, 0, $length );

			if ( isset( $countries[ $chosen ] ) && $countries[ $chosen ]['dial'] === $dial ) {
				return $chosen;
			}

			if ( isset( self::MAIN[ $dial ] ) && isset( $countries[ self::MAIN[ $dial ] ] ) ) {
				return self::MAIN[ $dial ];
			}

			foreach ( $countries as $code => $country ) {
				if ( $country['dial'] === $dial ) {
					return $code;
				}
			}
		}

		return '';
	}
}
