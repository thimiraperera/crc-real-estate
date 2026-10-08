<?php
/**
 * CSV files.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes CSV files the way spreadsheets make them: commas or
 * semicolons, with or without Excel's byte order mark, in UTF-8 or the
 * Windows character set. Cells that a spreadsheet would run as a formula
 * are written safely and read back as they were.
 */
final class Csv {

	/**
	 * Characters that make a spreadsheet treat a cell as a formula.
	 */
	const FORMULA_START = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Excel's byte order mark, so it opens the file as UTF-8.
	 */
	const BOM = "\xEF\xBB\xBF";

	/**
	 * No escape character: quotes are doubled, as spreadsheets do. PHP 7.4
	 * and later allow that; older PHP needs one.
	 *
	 * @return string
	 */
	private static function escape_char() {
		return PHP_VERSION_ID >= 70400 ? '' : '\\';
	}

	/**
	 * Reads a CSV file into rows keyed by the column names of the first row.
	 * Column names are trimmed and lower-cased; empty rows are skipped.
	 *
	 * @param string $path File path.
	 * @return array|\WP_Error 'columns' (names), 'rows' (each with 'line', the row's line in the file, and 'cells') and 'converted' (whether the file wasn't in UTF-8 and was converted from the Windows character set).
	 */
	public static function read( $path ) {
		$text = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.

		if ( '' === trim( $text ) ) {
			return new \WP_Error( 'empty', __( 'The file is empty.', 'crc-real-estate' ) );
		}

		return self::parse( $text );
	}

	/**
	 * Reads CSV text into rows keyed by the column names of the first row.
	 *
	 * @param string $text CSV text.
	 * @return array|\WP_Error See read().
	 */
	public static function parse( $text ) {
		$converted = false;
		$text      = self::to_utf8( $text, $converted );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$head = strtok( $text, "\n" );
		$sep  = substr_count( (string) $head, ';' ) > substr_count( (string) $head, ',' ) ? ';' : ',';

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $handle );

		$columns = array();
		$rows    = array();
		$line    = 0;

		while ( false !== ( $cells = fgetcsv( $handle, 0, $sep, '"', self::escape_char() ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Reading line by line.
			$line++;

			if ( ! $columns ) {
				$columns = array_map(
					function ( $name ) {
						return strtolower( trim( (string) $name ) );
					},
					$cells
				);
				continue;
			}

			if ( ! array_filter( $cells, 'strlen' ) ) {
				continue;
			}

			$row = array();

			foreach ( $columns as $i => $name ) {
				if ( '' !== $name ) {
					$row[ $name ] = isset( $cells[ $i ] ) ? self::unescape( (string) $cells[ $i ] ) : '';
				}
			}

			$rows[] = array(
				'line'  => $line,
				'cells' => $row,
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.

		if ( ! array_filter( $columns, 'strlen' ) ) {
			return new \WP_Error( 'no_columns', __( 'The first row of the file has no column names.', 'crc-real-estate' ) );
		}

		return array(
			'columns'   => array_values( array_filter( $columns, 'strlen' ) ),
			'rows'      => $rows,
			'converted' => $converted,
		);
	}

	/**
	 * Writes rows as CSV text, with Excel's byte order mark so it opens
	 * the text correctly.
	 *
	 * @param string[] $columns Column names, in order.
	 * @param array[]  $rows    Rows keyed by column name.
	 * @return string
	 */
	public static function write( array $columns, array $rows ) {
		$csv = self::BOM . self::line( $columns, false );

		foreach ( $rows as $row ) {
			$csv .= self::row( $columns, $row );
		}

		return $csv;
	}

	/**
	 * A row of a CSV file, its cells in the order of the columns.
	 *
	 * @param string[] $columns Column names, in order.
	 * @param array    $row     Cells keyed by column name.
	 * @return string
	 */
	public static function row( array $columns, array $row ) {
		$cells = array();

		foreach ( $columns as $name ) {
			$cells[] = isset( $row[ $name ] ) ? (string) $row[ $name ] : '';
		}

		return self::line( $cells );
	}

	/**
	 * One line of a CSV file, ending with Windows line ends, as Excel writes
	 * them. A line break inside a cell stays one line break.
	 *
	 * @param string[] $cells  Cells.
	 * @param bool     $escape Whether to protect cells a spreadsheet would run as a formula.
	 * @return string
	 */
	public static function line( array $cells, $escape = true ) {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		$clean  = array();

		foreach ( $cells as $cell ) {
			$cell    = str_replace( array( "\r\n", "\r" ), "\n", (string) $cell );
			$clean[] = $escape ? self::escape( $cell ) : $cell;
		}

		fputcsv( $handle, $clean, ',', '"', self::escape_char() );
		rewind( $handle );
		$line = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.

		return str_replace( "\n", "\r\n", $line );
	}

	/**
	 * Keeps a spreadsheet from running a cell as a formula: a cell that
	 * starts with = + - or @ gets an apostrophe in front, which spreadsheets
	 * hide. Phone numbers such as +94 77 123 4567 stay readable.
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	public static function escape( $value ) {
		$value = (string) $value;

		return '' !== $value && in_array( $value[0], self::FORMULA_START, true ) ? "'" . $value : $value;
	}

	/**
	 * Takes the apostrophe back off a cell that escape() protected.
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	public static function unescape( $value ) {
		$value = (string) $value;

		return strlen( $value ) > 1 && "'" === $value[0] && in_array( $value[1], self::FORMULA_START, true ) ? substr( $value, 1 ) : $value;
	}

	/**
	 * Text in UTF-8, without Excel's byte order mark. Files saved in the
	 * Windows character set are converted; letters that set doesn't have,
	 * such as Sinhala and Tamil, were already lost when the file was saved.
	 *
	 * @param string $text      Text.
	 * @param bool   $converted Set to whether the text was converted.
	 * @return string
	 */
	private static function to_utf8( $text, &$converted = false ) {
		$converted = false;

		if ( 0 === strpos( $text, "\xEF\xBB\xBF" ) ) {
			$text = substr( $text, 3 );
		}

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) && function_exists( 'mb_convert_encoding' ) ) {
			$text      = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
			$converted = true;
		}

		return $text;
	}
}
