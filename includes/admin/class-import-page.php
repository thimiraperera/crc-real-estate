<?php
/**
 * Import and export page.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Csv;
use CRC\RealEstate\Importer;
use CRC\RealEstate\Listing_Data;
use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Faq;
use CRC\RealEstate\Sections\Gallery;

defined( 'ABSPATH' ) || exit;

/**
 * Listings → Import & Export: adds or changes listings from a spreadsheet
 * saved as CSV, gives a sample file that shows the columns, and downloads
 * every listing in the same format.
 */
final class Import_Page {

	const SLUG   = 'crc-real-estate-import';
	const NONCE  = 'crc_re_import';
	const SAMPLE = 'crc_re_sample_csv';
	const EXPORT = 'crc_re_export_csv';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		// After Listings → Inquiries, which WordPress adds at the usual time.
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_crc_re_import_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_crc_re_import_step', array( $this, 'ajax_step' ) );
		add_action( 'wp_ajax_crc_re_import_stop', array( $this, 'ajax_stop' ) );
		add_action( 'admin_post_' . self::SAMPLE, array( $this, 'download_sample' ) );
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'download_export' ) );
	}

	/**
	 * Address of the page.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=' . Post_Type::NAME . '&page=' . self::SLUG );
	}

	/**
	 * Who can use the page: people who can change everyone's listings.
	 *
	 * @return string
	 */
	private static function capability() {
		$type = get_post_type_object( Post_Type::NAME );

		return $type ? (string) $type->cap->edit_others_posts : 'edit_others_posts';
	}

	/**
	 * Whether the current person can import: change everyone's listings and
	 * add photos to the Media Library.
	 *
	 * @return bool
	 */
	private static function can_import() {
		return current_user_can( self::capability() ) && current_user_can( 'upload_files' );
	}

	/**
	 * Adds the page under Listings.
	 */
	public function menu() {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'Import & Export', 'crc-real-estate' ),
			__( 'Import & Export', 'crc-real-estate' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Loads the page's files.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'crc-re-admin', CRC_RE_URL . 'assets/css/admin.css', array(), CRC_RE_VERSION );
		wp_enqueue_script( 'crc-re-admin-import', CRC_RE_URL . 'assets/js/admin-import.js', array(), CRC_RE_VERSION, true );

		$pending = Importer::pending( get_current_user_id() );

		wp_localize_script(
			'crc-re-admin-import',
			'crcImport',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'maxSize' => self::max_size(),
				'pending' => $pending,
				'last'    => $pending ? null : Importer::last( get_current_user_id() ),
				'text'    => self::messages(),
			)
		);
	}

	/**
	 * Largest file the page takes: 10 MB, or less when the server allows less.
	 *
	 * @return int Bytes.
	 */
	private static function max_size() {
		$size = min( Importer::MAX_SIZE, wp_max_upload_size() );
		$post = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );

		// A file bigger than the server takes in one request never arrives at all.
		if ( $post > 0 ) {
			$size = min( $size, $post - 65536 );
		}

		return (int) max( 0, $size );
	}

	/**
	 * Words the page's script shows.
	 *
	 * @return string[]
	 */
	private static function messages() {
		return array(
			'choose'      => __( 'Please choose the CSV file first.', 'crc-real-estate' ),
			/* translators: %s: largest file size, e.g. "10 MB". */
			'tooBig'      => sprintf( __( 'The file is too big. It can be up to %s; split it into smaller files.', 'crc-real-estate' ), size_format( self::max_size() ) ),
			'notCsv'      => __( 'Please choose a CSV file (it ends in .csv). In Excel, use File → Save As → CSV UTF-8.', 'crc-real-estate' ),
			'reading'     => __( 'Reading the file…', 'crc-real-estate' ),
			/* translators: 1: rows done, 2: all rows. */
			'progress'    => __( '%1$s of %2$s listings done', 'crc-real-estate' ),
			/* translators: 1: listings added, 2: listings changed, 3: rows that couldn't be imported. */
			'counts'      => __( 'Added: %1$s · Changed: %2$s · Couldn\'t import: %3$s', 'crc-real-estate' ),
			'working'     => __( 'Importing. Photos take a few seconds each, so please keep this page open until it finishes.', 'crc-real-estate' ),
			'finished'    => __( 'The import is finished.', 'crc-real-estate' ),
			'stopped'     => __( 'The import was stopped. The listings imported before that are kept.', 'crc-real-estate' ),
			'stop'        => __( 'Stop the import', 'crc-real-estate' ),
			'stopAsk'     => __( 'Stop the import? The listings imported so far are kept.', 'crc-real-estate' ),
			'carryOn'     => __( 'Continue the import', 'crc-real-estate' ),
			/* translators: %s: what went wrong. */
			'failed'      => __( 'The import paused because the site didn\'t answer (%s). Nothing is lost: press Continue the import to carry on from where it stopped.', 'crc-real-estate' ),
			/* translators: %s: what went wrong. */
			'startFailed' => __( 'The file couldn\'t be sent to the site (%s). Nothing was imported. Please try again; if it keeps happening, try a smaller file.', 'crc-real-estate' ),
			'noAnswer'    => __( 'no answer from the site', 'crc-real-estate' ),
			'slow'        => __( 'the site took too long to answer', 'crc-real-estate' ),
			'busySite'    => __( 'the site is busy', 'crc-real-estate' ),
			'blocked'     => __( 'the site\'s security settings blocked the request', 'crc-real-estate' ),
			'siteError'   => __( 'the site had a problem', 'crc-real-estate' ),
			'waiting'     => __( 'Waiting for the step that is still working to finish…', 'crc-real-estate' ),
			'stopping'    => __( 'Stopping the import… waiting for the part that is still working to finish.', 'crc-real-estate' ),
			/* translators: %s: what went wrong. */
			'stopAsked'   => __( 'The site was asked to stop the import, but its answer didn\'t arrive (%s). It is probably stopped already: press Stop the import again to check, or reload this page in a minute.', 'crc-real-estate' ),
			/* translators: %s: what went wrong. */
			'stopFailed'  => __( 'The import couldn\'t be stopped because the site didn\'t answer (%s). Nothing has changed: press Stop the import to try again, or Continue the import to carry on.', 'crc-real-estate' ),
			'loggedOut'   => __( 'You were logged out of the site. Please log in again (WordPress may show a login box), then reload this page. The import waits for you there; nothing is lost.', 'crc-real-estate' ),
			'unfinished'  => __( 'An import you started earlier isn\'t finished yet. Please press Continue the import to finish it, or Stop the import, and then start the new file. Starting again without stopping could add the same listings twice.', 'crc-real-estate' ),
			'lastDone'    => __( 'Your last import is finished. Check All Listings before importing the same file again, or its listings will be added twice.', 'crc-real-estate' ),
			'lastStopped' => __( 'Your last import was stopped. The listings imported before that are kept. Check All Listings before importing the same file again, or its listings will be added twice.', 'crc-real-estate' ),
			/* translators: 1: rows done, 2: all rows. */
			'pending'     => __( 'An import was left unfinished, or is still running in another window: %1$s of %2$s listings done. Continue it here, or stop it to start a new one.', 'crc-real-estate' ),
			/* translators: %s: column names. */
			'unknown'     => __( 'These columns aren\'t used by the plugin, so they are left out: %s. Check their names against the sample file if you meant them to be imported.', 'crc-real-estate' ),
			/* translators: %s: row number in the spreadsheet. */
			'row'         => __( 'Row %s', 'crc-real-estate' ),
			'created'     => __( 'Added', 'crc-real-estate' ),
			'updated'     => __( 'Changed', 'crc-real-estate' ),
			'failedRow'   => __( 'Couldn\'t import', 'crc-real-estate' ),
			'noTitle'     => __( '(no title)', 'crc-real-estate' ),
			'edit'        => __( 'Open the listing', 'crc-real-estate' ),
			'statuses'    => array(
				'publish' => __( 'published', 'crc-real-estate' ),
				'draft'   => __( 'draft', 'crc-real-estate' ),
				'pending' => __( 'pending review', 'crc-real-estate' ),
				'private' => __( 'private', 'crc-real-estate' ),
				'future'  => __( 'scheduled', 'crc-real-estate' ),
			),
			'leave'       => __( 'The import is still running. Leaving the page pauses it.', 'crc-real-estate' ),
		);
	}

	/**
	 * Prints the page.
	 */
	public function render() {
		$sample = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::SAMPLE ), self::SAMPLE );
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT ), self::EXPORT );
		?>
		<div class="wrap crc-info crc-import">
			<h1><?php esc_html_e( 'Import & Export', 'crc-real-estate' ); ?></h1>
			<p class="crc-info-intro">
				<?php esc_html_e( 'Add many listings at once from a spreadsheet, change listings that are already on the site, or download your listings to keep a copy or work on them in Excel or Google Sheets.', 'crc-real-estate' ); ?>
			</p>

			<section class="crc-info-card" id="crc-import">
				<h2 class="crc-info-title"><?php esc_html_e( 'Import listings from a spreadsheet', 'crc-real-estate' ); ?></h2>
				<ol class="crc-import-steps">
					<li><?php esc_html_e( 'Download the sample file and open it in Excel or Google Sheets. It has every column, with three example listings to show how each one is filled in.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Put one listing on each row, under the column names. Keep the first row with the column names as it is. Columns you don\'t need can be deleted or left empty.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'For photos, put links to them (starting with https://). They are downloaded into the Media Library, so they keep working even if the links stop working later. Google Drive and Dropbox links work when the file is shared with anyone who has the link.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Save the file as CSV. In Excel: File → Save As → CSV UTF-8 (Comma delimited). In Google Sheets: File → Download → Comma-separated values.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Choose the file below and press Start import. Keep this page open until it says the import is finished. Every row is listed with what happened to it.', 'crc-real-estate' ); ?></li>
				</ol>
				<p>
					<a class="button" href="<?php echo esc_url( $sample ); ?>"><?php esc_html_e( 'Download the sample file', 'crc-real-estate' ); ?></a>
				</p>

				<?php if ( ! current_user_can( 'upload_files' ) ) : ?>
					<p class="crc-warning"><?php esc_html_e( 'Importing needs permission to add photos to the Media Library, which your account doesn\'t have. Please ask the site\'s administrator.', 'crc-real-estate' ); ?></p>
				<?php else : ?>
					<div class="notice notice-warning inline crc-import-pending" hidden>
						<p class="crc-import-pending-text"></p>
						<p>
							<button type="button" class="button button-primary crc-import-resume"><?php esc_html_e( 'Continue the import', 'crc-real-estate' ); ?></button>
							<button type="button" class="button crc-import-stop"><?php esc_html_e( 'Stop the import', 'crc-real-estate' ); ?></button>
						</p>
					</div>

					<form class="crc-import-form" method="post" enctype="multipart/form-data">
						<p class="crc-import-file">
							<label for="crc-import-file"><?php esc_html_e( 'CSV file', 'crc-real-estate' ); ?></label>
							<input type="file" id="crc-import-file" name="file" accept=".csv,text/csv" aria-describedby="crc-import-file-help">
						</p>
						<p class="description" id="crc-import-file-help">
							<?php
							printf(
								/* translators: 1: largest file size, e.g. "10 MB", 2: most rows. */
								esc_html__( 'Up to %1$s and %2$s listings in one file. New listings are added as drafts unless their status column says publish. A listing is only published once it has a main photo and a category.', 'crc-real-estate' ),
								esc_html( size_format( self::max_size() ) ),
								esc_html( number_format_i18n( Importer::MAX_ROWS ) )
							);
							?>
						</p>
						<p>
							<button type="submit" class="button button-primary crc-import-start"><?php esc_html_e( 'Start import', 'crc-real-estate' ); ?></button>
						</p>
					</form>

					<div class="crc-import-run" hidden>
						<progress class="crc-import-bar" max="100" value="0" aria-labelledby="crc-import-status"></progress>
						<p class="crc-import-status" id="crc-import-status" role="status" aria-live="polite"></p>
						<p class="crc-import-counts"></p>
						<p class="crc-import-note" role="status" aria-live="polite"></p>
						<p class="crc-import-actions">
							<button type="button" class="button button-primary crc-import-resume" hidden><?php esc_html_e( 'Continue the import', 'crc-real-estate' ); ?></button>
							<button type="button" class="button crc-import-stop"><?php esc_html_e( 'Stop the import', 'crc-real-estate' ); ?></button>
						</p>
						<ol class="crc-import-log"></ol>
					</div>
				<?php endif; ?>
			</section>

			<section class="crc-info-card" id="crc-export">
				<h2 class="crc-info-title"><?php esc_html_e( 'Export listings', 'crc-real-estate' ); ?></h2>
				<p><?php esc_html_e( 'Download every listing (except those in the Trash) as a CSV file, in the same format as the sample file. You can open it in Excel or Google Sheets, change it, and import it back: rows keep their listing\'s ID, so importing them changes those listings instead of adding new ones.', 'crc-real-estate' ); ?></p>
				<p><?php esc_html_e( 'Photos are written as links to the photos on this site. The file includes the owners\' private details, so keep it somewhere safe.', 'crc-real-estate' ); ?></p>
				<p>
					<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Download all listings (CSV)', 'crc-real-estate' ); ?></a>
				</p>
				<h3 class="crc-info-subtitle"><?php esc_html_e( 'One listing, with its photos', 'crc-real-estate' ); ?></h3>
				<p><?php esc_html_e( 'To download everything about one listing in a zip file (a page with all its details, its photos in full size, its spreadsheet row and the inquiries about it), open the listing and press Download listing in the Publish box, or hover over the listing in All Listings and choose Download.', 'crc-real-estate' ); ?></p>
			</section>

			<section class="crc-info-card" id="crc-columns">
				<h2 class="crc-info-title"><?php esc_html_e( 'The columns', 'crc-real-estate' ); ?></h2>
				<p><?php esc_html_e( 'Column names can be in any order, and only the title is needed for a new listing. When a row changes a listing that is already on the site, empty cells leave that part of the listing as it is.', 'crc-real-estate' ); ?></p>
				<ul class="crc-import-rules">
					<li><?php esc_html_e( 'Lists: separate the items with |, for example Garden | Hot water | Balcony.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Groups: one line for each group in the same cell (Alt+Enter in Excel, Ctrl+Enter in Google Sheets), written as Group title: first | second.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Details in a group: Label = Value, for example Access and road: Bus route = 200 m | Bus stop = 50 m.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'If a title, label or value needs a | of its own (or a group title needs a :), put a \ in front of it, for example Bus routes = Galle \| Matara. Files downloaded from this page already do this.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Amounts: one amount in numbers, with or without commas and Rs., for example 12500000 or Rs. 12,500,000. Words such as million, or two amounts, aren\'t read.', 'crc-real-estate' ); ?></li>
					<li><?php esc_html_e( 'Choices: the word shown on the listing screen (in brackets in the table below) or the short name before it both work, for example Semi-furnished or semi_furnished.', 'crc-real-estate' ); ?></li>
				</ul>

				<?php $this->columns_table(); ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Prints every column with what to put in it, by section.
	 */
	private function columns_table() {
		$sections = array();

		foreach ( Listing_Data::columns( 2 ) as $name => $column ) {
			$sections[ $column['section'] ][ $name ] = $column['help'];
		}
		?>
		<table class="widefat striped crc-import-columns">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Column', 'crc-real-estate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What to put in it', 'crc-real-estate' ); ?></th>
				</tr>
			</thead>
			<?php foreach ( $sections as $section => $columns ) : ?>
				<tbody>
					<tr class="crc-import-section">
						<th scope="rowgroup" colspan="2"><?php echo esc_html( $section ); ?></th>
					</tr>
					<?php foreach ( $columns as $name => $help ) : ?>
						<tr>
							<td><code><?php echo esc_html( $name ); ?></code></td>
							<td><?php echo esc_html( $help ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Checks the request to the page's script: the page's security code and
	 * permission to import.
	 */
	private static function check_ajax() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Please reload it and try again.', 'crc-real-estate' ) ), 403 );
		}

		if ( ! self::can_import() ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, your account can\'t import listings.', 'crc-real-estate' ) ), 403 );
		}
	}

	/**
	 * Reads the chosen file and gets the import ready.
	 */
	public function ajax_start() {
		self::check_ajax();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A file upload; its path and name are checked below.
		$file = isset( $_FILES['file'] ) && is_array( $_FILES['file'] ) ? $_FILES['file'] : null;

		if ( ! $file || ! isset( $file['tmp_name'], $file['name'], $file['size'], $file['error'] ) || UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'Please choose the CSV file first.', 'crc-real-estate' ) ) );
		}

		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$big = in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );

			wp_send_json_error(
				array(
					'message' => $big
						/* translators: %s: largest file size, e.g. "10 MB". */
						? sprintf( __( 'The file is too big. It can be up to %s; split it into smaller files.', 'crc-real-estate' ), size_format( self::max_size() ) )
						: __( 'The file couldn\'t be uploaded. Please try again.', 'crc-real-estate' ),
				)
			);
		}

		if ( (int) $file['size'] > self::max_size() ) {
			/* translators: %s: largest file size, e.g. "10 MB". */
			wp_send_json_error( array( 'message' => sprintf( __( 'The file is too big. It can be up to %s; split it into smaller files.', 'crc-real-estate' ), size_format( self::max_size() ) ) ) );
		}

		$type = wp_check_filetype(
			sanitize_file_name( wp_unslash( $file['name'] ) ),
			array(
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			)
		);

		if ( ! $type['ext'] ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a CSV file (it ends in .csv). In Excel, use File → Save As → CSV UTF-8.', 'crc-real-estate' ) ) );
		}

		// The file is read where PHP put it and never saved in the uploads folder, which anyone can open.
		$result = Importer::start( $file['tmp_name'], get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();

			// An unfinished import: the page shows it again, with Continue and Stop.
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'pending' => is_array( $data ) && isset( $data['pending'] ) ? $data['pending'] : null,
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * The import's ID the page sends: letters and numbers only.
	 *
	 * @return string
	 */
	private static function posted_run() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check_ajax().
		return isset( $_POST['run'] ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['run'] ) ) : '';
	}

	/**
	 * Does the next part of the import.
	 */
	public function ajax_step() {
		self::check_ajax();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check_ajax().
		$seen   = isset( $_POST['seen'] ) ? absint( $_POST['seen'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check_ajax().
		$fresh  = ! empty( $_POST['fresh'] );
		$result = Importer::step( get_current_user_id(), self::posted_run(), $seen, $fresh );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Stops the import.
	 */
	public function ajax_stop() {
		self::check_ajax();

		$result = Importer::stop( get_current_user_id(), self::posted_run() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Downloads the sample file.
	 */
	public function download_sample() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, your account can\'t import listings.', 'crc-real-estate' ), 403 );
		}

		check_admin_referer( self::SAMPLE );

		self::send_csv( 'listings-sample.csv', Csv::write( array_keys( Listing_Data::columns( 2 ) ), self::sample_rows() ) );
	}

	/**
	 * Downloads every listing. The file is sent as it is written, a few
	 * listings at a time, so a large site doesn't run out of memory.
	 */
	public function download_export() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, your account can\'t export listings.', 'crc-real-estate' ), 403 );
		}

		check_admin_referer( self::EXPORT );

		wp_raise_memory_limit( 'admin' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not every host allows it.
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="listings-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a download.
		self::write_export( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming a download.
		exit;
	}

	/**
	 * Writes every listing (except those in the Trash) as a CSV file.
	 *
	 * @param resource $out Where to write.
	 */
	public static function write_export( $out ) {
		$ids    = get_posts(
			array(
				'post_type'        => Post_Type::NAME,
				'post_status'      => array( 'publish', 'future', 'pending', 'draft', 'private' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		$chunks = array_chunk( array_map( 'intval', $ids ), 100 );
		$faqs   = 2;

		// First, how many question columns the file needs.
		foreach ( $chunks as $chunk ) {
			update_meta_cache( 'post', $chunk );

			foreach ( $chunk as $id ) {
				$faqs = max( $faqs, count( Faq::own_items( $id ) ) );
			}

			self::free_memory();
		}

		$columns = array_keys( Listing_Data::columns( $faqs ) );

		fwrite( $out, Csv::BOM . Csv::line( $columns, false ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download.

		foreach ( $chunks as $chunk ) {
			_prime_post_caches( $chunk, true, true );

			$photos = array();

			foreach ( $chunk as $id ) {
				$photos[] = (int) get_post_thumbnail_id( $id );
				$photos   = array_merge( $photos, Gallery::gallery_ids( $id ) );
			}

			$photos = array_filter( array_unique( $photos ) );

			if ( $photos ) {
				_prime_post_caches( $photos, false, true );
			}

			foreach ( $chunk as $id ) {
				fwrite( $out, Csv::row( $columns, Listing_Data::row( $id ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download.
			}

			self::free_memory();
		}
	}

	/**
	 * Lets go of the listings and photos WordPress keeps in memory while a
	 * request runs, between groups of listings.
	 */
	private static function free_memory() {
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_runtime' ) ) {
			wp_cache_flush_runtime();
		} elseif ( ! wp_using_ext_object_cache() ) {
			wp_cache_flush();
		}
	}

	/**
	 * Sends a CSV file to the browser as a download.
	 *
	 * @param string $name File name.
	 * @param string $csv  File contents.
	 */
	private static function send_csv( $name, $csv ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		header( 'X-Content-Type-Options: nosniff' );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A file download, escaped for spreadsheets by Csv::write().
		exit;
	}

	/**
	 * Three example listings for the sample file: land, a house for sale and
	 * an apartment for rent.
	 *
	 * @return array[]
	 */
	public static function sample_rows() {
		$photo = 'https://example.com/photos/';

		return array(
			array(
				'title'              => 'Bare land for sale in Galle',
				'status'             => 'draft',
				'category'           => 'lands',
				'district'           => 'Galle',
				'description'        => "20 perches of flat, flood-free bare land 3 km from Galle town, on a carpeted road.\n\nIdeal for a house. Clear deed and approved survey plan.",
				'main_photo'         => $photo . 'galle-land-1.jpg',
				'more_photos'        => $photo . 'galle-land-2.jpg | ' . $photo . 'galle-land-3.jpg',
				'price'              => '12500000',
				'price_per_perch'    => '625000',
				'property_type'      => 'Bare Land',
				'offered_for'        => 'Sale',
				'availability'       => 'Available Now',
				'listed_by'          => 'Owner',
				'land_extent'        => '20',
				'land_extent_unit'   => 'perches',
				'extent_perches'     => '20',
				'price_basis'        => 'Per perch',
				'price_type'         => 'Negotiable',
				'road_frontage'      => '40',
				'road_width'         => '20',
				'road_type'          => 'Carpeted',
				'main_road_distance' => '300',
				'main_road_distance_unit' => 'm',
				'access'             => 'Direct road access',
				'facing'             => 'South',
				'electricity'        => 'Available',
				'water_supply'       => 'Pipe-borne',
				'more_details'       => 'Access and road: Bus route = 200 m',
				'own_detail_groups'  => "Nearby places: Galle Fort = 6 km | Karapitiya Hospital = 3 km\nDocuments: Deed number = 1234",
				'features'           => 'Clear deed | Approved survey plan | Flat land | Flood-free area | Ideal for a house | Close to town',
				'latitude'           => '6.0535',
				'longitude'          => '80.2210',
				'show_map'           => 'yes',
				'google_maps_link'   => 'https://maps.google.com/?q=6.0535,80.2210',
				'show_category_faqs' => 'yes',
				'faq_1_question'     => 'Can the land be divided into smaller plots?',
				'faq_1_answer'       => 'Yes. The approved survey plan allows two plots of 10 perches each.',
				'faq_2_question'     => 'Is there a bus route nearby?',
				'faq_2_answer'       => 'Yes, the Galle–Matara bus route is about 200 metres away.',
				'faq_2_link_text'    => 'Ask about this land',
				'faq_2_link'         => '#crc-inquiry-1',
				'owner_first_name'   => 'Nimal',
				'owner_last_name'    => 'Perera',
				'owner_phone'        => '+94 77 123 4567',
				'owner_email'        => 'nimal.perera@example.com',
				'owner_address'      => 'No. 12, Temple Road, Galle',
				'owner_notes'        => 'Prefers calls after 5 pm. Keys are with the neighbour.',
			),
			array(
				'title'              => 'Two-storey house for sale in Kandy',
				'status'             => 'draft',
				'category'           => 'properties-for-sale',
				'district'           => 'Kandy',
				'description'        => "A four-bedroom, two-storey house on 15 perches in a quiet neighbourhood, 10 minutes from Kandy town.\n\nSemi-furnished, with a garden, hot water and parking for two cars.",
				'main_photo'         => $photo . 'kandy-house-1.jpg',
				'more_photos'        => $photo . 'kandy-house-2.jpg | ' . $photo . 'kandy-house-3.jpg | ' . $photo . 'kandy-house-4.jpg',
				'price'              => 'Rs. 45,000,000',
				'phone'              => '+94 81 222 3344',
				'property_type'      => 'House',
				'offered_for'        => 'Sale',
				'availability'       => 'Available Now',
				'listed_by'          => 'Agent',
				'bedrooms'           => '4',
				'bathrooms'          => '3',
				'ensuite_bathrooms'  => '2',
				'floor_area'         => '2400',
				'floor_area_unit'    => 'sq_ft',
				'land_extent'        => '15',
				'land_extent_unit'   => 'perches',
				'storeys'            => '2',
				'parking'            => '2',
				'furnishing'         => 'Semi-furnished',
				'price_type'         => 'Negotiable',
				'bank_loan'          => 'Available',
				'road_type'          => 'Tarred',
				'electricity'        => 'Available',
				'water_supply'       => 'Pipe-borne and well',
				'features'           => 'Clear deed | Garden | Hot water | Modern kitchen | CCTV cameras | Close to schools | Quiet neighbourhood',
				'more_features'      => 'Home features: Pantry cupboards | Roof terrace',
				'latitude'           => '7.2906, 80.6337',
				'show_map'           => 'yes',
				'show_category_faqs' => 'yes',
				'faq_1_question'     => 'Is a bank loan possible for this house?',
				'faq_1_answer'       => 'Yes. The deed is clear, so most banks can offer a housing loan.',
				'owner_first_name'   => 'Kumari',
				'owner_last_name'    => 'Jayasinghe',
				'owner_phone'        => '+94 71 234 5678',
				'owner_email'        => 'kumari.j@example.com',
				'owner_address'      => 'No. 45, Peradeniya Road, Kandy',
				'owner_notes'        => 'Lowest price agreed: Rs. 42,000,000.',
			),
			array(
				'title'              => 'Furnished apartment for rent in Colombo 5',
				'status'             => 'draft',
				'category'           => 'properties-for-rent',
				'district'           => 'Colombo',
				'description'        => "A fully furnished three-bedroom apartment on the 8th floor, with a pool, a gym and 24-hour security.\n\nClose to schools, hospitals and supermarkets.",
				'main_photo'         => $photo . 'colombo-apartment-1.jpg',
				'more_photos'        => $photo . 'colombo-apartment-2.jpg',
				'price'              => '250000',
				'property_type'      => 'Apartment',
				'offered_for'        => 'Rent',
				'availability'       => 'Available Soon',
				'listed_by'          => 'Owner',
				'bedrooms'           => '3',
				'bathrooms'          => '2',
				'floor_area'         => '1450',
				'floor_area_unit'    => 'sq ft',
				'parking'            => '1',
				'furnishing'         => 'furnished',
				'advance_payment'    => '6',
				'minimum_lease'      => '1',
				'minimum_lease_unit' => 'years',
				'utility_bills'      => 'Paid separately',
				'maintenance_fee'    => '15000',
				'features'           => 'Air conditioning | Swimming pool | Security guard | Suitable for families | Close to supermarkets',
				'own_feature_groups' => 'Building: Gym | Rooftop garden | Backup generator',
				'latitude'           => '6.8894',
				'longitude'          => '79.8636',
				'show_map'           => 'no',
				'show_category_faqs' => 'no',
				'owner_first_name'   => 'Ruwan',
				'owner_last_name'    => 'Fernando',
				'owner_phone'        => '+94 76 345 6789',
				'owner_email'        => 'ruwan.fernando@example.com',
				'owner_address'      => 'Apartment 8B, Havelock Road, Colombo 5',
			),
		);
	}
}
