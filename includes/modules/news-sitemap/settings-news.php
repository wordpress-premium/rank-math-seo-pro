<?php
/**
 * Sitemap - News
 *
 * @since      1.0.0
 * @package    RankMath
 * @subpackage RankMathPro
 * @author     RankMath <support@rankmath.com>
 */

use RankMath\Helper;
use RankMathPro\Sitemap\News_Sitemap_Helper;

defined( 'ABSPATH' ) || exit;

$cmb->add_field(
	[
		'id'   => 'news_sitemap_publication_name',
		'type' => 'text',
		'name' => esc_html__( 'Google News Publication Name', 'rank-math-pro' ),
		'desc' => wp_kses_post( __( 'The name of the news publication. It must match the name exactly as it appears on your articles in news.google.com, omitting any trailing parentheticals. <a href="https://support.google.com/news/publisher-center/answer/9606710" target="_blank">More information at support.google.com</a>', 'rank-math-pro' ) ),
	]
);

$post_types = Helper::choices_post_types();
if ( isset( $post_types['attachment'] ) && Helper::get_settings( 'general.attachment_redirect_urls', true ) ) {
	unset( $post_types['attachment'] );
}

$cmb->add_field(
	[
		'id'      => 'news_sitemap_post_type',
		'type'    => 'multicheck_inline',
		'name'    => esc_html__( 'News Post Type', 'rank-math-pro' ),
		'desc'    => esc_html__( 'Select the post type you use for News articles.', 'rank-math-pro' ),
		'options' => $post_types,
	]
);

$post_types = Helper::get_settings( 'sitemap.news_sitemap_post_type', [] );
if ( empty( $post_types ) ) {
	return;
}

foreach ( $post_types as $post_type ) {
	$taxonomies = Helper::get_object_taxonomies( $post_type, 'objects' );
	if ( empty( $taxonomies ) ) {
		continue;
	}

	$post_type_obj   = get_post_type_object( $post_type );
	$post_type_label = $post_type_obj->labels->singular_name;
	$group_field_id  = '';

	foreach ( $taxonomies as $taxonomy => $data ) {
		if ( empty( $data->show_ui ) ) {
			continue;
		}

		$selected = Helper::get_settings( "sitemap.news_sitemap_exclude_{$post_type}_terms.0.{$taxonomy}", [] );
		$terms    = News_Sitemap_Helper::get_taxonomy_terms( $taxonomy, $selected );
		if ( empty( $terms ) ) {
			continue;
		}

		// Render each Taxonomy select, post label!
		if ( ! $group_field_id ) {
			$group_field_id = $cmb->add_field(
				[
					'id'         => "news_sitemap_exclude_{$post_type}_terms",
					'type'       => 'group',
					/* translators: Post Type */
					'name'       => sprintf( __( 'Exclude %s Terms ', 'rank-math-pro' ), $post_type_label ),
					'classes'    => 'news-sitemap-exclude-terms cmb-group-text-only',
					'repeatable' => false,
				]
			);
		}

		$cmb->add_group_field(
			$group_field_id,
			[
				'name'            => '',
				'id'              => esc_attr( $taxonomy ),
				'type'            => 'textarea',
				'classes'         => 'cmb-field-list',
				/* translators: 1. Taxonomy Name 2. Post Type */
				'desc'            => sprintf( esc_html__( '%1$s to exclude for %2$s.', 'rank-math-pro' ), $data->label, $post_type_label ),
				'attributes'      => [
					'data-terms'    => wp_json_encode( $terms ),
					'data-taxonomy' => $taxonomy,
				],
				'escape_cb'       => [ '\\RankMathPro\\Sitemap\\News_Sitemap_Helper', 'escape_exclude_terms_value' ],
				'sanitization_cb' => [ '\\RankMathPro\\Sitemap\\News_Sitemap_Helper', 'sanitize_exclude_terms_value' ],
			]
		);
	}
}
