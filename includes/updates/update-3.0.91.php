<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * The Updates routine for version 3.0.91.
 *
 * @since      3.0.91
 * @package    RankMathPro
 * @subpackage RankMathPro\Updates
 * @author     Rank Math <support@rankmath.com>
 */

defined( 'ABSPATH' ) || exit;

use RankMath\Helper;

/**
 * Update the schedule frequency.
 */
function rank_math_pro_3_0_91_update_schedule_frequency() {
	Helper::schedule_data_fetch( 1 );
}

rank_math_pro_3_0_91_update_schedule_frequency();
