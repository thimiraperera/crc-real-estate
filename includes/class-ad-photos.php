<?php
/**
 * Photos sent with the post a free ad form.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Admin\Listing_Export;

defined( 'ABSPATH' ) || exit;

/**
 * Photos people add to the post a free ad form.
 *
 * The browser makes each photo smaller, so neither side is bigger than the
 * largest size in Settings, turns it into WebP (JPEG where the browser
 * can't) and sends it in small pieces while people fill in the rest of the
 * form, so a big photo on a slow connection never stops halfway. The site
 * checks every piece, then checks that the whole is a real photo and saves
 * it again as WebP at the quality in Settings, which also leaves out where
 * it was taken. Photos are kept in a private folder, outside the website's
 * public folder when the server allows, never in the Media Library. When the
 * form is sent, its photos move to the ad's own folder and show on the ad in
 * Free Ads; they go when the ad is deleted for good.
 *
 * Pages with the form are cached, so the pieces carry no security token (it
 * would go stale). Random names, limits for each visitor and for the
 * waiting room, and the checks keep it safe instead. Photos never sent with
 * a form are removed after a day.
 */
final class Ad_Photos {

	const ACTION     = 'crc_re_ad_photo';
	const VIEW       = 'crc_re_ad_photo_view';
	const ZIP        = 'crc_re_ad_photos_zip';
	const CLEANUP    = 'crc_re_ad_photos_cleanup';
	const META       = '_crc_ad_photos';
	const DIR_OPTION = 'crc_re_private_dir';
	const CHUNK      = 524288; // Each piece the browser sends: 512 KB, or less when the server takes less.
	const MAX_BYTES  = 8388608; // The most one photo may weigh once the browser has made it smaller: 8 MB.
	const MAX_PIECES = 64;
	const MAX_PIXELS = 25000000;
	const KEEP       = 86400; // Photos never sent with a form are removed after a day.

	/**
	 * Photos one visitor may start in an hour, and how much they may send.
	 */
	const VISITOR_PHOTOS = 60;
	const VISITOR_BYTES  = 157286400; // 150 MB.

	/**
	 * The most all photos waiting for their form may weigh together: 500 MB.
	 */
	const WAITING_BYTES = 524288000;

	/**
	 * The kinds of photo kept: type => file extension.
	 */
	const TYPES = array(
		'image/webp' => 'webp',
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
	);

	/**
	 * Whether the browser settings were added to the page.
	 *
	 * @var bool
	 */
	private static $localized = false;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'receive' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'receive' ) );
		add_action( 'admin_post_' . self::VIEW, array( $this, 'view' ) );
		add_action( 'admin_post_' . self::ZIP, array( $this, 'zip' ) );
		add_action( 'before_delete_post', array( $this, 'deleted' ) );
		add_action( self::CLEANUP, array( $this, 'cleanup' ) );
		add_action( 'init', array( $this, 'schedule' ) );
	}

	/**
	 * Clears out photos never sent with a form, every hour.
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( self::CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP );
		}
	}

	/**
	 * Whether the form takes photos: Settings allows some, and there is a
	 * folder to keep them in.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (int) Settings::photos( 'count' ) > 0 && '' !== self::root();
	}

	/**
	 * The private folder for the photos. CRC_RE_PRIVATE_DIR in wp-config.php
	 * chooses it; otherwise it is a folder next to the website's public
	 * folder when the server allows, or else a locked folder in wp-content.
	 * The first that works is remembered, so photos stay together.
	 *
	 * @return string Folder, without a slash at the end; empty when none could be made.
	 */
	public static function root() {
		static $root = null;

		if ( null !== $root ) {
			return $root;
		}

		if ( defined( 'CRC_RE_PRIVATE_DIR' ) && '' !== (string) CRC_RE_PRIVATE_DIR ) {
			$wanted = untrailingslashit( wp_normalize_path( (string) CRC_RE_PRIVATE_DIR ) );
			$root   = self::prepare( $wanted ) ? $wanted : '';

			return $root;
		}

		$saved = (string) get_option( self::DIR_OPTION, '' );

		if ( '' !== $saved && self::prepare( $saved ) ) {
			$root = $saved;

			return $root;
		}

		foreach ( self::candidates() as $candidate ) {
			if ( self::prepare( $candidate ) ) {
				update_option( self::DIR_OPTION, $candidate, false );
				$root = $candidate;

				return $root;
			}
		}

		$root = '';

		return $root;
	}

	/**
	 * Whether the photos' folder is outside the website's public folder, so
	 * nothing in it can be opened from the internet.
	 *
	 * @return bool
	 */
	public static function outside_public() {
		$root = self::root();

		return '' !== $root && 0 !== strpos( trailingslashit( $root ), trailingslashit( self::public_folder() ) );
	}

	/**
	 * The folder the website is served from: the one the web server says,
	 * when WordPress is in it, or else the one WordPress is in.
	 *
	 * @return string
	 */
	private static function public_folder() {
		$site   = untrailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$public = isset( $_SERVER['DOCUMENT_ROOT'] ) ? untrailingslashit( wp_normalize_path( (string) realpath( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) ) ) : '';

		return '' !== $public && 0 === strpos( $site . '/', $public . '/' ) ? $public : $site;
	}

	/**
	 * Folders to try for the photos, best first: one next to the public
	 * folder, then one in wp-content with a name nobody can guess.
	 *
	 * @return string[]
	 */
	private static function candidates() {
		$public  = self::public_folder();
		$outside = '' !== $public ? untrailingslashit( wp_normalize_path( dirname( $public ) ) ) : '';
		$list    = array();

		if ( '' !== $outside && $outside !== $public && '.' !== $outside ) {
			$list[] = $outside . '/crc-real-estate-private';
		}

		$list[] = untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . '/crc-real-estate-private-' . strtolower( wp_generate_password( 12, false ) );

		return $list;
	}

	/**
	 * Makes the folder ready: there, writable, with its two inner folders,
	 * and locked so the web server never shows what is in it.
	 *
	 * @param string $dir Folder.
	 * @return bool
	 */
	private static function prepare( $dir ) {
		if ( '' === $dir || ! self::reachable( $dir ) ) {
			return false;
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		if ( ! wp_is_writable( $dir ) ) {
			return false;
		}

		foreach ( array( 'waiting', 'ads' ) as $inner ) {
			if ( ! is_dir( $dir . '/' . $inner ) && ! wp_mkdir_p( $dir . '/' . $inner ) ) {
				return false;
			}
		}

		$locks = array(
			'.htaccess' => "# Photos sent with the post a free ad form: never shown on the internet.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			'index.php' => "<?php\n// Silence is golden.\n",
		);

		foreach ( $locks as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A small file in the plugin's own folder.
			}
		}

		return true;
	}

	/**
	 * Whether PHP may use a folder, when the host limits PHP to some
	 * folders (open_basedir). Trying another one would only show warnings.
	 *
	 * @param string $dir Folder.
	 * @return bool
	 */
	private static function reachable( $dir ) {
		$allowed = (string) ini_get( 'open_basedir' );

		if ( '' === $allowed ) {
			return true;
		}

		$dir = trailingslashit( wp_normalize_path( $dir ) );

		foreach ( explode( PATH_SEPARATOR, $allowed ) as $base ) {
			$base = trailingslashit( wp_normalize_path( trim( $base ) ) );

			if ( '' !== trim( $base, '/.' ) && 0 === strpos( $dir, $base ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Where photos wait until their form is sent: a folder for each visit.
	 *
	 * @param string $session Visit, from id().
	 * @return string
	 */
	private static function waiting( $session ) {
		return self::root() . '/waiting/' . $session;
	}

	/**
	 * An ad's own folder of photos.
	 *
	 * @param int $ad_id Ad ID.
	 * @return string
	 */
	private static function ad_folder( $ad_id ) {
		return self::root() . '/ads/' . (int) $ad_id;
	}

	/**
	 * A name made by the browser for a visit or a photo: 32 letters and
	 * numbers from 0-9 and a-f, or an empty string when it isn't one.
	 *
	 * @param mixed $value Name as sent.
	 * @return string
	 */
	public static function id( $value ) {
		return is_string( $value ) && preg_match( '/^[a-f0-9]{32}$/', $value ) ? $value : '';
	}

	/**
	 * How big each piece the browser sends may be: 512 KB, or less when the
	 * server takes less in one go.
	 *
	 * @return int Bytes.
	 */
	public static function chunk_size() {
		$server = (int) floor( wp_max_upload_size() * 0.9 );

		return max( 65536, min( self::CHUNK, $server > 0 ? $server : self::CHUNK ) );
	}

	/**
	 * The photos box on the form: an Upload Photos button with the rules in
	 * a line under it, and the photos chosen, each sending in its turn.
	 * Photos can also be dropped anywhere on the box. ad-photos.js does the work.
	 *
	 * @param string $form Form ID.
	 * @return string
	 */
	public static function field( $form ) {
		$id    = $form . '-photos';
		$count = (int) Settings::photos( 'count' );

		return sprintf(
			'<div class="crc-inquiry-field crc-ad-photos" data-crc-field="photos" data-crc-photos data-max="%1$d" data-width="%2$d" data-height="%3$d" data-chunk="%4$d" data-bytes="%5$d">' .
			'<label class="crc-inquiry-label" for="%6$s">%7$s</label>' .
			'<div class="crc-ad-photos-button">' .
			'<input class="crc-ad-photos-input" id="%6$s" type="file" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="%6$s-hint %6$s-error">' .
			'<span class="crc-ad-photos-add" aria-hidden="true">%8$s<span>%9$s</span></span>' .
			'</div>' .
			'<p class="crc-ad-photos-hint" id="%6$s-hint">%10$s</p>' .
			'<ul class="crc-ad-photos-list" hidden></ul>' .
			'<p class="crc-ad-photos-status" role="status" hidden></p>' .
			'<p class="crc-inquiry-error" id="%6$s-error" role="alert" hidden></p>' .
			'<input type="hidden" name="crc_photo_session" value="">' .
			'</div>',
			$count,
			(int) Settings::photos( 'width' ),
			(int) Settings::photos( 'height' ),
			self::chunk_size(),
			self::MAX_BYTES,
			esc_attr( $id ),
			esc_html__( 'Photos', 'crc-real-estate' ),
			Icons::svg( 'upload', 'crc-ad-photos-icon' ),
			esc_html__( 'Upload Photos', 'crc-real-estate' ),
			/* translators: %s: most photos, e.g. 10. */
			esc_html( sprintf( __( 'Up to %s photos · JPG, PNG or WebP', 'crc-real-estate' ), number_format_i18n( $count ) ) )
		);
	}

	/**
	 * The words the photos box shows. %s, %1$s and %2$s are filled in by the browser.
	 *
	 * @return string[]
	 */
	public static function texts() {
		return array(
			/* translators: %s: number of the photo, e.g. 2. */
			'photo'      => __( 'Photo %s', 'crc-real-estate' ),
			/* translators: 1: photos added, 2: most photos. */
			'count'      => __( '%1$s of %2$s photos', 'crc-real-estate' ),
			'preparing'  => __( 'Getting it ready', 'crc-real-estate' ),
			'sending'    => __( 'Sending', 'crc-real-estate' ),
			'sent'       => __( 'Added', 'crc-real-estate' ),
			'failed'     => __( 'Couldn\'t be sent', 'crc-real-estate' ),
			'retry'      => __( 'Try again', 'crc-real-estate' ),
			/* translators: %s: number of the photo, e.g. 2. */
			'remove'     => __( 'Remove photo %s', 'crc-real-estate' ),
			/* translators: %s: most photos, e.g. 10. */
			'too_many'   => __( 'You can add up to %s photos.', 'crc-real-estate' ),
			/* translators: %s: file name. */
			'type'       => __( '%s isn\'t a JPG, PNG or WebP photo, so it wasn\'t added.', 'crc-real-estate' ),
			/* translators: 1: file name, 2: smallest size in pixels. */
			'small'      => __( '%1$s is too small. Please choose a photo at least %2$s pixels wide and high.', 'crc-real-estate' ),
			/* translators: %s: file name. */
			'huge'       => __( '%s is too big to send. Please choose a smaller photo.', 'crc-real-estate' ),
			/* translators: %s: file name. */
			'unreadable' => __( '%s couldn\'t be opened. Please choose a JPG, PNG or WebP photo.', 'crc-real-estate' ),
			/* translators: %s: file name. */
			'duplicate'  => __( '%s is already added.', 'crc-real-estate' ),
			'busy'       => __( 'Lots of photos are being sent right now. Please wait a few minutes, then try again.', 'crc-real-estate' ),
			'network'    => __( 'A photo couldn\'t be sent. Please check your internet connection, then try again.', 'crc-real-estate' ),
			'rejected'   => __( 'This photo couldn\'t be used. Please try another one.', 'crc-real-estate' ),
			'off'        => __( 'Photos can\'t be sent right now. Please send your ad without them, and we\'ll ask for them when we call.', 'crc-real-estate' ),
			'waiting'    => __( 'Your photos are still being sent. Your ad will go as soon as they\'re done.', 'crc-real-estate' ),
			'unsent'     => __( 'Some photos couldn\'t be sent. Please try them again, or remove them.', 'crc-real-estate' ),
		);
	}

	/**
	 * Loads the photos box's script, with its words, once a page.
	 */
	public static function enqueue() {
		wp_enqueue_script( 'crc-re-ad-photos', CRC_RE_URL . 'assets/js/ad-photos.js', array(), CRC_RE_VERSION, true );

		if ( self::$localized ) {
			return;
		}

		self::$localized = true;

		wp_localize_script(
			'crc-re-ad-photos',
			'crcReAdPhotos',
			array(
				'url'      => admin_url( 'admin-ajax.php' ),
				'action'   => self::ACTION,
				'messages' => self::texts(),
			)
		);
	}

	/**
	 * Receives one piece of a photo. Pieces come in order; one sent again
	 * after its answer was lost is answered without being kept twice. After
	 * the last one the photo is checked and saved again as WebP.
	 */
	public function receive() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Cached public form, see the class description.
		$session = self::id( isset( $_POST['session'] ) ? sanitize_key( wp_unslash( $_POST['session'] ) ) : '' );
		$photo   = self::id( isset( $_POST['photo'] ) ? sanitize_key( wp_unslash( $_POST['photo'] ) ) : '' );
		$index   = isset( $_POST['index'] ) && is_numeric( $_POST['index'] ) ? (int) $_POST['index'] : -1;
		$total   = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;
		$size    = isset( $_POST['size'] ) ? absint( $_POST['size'] ) : 0;
		$type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$piece   = isset( $_FILES['chunk'] ) && is_array( $_FILES['chunk'] ) ? $_FILES['chunk'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only PHP's own temporary file is used.
		// phpcs:enable

		if ( ! self::enabled() ) {
			self::refuse( 'off', 503 );
		}

		$length = isset( $piece['tmp_name'], $piece['error'], $piece['size'] ) && UPLOAD_ERR_OK === (int) $piece['error'] && is_uploaded_file( $piece['tmp_name'] ) ? (int) $piece['size'] : 0;

		if ( '' === $session || '' === $photo || ! isset( self::TYPES[ $type ] ) || $total < 1 || $total > self::MAX_PIECES || $index < 0 || $index >= $total || $size < 1 || $size > self::MAX_BYTES || $length < 1 || $length > 2 * self::CHUNK ) {
			self::refuse( 'invalid', 400 );
		}

		$folder = self::waiting( $session );
		$file   = $folder . '/' . $photo;

		if ( ! is_dir( $folder ) ) {
			if ( 0 !== $index ) {
				self::refuse( 'invalid', 400 );
			}

			if ( self::too_busy( $folder ) ) {
				self::refuse( 'busy', 429 );
			}

			if ( ! wp_mkdir_p( $folder ) ) {
				self::refuse( 'off', 503 );
			}
		}

		// One piece of a photo at a time, also when a piece is sent again while the first is still arriving.
		$lock = fopen( $file . '.lock', 'c' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A lock file in the plugin's own folder.

		if ( ! $lock || ! flock( $lock, LOCK_EX ) ) {
			self::refuse( 'off', 503 );
		}

		$info = self::info( $file );

		if ( ! $info ) {
			if ( 0 !== $index ) {
				self::refuse( 'invalid', 400 );
			}

			if ( self::too_busy( $folder ) ) {
				self::refuse( 'busy', 429 );
			}

			$info = array(
				'size'     => $size,
				'type'     => $type,
				'total'    => $total,
				'received' => 0,
				'bytes'    => 0,
			);

			self::count( 'photos', 1 );
		}

		if ( $info['size'] !== $size || $info['type'] !== $type || $info['total'] !== $total ) {
			self::refuse( 'invalid', 400 );
		}

		// The whole photo came already: the answer to its last piece was lost.
		if ( ! empty( $info['done'] ) ) {
			self::answer( $info );
		}

		// A piece sent again after its answer was lost.
		if ( $index < $info['received'] ) {
			wp_send_json_success( array( 'received' => $info['received'] ) );
		}

		if ( $index > $info['received'] || $info['bytes'] + $length > $size ) {
			self::refuse( 'order', 409 );
		}

		if ( self::counted( 'bytes' ) + $length > self::VISITOR_BYTES ) {
			self::refuse( 'busy', 429 );
		}

		if ( ! self::append( $piece['tmp_name'], $file . '.part' ) ) {
			self::refuse( 'off', 503 );
		}

		self::count( 'bytes', $length );
		++$info['received'];
		$info['bytes'] += $length;

		if ( $info['received'] < $total ) {
			self::save_info( $file, $info );
			wp_send_json_success( array( 'received' => $info['received'] ) );
		}

		// The last piece: the whole photo is here.
		$photo_info = $info['bytes'] === $size ? self::finish( $file ) : new \WP_Error( 'size' );

		wp_delete_file( $file . '.part' );

		if ( is_wp_error( $photo_info ) ) {
			wp_delete_file( $file . '.json' );
			self::refuse( 'rejected', 422 );
		}

		$info = array_merge( $info, $photo_info, array( 'done' => true ) );

		self::save_info( $file, $info );
		self::answer( $info );
	}

	/**
	 * Whether a new photo has to wait: the visitor started a lot of photos
	 * this hour, the visit has a lot of them, or the waiting room is full.
	 *
	 * @param string $folder The visit's folder.
	 * @return bool
	 */
	private static function too_busy( $folder ) {
		if ( self::counted( 'photos' ) >= self::VISITOR_PHOTOS ) {
			return true;
		}

		$started = is_dir( $folder ) ? glob( $folder . '/*.json' ) : array();

		if ( is_array( $started ) && count( $started ) >= 3 * max( 1, (int) Settings::photos( 'count' ) ) ) {
			return true;
		}

		return self::waiting_bytes() >= self::WAITING_BYTES;
	}

	/**
	 * How much the photos waiting for their form weigh together.
	 *
	 * @return int Bytes.
	 */
	private static function waiting_bytes() {
		$total = 0;
		$dir   = self::root() . '/waiting';

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			$total += $file->isFile() ? (int) $file->getSize() : 0;
		}

		return $total;
	}

	/**
	 * A private name for what the visitor sends this hour.
	 *
	 * @param string $what "photos" or "bytes".
	 * @return string
	 */
	private static function counter( $what ) {
		return 'crc_re_ad_photo_' . $what . '_' . substr( wp_hash( Form::ip() ), 0, 20 );
	}

	/**
	 * What the visitor has sent this hour.
	 *
	 * @param string $what "photos" or "bytes".
	 * @return int
	 */
	private static function counted( $what ) {
		return (int) get_transient( self::counter( $what ) );
	}

	/**
	 * Counts what the visitor sends.
	 *
	 * @param string $what   "photos" or "bytes".
	 * @param int    $amount How many photos or bytes.
	 */
	private static function count( $what, $amount ) {
		set_transient( self::counter( $what ), self::counted( $what ) + (int) $amount, HOUR_IN_SECONDS );
	}

	/**
	 * Adds a piece to the end of the photo.
	 *
	 * @param string $from The piece, as PHP received it.
	 * @param string $to   The photo so far.
	 * @return bool
	 */
	private static function append( $from, $to ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Pieces are added on the end without holding the photo in memory.
		$in  = fopen( $from, 'rb' );
		$out = fopen( $to, 'ab' );
		$ok  = $in && $out && false !== stream_copy_to_stream( $in, $out );

		if ( $in ) {
			fclose( $in );
		}

		if ( $out ) {
			fclose( $out );
		}
		// phpcs:enable

		return $ok;
	}

	/**
	 * What is known about a photo: its size, type and pieces so far, and once
	 * it is all here, its file, width, height and weight.
	 *
	 * @param string $file The photo's path, without an extension.
	 * @return array Empty when nothing is known.
	 */
	private static function info( $file ) {
		$json = is_readable( $file . '.json' ) ? json_decode( (string) file_get_contents( $file . '.json' ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A small file in the plugin's own folder.

		return is_array( $json ) ? $json : array();
	}

	/**
	 * Keeps what is known about a photo.
	 *
	 * @param string $file The photo's path, without an extension.
	 * @param array  $info See info().
	 */
	private static function save_info( $file, array $info ) {
		file_put_contents( $file . '.json', wp_json_encode( $info ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A small file in the plugin's own folder.
	}

	/**
	 * Checks the whole photo and saves it again: no side bigger than the
	 * largest size, as WebP at the quality in Settings (JPEG when the server
	 * can't make WebP).
	 *
	 * @param string $file The photo's path, without an extension.
	 * @return array|\WP_Error 'file', 'width', 'height', 'bytes' and 'type'.
	 */
	private static function finish( $file ) {
		$part = $file . '.part';
		$size = wp_getimagesize( $part );

		if ( ! $size || empty( $size[0] ) || empty( $size[1] ) || empty( $size['mime'] ) || ! isset( self::TYPES[ $size['mime'] ] ) || $size[0] * $size[1] > self::MAX_PIXELS ) {
			return new \WP_Error( 'not_photo' );
		}

		$width   = (int) Settings::photos( 'width' );
		$height  = (int) Settings::photos( 'height' );
		$quality = (int) Settings::photos( 'quality' );

		wp_raise_memory_limit( 'image' );

		$editor = wp_get_image_editor( $part, array( 'mime_type' => $size['mime'] ) );

		// No GD or Imagick: a WebP photo that is small enough is kept as it came.
		if ( is_wp_error( $editor ) ) {
			if ( 'image/webp' !== $size['mime'] || $size[0] > $width || $size[1] > $height || ! copy( $part, $file . '.webp' ) ) {
				return new \WP_Error( 'no_editor' );
			}

			return array(
				'file'   => basename( $file ) . '.webp',
				'width'  => (int) $size[0],
				'height' => (int) $size[1],
				'bytes'  => (int) filesize( $file . '.webp' ),
				'type'   => 'image/webp',
			);
		}

		if ( $size[0] > $width || $size[1] > $height ) {
			$resized = $editor->resize( $width, $height, false );

			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
		}

		$mime = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'image/webp' : 'image/jpeg';

		// The quality in Settings, also when the photo changes type on the way.
		$force = function () use ( $quality ) {
			return $quality;
		};

		add_filter( 'wp_editor_set_quality', $force, 99 );
		$editor->set_quality( $quality );
		$saved = $editor->save( $file . '.' . self::TYPES[ $mime ], $mime );
		remove_filter( 'wp_editor_set_quality', $force, 99 );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( empty( $saved['path'] ) || ! is_file( $saved['path'] ) || empty( $saved['mime-type'] ) || ! isset( self::TYPES[ $saved['mime-type'] ] ) ) {
			if ( ! empty( $saved['path'] ) ) {
				wp_delete_file( $saved['path'] );
			}

			return new \WP_Error( 'not_saved' );
		}

		return array(
			'file'   => basename( $saved['path'] ),
			'width'  => (int) $saved['width'],
			'height' => (int) $saved['height'],
			'bytes'  => (int) filesize( $saved['path'] ),
			'type'   => (string) $saved['mime-type'],
		);
	}

	/**
	 * Answers that the whole photo arrived, with its size.
	 *
	 * @param array $info See info().
	 */
	private static function answer( array $info ) {
		wp_send_json_success(
			array(
				'done'   => true,
				'width'  => (int) $info['width'],
				'height' => (int) $info['height'],
			)
		);
	}

	/**
	 * Answers that a piece wasn't taken, and why.
	 *
	 * @param string $code   Why: "off", "invalid", "busy", "order" or "rejected".
	 * @param int    $status HTTP status.
	 */
	private static function refuse( $code, $status ) {
		$texts   = self::texts();
		$message = isset( $texts[ $code ] ) ? $texts[ $code ] : $texts['network'];

		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * The photos sent with a form, in the order they were on it, once each:
	 * only whole photos of that visit.
	 *
	 * @param string   $session Visit, from the form.
	 * @param string[] $ids     Photos, from the form.
	 * @return array[]|\WP_Error See info(); a WP_Error "count" when there are too many, "missing" when one isn't all here.
	 */
	public static function ready( $session, array $ids ) {
		$session = self::id( $session );
		$ids     = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'id' ), $ids ) ) ) );
		$photos  = array();

		if ( ! $ids ) {
			return array();
		}

		if ( count( $ids ) > (int) Settings::photos( 'count' ) ) {
			return new \WP_Error( 'count' );
		}

		if ( '' === $session || '' === self::root() ) {
			return new \WP_Error( 'missing' );
		}

		foreach ( $ids as $id ) {
			$file = self::waiting( $session ) . '/' . $id;
			$info = self::info( $file );
			$name = isset( $info['file'] ) ? basename( (string) $info['file'] ) : '';

			if ( empty( $info['done'] ) || 0 !== strpos( $name, $id . '.' ) || empty( $info['type'] ) || ! isset( self::TYPES[ $info['type'] ] ) || ! is_file( dirname( $file ) . '/' . $name ) ) {
				return new \WP_Error( 'missing' );
			}

			$info['file'] = $name;
			$photos[]     = $info;
		}

		return $photos;
	}

	/**
	 * Moves the photos sent with a form into the ad's own folder, as
	 * photo-1.webp, photo-2.webp and so on, and notes them on the ad.
	 * Photos taken off the form before it was sent are removed.
	 *
	 * @param string   $session Visit, from the form.
	 * @param string[] $ids     Photos, from the form.
	 * @param int      $ad_id   The ad just kept.
	 * @return array[] The photos kept: 'file', 'width', 'height', 'bytes' and 'type'.
	 */
	public static function attach( $session, array $ids, $ad_id ) {
		$photos = self::ready( $session, $ids );
		$kept   = array();

		if ( is_wp_error( $photos ) || ! $photos || ! $ad_id || ! wp_mkdir_p( self::ad_folder( $ad_id ) ) ) {
			return $kept;
		}

		$waiting = self::waiting( self::id( $session ) );

		foreach ( $photos as $n => $info ) {
			$name = 'photo-' . ( $n + 1 ) . '.' . self::TYPES[ $info['type'] ];

			if ( rename( $waiting . '/' . $info['file'], self::ad_folder( $ad_id ) . '/' . $name ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Within the plugin's own folder.
				$kept[] = array(
					'file'   => $name,
					'width'  => (int) $info['width'],
					'height' => (int) $info['height'],
					'bytes'  => (int) $info['bytes'],
					'type'   => (string) $info['type'],
				);
			}
		}

		self::remove_folder( $waiting );

		if ( $kept ) {
			update_post_meta( $ad_id, self::META, $kept );
		}

		return $kept;
	}

	/**
	 * An ad's photos that are still there, with their paths.
	 *
	 * @param int $ad_id Ad ID.
	 * @return array[] 'file', 'width', 'height', 'bytes', 'type' and 'path'.
	 */
	public static function of( $ad_id ) {
		$saved  = get_post_meta( $ad_id, self::META, true );
		$photos = array();

		if ( ! is_array( $saved ) || '' === self::root() ) {
			return $photos;
		}

		foreach ( $saved as $photo ) {
			$name = is_array( $photo ) && isset( $photo['file'] ) ? basename( (string) $photo['file'] ) : '';
			$path = self::ad_folder( $ad_id ) . '/' . $name;

			if ( '' === $name || empty( $photo['type'] ) || ! isset( self::TYPES[ $photo['type'] ] ) || ! is_file( $path ) ) {
				continue;
			}

			$photos[] = array(
				'file'   => $name,
				'width'  => isset( $photo['width'] ) ? (int) $photo['width'] : 0,
				'height' => isset( $photo['height'] ) ? (int) $photo['height'] : 0,
				'bytes'  => isset( $photo['bytes'] ) ? (int) $photo['bytes'] : 0,
				'type'   => (string) $photo['type'],
				'path'   => $path,
			);
		}

		return $photos;
	}

	/**
	 * The address that shows a photo in wp-admin, or downloads it.
	 *
	 * @param int  $ad_id    Ad ID.
	 * @param int  $n        Photo, from 0.
	 * @param bool $download Whether it downloads.
	 * @return string
	 */
	public static function url( $ad_id, $n, $download = false ) {
		$args = array(
			'action' => self::VIEW,
			'ad'     => (int) $ad_id,
			'photo'  => (int) $n,
		);

		if ( $download ) {
			$args['download'] = 1;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::VIEW . '_' . (int) $ad_id );
	}

	/**
	 * The address that downloads all of an ad's photos as a zip file.
	 *
	 * @param int $ad_id Ad ID.
	 * @return string
	 */
	public static function zip_url( $ad_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ZIP,
					'ad'     => (int) $ad_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ZIP . '_' . (int) $ad_id
		);
	}

	/**
	 * Stops unless the person may see the ad.
	 *
	 * @param int $ad_id Ad ID.
	 */
	private static function allowed( $ad_id ) {
		if ( ! $ad_id || Ad_Requests::NAME !== get_post_type( $ad_id ) || ! current_user_can( 'edit_post', $ad_id ) ) {
			wp_die( esc_html__( 'Sorry, you can\'t see the photos of this ad.', 'crc-real-estate' ), 403 );
		}
	}

	/**
	 * Shows or downloads one photo, straight from the private folder.
	 */
	public function view() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checked just below.
		$ad_id    = isset( $_GET['ad'] ) ? absint( $_GET['ad'] ) : 0;
		$n        = isset( $_GET['photo'] ) ? absint( $_GET['photo'] ) : 0;
		$download = ! empty( $_GET['download'] );
		// phpcs:enable

		check_admin_referer( self::VIEW . '_' . $ad_id );
		self::allowed( $ad_id );

		$photos = self::of( $ad_id );

		if ( ! isset( $photos[ $n ] ) ) {
			wp_die( esc_html__( 'This photo isn\'t there any more.', 'crc-real-estate' ), 404 );
		}

		$photo = $photos[ $n ];

		header( 'Content-Type: ' . $photo['type'] );
		header( 'Content-Length: ' . filesize( $photo['path'] ) );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="free-ad-' . $ad_id . '-photo-' . ( $n + 1 ) . '.' . self::TYPES[ $photo['type'] ] . '"' );
		header( 'Cache-Control: private, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $photo['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a photo.
		exit;
	}

	/**
	 * Downloads all of an ad's photos as a zip file.
	 */
	public function zip() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked just below.
		$ad_id = isset( $_GET['ad'] ) ? absint( $_GET['ad'] ) : 0;

		check_admin_referer( self::ZIP . '_' . $ad_id );
		self::allowed( $ad_id );

		$files = array();

		foreach ( self::of( $ad_id ) as $photo ) {
			$files[ $photo['file'] ] = array( 'file' => $photo['path'] );
		}

		if ( ! $files ) {
			wp_die( esc_html__( 'This ad has no photos.', 'crc-real-estate' ), 404 );
		}

		$name = 'free-ad-' . $ad_id . '-photos';
		$zip  = Listing_Export::zip( $name, $files );

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

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a download.
		wp_delete_file( $zip );
		exit;
	}

	/**
	 * Removes an ad's photos when the ad is deleted for good.
	 *
	 * @param int $post_id Post ID.
	 */
	public function deleted( $post_id ) {
		if ( Ad_Requests::NAME === get_post_type( $post_id ) && '' !== self::root() ) {
			self::remove_folder( self::ad_folder( $post_id ) );
		}
	}

	/**
	 * Removes photos never sent with a form: visits with nothing new for a day.
	 */
	public function cleanup() {
		$root = self::root();

		if ( '' === $root ) {
			return;
		}

		$visits = glob( $root . '/waiting/*', GLOB_ONLYDIR );

		foreach ( is_array( $visits ) ? $visits : array() as $visit ) {
			$newest = (int) filemtime( $visit );
			$files  = glob( $visit . '/*' );

			foreach ( is_array( $files ) ? $files : array() as $file ) {
				$newest = max( $newest, (int) filemtime( $file ) );
			}

			if ( $newest < time() - self::KEEP ) {
				self::remove_folder( $visit );
			}
		}
	}

	/**
	 * Removes a folder of the plugin's photos with everything in it. Only
	 * folders inside the photos' folder are touched.
	 *
	 * @param string $dir Folder.
	 */
	private static function remove_folder( $dir ) {
		$root = self::root();
		$dir  = untrailingslashit( wp_normalize_path( $dir ) );

		if ( '' === $root || 0 !== strpos( $dir, $root . '/' ) || ! is_dir( $dir ) ) {
			return;
		}

		$names = scandir( $dir );

		foreach ( is_array( $names ) ? $names : array() as $name ) {
			$path = $dir . '/' . $name;

			if ( '.' === $name || '..' === $name ) {
				continue;
			}

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::remove_folder( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- An empty folder of the plugin's own.
	}
}
