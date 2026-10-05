<?php
/**
 * Download one listing as a zip file.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Csv;
use CRC\RealEstate\District;
use CRC\RealEstate\Inquiries;
use CRC\RealEstate\Listing_Data;
use CRC\RealEstate\Owner;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Faq;
use CRC\RealEstate\Sections\Features;
use CRC\RealEstate\Sections\Location;
use CRC\RealEstate\Sections\Overview;
use CRC\RealEstate\Sections\Price_Card;
use CRC\RealEstate\Settings;
use CRC\RealEstate\Taxonomy;
use CRC\RealEstate\Views;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Download listing to the listing screen and the Listings list. It
 * downloads a zip file with a page showing every detail of the listing,
 * its photos in full size, its row for the spreadsheet import, all of its
 * data for developers, and the inquiries about it.
 */
final class Listing_Export {

	const ACTION = 'crc_re_export_listing';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'button' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 20, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
	}

	/**
	 * The download address for a listing.
	 *
	 * @param int $post_id Listing ID.
	 * @return string
	 */
	public static function url( $post_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&post=' . (int) $post_id ), self::ACTION . '_' . (int) $post_id );
	}

	/**
	 * Whether the current person can download a listing.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private static function allowed( $post ) {
		return is_object( $post ) && ! empty( $post->ID ) && Post_Type::NAME === $post->post_type && 'auto-draft' !== $post->post_status && current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Whether the current person may see inquiries: only those who can open
	 * Listings → Inquiries (editors and administrators) get them in the zip.
	 *
	 * @return bool
	 */
	private static function sees_inquiries() {
		$type = get_post_type_object( Inquiries::NAME );

		return $type && current_user_can( $type->cap->edit_posts );
	}

	/**
	 * Adds Download listing to the Publish box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function button( $post ) {
		if ( ! self::allowed( $post ) ) {
			return;
		}
		?>
		<div class="misc-pub-section crc-export-listing">
			<span class="dashicons dashicons-download" aria-hidden="true"></span>
			<a href="<?php echo esc_url( self::url( $post->ID ) ); ?>"><?php esc_html_e( 'Download listing', 'crc-real-estate' ); ?></a>
			<p class="description">
				<?php
				if ( self::sees_inquiries() ) {
					esc_html_e( 'A zip file with every detail, the photos in full size, the owner\'s details and the inquiries about this listing. Save your changes first to include them.', 'crc-real-estate' );
				} else {
					esc_html_e( 'A zip file with every detail, the photos in full size and the owner\'s details. Save your changes first to include them.', 'crc-real-estate' );
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds Download under each listing in the Listings list.
	 *
	 * @param string[] $actions Row actions.
	 * @param \WP_Post $post    Post in the row.
	 * @return string[]
	 */
	public function row_action( $actions, $post ) {
		if ( ! self::allowed( $post ) || 'trash' === $post->post_status ) {
			return $actions;
		}

		$actions['crc_download'] = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( self::url( $post->ID ) ),
			/* translators: %s: listing title. */
			esc_attr( sprintf( __( 'Download “%s” as a zip file', 'crc-real-estate' ), _draft_or_post_title( $post ) ) ),
			esc_html__( 'Download', 'crc-real-estate' )
		);

		return $actions;
	}

	/**
	 * Sends the zip file.
	 */
	public function download() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked just below.
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( self::ACTION . '_' . $id );

		$post = $id ? get_post( $id ) : null;

		if ( ! self::allowed( $post ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t download this listing.', 'crc-real-estate' ), 403 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not every host allows it.
		}

		$name = self::file_name( $post );
		$zip  = self::zip( $name, self::files( $post ) );

		if ( is_wp_error( $zip ) ) {
			wp_die( esc_html( $zip->get_error_message() ), '', array( 'back_link' => true ) );
		}

		// Finish sending, and remove the file, even when the download is cancelled.
		ignore_user_abort( true );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.zip"' );
		header( 'Content-Length: ' . filesize( $zip ) );
		header( 'X-Content-Type-Options: nosniff' );

		// Sent straight from the file, so large photos aren't held in memory.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a download.
		wp_delete_file( $zip );
		exit;
	}

	/**
	 * The zip file's name, also the folder inside it, e.g. listing-12-bare-land-in-galle.
	 *
	 * @param \WP_Post $post Listing.
	 * @return string
	 */
	private static function file_name( $post ) {
		$slug = '' !== (string) $post->post_name ? (string) $post->post_name : sanitize_title( $post->post_title );
		$slug = trim( substr( sanitize_file_name( $slug ), 0, 60 ), '-.' );

		return 'listing-' . (int) $post->ID . ( '' !== $slug ? '-' . $slug : '' );
	}

	/**
	 * Everything in the zip file.
	 *
	 * @param \WP_Post $post Listing.
	 * @return array[] Path in the zip => array( 'content' => text ) or array( 'file' => path on the server ).
	 */
	public static function files( $post ) {
		$id        = (int) $post->ID;
		$photos    = self::photos( $id );
		$inquiries = self::sees_inquiries() ? self::inquiries( $id ) : array();
		$files     = array();
		$faqs      = max( 2, count( Faq::own_items( $id ) ) );

		$files['Listing details.html'] = array( 'content' => self::page( $post, $photos, $inquiries ) );
		$files['listing.csv']          = array( 'content' => Csv::write( array_keys( Listing_Data::columns( $faqs ) ), array( Listing_Data::row( $id ) ) ) );

		$data              = Listing_Data::full( $id );
		$data['inquiries'] = $inquiries;
		$files['listing.json'] = array( 'content' => (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		if ( $inquiries ) {
			$columns = array( 'date', 'first_name', 'last_name', 'phone', 'country', 'email', 'message', 'page', 'note' );
			$files['inquiries.csv'] = array( 'content' => Csv::write( $columns, $inquiries ) );
		}

		foreach ( $photos as $photo ) {
			if ( '' !== $photo['file'] ) {
				$files[ 'photos/' . $photo['name'] ] = array( 'file' => $photo['file'] );
			}
		}

		return $files;
	}

	/**
	 * The listing's photos, main photo first, with the name each has in the zip.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each with 'id', 'name', 'file' (full-size original on the server, or '' when it's missing), 'url', 'alt' and 'main'.
	 */
	private static function photos( $post_id ) {
		$ids    = Listing_Data::photo_ids( $post_id );
		$all    = $ids['main'] ? array_merge( array( $ids['main'] ), $ids['more'] ) : $ids['more'];
		$digits = max( 2, strlen( (string) count( $all ) ) );
		$photos = array();

		foreach ( $all as $i => $attachment ) {
			$file = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment ) : '';
			$file = $file && is_readable( $file ) ? $file : (string) get_attached_file( $attachment );
			$file = '' !== $file && is_readable( $file ) ? $file : '';
			$base = sanitize_file_name( wp_basename( '' !== $file ? $file : (string) wp_get_attachment_url( $attachment ) ) );
			$main = $attachment === $ids['main'];

			$photos[] = array(
				'id'   => (int) $attachment,
				'name' => str_pad( (string) ( $i + 1 ), $digits, '0', STR_PAD_LEFT ) . ( $main ? '-main' : '' ) . ( '' !== $base ? '-' . $base : '' ),
				'file' => $file,
				'url'  => (string) wp_get_attachment_url( $attachment ),
				'alt'  => trim( wp_strip_all_tags( (string) get_post_meta( $attachment, '_wp_attachment_image_alt', true ) ) ),
				'main' => $main,
			);
		}

		return $photos;
	}

	/**
	 * The inquiries about a listing, newest first.
	 *
	 * @param int $post_id Listing ID.
	 * @return array[] Each with 'date' and the inquiry's details.
	 */
	private static function inquiries( $post_id ) {
		$posts = get_posts(
			array(
				'post_type'        => Inquiries::NAME,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'meta_key'         => Inquiries::META['listing'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Once per download.
				'meta_value'       => (string) $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Once per download.
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		$list  = array();

		foreach ( $posts as $inquiry ) {
			$details = Inquiries::get( $inquiry->ID );

			unset( $details['listing'], $details['mailed'] );

			$list[] = array( 'date' => (string) $inquiry->post_date ) + $details;
		}

		return $list;
	}

	/**
	 * Makes the zip file.
	 *
	 * @param string  $folder Folder inside the zip that holds everything.
	 * @param array[] $files  See files().
	 * @return string|\WP_Error Path of the zip file, to be deleted after sending.
	 */
	private static function zip( $folder, array $files ) {
		$path = wp_tempnam( $folder . '.zip' );

		if ( ! $path ) {
			return new \WP_Error( 'no_temp', __( 'The zip file couldn\'t be made because the server has no space for it. Please try again later.', 'crc-real-estate' ) );
		}

		// The file holds private details: it is removed when the request ends, even if it is cut short.
		register_shutdown_function(
			function () use ( $path ) {
				if ( file_exists( $path ) ) {
					unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WordPress may already be shutting down.
				}
			}
		);

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new \ZipArchive();

			if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				wp_delete_file( $path );

				return new \WP_Error( 'zip', __( 'The zip file couldn\'t be made. Please try again.', 'crc-real-estate' ) );
			}

			foreach ( $files as $name => $file ) {
				$entry = $folder . '/' . $name;

				if ( isset( $file['file'] ) ) {
					$zip->addFile( $file['file'], $entry );

					// Photos are already compressed; storing them as they are is much quicker.
					if ( method_exists( $zip, 'setCompressionName' ) ) {
						$zip->setCompressionName( $entry, \ZipArchive::CM_STORE );
					}
				} else {
					$zip->addFromString( $entry, $file['content'] );
				}
			}

			if ( ! $zip->close() ) {
				wp_delete_file( $path );

				return new \WP_Error( 'zip', __( 'The zip file couldn\'t be made. Please try again.', 'crc-real-estate' ) );
			}

			return $path;
		}

		// Servers without PHP's zip module: the zip library that comes with WordPress.
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

		$list = array();

		foreach ( $files as $name => $file ) {
			$list[] = isset( $file['file'] )
				? array(
					PCLZIP_ATT_FILE_NAME          => $file['file'],
					PCLZIP_ATT_FILE_NEW_FULL_NAME => $folder . '/' . $name,
				)
				: array(
					PCLZIP_ATT_FILE_NAME    => $folder . '/' . $name,
					PCLZIP_ATT_FILE_CONTENT => $file['content'],
				);
		}

		wp_delete_file( $path );

		$archive = new \PclZip( $path );

		if ( ! $archive->create( $list ) ) {
			wp_delete_file( $path );

			return new \WP_Error( 'zip', __( 'The zip file couldn\'t be made. Please try again.', 'crc-real-estate' ) );
		}

		return $path;
	}

	/**
	 * A page showing everything about the listing, to open in a browser.
	 *
	 * @param \WP_Post $post      Listing.
	 * @param array[]  $photos    See photos().
	 * @param array[]  $inquiries See inquiries().
	 * @return string
	 */
	private static function page( $post, array $photos, array $inquiries ) {
		$id       = (int) $post->ID;
		$category = Taxonomy::listing_category( $id );
		$district = District::of( $id );
		$status   = get_post_status_object( $post->post_status );
		$price    = Price_Card::price( $id );
		$perch    = Price_Card::price_per_perch( $post->ID );
		$place    = Location::exact( $id );
		$google   = (string) get_post_meta( $id, Location::GOOGLE_META, true );
		$owner    = Owner::get( $id );
		$labels   = Owner::labels();
		$format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$sections = array();

		// Listing.
		$sections[] = self::section(
			__( 'Listing', 'crc-real-estate' ),
			self::table(
				array(
					__( 'Listing ID', 'crc-real-estate' )   => (string) $id,
					__( 'Status', 'crc-real-estate' )       => $status ? $status->label : $post->post_status,
					__( 'Category', 'crc-real-estate' )     => $category ? $category['term']->name : '',
					__( 'District', 'crc-real-estate' )     => $district ? $district['name'] : '',
					__( 'Web address', 'crc-real-estate' )  => array( 'link' => (string) get_permalink( $post ) ),
					__( 'Added', 'crc-real-estate' )        => mysql2date( $format, $post->post_date ),
					__( 'Last changed', 'crc-real-estate' ) => mysql2date( $format, $post->post_modified ),
					__( 'Views', 'crc-real-estate' )        => Views::label( Views::get( $id ) ),
				)
			)
		);

		// Photos.
		if ( $photos ) {
			$grid = '';

			foreach ( $photos as $photo ) {
				$src   = '' !== $photo['file'] ? 'photos/' . rawurlencode( $photo['name'] ) : $photo['url'];
				$grid .= sprintf(
					'<figure><img src="%1$s" alt="%2$s" loading="lazy"><figcaption>%3$s</figcaption></figure>',
					esc_attr( $src ),
					esc_attr( $photo['alt'] ),
					esc_html( $photo['main'] ? __( 'Main photo', 'crc-real-estate' ) : $photo['name'] )
				);
			}

			$sections[] = self::section( __( 'Photos', 'crc-real-estate' ), '<div class="photos">' . $grid . '</div>' );
		}

		// Price and contact buttons.
		$sections[] = self::section(
			__( 'Price', 'crc-real-estate' ),
			self::table(
				array(
					__( 'Price', 'crc-real-estate' )           => '' !== $price ? Price_Card::money( $price ) : '',
					__( 'Price per perch', 'crc-real-estate' ) => '' !== $perch ? Price_Card::money( $perch ) : '',
				)
			)
		);

		$sections[] = self::section(
			__( 'Contact buttons', 'crc-real-estate' ),
			self::table(
				array(
					__( 'Call button number', 'crc-real-estate' )     => Settings::number_for( $id, 'phone' ),
					__( 'WhatsApp number', 'crc-real-estate' )        => Settings::number_for( $id, 'whatsapp' ),
					__( 'Call button text', 'crc-real-estate' )       => Settings::button_text( $id, 'call' ),
					__( 'Message button text', 'crc-real-estate' )    => Settings::button_text( $id, 'message' ),
				)
			) . '<p class="note">' . esc_html__( 'As the listing page shows them: the listing\'s own numbers and texts, or else the ones from Settings.', 'crc-real-estate' ) . '</p>'
		);

		// Description.
		if ( '' !== trim( (string) $post->post_content ) ) {
			$sections[] = self::section( __( 'Description', 'crc-real-estate' ), '<div class="text">' . wp_kses_post( wpautop( (string) $post->post_content ) ) . '</div>' );
		}

		// Property overview.
		$overview = array();

		foreach ( Overview::main_details( $id ) as $detail ) {
			$overview[ $detail['label'] ] = $detail['value'];
		}

		$html = $overview ? self::table( $overview ) : '';

		foreach ( array_merge( Overview::common_details( $id ), Overview::groups( $id ) ) as $group ) {
			$rows = array();

			foreach ( $group['items'] as $item ) {
				$rows[] = array( $item['label'], $item['value'] );
			}

			$html .= '<h3>' . esc_html( '' !== $group['title'] ? $group['title'] : __( 'More details', 'crc-real-estate' ) ) . '</h3>' . self::pairs( $rows );
		}

		if ( '' !== $html ) {
			$sections[] = self::section( __( 'Property overview', 'crc-real-estate' ), $html );
		}

		// Property features.
		$html = '';

		foreach ( Features::listing_groups( $id ) as $group ) {
			$html .= '<h3>' . esc_html( '' !== $group['title'] ? $group['title'] : __( 'More features', 'crc-real-estate' ) ) . '</h3><ul class="features">';

			foreach ( $group['features'] as $feature ) {
				$html .= '<li>' . esc_html( $feature ) . '</li>';
			}

			$html .= '</ul>';
		}

		if ( '' !== $html ) {
			$sections[] = self::section( __( 'Property features', 'crc-real-estate' ), $html );
		}

		// Location.
		$sections[] = self::section(
			__( 'Location', 'crc-real-estate' ),
			self::table(
				array(
					__( 'Exact place', 'crc-real-estate' )      => $place ? $place['lat'] . ', ' . $place['lng'] : '',
					__( 'On a map', 'crc-real-estate' )         => $place ? array( 'link' => 'https://www.google.com/maps?q=' . rawurlencode( $place['lat'] . ',' . $place['lng'] ) ) : '',
					__( 'Google Maps link', 'crc-real-estate' ) => '' !== $google ? array( 'link' => $google ) : '',
					__( 'Map on the listing page', 'crc-real-estate' ) => Location::shows_map( $id ) ? __( 'Shown (only the area around the place)', 'crc-real-estate' ) : ( $place ? __( 'Hidden', 'crc-real-estate' ) : __( 'None, as no place is marked', 'crc-real-estate' ) ),
				)
			) . '<p class="note">' . esc_html__( 'The exact place is private: the website only ever shows the area around it.', 'crc-real-estate' ) . '</p>'
		);

		// FAQs: every question, and whether the listing page shows it.
		$own      = Faq::own_items( $id );
		$with_cat = $category && Faq::shows_category( $id );
		$faqs     = $with_cat ? array_merge( Faq::category_items( $category['term']->term_id ), $own ) : $own;
		$on_page  = Faq::with_answers( $faqs );

		if ( $faqs ) {
			$html = '';

			foreach ( $faqs as $faq ) {
				$html .= '<div class="faq"><h3>' . esc_html( $faq['question'] ) . '</h3>' . wp_kses_post( wpautop( $faq['answer'] ) );

				if ( ! in_array( $faq, $on_page, true ) ) {
					$html .= '<p class="note">' . esc_html__( 'Not shown on the listing page yet, as it has no answer.', 'crc-real-estate' ) . '</p>';
				}

				if ( '' !== $faq['link_text'] && '' !== $faq['link_url'] ) {
					$html .= '<p class="note">' . esc_html( $faq['link_text'] ) . ' → ' . esc_html( $faq['link_url'] ) . '</p>';
				}

				$html .= '</div>';
			}

			if ( $with_cat ) {
				$note = __( 'The category\'s questions, then the listing\'s own, in the order the listing page shows them.', 'crc-real-estate' );
			} elseif ( ! $category ) {
				$note = __( 'The listing\'s own questions. It has no category yet, so no category questions show with them.', 'crc-real-estate' );
			} else {
				$note = __( 'The listing\'s own questions. Its category\'s questions are turned off for this listing.', 'crc-real-estate' );
			}

			$sections[] = self::section( __( 'FAQs', 'crc-real-estate' ), '<p class="note">' . esc_html( $note ) . '</p>' . $html );
		}

		// Owner.
		$rows = array();

		foreach ( $labels as $key => $label ) {
			$rows[ $label ] = $owner[ $key ];
		}

		$sections[] = self::section( __( 'Owner (private)', 'crc-real-estate' ), self::table( $rows ) );

		// Inquiries.
		if ( $inquiries ) {
			$html = '';

			foreach ( $inquiries as $inquiry ) {
				$html .= '<div class="inquiry"><h3>' . esc_html( Inquiries::name( $inquiry ) ) . ' <span class="note">' . esc_html( mysql2date( $format, $inquiry['date'] ) ) . '</span></h3>'
					. self::table(
						array(
							__( 'Phone', 'crc-real-estate' )     => $inquiry['phone'],
							__( 'Email', 'crc-real-estate' )     => $inquiry['email'],
							__( 'Sent from', 'crc-real-estate' ) => $inquiry['page'],
							__( 'Note', 'crc-real-estate' )      => $inquiry['note'],
						)
					)
					. ( '' !== $inquiry['message'] ? '<div class="text">' . wpautop( esc_html( $inquiry['message'] ) ) . '</div>' : '' )
					. '</div>';
			}

			/* translators: %d: number of inquiries. */
			$sections[] = self::section( sprintf( __( 'Inquiries (%d)', 'crc-real-estate' ), count( $inquiries ) ), $html );
		}

		// What's in the zip.
		$files = array( 'Listing details.html' => __( 'This page.', 'crc-real-estate' ) );

		if ( array_filter( wp_list_pluck( $photos, 'file' ) ) ) {
			$files['photos'] = __( 'The photos in full size, main photo first, in the order the listing shows them.', 'crc-real-estate' );
		}

		$files['listing.csv']  = __( 'The listing as a spreadsheet row, in the same format as Listings → Import & Export. To add it to another site, import it there with the id cell emptied.', 'crc-real-estate' );
		$files['listing.json'] = __( 'All of the listing\'s data, for developers.', 'crc-real-estate' );

		if ( $inquiries ) {
			$files['inquiries.csv'] = __( 'The inquiries about this listing, as a spreadsheet.', 'crc-real-estate' );
		}

		$sections[] = self::section( __( 'Files in this zip', 'crc-real-estate' ), self::table( $files, true ) );

		$title = '' !== (string) $post->post_title ? (string) $post->post_title : __( '(no title)', 'crc-real-estate' );

		return '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . esc_html( $title ) . '</title><style>' . self::styles() . '</style></head><body><main>'
			. '<header><p class="note">' . esc_html( get_bloginfo( 'name' ) ) . '</p><h1>' . esc_html( $title ) . '</h1>'
			/* translators: %s: date and time. */
			. '<p class="note">' . esc_html( sprintf( $inquiries ? __( 'Downloaded on %s. It includes private details: the owner, the exact place and the inquiries.', 'crc-real-estate' ) : __( 'Downloaded on %s. It includes private details: the owner and the exact place.', 'crc-real-estate' ), wp_date( $format ) ) ) . '</p></header>'
			. implode( '', $sections )
			. '</main></body></html>';
	}

	/**
	 * A section of the page.
	 *
	 * @param string $title Heading.
	 * @param string $html  Contents, already escaped.
	 * @return string
	 */
	private static function section( $title, $html ) {
		return '<section><h2>' . esc_html( $title ) . '</h2>' . $html . '</section>';
	}

	/**
	 * A table of labels and values. Empty values show a dash.
	 *
	 * @param array $rows Label => value, or array( 'link' => address ).
	 * @param bool  $code Whether the labels are file names.
	 * @return string
	 */
	private static function table( array $rows, $code = false ) {
		$pairs = array();

		foreach ( $rows as $label => $value ) {
			$pairs[] = array( (string) $label, $value );
		}

		return self::pairs( $pairs, $code );
	}

	/**
	 * A table of label and value pairs, which may repeat a label.
	 *
	 * @param array[] $pairs Each array( label, value ); a value can be array( 'link' => address ).
	 * @param bool    $code  Whether the labels are file names.
	 * @return string
	 */
	private static function pairs( array $pairs, $code = false ) {
		$html = '<table>';

		foreach ( $pairs as $pair ) {
			$value = $pair[1];

			if ( is_array( $value ) ) {
				$value = '' !== $value['link'] ? '<a href="' . esc_url( $value['link'] ) . '">' . esc_html( $value['link'] ) . '</a>' : '—';
			} else {
				$value = '' !== trim( (string) $value ) ? nl2br( esc_html( (string) $value ) ) : '—';
			}

			$label = esc_html( $pair[0] );
			$html .= '<tr><th scope="row">' . ( $code ? '<code>' . $label . '</code>' : $label ) . '</th><td>' . $value . '</td></tr>';
		}

		return $html . '</table>';
	}

	/**
	 * The page's styles.
	 *
	 * @return string
	 */
	private static function styles() {
		return 'body{margin:0;background:#f4f4f4;color:#121212;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}'
			. 'main{max-width:960px;margin:0 auto;padding:32px 16px}'
			. 'header{margin-bottom:24px}h1{margin:4px 0;font-size:28px;line-height:1.3}'
			. 'section{margin:0 0 24px;padding:8px 24px 24px;border:1px solid #e0e0e0;border-radius:16px;background:#fff}'
			. 'h2{margin:16px 0 12px;color:#0b6200;font-size:20px}h3{margin:20px 0 8px;font-size:16px}'
			. 'table{width:100%;border-collapse:collapse}th,td{padding:8px 12px 8px 0;border-bottom:1px solid #f0f0f0;text-align:left;vertical-align:top}'
			. 'th{width:36%;color:#6d6d6d;font-weight:500}td{overflow-wrap:anywhere}a{color:#0b6200}'
			. '.note{color:#6d6d6d;font-size:14px}.text p{margin:0 0 12px}'
			. '.photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}'
			. 'figure{margin:0}img{display:block;width:100%;height:160px;object-fit:cover;border-radius:8px;background:#f0f0f0}'
			. 'figcaption{margin-top:4px;color:#6d6d6d;font-size:13px;overflow-wrap:anywhere}'
			. '.features{margin:0;padding-left:20px;columns:2}.faq,.inquiry{padding:4px 0 12px;border-bottom:1px solid #f0f0f0}'
			. '@media (max-width:767px){section{padding:4px 16px 16px}th{width:44%}.features{columns:1}}';
	}
}
