<?php
/**
 * Redirection categories.
 *
 * @since      3.0.11
 * @package    RankMathPro
 * @subpackage RankMathPro\Redirections
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\Redirections;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use RankMath\Helpers\Param;
use RankMath\Helpers\DB as DB_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Categories class.
 */
class Categories {

	use Hooker;

	/**
	 * Import categories from the Redirection plugin.
	 *
	 * @var array
	 */
	private $import_categories = [];

	/**
	 * Whether categories have been imported from the Redirection plugin.
	 *
	 * @var boolean
	 */
	private $categories_imported = false;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Redirection categories.
		$this->action( 'init', 'register_categories', 20 );
		$this->action( 'rank_math/redirection/saved', 'save_category_after_add', 10, 2 );
		$this->filter( 'rank_math/redirections/table_item', 'add_category_in_data_attributes' );

		$this->action( 'wp_loaded', 'filter_category', 5 );
		$this->action( 'rank_math/redirection/extra_tablenav', 'category_filter', 20, 1 );
		$this->action( 'rank_math/redirection/get_redirections_query', 'get_redirections_query', 20 );
		$this->action( 'rank_math/redirection/after_import', 'import_redirection_categories', 20, 2 );
		$this->action( 'rank_math_redirection_category_add_form', 'back_to_redirections_link', 20 );

		$this->filter( 'rank_math/redirection/bulk_actions', 'bulk_actions', 20, 1 );
		$this->filter( 'wp_loaded', 'handle_bulk_actions', 8, 3 );
		$this->filter( 'rank_math/redirection/admin_columns', 'add_category_column', 20, 1 );
		$this->filter( 'rank_math/redirection/admin_column_category', 'category_column_content', 20, 2 );
		$this->filter( 'parent_file', 'fix_categories_parent_menu', 20, 1 );
		$this->filter( 'submenu_file', 'fix_categories_sub_menu', 20 );

		$this->filter( 'rank_math/redirections/page_title_actions', 'page_title_actions', 20, 1 );
	}

	/**
	 * Register redirection categories taxonomy.
	 *
	 * @return void
	 */
	public function register_categories() {
		new CSV_Import_Export_Redirections\CSV_Import_Export_Redirections();
		$tax_labels = [
			'name'              => _x( 'Redirection Categories', 'taxonomy general name', 'rank-math-pro' ),
			'singular_name'     => _x( 'Redirection Category', 'taxonomy singular name', 'rank-math-pro' ),
			'search_items'      => __( 'Search Redirection Categories', 'rank-math-pro' ),
			'all_items'         => __( 'All Redirection Categories', 'rank-math-pro' ),
			'parent_item'       => __( 'Parent Category', 'rank-math-pro' ),
			'parent_item_colon' => __( 'Parent Category:', 'rank-math-pro' ),
			'edit_item'         => __( 'Edit Category', 'rank-math-pro' ),
			'update_item'       => __( 'Update Category', 'rank-math-pro' ),
			'add_new_item'      => __( 'Add New Category', 'rank-math-pro' ),
			'new_item_name'     => __( 'New Category Name', 'rank-math-pro' ),
			'menu_name'         => __( 'Redirection Categories', 'rank-math-pro' ),
		];

		$tax_args = [
			'labels'            => $tax_labels,
			'public'            => false,
			'rewrite'           => false,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => false,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'rest_base'         => 'rank_math_redirection_category',
			'capabilities'      => [
				'manage_terms' => 'rank_math_redirections',
				'edit_terms'   => 'rank_math_redirections',
				'delete_terms' => 'rank_math_redirections',
				'assign_terms' => 'rank_math_redirections',
			],
		];

		register_taxonomy( 'rank_math_redirection_category', 'rank_math_redirection', $tax_args );
	}

	/**
	 * Add bulk actions for Redirections screen.
	 *
	 * @param array $actions Original actions.
	 * @return array
	 */
	public function bulk_actions( $actions ) {
		if ( Param::get( 'status' ) === 'trashed' ) {
			return $actions;
		}

		$actions['bulk_add_redirection_category'] = __( 'Add to Category', 'rank-math-pro' );
		return $actions;
	}

	/**
	 * Handle new bulk actions.
	 *
	 * @return void
	 */
	public function handle_bulk_actions() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'bulk_add_redirection_category' || ! Helper::has_cap( 'redirections' ) ) {
			return;
		}

		check_admin_referer( 'bulk-redirections' );

		$top    = isset( $_POST['redirection_category_filter_top'] ) ? sanitize_text_field( $_POST['redirection_category_filter_top'] ) : false;
		$bottom = isset( $_POST['redirection_category_filter_bottom'] ) ? sanitize_text_field( $_POST['redirection_category_filter_bottom'] ) : false;

		$category_filter = ! empty( $_POST['redirection_category_filter_top'] ) ? $top : $bottom;
		if ( empty( $category_filter ) ) {
			return;
		}
		$redirection = isset( $_REQUEST['redirection'] ) ? array_map( 'sanitize_text_field', $_REQUEST['redirection'] ) : [];
		if ( empty( $redirection ) ) {
			return;
		}

		$ids = (array) wp_parse_id_list( $redirection );
		if ( empty( $ids ) ) {
			Helper::add_notification( __( 'No valid ID provided.', 'rank-math-pro' ) );
			return;
		}

		foreach ( $ids as $id ) {
			wp_set_object_terms( $id, absint( $category_filter ), 'rank_math_redirection_category', apply_filters( 'rank_math_pro/redirection/bulk_append_categories', true ) );
		}

		// Translators: placeholder is the number of updated redirections.
		Helper::add_notification( sprintf( __( '%d redirections have been assigned to the category.', 'rank-math-pro' ), count( $ids ) ) );
	}

	/**
	 * Select correct parent item when we are editing the Redirection Categories.
	 *
	 * @param string $parent_file Original parent file.
	 * @return string
	 */
	public function fix_categories_parent_menu( $parent_file ) {
		global $pagenow;

		if ( in_array( $pagenow, [ 'edit-tags.php', 'term.php' ], true ) && Param::get( 'taxonomy' ) === 'rank_math_redirection_category' ) {
			$parent_file = 'rank-math';
		}

		return $parent_file;
	}

	/**
	 * Select correct submenu item when we are editing the Redirection Categories.
	 *
	 * @param string $submenu_file Original submenu file.
	 * @return string
	 */
	public function fix_categories_sub_menu( $submenu_file ) {
		global $pagenow;

		if ( in_array( $pagenow, [ 'edit-tags.php', 'term.php' ], true ) && Param::get( 'taxonomy' ) === 'rank_math_redirection_category' ) {
			$submenu_file = 'rank-math-redirections';
		}

		return $submenu_file;
	}

	/**
	 * Add "Category" column for Redirections screen.
	 *
	 * @param array $columns Original columns.
	 * @return array
	 */
	public function add_category_column( $columns ) {
		$columns['category'] = __( 'Category', 'rank-math-pro' );
		return $columns;
	}

	/**
	 * Add content in the new "Category" column fields.
	 *
	 * @param bool  $defaults False.
	 * @param array $item  Item data.
	 * @return string
	 */
	public function category_column_content( $defaults, $item ) {
		$format     = '<span class="%1$s">%2$s</span>';
		$categories = $this->get_redirection_categories( $item['id'] );
		$classes    = '';

		$cats  = '';
		$count = 0;
		foreach ( $categories as $category ) {
			++$count;
			if ( $count > 10 ) {
				$cats .= '...';
				break;
			}
			$url   = Helper::get_admin_url( 'redirections', [ 'redirection_category' => $category->term_id ] );
			$cats .= '<a href="' . $url . '">' . $category->name . '</a>, ';
		}
		$cats = rtrim( $cats, ', ' );

		if ( empty( $cats ) ) {
			$cats     = __( 'Uncategorized', 'rank-math-pro' );
			$classes .= ' uncategorized';
		}
		return sprintf( $format, $classes, $cats );
	}

	/**
	 * Get categories for a redirection.
	 *
	 * @param int   $redirection_id Redirection ID.
	 * @param array $args           Array of query string of term query parameters.
	 * @return array
	 */
	public function get_redirection_categories( $redirection_id, $args = [] ) {
		return wp_get_object_terms( $redirection_id, 'rank_math_redirection_category', $args );
	}

	/**
	 * Redirect to filtered URL when the category filter dropdown is used.
	 *
	 * @return void
	 */
	public function filter_category() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_POST['rank_math_filter_redirections_top'] ) && ! isset( $_POST['rank_math_filter_redirections_bottom'] ) ) {
			return;
		}

		if ( ! Helper::has_cap( 'redirections' ) ) {
			return;
		}

		$top    = isset( $_POST['redirection_category_filter_top'] ) ? sanitize_text_field( $_POST['redirection_category_filter_top'] ) : false;
		$bottom = isset( $_POST['redirection_category_filter_bottom'] ) ? sanitize_text_field( $_POST['redirection_category_filter_bottom'] ) : false;

		$category_filter = isset( $_POST['rank_math_filter_redirections_top'] ) ? $top : $bottom;
		if ( ! $category_filter || 'none' === $category_filter ) {
			wp_safe_redirect( Helper::get_admin_url( 'redirections' ) );
			exit;
		}

		wp_safe_redirect( Helper::get_admin_url( 'redirections', [ 'redirection_category' => $category_filter ] ) );
		exit;
	}

	/**
	 * Save categories with a delay so that we know the new redirection ID.
	 *
	 * @param object $redirection Redirection object passed to the hook.
	 * @param array  $params      Redirection parameters.
	 */
	public function save_category_after_add( $redirection, $params ) {
		if ( ! isset( $params['redirection_category'] ) || ! isset( $params['redirection_category']['categories'] ) ) {
			return;
		}

		wp_set_object_terms( $redirection->get_id(), array_map( 'absint', $params['redirection_category']['categories'] ), 'rank_math_redirection_category' );
	}

	/**
	 * Add categories to the data attribute used when editing a Redirection rule
	 *
	 * @param array $data Redirection data.
	 * @return array
	 */
	public function add_category_in_data_attributes( $data ) {
		$data['categories'] = $this->get_redirection_categories( $data['id'], [ 'fields' => 'ids' ] );

		return $data;
	}

	/**
	 * Output category filter dropdown and the submit button for it in the tablenav area.
	 *
	 * @param string $which "top" or "bottom".
	 * @return void
	 */
	public function category_filter( $which ) {
		if ( $this->is_trashed_page() ) {
			return;
		}

		$dropdown_args = [
			'taxonomy'          => 'rank_math_redirection_category',
			'show_option_all'   => false,
			'show_option_none'  => __( 'Select Category', 'rank-math-pro' ),
			'option_none_value' => 'none',
			'echo'              => false,
			'hierarchical'      => true,
			'name'              => 'redirection_category_filter_' . $which,
			'id'                => 'redirection-category-filter-' . $which,
			'selected'          => Param::get( 'redirection_category', '' ),
			'class'             => 'redirection-category-filter',
			'hide_empty'        => false,
		];

		$submit_args = [
			__( 'Filter', 'rank-math-pro' ), // text.
			'secondary category-filter-submit', // type.
			'rank_math_filter_redirections_' . $which, // name.
			false,  // wrap.
		];

		$clear_label    = __( 'Clear Filter', 'rank-math-pro' );
		$clear_url      = Helper::get_admin_url( 'redirections' );
		$clear_classes  = 'clear-redirection-category-filter';
		$clear_classes .= $dropdown_args['selected'] ? '' : ' hidden';
		$clear_button   = '<a href="' . $clear_url . '" class="' . esc_attr( $clear_classes ) . '" title="' . esc_attr( $clear_label ) . '"><span class="dashicons dashicons-dismiss"></span> ' . $clear_label . '</a>';

		$categories    = rtrim( wp_dropdown_categories( $dropdown_args ) );
		$submit_button = call_user_func_array( 'get_submit_button', $submit_args );

		printf( '%1$s%2$s%3$s', $categories, $submit_button, $clear_button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All 3 variables are escaped above.
	}

	/**
	 * Extend get_redirections query to filter by category.
	 *
	 * @param object $table Table object.
	 * @return void
	 */
	public function get_redirections_query( $table ) {
		$categories = Param::get( 'redirection_category' );
		if ( ! $categories ) {
			return;
		}

		$categories = array_map( 'absint', (array) $categories );

		global $wpdb;
		$table->leftJoin( $wpdb->term_relationships, $wpdb->prefix . 'rank_math_redirections.id', $wpdb->term_relationships . '.object_id' );
		$table->leftJoin( $wpdb->term_taxonomy, $wpdb->term_taxonomy . '.term_taxonomy_id', $wpdb->term_relationships . '.term_taxonomy_id' );
		$table->whereIn( $wpdb->term_taxonomy . '.term_id', $categories );
	}

	/**
	 * Import category from the Redirection plugin after importing the redirection itself.
	 *
	 * @param int   $redirection_id  Redirection ID of the imported redirection.
	 * @param array $source_data_row Data related to the redirection in the original table.
	 * @return void
	 */
	public function import_redirection_categories( $redirection_id, $source_data_row ) {
		if ( ! isset( $this->categories_imported ) ) {
			$this->import_categories_from_redirection_plugin();
		}

		if ( ! $source_data_row->group_id || ! isset( $this->import_categories[ $source_data_row->group_id ] ) ) {
			return;
		}

		wp_set_object_terms( $redirection_id, $this->import_categories[ $source_data_row->group_id ], 'rank_math_redirection_category' );
	}

	/**
	 * Import "groups" from Redirection plugin as redirection categories.
	 *
	 * @return bool
	 */
	public function import_categories_from_redirection_plugin() {
		global $wpdb;

		$count = 0;
		$rows  = DB_Helper::get_results( "SELECT * FROM {$wpdb->prefix}redirection_groups" );

		$this->import_categories = [];

		if ( empty( $rows ) ) {
			$this->categories_imported = true;
			return false;
		}

		foreach ( (array) $rows as $row ) {
			$insert = wp_insert_term( $row->name, 'rank_math_redirection_category' );
			if ( ! is_array( $insert ) || ! isset( $insert['term_id'] ) ) {
				continue;
			}
			$this->import_categories[ $row->id ] = $insert['term_id'];
		}

		$this->categories_imported = true;
		return true;
	}

	/**
	 * Show link to go back to the Redirections from the Redirections Categories screen.
	 *
	 * @return void
	 */
	public function back_to_redirections_link() {
		$link = Helper::get_admin_url( 'redirections' );
		echo '<p><a href="' . esc_url( $link ) . '">' . esc_html__( '&larr; Go Back to the Redirections', 'rank-math-pro' ) . '</a></p>';
	}

	/**
	 * Is editing a record.
	 *
	 * @return int|boolean
	 */
	public function is_editing() {

		if ( 'edit' !== Param::get( 'action' ) ) {
			return false;
		}

		return Param::get( 'redirection', false, FILTER_VALIDATE_INT );
	}

	/**
	 * Checks if page status is set to trashed.
	 *
	 * @return bool
	 */
	protected function is_trashed_page() {
		return 'trashed' === Param::get( 'status' );
	}

	/**
	 * Add page title action for categories.
	 *
	 * @param array $actions Original actions.
	 * @return array
	 */
	public function page_title_actions( $actions ) {
		// Move Settings button to the end.
		$tmp_settings = false;
		if ( isset( $actions['settings'] ) ) {
			$tmp_settings = $actions['settings'];
			unset( $actions['settings'] );
		}

		$actions['manage_categories'] = [
			'class' => 'page-title-action',
			'href'  => admin_url( 'edit-tags.php?taxonomy=rank_math_redirection_category' ),
			'label' => __( 'Manage Categories', 'rank-math-pro' ),
		];

		if ( $tmp_settings ) {
			$actions['settings'] = $tmp_settings;
		}

		return $actions;
	}
}
