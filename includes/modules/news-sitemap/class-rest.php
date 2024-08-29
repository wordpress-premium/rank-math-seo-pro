<?php
/**
 * Rest class for News Sitemap.
 *
 * @since      3.0.57
 * @package    RankMath
 * @subpackage RankMath\Rest
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Sitemap\News_Sitemap;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Controller;
use RankMath\Helper;
use RankMathPro\Sitemap\News_Sitemap_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Rest class.
 */
class Rest extends WP_REST_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		register_rest_route(
			\RankMath\Rest\Rest_Helper::BASE . '/sitemap',
			'/getTerms',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_terms' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->validate_args(),
			]
		);
	}

	/**
	 * Determines if the current user can manage sitemap.
	 *
	 * @return true
	 */
	public function has_permission() {
		if ( ! Helper::has_cap( 'sitemap' ) ) {
			return new WP_Error(
				'rest_cannot_access',
				__( 'Sorry, only authenticated users can research the keyword.', 'rank-math-pro' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Rest callback to get the terms.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_terms( WP_REST_Request $request ) {
		return News_Sitemap_Helper::get_taxonomy_terms( $request->get_param( 'taxonomy' ), [], $request->get_param( 'search' ) );
	}

	/**
	 * Validate getTerms endpoint arguments.
	 *
	 * @return array
	 */
	public function validate_args() {
		return [
			'taxonomy' => [
				'type'              => 'string',
				'required'          => true,
				'description'       => esc_html__( 'Taxonomy to look for terms', 'rank-math-pro' ),
				'validate_callback' => [ '\\RankMath\\Rest\\Rest_Helper', 'is_param_empty' ],
			],
			'search'   => [
				'type'              => 'string',
				'required'          => true,
				'description'       => esc_html__( 'Searched string', 'rank-math-pro' ),
				'validate_callback' => [ '\\RankMath\\Rest\\Rest_Helper', 'is_param_empty' ],
			],
		];
	}
}
