<?php
/**
 * SEO Analyzer module - Competitor Analyzer feature.
 *
 * @since      1.0
 * @package    RankMathPro
 * @subpackage RankMathPro\Admin
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\SEO_Analysis;

use RankMath\Traits\Ajax;
use RankMath\Traits\Hooker;
use RankMath\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * SEO_Analysis_Pro class.
 *
 * @codeCoverageIgnore
 */
class Competitor_Analysis {

	use Hooker;
	use Ajax;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->filter( 'rank_math/analysis/is_allowed_url', 'allow_competitor_urls', 10, 2 );
		$this->filter( 'rank_math/seo_analysis/api_endpoint', 'set_ca_param' );
		$this->action( 'rank_math/seo_analysis/after_analyze', 'store_results' );
		$this->action( 'rank_math/seo_analysis/after_set_url', 'load_previous_results' );
		$this->action( 'rank_math/tools/clear_seo_analysis', 'clear_competitor_results', 5 );
	}

	/**
	 * ALlow competitor URLs to be analyzed with the SEO Analyzer.
	 *
	 * @return bool
	 */
	public function allow_competitor_urls() {
		return true;
	}

	/**
	 * Add the ca parameter to the API URL when appropriate.
	 *
	 * @param string $url API URL.
	 *
	 * @return string
	 */
	public function set_ca_param( $url ) {
		return ! Param::request( 'competitor_analyzer' ) ? $url : add_query_arg( 'ca', '1', $url );
	}

	/**
	 * Store the results of a competitor analysis.
	 *
	 * @param object $seo_analyzer SEO Analyzer object.
	 */
	public function store_results( $seo_analyzer ) {
		if ( ! Param::request( 'competitor_analyzer' ) ) {
			return;
		}

		update_option( 'rank_math_seo_analysis_competitor_results', $seo_analyzer->results, false );
		update_option( 'rank_math_seo_analysis_competitor_url', $seo_analyzer->analyse_url, false );
		update_option( 'rank_math_seo_analysis_competitor_date', time(), false );
	}

	/**
	 * Load the previous results of a competitor analysis.
	 *
	 * @param object $seo_analyzer SEO Analyzer object.
	 */
	public function load_previous_results( $seo_analyzer ) {
		return 'competitor_analyzer' !== Param::get( 'view' ) ? null : $seo_analyzer->get_results_from_storage( 'rank_math_seo_analysis_competitor' );
	}

	/**
	 * Clear the competitor analysis results.
	 */
	public function clear_competitor_results() {
		delete_option( 'rank_math_seo_analysis_competitor_results' );
		delete_option( 'rank_math_seo_analysis_competitor_url' );
		delete_option( 'rank_math_seo_analysis_competitor_date' );
	}
}
