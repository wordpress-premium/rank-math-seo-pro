<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * The Updates routine for version 3.0.97
 *
 * @since      3.0.97
 * @package    RankMathPro
 * @subpackage RankMathPro\Updates
 * @author     Rank Math <support@rankmath.com>
 */

defined( 'ABSPATH' ) || exit;

use RankMath\Helpers\DB as DB_Helper;

/**
 * Update code needed to add referrer column to Google Analytics table.
 */
function rank_math_pro_3_0_97_google_analytics_add_referrer() {
	if ( ! DB_Helper::check_table_exists( 'rank_math_analytics_ga' ) ) {
		return;
	}

	global $wpdb;

	DB_Helper::query( "ALTER TABLE `{$wpdb->prefix}rank_math_analytics_ga` ADD `referrer` varchar(500) NOT NULL" );
}

rank_math_pro_3_0_97_google_analytics_add_referrer();
