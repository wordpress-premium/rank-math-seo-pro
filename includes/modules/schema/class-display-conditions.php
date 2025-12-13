<?php
/**
 *  Outputs specific schema code from Schema Template
 *
 * @since      2.0.7
 * @package    RankMath
 * @subpackage RankMathPro
 * @author     RankMath <support@rankmath.com>
 */

namespace RankMathPro\Schema;

use RankMath\Helper;
use RankMath\Schema\DB;
use RankMath\Helpers\DB as DB_Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Display Conditions class.
 */
class Display_Conditions {

	use Hooker;

	/**
	 * Display conditions data.
	 *
	 * @var array
	 */
	private static $conditions = [];

	/**
	 * Insert Schema data.
	 *
	 * @var array
	 */
	private static $insert_schemas = [];

	/**
	 * Get Schema data from Schema Templates post type.
	 *
	 * @param array  $data   Array of json-ld data.
	 * @param JsonLD $jsonld Instance of jsonld.
	 *
	 * @return array
	 */
	public static function get_schema_templates( $data = [], $jsonld = [] ) {
		global $wpdb;
		$templates = DB_Helper::get_col( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type='rank_math_schema' AND post_status='publish'" );

		if ( empty( $templates ) ) {
			return;
		}

		$newdata = [];
		foreach ( $templates as $template ) {
			self::$conditions = [
				'general'  => '',
				'singular' => '',
				'archive'  => '',
			];

			$schema = DB::get_schemas( $template );

			self::prepare_inserted_schemas( current( $schema ) );

			if ( ! self::can_add( current( $schema ) ) ) {
				continue;
			}

			if ( is_admin() || Helper::is_divi_frontend_editor() ) {
				$newdata[] = [
					'id'     => $template,
					'schema' => current( $schema ),
				];

				continue;
			}

			DB::unpublish_jobposting_post( $jsonld, $schema );

			$schema = $jsonld->replace_variables( $schema );
			$schema = $jsonld->filter( $schema, $jsonld, $data );

			$newdata[] = $schema;
		}

		return $newdata;
	}

	/**
	 * Whether schema can be added to current page
	 *
	 * @param array $schema Schema Data.
	 *
	 * @return boolean
	 */
	public static function can_add( $schema ) {
		if ( empty( $schema ) || empty( $schema['metadata']['displayConditions'] ) ) {
			return false;
		}

		$post_ids      = [];
		$post_terms    = [];
		$post_types    = [];
		$all_singulars = [];

		$archive_ids   = [];
		$archive_terms = [];
		$archive_types = [];
		$all_archives  = [];

		$searches = [];
		$generals = [];

		$group = [];

		foreach ( $schema['metadata']['displayConditions'] as $condition ) {
			$operator = $condition['condition'];
			if ( 'insert' === $operator ) {
				// We handle the insert condition in the prepare_inserted_schemas() method.
				continue;
			}
			$category = $condition['category'];
			$taxonomy = ! empty( $condition['postTaxonomy'] ) ? $condition['postTaxonomy'] : '';
			$type     = $condition['type'];
			$value    = (int) $condition['value'];

			if ( 'singular' === $category ) {
				if ( $taxonomy ) {
					self::add_to_conditions( $post_terms, $condition, $operator );
				} elseif ( $value && ! $taxonomy ) {
					self::add_to_conditions( $post_ids, $condition, $operator );
				} elseif ( 'all' === $type ) {
					self::add_to_conditions( $all_singulars, $condition, $operator );
				} else {
					self::add_to_conditions( $post_types, $condition, $operator );
				}
			} elseif ( 'archive' === $category && 'search' !== $type ) {
				if ( $taxonomy ) {
					self::add_to_conditions( $archive_terms, $condition, $operator );
				} elseif ( $value && ! $taxonomy ) {
					self::add_to_conditions( $archive_ids, $condition, $operator );
				} elseif ( 'all' === $type ) {
					self::add_to_conditions( $all_archives, $condition, $operator );
				} else {
					self::add_to_conditions( $archive_types, $condition, $operator );
				}
			} elseif ( 'archive' === $category && 'search' === $type ) {
				self::add_to_conditions( $searches, $condition, $operator );
			} elseif ( 'general' === $category ) {
				self::add_to_conditions( $generals, $condition, $operator );
			}
		}

		$all = [
			'singulars' => array_merge( $post_ids, $post_terms, $post_types, $all_singulars ),
			'archives'  => array_merge( $archive_ids, $archive_terms, $archive_types, $all_archives ),
			'searches'  => $searches,
			'generals'  => $generals,
		];

		/**
		 * Singular
		 */
		if ( ( is_singular() || is_admin() ) && isset( $all['singulars'] ) ) {
			global $post;
			if ( is_admin() && is_null( $post ) ) {
				return false;
			}

			$post_id = is_admin() ? $post->ID : get_the_ID();
			if ( empty( $post_id ) ) {
				return false;
			}
			$post_type = get_post_type( $post_id );

			foreach ( $all['singulars'] as $condition ) {
				$operator = $condition['condition'];
				$category = $condition['category'];
				$taxonomy = ! empty( $condition['postTaxonomy'] ) ? $condition['postTaxonomy'] : '';
				$type     = $condition['type'];
				$value    = (int) $condition['value'];

				$method = "can_add_{$category}";

				$result = self::$method( $operator, $type, $value, $taxonomy );

				// Is post ID matches?
				if ( $post_id === $value ) {
					return $result;
				}

				// Has term?
				if ( $taxonomy && has_term( $value, $taxonomy ) ) {
					return $result;
				}

				// Is post type matches?
				if ( $post_type === $type && ! $taxonomy && ! $value ) {
					return $result;
				}

				// All.
				if ( 'all' === $type ) {
					return $result;
				}
			}
		}

		/**
		 * Search
		 */
		if ( is_search() && $all['searches'] ) {
			foreach ( $all['searches'] as $condition ) {
				$operator = $condition['condition'];
				$category = $condition['category'];
				$taxonomy = ! empty( $condition['postTaxonomy'] ) ? $condition['postTaxonomy'] : '';
				$type     = $condition['type'];
				$value    = (int) $condition['value'];

				$method = "can_add_{$category}";

				$result = self::$method( $operator, $type, $value, $taxonomy );

				return $result;
			}
		}

		/**
		 * Archive
		 */
		if ( ( is_search() || is_archive() ) && $all['archives'] ) {
			$object_id = is_archive() ? get_queried_object_id() : null;

			foreach ( $all['archives'] as $condition ) {
				$operator = $condition['condition'];
				$category = $condition['category'];
				$taxonomy = ! empty( $condition['postTaxonomy'] ) ? $condition['postTaxonomy'] : '';
				$type     = $condition['type'];
				$value    = (int) $condition['value'];
				$result   = 'include' === $operator;

				if ( 'author' === $type && is_author() ) {
					if ( is_author( $value ) && $object_id === $value ) {
						return $result;
					} elseif ( ! $value ) {
						return $result;
					}
				} elseif ( 'category' === $type ) {
					if ( is_category( $value ) && $object_id === $value ) {
						return $result;
					} elseif ( ! $value && is_category() ) {
						return $result;
					}
				} elseif ( 'post_tag' === $type ) {
					if ( is_tag( $value ) && $object_id === $value ) {
						return $result;
					} elseif ( ! $value && is_tag() ) {
						return $result;
					}
				} elseif ( is_tax( $type ) && $object_id === $value ) {
					return $result;
				} elseif ( ! $value && is_tax( $type ) ) {
					return $result;
				} elseif ( 'all' === $type ) {
					return $result;
				}
			}
		}

		/**
		 * General
		 */
		if ( isset( $all['generals'] ) ) {
			foreach ( $all['generals'] as $condition ) {
				$operator = $condition['condition'];
				$category = $condition['category'];
				$taxonomy = ! empty( $condition['postTaxonomy'] ) ? $condition['postTaxonomy'] : '';
				$type     = $condition['type'];
				$value    = (int) $condition['value'];

				$method = "can_add_{$category}";

				return self::$method( $operator, $type, $value, $taxonomy );
			}
		}

		return false;
	}

	/**
	 * Add to conditions.
	 *
	 * @param array  $data      Array of conditions.
	 * @param array  $condition Condition to add.
	 * @param string $operator  Comparision Operator.
	 */
	public static function add_to_conditions( &$data, $condition, $operator ) {
		if ( self::is_exclude( $operator ) ) {
			array_unshift( $data, $condition );
		} else {
			$data[] = $condition;
		}
	}

	/**
	 * Prepare inserted schemas: check if they can be added to current page, and if so, add them to the $insert_schemas static array.
	 *
	 * @param array $schema Schema Data.
	 */
	private static function prepare_inserted_schemas( $schema ) {
		if ( empty( $schema ) || empty( $schema['metadata']['displayConditions'] ) ) {
			return;
		}

		foreach ( $schema['metadata']['displayConditions'] as $condition ) {
			$operator = $condition['condition'];
			if ( 'insert' !== $operator ) {
				continue;
			}

			if ( empty( $schema['metadata']['title'] ) ) {
				continue;
			}

			$in_schema = $condition['category'];
			if ( 'custom' === $in_schema ) {
				if ( empty( $condition['value'] ) ) {
					continue;
				}
				$in_schema = $condition['value'];
			}

			if ( 'ProfilePage' === $in_schema && ! is_singular() ) {
				continue;
			}

			if ( 'ProfilePage' === $in_schema && ! empty( $condition['authorID'] ) ) {
				$author_ids = wp_parse_id_list( $condition['authorID'] );
				global $post;

				if ( ! in_array( (int) $post->post_author, $author_ids, true ) ) {
					continue;
				}
			}

			$with_key = $schema['metadata']['title'];

			self::$insert_schemas[ $in_schema ][] = [
				'key'    => $with_key,
				'schema' => $schema,
			];
		}
	}

	/**
	 * Get inserted schemas.
	 *
	 * @return array
	 */
	public static function get_insertable_schemas() {
		return self::$insert_schemas;
	}

	/**
	 * Whether schema can be added to current page
	 *
	 * @param string $operator Comparision Operator.
	 *
	 * @return boolean
	 */
	private static function can_add_general( $operator ) {
		return 'include' === $operator;
	}

	/**
	 * Whether schema can be added on archive page
	 *
	 * @param string $operator Comparision Operator.
	 * @param string $type     Post/Taxonoy type.
	 * @param string $value    Post/Term ID.
	 *
	 * @return boolean
	 */
	private static function can_add_archive( $operator, $type, $value ) {
		if ( 'search' === $type ) {
			return 'include' === $operator && is_search();
		}

		if ( ! is_archive() ) {
			return false;
		}

		if ( 'all' === $type ) {
			return 'include' === $operator;
		}

		if ( 'author' === $type ) {
			return is_author() && 'include' === $operator && is_author( $value );
		}

		if ( 'category' === $type ) {
			return ! is_category() ? self::$conditions['archive'] : 'include' === $operator && is_category( $value );
		}

		if ( 'post_tag' === $type ) {
			return ! is_tag() ? self::$conditions['archive'] : 'include' === $operator && is_tag( $value );
		}

		return 'include' === $operator && is_tax( $type, $value );
	}

	/**
	 * Whether schema can be added on single page
	 *
	 * @param string $operator Comparision Operator.
	 * @param string $type     Post/Taxonoy type.
	 * @param string $value    Post/Term ID.
	 * @param string $taxonomy Post Taxonomy.
	 *
	 * @return boolean
	 */
	private static function can_add_singular( $operator, $type, $value, $taxonomy ) {
		$post = is_admin() || is_singular() ? get_post( get_the_ID() ) : [];
		if ( empty( $post ) ) {
			return false;
		}

		if ( 'all' === $type ) {
			return 'include' === $operator;
		}

		if ( $type !== $post->post_type ) {
			return false;
		}

		if ( ! $value ) {
			return 'include' === $operator;
		}

		if ( $taxonomy && self::is_exclude( $operator ) ) {
			return ! has_term( $value, $taxonomy );
		}

		if ( $taxonomy ) {
			return 'include' === $operator && has_term( $value, $taxonomy );
		}

		if ( absint( $post->ID ) === absint( $value ) ) {
			return 'include' === $operator;
		}

		return self::is_exclude( $operator );
	}

	/**
	 * Is excluded operator
	 *
	 * @param string $operator Comparision Operator.
	 */
	private static function is_exclude( $operator ) {
		return 'exclude' === $operator;
	}
}
