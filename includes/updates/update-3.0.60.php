<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * The Updates routine for version 3.0.60.
 *
 * @since      3.0.60
 * @package    RankMathPro
 * @subpackage RankMathPro\Updates
 * @author     Rank Math <support@rankmath.com>
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove Video schema from homepage if videos are not available in the content.
 */
function rank_math_pro_3_0_60_delete_video_schema() {
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		return;
	}

	$home_page_id = get_option( 'page_on_front' );
	if ( ! $home_page_id ) {
		return;
	}

	$homepage = get_post( $home_page_id );
	( new RankMathPro\Schema\Video\Parser( $homepage ) )->save( false );
}

rank_math_pro_3_0_60_delete_video_schema();
