<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * The Updates routine for version 3.0.81.
 *
 * @since      3.0.81
 * @package    RankMathPro
 * @subpackage RankMathPro\Updates
 * @author     Rank Math <support@rankmath.com>
 */

defined( 'ABSPATH' ) || exit;

/**
 * Update the old "Serbia and Montenegro" country code with "Serbia".
 */
function rank_math_pro_3_0_81_update_country_code() {
	$analytics = get_option( 'rank_math_google_analytic_options' );

	if ( ! isset( $analytics['country'] ) || 'CS' !== $analytics['country'] ) {
		return;
	}

	$analytics['country'] = 'RS';

	update_option( 'rank_math_google_analytic_options', $analytics );
}

rank_math_pro_3_0_81_update_country_code();
