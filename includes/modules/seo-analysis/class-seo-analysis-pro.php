<?php
/**
 * SEO Analyzer module - Pro features.
 *
 * @since      1.0
 * @package    RankMathPro
 * @subpackage RankMathPro\Admin
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\SEO_Analysis;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use RankMath\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * SEO_Analysis_Pro class.
 */
class SEO_Analysis_Pro {

	use Hooker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->action( 'admin_enqueue_scripts', 'enqueue' );

		new Competitor_Analysis();
	}

	/**
	 * Enqueue styles and scripts.
	 *
	 * @param string $hook Page hook name.
	 */
	public function enqueue( $hook ) {
		if ( 'rank-math_page_rank-math-seo-analysis' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'rank-math-pro-seo-analysis', RANK_MATH_PRO_URL . 'includes/modules/seo-analysis/assets/css/seo-analysis.css', [], RANK_MATH_PRO_VERSION );
		wp_enqueue_script( 'rank-math-pro-seo-analysis', RANK_MATH_PRO_URL . 'includes/modules/seo-analysis/assets/js/seo-analysis-pro.js', [ 'jquery', 'lodash', 'wp-element', 'rank-math-components' ], RANK_MATH_PRO_VERSION, true );
		wp_set_script_translations( 'rank-math-pro-seo-analysis', 'rank-math-pro', RANK_MATH_PRO_PATH . 'languages/' );

		$this->add_localized_data();
	}

	/**
	 * Add Localized data.
	 */
	private function add_localized_data() {
		$module   = Helper::get_module( 'seo-analysis' );
		$analyzer = $module->admin->analyzer;
		$results  = $analyzer->get_results_from_storage( 'rank_math_seo_analysis_competitor' );

		Helper::add_json( 'competitorResults', $results );
		Helper::add_json( 'competitorUrl', get_option( 'rank_math_seo_analysis_competitor_url', '' ) );
		Helper::add_json( 'printLogo', rank_math()->plugin_url() . 'assets/admin/img/logo.svg' );
		Helper::add_json( 'isWpRocketActive', is_plugin_active( 'wp-rocket/wp-rocket.php' ) );
	}
}
