<?php
/**
 * The CSV Import class.
 *
 * @since      1.0
 * @package    RankMathPro
 * @subpackage RankMathPro\Admin
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Admin\CSV_Import_Export;

use RankMath\Helpers\Arr;
use RankMath\Helpers\DB as DB_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * CSV Importer class.
 *
 * @codeCoverageIgnore
 */
class Importer {

	/**
	 * Term slug => ID cache.
	 *
	 * @var array
	 */
	private static $term_ids = [];

	/**
	 * Settings array. Default values.
	 *
	 * @var array
	 */
	private $settings = [
		'not_applicable_value' => 'n/a',
		'clear_command'        => 'DELETE',
		'no_overwrite'         => true,
	];

	/**
	 * Lines in the CSV that could not be imported for any reason.
	 *
	 * @var array
	 */
	private $failed_rows = [];

	/**
	 * Lines in the CSV that could be imported successfully.
	 *
	 * @var array
	 */
	private $imported_rows = [];

	/**
	 * Error messages.
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * SplFileObject instance.
	 *
	 * @var \SplFileObject
	 */
	private $spl;

	/**
	 * Column headers.
	 *
	 * @var array
	 */
	private $column_headers = [];

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_settings();
	}

	/**
	 * Load settings.
	 *
	 * @return void
	 */
	public function load_settings() {
		$this->settings = apply_filters( 'rank_math/admin/csv_import_settings', wp_parse_args( get_option( 'rank_math_csv_import_settings', [] ), $this->settings ) );
	}

	/**
	 * Start import from file.
	 *
	 * @param string $file     Path to temporary CSV file.
	 * @param string $settings Import settings.
	 * @return void
	 */
	public function start( $file, $settings = [] ) {
		update_option( 'rank_math_csv_import', $file );
		update_option( 'rank_math_csv_import_settings', $settings );
		delete_option( 'rank_math_csv_import_status' );
		$this->load_settings();
		$lines = $this->count_lines( $file );
		update_option( 'rank_math_csv_import_total', $lines );
		Import_Background_Process::get()->start( $lines );
	}

	/**
	 * Count all lines in CSV file.
	 *
	 * @param mixed $file Path to CSV.
	 * @return int
	 */
	public function count_lines( $file ) {
		$file = new \SplFileObject( $file );
		while ( $file->valid() ) {
			$file->fgets();
		}

		$count = $file->key();

		// Check if last line is empty.
		$file->seek( $count );
		$contents = $file->current();
		if ( empty( trim( $contents ) ) ) {
			--$count;
		}

		// Unlock file.
		$file = null;

		return $count;
	}

	/**
	 * Get specified line from CSV.
	 *
	 * @param string $file Path to file.
	 * @param int    $line Line number.
	 * @return string
	 */
	public function get_line( $file, $line ) {
		if ( empty( $this->spl ) ) {
			$this->spl = new \SplFileObject( $file );
		}

		if ( ! $this->spl->eof() ) {
			$this->spl->seek( $line );
			$contents = $this->spl->current();
		}

		return $contents;
	}

	/**
	 * Parse and return column headers (first line in CSV).
	 *
	 * @param string $file Path to file.
	 * @return array
	 */
	public function get_column_headers( $file ) {
		if ( ! empty( $this->column_headers ) ) {
			return $this->column_headers;
		}

		if ( empty( $this->spl ) ) {
			$this->spl = new \SplFileObject( $file );
		}

		if ( ! $this->spl->eof() ) {
			$this->spl->seek( 0 );
			$contents = $this->spl->current();
		}

		if ( empty( $contents ) ) {
			return [];
		}

		$this->column_headers = Arr::from_string( $contents, apply_filters( 'rank_math/csv_import/separator', ',' ) );
		return $this->column_headers;
	}

	/**
	 * Imports batch of rows started getting processed by WP_Background_Process.
	 *
	 * @param array $item Array of line numbers.
	 *
	 * @return void
	 */
	public function import_batch( $item ) {
		$data = [];
		foreach ( $item as $line_number ) {
			$row_data = $this->get_row_data( $line_number );
			if ( ! $row_data ) {
				continue;
			}
			$row_importer = new Import_Row( $row_data, $this->settings, false );
			$object_type  = $row_importer->object_type;
			foreach ( [ 'update', 'delete' ] as $action ) {
				if ( ! empty( $row_importer->meta_data[ $action ] ) ) {
					if ( empty( $data[ $object_type ][ $action ] ) ) {
						$data[ $object_type ][ $action ] = [];
					}
					$data[ $object_type ][ $action ] = array_merge( $data[ $object_type ][ $action ], $row_importer->meta_data[ $action ] );
				}
			}
			$this->row_imported( $line_number );
		}
		foreach ( $data as $object_type => $object_data ) {
			if ( ! empty( $object_data['update'] ) ) {
				$this->update_object_metas( $object_data['update'], $object_type );
			}
			if ( ! empty( $object_data['delete'] ) ) {
				$this->delete_object_metas( $object_data['delete'], $object_type );
			}
		}
	}

	/**
	 * Get the table name to update for the current row being imported.
	 *
	 * @param string $object_type  Object type. Either of 'post', 'term' or 'user'.
	 *
	 * @return string
	 */
	private function get_table_name( $object_type ) {
		global $wpdb;
		$type = "{$object_type}meta";
		return $wpdb->$type;
	}

	/**
	 * Deletes object metas.
	 * Note: We would delete the entry from the meta table, when the value read from CSV is empty for each meta.
	 *
	 * @param array  $metas_to_delete  Array of metas to delete.
	 * @param string $object_type      Object type. Either of 'post', 'term' or 'user'.
	 *
	 * @return void
	 */
	public function delete_object_metas( $metas_to_delete, $object_type ) {
		global $wpdb;
		$where_conditions = [];
		$table_name       = $this->get_table_name( $object_type );
		$id_column_name   = "{$object_type}_id"; // Can be post_id, term_id or user_id.
		foreach ( $metas_to_delete as $meta_to_delete ) {
			$where_conditions[] = $wpdb->prepare(
				'(%i=%d AND meta_key=%s)',
				$id_column_name,
				$meta_to_delete[ $id_column_name ],
				$meta_to_delete['meta_key']
			);
		}
		$wpdb->query( "DELETE FROM {$table_name} WHERE " . implode( ' OR ', $where_conditions ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Updates the object metas tables.
	 *
	 * @param array  $meta_updates Array of object metas to update.
	 * @param string $object_type  Object type. Either of 'post', 'term' or 'user'.
	 *
	 * @return void
	 */
	public function update_object_metas( $meta_updates, $object_type ) {
		if ( empty( $meta_updates ) ) {
			return;
		}
		$this->update_existing_metas( $meta_updates, $object_type );
		$this->insert_new_metas( $meta_updates, $object_type );
	}

	/**
	 * Updates existing metas.
	 *
	 * @param array  $meta_updates Array of object metas to update.
	 * @param string $object_type  Object type. Either of 'post', 'term' or 'user'.
	 *
	 * @return void
	 */
	private function update_existing_metas( $meta_updates, $object_type ) {
		global $wpdb;
		$table_name = $this->get_table_name( $object_type );

		$id_column_name = "{$object_type}_id";
		$values_sql     = [];
		foreach ( $meta_updates as $i => $row ) {
			$post_id    = (int) $row[ $id_column_name ];
			$meta_key   = addslashes( $row['meta_key'] );
			$meta_value = addslashes( $row['meta_value'] );

			if ( $i === 0 ) {
				$values_sql[] = "SELECT {$post_id} AS $id_column_name, '{$meta_key}' AS meta_key, '{$meta_value}' AS meta_value";
			} else {
				$values_sql[] = "SELECT {$post_id}, '{$meta_key}', '{$meta_value}'";
			}
		}
		$values_union_sql = implode( " UNION ALL\n", $values_sql );

		//phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"
				UPDATE $table_name pm JOIN ( {$values_union_sql} ) AS new_values
				ON pm.$id_column_name = new_values.$id_column_name AND pm.meta_key = new_values.meta_key
				SET pm.meta_value = new_values.meta_value;
			"
		);
		//phpcs:enable
	}

	/**
	 * Inserts new metas.
	 *
	 * @param array  $meta_updates Array of object metas to update.
	 * @param string $object_type  Object type. Either of 'post', 'term' or 'user'.
	 *
	 * @return void
	 */
	private function insert_new_metas( $meta_updates, $object_type ) {
		global $wpdb;
		$id_column_name   = "{$object_type}_id";
		$where_conditions = [];

		foreach ( $meta_updates as $update ) {
			$where_conditions[] = $wpdb->prepare(
				'( %i = %d AND meta_key = %s)',
				$id_column_name,
				$update[ $id_column_name ],
				$update['meta_key']
			);
		}

		$table_name     = $this->get_table_name( $object_type );
		$existing_metas = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT %i, meta_key FROM %i WHERE ' . implode( ' OR ', $where_conditions ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$id_column_name,
				$table_name
			)
		);

		foreach ( $meta_updates as $key => $update ) {
			$metas_with_matching_post_id = array_filter(
				$existing_metas,
				function ( $row ) use ( $update, $id_column_name ) {
					return $row->{$id_column_name} === $update[ $id_column_name ];
				}
			);

			$meta_key_exists = false !== array_search( $update['meta_key'], array_column( $metas_with_matching_post_id, 'meta_key' ), true );
			if ( $meta_key_exists ) {
				unset( $meta_updates[ $key ] );
				continue;
			}

			$meta_updates[ $key ] = $wpdb->prepare(
				'(%d, %s, %s)',
				$update[ $id_column_name ],
				$update['meta_key'],
				$update['meta_value']
			);
		}
		if ( empty( $meta_updates ) ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i ( %i, meta_key, meta_value ) VALUES ' . implode( ',', $meta_updates ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$table_name,
				$id_column_name
			)
		);
	}

	/**
	 * Get row data.
	 * Returns false if the line number is 0 or if the CSV structure is not validated.
	 *
	 * @param int $line_number Line number.
	 *
	 * @return array|false
	 */
	public function get_row_data( $line_number ) {
		// Skip headers.
		if ( 0 === $line_number ) {
			return false;
		}

		$file = get_option( 'rank_math_csv_import' );
		if ( ! $file ) {
			$this->add_error( esc_html__( 'Missing import file.', 'rank-math-pro' ), 'missing_file' );
			CSV_Import_Export::cancel_import( true );
			return false;
		}

		$headers = $this->get_column_headers( $file );
		if ( empty( $headers ) ) {
			$this->add_error( esc_html__( 'Missing CSV headers.', 'rank-math-pro' ), 'missing_headers' );
			return false;
		}

		$required_columns = [ 'id', 'object_type', 'slug' ];
		if ( count( array_intersect( $headers, $required_columns ) ) !== count( $required_columns ) ) {
			$this->add_error( esc_html__( 'Missing one or more required columns.', 'rank-math-pro' ), 'missing_required_columns' );
			return false;
		}

		$raw_data = $this->get_line( $file, $line_number );
		if ( empty( $raw_data ) ) {
			$total_lines = (int) get_option( 'rank_math_csv_import_total' );

			// Last line can be empty, that is not an error.
			if ( $line_number !== $total_lines ) {
				$this->add_error( esc_html__( 'Empty column data.', 'rank-math-pro' ), 'missing_data' );
				$this->row_failed( $line_number );
			}

			return false;
		}

		$csv_separator = apply_filters( 'rank_math/csv_import/separator', ',' );
		$decoded       = str_getcsv( $raw_data, $csv_separator );
		if ( count( $headers ) !== count( $decoded ) ) {
			$this->add_error( esc_html__( 'Columns number mismatch.', 'rank-math-pro' ), 'columns_number_mismatch' );
			$this->row_failed( $line_number );
			return false;
		}

		$data = array_combine( $headers, $decoded );
		if ( ! in_array( $data['object_type'], array_keys( CSV_Import_Export::get_possible_object_types() ), true ) ) {
			$this->add_error( esc_html__( 'Unknown object type.', 'rank-math-pro' ), 'unknown_object_type' );
			$this->row_failed( $line_number );
			return false;
		}

		return $data;
	}

	/**
	 * Import specified line.
	 *
	 * @param int $line_number Selected line number.
	 * @return void
	 */
	public function import_line( $line_number ) {
		$data = $this->get_row_data( $line_number );
		if ( ! $data ) {
			return;
		}

		new Import_Row( $data, $this->settings );
		$this->row_imported( $line_number );
	}

	/**
	 * Get term ID from slug.
	 *
	 * @param string $term_slug Term slug.
	 * @return int
	 */
	public static function get_term_id( $term_slug ) {
		global $wpdb;

		if ( ! empty( self::$term_ids[ $term_slug ] ) ) {
			return self::$term_ids[ $term_slug ];
		}

		self::$term_ids[ $term_slug ] = DB_Helper::get_var(
			$wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug = %s", $term_slug )
		);

		return self::$term_ids[ $term_slug ];
	}

	/**
	 * After each batch is finished.
	 *
	 * @param array $items Processed items.
	 */
	public function batch_done( $items ) { // phpcs:ignore
		unset( $this->spl );

		$status = (array) get_option( 'rank_math_csv_import_status', [] );
		if ( ! isset( $status['errors'] ) || ! is_array( $status['errors'] ) ) {
			$status['errors'] = [];
		}
		if ( ! isset( $status['failed_rows'] ) || ! is_array( $status['failed_rows'] ) ) {
			$status['failed_rows'] = [];
		}
		if ( ! isset( $status['imported_rows'] ) || ! is_array( $status['imported_rows'] ) ) {
			$status['imported_rows'] = [];
		}

		$status['imported_rows'] = array_merge( $status['imported_rows'], $this->get_imported_rows() );

		$errors = $this->get_errors();
		if ( $errors ) {
			$status['errors']      = array_merge( $status['errors'], $errors );
			$status['failed_rows'] = array_merge( $status['failed_rows'], $this->get_failed_rows() );
		}

		update_option( 'rank_math_csv_import_status', $status );
	}

	/**
	 * Set row import status.
	 *
	 * @param int $row Row index.
	 */
	private function row_failed( $row ) {
		$this->failed_rows[] = $row + 1;
	}

	/**
	 * Set row import status.
	 *
	 * @param int $row Row index.
	 */
	private function row_imported( $row ) {
		$this->imported_rows[] = $row + 1;
	}

	/**
	 * Get failed rows array.
	 *
	 * @return array
	 */
	private function get_failed_rows() {
		return $this->failed_rows;
	}

	/**
	 * Get failed rows array.
	 *
	 * @return array
	 */
	private function get_imported_rows() {
		return $this->imported_rows;
	}

	/**
	 * Get all import errors.
	 *
	 * @return mixed Array of errors or false if there is no error.
	 */
	public function get_errors() {
		return empty( $this->errors ) ? false : $this->errors;
	}

	/**
	 * Add import error.
	 *
	 * @param string $message Error message.
	 * @param int    $code    Error code.
	 */
	public function add_error( $message, $code = null ) {
		if ( is_null( $code ) ) {
			$this->errors[] = $message;
			return;
		}
		$this->errors[ $code ] = $message;
	}
}
