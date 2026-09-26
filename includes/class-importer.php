<?php
/**
 * Listings import.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

use CRC\RealEstate\Sections\Gallery;

defined( 'ABSPATH' ) || exit;

/**
 * Imports listings from a CSV file a little at a time, so a shared host
 * never runs out of time: each step adds listings and downloads their
 * photos for a few seconds, and the page asks for the next step until
 * every row is done.
 *
 * The rows are kept once, in an option for the person importing, and the
 * rows with problems in another. A small record of how far the import got
 * is saved after every piece of work, so a step cut short by the server
 * carries on where it stopped, and only one step runs at a time. The rows
 * are removed when the import ends, is stopped, or is left for a day; a
 * summary of what it did is kept for an hour after it ends.
 */
final class Importer {

	const JOB_OPTION   = 'crc_re_import_job_';
	const ROWS_OPTION  = 'crc_re_import_rows_';
	const LOG_OPTION   = 'crc_re_import_log_';
	const DONE_OPTION  = 'crc_re_import_done_';
	const LOCK_OPTION  = 'crc_re_import_lock_';
	const STOP_OPTION  = 'crc_re_import_stop_';
	const EXPIRE_HOOK  = 'crc_re_import_expire';
	const ROW_META     = '_crc_import_row';
	const SOURCE_META  = '_crc_source_url';
	const PENDING_META = '_crc_import_unfinished';
	const MAX_ROWS     = 1000;
	const MAX_SIZE     = 10485760;
	const BUDGET       = 5;
	const TIMEOUT      = 15;
	const LOCK_TIME    = 150;
	const KEEP_DONE    = 3600;

	/**
	 * Picture types a photo can be, with their file extension and name.
	 */
	const PHOTO_TYPES = array(
		'image/jpeg' => array( 'jpg', 'JPG' ),
		'image/png'  => array( 'png', 'PNG' ),
		'image/gif'  => array( 'gif', 'GIF' ),
		'image/webp' => array( 'webp', 'WebP' ),
		'image/avif' => array( 'avif', 'AVIF' ),
	);

	/**
	 * Locks this request holds, by person.
	 *
	 * @var string[]
	 */
	private static $locks = array();

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( self::EXPIRE_HOOK, array( __CLASS__, 'expire' ) );
		add_action( 'deleted_user', array( __CLASS__, 'forget' ) );
	}

	/**
	 * Reads a CSV file and gets its rows ready to import.
	 *
	 * @param string $path    Uploaded file.
	 * @param int    $user_id Person importing.
	 * @return array|\WP_Error 'total' rows, 'unknown' column names that will be left out, and 'run', the import's ID.
	 */
	public static function start( $path, $user_id ) {
		$csv = Csv::read( $path );

		if ( is_wp_error( $csv ) ) {
			return $csv;
		}

		if ( ! in_array( 'title', $csv['columns'], true ) && ! in_array( 'id', $csv['columns'], true ) ) {
			return new \WP_Error( 'no_title', __( 'The file needs a title column, or an id column to change listings that are already on the site. Download the sample file to see the columns.', 'crc-real-estate' ) );
		}

		if ( ! $csv['rows'] ) {
			return new \WP_Error( 'no_rows', __( 'The file has column names but no listings under them.', 'crc-real-estate' ) );
		}

		if ( count( $csv['rows'] ) > self::MAX_ROWS ) {
			/* translators: %s: most rows in one file. */
			return new \WP_Error( 'too_many', sprintf( __( 'The file has more than %s listings. Please split it into smaller files and import them one after the other.', 'crc-real-estate' ), number_format_i18n( self::MAX_ROWS ) ) );
		}

		$known   = array_keys( Listing_Data::columns( 1 ) );
		$unknown = array_values(
			array_filter(
				$csv['columns'],
				function ( $name ) use ( $known ) {
					return ! in_array( $name, $known, true ) && ! preg_match( '/^faq_\d+_(question|answer|link_text|link)$/', $name );
				}
			)
		);

		// Empty cells leave a listing as it is, just as missing columns do, so they aren't kept.
		$rows = array();

		foreach ( $csv['rows'] as $row ) {
			$rows[] = array(
				'line'  => (int) $row['line'],
				'cells' => array_filter(
					$row['cells'],
					function ( $value ) {
						return '' !== trim( (string) $value );
					}
				),
			);
		}

		if ( ! self::lock( $user_id ) ) {
			return new \WP_Error( 'busy', __( 'An import is still working in another window. Please wait for it to finish, or stop it there, and then try again.', 'crc-real-estate' ) );
		}

		// An unfinished import isn't replaced: it is continued or stopped first.
		$job = get_option( self::JOB_OPTION . (int) $user_id );

		if ( self::valid( $job ) && time() - (int) $job['time'] <= DAY_IN_SECONDS ) {
			self::unlock( $user_id );

			return new \WP_Error(
				'unfinished',
				sprintf(
					/* translators: 1: rows done, 2: all rows. */
					__( 'An import you started earlier isn\'t finished yet (%1$s of %2$s listings done). Please press Continue the import to finish it, or Stop the import, and then start the new file. Starting again without stopping could add the same listings twice.', 'crc-real-estate' ),
					number_format_i18n( $job['at'] ),
					number_format_i18n( $job['total'] )
				),
				array( 'pending' => self::pending_info( $job ) )
			);
		}

		self::clear( $user_id );

		if ( ! add_option( self::ROWS_OPTION . (int) $user_id, $rows, '', 'no' ) ) {
			self::clear( $user_id );
			self::unlock( $user_id );

			return new \WP_Error( 'too_big', __( 'The file is too large for this server to import at once. Please split it into smaller files and import them one after the other.', 'crc-real-estate' ) );
		}

		$run = wp_generate_password( 12, false );

		update_option(
			self::JOB_OPTION . (int) $user_id,
			array(
				'run'     => $run,
				'total'   => count( $rows ),
				'at'      => 0,
				'current' => null,
				'counts'  => self::no_counts(),
				'logged'  => 0,
				'time'    => time(),
			),
			false
		);

		self::schedule_expiry( $user_id, DAY_IN_SECONDS + HOUR_IN_SECONDS );
		self::unlock( $user_id );

		return array(
			'total'   => count( $rows ),
			'unknown' => $unknown,
			'run'     => $run,
		);
	}

	/**
	 * Does the next pieces of work, for about BUDGET seconds.
	 *
	 * @param int    $user_id Person importing.
	 * @param string $run     The import's ID, from start().
	 * @param int    $seen    How many rows with problems the page has shown.
	 * @param bool   $fresh   Whether the person has just pressed Continue: a stop asked for earlier is then forgotten.
	 * @return array|\WP_Error 'busy' when another step is working; otherwise 'done' and 'total' rows, 'reports' on the rows finished in this step, 'log' (rows with problems the page hasn't shown), 'seen', 'counts' so far, 'finished' and 'stopped'.
	 */
	public static function step( $user_id, $run, $seen = 0, $fresh = false ) {
		if ( ! self::lock( $user_id ) ) {
			return array( 'busy' => true );
		}

		if ( $fresh ) {
			self::clear_stop( $user_id );
		}

		$result = self::run_step( (int) $user_id, (string) $run, max( 0, (int) $seen ) );

		self::unlock( $user_id );

		return $result;
	}

	/**
	 * The work of step(), while it holds the lock.
	 *
	 * @param int    $user_id Person importing.
	 * @param string $run     The import's ID.
	 * @param int    $seen    Rows with problems already shown.
	 * @return array|\WP_Error
	 */
	private static function run_step( $user_id, $run, $seen ) {
		$option = self::JOB_OPTION . $user_id;
		$job    = get_option( $option );

		if ( ! self::valid( $job ) ) {
			$done = self::summary( $user_id );

			// Finished already, e.g. when the answer to the last step didn't arrive.
			if ( $done && $run === $done['run'] ) {
				return self::answer( $done['done'], $done['total'], $done['counts'], array(), $done['log'], $seen, true, ! empty( $done['stopped'] ) );
			}

			return new \WP_Error( 'no_import', __( 'This import has already finished or was stopped. Please check All Listings before importing the same file again, or its listings will be added twice.', 'crc-real-estate' ) );
		}

		if ( $run !== $job['run'] ) {
			return new \WP_Error( 'replaced', __( 'This import was replaced by a newer one, started in another window. That window shows how it is going.', 'crc-real-estate' ) );
		}

		$rows = get_option( self::ROWS_OPTION . $user_id );

		if ( ! is_array( $rows ) ) {
			self::clear( $user_id );

			return new \WP_Error( 'no_rows', __( 'The rows of this import are no longer on the site. Please choose the file again, but check All Listings first, as some listings may be there already.', 'crc-real-estate' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not every host allows it.
		}

		$start   = microtime( true );
		$reports = array();

		while ( $job['at'] < $job['total'] && microtime( true ) - $start < self::BUDGET ) {
			// Stop pressed in another window while this one works.
			if ( self::stop_asked( $user_id, $job['run'] ) ) {
				$report = self::halt( $user_id, $job );

				return self::answer( $job['at'], $job['total'], $job['counts'], $report ? array_merge( $reports, array( $report ) ) : $reports, self::log( $user_id, true ), $seen, true, true );
			}

			$report = self::work( $job, $rows, $user_id );

			if ( $report ) {
				$reports[] = $report;
				self::count( $user_id, $job, $report );
			}

			$job['time'] = time();
			update_option( $option, $job, false );
		}

		$log      = self::log( $user_id );
		$finished = $job['at'] >= $job['total'];

		if ( $finished ) {
			self::keep_summary( $user_id, $job, false );
			self::clear_job( $user_id );
			self::schedule_expiry( $user_id, self::KEEP_DONE + MINUTE_IN_SECONDS );
		}

		return self::answer( $job['at'], $job['total'], $job['counts'], $reports, $log, $seen, $finished, false );
	}

	/**
	 * What a step tells the page.
	 *
	 * @param int     $done     Rows done.
	 * @param int     $total    All rows.
	 * @param int[]   $counts   Counts.
	 * @param array[] $reports  Rows finished in this step.
	 * @param array[] $log      Every row with problems.
	 * @param int     $seen     Rows with problems the page has shown.
	 * @param bool    $finished Whether the import ended.
	 * @param bool    $stopped  Whether it was stopped.
	 * @return array
	 */
	private static function answer( $done, $total, $counts, array $reports, array $log, $seen, $finished, $stopped ) {
		return array(
			'done'     => (int) $done,
			'total'    => (int) $total,
			'reports'  => $reports,
			'log'      => array_values( array_slice( $log, $seen ) ),
			'seen'     => count( $log ),
			'counts'   => $counts,
			'finished' => (bool) $finished,
			'stopped'  => (bool) $stopped,
		);
	}

	/**
	 * An import the person started and didn't finish.
	 *
	 * @param int $user_id Person importing.
	 * @return array|null 'done', 'total', 'counts' and 'run', or null when there is none.
	 */
	public static function pending( $user_id ) {
		$job = get_option( self::JOB_OPTION . (int) $user_id );

		if ( ! self::valid( $job ) ) {
			return null;
		}

		// Left for a day: the rows are removed, with the owner details in them.
		if ( time() - (int) $job['time'] > DAY_IN_SECONDS ) {
			self::forget( $user_id );

			return null;
		}

		return self::pending_info( $job );
	}

	/**
	 * How far an unfinished import got.
	 *
	 * @param array $job The import.
	 * @return array 'done', 'total', 'counts' and 'run'.
	 */
	private static function pending_info( array $job ) {
		return array(
			'done'   => (int) $job['at'],
			'total'  => (int) $job['total'],
			'counts' => $job['counts'],
			'run'    => (string) $job['run'],
		);
	}

	/**
	 * What the person's last import did, if it ended less than an hour ago.
	 *
	 * @param int $user_id Person importing.
	 * @return array|null 'done', 'total', 'counts', 'log' (rows with problems), 'stopped' and 'run'.
	 */
	public static function last( $user_id ) {
		return self::summary( $user_id );
	}

	/**
	 * Stops an import. Listings already added stay.
	 *
	 * @param int    $user_id Person importing.
	 * @param string $run     The import's ID; '' stops whichever import there is.
	 * @return array|\WP_Error 'busy' while a step is working (it is asked to stop); otherwise 'report' on a listing the import was in the middle of, or null, with 'done', 'total', 'counts', 'finished' and 'stopped'.
	 */
	public static function stop( $user_id, $run = '' ) {
		$run = (string) $run;

		if ( ! self::lock( $user_id ) ) {
			// A step is working: it is asked to stop at its next piece of work.
			$job = get_option( self::JOB_OPTION . (int) $user_id );

			if ( self::valid( $job ) && ( '' === $run || $run === $job['run'] ) ) {
				self::ask_stop( $user_id, $job['run'] );
			}

			return array( 'busy' => true );
		}

		$job    = get_option( self::JOB_OPTION . (int) $user_id );
		$result = array(
			'report'   => null,
			'finished' => true,
			'stopped'  => true,
		);

		if ( self::valid( $job ) && '' !== $run && $run !== $job['run'] ) {
			self::unlock( $user_id );

			return new \WP_Error( 'replaced', __( 'This import was replaced by a newer one, started in another window. Stop it there if you need to.', 'crc-real-estate' ) );
		}

		if ( self::valid( $job ) ) {
			$result['report'] = self::halt( $user_id, $job );
			$result['done']   = (int) $job['at'];
			$result['total']  = (int) $job['total'];
			$result['counts'] = $job['counts'];
			$result['log']    = self::log( $user_id, true );
		} else {
			$done = self::summary( $user_id );

			// It had already ended, e.g. with the step that was working when Stop was pressed.
			if ( $done && ( '' === $run || $run === $done['run'] ) ) {
				$result['stopped'] = ! empty( $done['stopped'] );
				$result['done']    = (int) $done['done'];
				$result['total']   = (int) $done['total'];
				$result['counts']  = $done['counts'];
				$result['log']     = array_values( (array) $done['log'] );
			}
		}

		self::clear_stop( $user_id );
		self::unlock( $user_id );

		return $result;
	}

	/**
	 * Ends an import where it is: the listing it was in the middle of gets
	 * the photos downloaded so far and is reported, and a summary is kept.
	 *
	 * @param int   $user_id Person importing.
	 * @param array $job     The import, changed in place.
	 * @return array|null Report on the listing it was in the middle of.
	 */
	private static function halt( $user_id, array &$job ) {
		$report = null;

		if ( is_array( $job['current'] ) && ! empty( $job['current']['report']['id'] ) ) {
			$current = $job['current'];
			$id      = (int) $current['report']['id'];

			self::place_photos( $current );

			$report           = $current['report'];
			$report['status'] = (string) get_post_status( $id );
			$report['link']   = (string) get_edit_post_link( $id, 'raw' );

			if ( null === $current['photos'] ) {
				$report['warnings'][] = __( 'The import was stopped before this row\'s details (price, category, owner and the rest) and photos were filled in.', 'crc-real-estate' );
			} elseif ( $current['photos'] ) {
				$report['warnings'][] = __( 'The import was stopped before all its photos were in; the ones already downloaded are on it.', 'crc-real-estate' );
			} else {
				$report['warnings'][] = __( 'The import was stopped just before this row was finished; its details and photos are in.', 'crc-real-estate' );
			}

			if ( 'created' === $report['action'] ) {
				$report['warnings'][] = __( 'It stays a draft: open it to finish it, or delete it.', 'crc-real-estate' );
			} elseif ( 'draft' === $report['status'] && in_array( $current['status'], array( 'publish', 'pending', 'future' ), true ) ) {
				$report['warnings'][] = __( 'It is a draft for now: add what it needs on the listing screen, then publish it again.', 'crc-real-estate' );
			}

			delete_post_meta( $id, self::ROW_META );
			self::count( $user_id, $job, $report );
		}

		self::keep_summary( $user_id, $job, true );
		self::clear_job( $user_id );
		self::clear_stop( $user_id );
		self::schedule_expiry( $user_id, self::KEEP_DONE + MINUTE_IN_SECONDS );

		return $report;
	}

	/**
	 * Removes everything an import keeps for a person: when they are
	 * deleted, when it was left for a day, or when the plugin is turned off.
	 *
	 * @param int $user_id Person importing.
	 */
	public static function forget( $user_id ) {
		self::clear( $user_id );
		self::clear_stop( $user_id );
		self::unlock( $user_id, true );
	}

	/**
	 * Removes an import that was left for a day, so the rows (with the
	 * owners' details in them) don't stay on the site, and a summary more
	 * than an hour old. What is still in use is checked again later.
	 *
	 * @param int $user_id Person importing.
	 */
	public static function expire( $user_id ) {
		$done = self::summary( $user_id );

		if ( null !== self::pending( $user_id ) ) {
			$job = get_option( self::JOB_OPTION . (int) $user_id );
			self::schedule_expiry( $user_id, max( MINUTE_IN_SECONDS, DAY_IN_SECONDS - ( time() - (int) $job['time'] ) + MINUTE_IN_SECONDS ) );
		} elseif ( $done ) {
			self::schedule_expiry( $user_id, max( MINUTE_IN_SECONDS, self::KEEP_DONE - ( time() - (int) $done['time'] ) + MINUTE_IN_SECONDS ) );
		}
	}

	/**
	 * Removes every import, for example when the plugin is turned off.
	 */
	public static function stop_all() {
		global $wpdb;

		foreach ( array( self::JOB_OPTION, self::ROWS_OPTION, self::LOG_OPTION, self::DONE_OPTION, self::LOCK_OPTION, self::STOP_OPTION ) as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Options with a user ID in their name, removed once.
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );

			foreach ( (array) $names as $name ) {
				delete_option( $name );
			}
		}

		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( self::EXPIRE_HOOK );
		}
	}

	/**
	 * Removes a person's import, and the summary of the last one.
	 *
	 * @param int $user_id Person importing.
	 */
	private static function clear( $user_id ) {
		self::clear_job( $user_id );
		delete_option( self::DONE_OPTION . (int) $user_id );
		wp_clear_scheduled_hook( self::EXPIRE_HOOK, array( (int) $user_id ) );
	}

	/**
	 * Removes an import's rows, its rows with problems, and how far it got.
	 *
	 * @param int $user_id Person importing.
	 */
	private static function clear_job( $user_id ) {
		$job = get_option( self::JOB_OPTION . (int) $user_id );

		if ( self::valid( $job ) && is_array( $job['current'] ) && ! empty( $job['current']['trying']['url'] ) ) {
			self::drop_unfinished( $job['current']['trying']['url'], $job['current']['id'] );
		}

		delete_option( self::JOB_OPTION . (int) $user_id );
		delete_option( self::ROWS_OPTION . (int) $user_id );
		delete_option( self::LOG_OPTION . (int) $user_id );
	}

	/**
	 * Keeps what an import did for an hour after it ends, without its rows,
	 * so a page that missed the last answer, or is opened again, can show it.
	 *
	 * @param int   $user_id Person importing.
	 * @param array $job     The import.
	 * @param bool  $stopped Whether it was stopped.
	 */
	private static function keep_summary( $user_id, array $job, $stopped ) {
		update_option(
			self::DONE_OPTION . (int) $user_id,
			array(
				'run'     => $job['run'],
				'done'    => $job['at'],
				'total'   => $job['total'],
				'counts'  => $job['counts'],
				'log'     => self::log( $user_id ),
				'stopped' => $stopped,
				'time'    => time(),
			),
			false
		);
	}

	/**
	 * What an import did, if it ended less than an hour ago. An older one is removed.
	 *
	 * @param int $user_id Person importing.
	 * @return array|null
	 */
	private static function summary( $user_id ) {
		$done = get_option( self::DONE_OPTION . (int) $user_id );

		if ( ! is_array( $done ) || ! isset( $done['run'], $done['time'], $done['counts'], $done['log'] ) ) {
			return null;
		}

		if ( time() - (int) $done['time'] > self::KEEP_DONE ) {
			delete_option( self::DONE_OPTION . (int) $user_id );

			return null;
		}

		return $done;
	}

	/**
	 * Checks the person's import again after a while: removes it once it has
	 * been left for a day, and a summary once it is an hour old.
	 *
	 * @param int $user_id Person importing.
	 * @param int $after   Seconds from now.
	 */
	private static function schedule_expiry( $user_id, $after ) {
		$args = array( (int) $user_id );

		wp_clear_scheduled_hook( self::EXPIRE_HOOK, $args );
		wp_schedule_single_event( time() + (int) $after, self::EXPIRE_HOOK, $args );
	}

	/**
	 * The rows with problems so far.
	 *
	 * @param int  $user_id Person importing.
	 * @param bool $done    Whether to read them from the summary of an import that just ended.
	 * @return array[]
	 */
	private static function log( $user_id, $done = false ) {
		if ( $done ) {
			$summary = get_option( self::DONE_OPTION . (int) $user_id );

			return is_array( $summary ) && isset( $summary['log'] ) ? (array) $summary['log'] : array();
		}

		$log = get_option( self::LOG_OPTION . (int) $user_id );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Asks the step that is working on a person's import to stop. Written
	 * straight to the database, so the working request sees it at once.
	 *
	 * @param int    $user_id Person importing.
	 * @param string $run     The import's ID.
	 */
	private static function ask_stop( $user_id, $run ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read by another request, so it must bypass the options cache.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", self::STOP_OPTION . (int) $user_id, $run ) );
	}

	/**
	 * Whether Stop was pressed for this import in another window.
	 *
	 * @param int    $user_id Person importing.
	 * @param string $run     The import's ID.
	 * @return bool
	 */
	private static function stop_asked( $user_id, $run ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Written by another request, so it must bypass the options cache.
		return (string) $run === (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STOP_OPTION . (int) $user_id ) );
	}

	/**
	 * Forgets a request to stop.
	 *
	 * @param int $user_id Person importing.
	 */
	private static function clear_stop( $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Written straight to the database, so removed the same way.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::STOP_OPTION . (int) $user_id ) );
	}

	/**
	 * Makes sure only one request works on a person's import at a time.
	 * The lock is a row of its own, added in one go, so two requests can't
	 * both get it. A lock left by a request that died is taken over after
	 * LOCK_TIME seconds.
	 *
	 * @param int $user_id Person importing.
	 * @return bool Whether this request has the lock.
	 */
	private static function lock( $user_id ) {
		global $wpdb;

		$name  = self::LOCK_OPTION . (int) $user_id;
		$token = time() . '|' . wp_generate_password( 12, false );

		for ( $try = 0; $try < 2; $try++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock must bypass the options cache.
			if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $token ) ) ) {
				self::$locks[ (int) $user_id ] = $token;

				// Let go of it even when the request ends with an error.
				register_shutdown_function( array( __CLASS__, 'unlock' ), (int) $user_id );

				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock must bypass the options cache.
			$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

			if ( null !== $held ) {
				if ( time() - (int) $held < self::LOCK_TIME ) {
					return false;
				}

				// Left by a request that died: removed once, by whichever request gets here first.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock must bypass the options cache.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $held ) );
			}
		}

		return false;
	}

	/**
	 * Lets go of the lock this request holds.
	 *
	 * @param int  $user_id Person importing.
	 * @param bool $any     Whether to remove the lock whoever holds it.
	 */
	public static function unlock( $user_id, $any = false ) {
		global $wpdb;

		$name = self::LOCK_OPTION . (int) $user_id;

		if ( $any ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock must bypass the options cache.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		} elseif ( isset( self::$locks[ (int) $user_id ] ) ) {
			// Only this request's own lock, never one another request took since.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock must bypass the options cache.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, self::$locks[ (int) $user_id ] ) );
		}

		unset( self::$locks[ (int) $user_id ] );
	}

	/**
	 * Counts that start at nothing.
	 *
	 * @return int[]
	 */
	private static function no_counts() {
		return array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
		);
	}

	/**
	 * Counts a finished row, and keeps its report when it had problems, so
	 * the page can show it even if the answer to its step got lost. The rows
	 * with problems have an option of their own, written once for each.
	 *
	 * @param int   $user_id Person importing.
	 * @param array $job     Import, changed in place.
	 * @param array $report  The row's report.
	 */
	private static function count( $user_id, array &$job, array $report ) {
		$job['counts'][ $report['action'] ]++;

		if ( 'failed' === $report['action'] || $report['warnings'] ) {
			$log   = self::log( $user_id );
			$log[] = $report;

			update_option( self::LOG_OPTION . (int) $user_id, $log, false );
			$job['logged'] = count( $log );
		}
	}

	/**
	 * Whether a saved import is complete enough to carry on.
	 *
	 * @param mixed $job Saved import.
	 * @return bool
	 */
	private static function valid( $job ) {
		return is_array( $job ) && isset( $job['run'], $job['total'], $job['at'], $job['counts'], $job['time'] ) && array_key_exists( 'current', $job );
	}

	/**
	 * One piece of work on the current row: add or find its listing, fill in
	 * its details, download one of its photos, or finish it.
	 *
	 * @param array $job     Import, changed in place.
	 * @param array $rows    The import's rows.
	 * @param int   $user_id Person importing.
	 * @return array|null The row's report once it's finished.
	 */
	private static function work( array &$job, array $rows, $user_id ) {
		$index = (int) $job['at'];
		$row   = isset( $rows[ $index ] ) ? $rows[ $index ] : array(
			'line'  => 0,
			'cells' => array(),
		);

		if ( null === $job['current'] ) {
			$current = self::begin( $row, $job['run'] . ':' . $index );

			if ( isset( $current['failed'] ) ) {
				self::next_row( $job );

				return $current['failed'];
			}

			$job['current'] = $current;

			return null;
		}

		$current = &$job['current'];

		if ( null === $current['photos'] ) {
			self::fill( $current, $row['cells'] );

			return null;
		}

		if ( $current['photos'] ) {
			$photo = $current['photos'][0];

			// The server stopped twice while this photo was being added: it isn't tried again.
			$tries = isset( $current['trying']['url'] ) && $current['trying']['url'] === $photo['url'] ? (int) $current['trying']['tries'] : 0;

			if ( $tries >= 2 ) {
				array_shift( $current['photos'] );
				unset( $current['trying'] );
				self::drop_unfinished( $photo['url'], $current['id'] );
				/* translators: 1: photo link, 2: why it couldn't be used. */
				$current['report']['warnings'][] = sprintf( __( 'The photo %1$s couldn\'t be added: %2$s', 'crc-real-estate' ), $photo['url'], self::too_big() );

				return null;
			}

			// What the stopped try left half-made goes before trying again.
			if ( $tries > 0 ) {
				self::drop_unfinished( $photo['url'], $current['id'] );
			}

			// Saved before trying, so a try the server stops is counted.
			$current['trying'] = array(
				'url'   => $photo['url'],
				'tries' => $tries + 1,
			);
			update_option( self::JOB_OPTION . (int) $user_id, $job, false );

			$id = self::photo( $photo['url'], $current['id'], $current['report']['title'] );

			array_shift( $current['photos'] );
			unset( $current['trying'] );

			if ( is_wp_error( $id ) ) {
				/* translators: 1: photo link, 2: why it couldn't be used. */
				$current['report']['warnings'][] = sprintf( __( 'The photo %1$s couldn\'t be added: %2$s', 'crc-real-estate' ), $photo['url'], $id->get_error_message() );
			} elseif ( 'main' === $photo['role'] ) {
				$current['main'] = $id;
			} else {
				$current['more'][] = $id;
			}

			return null;
		}

		$report = self::finish( $current );
		unset( $current );
		self::next_row( $job );

		return $report;
	}

	/**
	 * Moves on to the next row.
	 *
	 * @param array $job Import, changed in place.
	 */
	private static function next_row( array &$job ) {
		$job['current'] = null;
		$job['at']++;
	}

	/**
	 * Adds or changes a row's listing: its title and text. A new listing is
	 * marked with the row it came from, so if the server stops before this
	 * is saved, the row finds the same listing again instead of adding it twice.
	 *
	 * @param array  $row Row with 'line' and 'cells'.
	 * @param string $tag The import's ID and the row's number.
	 * @return array The work left for the row, or 'failed' with its report.
	 */
	private static function begin( array $row, $tag ) {
		$cells  = $row['cells'];
		$cell   = function ( $name ) use ( $cells ) {
			return isset( $cells[ $name ] ) ? trim( (string) $cells[ $name ] ) : '';
		};
		$title  = trim( wp_strip_all_tags( $cell( 'title' ) ) );
		$id     = absint( $cell( 'id' ) );
		$post   = null;
		$report = array(
			'line'     => (int) $row['line'],
			'title'    => $title,
			'id'       => 0,
			'link'     => '',
			'action'   => 'failed',
			'status'   => '',
			'warnings' => array(),
		);
		$fail   = function ( $message ) use ( &$report ) {
			$report['warnings'][] = $message;

			return array( 'failed' => $report );
		};

		if ( '' !== $cell( 'id' ) && ( ! $id || ! ctype_digit( $cell( 'id' ) ) ) ) {
			/* translators: %s: what was written. */
			return $fail( sprintf( __( 'The id "%s" isn\'t a listing ID. Leave it empty to add a new listing.', 'crc-real-estate' ), $cell( 'id' ) ) );
		}

		if ( $id ) {
			$post = get_post( $id );

			if ( ! $post || Post_Type::NAME !== $post->post_type ) {
				/* translators: %d: listing ID. */
				return $fail( sprintf( __( 'There is no listing with the ID %d. Leave the id empty to add it as a new listing.', 'crc-real-estate' ), $id ) );
			}

			if ( 'trash' === $post->post_status ) {
				/* translators: %d: listing ID. */
				return $fail( sprintf( __( 'The listing with the ID %d is in the Trash. Restore it first, or leave the id empty to add it as a new listing.', 'crc-real-estate' ), $id ) );
			}

			if ( ! current_user_can( 'edit_post', $id ) ) {
				/* translators: %d: listing ID. */
				return $fail( sprintf( __( 'You can\'t change the listing with the ID %d.', 'crc-real-estate' ), $id ) );
			}

			$report['title'] = '' !== $title ? $title : (string) $post->post_title;
		} elseif ( '' === $title ) {
			return $fail( __( 'A new listing needs a title.', 'crc-real-estate' ) );
		}

		// "future" (scheduled), as the export writes it, leaves the status as it is.
		$asked  = strtolower( $cell( 'status' ) );
		$keep   = in_array( $asked, array( 'future', 'scheduled' ), true );
		$status = $keep ? '' : Listing_Data::status( $asked );

		if ( '' !== $asked && '' === $status && ! $keep ) {
			/* translators: %s: what was written. */
			$report['warnings'][] = sprintf( __( 'The status "%s" isn\'t one of draft, publish, pending or private, so it was left as it was.', 'crc-real-estate' ), $cell( 'status' ) );
		}

		// A listing that was live stays live once its photos and category are in.
		if ( $post && '' === $status && in_array( $post->post_status, array( 'publish', 'pending', 'future' ), true ) ) {
			$status = $post->post_status;
		}

		$fields = array( 'post_type' => Post_Type::NAME );

		if ( '' !== $title ) {
			$fields['post_title'] = $title;
		}

		// WordPress removes code that the person importing isn't allowed to add, as on the listing screen.
		if ( '' !== $cell( 'description' ) ) {
			$fields['post_content'] = str_replace( array( "\r\n", "\r" ), "\n", $cell( 'description' ) );
		}

		if ( ! $id ) {
			$found = get_posts(
				array(
					'post_type'        => Post_Type::NAME,
					'post_status'      => 'any',
					'meta_key'         => self::ROW_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Once per new listing.
					'meta_value'       => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Once per new listing.
					'fields'           => 'ids',
					'numberposts'      => 1,
					'suppress_filters' => true,
				)
			);

			if ( $found ) {
				$fields['ID'] = (int) $found[0];
				$result       = wp_update_post( wp_slash( $fields ), true );
			} else {
				// Every new listing starts as a draft; it goes live once its photos are in.
				$fields['post_status'] = 'draft';
				$fields['meta_input']  = array( self::ROW_META => $tag );
				$result                = wp_insert_post( wp_slash( $fields ), true );
			}
		} elseif ( isset( $fields['post_title'] ) || isset( $fields['post_content'] ) ) {
			$fields['ID'] = $id;
			$result       = wp_update_post( wp_slash( $fields ), true );
		} else {
			$result = $id;
		}

		if ( is_wp_error( $result ) || ! $result ) {
			return $fail( is_wp_error( $result ) ? $result->get_error_message() : __( 'The listing couldn\'t be saved.', 'crc-real-estate' ) );
		}

		$report['id']     = (int) $result;
		$report['action'] = $id ? 'updated' : 'created';

		return array(
			'id'         => $report['id'],
			'status'     => $status,
			'photos'     => null,
			'more_given' => false,
			'main'       => 0,
			'more'       => array(),
			'report'     => $report,
		);
	}

	/**
	 * Fills in a row's details and lists its photos to download.
	 *
	 * @param array $current The row's work, changed in place.
	 * @param array $cells   The row's cells.
	 */
	private static function fill( array &$current, array $cells ) {
		$current['report']['warnings'] = array_merge( $current['report']['warnings'], Listing_Data::apply( $current['id'], $cells ) );

		$links  = Listing_Data::photo_links( $cells );
		$photos = array();

		if ( null !== $links['main'] ) {
			$photos[] = array(
				'url'  => $links['main'],
				'role' => 'main',
			);
		}

		foreach ( null !== $links['more'] ? $links['more'] : array() as $url ) {
			$photos[] = array(
				'url'  => $url,
				'role' => 'more',
			);
		}

		$current['photos']     = $photos;
		$current['more_given'] = null !== $links['more'];
	}

	/**
	 * Puts a row's downloaded photos on its listing: the main photo, and the
	 * more photos in their order.
	 *
	 * @param array $current The row's work.
	 * @return bool Whether none of the more photos the row gave could be added.
	 */
	private static function place_photos( array $current ) {
		$id = (int) $current['id'];

		if ( $current['main'] ) {
			set_post_thumbnail( $id, $current['main'] );
		}

		if ( ! $current['more_given'] ) {
			return false;
		}

		$main = (int) get_post_thumbnail_id( $id );
		$more = array_values( array_diff( array_unique( array_map( 'intval', $current['more'] ) ), array( $main ) ) );

		if ( $more ) {
			update_post_meta( $id, Gallery::META, $more );
		}

		return ! $current['more'];
	}

	/**
	 * Puts a row's downloaded photos on its listing, sets the status it asked
	 * for, and reports on it.
	 *
	 * @param array $current The row's work.
	 * @return array Report.
	 */
	private static function finish( array $current ) {
		$id     = $current['id'];
		$report = $current['report'];

		if ( self::place_photos( $current ) ) {
			$report['warnings'][] = __( 'None of the more photos could be added, so the listing keeps the ones it had.', 'crc-real-estate' );
		}

		$wanted = $current['status'];

		if ( '' !== $wanted && get_post_status( $id ) !== $wanted ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => $wanted,
				)
			);
		}

		$report['status'] = (string) get_post_status( $id );

		if ( in_array( $wanted, array( 'publish', 'pending', 'future' ), true ) && ! in_array( $report['status'], array( $wanted, 'publish' ), true ) ) {
			$missing = array();

			if ( ! get_post_thumbnail_id( $id ) ) {
				$missing[] = __( 'a main photo', 'crc-real-estate' );
			}

			if ( ! Taxonomy::listing_category( $id ) ) {
				$missing[] = __( 'a category', 'crc-real-estate' );
			}

			$report['warnings'][] = $missing
				/* translators: %s: what's missing, e.g. "a main photo and a category". */
				? sprintf( __( 'It was kept as a draft because it needs %s before it can be published.', 'crc-real-estate' ), implode( __( ' and ', 'crc-real-estate' ), $missing ) )
				/* translators: %s: status asked for. */
				: sprintf( __( 'It couldn\'t be set to %s, so it was kept as it was.', 'crc-real-estate' ), $wanted );
		}

		delete_post_meta( $id, self::ROW_META );
		$report['link'] = (string) get_edit_post_link( $id, 'raw' );

		return $report;
	}

	/**
	 * Picture types this site accepts, with their extension and name. AVIF
	 * only from WordPress 6.5, which added it.
	 *
	 * @return array[]
	 */
	private static function photo_types() {
		$types   = self::PHOTO_TYPES;
		$allowed = get_allowed_mime_types();

		foreach ( array_keys( $types ) as $mime ) {
			if ( ! in_array( $mime, $allowed, true ) ) {
				unset( $types[ $mime ] );
			}
		}

		return $types;
	}

	/**
	 * Why a photo that stopped the server isn't added.
	 *
	 * @return string
	 */
	private static function too_big() {
		return __( 'the server stopped while preparing this photo, which usually means it is very large. Please use a smaller copy, for example one saved for the web, or add it on the listing screen.', 'crc-real-estate' );
	}

	/**
	 * Removes photos that a stopped try left half-made on a listing.
	 *
	 * @param string $url     The photo's link.
	 * @param int    $post_id Listing ID.
	 */
	private static function drop_unfinished( $url, $post_id ) {
		$url = esc_url_raw( self::direct_link( trim( (string) $url ) ), array( 'http', 'https' ) );

		if ( '' === $url ) {
			return;
		}

		$found = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'post_parent'      => (int) $post_id,
				'meta_key'         => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Rarely, after a stopped try.
				'meta_value'       => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Rarely, after a stopped try.
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);

		foreach ( $found as $id ) {
			if ( get_post_meta( $id, self::PENDING_META, true ) ) {
				wp_delete_attachment( $id, true );
			}
		}
	}

	/**
	 * Adds a photo from a link to the Media Library, attached to a listing.
	 * A photo already in the library, or downloaded before from the same
	 * link, is used again instead of downloaded twice.
	 *
	 * @param string $url     Link.
	 * @param int    $post_id Listing ID.
	 * @param string $title   Listing title, used as the photo's title and alt text.
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function photo( $url, $post_id, $title = '' ) {
		$url = esc_url_raw( self::direct_link( trim( (string) $url ) ), array( 'http', 'https' ) );

		if ( '' === $url ) {
			return new \WP_Error( 'bad_link', __( 'it isn\'t a web address starting with https://.', 'crc-real-estate' ) );
		}

		$id = attachment_url_to_postid( $url );

		if ( $id && wp_attachment_is_image( $id ) ) {
			return (int) $id;
		}

		$found = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'meta_key'         => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Once per photo during an import.
				'meta_value'       => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Once per photo during an import.
				'fields'           => 'ids',
				'numberposts'      => 5,
				'suppress_filters' => true,
			)
		);

		foreach ( $found as $found_id ) {
			$started = (int) get_post_meta( $found_id, self::PENDING_META, true );

			if ( ! $started ) {
				return (int) $found_id;
			}

			// Left half-made by an earlier try that stopped: removed, and the photo is downloaded again.
			// (A photo that stops the server twice is left out by the import itself.)
			if ( time() - $started > self::LOCK_TIME ) {
				wp_delete_attachment( $found_id, true );
			}

			// Otherwise another import is adding the same photo right now: this one gets its own copy.
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// download_url() only fetches public addresses, never ones inside the server's own network.
		$file = download_url( $url, self::TIMEOUT );

		if ( is_wp_error( $file ) ) {
			return new \WP_Error( 'download', self::download_error( $file ) );
		}

		$types = self::photo_types();
		$mime  = wp_get_image_mime( $file );

		if ( ! $mime || ! isset( $types[ $mime ] ) ) {
			wp_delete_file( $file );

			$names = wp_list_pluck( $types, 1 );
			$last  = array_pop( $names );

			/* translators: %s: photo types, e.g. "JPG, PNG, GIF or WebP". */
			return new \WP_Error( 'not_photo', sprintf( __( 'the link doesn\'t lead to a photo (%s). If it\'s a Google Drive or Dropbox link, check the file is shared with anyone who has the link.', 'crc-real-estate' ), $names ? implode( ', ', $names ) . ' ' . __( 'or', 'crc-real-estate' ) . ' ' . $last : $last ) );
		}

		// Phone photos can hold the exact place they were taken, which the website keeps private.
		Photo_Privacy::clean( $file );

		$name = sanitize_file_name( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) );
		$data = array(
			// Marked as soon as it is added, so a try the server stops is found again.
			'meta_input' => array(
				self::SOURCE_META  => wp_slash( $url ),
				self::PENDING_META => (string) time(),
			),
		);

		$title = sanitize_text_field( $title );

		if ( '' !== $title ) {
			$data['post_title'] = $title;
		}

		$id = media_handle_sideload(
			array(
				'name'     => ( '' !== $name && ! in_array( $name, array( 'uc', 'download' ), true ) ? $name : 'photo' ) . '.' . $types[ $mime ][0],
				'tmp_name' => $file,
			),
			$post_id,
			null,
			$data
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $file );

			return $id;
		}

		delete_post_meta( $id, self::PENDING_META );

		if ( '' !== $title ) {
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $title ) );
		}

		return (int) $id;
	}

	/**
	 * Why a photo couldn't be downloaded, in plain words.
	 *
	 * @param \WP_Error $error Download error.
	 * @return string
	 */
	private static function download_error( \WP_Error $error ) {
		$data    = $error->get_error_data();
		$code    = is_array( $data ) && isset( $data['code'] ) ? (int) $data['code'] : 0;
		$message = (string) $error->get_error_message();

		if ( 404 === $code || 410 === $code ) {
			return __( 'there is nothing at that link (page not found).', 'crc-real-estate' );
		}

		if ( 401 === $code || 403 === $code ) {
			return __( 'the website doesn\'t let anyone else download it. Make it public, or share it with anyone who has the link.', 'crc-real-estate' );
		}

		if ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'cURL error 28' ) ) {
			/* translators: %d: seconds. */
			return sprintf( __( 'the photo took too long to download (more than %d seconds). Please use a smaller copy, or add it on the listing screen.', 'crc-real-estate' ), self::TIMEOUT );
		}

		if ( 'http_request_not_executed' === $error->get_error_code() || 'http_request_failed' === $error->get_error_code() ) {
			return __( 'the website couldn\'t be reached. Check that the link opens in your browser.', 'crc-real-estate' );
		}

		if ( $code >= 500 ) {
			return __( 'the website that has the photo had a problem. Please try again later.', 'crc-real-estate' );
		}

		return $message;
	}

	/**
	 * Turns a Google Drive or Dropbox share link into a link to the file itself.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	public static function direct_link( $url ) {
		if ( preg_match( '#^https?://drive\.google\.com/(?:file/d/([\w-]+)|open\?id=([\w-]+)|uc\?(?:[^\#]*&)?id=([\w-]+))#', $url, $drive ) ) {
			return 'https://drive.google.com/uc?export=download&id=' . implode( '', array_slice( $drive, 1 ) );
		}

		if ( preg_match( '#^https?://(?:www\.)?dropbox\.com/#', $url ) ) {
			return add_query_arg( 'raw', '1', remove_query_arg( 'dl', $url ) );
		}

		return $url;
	}
}
