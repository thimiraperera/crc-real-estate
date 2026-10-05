<?php
/**
 * A listing as one row of a spreadsheet, and back.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Faq;
use CRC\RealEstate\Sections\Features;
use CRC\RealEstate\Sections\Gallery;
use CRC\RealEstate\Sections\Location;
use CRC\RealEstate\Sections\Overview;
use CRC\RealEstate\Sections\Price_Card;

defined( 'ABSPATH' ) || exit;

/**
 * The columns of the listings CSV file: reading a listing into a row, and
 * writing a row onto a listing with the same checks as the listing screen.
 *
 * Lists in a cell are separated by |. Groups each take a line of their own,
 * written as "Group title: first | second"; details are "Label = Value".
 * Photos are links; the importer downloads them.
 */
final class Listing_Data {

	/**
	 * Statuses a row can ask for.
	 */
	const STATUSES = array( 'draft', 'pending', 'publish', 'private' );

	/**
	 * Every column, in order.
	 *
	 * @param int $faqs How many sets of question columns.
	 * @return array[] Name => array( 'section' => section title, 'help' => what to put in it ).
	 */
	public static function columns( $faqs = 2 ) {
		$columns = array();
		$add     = function ( $name, $section, $help ) use ( &$columns ) {
			$columns[ $name ] = array(
				'section' => $section,
				'help'    => $help,
			);
		};

		$listing  = __( 'Listing', 'crc-real-estate' );
		$photos   = __( 'Photos', 'crc-real-estate' );
		$price    = __( 'Price', 'crc-real-estate' );
		$contact  = __( 'Contact buttons', 'crc-real-estate' );
		$overview = __( 'Property overview', 'crc-real-estate' );
		$features = __( 'Property features', 'crc-real-estate' );
		$location = __( 'Location', 'crc-real-estate' );
		$faq      = __( 'FAQs', 'crc-real-estate' );
		$owner    = __( 'Owner (private)', 'crc-real-estate' );

		$add( 'id', $listing, __( 'Leave it empty to add a new listing. To change a listing that is already on the site, put its ID here (hover over the listing in All Listings to see it). When a row has an ID, empty cells leave that part of the listing as it is.', 'crc-real-estate' ) );
		$add( 'title', $listing, __( 'The listing\'s title, for example Bare land for sale in Galle. Leave it empty for a new listing to name it from its category, property type, bedrooms and district, the way the listing screen suggests.', 'crc-real-estate' ) );
		$add( 'status', $listing, __( 'draft (the default), publish, pending or private. A listing is only published when it has a main photo and a category; otherwise it stays a draft.', 'crc-real-estate' ) );
		/* translators: %s: category slugs, e.g. "lands, properties-for-sale". */
		$add( 'category', $listing, sprintf( __( 'One of: %s. The category\'s name works too.', 'crc-real-estate' ), implode( ', ', array_keys( Taxonomy::terms() ) ) ) );
		$add( 'district', $listing, __( 'One of Sri Lanka\'s 25 districts, for example Galle or Nuwara Eliya. It shows under the title and goes into the suggested name.', 'crc-real-estate' ) );
		$add( 'description', $listing, __( 'The description shown on the listing page. Leave an empty line between paragraphs.', 'crc-real-estate' ) );

		$add( 'main_photo', $photos, __( 'A link to the main photo, starting with https://. The photo is downloaded into the Media Library. Google Drive and Dropbox share links work when the file is shared with anyone who has the link.', 'crc-real-estate' ) );
		$add( 'more_photos', $photos, __( 'Links to more photos, in the order they should show, separated by |.', 'crc-real-estate' ) );

		$add( 'price', $price, __( 'The price in rupees, numbers only, for example 12500000 or 12,500,000. For a property for rent, the rent for one month.', 'crc-real-estate' ) );
		$add( 'price_per_perch', $price, __( 'For land: the price of one perch, numbers only.', 'crc-real-estate' ) );

		$add( 'phone', $contact, __( 'This listing\'s own number for the Call button, with the country code, for example +94 77 123 4567. A new listing without one uses the number from Settings. For a listing already on the site, an empty cell keeps the number it has; to go back to the number from Settings, empty it in the Contact buttons box on the listing screen.', 'crc-real-estate' ) );
		$add( 'whatsapp', $contact, __( 'This listing\'s own WhatsApp number, with the country code. A new listing without one uses the number from Settings. For a listing already on the site, an empty cell keeps the number it has.', 'crc-real-estate' ) );
		$add( 'call_text', $contact, __( 'This listing\'s own Call button text. Write {number} where the number goes.', 'crc-real-estate' ) );
		$add( 'message_text', $contact, __( 'This listing\'s own Message button text. Write {number} where the number goes.', 'crc-real-estate' ) );

		foreach ( Overview::fields() as $name => $field ) {
			$help = $field['help'];

			if ( 'select' === $field['type'] && $field['options'] ) {
				/* translators: %s: choices, e.g. "Available Now, Sold". */
				$help .= ' ' . sprintf( __( 'One of: %s.', 'crc-real-estate' ), implode( ', ', $field['options'] ) );
			}

			$add( self::field_column( $name ), $overview, $help );
		}

		foreach ( self::detail_items() as $name => $item ) {
			$add( self::detail_column( $name ), $overview, self::detail_help( $item ) );

			if ( $item['units'] ) {
				$units = array();

				foreach ( $item['units'] as $key => $unit ) {
					$units[] = $key . ' (' . Overview::unit_name( $unit ) . ')';
				}

				/* translators: 1: detail name, 2: units, e.g. "perches, acres". */
				$add( self::detail_column( $name ) . '_unit', $overview, sprintf( __( 'The unit of %1$s: %2$s. For a new listing, empty means the first one. For a listing already on the site, an empty cell keeps the unit it has, so fill it in when you change the number.', 'crc-real-estate' ), $item['label'], implode( ', ', $units ) ) );
			}
		}

		$add( 'more_details', $overview, __( 'Details of your own added to the ready-made groups: a line for each group, written as Group title: Label = Value | Label = Value. For example Access and road: Bus route = 200 m.', 'crc-real-estate' ) );
		$add( 'own_detail_groups', $overview, __( 'Groups of your own: a line for each group, written as Group title: Label = Value | Label = Value.', 'crc-real-estate' ) );

		/* translators: %s: examples of ready-made features. */
		$add( 'features', $features, sprintf( __( 'The ready-made features this listing has, separated by |, for example %s. Features that aren\'t ready-made are kept in a group of their own.', 'crc-real-estate' ), implode( ' | ', array_slice( array_values( Features::common_features() ), 0, 3 ) ) ) );
		$add( 'more_features', $features, __( 'Features of your own added to the ready-made groups: a line for each group, written as Group title: first | second.', 'crc-real-estate' ) );
		$add( 'own_feature_groups', $features, __( 'Groups of your own: a line for each group, written as Group title: first | second.', 'crc-real-estate' ) );

		$add( 'latitude', $location, __( 'The exact place\'s latitude, for example 6.0535. You can also put both numbers here, as 6.0535, 80.2210. The site only ever shows the area around it.', 'crc-real-estate' ) );
		$add( 'longitude', $location, __( 'The exact place\'s longitude, for example 80.2210.', 'crc-real-estate' ) );
		$add( 'show_map', $location, __( 'yes or no: whether the listing page shows the map.', 'crc-real-estate' ) );
		$add( 'google_maps_link', $location, __( 'A Google Maps link to the place, kept for your team. It never shows on the site.', 'crc-real-estate' ) );

		$add( 'show_category_faqs', $faq, __( 'yes or no: whether the listing shows its category\'s questions before its own.', 'crc-real-estate' ) );

		for ( $i = 1; $i <= max( 1, (int) $faqs ); $i++ ) {
			/* translators: 1: question number, 2: the next question number. */
			$add( 'faq_' . $i . '_question', $faq, sprintf( __( 'Question %1$d of the listing\'s own questions. Add more with faq_%2$d_question, faq_%2$d_answer and so on. For a listing already on the site, only the questions you fill in change; the others stay as they are.', 'crc-real-estate' ), $i, max( 3, (int) $faqs + 1 ) ) );
			/* translators: %d: question number. */
			$add( 'faq_' . $i . '_answer', $faq, sprintf( __( 'The answer to question %d. Leave an empty line between paragraphs.', 'crc-real-estate' ), $i ) );
			/* translators: %d: question number. */
			$add( 'faq_' . $i . '_link_text', $faq, sprintf( __( 'Optional link text under answer %d, for example Enquire about this land.', 'crc-real-estate' ), $i ) );
			/* translators: %d: question number. */
			$add( 'faq_' . $i . '_link', $faq, sprintf( __( 'Optional link for answer %d, for example #crc-inquiry-1 for the inquiry form, or a web address.', 'crc-real-estate' ), $i ) );
		}

		foreach ( Owner::labels() as $key => $label ) {
			/* translators: %s: detail, e.g. "Phone". */
			$add( 'owner_' . $key, $owner, sprintf( __( 'The owner\'s %s. Kept for your team only; it never shows on the site.', 'crc-real-estate' ), strtolower( $label ) ) );
		}

		return $columns;
	}

	/**
	 * The ready-made details that are typed in. Linked ones (read from the
	 * Price box) and worked-out ones are left out.
	 *
	 * @return array[] Name => detail from Overview::common_items().
	 */
	public static function detail_items() {
		return array_filter(
			Overview::common_items(),
			function ( $item ) {
				return 'linked' !== $item['type'];
			}
		);
	}

	/**
	 * A main detail's column name.
	 *
	 * @param string $name Detail name.
	 * @return string
	 */
	private static function field_column( $name ) {
		// Lower-case, as column names are read from files.
		$name = strtolower( (string) $name );

		return in_array( $name, self::fixed_columns(), true ) ? 'overview_' . $name : $name;
	}

	/**
	 * A ready-made detail's column name.
	 *
	 * @param string $name Detail name.
	 * @return string
	 */
	private static function detail_column( $name ) {
		$fields = array_map( 'strtolower', array_keys( Overview::fields() ) );
		$name   = strtolower( (string) $name );

		return in_array( $name, self::fixed_columns(), true ) || in_array( $name, $fields, true ) ? 'detail_' . $name : $name;
	}

	/**
	 * Column names that details can't take.
	 *
	 * @return string[]
	 */
	private static function fixed_columns() {
		return array( 'id', 'title', 'status', 'category', 'district', 'description', 'main_photo', 'more_photos', 'price', 'price_per_perch', 'phone', 'whatsapp', 'call_text', 'message_text', 'more_details', 'own_detail_groups', 'features', 'more_features', 'own_feature_groups', 'latitude', 'longitude', 'show_map', 'google_maps_link', 'show_category_faqs' );
	}

	/**
	 * What to put in a ready-made detail's column.
	 *
	 * @param array $item Detail.
	 * @return string
	 */
	private static function detail_help( array $item ) {
		if ( 'select' === $item['type'] ) {
			$choices = array();

			foreach ( $item['options'] as $key => $label ) {
				$choices[] = $key . ' (' . $label . ')';
			}

			/* translators: 1: detail name, 2: choices. */
			return sprintf( __( '%1$s: one of %2$s.', 'crc-real-estate' ), $item['label'], implode( ', ', $choices ) );
		}

		if ( 'number' === $item['type'] ) {
			$label = $item['label'];

			// A number with one fixed unit says which, e.g. "Advance payment (in months)".
			if ( $item['unit'] ) {
				/* translators: 1: detail name, 2: unit, e.g. "months". */
				$label = sprintf( __( '%1$s (in %2$s)', 'crc-real-estate' ), $label, Overview::unit_name( $item['unit'] ) );
			}

			/* translators: %s: detail name. */
			return sprintf( $item['decimals'] ? __( '%s: a number, for example 12 or 1.5.', 'crc-real-estate' ) : __( '%s: a whole number, for example 3.', 'crc-real-estate' ), $label );
		}

		if ( 'money' === $item['type'] ) {
			$help = sprintf(
				/* translators: %s: detail name. */
				__( '%s: an amount in rupees, numbers only.', 'crc-real-estate' ),
				$item['label']
			);

			if ( '%s' !== $item['format'] ) {
				/* translators: %s: example, e.g. "Rs. 15,000 per month". */
				$help .= ' ' . sprintf( __( 'It shows like this: %s.', 'crc-real-estate' ), sprintf( $item['format'], Price_Card::money( '15000' ) ) );
			}

			return $help;
		}

		return $item['label'];
	}

	/**
	 * A listing as a row, keyed by column name. Cells are text; the ones
	 * the listing doesn't fill are empty.
	 *
	 * @param int $post_id Listing ID.
	 * @return string[]
	 */
	public static function row( $post_id ) {
		$post     = get_post( $post_id );
		$category = Taxonomy::listing_category( $post_id );
		$district = District::of( $post_id );
		$row      = array(
			'id'          => (string) $post->ID,
			'title'       => (string) $post->post_title,
			'status'      => (string) $post->post_status,
			'category'    => $category ? $category['slug'] : '',
			'district'    => $district ? $district['name'] : '',
			'description' => (string) $post->post_content,
		);

		$photos            = self::photo_ids( $post_id );
		$row['main_photo'] = $photos['main'] ? (string) wp_get_attachment_url( $photos['main'] ) : '';
		$row['more_photos'] = implode( ' | ', array_filter( array_map( 'wp_get_attachment_url', $photos['more'] ) ) );

		$row['price']           = Price_Card::price( $post_id );
		$row['price_per_perch'] = Price_Card::price_per_perch( $post_id );
		$row['phone']           = (string) get_post_meta( $post_id, Settings::PHONE_META, true );
		$row['whatsapp']        = (string) get_post_meta( $post_id, Settings::WHATSAPP_META, true );
		$row['call_text']       = (string) get_post_meta( $post_id, Settings::CALL_TEXT_META, true );
		$row['message_text']    = (string) get_post_meta( $post_id, Settings::MESSAGE_TEXT_META, true );

		foreach ( Overview::fields() as $name => $field ) {
			$row[ self::field_column( $name ) ] = (string) get_post_meta( $post_id, $field['meta'], true );
		}

		foreach ( self::detail_items() as $name => $item ) {
			$row[ self::detail_column( $name ) ] = (string) get_post_meta( $post_id, $item['meta'], true );

			if ( $item['units'] ) {
				$row[ self::detail_column( $name ) . '_unit' ] = (string) get_post_meta( $post_id, $item['meta'] . '_unit', true );
			}
		}

		$titles = array();

		foreach ( Overview::common_groups() as $key => $group ) {
			$titles[ $key ] = $group['title'];
		}

		$lines = array();

		foreach ( Overview::extras( $post_id ) as $key => $items ) {
			$lines[] = self::group_line( isset( $titles[ $key ] ) ? $titles[ $key ] : $key, $items, true );
		}

		$row['more_details'] = implode( "\n", $lines );
		$lines               = array();

		foreach ( Overview::groups( $post_id ) as $group ) {
			$lines[] = self::group_line( $group['title'], $group['items'], true );
		}

		$row['own_detail_groups'] = implode( "\n", $lines );

		$labels          = Features::common_features();
		$row['features'] = implode(
			' | ',
			array_map(
				function ( $key ) use ( $labels ) {
					return addcslashes( isset( $labels[ $key ] ) ? $labels[ $key ] : $key, '\\|' );
				},
				Features::ticked( $post_id )
			)
		);

		$feature_titles = array();

		foreach ( Features::common_groups() as $key => $group ) {
			$feature_titles[ $key ] = $group['title'];
		}

		$lines = array();

		foreach ( Features::extras( $post_id ) as $key => $items ) {
			$lines[] = self::group_line( isset( $feature_titles[ $key ] ) ? $feature_titles[ $key ] : $key, $items );
		}

		$row['more_features'] = implode( "\n", $lines );
		$lines                = array();

		foreach ( Features::groups( $post_id ) as $group ) {
			$lines[] = self::group_line( $group['title'], $group['items'] );
		}

		$row['own_feature_groups'] = implode( "\n", $lines );

		$place                   = Location::exact( $post_id );
		$row['latitude']         = $place ? self::coordinate( $place['lat'] ) : '';
		$row['longitude']        = $place ? self::coordinate( $place['lng'] ) : '';
		$row['show_map']         = '1' === (string) get_post_meta( $post_id, Location::HIDDEN_META, true ) ? 'no' : 'yes';
		$row['google_maps_link'] = (string) get_post_meta( $post_id, Location::GOOGLE_META, true );

		$row['show_category_faqs'] = Faq::shows_category( $post_id ) ? 'yes' : 'no';

		foreach ( Faq::own_items( $post_id ) as $i => $item ) {
			$n                             = $i + 1;
			$row[ 'faq_' . $n . '_question' ]  = $item['question'];
			$row[ 'faq_' . $n . '_answer' ]    = $item['answer'];
			$row[ 'faq_' . $n . '_link_text' ] = $item['link_text'];
			$row[ 'faq_' . $n . '_link' ]      = $item['link_url'];
		}

		foreach ( Owner::get( $post_id ) as $key => $value ) {
			$row[ 'owner_' . $key ] = $value;
		}

		return $row;
	}

	/**
	 * A listing's photos: the main one, then the others in order, each once.
	 *
	 * @param int $post_id Listing ID.
	 * @return array 'main' (attachment ID or 0) and 'more' (attachment IDs).
	 */
	public static function photo_ids( $post_id ) {
		$main = (int) get_post_thumbnail_id( $post_id );
		$more = array_values(
			array_filter(
				array_unique( Gallery::gallery_ids( $post_id ) ),
				function ( $id ) use ( $main ) {
					return $id !== $main && wp_attachment_is_image( $id );
				}
			)
		);

		return array(
			'main' => $main > 0 && wp_attachment_is_image( $main ) ? $main : 0,
			'more' => $more,
		);
	}

	/**
	 * Writes a row's filled-in cells onto a listing. Empty cells and columns
	 * the file doesn't have leave the listing as it is. The title, text,
	 * status and photos are left to the importer.
	 *
	 * @param int      $post_id Listing ID.
	 * @param string[] $cells   Row, keyed by column name.
	 * @return string[] Warnings about cells that couldn't be used.
	 */
	public static function apply( $post_id, array $cells ) {
		$warnings = array();
		$filled   = function ( $name ) use ( $cells ) {
			return isset( $cells[ $name ] ) && '' !== trim( (string) $cells[ $name ] );
		};
		$cell     = function ( $name ) use ( $cells ) {
			return isset( $cells[ $name ] ) ? trim( (string) $cells[ $name ] ) : '';
		};

		if ( $filled( 'category' ) ) {
			$slug = self::category_slug( $cell( 'category' ) );
			$term = '' !== $slug ? get_term_by( 'slug', $slug, Taxonomy::NAME ) : null;

			if ( $term && ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, array( (int) $term->term_id ), Taxonomy::NAME );
			} else {
				/* translators: 1: what was written, 2: category slugs. */
				$warnings[] = sprintf( __( 'Category "%1$s" isn\'t one of the categories (%2$s).', 'crc-real-estate' ), $cell( 'category' ), implode( ', ', array_keys( Taxonomy::terms() ) ) );
			}
		}

		if ( $filled( 'district' ) ) {
			$term = District::term( District::find( $cell( 'district' ) ) );

			if ( $term ) {
				wp_set_object_terms( $post_id, array( (int) $term->term_id ), District::NAME );
			} else {
				/* translators: %s: what was written. */
				$warnings[] = sprintf( __( 'District "%s" isn\'t one of Sri Lanka\'s 25 districts, so it was left out. Write it like Galle or Nuwara Eliya.', 'crc-real-estate' ), $cell( 'district' ) );
			}
		}

		$category = Taxonomy::listing_category( $post_id );
		$slug     = $category ? $category['slug'] : '';
		$unseen   = array();

		// Price.
		foreach ( array(
			'price'           => Price_Card::PRICE_META,
			'price_per_perch' => Price_Card::PER_PERCH_META,
		) as $name => $meta ) {
			if ( $filled( $name ) ) {
				$amount = self::amount( $cell( $name ) );

				if ( '' !== $amount ) {
					update_post_meta( $post_id, $meta, $amount );
				} else {
					/* translators: 1: column, 2: what was written. */
					$warnings[] = sprintf( __( '%1$s "%2$s" isn\'t one amount in numbers, so it was left out. Write it like 12500000 or Rs. 12,500,000.', 'crc-real-estate' ), $name, $cell( $name ) );
				}
			}
		}

		// Contact buttons.
		foreach ( array(
			'phone'        => array( Settings::PHONE_META, 'sanitize_number' ),
			'whatsapp'     => array( Settings::WHATSAPP_META, 'sanitize_number' ),
			'call_text'    => array( Settings::CALL_TEXT_META, 'sanitize_text' ),
			'message_text' => array( Settings::MESSAGE_TEXT_META, 'sanitize_text' ),
		) as $name => $how ) {
			if ( $filled( $name ) ) {
				$value = call_user_func( array( Settings::class, $how[1] ), $cell( $name ) );

				// A number without a digit, such as "-" for "not known", isn't one.
				if ( 'sanitize_number' === $how[1] && ! preg_match( '/\d/', $value ) ) {
					$value = '';
				}

				if ( '' !== $value ) {
					update_post_meta( $post_id, $how[0], wp_slash( $value ) );
				} else {
					/* translators: 1: column, 2: what was written. */
					$warnings[] = sprintf( __( '%1$s "%2$s" couldn\'t be used, so it was left as it was.', 'crc-real-estate' ), $name, $cell( $name ) );
				}
			}
		}

		// Main overview details.
		foreach ( Overview::fields() as $name => $field ) {
			$column = self::field_column( $name );

			if ( ! $filled( $column ) ) {
				continue;
			}

			// A value saved before the detail became a list is kept while it isn't changed, as on the listing screen.
			$value = Overview::sanitize_main( $field, $cell( $column ), (string) get_post_meta( $post_id, $field['meta'], true ) );

			if ( '' !== $value ) {
				update_post_meta( $post_id, $field['meta'], wp_slash( $value ) );
			} else {
				/* translators: 1: detail, 2: what was written, 3: choices. */
				$warnings[] = sprintf( __( '%1$s "%2$s" isn\'t one of the choices (%3$s).', 'crc-real-estate' ), $field['label'], $cell( $column ), implode( ', ', $field['options'] ) );
			}
		}

		// Ready-made details.
		$groups = Overview::common_groups();

		foreach ( self::detail_items() as $name => $item ) {
			$column = self::detail_column( $name );

			if ( $filled( $column ) ) {
				$value = self::detail_value( $item, $cell( $column ) );

				if ( '' !== $value ) {
					update_post_meta( $post_id, $item['meta'], wp_slash( $value ) );

					if ( ! self::detail_fits( $groups, $name, $slug ) ) {
						$unseen[] = $item['label'];
					}
				} else {
					/* translators: 1: detail, 2: what was written. */
					$warnings[] = sprintf( __( '%1$s "%2$s" couldn\'t be used, so it was left out.', 'crc-real-estate' ), $item['label'], $cell( $column ) );
				}
			}

			if ( $item['units'] && $filled( $column . '_unit' ) ) {
				$unit = self::unit_key( $item, $cell( $column . '_unit' ) );

				if ( '' !== $unit ) {
					update_post_meta( $post_id, $item['meta'] . '_unit', $unit );
				} else {
					/* translators: 1: detail, 2: what was written. */
					$warnings[] = sprintf( __( 'The unit "%2$s" of %1$s isn\'t one of its units, so it was left out.', 'crc-real-estate' ), $item['label'], $cell( $column . '_unit' ) );
				}
			}
		}

		// Details added to ready-made groups, and groups of the listing's own.
		$own_details = null;

		if ( $filled( 'own_detail_groups' ) ) {
			$own_details = array();

			foreach ( self::detail_groups( $cell( 'own_detail_groups' ) ) as $group ) {
				$own_details[] = $group;
			}
		}

		if ( $filled( 'more_details' ) ) {
			$extras = array();

			foreach ( self::detail_groups( $cell( 'more_details' ) ) as $group ) {
				$key = self::group_key( $groups, $group['title'] );

				if ( '' !== $key ) {
					$extras[ $key ] = isset( $extras[ $key ] ) ? array_merge( $extras[ $key ], $group['items'] ) : $group['items'];

					if ( $groups[ $key ]['categories'] && ! in_array( $slug, $groups[ $key ]['categories'], true ) ) {
						$unseen[] = $groups[ $key ]['title'];
					}
				} else {
					// Not a ready-made group: kept as a group of the listing's own.
					$own_details = self::add_group( null === $own_details ? Overview::groups( $post_id ) : $own_details, $group, true );
					$warnings[]  = self::unknown_group( $group['title'], $groups, 'more_details', __( 'Access and road: Bus route = 200 m', 'crc-real-estate' ) );
				}
			}

			// Only lines for ready-made groups replace what the listing added to them.
			if ( $extras ) {
				self::store( $post_id, Overview::EXTRA_META, Overview::sanitize_extras( $extras ) );
			}
		}

		if ( null !== $own_details ) {
			self::store( $post_id, Overview::MORE_META, Overview::sanitize_groups( $own_details ) );
		}

		// Features.
		$feature_groups = Features::common_groups();
		$own_features   = null;

		if ( $filled( 'own_feature_groups' ) ) {
			$own_features = array();

			foreach ( self::item_groups( $cell( 'own_feature_groups' ) ) as $group ) {
				$own_features[] = $group;
			}
		}

		if ( $filled( 'features' ) ) {
			$keys    = array();
			$unknown = array();

			foreach ( self::split_list( $cell( 'features' ) ) as $feature ) {
				$key = self::feature_key( $feature );

				if ( '' !== $key ) {
					$keys[] = $key;

					if ( ! self::feature_fits( $feature_groups, $key, $slug ) ) {
						$unseen[] = $feature;
					}
				} else {
					$unknown[] = $feature;
				}
			}

			self::store( $post_id, Features::META, Features::sanitize_names( $keys ) );

			if ( $unknown ) {
				$own_features = self::add_group(
					null === $own_features ? Features::groups( $post_id ) : $own_features,
					array(
						'title' => __( 'More features', 'crc-real-estate' ),
						'items' => $unknown,
					)
				);
				/* translators: %s: features. */
				$warnings[] = sprintf( __( 'These aren\'t ready-made features, so they were put in a group called More features: %s.', 'crc-real-estate' ), implode( ', ', $unknown ) );
			}
		}

		if ( $filled( 'more_features' ) ) {
			$extras = array();

			foreach ( self::item_groups( $cell( 'more_features' ) ) as $group ) {
				$key = self::group_key( $feature_groups, $group['title'] );

				if ( '' !== $key ) {
					$extras[ $key ] = isset( $extras[ $key ] ) ? array_merge( $extras[ $key ], $group['items'] ) : $group['items'];

					if ( $feature_groups[ $key ]['categories'] && ! in_array( $slug, $feature_groups[ $key ]['categories'], true ) ) {
						$unseen[] = $feature_groups[ $key ]['title'];
					}
				} else {
					$own_features = self::add_group( null === $own_features ? Features::groups( $post_id ) : $own_features, $group );
					$warnings[]   = self::unknown_group( $group['title'], $feature_groups, 'more_features', __( 'Home features: Pantry | Roof terrace', 'crc-real-estate' ) );
				}
			}

			if ( $extras ) {
				self::store( $post_id, Features::EXTRA_META, Features::sanitize_extras( $extras ) );
			}
		}

		if ( null !== $own_features ) {
			self::store( $post_id, Features::GROUPS_META, Features::sanitize_groups( $own_features ) );
		}

		if ( $unseen ) {
			$warnings[] = '' === $slug
				/* translators: %s: details and features. */
				? sprintf( __( 'The listing has no category yet, so these were saved but won\'t show until you choose one: %s.', 'crc-real-estate' ), implode( ', ', array_unique( $unseen ) ) )
				/* translators: %s: details and features. */
				: sprintf( __( 'These were saved, but listings in this category don\'t show them: %s.', 'crc-real-estate' ), implode( ', ', array_unique( $unseen ) ) );
		}

		// Location.
		if ( $filled( 'latitude' ) || $filled( 'longitude' ) ) {
			$lat = $cell( 'latitude' );
			$lng = $cell( 'longitude' );

			// Both numbers in one cell, as Google Maps copies them.
			if ( '' === $lng && preg_match( '/^\s*(-?[\d.]+)\s*[,;\s]\s*(-?[\d.]+)\s*$/', $lat, $both ) ) {
				$lat = $both[1];
				$lng = $both[2];
			}

			$lat = Location::sanitize_coordinate( $lat, 90 );
			$lng = Location::sanitize_coordinate( $lng, 180 );

			if ( '' !== $lat && '' !== $lng ) {
				update_post_meta( $post_id, Location::LAT_META, $lat );
				update_post_meta( $post_id, Location::LNG_META, $lng );
			} else {
				$warnings[] = __( 'The place needs a latitude from -90 to 90 and a longitude from -180 to 180, so it was left out.', 'crc-real-estate' );
			}
		}

		$warnings = array_merge( $warnings, self::apply_switch( $post_id, $cells, 'show_map', Location::HIDDEN_META ) );

		if ( $filled( 'google_maps_link' ) ) {
			$link = esc_url_raw( $cell( 'google_maps_link' ), array( 'http', 'https' ) );

			if ( '' !== $link ) {
				update_post_meta( $post_id, Location::GOOGLE_META, wp_slash( $link ) );
			} else {
				$warnings[] = __( 'The Google Maps link isn\'t a web address, so it was left out.', 'crc-real-estate' );
			}
		}

		// FAQs.
		$warnings = array_merge( $warnings, self::apply_switch( $post_id, $cells, 'show_category_faqs', Faq::HIDE_META ) );
		$faqs     = self::faqs( $cells );

		if ( null !== $faqs ) {
			$warnings = array_merge( $warnings, self::apply_faqs( $post_id, $faqs ) );
		}

		// Owner.
		$owner  = array();
		$labels = Owner::labels();

		foreach ( array_keys( Owner::FIELDS ) as $key ) {
			if ( ! $filled( 'owner_' . $key ) ) {
				continue;
			}

			$value = $cell( 'owner_' . $key );

			// A cell that can't be used leaves the saved detail as it was.
			$clean = Owner::sanitize( $key, $value );

			if ( '' === $clean || ( 'phone' === $key && ! preg_match( '/\d/', $clean ) ) ) {
				if ( 'email' === $key ) {
					/* translators: %s: what was written. */
					$warnings[] = sprintf( __( 'The owner\'s email "%s" isn\'t an email address, so it was left as it was.', 'crc-real-estate' ), $value );
				} elseif ( 'phone' === $key ) {
					/* translators: %s: what was written. */
					$warnings[] = sprintf( __( 'The owner\'s phone "%s" isn\'t a phone number, so it was left as it was.', 'crc-real-estate' ), $value );
				} else {
					/* translators: 1: detail, e.g. "first name", 2: what was written. */
					$warnings[] = sprintf( __( 'The owner\'s %1$s "%2$s" couldn\'t be used, so it was left as it was.', 'crc-real-estate' ), strtolower( $labels[ $key ] ), $value );
				}

				continue;
			}

			$owner[ $key ] = $value;
		}

		Owner::save( $post_id, $owner );

		return $warnings;
	}

	/**
	 * Writes the listing's own questions from a row. Only the questions the
	 * row fills in change, by number: faq_2_answer changes the answer of
	 * question 2 and leaves the others as they are. Numbers after the last
	 * question add new ones.
	 *
	 * @param int     $post_id Listing ID.
	 * @param array[] $faqs    From faqs(): number => filled parts.
	 * @return string[] Warnings.
	 */
	private static function apply_faqs( $post_id, array $faqs ) {
		$items    = array();
		$warnings = array();
		$number   = 0;
		$empty    = array(
			'question'  => '',
			'answer'    => '',
			'link_text' => '',
			'link_url'  => '',
		);

		foreach ( Faq::own_items( $post_id ) as $item ) {
			$items[ ++$number ] = $item;
		}

		foreach ( $faqs as $n => $parts ) {
			// A number after the last question, whose question is one the listing has already
			// (from importing the same file before), changes that question instead of adding it again.
			if ( ! isset( $items[ $n ] ) && isset( $parts['question'] ) ) {
				foreach ( $items as $i => $saved ) {
					if ( self::same_text( $saved['question'], $parts['question'] ) ) {
						$n = $i;
						break;
					}
				}
			}

			$item = array_merge( isset( $items[ $n ] ) ? $items[ $n ] : $empty, $parts );

			if ( '' === trim( wp_strip_all_tags( (string) $item['question'] ) ) ) {
				/* translators: %d: question number. */
				$warnings[] = sprintf( __( 'Question %1$d has an answer or a link but no question, so it was left out. Fill in faq_%1$d_question too.', 'crc-real-estate' ), $n );
				continue;
			}

			$items[ $n ] = $item;
		}

		ksort( $items );
		self::store( $post_id, Faq::META, Faq::sanitize_items( array_values( $items ) ) );

		return $warnings;
	}

	/**
	 * Whether two texts are the same, ignoring case, spaces and code.
	 *
	 * @param string $a Text.
	 * @param string $b Text.
	 * @return bool
	 */
	private static function same_text( $a, $b ) {
		$clean = function ( $text ) {
			return strtolower( trim( sanitize_text_field( (string) $text ) ) );
		};

		return $clean( $a ) === $clean( $b );
	}

	/**
	 * A warning about a line whose title is none of the ready-made groups.
	 *
	 * @param string  $title   Title as written.
	 * @param array[] $groups  Ready-made groups.
	 * @param string  $column  Column, e.g. more_details.
	 * @param string  $example A line written the right way.
	 * @return string
	 */
	private static function unknown_group( $title, array $groups, $column, $example ) {
		if ( '' === trim( (string) $title ) ) {
			return sprintf(
				/* translators: 1: column, 2: example line. */
				__( 'A line in %1$s has no group title, so it was put in a group of the listing\'s own. Start the line with the group\'s title and a colon, for example %2$s.', 'crc-real-estate' ),
				$column,
				$example
			);
		}

		return sprintf(
			/* translators: 1: group title as written, 2: ready-made group titles. */
			__( 'There is no ready-made group called "%1$s", so its lines were put in a group of the listing\'s own. The ready-made groups are: %2$s.', 'crc-real-estate' ),
			$title,
			implode( ', ', wp_list_pluck( $groups, 'title' ) )
		);
	}

	/**
	 * An amount from a cell: digits, with or without commas, spaces, Rs. or
	 * cents, e.g. "Rs. 12,500,000.00" or "12500000/=". Anything else, such as
	 * "12.5 million" or a range, isn't one amount.
	 *
	 * @param string $value Cell.
	 * @return string Digits, or '' when it isn't one amount.
	 */
	private static function amount( $value ) {
		$value = str_replace( array( ',', ' ', "\xC2\xA0" ), '', self::without_currency( $value ) );
		$value = (string) preg_replace( '#/[-=]$#', '', $value );

		return preg_match( '/^\d+(\.\d+)?$/', $value ) ? Price_Card::sanitize_amount( $value ) : '';
	}

	/**
	 * Whether listings in a category show a ready-made feature: one of the
	 * groups it is in shows for every category, or for this one.
	 *
	 * @param array[] $groups Ready-made feature groups.
	 * @param string  $key    Feature key.
	 * @param string  $slug   Category slug, or '' for none.
	 * @return bool
	 */
	private static function feature_fits( array $groups, $key, $slug ) {
		foreach ( $groups as $group ) {
			if ( isset( $group['features'][ $key ] ) && ( ! $group['categories'] || in_array( $slug, $group['categories'], true ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A row's photo links: the main photo and the others, in order.
	 *
	 * @param string[] $cells Row.
	 * @return array 'main' (a link or '') and 'more' (links); null when the column is empty.
	 */
	public static function photo_links( array $cells ) {
		$main = isset( $cells['main_photo'] ) ? trim( (string) $cells['main_photo'] ) : '';
		$more = isset( $cells['more_photos'] ) ? trim( (string) $cells['more_photos'] ) : '';

		return array(
			'main' => '' !== $main ? $main : null,
			'more' => '' !== $more ? array_values( array_filter( array_map( 'trim', preg_split( '/[|\n]+/', $more ) ), 'strlen' ) ) : null,
		);
	}

	/**
	 * A category slug from what a row says: the slug, or the category's
	 * name or caption.
	 *
	 * @param string $value Cell.
	 * @return string Slug, or '' when it matches none.
	 */
	public static function category_slug( $value ) {
		$value = strtolower( trim( (string) $value ) );

		foreach ( Taxonomy::terms() as $slug => $term ) {
			$names = array( $slug, strtolower( $term['name'] ), strtolower( $term['label'] ) );
			$saved = get_term_by( 'slug', $slug, Taxonomy::NAME );

			if ( $saved && ! is_wp_error( $saved ) ) {
				$names[] = strtolower( $saved->name );
				$names[] = strtolower( Taxonomy::caption( $saved ) );
			}

			if ( in_array( $value, $names, true ) ) {
				return $slug;
			}
		}

		return '';
	}

	/**
	 * A status a row asks for: draft, pending, publish or private. Published
	 * and Draft as words work too.
	 *
	 * @param string $value Cell.
	 * @return string The status, or '' when it isn't one.
	 */
	public static function status( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$words = array(
			'published' => 'publish',
			'live'      => 'publish',
			'pending review' => 'pending',
		);
		$value = isset( $words[ $value ] ) ? $words[ $value ] : $value;

		return in_array( $value, self::STATUSES, true ) ? $value : '';
	}

	/**
	 * Reads yes or no.
	 *
	 * @param string $value Cell.
	 * @return bool|null Null when it's neither.
	 */
	public static function yes_no( $value ) {
		$value = strtolower( trim( (string) $value ) );

		if ( in_array( $value, array( 'yes', 'y', 'true', '1', 'on', 'show', 'shown' ), true ) ) {
			return true;
		}

		if ( in_array( $value, array( 'no', 'n', 'false', '0', 'off', 'hide', 'hidden' ), true ) ) {
			return false;
		}

		return null;
	}

	/**
	 * The listing's own questions from the faq_N_ columns, in number order:
	 * only the parts filled in.
	 *
	 * @param string[] $cells Row.
	 * @return array[]|null Number => parts ('question', 'answer', 'link_text', 'link_url'); null when no question column has anything in it.
	 */
	public static function faqs( array $cells ) {
		$faqs = array();

		foreach ( $cells as $name => $value ) {
			if ( ! preg_match( '/^faq_(\d+)_(question|answer|link_text|link)$/', (string) $name, $parts ) || '' === trim( (string) $value ) ) {
				continue;
			}

			$key = 'link' === $parts[2] ? 'link_url' : $parts[2];

			$faqs[ (int) $parts[1] ][ $key ] = (string) $value;
		}

		if ( ! $faqs ) {
			return null;
		}

		ksort( $faqs );

		return $faqs;
	}

	/**
	 * Everything a listing holds, for the full export, arranged by section.
	 *
	 * @param int $post_id Listing ID.
	 * @return array
	 */
	public static function full( $post_id ) {
		$post     = get_post( $post_id );
		$category = Taxonomy::listing_category( $post_id );
		$district = District::of( $post_id );
		$photos   = self::photo_ids( $post_id );
		$photo    = function ( $id ) {
			return array(
				'id'  => (int) $id,
				'url' => (string) wp_get_attachment_url( $id ),
				'alt' => trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ),
			);
		};
		$details  = array();

		foreach ( self::detail_items() as $name => $item ) {
			$value = (string) get_post_meta( $post_id, $item['meta'], true );

			if ( '' !== $value ) {
				$details[ $name ] = array(
					'label' => $item['label'],
					'value' => $value,
					'shown' => Overview::detail_text( $post_id, $item ),
				);

				if ( $item['units'] ) {
					$details[ $name ]['unit'] = (string) get_post_meta( $post_id, $item['meta'] . '_unit', true );
				}
			}
		}

		$main = array();

		foreach ( Overview::fields() as $name => $field ) {
			$main[ $name ] = (string) get_post_meta( $post_id, $field['meta'], true );
		}

		return array(
			'id'          => (int) $post->ID,
			'title'       => (string) $post->post_title,
			'slug'        => (string) $post->post_name,
			'status'      => (string) $post->post_status,
			'link'        => (string) get_permalink( $post ),
			'date'        => (string) $post->post_date,
			'modified'    => (string) $post->post_modified,
			'category'    => $category ? array(
				'slug'    => $category['slug'],
				'name'    => $category['term']->name,
				'caption' => $category['label'],
			) : null,
			'district'    => $district ? array(
				'slug'     => $district['slug'],
				'name'     => $district['name'],
				'province' => $district['province'],
			) : null,
			'description' => (string) $post->post_content,
			'photos'      => array(
				'main' => $photos['main'] ? $photo( $photos['main'] ) : null,
				'more' => array_map( $photo, $photos['more'] ),
			),
			'price'       => array(
				'currency'        => Price_Card::currency(),
				'price'           => Price_Card::price( $post_id ),
				'price_per_perch' => Price_Card::price_per_perch( $post_id ),
			),
			'contact'     => array(
				'phone'        => (string) get_post_meta( $post_id, Settings::PHONE_META, true ),
				'whatsapp'     => (string) get_post_meta( $post_id, Settings::WHATSAPP_META, true ),
				'call_text'    => (string) get_post_meta( $post_id, Settings::CALL_TEXT_META, true ),
				'message_text' => (string) get_post_meta( $post_id, Settings::MESSAGE_TEXT_META, true ),
				'shown'        => array(
					'phone'    => Settings::number_for( $post_id, 'phone' ),
					'whatsapp' => Settings::number_for( $post_id, 'whatsapp' ),
					'call'     => Settings::button_text( $post_id, 'call' ),
					'message'  => Settings::button_text( $post_id, 'message' ),
				),
			),
			'overview'    => array(
				'main'        => $main,
				'details'     => $details,
				'more'        => Overview::extras( $post_id ),
				'own_groups'  => Overview::groups( $post_id ),
				'as_shown'    => Overview::common_details( $post_id ),
			),
			'features'    => array(
				'ticked'     => Features::ticked( $post_id ),
				'more'       => Features::extras( $post_id ),
				'own_groups' => Features::groups( $post_id ),
				'as_shown'   => Features::listing_groups( $post_id ),
			),
			'location'    => array(
				'place'            => Location::exact( $post_id ),
				'show_map'         => '1' !== (string) get_post_meta( $post_id, Location::HIDDEN_META, true ),
				'google_maps_link' => (string) get_post_meta( $post_id, Location::GOOGLE_META, true ),
			),
			'faqs'        => array(
				'show_category_faqs' => Faq::shows_category( $post_id ),
				'own'                => Faq::own_items( $post_id ),
				'category'           => $category ? Faq::category_items( $category['term']->term_id ) : array(),
			),
			'owner'       => Owner::get( $post_id ),
			'views'       => Views::get( $post_id ),
		);
	}

	/**
	 * Splits a list cell at | or new lines. A | written as \| belongs to the item.
	 *
	 * @param string $value Cell.
	 * @return string[]
	 */
	public static function split_list( $value ) {
		$items = array();

		foreach ( preg_split( '/\n+/', (string) $value ) as $line ) {
			foreach ( self::split_at( $line, '|' ) as $item ) {
				$items[] = trim( self::unescape( $item ) );
			}
		}

		return array_values( array_filter( $items, 'strlen' ) );
	}

	/**
	 * Reads groups of items: a line for each group, "Title: first | second".
	 * A line without a title is a group without one.
	 *
	 * @param string $value Cell.
	 * @return array[] Each with 'title' and 'items' (strings).
	 */
	public static function item_groups( $value ) {
		$groups = array();

		foreach ( self::raw_groups( $value ) as $group ) {
			$groups[] = array(
				'title' => $group['title'],
				'items' => array_values( array_filter( array_map( 'trim', array_map( array( __CLASS__, 'unescape' ), $group['items'] ) ), 'strlen' ) ),
			);
		}

		return $groups;
	}

	/**
	 * Reads groups of details: a line for each group, "Title: Label = Value | Label = Value".
	 *
	 * @param string $value Cell.
	 * @return array[] Each with 'title' and 'items' (each with 'label' and 'value').
	 */
	public static function detail_groups( $value ) {
		$groups = array();

		foreach ( self::raw_groups( $value ) as $group ) {
			$items = array();

			foreach ( $group['items'] as $pair ) {
				$parts = self::split_at( $pair, '=', 2 );
				$label = trim( self::unescape( $parts[0] ) );
				$value = isset( $parts[1] ) ? trim( self::unescape( $parts[1] ) ) : '';

				// A detail can have a value without a label, written as "= value".
				if ( '' !== $label || '' !== $value ) {
					$items[] = array(
						'label' => $label,
						'value' => $value,
					);
				}
			}

			$groups[] = array(
				'title' => $group['title'],
				'items' => $items,
			);
		}

		return $groups;
	}

	/**
	 * Splits a groups cell into lines, each with its title and its items as
	 * written (still with their \ marks).
	 *
	 * @param string $value Cell.
	 * @return array[] Each with 'title' and 'items'.
	 */
	private static function raw_groups( $value ) {
		$groups = array();

		foreach ( preg_split( '/\n+/', (string) $value ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = self::split_at( $line, ':', 2 );
			$title = isset( $parts[1] ) ? trim( self::unescape( $parts[0] ) ) : '';
			$rest  = isset( $parts[1] ) ? $parts[1] : $parts[0];
			$items = array();

			foreach ( self::split_at( $rest, '|' ) as $item ) {
				if ( '' !== trim( $item ) ) {
					$items[] = trim( $item );
				}
			}

			$groups[] = array(
				'title' => $title,
				'items' => $items,
			);
		}

		return $groups;
	}

	/**
	 * Splits text at a mark that isn't written with a \ in front of it.
	 *
	 * @param string $text  Text.
	 * @param string $mark  One character: | : or =.
	 * @param int    $limit Most parts; 0 for no limit.
	 * @return string[] Parts, still with their \ marks.
	 */
	private static function split_at( $text, $mark, $limit = 0 ) {
		$text    = (string) $text;
		$length  = strlen( $text );
		$parts   = array();
		$current = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( '\\' === $char && $i + 1 < $length && false !== strpos( '\\|:=', $text[ $i + 1 ] ) ) {
				$current .= $char . $text[ ++$i ];
				continue;
			}

			if ( $mark === $char && ( ! $limit || count( $parts ) < $limit - 1 ) ) {
				$parts[] = $current;
				$current = '';
				continue;
			}

			$current .= $char;
		}

		$parts[] = $current;

		return $parts;
	}

	/**
	 * Takes the \ off marks written as part of the text: \| \: \= and \\.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function unescape( $text ) {
		return (string) preg_replace( '/\\\\([\\\\|:=])/', '$1', (string) $text );
	}

	/**
	 * Writes a group as a line: "Title: first | second", or for details
	 * "Title: Label = Value | Label = Value". Marks that are part of the text
	 * get a \ in front, so the line reads back the same.
	 *
	 * @param string $title   Group title.
	 * @param array  $items   Items: strings, or for details arrays with 'label' and 'value'.
	 * @param bool   $details Whether the items are details.
	 * @return string
	 */
	private static function group_line( $title, array $items, $details = false ) {
		$title = (string) $title;

		// In a group without a title, a : in the first item would read as a title.
		$colon = '' === $title ? ':' : '';
		$parts = array();

		foreach ( $items as $item ) {
			if ( $details ) {
				$label   = addcslashes( (string) $item['label'], '\\|=' . $colon );
				$value   = addcslashes( (string) $item['value'], '\\|' . $colon );
				$parts[] = '' !== (string) $item['value'] ? $label . ' = ' . $value : $label;
			} else {
				$parts[] = addcslashes( (string) $item, '\\|' . $colon );
			}
		}

		$line = implode( ' | ', $parts );

		return '' !== $title ? addcslashes( $title, '\\:' ) . ': ' . $line : $line;
	}

	/**
	 * Adds a group to a list of groups. When one with the same title is
	 * there already, the new items join it, each only once, so importing
	 * the same file again doesn't repeat them.
	 *
	 * @param array[] $groups  Groups, each with 'title' and 'items'.
	 * @param array   $group   Group to add.
	 * @param bool    $details Whether the items are details (with 'label' and 'value').
	 * @return array[]
	 */
	private static function add_group( array $groups, array $group, $details = false ) {
		$key = function ( $item ) use ( $details ) {
			return $details ? strtolower( trim( (string) $item['label'] ) . "\0" . trim( (string) $item['value'] ) ) : strtolower( trim( (string) $item ) );
		};

		foreach ( $groups as $i => $existing ) {
			if ( strtolower( trim( (string) $existing['title'] ) ) !== strtolower( trim( (string) $group['title'] ) ) ) {
				continue;
			}

			$have = array_map( $key, $existing['items'] );

			foreach ( $group['items'] as $item ) {
				if ( ! in_array( $key( $item ), $have, true ) ) {
					$groups[ $i ]['items'][] = $item;
					$have[]                  = $key( $item );
				}
			}

			return $groups;
		}

		$groups[] = $group;

		return $groups;
	}

	/**
	 * A ready-made group's key from its title or key.
	 *
	 * @param array[] $groups Ready-made groups, key => array with 'title'.
	 * @param string  $title  Title or key as written.
	 * @return string Key, or '' when it matches none.
	 */
	private static function group_key( array $groups, $title ) {
		$title = strtolower( trim( (string) $title ) );

		foreach ( $groups as $key => $group ) {
			if ( strtolower( (string) $key ) === $title || strtolower( (string) $group['title'] ) === $title ) {
				return (string) $key;
			}
		}

		return '';
	}

	/**
	 * A ready-made feature's key from its name or key.
	 *
	 * @param string $feature As written.
	 * @return string Key, or '' when it matches none.
	 */
	private static function feature_key( $feature ) {
		$feature = strtolower( trim( (string) $feature ) );

		foreach ( Features::common_features() as $key => $label ) {
			if ( strtolower( (string) $key ) === $feature || strtolower( (string) $label ) === $feature ) {
				return (string) $key;
			}
		}

		return '';
	}

	/**
	 * A ready-made detail's value from a cell: a number without its unit,
	 * an amount without Rs., or a choice by its key or its name.
	 *
	 * @param array  $item  Detail.
	 * @param string $value Cell.
	 * @return string '' when it can't be used.
	 */
	private static function detail_value( array $item, $value ) {
		if ( 'select' === $item['type'] ) {
			$wanted = strtolower( trim( $value ) );

			foreach ( $item['options'] as $key => $label ) {
				if ( strtolower( (string) $key ) === $wanted || strtolower( (string) $label ) === $wanted ) {
					return (string) $key;
				}
			}

			return '';
		}

		if ( 'money' === $item['type'] ) {
			$value = self::amount( $value );

			if ( '' === $value ) {
				return '';
			}
		}

		return Overview::sanitize_detail( $item, $value );
	}

	/**
	 * A unit's key from its key or its name, e.g. "Acres" or "acre".
	 *
	 * @param array  $item  Detail with units.
	 * @param string $value Cell.
	 * @return string '' when it matches none.
	 */
	private static function unit_key( array $item, $value ) {
		$wanted = strtolower( trim( $value ) );

		foreach ( $item['units'] as $key => $unit ) {
			$name = strtolower( Overview::unit_name( $unit ) );
			$one  = strtolower( trim( sprintf( translate_nooped_plural( $unit, 1, 'crc-real-estate' ), '' ) ) );

			if ( in_array( $wanted, array( strtolower( $key ), $name, $one, rtrim( $name, 's' ), rtrim( strtolower( $key ), 's' ) ), true ) ) {
				return (string) $key;
			}
		}

		return '';
	}

	/**
	 * Whether listings in a category show a ready-made detail.
	 *
	 * @param array[] $groups Ready-made groups.
	 * @param string  $name   Detail name.
	 * @param string  $slug   Category slug, or '' for none.
	 * @return bool
	 */
	private static function detail_fits( array $groups, $name, $slug ) {
		foreach ( $groups as $group ) {
			if ( isset( $group['items'][ $name ] ) && ( ! $group['categories'] || in_array( $slug, $group['categories'], true ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * An amount without Rs. or LKR in front, which would otherwise stop it
	 * being read ("Rs. 5,000" is 5000).
	 *
	 * @param string $value Amount.
	 * @return string
	 */
	private static function without_currency( $value ) {
		return trim( (string) preg_replace( '/^\s*(rs\.?|lkr|රු\.?)\s*/iu', '', (string) $value ) );
	}

	/**
	 * A latitude or longitude written plainly, e.g. 6.0535.
	 *
	 * @param float $value Number.
	 * @return string
	 */
	private static function coordinate( $value ) {
		return rtrim( rtrim( sprintf( '%.6F', $value ), '0' ), '.' );
	}

	/**
	 * Writes a yes/no cell to a flag stored the other way round: '1' when
	 * the answer is no, nothing when it's yes.
	 *
	 * @param int      $post_id Listing ID.
	 * @param string[] $cells   Row.
	 * @param string   $column  Column.
	 * @param string   $meta    Flag.
	 * @return string[] Warnings.
	 */
	private static function apply_switch( $post_id, array $cells, $column, $meta ) {
		if ( ! isset( $cells[ $column ] ) || '' === trim( (string) $cells[ $column ] ) ) {
			return array();
		}

		$yes = self::yes_no( $cells[ $column ] );

		if ( null === $yes ) {
			/* translators: 1: column, 2: what was written. */
			return array( sprintf( __( '%1$s should be yes or no, not "%2$s", so it was left as it was.', 'crc-real-estate' ), $column, trim( (string) $cells[ $column ] ) ) );
		}

		if ( $yes ) {
			delete_post_meta( $post_id, $meta );
		} else {
			update_post_meta( $post_id, $meta, '1' );
		}

		return array();
	}

	/**
	 * Saves a list, or removes it when it's empty, as the listing screen does.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $meta    Field.
	 * @param array  $value   List.
	 */
	private static function store( $post_id, $meta, array $value ) {
		if ( $value ) {
			update_post_meta( $post_id, $meta, wp_slash( $value ) );
		} else {
			delete_post_meta( $post_id, $meta );
		}
	}
}
