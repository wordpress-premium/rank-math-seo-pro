<?php
/**
 * Status module.
 *
 * @since      1.0
 * @package    RankMathPro
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Status;

use RankMath\Traits\Hooker;
use RankMath\Helper;
use RankMath\Helpers\Param;
use RankMath\Schema\DB;
use RankMath\Admin\Admin_Helper;
use RankMath\Google\Authentication;
use RankMath\Status\Error_Log;
use RankMath\Status\System_Status as System_Status_Free;
use RankMathPro\Admin\CSV_Import_Export\CSV_Import_Export;

defined( 'ABSPATH' ) || exit;

/**
 * System_Status class.
 */
class System_Status {
	use Hooker;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		$this->filter( 'rank_math/status/rank_math_info', 'filter_status_info' );
		$this->action( 'admin_enqueue_scripts', 'enqueue', 99 );
		$this->filter( 'rank_math/status/import_export/json_data', 'add_csv_json_data' );
	}

	/**
	 * Filter Status Info
	 *
	 * @param array $rankmath Array of rankmath.
	 */
	public function filter_status_info( $rankmath ) {
		$rankmath['fields']['version']['label'] = esc_html__( 'Free version', 'rank-math-pro' );
		array_splice(
			$rankmath['fields'],
			1,
			0,
			[
				[
					'label' => esc_html__( 'PRO version', 'rank-math-pro' ),
					'value' => get_option( 'rank_math_pro_version' ),
				],
			]
		);
		// Change pro_version key with keeping array order the same.
		$keys               = array_keys( $rankmath['fields'] );
		$keys[1]            = 'pro_version';
		$rankmath['fields'] = array_combine( $keys, array_values( $rankmath['fields'] ) );

		return $rankmath;
	}

	/**
	 * Enqueue script on Status & Tools page.
	 *
	 * @since 3.0.81
	 */
	public function enqueue() {
		if ( Param::get( 'page' ) !== 'rank-math-status' ) {
			return;
		}

		Helper::add_json( 'csvProgressNonce', wp_create_nonce( 'rank_math_csv_progress' ) );
		wp_enqueue_script( 'rank-math-pro-status', RANK_MATH_PRO_URL . 'includes/modules/status/assets/js/status.js', [ 'rank-math-status' ], rank_math_pro()->version, true );
		wp_enqueue_script( 'rank-math-gallery-analysis', RANK_MATH_PRO_URL . 'assets/admin/js/product-analysis.js', [], rank_math_pro()->version, true );
	}

	/**
	 * Localized data used in the CSV File Import/Export.
	 *
	 * @param array $data Localized data.
	 * @since 3.0.85
	 */
	public function add_csv_json_data( $data ) {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php'; // @phpstan-ignore-line
		}

		$export_data  = [];
		$object_types = CSV_Import_Export::get_possible_object_types();
		foreach ( $object_types as $key => $label ) {
			$method              = "get_{$key}_values";
			$export_data[ $key ] = $this->$method();
		}

		$import_progress = [];
		if ( get_option( 'rank_math_csv_import' ) ) {
			ob_start();
			CSV_Import_Export::import_progress_details();
			$import_progress = [
				'status'  => 'processing',
				'content' => ob_get_clean(),
			];
		}

		$json = [
			'exportData'       => $export_data,
			'exportCsvNonce'   => wp_create_nonce( 'rank_math_pro_csv_export' ),
			'csvProgressNonce' => wp_create_nonce( 'rank_math_csv_progress' ),
			'importProgress'   => $import_progress,
		];

		return array_merge( $data, $json );
	}

	/**
	 * Localized Posts data used in the CSV File Export.
	 *
	 * @since 3.0.85
	 */
	private function get_post_values() {
		$post_types = Helper::get_allowed_post_types();
		$data       = [];

		foreach ( $post_types as $post_type ) {
			$data[ $post_type ] = esc_html( get_post_type_object( $post_type )->labels->name );
		}

		return $data;
	}

	/**
	 * Localized Taxonomies data used in the CSV File Export.
	 *
	 * @since 3.0.85
	 */
	private function get_term_values() {
		$taxonomies = Helper::get_allowed_taxonomies();
		$data       = [];

		foreach ( $taxonomies as $taxonomy ) {
			$data[ $taxonomy ] = esc_html( get_taxonomy( $taxonomy )->labels->name );
		}

		return $data;
	}

	/**
	 * Localized User data used in the CSV File Export.
	 *
	 * @since 3.0.85
	 */
	private function get_user_values() {
		$roles = get_editable_roles();
		$data  = [];

		foreach ( $roles as $role_id => $role ) {
			$data[ $role_id ] = esc_html( $role['name'] );
		}

		return $data;
	}
}
