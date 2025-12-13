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
use RankMathPro\Sitemap\News_Sitemap_Helper;

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
		$this->action( 'rank_math/module_changed', 'format_news_sitemap_exclude_terms', 10, 2 );

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
	 * Watch for React UI module change.
	 *
	 * @param  string $module Module name.
	 * @param  string $state  Module state.
	 */
	public static function format_news_sitemap_exclude_terms( $module, $state ) {
		if ( $module !== 'react-settings' ) {
			return;
		}

		$post_types = Helper::get_settings( 'sitemap.news_sitemap_post_type', [] );
		if ( empty( $post_types ) ) {
			return;
		}

		$all_opts         = rank_math()->settings->all_raw();
		$sitemap_settings = $all_opts['sitemap'];
		foreach ( $post_types as $post_type ) {
			$key           = "news_sitemap_exclude_{$post_type}_terms";
			$exclude_terms = isset( $sitemap_settings[ $key ] ) ? $sitemap_settings[ $key ] : [];
			if ( empty( $exclude_terms ) ) {
				continue;
			}

			// When React UI is disabled, convert News sitemap exclude terms value to support legacy settings.
			if ( $state === 'off' && ! isset( $exclude_terms[0] ) ) {
				$exclude_terms = [ $exclude_terms ];
			}

			// When React UI is enabled, convert News sitemap exclude terms value to the new format.
			if ( $state === 'on' && isset( $exclude_terms[0] ) ) {
				$exclude_terms = current( $exclude_terms );
			}

			$sitemap_settings[ $key ] = $exclude_terms;
		}

		Helper::update_all_settings( null, null, $sitemap_settings );
		rank_math()->settings->reset();
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
			'title'     => esc_html__( 'News Sitemap', 'rank-math-pro' ),
			'icon'      => 'rm-icon rm-icon-post',
			'desc'      => wp_kses_post(
				/* translators: News Sitemap KB link */
				sprintf( __( 'News Sitemaps allow you to control which content you submit to Google News. More information: <a href="%s" target="_blank">News Sitemaps overview</a>', 'rank-math-pro' ), KB::get( 'news-sitemap', 'Options Panel Sitemap News Tab' ) )
			),
			'file'      => __DIR__ . '/settings-news.php',
			/* translators: News Sitemap Url */
			'after_row' => '<div class="notice notice-alt notice-info info inline rank-math-notice"><p>' . sprintf( esc_html__( 'Your News Sitemap index can be found here: : %s', 'rank-math-pro' ), '<a href="' . $sitemap_url . '" target="_blank">' . $sitemap_url . '</a>' ) . '</p></div>',
			'json'      => [
				'newsSitemapUrl' => $sitemap_url,
				'excludeTerms'   => $this->get_exclude_terms(),
			],
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

	/**
	 * Get exclude terms.
	 *
	 * @return array
	 */
	private function get_exclude_terms() {
		$post_types = Helper::get_settings( 'sitemap.news_sitemap_post_type', [] );
		if ( empty( $post_types ) ) {
			return [];
		}

		$exclude_terms = [];
		foreach ( $post_types as $post_type ) {
			$taxonomies = Helper::get_object_taxonomies( $post_type, 'objects' );
			if ( empty( $taxonomies ) ) {
				continue;
			}

			$terms = Helper::get_settings( "sitemap.news_sitemap_exclude_{$post_type}_terms", [] );

			$post_type_obj   = get_post_type_object( $post_type );
			$post_type_label = $post_type_obj->labels->singular_name;

			foreach ( $taxonomies as $taxonomy => $data ) {
				if ( empty( $data->show_ui ) ) {
					continue;
				}

				$selected = [];
				if ( isset( $terms[ $taxonomy ] ) ) {
					$selected = $terms[ $taxonomy ];
				}

				if ( isset( $terms[0] ) && isset( $terms[0][ $taxonomy ] ) ) {
					$selected = $terms[0][ $taxonomy ];
				}

				$terms = News_Sitemap_Helper::get_taxonomy_terms( $taxonomy, $selected );
				if ( empty( $terms ) ) {
					continue;
				}

				$exclude_terms[ $post_type ][ $taxonomy ] = $terms;
			}
		}

		return $exclude_terms;
	}
}
