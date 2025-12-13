<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    RankMath
 * @subpackage RankMathPro\Admin
 * @author     RankMath <support@rankmath.com>
 */

namespace RankMathPro\Admin;

use RankMathPro\Updates;
use RankMath\Helper;
use RankMath\Helpers\Param;
use RankMath\Traits\Hooker;
use RankMathPro\Google\Adsense;
use RankMathPro\Setup_Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 *
 * @codeCoverageIgnore
 */
class Admin {

	use Hooker;

	/**
	 * Stores object instances.
	 *
	 * @var array
	 */
	public $components = [];

	/**
	 * Register hooks.
	 */
	public function __construct() {
		$this->action( 'init', 'init_components' );
		$this->filter( 'rank_math/admin/options/general_data', 'add_general_json_data' );
		add_filter( 'rank_math/analytics/classic/pro_notice', '__return_empty_string' );
		$this->filter( 'rank_math/settings/sitemap', 'special_seprator' );
		$this->action( 'admin_enqueue_scripts', 'enqueue' );
		$this->filter( 'wp_helpers_notifications_render', 'prevent_pro_notice', 10, 3 );
		$this->action( 'rank_math/admin/settings/others', 'add_search_intent_setting' );

		new Updates();
	}

	/**
	 * Initialize the required components.
	 */
	public function init_components() {
		$components = [
			'bulk_actions'       => 'RankMathPro\\Admin\\Bulk_Actions',
			'post_filters'       => 'RankMathPro\\Admin\\Post_Filters',
			'media_filters'      => 'RankMathPro\\Admin\\Media_Filters',
			'quick_edit'         => 'RankMathPro\\Admin\\Quick_Edit',
			'trends_tool'        => 'RankMathPro\\Admin\\Trends_Tool',
			'links'              => 'RankMathPro\\Admin\\Links',
			'misc'               => 'RankMathPro\\Admin\\Misc',
			'csv_import'         => 'RankMathPro\\Admin\\CSV_Import_Export\\CSV_Import_Export',
			'licence_activation' => 'RankMathPro\\Admin\\Licence_Activation',
		];

		if ( Helper::is_amp_active() ) {
			$components['amp'] = 'RankMathPro\\Admin\\Amp';
		}

		$components = apply_filters( 'rank_math/admin/pro_components', $components );
		foreach ( $components as $name => $component ) {
			$this->components[ $name ] = new $component();
		}
	}

	/**
	 * Add localized data to use on the General Setttings.
	 *
	 * @param array $data Localized data.
	 *
	 * @return array
	 */
	public function add_general_json_data( $data ) {
		$data['canActivateImagify'] = Admin_Helper::can_activate_imagify();
		return $data;
	}

	/**
	 * Add Special seprator into sitemap option panel
	 *
	 * @param array $tabs Hold tabs for optional panel.
	 *
	 * @return array
	 */
	public function special_seprator( $tabs ) {
		if ( Helper::is_module_active( 'news-sitemap' ) || Helper::is_module_active( 'video-sitemap' ) || Helper::is_module_active( 'local-seo' ) ) {
			$tabs['special'] = [
				'title' => esc_html__( 'Special Sitemaps:', 'rank-math-pro' ),
				'type'  => 'seprator',
			];
		}

		return $tabs;
	}

	/**
	 * Add new settings.
	 *
	 * @param object $cmb CMB2 instance.
	 */
	public function add_search_intent_setting( $cmb ) {
		$field_ids      = wp_list_pluck( $cmb->prop( 'fields' ), 'id' );
		$field_position = array_search( 'rss_after_content', array_keys( $field_ids ), true ) + 1;

		$cmb->add_field(
			[
				'id'      => 'determine_search_intent',
				'type'    => 'toggle',
				'name'    => esc_html__( 'Enable Search Intent', 'rank-math-pro' ),
				// Translators: placeholder is a link to "Read more".
				'desc'    => sprintf( esc_html__( 'Determine the Keyword\'s Search Intent for Writing Tailored Content. %s', 'rank-math-pro' ), '<a href="https://rankmath.com/kb/search-intent-analysis/?utm_source=Plugin&utm_medium=Others%20Tab%20KB%20Link&utm_campaign=WP" target="_blank">' . esc_html__( 'Read more', 'rank-math-pro' ) . '</a>' ),
				'default' => 'on',
			],
			++$field_position
		);
	}

	/**
	 * Load setup wizard.
	 */
	private function load_setup_wizard() {
		if ( Helper::is_wizard() ) {
			new Setup_Wizard();
		}
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! in_array(
			Param::get( 'page' ),
			[
				'rank-math-options-general',
				'rank-math-options-titles',
				'rank-math-options-sitemap',
				'rank-math-options-instant-indexing',
			],
			true
		) ) {
			return;
		}

		wp_enqueue_style(
			'rank-math-pro-general-options',
			RANK_MATH_PRO_URL . 'assets/admin/css/general-options.css',
			[ 'wp-components' ],
			rank_math_pro()->version
		);

		$settings_file = Helper::is_react_enabled() ? 'settings' : 'general-options';

		wp_enqueue_script( 'rank-math-pro-general-options', RANK_MATH_PRO_URL . "assets/admin/js/{$settings_file}.js", [ 'wp-hooks' , 'lodash', 'jquery', 'wp-i18n', 'wp-api-fetch', 'wp-data', 'rank-math-components' ], rank_math_pro()->version, true );

		Helper::add_json( 'isAdsenseConnected', Adsense::is_adsense_connected() );
		Helper::add_json( 'choicesPhoneTypes', Helper::choices_phone_types() );
		Helper::add_json( 'choicesAdditionalOrganizationInfo', Helper::choices_additional_organization_info() );
		Helper::add_json( 'choicesBusinessTypes', Helper::choices_business_types( true ) );
	}

	/**
	 * Make sure that our "Upgrade to Pro" admin notice is not showing when the
	 * Pro version is active.
	 *
	 * @param string $output  Notice HTML output.
	 * @param string $message Notice message text.
	 * @param array  $options Notice options.
	 *
	 * @return string
	 */
	public function prevent_pro_notice( $output, $message, $options ) {
		if ( 'rank_math_pro_notice' !== $options['id'] ) {
			return $output;
		}

		return '';
	}
}
