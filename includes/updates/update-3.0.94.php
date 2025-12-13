<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * The Updates routine for version 3.0.94.
 *
 * @since      3.0.94
 * @package    RankMathPro
 * @subpackage RankMathPro\Updates
 * @author     Rank Math <support@rankmath.com>
 */

defined( 'ABSPATH' ) || exit;

use RankMath\Helper;

/**
 * Update the news sitemap exclude terms data format.
 */
function rank_math_pro_3_0_94_update_news_sitemap_exclude_terms_format() {
	if ( Helper::is_module_active( 'news-sitemap' ) ) {
		\RankMathPro\Sitemap\News_Sitemap\Admin::format_news_sitemap_exclude_terms( 'react-settings', 'on' );
	}
}

rank_math_pro_3_0_94_update_news_sitemap_exclude_terms_format();
