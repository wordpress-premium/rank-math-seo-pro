<?php
/**
 * Setup wizard.
 *
 * @since      1.0
 * @package    RankMathPro
 * @subpackage RankMathPro\Admin
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro;

use RankMath\Helper;
use RankMath\Helpers\Param;
use RankMath\Traits\Ajax;
use RankMath\Traits\Hooker;
use RankMath\Admin\Admin_Helper;
use RankMathPro\Admin\Admin_Helper as ProAdminHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Trends tool class.
 *
 * @codeCoverageIgnore
 */
class Setup_Wizard {

	use Hooker;
	use Ajax;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		$this->action( 'admin_init', 'enqueue', 20 );

		$this->filter( 'rank_math/setup_wizard/sitemaps/localized_data', 'add_sitemap_localized_data' );
		$this->action( 'rank_math/setup_wizard/sitemaps/save_data', 'save_sitemap_data' );
		$this->filter( 'rank_math/setup_wizard/analytics/localized_data', 'add_analytics_localized_data' );
		$this->filter( 'rank_math/admin/options/general_data', 'add_analytics_localized_data' );
		$this->action( 'rank_math/setup_wizard/analytics/save_data', 'save_analytics_data' );

		$this->ajax( 'import_settings', 'ajax_import_settings' );
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( Param::get( 'page' ) !== 'rank-math-wizard' ) {
			return;
		}

		wp_enqueue_style(
			'rank-math-pro-setup-wizard',
			RANK_MATH_PRO_URL . 'assets/admin/css/setup-wizard.css',
			[],
			rank_math_pro()->version
		);
		wp_enqueue_script(
			'rank-math-pro-setup-wizard',
			RANK_MATH_PRO_URL . 'assets/admin/js/setup-wizard.js',
			[ 'jquery', 'wp-element', 'lodash', 'rank-math-components' ],
			rank_math_pro()->version,
			true
		);
	}

	/**
	 * Ajax import settings.
	 */
	public function ajax_import_settings() {
		$this->verify_nonce( 'rank-math-ajax-nonce' );
		$this->has_cap_ajax( 'general' );

		$file = $this->has_valid_import_file();
		if ( false === $file ) {
			return false;
		}

		// Parse Options.
		$wp_filesystem = Helper::get_filesystem();
		if ( is_null( $wp_filesystem ) ) {
			return false;
		}

		$settings = $wp_filesystem->get_contents( $file['file'] );
		$settings = json_decode( $settings, true );

		\wp_delete_file( $file['file'] );

		if ( is_array( $settings ) && $this->do_import_data( $settings ) ) {
			$this->success( __( 'Import successful.', 'rank-math-pro' ) );
			exit();
		}

		$this->error( __( 'No settings found to be imported.', 'rank-math-pro' ) );
		exit();
	}

	/**
	 * Add Localized data to be used in the Sitemap step.
	 *
	 * @param array $data Localized data.
	 *
	 * @return array
	 */
	public function add_sitemap_localized_data( $data ) {
		return array_merge(
			$data,
			[
				'news-sitemap'                  => Helper::is_module_active( 'news-sitemap' ),
				'news_sitemap_publication_name' => Helper::get_settings( 'sitemap.news_sitemap_publication_name', '' ),
				'news_sitemap_post_type'        => Helper::get_settings( 'sitemap.news_sitemap_post_type', [] ),
				'video-sitemap'                 => Helper::is_module_active( 'video-sitemap' ),
				'video_sitemap_post_type'       => Helper::get_settings( 'sitemap.video_sitemap_post_type', array_keys( $data['postTypes'] ) ),

			]
		);
	}

	/**
	 * Save Sitemap data.
	 *
	 * @param array $values Values to update.
	 */
	public function save_sitemap_data( $values ) {
		$settings      = rank_math()->settings->all_raw();
		$news_sitemap  = $values['news-sitemap'];
		$video_sitemap = $values['video-sitemap'];

		Helper::update_modules( [ 'news-sitemap' => $news_sitemap ? 'on' : 'off' ] );
		Helper::update_modules( [ 'video-sitemap' => $video_sitemap ? 'on' : 'off' ] );

		if ( $news_sitemap ) {
			$settings['sitemap']['news_sitemap_publication_name'] = ! empty( $values['news_sitemap_publication_name'] ) ? sanitize_text_field( $values['news_sitemap_publication_name'] ) : '';
			$settings['sitemap']['news_sitemap_post_type']        = ! empty( $values['news_sitemap_post_type'] ) ? $values['news_sitemap_post_type'] : [];

			Helper::update_all_settings( null, null, $settings['sitemap'] );
		}

		if ( $video_sitemap ) {
			$settings['sitemap']['video_sitemap_post_type'] = ! empty( $values['video_sitemap_post_type'] ) ? $values['video_sitemap_post_type'] : [];

			Helper::update_all_settings( null, null, $settings['sitemap'] );
		}
	}

	/**
	 * Add Localized data to be used in the Sitemap step.
	 *
	 * @param array $data Localized data.
	 *
	 * @return array
	 */
	public function add_analytics_localized_data( $data ) {
		$registered = Admin_Helper::get_registration_data();

		return array_merge(
			$data,
			[
				'countriesChoices3'     => ProAdminHelper::choices_countries_3(),
				'countriesChoices'      => ProAdminHelper::choices_countries(),
				'console_email_send_to' => Helper::get_settings( 'general.console_email_send_to', $registered['email'] ?? '' ),
				'isBusinessPlan'        => ProAdminHelper::is_business_plan(),
			]
		);
	}

	/**
	 * Save Sitemap data.
	 *
	 * @param array $values Values to update.
	 */
	public function save_analytics_data( $values ) {
		$settings = rank_math()->settings->all_raw();

		$settings['general']['console_email_send_to'] = sanitize_text_field( $values['console_email_send_to'] );
		Helper::update_all_settings( $settings['general'], null, null );
	}

	/**
	 * Import has valid file.
	 *
	 * @return mixed
	 */
	private function has_valid_import_file() {
		if ( empty( $_FILES['import-me'] ) ) {  // phpcs:ignore WordPress.Security -- Verficiation is handled in the Rest function.
			$this->error( __( 'No file selected.', 'rank-math-pro' ) );
			return false;
		}

		$file = Helper::handle_file_upload();
		if ( is_wp_error( $file ) ) {
			$this->error( __( 'Settings file could not be imported:', 'rank-math-pro' ) . ' ' . $file->get_error_message() );
			return false;
		}

		if ( isset( $file['error'] ) ) {
			$this->error( __( 'Settings could not be imported:', 'rank-math-pro' ) . ' ' . $file['error'] );
			return false;
		}

		if ( ! isset( $file['file'] ) ) {
			$this->error( __( 'Settings could not be imported: Upload failed.', 'rank-math-pro' ) );
			return false;
		}

		return $file;
	}

	/**
	 * Does import data.
	 *
	 * @param  array $data           Import data.
	 * @param  bool  $suppress_hooks Suppress hooks or not.
	 * @return bool
	 */
	private function do_import_data( array $data, $suppress_hooks = false ) {
		$this->run_import_hooks( 'pre_import', $data, $suppress_hooks );

		// Import options.
		$down = $this->import_set_options( $data );

		// Import capabilities.
		if ( ! empty( $data['role-manager'] ) ) {
			$down = true;
			Helper::set_capabilities( $data['role-manager'] );
		}

		// Import redirections.
		if ( ! empty( $data['redirections'] ) ) {
			$down = true;
			$this->import_set_redirections( $data['redirections'] );
		}

		$this->run_import_hooks( 'after_import', $data, $suppress_hooks );

		return $down;
	}

	/**
	 * Set options from data.
	 *
	 * @param array $data An array of data.
	 */
	private function import_set_options( $data ) {
		$set  = false;
		$hash = [
			'modules' => 'rank_math_modules',
			'general' => 'rank-math-options-general',
			'titles'  => 'rank-math-options-titles',
			'sitemap' => 'rank-math-options-sitemap',
		];

		foreach ( $hash as $key => $option_key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$set = true;
				update_option( $option_key, $data[ $key ] );
			}
		}

		return $set;
	}

	/**
	 * Set redirections.
	 *
	 * @param array $redirections An array of redirections to import.
	 */
	private function import_set_redirections( $redirections ) {
		foreach ( $redirections as $key => $redirection ) {
			$matched = \RankMath\Redirections\DB::match_redirections_source( $redirection['sources'] );
			if ( ! empty( $matched ) ) {
				continue;
			}

			$sources = maybe_unserialize( $redirection['sources'] );
			if ( ! is_array( $sources ) ) {
				continue;
			}

			\RankMath\Redirections\DB::add(
				[
					'url_to'      => $redirection['url_to'],
					'sources'     => $sources,
					'header_code' => $redirection['header_code'],
					'hits'        => $redirection['hits'],
					'created'     => $redirection['created'],
					'updated'     => $redirection['updated'],
				]
			);
		}
	}

	/**
	 * Run import hooks
	 *
	 * @param string $hook     Hook to fire.
	 * @param array  $data     Import data.
	 * @param bool   $suppress Suppress hooks or not.
	 */
	private function run_import_hooks( $hook, $data, $suppress ) {
		if ( ! $suppress ) {
			/**
			 * Fires while importing settings.
			 *
			 * @since 0.9.0
			 *
			 * @param array $data Import data.
			 */
			$this->do_action( 'import/settings/' . $hook, $data );
		}
	}
}
