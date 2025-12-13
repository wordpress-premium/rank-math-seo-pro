<?php
/**
 * The News Sitemap Module
 *
 * @since      1.0.0
 * @package    RankMath
 * @subpackage RankMathPro
 * @author     RankMath <support@rankmath.com>
 */

namespace RankMathPro\Sitemap;

use RankMath\Helper;
use RankMath\Helpers\Locale;
use RankMath\Traits\Hooker;
use RankMath\Sitemap\Router;

defined( 'ABSPATH' ) || exit;

/**
 * News_Sitemap class.
 */
class News_Sitemap {

	use Hooker;

	/**
	 * Holds the Sitemap slug.
	 *
	 * @var string
	 */
	protected $sitemap_slug = null;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		new News_Sitemap\Admin();

		$sitemap_slug = Router::get_sitemap_slug( 'news' );
		$this->action( 'rank_math/head', 'robots', 10 );
		$this->filter( 'rank_math/sitemap/providers', 'add_provider' );
		$this->filter( 'rank_math/sitemap/' . $sitemap_slug . '_urlset', 'xml_urlset' );
		$this->filter( 'rank_math/sitemap/xsl_' . $sitemap_slug, 'sitemap_xsl' );
		$this->filter( 'rank_math/sitemap/' . $sitemap_slug . '_stylesheet_url', 'stylesheet_url' );
		$this->filter( 'rank_math/sitemap/' . $sitemap_slug . '_sitemap_url', 'sitemap_url', 10, 2 );

		$this->filter( 'rank_math/schema/default_type', 'change_default_schema_type', 10, 3 );
		$this->filter( 'rank_math/snippet/rich_snippet_article_entity', 'add_copyrights_data' );
	}

	/**
	 * Output the meta robots tag.
	 */
	public function robots() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		/**
		 * Filter: 'rank_math/sitemap/news/noindex' - Allow preventing of outputting noindex tag.
		 *
		 * @api string $meta_robots The noindex tag.
		 *
		 * @param object $post The post.
		 */
		if ( ! $this->do_filter( 'sitemap/news/noindex', true, $post ) || self::is_post_indexable( $post->ID ) ) {
			return;
		}

		echo '<meta name="Googlebot-News" content="noindex" />' . "\n";
	}

	/**
	 * Check if post is indexable.
	 *
	 * @param int $post_id Post ID to check.
	 *
	 * @return boolean
	 */
	public static function is_post_indexable( $post_id ) {
		$robots = get_post_meta( $post_id, 'rank_math_news_sitemap_robots', true );
		if ( ! empty( $robots ) && 'noindex' === $robots ) {
			return false;
		}

		return true;
	}

	/**
	 * Add news sitemap provider.
	 *
	 * @param array $providers Sitemap provider registry.
	 */
	public function add_provider( $providers ) {
		$providers[] = new \RankMathPro\Sitemap\News_Provider();
		return $providers;
	}

	/**
	 * Produce XML output for google news urlset.
	 *
	 * @return string
	 */
	public function xml_urlset() {
		return '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
			. 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd '
			. 'http://www.google.com/schemas/sitemap-news/0.9 http://www.google.com/schemas/sitemap-news/0.9/sitemap-news.xsd" '
			. 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
			. 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
	}

	/**
	 * Stylesheet Url for google news.
	 *
	 * @param  string $url Current stylesheet url.
	 * @return string
	 */
	public function stylesheet_url( $url ) { // phpcs:ignore
		$stylesheet_url = preg_replace( '/(^http[s]?:)/', '', Router::get_base_url( 'news-sitemap.xsl' ) );
		return '<?xml-stylesheet type="text/xsl" href="' . $stylesheet_url . '"?>';
	}

	/**
	 * Stylesheet for google news.
	 *
	 * @param string $title Title for stylesheet.
	 */
	public function sitemap_xsl( $title ) { // phpcs:ignore
		require_once 'sitemap-xsl.php';
	}

	/**
	 * Build the `<url>` tag for a given URL.
	 *
	 * @param  array    $url      Array of parts that make up this entry.
	 * @param  Renderer $renderer Sitemap renderer class object.
	 * @return string
	 */
	public function sitemap_url( $url, $renderer ) {
		$date = null;
		if ( ! empty( $url['publication_date'] ) ) {
			// Create a DateTime object date in the correct timezone.
			$date = $renderer->timezone->format_date( $url['publication_date'] );
		}

		$output  = $renderer->newline( '<url>', 1 );
		$output .= $renderer->newline( '<loc>' . $renderer->encode_url_rfc3986( htmlspecialchars( $url['loc'] ) ) . '</loc>', 2 );

		$output .= $renderer->newline( '<news:news>', 2 );
		$output .= $this->get_news_publication( $renderer, $url );

		$output .= empty( $date ) ? '' : $renderer->newline( '<news:publication_date>' . htmlspecialchars( $date ) . '</news:publication_date>', 3 );
		$output .= $renderer->add_cdata( $url['title'], 'news:title', 3 );

		$output .= $renderer->newline( '</news:news>', 2 );

		$output .= $renderer->newline( '</url>', 1 );

		/**
		 * Filters the output for the sitemap url tag.
		 *
		 * @param string $output The output for the sitemap url tag.
		 * @param array  $url    The sitemap url array on which the output is based.
		 */
		return $this->do_filter( 'sitemap_url', $output, $url );
	}

	/**
	 * Get excluded post terms.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	private static function get_exclude_terms( $post_type ) {
		$exclude_terms = (array) Helper::get_settings( "sitemap.news_sitemap_exclude_{$post_type}_terms" );
		if ( isset( $exclude_terms[0] ) ) {
			return $exclude_terms[0];
		}

		return $exclude_terms;
	}

	/**
	 * Change default schema type on News Posts.
	 *
	 * @param string $schema    Default schema type.
	 * @param string $post_type Current Post Type.
	 * @param int    $post_id   Current Post ID.
	 *
	 * @return string
	 */
	public function change_default_schema_type( $schema, $post_type, $post_id ) {
		$news_post_types = (array) Helper::get_settings( 'sitemap.news_sitemap_post_type' );
		if ( ! in_array( $post_type, $news_post_types, true ) ) {
			return $schema;
		}

		$exclude_terms = self::get_exclude_terms( $post_type );

		if ( empty( $exclude_terms ) ) {
			return 'NewsArticle';
		}

		$has_excluded_term = false;
		foreach ( $exclude_terms as $taxonomy => $terms ) {
			if ( has_term( $terms, $taxonomy, $post_id ) ) {
				$has_excluded_term = true;
				break;
			}
		}

		return $has_excluded_term ? $schema : 'NewsArticle';
	}

	/**
	 * Filter to add Copyrights data in Article Schema on News Posts.
	 *
	 * @param array $entity Snippet Data.
	 * @return array
	 */
	public function add_copyrights_data( $entity ) {
		global $post;
		if ( is_null( $post ) ) {
			return $entity;
		}

		$news_post_types = (array) Helper::get_settings( 'sitemap.news_sitemap_post_type' );
		if ( ! in_array( $post->post_type, $news_post_types, true ) ) {
			return $entity;
		}

		$entity['copyrightYear'] = get_the_modified_date( 'Y', $post );
		if ( ! empty( $entity['publisher'] ) ) {
			$entity['copyrightHolder'] = $entity['publisher'];
		}

		return $entity;
	}

	/**
	 * Get News Pub Tags.
	 *
	 * @param Renderer $renderer Sitemap renderer class object.
	 * @param array    $entity   Array of parts that make up this entry.
	 * @return string
	 */
	private function get_news_publication( $renderer, $entity ) {
		$lang = Locale::get_site_language();

		/**
		 * Filter: 'rank_math/sitemap/news/language' - Allow changing the news language based on the entity.
		 *
		 * @param string $lang   Language code.
		 * @param array  $entity Array of parts that make up this entry.
		 */
		$lang = $this->do_filter( 'sitemap/news/language', $lang, $entity );
		$name = Helper::get_settings( 'sitemap.news_sitemap_publication_name' );
		$name = $name ? $name : get_bloginfo( 'name' );

		$news_publication  = '';
		$news_publication .= $renderer->newline( '<news:publication>', 3 );
		$news_publication .= $renderer->newline( '<news:name>' . esc_html( $name ) . '</news:name>', 4 );
		$news_publication .= $renderer->newline( '<news:language>' . $lang . '</news:language>', 4 );
		$news_publication .= $renderer->newline( '</news:publication>', 3 );

		return $news_publication;
	}
}
