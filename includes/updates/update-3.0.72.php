<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * The Updates routine for version 3.0.72.
 *
 * @since      3.0.72
 * @package    RankMathPro
 * @subpackage RankMathPro\Updates
 * @author     Rank Math <support@rankmath.com>
 */

use RankMath\Helper;
use RankMathPro\WooCommerce\Migrate_GTIN;

defined( 'ABSPATH' ) || exit;

/**
 * This code is needed to flush the new rewrite rules we added to fix the Code Validation issue.
 */
function rank_math_pro_3_0_72_migrate_gtin() {
	if ( ! Helper::is_module_active( 'woocommerce' ) || ! defined( 'WOOCOMMERCE_VERSION' ) || version_compare( WOOCOMMERCE_VERSION, '9.1', '<' ) ) {
		return;
	}

	$products = Migrate_GTIN::get()->find_posts();
	if ( empty( $products ) ) {
		return;
	}

	Migrate_GTIN::get()->start( $products );

	Helper::add_notification(
		// Translators: placeholder is the number of modified products.
		__( 'The GTIN values from the plugin are being transferred to the built-in WooCommerce GTIN field. This process runs in the background, and you\'ll receive a confirmation message once all product data has been successfully migrated. You can close this page.', 'rank-math-pro' ),
		[
			'type'    => 'success',
			'id'      => 'rank_math_gtin_products',
			'classes' => 'rank-math-notice',
		]
	);
}

rank_math_pro_3_0_72_migrate_gtin();
