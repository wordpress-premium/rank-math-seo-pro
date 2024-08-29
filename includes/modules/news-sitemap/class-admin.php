<?php
/**
 * The News Sitemap Admin.
 *
 * @since      3.0.57
 * @package    RankMath
 * @subpackage RankMathPro
 * @author     RankMath <support@rankmath.com>
 */

namespace RankMathPro\Sitemap\News_Sitemap;

use RankMath\Helper;
use RankMath\KB;
use RankMath\Helpers\Param;
use RankMath\Traits\Hooker;
use RankMath\Admin\Admin_Helper;
use RankMath\Sitemap\Router;
use RankMath\Sitemap\Cache_Watcher;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 */
class Admin {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->action( 'rest_api_init', 'init_rest_api' );
		$this->action( 'transition_post_status', 'status_transition', 10, 3 );

		if ( ! Helper::has_cap( 'sitemap' ) ) {
			return;
		}

		$this->action( 'save_post', 'save_post' );
		$this->action( 'rank_math/admin/editor_scripts', 'enqueue_news_sitemap', 11 );
		$this->filter( 'rank_math/metabox/post/values', 'add_metadata', 10, 2 );

		$this->filter( 'rank_math/settings/sitemap', 'add_settings', 11 );
		$this->action( 'admin_enqueue_scripts', 'enqueue_settings_scripts' );
	}

	/**
	 * Load the REST API endpoints.
	 */
	public function init_rest_api() {
		$rest = new Rest();
	}

	/**
	 * Enqueue scripts for the metabox.
	 */
	public function enqueue_news_sitemap() {
		if ( ! $this->can_add_tab() ) {
			return;
		}

		wp_enqueue_script(
			'rank-math-pro-news',
			RANK_MATH_PRO_URL . 'includes/modules/news-sitemap/assets/js/news-sitemap.js',
			[ 'rank-math-pro-editor' ],
			rank_math_pro()->version,
			true
		);
	}

	/**
	 * Add meta data to use in gutenberg.
	 *
	 * @param array  $values Aray of tabs.
	 * @param Screen $screen Sceen object.
	 *
	 * @return array
	 */
	public function add_metadata( $values, $screen ) {
		$robots                = get_post_meta( $screen->get_object_id(), 'rank_math_news_sitemap_robots', true );
		$values['newsSitemap'] = [
			'robots' => $robots ? $robots : 'index',
		];

		return $values;
	}

	/**
	 * Clear News Sitemap cache when a post is published.
	 *
	 * @param int $post_id Post ID to possibly invalidate for.
	 */
	public function save_post( $post_id ) {
		if (
			wp_is_post_revision( $post_id ) ||
			! $this->can_add_tab( get_post_type( $post_id ) ) ||
			false === Helper::is_post_indexable( $post_id )
		) {
			return false;
		}

		Cache_Watcher::invalidate( 'news' );
	}

	/**
	 * Clear News Sitemap cache when a scheduled post is published.
	 *
	 * @param string $new_status New Status.
	 * @param string $old_status Old Status.
	 * @param object $post       Post Object.
	 */
	public function status_transition( $new_status, $old_status, $post ) {
		if ( $old_status === $new_status || 'publish' !== $new_status ) {
			return;
		}

		$this->save_post( $post->ID );
	}

	/**
	 * Add module settings into general optional panel.
	 *
	 * @param array $tabs Array of option panel tabs.
	 *
	 * @return array
	 */
	public function add_settings( $tabs ) {
		$sitemap_slug         = Router::get_sitemap_slug( 'news' );
		$sitemap_url          = Router::get_base_url( "{$sitemap_slug}-sitemap.xml" );
		$tabs['news-sitemap'] = [
			'icon'      => 'fa fa-newspaper-o',
			'title'     => esc_html__( 'News Sitemap', 'rank-math-pro' ),
			'icon'      => 'rm-icon rm-icon-post',
			'desc'      => wp_kses_post(
				/* translators: News Sitemap KB link */
				sprintf( __( 'News Sitemaps allow you to control which content you submit to Google News. More information: <a href="%s" target="_blank">News Sitemaps overview</a>', 'rank-math-pro' ), KB::get( 'news-sitemap', 'Options Panel Sitemap News Tab' ) )
			),
			'file'      => dirname( __FILE__ ) . '/settings-news.php',
			/* translators: News Sitemap Url */
			'after_row' => '<div class="notice notice-alt notice-info info inline rank-math-notice"><p>' . sprintf( esc_html__( 'Your News Sitemap index can be found here: : %s', 'rank-math-pro' ), '<a href="' . $sitemap_url . '" target="_blank">' . $sitemap_url . '</a>' ) . '</p></div>',
		];

		return $tabs;
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_settings_scripts() {
		if ( Param::get( 'page' ) !== 'rank-math-options-sitemap' ) {
			return;
		}

		wp_enqueue_script(
			'rank-math-pro-news-sitemap-settings',
			RANK_MATH_PRO_URL . 'includes/modules/news-sitemap/assets/js/news-sitemap-settings.js',
			[ 'lodash', 'wp-i18n', 'wp-dom-ready', 'wp-api-fetch' ],
			rank_math_pro()->version,
			true
		);
	}

	/**
	 * Show field check callback.
	 *
	 * @param string $post_type Current Post Type.
	 *
	 * @return boolean
	 */
	private function can_add_tab( $post_type = false ) {
		if ( Admin_Helper::is_term_profile_page() || Admin_Helper::is_posts_page() ) {
			return false;
		}

		$post_type = $post_type ? $post_type : Helper::get_post_type();
		return in_array(
			$post_type,
			(array) Helper::get_settings( 'sitemap.news_sitemap_post_type' ),
			true
		);
	}
}
