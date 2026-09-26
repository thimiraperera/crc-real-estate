<?php
/**
 * Removes the place a photo was taken from its file.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Phones write the exact place a photo was taken (its GPS position) into
 * the photo file. Anyone who downloads a listing photo could read it, and
 * so find the exact place the website keeps private.
 *
 * This removes the position from photos when they are uploaded or
 * imported, and once from every photo already in the Media Library, with
 * all the sizes WordPress made of it. Everything else in the file, such
 * as which way up the photo is, stays as it was. JPEG, PNG, WebP, AVIF and
 * HEIC photos are handled.
 *
 * Files are never cut short: a change of a few bytes is written in place,
 * and a file that changes length is written next to the old one first and
 * then put in its place in one go.
 */
final class Photo_Privacy {

	const DONE_OPTION   = 'crc_re_photo_places_removed';
	const CURSOR_OPTION = 'crc_re_photo_places_cursor';
	const TRYING_OPTION = 'crc_re_photo_places_trying';
	const BATCH         = 10;
	const BUDGET        = 2;
	const MAX_PIECES    = 10000;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'upload' ) );
		add_action( 'admin_init', array( $this, 'clean_existing' ) );

		// Photos added in other ways, e.g. from the WordPress app, before their sizes are made.
		add_action( 'add_attachment', array( $this, 'added' ) );
	}

	/**
	 * Removes the place from a photo just added to the Media Library.
	 *
	 * @param int $id Attachment ID.
	 */
	public function added( $id ) {
		self::clean( (string) get_attached_file( $id ) );
	}

	/**
	 * Whether photos should lose the place they were taken. On by default.
	 *
	 * @param string $path Photo file.
	 * @return bool
	 */
	private static function wanted( $path ) {
		/**
		 * Filters whether the plugin removes the place a photo was taken (its GPS position) from uploaded photos.
		 *
		 * @param bool   $remove Whether to remove it. Default true.
		 * @param string $path   Photo file.
		 */
		return (bool) apply_filters( 'crc_re_strip_photo_location', true, $path );
	}

	/**
	 * Removes the place from a photo being uploaded, before WordPress saves it.
	 *
	 * @param array $file Uploaded file, with 'tmp_name'.
	 * @return array
	 */
	public function upload( $file ) {
		if ( is_array( $file ) && ! empty( $file['tmp_name'] ) && empty( $file['error'] ) ) {
			self::clean( $file['tmp_name'] );
		}

		return $file;
	}

	/**
	 * Removes the place from a photo file, unless turned off with the
	 * crc_re_strip_photo_location filter.
	 *
	 * @param string $path Photo file.
	 * @return bool Whether the file was changed.
	 */
	public static function clean( $path ) {
		return self::wanted( $path ) ? self::strip( $path ) : false;
	}

	/**
	 * Removes the place from the photos already in the Media Library, a few
	 * at a time while people use the admin, until all are done.
	 */
	public function clean_existing() {
		global $wpdb;

		if ( '1' === get_option( self::DONE_OPTION ) || wp_doing_ajax() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Not while a download (a listing's zip, or all listings) is being made.
		if ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) {
			return;
		}

		$start = microtime( true );
		$last  = (int) get_option( self::CURSOR_OPTION, 0 );

		while ( microtime( true ) - $start < self::BUDGET ) {
			// By ID, so photos added or deleted meanwhile don't make it skip any; in the Trash too.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Walked once, in ID order.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE %s AND ID > %d ORDER BY ID ASC LIMIT %d", 'image/%', $last, self::BATCH ) );

			if ( ! $ids ) {
				update_option( self::DONE_OPTION, '1', false );
				delete_option( self::CURSOR_OPTION );

				return;
			}

			foreach ( $ids as $id ) {
				$id  = (int) $id;
				$try = get_option( self::TRYING_OPTION );

				// A photo being worked on now, or that stopped a request: wait a minute, then
				// try it once more, and after two stopped tries leave it out.
				if ( is_array( $try ) && isset( $try[0], $try[1], $try[2] ) && $id === (int) $try[0] ) {
					if ( time() - (int) $try[1] < MINUTE_IN_SECONDS ) {
						return;
					}

					if ( (int) $try[2] >= 2 ) {
						delete_option( self::TRYING_OPTION );
						$last = $id;
						update_option( self::CURSOR_OPTION, $last, false );
						continue;
					}

					update_option( self::TRYING_OPTION, array( $id, time(), (int) $try[2] + 1 ), false );
				} else {
					update_option( self::TRYING_OPTION, array( $id, time(), 1 ), false );
				}

				foreach ( self::photo_files( $id ) as $file ) {
					self::clean( $file );
				}

				delete_option( self::TRYING_OPTION );
				$last = $id;
				update_option( self::CURSOR_OPTION, $last, false );

				if ( microtime( true ) - $start >= self::BUDGET ) {
					return;
				}
			}
		}
	}

	/**
	 * Every file of a photo: the file as uploaded, the full-size one, the
	 * sizes WordPress made of it (which can keep the place on servers using
	 * Imagick), and the copies kept when it was edited in WordPress.
	 *
	 * @param int $id Attachment ID.
	 * @return string[]
	 */
	private static function photo_files( $id ) {
		$main  = (string) get_attached_file( $id );
		$files = array( $main );

		if ( function_exists( 'wp_get_original_image_path' ) ) {
			$files[] = (string) wp_get_original_image_path( $id );
		}

		if ( '' !== $main ) {
			$dir  = dirname( $main );
			$meta = wp_get_attachment_metadata( $id );

			foreach ( is_array( $meta ) && ! empty( $meta['sizes'] ) ? (array) $meta['sizes'] : array() as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = path_join( $dir, wp_basename( $size['file'] ) );
				}
			}

			$backup = get_post_meta( $id, '_wp_attachment_backup_sizes', true );

			foreach ( is_array( $backup ) ? $backup : array() as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = path_join( $dir, wp_basename( $size['file'] ) );
				}
			}
		}

		return array_values( array_unique( array_filter( $files ) ) );
	}

	/**
	 * Removes the place a photo was taken from a JPEG, PNG, WebP, AVIF or
	 * HEIC file. Other files are left alone, after reading only their first
	 * few bytes.
	 *
	 * @param string $path File.
	 * @return bool Whether the file was changed.
	 */
	public static function strip( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_file( $path ) || ! is_readable( $path ) || ! is_writable( $path ) ) {
			return false;
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local file.

		if ( ! $handle ) {
			return false;
		}

		$head = (string) fread( $handle, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.

		if ( 0 === strpos( $head, "\xFF\xD8\xFF" ) ) {
			$kind = 'jpeg';
		} elseif ( 0 === strpos( $head, "\x89PNG\r\n\x1A\n" ) ) {
			$kind = 'png';
		} elseif ( 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) ) {
			$kind = 'webp';
		} elseif ( 'ftyp' === substr( $head, 4, 4 ) ) {
			$kind = 'isobmff';
		} else {
			return false;
		}

		// A large photo needs more memory than the page might have.
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		if ( 'jpeg' === $kind ) {
			return self::strip_jpeg( $path );
		}

		if ( 'isobmff' === $kind ) {
			return self::strip_isobmff( $path );
		}

		$data  = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$clean = 'png' === $kind ? self::strip_png( $data ) : self::strip_webp( $data );

		if ( null === $clean || $clean === $data ) {
			return false;
		}

		return self::replace( $path, $clean );
	}

	/**
	 * Puts new contents in place of a file without ever leaving it cut
	 * short: they are written next to it first, then moved over it.
	 *
	 * @param string        $path File.
	 * @param string        $data New contents, or the new start of the file.
	 * @param int           $rest Where the rest of the old file, kept as it is, starts; -1 for none.
	 * @param resource|null $in   The old file, still open from reading it, so the rest comes from the
	 *                            very file that was read even if another request replaced it meanwhile.
	 * @return bool Whether the file was replaced.
	 */
	private static function replace( $path, $data, $rest = -1, $in = null ) {
		$temp = $path . '.crc-' . wp_generate_password( 6, false ) . '.tmp';
		$stat = $in ? fstat( $in ) : false;
		$size = $stat ? (int) $stat['size'] : (int) filesize( $path );
		$out  = fopen( $temp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local file.
		$ok   = (bool) $out;

		if ( $ok ) {
			$ok = strlen( $data ) === fwrite( $out, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Local file.
		}

		if ( $ok && $rest >= 0 ) {
			$ok = $in && 0 === fseek( $in, $rest ) && false !== stream_copy_to_stream( $in, $out );
		}

		// Closed before the new file takes its place, which Windows needs.
		if ( $in ) {
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.
		}

		if ( ! $out ) {
			return false;
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.
		clearstatcache();

		if ( $ok ) {
			$ok = filesize( $temp ) === strlen( $data ) + ( $rest >= 0 ? $size - $rest : 0 );
		}

		if ( $ok ) {
			@chmod( $temp, fileperms( $path ) & 0777 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Not every host allows it.
			$ok = @rename( $temp, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Reported by the return value.
		}

		if ( ! $ok && file_exists( $temp ) ) {
			wp_delete_file( $temp );
		}

		return (bool) $ok;
	}

	/**
	 * Writes a few changed bytes of a file where they are, keeping its length.
	 *
	 * @param string $path    File.
	 * @param array  $patches Offset => new bytes.
	 * @return bool
	 */
	private static function patch( $path, array $patches ) {
		$handle = fopen( $path, 'r+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local file.

		if ( ! $handle ) {
			return false;
		}

		$ok = true;

		foreach ( $patches as $at => $bytes ) {
			$ok = $ok && 0 === fseek( $handle, (int) $at ) && strlen( $bytes ) === fwrite( $handle, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Local file.
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.

		return $ok;
	}

	/**
	 * A JPEG without the place: the GPS part of its EXIF block is emptied,
	 * and XMP blocks that mention GPS are removed. Only the blocks before
	 * the picture itself are read.
	 *
	 * @param string $path File.
	 * @return bool Whether the file was changed.
	 */
	private static function strip_jpeg( $path ) {
		$in = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local file.

		if ( ! $in ) {
			return false;
		}

		$head    = (string) fread( $in, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.
		$pos     = 2;
		$rest    = -1;
		$changed = false;
		$patches = array();
		$moved   = false;

		while ( true ) {
			$mark = (string) fread( $in, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.

			if ( 2 !== strlen( $mark ) || "\xFF" !== $mark[0] ) {
				break;
			}

			$marker = ord( $mark[1] );

			// Padding between blocks: step over one byte.
			if ( 0xFF === $marker ) {
				fseek( $in, -1, SEEK_CUR );
				++$pos;
				$moved = true;
				continue;
			}

			// The picture itself starts here; nothing after it is changed.
			if ( 0xDA === $marker || 0xD9 === $marker ) {
				$rest = $pos;
				break;
			}

			// Markers without a length.
			if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
				$head .= $mark;
				$pos  += 2;
				continue;
			}

			$length = (string) fread( $in, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.
			$size   = 2 === strlen( $length ) ? unpack( 'n', $length )[1] : 0;
			$body   = $size > 2 ? (string) fread( $in, $size - 2 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.

			if ( $size < 2 || strlen( $body ) !== $size - 2 ) {
				break;
			}

			$block = $mark . $length . $body;

			if ( 0xE1 === $marker && 0 === strpos( $body, "Exif\0\0" ) ) {
				$tiff  = substr( $body, 6 );
				$clear = self::clear_gps( $tiff );

				if ( null === $clear ) {
					// An EXIF block that can't be read safely is left out altogether.
					$changed = true;
					$moved   = true;
					$pos    += 2 + $size;
					continue;
				}

				if ( $clear !== $tiff ) {
					$changed                 = true;
					$block                   = $mark . $length . "Exif\0\0" . $clear;
					$patches[ $pos + 10 ] = $clear;
				}
			} elseif ( 0xE1 === $marker && 0 === strpos( $body, 'http://ns.adobe.com/' ) && false !== stripos( $body, 'GPS' ) ) {
				$changed = true;
				$moved   = true;
				$pos    += 2 + $size;
				continue;
			}

			$head .= $block;
			$pos  += 2 + $size;
		}

		if ( ! $changed || $rest < 0 || ! $moved ) {
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.
		}

		if ( ! $changed || $rest < 0 ) {
			return false;
		}

		// Only the GPS bytes changed: write them where they are.
		if ( ! $moved ) {
			return self::patch( $path, $patches );
		}

		return self::replace( $path, $head, $rest, $in );
	}

	/**
	 * A PNG without the place: the GPS part of its EXIF chunk is emptied, and
	 * text that carries GPS (text named after GPS, copies of the EXIF as text,
	 * and XMP that mentions GPS) is removed.
	 *
	 * @param string $data File contents.
	 * @return string|null Null when the file isn't laid out as expected.
	 */
	private static function strip_png( $data ) {
		$length = strlen( $data );
		$pos    = 8;
		$edits  = array();

		while ( $pos + 12 <= $length ) {
			$size = unpack( 'N', substr( $data, $pos, 4 ) )[1];
			$type = substr( $data, $pos + 4, 4 );
			$full = 12 + $size;

			if ( $pos + $full > $length ) {
				return null;
			}

			if ( 'eXIf' === $type ) {
				$body  = substr( $data, $pos + 8, $size );
				$clear = self::clear_gps( $body );

				if ( null === $clear ) {
					$edits[ $pos ] = array( $full, '' );
				} elseif ( $clear !== $body ) {
					$edits[ $pos ] = array( $full, substr( $data, $pos, 8 ) . $clear . pack( 'N', crc32( $type . $clear ) ) );
				}
			} elseif ( in_array( $type, array( 'iTXt', 'tEXt', 'zTXt' ), true ) && self::text_has_place( $type, substr( $data, $pos + 8, $size ) ) ) {
				$edits[ $pos ] = array( $full, '' );
			}

			$pos += $full;

			if ( 'IEND' === $type ) {
				break;
			}
		}

		return $edits ? self::apply_edits( $data, 0, $pos, $edits ) : $data;
	}

	/**
	 * Text with some of its parts replaced.
	 *
	 * @param string  $data  Text.
	 * @param int     $from  Where the result starts.
	 * @param int     $to    Where it ends.
	 * @param array[] $edits Offset => array( length, new bytes ), in order.
	 * @return string
	 */
	private static function apply_edits( $data, $from, $to, array $edits ) {
		$out = '';

		foreach ( $edits as $at => $edit ) {
			$out  .= substr( $data, $from, $at - $from ) . $edit[1];
			$from  = $at + $edit[0];
		}

		return $out . substr( $data, $from, $to - $from );
	}

	/**
	 * Whether a PNG text chunk can hold the place: named after GPS (as
	 * ImageMagick writes exif:GPSLatitude), a copy of the EXIF or XMP as
	 * text (as GIMP writes), or XMP that mentions GPS or is packed.
	 *
	 * @param string $type Chunk type.
	 * @param string $body Chunk contents.
	 * @return bool
	 */
	private static function text_has_place( $type, $body ) {
		$zero    = strpos( $body, "\0" );
		$keyword = false !== $zero ? substr( $body, 0, $zero ) : $body;

		if ( false !== stripos( $keyword, 'GPS' ) || in_array( strtolower( $keyword ), array( 'raw profile type exif', 'raw profile type app1', 'raw profile type xmp' ), true ) ) {
			return true;
		}

		if ( 'XML:com.adobe.xmp' === $keyword ) {
			return 'zTXt' === $type || false !== stripos( $body, 'GPS' ) || "\x01" === substr( $body, $zero + 1, 1 );
		}

		return false;
	}

	/**
	 * A WebP without the place: the GPS part of its EXIF chunk is emptied,
	 * and XMP that mentions GPS is removed.
	 *
	 * @param string $data File contents.
	 * @return string|null Null when the file isn't laid out as expected.
	 */
	private static function strip_webp( $data ) {
		$length  = strlen( $data );
		$pos     = 12;
		$edits   = array();
		$dropped = 0;

		while ( $pos + 8 <= $length ) {
			$type = substr( $data, $pos, 4 );
			$size = unpack( 'V', substr( $data, $pos + 4, 4 ) )[1];
			$full = 8 + $size + ( $size % 2 );

			if ( $pos + 8 + $size > $length ) {
				return null;
			}

			if ( 'EXIF' === $type ) {
				$body   = substr( $data, $pos + 8, $size );
				$prefix = 0 === strpos( $body, "Exif\0\0" ) ? "Exif\0\0" : '';
				$clear  = self::clear_gps( substr( $body, strlen( $prefix ) ) );

				if ( null === $clear ) {
					// An EXIF chunk that can't be read safely is left out altogether.
					$edits[ $pos ] = array( $full, '' );
					$dropped      |= 0x08;
				} elseif ( $prefix . $clear !== $body ) {
					$edits[ $pos ] = array( 8 + $size, substr( $data, $pos, 8 ) . $prefix . $clear );
				}
			} elseif ( 'XMP ' === $type && false !== stripos( substr( $data, $pos + 8, $size ), 'GPS' ) ) {
				$edits[ $pos ] = array( $full, '' );
				$dropped      |= 0x04;
			}

			$pos += $full;
		}

		if ( ! $edits ) {
			return $data;
		}

		$out = self::apply_edits( $data, 12, min( $pos, $length ), $edits );

		// Without its EXIF or XMP chunk, the header mustn't say the file has one.
		if ( $dropped && 'VP8X' === substr( $out, 0, 4 ) ) {
			$out[8] = chr( ord( $out[8] ) & ~$dropped & 0xFF );
		}

		return 'RIFF' . pack( 'V', 4 + strlen( $out ) ) . 'WEBP' . $out;
	}

	/**
	 * An AVIF or HEIC photo without the place: these keep EXIF and XMP as
	 * items inside the file. The GPS part of the EXIF item is emptied and
	 * XMP that mentions GPS is blanked, in place, so nothing moves.
	 *
	 * @param string $path File.
	 * @return bool Whether the file was changed.
	 */
	private static function strip_isobmff( $path ) {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local file.

		if ( ! $handle ) {
			return false;
		}

		$size = (int) filesize( $path );
		$meta = null;
		$at   = 0;

		// The top-level boxes, to find the "meta" box that lists the items.
		while ( $at + 8 <= $size ) {
			fseek( $handle, $at );
			$box    = (string) fread( $handle, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.
			$length = unpack( 'N', substr( $box, 0, 4 ) )[1];
			$type   = substr( $box, 4, 4 );
			$header = 8;

			if ( 1 === $length ) {
				$length = self::number( substr( $box, 8, 8 ) );
				$header = 16;
			} elseif ( 0 === $length ) {
				$length = $size - $at;
			}

			if ( $length < $header || $at + $length > $size ) {
				break;
			}

			if ( 'meta' === $type && $length < 16777216 ) {
				fseek( $handle, $at + $header );
				$meta = array(
					'start' => $at + $header,
					'data'  => (string) fread( $handle, $length - $header ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.
				);
				break;
			}

			$at += $length;
		}

		if ( ! $meta ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.

			return false;
		}

		$items   = self::isobmff_items( $meta['data'], $meta['start'] );
		$patches = array();

		foreach ( $items as $item ) {
			if ( ! in_array( $item['type'], array( 'Exif', 'mime' ), true ) || ! $item['extents'] ) {
				continue;
			}

			// The item's bytes, which can be in several pieces.
			$bytes = '';

			foreach ( $item['extents'] as $extent ) {
				if ( $extent[1] <= 0 || $extent[0] < 0 || $extent[0] + $extent[1] > $size || $extent[1] > 16777216 ) {
					continue 2;
				}

				fseek( $handle, $extent[0] );
				$bytes .= (string) fread( $handle, $extent[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Local file.
			}

			if ( 'Exif' === $item['type'] ) {
				if ( strlen( $bytes ) < 4 ) {
					continue;
				}

				$skip  = 4 + unpack( 'N', substr( $bytes, 0, 4 ) )[1];
				$tiff  = substr( $bytes, $skip );
				$clear = self::clear_gps( $tiff );

				// EXIF that can't be read safely is emptied.
				$clean = substr( $bytes, 0, $skip ) . ( null === $clear ? str_repeat( "\0", strlen( $tiff ) ) : $clear );
			} else {
				$clean = false !== stripos( $bytes, 'GPS' ) ? str_repeat( ' ', strlen( $bytes ) ) : $bytes;
			}

			if ( $clean === $bytes ) {
				continue;
			}

			// Back into the pieces it came from.
			$offset = 0;

			foreach ( $item['extents'] as $extent ) {
				$patches[ $extent[0] ] = substr( $clean, $offset, $extent[1] );
				$offset               += $extent[1];
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local file.

		return $patches ? self::patch( $path, $patches ) : false;
	}

	/**
	 * The items listed in an AVIF or HEIC file's "meta" box, with where
	 * their bytes are in the file.
	 *
	 * @param string $meta  The meta box's contents.
	 * @param int    $start Where they start in the file.
	 * @return array[] Item ID => 'type' and 'extents' (each array( offset in the file, length )).
	 */
	private static function isobmff_items( $meta, $start ) {
		$types = array();
		$spots = array();
		$idat  = -1;
		$at    = 4;
		$end   = strlen( $meta );

		while ( $at + 8 <= $end ) {
			$length = unpack( 'N', substr( $meta, $at, 4 ) )[1];
			$type   = substr( $meta, $at + 4, 4 );

			if ( $length < 8 || $at + $length > $end ) {
				break;
			}

			$box = substr( $meta, $at + 8, $length - 8 );

			if ( 'iinf' === $type ) {
				$types = self::isobmff_types( $box );
			} elseif ( 'iloc' === $type ) {
				$spots = self::isobmff_spots( $box );
			} elseif ( 'idat' === $type ) {
				$idat = $start + $at + 8;
			}

			$at += $length;
		}

		$items = array();

		foreach ( $types as $id => $type ) {
			if ( ! isset( $spots[ $id ] ) ) {
				continue;
			}

			$extents = array();

			foreach ( $spots[ $id ]['extents'] as $extent ) {
				// Stored in the file, or in the meta box's own data ("idat").
				if ( 0 === $spots[ $id ]['method'] ) {
					$extents[] = array( $spots[ $id ]['base'] + $extent[0], $extent[1] );
				} elseif ( 1 === $spots[ $id ]['method'] && $idat >= 0 ) {
					$extents[] = array( $idat + $spots[ $id ]['base'] + $extent[0], $extent[1] );
				}
			}

			$items[ $id ] = array(
				'type'    => $type,
				'extents' => $extents,
			);
		}

		return $items;
	}

	/**
	 * Item types from an "iinf" box.
	 *
	 * @param string $box Box contents.
	 * @return string[] Item ID => type, e.g. "Exif".
	 */
	private static function isobmff_types( $box ) {
		$types = array();

		if ( strlen( $box ) < 8 ) {
			return $types;
		}

		$version = ord( $box[0] );
		$at      = 0 === $version ? 6 : 8;
		$end     = strlen( $box );
		$left    = self::MAX_PIECES;

		while ( $at + 8 <= $end && --$left >= 0 ) {
			$length = unpack( 'N', substr( $box, $at, 4 ) )[1];

			if ( $length < 8 || $at + $length > $end ) {
				break;
			}

			if ( 'infe' === substr( $box, $at + 4, 4 ) ) {
				$infe = (string) substr( $box, $at + 8, $length - 8 );
				$v    = '' === $infe ? -1 : ord( $infe[0] );

				if ( 2 === $v && strlen( $infe ) >= 12 ) {
					$types[ unpack( 'n', substr( $infe, 4, 2 ) )[1] ] = substr( $infe, 8, 4 );
				} elseif ( 3 === $v && strlen( $infe ) >= 14 ) {
					$types[ unpack( 'N', substr( $infe, 4, 4 ) )[1] ] = substr( $infe, 10, 4 );
				}
			}

			$at += $length;
		}

		return $types;
	}

	/**
	 * Where each item's bytes are, from an "iloc" box.
	 *
	 * @param string $box Box contents.
	 * @return array[] Item ID => 'method' (0: in the file, 1: in "idat"), 'base' and 'extents' (each array( offset, length )).
	 */
	private static function isobmff_spots( $box ) {
		$spots = array();
		$end   = strlen( $box );

		if ( $end < 8 ) {
			return $spots;
		}

		$version = ord( $box[0] );

		$offset_size = ord( $box[4] ) >> 4;
		$length_size = ord( $box[4] ) & 0x0F;
		$base_size   = ord( $box[5] ) >> 4;
		$index_size  = $version > 0 ? ord( $box[5] ) & 0x0F : 0;
		$left        = self::MAX_PIECES;

		// Sizes the format allows; anything else isn't read.
		foreach ( array( $offset_size, $length_size, $base_size, $index_size ) as $bytes ) {
			if ( ! in_array( $bytes, array( 0, 4, 8 ), true ) ) {
				return array();
			}
		}
		$at          = 6;
		$count       = $version < 2 ? unpack( 'n', substr( $box, $at, 2 ) )[1] : unpack( 'N', substr( $box, $at, 4 ) )[1];
		$at         += $version < 2 ? 2 : 4;
		$read        = function ( $bytes ) use ( $box, &$at ) {
			$value = self::number( substr( $box, $at, $bytes ) );
			$at   += $bytes;

			return $value;
		};

		for ( $i = 0; $i < $count && $at < $end; $i++ ) {
			// Real photos list a few hundred pieces at most; more means a broken file.
			if ( --$left < 0 ) {
				return array();
			}

			$id     = $read( $version < 2 ? 2 : 4 );
			$method = $version > 0 ? $read( 2 ) & 0x0F : 0;
			$read( 2 );
			$base    = $read( $base_size );
			$extents = array();
			$pieces  = $read( 2 );

			for ( $j = 0; $j < $pieces && $at <= $end; $j++ ) {
				if ( --$left < 0 ) {
					return array();
				}

				$read( $index_size );
				$offset    = $read( $offset_size );
				$extents[] = array( $offset, $read( $length_size ) );
			}

			if ( $at > $end ) {
				break;
			}

			$spots[ $id ] = array(
				'method'  => $method,
				'base'    => $base,
				'extents' => $extents,
			);
		}

		return $spots;
	}

	/**
	 * A big-endian number of 0, 2, 4 or 8 bytes.
	 *
	 * @param string $bytes Bytes.
	 * @return int
	 */
	private static function number( $bytes ) {
		$value = 0;
		$count = strlen( $bytes );

		for ( $i = 0; $i < $count; $i++ ) {
			$value = $value * 256 + ord( $bytes[ $i ] );
		}

		return (int) $value;
	}

	/**
	 * EXIF data (in TIFF layout) with its GPS entries emptied. The data keeps
	 * its length, so nothing around it moves; the rest of the EXIF, such as
	 * which way up the photo is, stays.
	 *
	 * @param string $tiff EXIF data.
	 * @return string|null Null when it can't be read safely.
	 */
	private static function clear_gps( $tiff ) {
		$length = strlen( $tiff );
		$order  = substr( $tiff, 0, 2 );

		if ( $length < 8 || ( 'II' !== $order && 'MM' !== $order ) ) {
			return null;
		}

		$short = function ( $at ) use ( $tiff, $order ) {
			return unpack( 'II' === $order ? 'v' : 'n', substr( $tiff, $at, 2 ) )[1];
		};
		$long  = function ( $at ) use ( $tiff, $order ) {
			return unpack( 'II' === $order ? 'V' : 'N', substr( $tiff, $at, 4 ) )[1];
		};

		$ifd = $long( 4 );

		if ( $ifd < 8 || $ifd + 2 > $length ) {
			return null;
		}

		$count = $short( $ifd );
		$gps   = 0;

		if ( $ifd + 2 + 12 * $count > $length ) {
			return null;
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$entry = $ifd + 2 + 12 * $i;

			if ( 0x8825 === $short( $entry ) ) {
				$gps = $long( $entry + 8 );
			}
		}

		if ( ! $gps ) {
			return $tiff;
		}

		if ( $gps < 8 || $gps + 2 > $length ) {
			return null;
		}

		$entries = $short( $gps );

		if ( 0 === $entries ) {
			return $tiff;
		}

		if ( $gps + 2 + 12 * $entries > $length ) {
			return null;
		}

		// Bytes per value of each EXIF data type.
		$sizes = array( 1 => 1, 2 => 1, 3 => 2, 4 => 4, 5 => 8, 6 => 1, 7 => 1, 8 => 2, 9 => 4, 10 => 8, 11 => 4, 12 => 8 );

		for ( $i = 0; $i < $entries; $i++ ) {
			$entry = $gps + 2 + 12 * $i;
			$type  = $short( $entry + 2 );
			$bytes = ( isset( $sizes[ $type ] ) ? $sizes[ $type ] : 1 ) * $long( $entry + 4 );

			// Values longer than 4 bytes are stored elsewhere; empty them there too.
			if ( $bytes > 4 ) {
				$at = $long( $entry + 8 );

				if ( $at >= 8 && $bytes <= $length - $at ) {
					$tiff = substr_replace( $tiff, str_repeat( "\0", $bytes ), $at, $bytes );
				}
			}
		}

		// No GPS entries left, and no further block after them.
		$wipe = min( 12 * $entries + 4, $length - $gps - 2 );
		$tiff = substr_replace( $tiff, str_repeat( "\0", 2 + $wipe ), $gps, 2 + $wipe );

		return $tiff;
	}
}
