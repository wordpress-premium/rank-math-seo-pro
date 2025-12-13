<?php
/**
 * Background process to migrate GTIN values from a plugin to the WooCommerce GTIN field.
 *
 * @since      3.0.72
 * @package    RankMathPRO
 * @subpackage RankMathPRO\WooCommerce
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro\WooCommerce;

use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Migrate_GTIN class.
 */
class Migrate_GTIN extends \WP_Background_Process {
	/**
	 * Action.
	 *
	 * @var string
	 */
	protected $action = 'gtin_data_migration';

	/**
	 * Main instance.
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return Migrate_GTIN
	 */
	public static function get() {
		static $instance;

		if ( is_null( $instance ) && ! ( $instance instanceof Migrate_GTIN ) ) {
			$instance = new Migrate_GTIN();
		}

		return $instance;
	}

	/**
	 * Start creating batches.
	 *
	 * @param array $products Products to process.
	 */
	public function start( $products ) {
		$chunks = array_chunk( $products, 10 );
		foreach ( $chunks as $chunk ) {
			$this->push_to_queue( $chunk );
		}

		$this->save()->dispatch();
	}

	/**
	 * Task to perform.
	 *
	 * @param string $products Products to process.
	 */
	public function wizard( $products ) {
		$this->task( $products );
	}

	/**
	 * Task to perform.
	 *
	 * @param array $products Products to process.
	 *
	 * @return bool
	 */
	protected function task( $products ) {
		try {
			foreach ( $products as $product_id ) {
				$product                = wc_get_product( $product_id );
				$gtin                   = get_post_meta( $product_id, '_rank_math_gtin_code', true );
				$global_unique_id_found = wc_get_product_id_by_global_unique_id( $gtin );
				if ( ! empty( $global_unique_id_found ) ) {
					continue;
				}

				$product->set_global_unique_id( $gtin );
				$product->save();
			}

			return false;
		} catch ( \Exception $error ) {
			return true;
		}
	}

	/**
	 * Find products with GTIN value.
	 *
	 * @return array
	 */
	public function find_posts() {
		$products = get_option( 'rank_math_gtin_products' );
		if ( ! empty( $products ) ) {
			return $products;
		}

		// Products with GTIN value.
		$products = get_posts(
			[
				'post_type'   => [ 'product', 'product_variation' ],
				'fields'      => 'ids',
				'numberposts' => -1,
				'meta_query'  => [
					[
						'key'     => '_rank_math_gtin_code',
						'compare' => 'EXISTS',
					],
				],
			]
		);
		update_option( 'rank_math_gtin_products', $products, false );

		return $products;
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		$count = count( get_option( 'rank_math_gtin_products' ) );
		delete_option( 'rank_math_gtin_products' );
		Helper::add_notification(
			// Translators: placeholder is the number of modified products.
			sprintf( _n( 'GTIN value has been successfully migrated to the WooCommerce GTIN field for %d product.', 'GTIN values have been successfully migrated to the WooCommerce GTIN field for %d products.', $count, 'rank-math-pro' ), $count ),
			[
				'type'    => 'success',
				'id'      => 'rank_math_gtin_products',
				'classes' => 'rank-math-notice',
			]
		);

		update_option( 'rank_math_gtin_migrated', true, false );

		parent::complete();
	}
}
