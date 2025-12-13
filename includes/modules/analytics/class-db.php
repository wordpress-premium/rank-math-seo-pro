<?php
/**
 * The Analytics module database operations
 *
 * @since      2.0.0
 * @package    RankMathPro
 * @subpackage RankMathPro\modules
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Analytics;

use RankMath\Helper;
use RankMath\Helpers\Str;
use RankMath\Admin\Admin_Helper;
use RankMath\Google\Analytics as Analytics_Free;
use RankMath\Analytics\Stats;
use RankMath\Analytics\DB as AnalyticsDB;
use RankMath\Helpers\DB as DB_Helper;
use RankMathPro\Google\Adsense;
use RankMathPro\Analytics\Keywords;
use RankMath\Admin\Database\Database;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * DB class.
 */
class DB {

	/**
	 * Get any table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function table( $table_name ) {
		return Database::table( $table_name );
	}

	/**
	 * Get console data table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function analytics() {
		return Database::table( 'rank_math_analytics_gsc' );
	}

	/**
	 * Get analytics data table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function traffic() {
		return Database::table( 'rank_math_analytics_ga' );
	}

	/**
	 * Get adsense data table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function adsense() {
		return Database::table( 'rank_math_analytics_adsense' );
	}

	/**
	 * Get objects table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function objects() {
		return Database::table( 'rank_math_analytics_objects' );
	}

	/**
	 * Get inspections table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function inspections() {
		return Database::table( 'rank_math_analytics_inspections' );
	}

	/**
	 * Get links table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function links() {
		return Database::table( 'rank_math_internal_meta' );
	}

	/**
	 * Get keywords table.
	 *
	 * @return \RankMath\Admin\Database\Query_Builder
	 */
	public static function keywords() {
		return Database::table( 'rank_math_analytics_keyword_manager' );
	}

	/**
	 * Delete console and analytics data.
	 *
	 * @param  int $days Decide whether to delete all or delete 90 days data.
	 */
	public static function delete_by_days( $days ) {
		if ( -1 === $days ) {
			self::traffic()->truncate();
			self::analytics()->truncate();
		} else {
			$start = date_i18n( 'Y-m-d H:i:s', strtotime( '-1 days' ) );
			$end   = date_i18n( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

			self::traffic()->whereBetween( 'created', [ $end, $start ] )->delete();
			self::analytics()->whereBetween( 'created', [ $end, $start ] )->delete();
		}
		self::purge_cache();

		return true;
	}

	/**
	 * Delete record for comparison.
	 */
	public static function delete_data_log() {
		$days = Helper::get_settings( 'general.console_caching_control', 90 );

		$start = date_i18n( 'Y-m-d H:i:s', strtotime( '-' . ( $days * 2 ) . ' days' ) );

		self::traffic()->where( 'created', '<', $start )->delete();
		self::analytics()->where( 'created', '<', $start )->delete();
	}

	/**
	 * Purge SC transient
	 */
	public static function purge_cache() {
		$table = Database::table( 'options' );
		$table->whereLike( 'option_name', 'rank_math_analytics_data_info' )->delete();
		$table->whereLike( 'option_name', 'tracked_keywords_summary' )->delete();
		$table->whereLike( 'option_name', 'top_keywords' )->delete();
		$table->whereLike( 'option_name', 'top_keywords_graph' )->delete();
		$table->whereLike( 'option_name', 'winning_keywords' )->delete();
		$table->whereLike( 'option_name', 'losing_keywords' )->delete();
		$table->whereLike( 'option_name', 'posts_summary' )->delete();
		$table->whereLike( 'option_name', 'winning_posts' )->delete();
		$table->whereLike( 'option_name', 'losing_posts' )->delete();

		wp_cache_flush();
	}

	/**
	 * Get search console table info (for pro version only).
	 *
	 * @return array
	 */
	public static function info() {
		if ( ! DB_Helper::check_table_exists( 'rank_math_analytics_ga' ) || ! DB_Helper::check_table_exists( 'rank_math_analytics_adsense' ) ) {
			return [
				'days' => 0,
				'rows' => 0,
				'size' => 0,
			];
		}

		global $wpdb;

		$key  = 'rank_math_analytics_data_info';
		$data = get_transient( $key );
		if ( false !== $data ) {
			return $data;
		}

		$days = 0;

		$rows = self::get_total_rows();

		$size = DB_Helper::get_var( 'SELECT SUM((data_length + index_length)) AS size FROM information_schema.TABLES WHERE table_schema="' . $wpdb->dbname . '" AND table_name IN ( ' . '"' . $wpdb->prefix . 'rank_math_analytics_ga", "' . $wpdb->prefix . 'rank_math_analytics_adsense"' . ' )' ); // phpcs:ignore

		$data = compact( 'days', 'rows', 'size' );

		set_transient( $key, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Get total row count of analytics and adsense tables
	 *
	 * @return int total row count
	 */
	public static function get_total_rows() {
		$rows = 0;

		if ( Analytics_Free::is_analytics_connected() ) {
			$rows += self::table( 'rank_math_analytics_ga' )
				->selectCount( 'id' )
				->getVar();
		}

		if ( Adsense::is_adsense_connected() ) {
			$rows += self::table( 'rank_math_analytics_adsense' )
				->selectCount( 'id' )
				->getVar();
		}

		return $rows;
	}

	/**
	 * Has data pulled.
	 *
	 * @return boolean
	 */
	public static function has_data() {
		static $rank_math_gsc_has_data;
		if ( isset( $rank_math_gsc_has_data ) ) {
			return $rank_math_gsc_has_data;
		}

		$id = self::objects()
			->select( 'id' )
			->limit( 1 )
			->getVar();

		$rank_math_gsc_has_data = $id > 0 ? true : false;
		return $rank_math_gsc_has_data;
	}

	/**
	 * Add a new record into objects table.
	 *
	 * @param array $args Values to insert.
	 *
	 * @return bool|int
	 */
	public static function add_object( $args = [] ) {
		if ( empty( $args ) ) {
			return false;
		}

		$args = wp_parse_args(
			$args,
			[
				'created'        => current_time( 'mysql' ),
				'page'           => '',
				'object_type'    => 'post',
				'object_subtype' => 'post',
				'object_id'      => 0,
				'primary_key'    => '',
				'seo_score'      => 0,
				'page_score'     => 0,
				'is_indexable'   => false,
				'schemas_in_use' => '',
			]
		);

		return self::objects()->insert( $args, [ '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s' ] );
	}

	/**
	 * Add/Update a record into/from objects table.
	 *
	 * @param array $args Values to update.
	 *
	 * @return bool|int
	 */
	public static function update_object( $args = [] ) {
		if ( empty( $args ) ) {
			return false;
		}

		// If object exists, try to update.
		$old_id = absint( $args['id'] );
		if ( ! empty( $old_id ) ) {
			unset( $args['id'] );

			$updated = self::objects()->set( $args )
				->where( 'id', $old_id )
				->where( 'object_id', absint( $args['object_id'] ) )
				->update();

			if ( ! empty( $updated ) ) {
				return $old_id;
			}
		}

		// In case of new object or failed to update, try to add.
		return self::add_object( $args );
	}

	/**
	 * Add analytic records.
	 *
	 * @param string $date Date of creation.
	 * @param array  $rows Data rows to insert.
	 */
	public static function add_analytics_bulk( $date, $rows ) {
		$chunks = array_chunk( $rows, 50 );

		foreach ( $chunks as $chunk ) {
			self::bulk_insert_analytics_data( $date . ' 00:00:00', $chunk );
		}
	}

	/**
	 * Bulk inserts records into a table using WPDB.  All rows must contain the same keys.
	 *
	 * @param  string $date        Date.
	 * @param  array  $rows        Rows to insert.
	 */
	public static function bulk_insert_analytics_data( $date, $rows ) {
		try {
			$rows = self::prepare_rows( $rows );
			$rows = self::ignore_non_exists_page_rows( $rows );

			// Aggregate rows by page + referrer to prevent duplicates.
			$grouped_rows = [];
			foreach ( $rows as $row ) {
				$key = $row['page'] . '|' . ( $row['referrer'] ?? 'null' );

				if ( ! isset( $grouped_rows[ $key ] ) ) {
					$grouped_rows[ $key ] = [
						'page'      => $row['page'],
						'referrer'  => $row['referrer'],
						'pageviews' => 0,
					];
				}

				$grouped_rows[ $key ]['pageviews'] += (int) $row['pageviews'];
			}

			global $wpdb;

			$pages = array_column( $grouped_rows, 'page' );
			$pages = array_unique( $pages );

			$records        = DB_Helper::get_results(
				$wpdb->prepare(
					"SELECT *
					FROM {$wpdb->prefix}rank_math_analytics_ga
					WHERE `created` = %s AND `page` IN ('" . implode( "','", array_map( 'esc_sql', $pages ) ) . "')",
					$date
				)
			);
			$update         = 0;
			$existing_pages = [];
			foreach ( $records as $exists ) {
				$existing_pages[ $exists->page ] = $exists;
			}

			if ( $existing_pages ) {
				$keys = array_keys( $existing_pages );

				foreach ( $grouped_rows as $index => $row ) {
					$page           = $row['page'];
					$created        = isset( $existing_pages[ $page ]->created ) ? $existing_pages[ $page ]->created : '';
					$referrer       = $row['referrer'];
					$exist_referrer = isset( $existing_pages[ $page ]->referrer ) ? $existing_pages[ $page ]->referrer : '';
					if (
						in_array( $page, $keys, true ) &&
						strpos( $date, $created ) !== false &&
						$referrer === $exist_referrer
					) {
						$pageviews = (int) $existing_pages[ $page ]->pageviews + (int) $row['pageviews'];
						$query     = $wpdb->prepare(
							"UPDATE `{$wpdb->prefix}rank_math_analytics_ga` SET `pageviews` = %d WHERE `id` = %d",
							$pageviews,
							$existing_pages[ $page ]->id
						);
						DB_Helper::query( $query );

						unset( $grouped_rows[ $index ] ); // Remove the processed row.
						++$update;
					}
				}
			}

			if ( empty( $grouped_rows ) ) {
				return [
					'insert' => 0,
					'update' => $update,
				];
			}

			$columns = [
				'created',
				'page',
				'pageviews',
				'referrer',
			];
			$columns = '`' . implode( '`, `', $columns ) . '`';

			$insert_data         = [];
			$insert_placeholders = [];

			$placeholder = [
				'%s',
				'%s',
				'%d',
				'%s',
			];

			// Start building SQL, initialise data and placeholder arrays.
			$insert_sql = "INSERT INTO `{$wpdb->prefix}rank_math_analytics_ga` ( $columns ) VALUES\n";

			// Build placeholders for each row, and add values to data array.
			foreach ( $grouped_rows as $row ) {
				$insert_data[] = $date; // created.
				$insert_data[] = $row['page'];
				$insert_data[] = $row['pageviews'];
				$insert_data[] = $row['referrer'];

				$insert_placeholders[] = '(' . implode( ', ', $placeholder ) . ')';
			}

			// Stitch all rows together.
			$insert_sql .= implode( ",\n", $insert_placeholders );

			// Run the query.  Returns number of affected rows.
			DB_Helper::query( $wpdb->prepare( $insert_sql, $insert_data ) );

			return [
				'insert' => count( $insert_placeholders ),
				'update' => $update,
			];

		} catch ( Exception $e ) {
			return [
				'insert' => 0,
				'update' => 0,
			];
		}
	}

	/**
	 * Prepare rows for insertion.
	 *
	 * @param array $data Raw data from Google Analytics.
	 *
	 * @return array
	 */
	public static function prepare_rows( $data ) {
		$rows = [];

		foreach ( $data as $row ) {
			$page = $row['pagePath'] ?? '';

			try {
				$page = AnalyticsDB::get_page( $page );
			} catch ( Exception $e ) {
				continue;
			}

			$referrer = self::get_referrer( $row['pageReferrer'] ?? '' );

			$rows[] = [
				'page'      => $page,
				'pageviews' => $row['screenPageViews'] ?? 0,
				'referrer'  => $referrer,
			];
		}

		return $rows;
	}

	/**
	 * Ignore rows
	 *
	 * @param array $data The rows to get.
	 */
	public static function ignore_non_exists_page_rows( $data ) {
		global $wpdb;

		$pages = wp_list_pluck( $data, 'page' );
		$pages = array_unique( $pages );

		$existing_pages = $wpdb->get_col( "SELECT `page` FROM `{$wpdb->prefix}rank_math_analytics_objects` WHERE `page` IN ('" . implode( "','", array_map( 'esc_sql', $pages ) ) . "')" );

		// Keep only pages that exist in the objects table.
		$data = array_filter(
			$data,
			function ( $row ) use ( $existing_pages ) {
				return in_array( $row['page'], $existing_pages, true );
			}
		);

		return $data;
	}

	/**
	 * Group referrer.
	 *
	 * @param string $url The URL to group.
	 */
	public static function get_referrer( $url ) {
		if ( empty( $url ) || $url === '""' || $url === 'value: ""' ) {
			return '';
		}

		$host = self::get_host( $url );

		$groups = [
			'gemini'     => [ 'gemini.google.com' ],
			'chatgpt'    => [ 'chatgpt.com', 'chat.openai.com' ],
			'copilot'    => [ 'copilot.microsoft.com' ],
			'perplexity' => [ 'perplexity.ai' ],
			'deepseek'   => [ 'chat.deepseek.com' ],
			'claude'     => [ 'claude.ai' ],
			'grok'       => [ 'grok.com' ],
			'meta'       => [ 'meta.ai' ],
			'mistral'    => [ 'mistral.ai' ],
		];

		foreach ( $groups as $group => $domains ) {
			foreach ( $domains as $pattern ) {
				if ( stripos( $host, $pattern ) !== false ) {
					return $group;
				}
			}
		}

		return '';
	}

	/**
	 * Extract host from URL.
	 *
	 * @param string $url The URL to extract the host from.
	 */
	public static function get_host( $url ) {
		// Remove scheme and www.
		$url = preg_replace( '#^https?://(www\.)?#i', '', $url );

		// Get string before first "/".
		$parts = explode( '/', $url );

		return strtolower( $parts[0] ); // Normalize to lowercase.
	}

	/**
	 * Add adsense records.
	 *
	 * @param array $rows Data rows to insert.
	 */
	public static function add_adsense( $rows ) {
		if ( ! \RankMath\Helpers\DB::check_table_exists( 'rank_math_analytics_adsense' ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$date     = $row['cells'][0]['value'];
			$earnings = floatval( $row['cells'][1]['value'] );

			self::adsense()
				->insert(
					[
						'created'  => $date . ' 00:00:00',
						'earnings' => $earnings,
					],
					[ '%s', '%f' ]
				);
		}
	}

	/**
	 * Get position filter.
	 *
	 * @return int
	 */
	private static function get_position_filter() {
		$number = apply_filters( 'rank_math/analytics/position_limit', false );
		if ( false === $number ) {
			return 100;
		}

		return $number;
	}

	/**
	 * Bulk inserts records into a keyword table using WPDB.  All rows must contain the same keys.
	 *
	 * @param array $rows Rows to insert.
	 */
	public static function bulk_insert_query_focus_keyword_data( $rows ) {
		$registered = Admin_Helper::get_registration_data();
		if ( ! $registered || empty( $registered['username'] ) || empty( $registered['api_key'] ) ) {
			return false;
		}

		// Check remain keywords count can be added.
		$total_keywords = Keywords::get()->get_tracked_keywords_count();
		$new_keywords   = Keywords::get()->extract_addable_track_keyword( implode( ',', $rows ) );
		$keywords_count = count( $new_keywords );
		if ( $keywords_count <= 0 ) {
			return false;
		}

		$summary = Keywords::get()->get_tracked_keywords_quota();
		$remain  = $summary['available'] - $total_keywords;
		if ( $remain <= 0 ) {
			return false;
		}

		// Add remaining limit keywords.
		$new_keywords = array_slice( $new_keywords, 0, $remain );

		$data         = [];
		$placeholders = [];
		$columns      = [
			'keyword',
			'collection',
			'is_active',
		];
		$columns      = '`' . implode( '`, `', $columns ) . '`';
		$placeholder  = [
			'%s',
			'%s',
			'%s',
		];

		// Start building SQL, initialise data and placeholder arrays.
		global $wpdb;
		$sql = "INSERT INTO `{$wpdb->prefix}rank_math_analytics_keyword_manager` ( $columns ) VALUES\n";

		// Build placeholders for each row, and add values to data array.
		foreach ( $new_keywords as $new_keyword ) {
			$data[]         = $new_keyword;
			$data[]         = 'uncategorized';
			$data[]         = 1;
			$placeholders[] = '(' . implode( ', ', $placeholder ) . ')';
		}

		// Stitch all rows together.
		$sql .= implode( ",\n", $placeholders );

		// Run the query.  Returns number of affected rows.
		$count = DB_Helper::query( $wpdb->prepare( $sql, $data ) );

		$total_keywords = Keywords::get()->get_tracked_keywords_count();
		$response       = \RankMathPro\Admin\Api::get()->keywords_info( $registered['username'], $registered['api_key'], $total_keywords );
		if ( $response ) {
			update_option( 'rank_math_keyword_quota', $response );
		}

		return $count;
	}

	/**
	 * Get stats from DB for "Presence on Google" widget:
	 * All unique coverage_state values and their counts.
	 */
	public static function get_presence_stats() {
		$results = self::inspections()
			->select(
				[
					'coverage_state',
					'COUNT(*)' => 'count',
				]
			)
			->groupBy( 'coverage_state' )
			->orderBy( 'count', 'DESC' )
			->get();

		$results = array_map(
			'absint',
			array_combine(
				array_column( $results, 'coverage_state' ),
				array_column( $results, 'count' )
			)
		);

		return $results;
	}

	/**
	 * Get stats from DB for "Top Statuses" widget.
	 */
	public static function get_status_stats() {
		$statuses = [
			'VERDICT_UNSPECIFIED',
			'PASS',
			'PARTIAL',
			'FAIL',
			'NEUTRAL',
		];

		// Get all unique index_verdict values and their counts.
		$index_statuses = self::inspections()
			->select(
				[
					'index_verdict',
					'COUNT(*)' => 'count',
				]
			)
			->groupBy( 'index_verdict' )
			->orderBy( 'count', 'DESC' )
			->get();

		$results = array_fill_keys( $statuses, 0 );
		foreach ( $index_statuses as $status ) {
			if ( empty( $status->index_verdict ) ) {
				continue;
			}

			$results[ $status->index_verdict ] += $status->count;
		}

		return $results;
	}

	/**
	 * Get stats from DB for "Top Statuses" widget.
	 *
	 * @param string $page Page URL.
	 */
	public static function get_index_verdict( $page ) {
		$verdict = self::inspections()
			->select()
			->where( 'page', '=', $page )
			->one();

		return (array) $verdict;
	}
}
