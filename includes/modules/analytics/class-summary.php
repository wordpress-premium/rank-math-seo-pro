<?php
/**
 * The Analytics Module
 *
 * @since      2.0.0
 * @package    RankMathPro
 * @subpackage RankMathPro\modules
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Analytics;

use RankMath\Helper;
use RankMath\Helpers\DB as DB_Helper;
use RankMath\Traits\Hooker;
use RankMath\Analytics\Stats;
use RankMath\Admin\Admin_Helper;
use RankMathPro\Admin\Admin_Helper as ProAdminHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Summary class.
 */
class Summary {

	use Hooker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( \RankMathPro\Google\Adsense::is_adsense_connected() ) {
			$this->filter( 'rank_math/analytics/summary', 'get_adsense_summary' );
		}
		if ( \RankMath\Google\Analytics::is_analytics_connected() ) {
			$this->filter( 'rank_math/analytics/summary', 'get_pageviews_summary' );
			$this->filter( 'rank_math/analytics/get_widget', 'get_pageviews_summary' );
		}
		$this->filter( 'rank_math/analytics/summary', 'get_clicks_summary' );
		$this->filter( 'rank_math/analytics/summary', 'get_g_update_summary' );
		$this->filter( 'rank_math/analytics/posts_summary', 'get_posts_summary', 10, 3 );
		$this->filter( 'rank_math/analytics/analytics_summary_graph', 'get_analytics_summary_graph', 10, 2 );
		$this->filter( 'rank_math/analytics/analytics_tables_info', 'get_analytics_tables_info' );
	}

	/**
	 * Get posts summary.
	 *
	 * @param object $summary   Posts summary.
	 * @param string $post_type Post type.
	 * @param string $query     Query to get the summary data.
	 * @return object
	 */
	public function get_posts_summary( $summary, $post_type, $query ) {
		if ( empty( $summary ) ) {
			return $summary;
		}

		if ( $post_type && is_string( $post_type ) ) {
			global $wpdb;
			$query->leftJoin( $wpdb->prefix . 'rank_math_analytics_objects', $wpdb->prefix . 'rank_math_analytics_gsc.page', $wpdb->prefix . 'rank_math_analytics_objects.page' );
			$query->where( $wpdb->prefix . 'rank_math_analytics_objects.object_subtype', sanitize_key( $post_type ) );
			$summary = (object) $query->one();
		}

		$summary->pageviews = 0;
		if ( ! \RankMath\Google\Analytics::is_analytics_connected() ) {
			return $summary;
		}

		$ai_only = ProAdminHelper::ai_traffic_enabled();

		$pageviews = DB::traffic()
			->selectSum( 'pageviews', 'pageviews' )
			->whereBetween( 'created', [ Stats::get()->start_date, Stats::get()->end_date ] );
		if ( $ai_only ) {
			$pageviews->where( 'referrer', '!=', '' );
		}
		$pageviews = $pageviews->getVar();

		$summary->pageviews = $pageviews;

		return $summary;
	}

	/**
	 * Get pageviews summary.
	 *
	 * @param  object $stats Stats holder.
	 * @return object
	 */
	public function get_pageviews_summary( $stats ) {
		$ai_only = ProAdminHelper::ai_traffic_enabled();

		$pageviews = DB::traffic()
			->selectSum( 'pageviews', 'pageviews' )
			->whereBetween( 'created', [ Stats::get()->start_date, Stats::get()->end_date ] );

		if ( $ai_only ) {
			$pageviews->where( 'referrer', '!=', '' );
		}
		$pageviews = $pageviews->getVar();

		$old_pageviews = DB::traffic()
			->selectSum( 'pageviews', 'pageviews' )
			->whereBetween( 'created', [ Stats::get()->compare_start_date, Stats::get()->compare_end_date ] );
		if ( $ai_only ) {
			$old_pageviews->where( 'referrer', '!=', '' );
		}
		$old_pageviews = $old_pageviews->getVar();

		$stats->pageviews = [
			'total'      => is_null( $pageviews ) ? 'n/a' : (int) $pageviews,
			'previous'   => is_null( $old_pageviews ) ? 'n/a' : (int) $old_pageviews,
			'difference' => is_null( $pageviews ) || is_null( $old_pageviews ) ? 'n/a' : (int) $pageviews - (int) $old_pageviews,
		];

		return $stats;
	}

	/**
	 * Get adsense summary.
	 *
	 * @param  object $stats Stats holder.
	 * @return object
	 */
	public function get_adsense_summary( $stats ) {
		$stats->adsense = [
			'total'      => 'n/a',
			'previous'   => 'n/a',
			'difference' => 'n/a',
		];

		if ( DB_Helper::check_table_exists( 'rank_math_analytics_adsense' ) ) {
			$earnings = DB::adsense()
				->selectSum( 'earnings', 'earnings' )
				->whereBetween( 'created', [ Stats::get()->start_date, Stats::get()->end_date ] )
				->getVar();

			$old_earnings = DB::adsense()
				->selectSum( 'earnings', 'earnings' )
				->whereBetween( 'created', [ Stats::get()->compare_start_date, Stats::get()->compare_end_date ] )
				->getVar();

			$stats->adsense = [
				'total'      => is_null( $earnings ) ? 'n/a' : (int) $earnings,
				'previous'   => is_null( $old_earnings ) ? 'n/a' : (int) $old_earnings,
				'difference' => is_null( $earnings ) || is_null( $old_earnings ) ? 'n/a' : (int) $earnings - (int) $old_earnings,
			];
		}

		return $stats;
	}

	/**
	 * Get analytics and adsense graph data.
	 *
	 * @param  object $data      Graph data.
	 * @param  array  $intervals Date intervals.
	 * @return array
	 */
	public function get_analytics_summary_graph( $data, $intervals ) {
		global $wpdb;

		if ( \RankMath\Google\Analytics::is_analytics_connected() ) {
			$data->traffic = $this->get_traffic_graph( $intervals );

			// Convert types.
			$data->traffic = array_map( [ Stats::get(), 'normalize_graph_rows' ], $data->traffic );

			// Merge for performance.
			$data->merged = Stats::get()->get_merge_data_graph( $data->traffic, $data->merged, $intervals['map'] );
		}

		if ( \RankMathPro\Google\Adsense::is_adsense_connected() ) {
			$data->adsense = $this->get_adsense_graph( $intervals );

			// Convert types.
			$data->adsense = array_map( [ Stats::get(), 'normalize_graph_rows' ], $data->adsense );

			// Merge for performance.
			$data->merged = Stats::get()->get_merge_data_graph( $data->adsense, $data->merged, $intervals['map'] );
		}

		return $data;
	}

	/**
	 * Get analytics graph data.
	 *
	 * @param  array $intervals Date intervals.
	 * @return array
	 */
	public function get_traffic_graph( $intervals ) {
		global $wpdb;

		$ai_only = ProAdminHelper::ai_traffic_enabled();

		$sql_daterange = Stats::get()->get_sql_date_intervals( $intervals );
		$where         = $ai_only ? " AND `referrer` != ''" : '';

		$query        = $wpdb->prepare(
			"SELECT DATE_FORMAT( created, '%%Y-%%m-%%d') as date, SUM(pageviews) as pageviews, {$sql_daterange}
			FROM {$wpdb->prefix}rank_math_analytics_ga
			WHERE created BETWEEN %s AND %s {$where}
			GROUP BY range_group",
			Stats::get()->start_date,
			Stats::get()->end_date
		);
		$traffic_data = DB_Helper::get_results( $query );
		// phpcs:enable

		return $traffic_data;
	}

	/**
	 * Get adsense graph data.
	 *
	 * @param  array $intervals Date intervals.
	 * @return array
	 */
	public function get_adsense_graph( $intervals ) {
		global $wpdb;

		$adsense_data = [];

		if ( DB_Helper::check_table_exists( 'rank_math_analytics_adsense' ) ) {
			$sql_daterange = Stats::get()->get_sql_date_intervals( $intervals );

			$query        = $wpdb->prepare(
				"SELECT DATE_FORMAT( created, '%%Y-%%m-%%d') as date, SUM(earnings) as earnings, {$sql_daterange}
				FROM {$wpdb->prefix}rank_math_analytics_adsense
				WHERE created BETWEEN %s AND %s
				GROUP BY range_group",
				Stats::get()->start_date,
				Stats::get()->end_date
			);
			$adsense_data = DB_Helper::get_results( $query );
			// phpcs:enable
		}

		return $adsense_data;
	}

	/**
	 * Get clicks summary.
	 *
	 * @param  object $stats Stats holder.
	 * @return object
	 */
	public function get_clicks_summary( $stats ) {
		$clicks = DB::analytics()
			->selectSum( 'clicks', 'clicks' )
			->whereBetween( 'created', [ Stats::get()->start_date, Stats::get()->end_date ] )
			->getVar();

		$old_clicks = DB::analytics()
			->selectSum( 'clicks', 'clicks' )
			->whereBetween( 'created', [ Stats::get()->compare_start_date, Stats::get()->compare_end_date ] )
			->getVar();

		$stats->clicks = [
			'total'      => is_null( $clicks ) ? 'n/a' : (int) $clicks,
			'previous'   => is_null( $old_clicks ) ? 'n/a' : (int) $old_clicks,
			'difference' => is_null( $clicks ) || is_null( $old_clicks ) ? 'n/a' : (int) $clicks - (int) $old_clicks,
		];

		return $stats;
	}

	/**
	 * Get google update summary.
	 *
	 * @param  object $stats Stats holder.
	 * @return object
	 */
	public function get_g_update_summary( $stats ) {
		if ( ! Helper::get_settings( 'general.google_updates' ) && ProAdminHelper::is_business_plan() ) {
			$stats->graph->g_updates = null;

			return $stats;
		}

		$stored                  = get_site_option( 'rank_math_pro_google_updates' );
		$g_updates               = json_decode( $stored );
		$stats->graph->g_updates = $g_updates;

		return $stats;
	}

	/**
	 * Get analytics tables info
	 *
	 * @param  array $data      Analytics tables info.
	 * @return array
	 */
	public function get_analytics_tables_info( $data ) {
		$pro_data = DB::info();

		$days = $data['days'] + $pro_data['days'];
		$rows = $data['rows'] + $pro_data['rows'];
		$size = $data['size'] + $pro_data['size'];

		$data = compact( 'days', 'rows', 'size' );

		return $data;
	}
}
