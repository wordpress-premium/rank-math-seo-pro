<?php
/**
 * The Global functionality of the plugin.
 *
 * Defines the functionality loaded on admin.
 *
 * @since      1.0.15
 * @package    RankMathPro
 * @subpackage RankMathPro\Rest
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Analytics;

use WP_Error;
use WP_REST_Server;
use RankMath\Helper;
use RankMath\Helpers\DB as DB_Helper;
use WP_REST_Request;
use WP_REST_Controller;
use RankMath\Admin\Admin_Helper;
use RankMathPro\Google\PageSpeed;
use RankMath\SEO_Analysis\SEO_Analyzer;
use RankMathPro\Analytics\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Rest class.
 */
class Rest extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = \RankMath\Rest\Rest_Helper::BASE . '/an';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/getKeywordPages',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ Keywords::get(), 'get_keyword_pages' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_keyword_pages_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/postsOverview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_posts_overview' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/getTrackedKeywords',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_tracked_keywords' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/getTrackedKeywordsRows',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_tracked_keywords_rows' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_tracked_keywords_rows_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/getTrackedKeywordSummary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_tracked_keyword_summary' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/trackedKeywordsOverview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_tracked_keywords_overview' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/addTrackKeyword',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_track_keyword' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_add_track_keyword_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/autoAddFocusKeywords',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'auto_add_focus_keywords' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_auto_add_focus_keywords_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/removeTrackKeyword',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'remove_track_keyword' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_collection_params(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/deleteTrackedKeywords',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'delete_all_tracked_keywords' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/getDesktopPagespeed',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_desktop_pagespeed' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_pagespeed_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/getMobilePagespeed',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_mobile_pagespeed' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_pagespeed_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/getPageSEOScore',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_page_seo_score' ],
				'permission_callback' => [ $this, 'has_permission' ],
				'args'                => $this->get_pagespeed_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/postsRows',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ Posts::get(), 'get_posts_rows' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/inspectionStats',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_inspection_stats' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);
	}

	/**
	 * Get top 5 winning and losing posts rows.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_posts_overview() {
		return rest_ensure_response(
			[
				'winningPosts' => Posts::get()->get_winning_posts(),
				'losingPosts'  => Posts::get()->get_losing_posts(),
			]
		);
	}

	/**
	 * Get tracked keywords rows.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_tracked_keywords() {
		return rest_ensure_response(
			[ 'rows' => Keywords::get()->get_tracked_keywords() ]
		);
	}

	/**
	 * Get tracked keywords rows.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return array
	 */
	public function get_tracked_keywords_rows( WP_REST_Request $request ) {
		return Keywords::get()->get_tracked_keywords_rows( $request );
	}

	/**
	 * Get tracked keywords summary.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_tracked_keyword_summary() {
		\RankMathPro\Admin\Api::get()->get_settings();

		return rest_ensure_response( Keywords::get()->get_tracked_keywords_summary() );
	}

	/**
	 * Get top 5 winning and losing tracked keywords overview.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_tracked_keywords_overview() {
		return rest_ensure_response(
			[
				'winningKeywords' => Keywords::get()->get_tracked_winning_keywords(),
				'losingKeywords'  => Keywords::get()->get_tracked_losing_keywords(),
			]
		);
	}
	/**
	 * Add track keyword to DB.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function auto_add_focus_keywords( WP_REST_Request $request ) {
		$data              = $request->get_param( 'data' );
		$secondary_keyword = ! empty( $data['secondary_keyword'] );
		$post_types        = ! empty( $data['post_types'] ) ? $data['post_types'] : [];

		$all_opts = rank_math()->settings->all_raw();
		$general  = $all_opts['general'];

		$general['auto_add_focus_keywords'] = $data;
		Helper::update_all_settings( $general, null, null );

		if ( empty( $post_types ) ) {
			return false;
		}

		global $wpdb;
		$focus_keywords = DB_Helper::get_col(
			"SELECT {$wpdb->postmeta}.meta_value FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta}
			ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
			WHERE 1=1
			AND {$wpdb->posts}.post_type IN ('" . implode( "', '", esc_sql( $post_types ) ) . "')
			AND {$wpdb->posts}.post_status = 'publish'
			AND {$wpdb->postmeta}.meta_key = 'rank_math_focus_keyword'
			"
		);

		$keywords_data = [];
		foreach ( $focus_keywords as $focus_keyword ) {
			$keywords = explode( ',', mb_strtolower( $focus_keyword ) );
			if ( $secondary_keyword ) {
				$keywords_data = array_merge( $keywords, $keywords_data );
			} else {
				$keywords_data[] = current( $keywords );
			}
		}

		if ( empty( $keywords_data ) ) {
			return false;
		}

		return DB::bulk_insert_query_focus_keyword_data( array_unique( $keywords_data ) );
	}
	/**
	 * Add track keyword to DB.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function add_track_keyword( WP_REST_Request $request ) {
		$keyword = $request->get_param( 'keyword' );

		// Check remain keywords count can be added.
		$total_keywords = Keywords::get()->get_tracked_keywords_count();
		$new_keywords   = Keywords::get()->extract_addable_track_keyword( $keyword );
		$keywords_count = count( $new_keywords );
		if ( $keywords_count <= 0 ) {
			return false;
		}

		$summary = Keywords::get()->get_tracked_keywords_quota();
		$remain  = $summary['available'] - $total_keywords;
		if ( $remain <= 0 ) {
			return false;
		}

		// Add keywords.
		Keywords::get()->add_track_keyword( $new_keywords );

		$registered = Admin_Helper::get_registration_data();
		if ( ! $registered || empty( $registered['username'] ) || empty( $registered['api_key'] ) ) {
			return false;
		}

		// Send total keywords count to RankMath.
		$total_keywords = Keywords::get()->get_tracked_keywords_count();
		$response       = \RankMathPro\Admin\Api::get()->keywords_info( $registered['username'], $registered['api_key'], $total_keywords );
		if ( $response ) {
			update_option( 'rank_math_keyword_quota', $response );
		}

		return true;
	}

	/**
	 * Remove track keyword from DB.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function remove_track_keyword( WP_REST_Request $request ) {
		$keyword = $request->get_param( 'keyword' );

		// Remove keyword.
		Keywords::get()->remove_track_keyword( $keyword );

		$registered = Admin_Helper::get_registration_data();
		if ( ! $registered || empty( $registered['username'] ) || empty( $registered['api_key'] ) ) {
			return false;
		}

		// Send total keywords count to RankMath.
		$total_keywords = Keywords::get()->get_tracked_keywords_count();
		$response       = \RankMathPro\Admin\Api::get()->keywords_info( $registered['username'], $registered['api_key'], $total_keywords );
		if ( $response ) {
			update_option( 'rank_math_keyword_quota', $response );
		}

		return true;
	}

	/**
	 * Delete all the manually tracked keywords.
	 */
	public function delete_all_tracked_keywords() {

		// Delete all keywords.
		Keywords::get()->delete_all_tracked_keywords();

		$registered = Admin_Helper::get_registration_data();
		if ( empty( $registered['username'] ) || empty( $registered['api_key'] ) ) {
			return false;
		}

		// Send total keywords count as 0 to RankMath.
		$response = \RankMathPro\Admin\Api::get()->keywords_info( $registered['username'], $registered['api_key'], 0 );
		if ( $response ) {
			update_option( 'rank_math_keyword_quota', $response );
		}

		return true;
	}

	/**
	 * Check if keyword can be added.
	 *
	 * @param  string $keywords Comma separated keywords.
	 * @return bool True if remain keyword count is larger than zero.
	 */
	private function can_add_keyword( $keywords = '' ) {
		// Check remain keywords count can be added by supposing current keyword is added.
		$total_keywords = Keywords::get()->get_tracked_keywords_count();
		$new_keywords   = Keywords::get()->extract_addable_track_keyword( $keywords );
		$keywords_count = count( $new_keywords );
		$summary        = Keywords::get()->get_tracked_keywords_quota();
		$remain         = $summary['available'] - $total_keywords - $keywords_count;

		return $remain >= 0;
	}

	/**
	 * Get desktop pagespeed data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function get_desktop_pagespeed( WP_REST_Request $request ) {
		return $this->get_page_score( $request, 'desktop' );
	}

	/**
	 * Get mobile pagespeed data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function get_mobile_pagespeed( WP_REST_Request $request ) {
		return $this->get_page_score( $request, 'mobile' );
	}

	/**
	 * Get page SEO score data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function get_page_seo_score( WP_REST_Request $request ) {
		return $this->get_page_score( $request, 'page_score' );
	}

	/**
	 * Get page score data.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $type Type of page score.
	 *
	 * @return array|bool Pagespeed info on success, false on failure.
	 */
	public function get_page_score( WP_REST_Request $request, $type = '' ) {
		$id      = $request->get_param( 'id' );
		$post_id = $request->get_param( 'objectID' );

		$url = get_permalink( $post_id );
		$pre = apply_filters( 'rank_math/analytics/pre_pagespeed', false, $post_id, $request );
		if ( false !== $pre ) {
			return $pre;
		}

		$force        = \boolval( $request->get_param( 'force' ) );
		$is_admin_bar = \boolval( $request->get_param( 'isAdminBar' ) );
		if ( $force || ( ! $is_admin_bar && $this->should_update_pagespeed( $id ) ) ) {
			$update = [];

			if ( 'page_score' === $type ) {
				$score = $this->get_page_analysis_score( $url );
				if ( ! is_wp_error( $score ) && $score > 0 ) {
					$update['page_score']          = $score;
					$update['pagespeed_refreshed'] = current_time( 'mysql' );
				}
			}

			if ( 'desktop' === $type || 'mobile' === $type ) {
				$score = PageSpeed::get_pagespeed( $url, $type );
				if ( ! empty( $score ) && ! is_wp_error( $score ) ) {
					$update                        = \array_merge( $update, $score );
					$update['pagespeed_refreshed'] = current_time( 'mysql' );
				}
			}
		}

		if ( ! empty( $update ) ) {
			$update['id']        = $id;
			$update['object_id'] = $post_id;
			DB::update_object( $update );
		}

		if ( is_wp_error( $score ) ) {
			return wp_send_json_error(
				[
					'message' => $score->get_error_message(),
					'code'    => $score->get_error_code(),
				],
			);
		}

		return wp_send_json_success( $update );
	}

	/**
	 * Get page analysis score.
	 *
	 * @param string $url URL to check.
	 * @return int|WP_Error Page analysis score or WP_Error on failure.
	 */
	private function get_page_analysis_score( $url ) {
		$analyzer = new SEO_Analyzer();
		$analyzer->set_url();

		$analyzer->results         = [];
		$analyzer->analyse_url     = $url;
		$analyzer->analyse_subpage = true;
		if ( ! $analyzer->run_api_tests() ) {
			$message = $analyzer->api_error ?? esc_html__( 'An error occurred while running the SEO analysis.', 'rank-math-pro' );

			return new WP_Error(
				'seo_analysis_error',
				/* translators: %s: Error message */
				sprintf( esc_html__( 'SEO Analysis failed: %s', 'rank-math-pro' ), $message )
			);
		}

		$analyzer->build_results();
		if ( empty( $analyzer->results ) ) {
			$message = $analyzer->api_error ?? esc_html__( 'No results found for the SEO analysis.', 'rank-math-pro' );

			return new WP_Error(
				'seo_analysis_no_results',
				/* translators: %s: Error message */
				sprintf( esc_html__( 'SEO Analysis failed: %s', 'rank-math-pro' ), $message )
			);
		}

		$score = 0;
		foreach ( $analyzer->results as $id => $result ) {
			if (
				$result->is_hidden() ||
				'ok' !== $result->get_status() ||
				false === $analyzer->can_count_result( $result )
			) {
				continue;
			}

			$score = $score + $result->get_score();
		}

		return $score;
	}

	/**
	 * Should update pagespeed record.
	 *
	 * @param  int $id      Database row id.
	 * @return bool
	 */
	private function should_update_pagespeed( $id ) {
		$record = DB::objects()->where( 'id', $id )->one();

		return \time() > ( \strtotime( $record->pagespeed_refreshed ) + ( DAY_IN_SECONDS * 7 ) );
	}

	/**
	 * Get inspection stats.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_inspection_stats() {
		// Early Bail!!
		if ( ! DB_Helper::check_table_exists( 'rank_math_analytics_inspections' ) ) {
			return [
				'presence' => [],
				'status'   => [],
			];
		}

		return rest_ensure_response(
			[
				'presence' => Url_Inspection::get_presence_stats(),
				'status'   => Url_Inspection::get_status_stats(),
			]
		);
	}

	/**
	 * Get tracked keywords collection params.
	 *
	 * @return array
	 */
	public function get_tracked_keywords_rows_args() {
		$query_params                       = parent::get_collection_params();
		$query_params['orderby']['default'] = 'default';
		$query_params['orderby']['enum'][]  = 'default';
		$query_params['orderby']['enum'][]  = 'keyword';

		$query_params['search'] = [
			'description'       => esc_html__( 'Keyword to search.', 'rank-math-pro' ),
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => function ( $keyword ) {
				$keyword = mb_strtolower( filter_var( $keyword, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES ) );
				$keyword = html_entity_decode( $keyword );
				return $keyword;
			},
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $query_params;
	}

	/**
	 * Get keyword pages collection params.
	 *
	 * @return array
	 */
	public function get_keyword_pages_args() {
		$query_params = parent::get_collection_params();

		$query_params['query'] = [
			'description'       => esc_html__( 'Query to search.', 'rank-math-pro' ),
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => function ( $param, $request, $key ) {
				if ( empty( $param ) ) {
					return new WP_Error(
						'rest_invalid_param',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must not be empty.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		return $query_params;
	}

	/**
	 * Get add track keyword collection params.
	 *
	 * @return array
	 */
	public function get_add_track_keyword_args() {
		$query_params = parent::get_collection_params();

		$query_params['keyword'] = [
			'description'       => esc_html__( 'Keyword to add.', 'rank-math-pro' ),
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => function ( $param, $request, $key ) {
				if ( empty( $param ) ) {
					return new WP_Error(
						'param_value_empty',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must not be empty.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		return $query_params;
	}

	/**
	 * Get auto add focus keywords collection params.
	 *
	 * @return array
	 */
	public function get_auto_add_focus_keywords_args() {
		$query_params = parent::get_collection_params();

		$query_params['data'] = [
			'description'       => esc_html__( 'Data to add.', 'rank-math-pro' ),
			'type'              => 'object',
			'default'           => '',
			'sanitize_callback' => function ( $param ) {
				if ( ! is_array( $param ) ) {
					return [];
				}
				$param = filter_var_array(
					$param,
					[
						'enable_auto_import' => [
							'filter' => FILTER_VALIDATE_INT,
						],
						'post_types'         => [
							'filter' => FILTER_SANITIZE_STRING,
							'flags'  => FILTER_REQUIRE_ARRAY,
						],
						'secondary_keyword'  => [
							'filter' => FILTER_VALIDATE_BOOLEAN,
						],
					]
				);

				if ( ! empty( $param['post_types'] ) ) {
					$param['post_types'] = array_map( 'sanitize_text_field', $param['post_types'] );
				}

				return $param;
			},
			'validate_callback' => function ( $param, $request, $key ) {
				if ( empty( $param ) ) {
					return new WP_Error(
						'param_value_empty',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must not be empty.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				if ( ! empty( $param['enable_auto_import'] ) && ! is_numeric( $param['enable_auto_import'] ) ) {
					return new WP_Error(
						'param_value_invalid',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must be numeric.', 'rank-math-pro' ), 'enable_auto_import' ),
						[ 'status' => 400 ]
					);
				}

				if ( ! empty( $param['post_types'] ) ) {
					if ( ! is_array( $param['post_types'] ) ) {
						return new WP_Error(
							'param_value_invalid',
							/* translators: %s: parameter name */
							sprintf( esc_html__( 'The %s parameter must be array.', 'rank-math-pro' ), 'post_types' ),
							[ 'status' => 400 ]
						);
					}

					if ( ! preg_match( '/^[a-zA-Z0-9-_]+$/', implode( '', $param['post_types'] ) ) ) {
						return new WP_Error(
							'param_value_invalid',
							/* translators: %s: parameter name */
							sprintf( esc_html__( 'The %s parameter must be valid post type.', 'rank-math-pro' ), 'post_types' ),
							[ 'status' => 400 ]
						);
					}
				}

				if ( ! empty( $param['secondary_keyword'] ) && ! is_bool( $param['secondary_keyword'] ) ) {
					return new WP_Error(
						'param_value_invalid',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must be boolean.', 'rank-math-pro' ), 'secondary_keyword' ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		return $query_params;
	}

	/**
	 * Get pagespeed collection params.
	 *
	 * @return array
	 */
	public function get_pagespeed_args() {
		$query_params = parent::get_collection_params();

		$query_params['id'] = [
			'description'       => esc_html__( 'Record id.', 'rank-math-pro' ),
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => function ( $param, $request, $key ) {
				if ( empty( $param ) ) {
					return new WP_Error(
						'param_value_empty',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must not be empty.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				if ( ! is_numeric( $param ) ) {
					return new WP_Error(
						'param_value_invalid',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must be integer.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		$query_params['objectID'] = [
			'description'       => esc_html__( 'Post id.', 'rank-math-pro' ),
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => function ( $param, $request, $key ) {
				if ( empty( $param ) ) {
					return new WP_Error(
						'param_value_empty',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must not be empty.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		$query_params['force'] = [
			'description'       => esc_html__( 'Force update pagespeed.', 'rank-math-pro' ),
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => function ( $param, $request, $key ) {
				if ( ! is_bool( $param ) ) {
					return new WP_Error(
						'param_value_invalid',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must be boolean.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		$query_params['isAdminBar'] = [
			'description'       => esc_html__( 'Is admin bar.', 'rank-math-pro' ),
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => function ( $param, $request, $key ) {
				if ( ! is_bool( $param ) ) {
					return new WP_Error(
						'param_value_invalid',
						/* translators: %s: parameter name */
						sprintf( esc_html__( 'The %s parameter must be boolean.', 'rank-math-pro' ), $key ),
						[ 'status' => 400 ]
					);
				}

				return rest_validate_request_arg( $param, $request, $key );
			},
		];

		return $query_params;
	}

	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['order'] = [
			'description'       => esc_html__( 'Order sort attribute ascending or descending.', 'rank-math-pro' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => [ 'asc', 'desc', 'ASC', 'DESC' ],
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => 'rest_validate_request_arg',
		];

		$query_params['orderby'] = [
			'description'       => esc_html__( 'Sort collection by post attribute.', 'rank-math-pro' ),
			'type'              => 'string',
			'default'           => 'date',
			'enum'              => [
				'author',
				'date',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
			],
			'sanitize_callback' => 'rest_sanitize_request_arg',
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $query_params;
	}

	/**
	 * Determines if the current user can manage analytics.
	 *
	 * @return true
	 */
	public function has_permission() {
		return current_user_can( 'rank_math_analytics' );
	}
}
