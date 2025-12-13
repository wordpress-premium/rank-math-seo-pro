<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- This filename format is intentionally used to match the plugin version.
/**
 * WooCommerce module.
 *
 * @since      1.0
 * @package    RankMathPro
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro;

use RankMath\Helper;
use RankMathPro\WooCommerce\Migrate_GTIN;
use RankMath\Traits\Hooker;
use RankMath\Schema\DB;
use RankMath\Schema\Product_WooCommerce;
use RankMath\Schema\Product;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce class.
 *
 * @codeCoverageIgnore
 */
class WooCommerce {

	use Hooker;

	/**
	 * Hold variesBy data to use in the ProductGroup schema.
	 *
	 * @var array
	 */
	private $varies_by = [];

	/**
	 * Whether to noindex and remove hidden products from the Sitemap.
	 *
	 * @var bool
	 */
	private $noindex_hidden_products;

	/**
	 * Include Products with specific statuses in the Sitemap.
	 *
	 * @var array
	 */
	private $exclude_stock_status = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->filter( 'rank_math/database/tools', 'add_gtin_migration_tool' );
		if ( is_admin() ) {
			new Admin();
			return;
		}

		$this->noindex_hidden_products = Helper::get_settings( 'general.noindex_hidden_products' );
		$this->exclude_stock_status    = $this->do_filter( 'woocommerce/stock_status', [] );

		Migrate_GTIN::get();
		$this->action( 'wp', 'init' );
		$this->filter( 'rank_math/json_ld', 'add_carousels', 11, 2 );
		$this->filter( 'rank_math/tools/migrate_gtin_values', 'migrate_gtin_values' );

		if ( ! $this->noindex_hidden_products && empty( $this->exclude_stock_status ) ) {
			return;
		}

		$this->filter( 'rank_math/sitemap/post_count/join', 'join_clause', 10, 2 );
		$this->filter( 'rank_math/sitemap/post_count/where', 'where_clause', 10, 2 );
		$this->filter( 'rank_math/sitemap/get_posts/join', 'join_clause', 10, 2 );
		$this->filter( 'rank_math/sitemap/get_posts/where', 'where_clause', 10, 2 );
	}

	/**
	 * Get JOIN clause for the sitemap query.
	 *
	 * @param string $join     JOIN clause.
	 * @param string $post_type Post type.
	 */
	public function join_clause( $join, $post_type ) {
		if ( 'product' !== $post_type ) {
			return $join;
		}

		global $wpdb;

		return $join . " INNER JOIN {$wpdb->prefix}postmeta ON p.ID = {$wpdb->prefix}postmeta.post_id ";
	}

	/**
	 * Get WHERE clause for the sitemap query.
	 *
	 * @param string $where    WHERE clause.
	 * @param string $post_type Post type.
	 */
	public function where_clause( $where, $post_type ) {
		if ( 'product' !== $post_type ) {
			return $where;
		}

		global $wpdb;

		if ( $this->exclude_stock_status ) {
			$where .= " AND {$wpdb->prefix}postmeta.meta_key = '_stock_status'
					AND {$wpdb->prefix}postmeta.meta_value IN ( '" . implode( "', '", $this->exclude_stock_status ) . "' )";
		}

		if ( $this->noindex_hidden_products ) {
			$where .= " AND NOT EXISTS (
						SELECT 1 FROM {$wpdb->prefix}term_relationships AS tr
						INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						INNER JOIN {$wpdb->prefix}terms AS t ON tt.term_id = t.term_id
						WHERE tr.object_id = p.ID
						AND tt.taxonomy = 'product_visibility'
						AND t.slug = 'exclude-from-catalog'
					)";
		}

		return $where;
	}

	/**
	 * Filter/Hooks to add GTIN value on Product page.
	 */
	public function init() {
		$this->filter( 'rank_math/frontend/robots', 'robots' );

		if ( ! is_product() ) {
			return;
		}

		$this->filter( 'rank_math/snippet/rich_snippet_product_entity', 'add_gtin_in_schema' );
		$this->filter( 'rank_math/woocommerce/product_brand', 'add_custom_product_brand' );
		$this->filter( 'rank_math/snippet/rich_snippet_product_entity', 'add_variations_data' );
		$this->action( 'rank_math/opengraph/facebook', 'og_retailer_id', 60 );
		$this->filter( 'rank_math/snippet/rich_snippet_product_entity', 'additional_schema_properties' );

		if ( Helper::get_settings( 'general.show_gtin' ) ) {
			$this->action( 'woocommerce_product_meta_start', 'add_gtin_meta' );
			$this->filter( 'woocommerce_available_variation', 'add_gtin_to_variation_param', 10, 3 );
			$this->action( 'wp_footer', 'add_variation_script' );
		}
	}

	/**
	 * Add carousels
	 *
	 * @param array  $data   Array of JSON-LD data.
	 * @param JsonLD $jsonld JsonLD Instance.
	 */
	public function add_carousels( $data, $jsonld ) {
		if ( ! isset( $data['ProductsPage'] ) ) {
			return $data;
		}

		$seller   = Product::get_seller( $jsonld );
		$items    = [];
		$position = 0;
		while ( have_posts() ) {
			the_post();

			$post_id = get_the_ID();
			$product = wc_get_product( $post_id );

			if ( empty( $product ) || 'grouped' === $product->get_type() ) {
				continue;
			}

			$single = [
				'@type'    => 'ListItem',
				'position' => ++$position,
				'item'     => [
					'@type' => 'Product',
					'name'  => $jsonld->get_product_title( $product ),
					'url'   => $jsonld->get_post_url( $post_id ),
				],
			];

			$images = Product_WooCommerce::get()->get_images( $product );
			if ( $images ) {
				$single['item']['image'] = $images;
			}

			$offers = Product_WooCommerce::get()->get_offers( $product, $seller );
			if ( $offers ) {
				$single['item']['offers'] = $offers;
			}

			$items[] = $single;
		}

		wp_reset_postdata();

		if ( ! empty( $items ) ) {
			unset( $data['ProductsPage'] );

			$data['ProductsCarousel'] = [
				'@context'        => 'https://schema.org/',
				'@type'           => 'ItemList',
				'itemListElement' => $items,
			];
		}

		return $data;
	}

	/**
	 * Change robots for WooCommerce pages according to settings
	 *
	 * @param array $robots Array of robots to sanitize.
	 *
	 * @return array Modified robots.
	 */
	public function robots( $robots ) {
		if ( ! $this->noindex_hidden_products ) {
			return $robots;
		}

		if ( is_product() ) {
			$product   = \wc_get_product();
			$is_hidden = $product && $product->get_catalog_visibility() === 'hidden';
			if ( $is_hidden ) {
				return [
					'noindex'  => 'noindex',
					'nofollow' => 'nofollow',
				];
			}
		}

		global $wp_query;
		if ( is_product_taxonomy() && ! $wp_query->post_count && $wp_query->queried_object->count ) {
			return [
				'noindex'  => 'noindex',
				'nofollow' => 'nofollow',
			];
		}

		return $robots;
	}

	/**
	 * Filter to change Product brand value based on the Settings.
	 *
	 * @param string $brand Brand.
	 *
	 * @return string Modified brand.
	 */
	public function add_custom_product_brand( $brand ) {
		return 'custom' === Helper::get_settings( 'general.product_brand' ) ? Helper::get_settings( 'general.custom_product_brand' ) : $brand;
	}

	/**
	 * Filter to add url, manufacturer & brand url in Product schema.
	 *
	 * @param  array $entity Snippet Data.
	 * @return array
	 *
	 * @since 2.7.0
	 */
	public function additional_schema_properties( $entity ) {
		if ( ! $this->do_filter( 'schema/woocommerce/additional_properties', false ) ) {
			return $entity;
		}

		$type                   = 'company' === Helper::get_settings( 'titles.knowledgegraph_type' ) ? 'organization' : 'person';
		$entity['manufacturer'] = [ '@id' => home_url( "/#{$type}" ) ];
		$entity['url']          = get_the_permalink();

		$taxonomy = Helper::get_settings( 'general.product_brand' );
		if ( ! empty( $entity['brand'] ) && $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$brands                 = get_the_terms( get_the_ID(), $taxonomy );
			$entity['brand']['url'] = is_wp_error( $brands ) || empty( $brands[0] ) ? '' : get_term_link( $brands[0], $taxonomy );
		}

		return $entity;
	}

	/**
	 * Filter to add GTIN in Product schema.
	 *
	 * @param array $entity Snippet Data.
	 * @return array
	 */
	public function add_gtin_in_schema( $entity ) {
		$gtin_key = Helper::get_settings( 'general.gtin', 'gtin8' );
		if ( ! empty( $entity[ $gtin_key ] ) ) {
			return $entity;
		}

		global $product;
		if ( ! is_object( $product ) ) {
			$product = wc_get_product( get_the_ID() );
		}

		$gtin = $this->get_gtin_value( $product );
		if ( $gtin ) {
			// Remove the default gtin property added in the Free plugin so it can be overwritten with a selected key from Settings.
			if ( isset( $entity['gtin'] ) ) {
				unset( $entity['gtin'] );
			}

			$entity[ $gtin_key ] = $gtin;
		}

		if ( ! empty( $entity['isbn'] ) ) {
			$entity['@type'] = [
				'Product',
				'Book',
			];
		}

		return $entity;
	}

	/**
	 * Add GTIN data in Product metadata.
	 */
	public function add_gtin_meta() {
		global $product;
		$gtin_code = $this->get_gtin_value( $product );
		if ( ! $gtin_code && ! $this->variations_have_gtin( $product ) ) {
			return;
		}

		$hidden = ! $gtin_code ? 'hidden' : '';

		echo '<span class="rank-math-gtin-wrapper" ' . esc_attr( $hidden ) . '>';
		echo esc_html( $this->get_formatted_value( $gtin_code ) );
		echo '</span>';
	}

	/**
	 * Add GTIN value to available variations.
	 *
	 * @param array  $args      Array of variation arguments.
	 * @param Object $product   Current Product Object.
	 * @param Object $variation Product variation.
	 *
	 * @return array Modified robots.
	 */
	public function add_gtin_to_variation_param( $args, $product, $variation ) {
		$gtin = $this->get_gtin_value( $variation );
		if ( ! $gtin ) {
			return $args;
		}

		$args['rank_math_gtin'] = $this->get_formatted_value( $gtin );

		return $args;
	}

	/**
	 * Variation script to change GTIN when variation is changed from the dropdown.
	 */
	public function add_variation_script() {
		global $product;
		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}
		$label = $this->get_gtin_label();
		?>
		<script>
			( function () {
				const $form = jQuery( '.variations_form' );
				const wrapper = jQuery( '.rank-math-gtin-wrapper' );
				const gtin_code = wrapper.text();

				function toggleAttributes( variation ) {
					variation.rank_math_gtin ? wrapper.removeAttr( 'hidden' ) :
						wrapper.attr( 'hidden', 'hidden' )
				}
				if ( $form.length ) {
					$form.on( 'found_variation', function( event, variation ) {
						toggleAttributes( variation )
						if ( variation.rank_math_gtin ) {
							wrapper.text( variation.rank_math_gtin );
						}
					} );

					$form.on( 'reset_data', function() {
						wrapper.text( gtin_code );

						if ( '<?php echo esc_attr( $label ); ?>' === gtin_code ) {
							toggleAttributes( { } )
						}
					} );
				}
			} )();
		</script>
		<?php
	}

	/**
	 * Filter to add Offers array in Product schema.
	 *
	 * @param array $entity Snippet Data.
	 * @return array
	 */
	public function add_variations_data( $entity ) {
		$product_id = get_the_ID();
		$product    = wc_get_product( $product_id );
		if ( ! $product->is_type( 'variable' ) ) {
			return $entity;
		}

		$schemas = array_filter(
			DB::get_schemas( $product_id ),
			function ( $schema ) {
				return $schema['@type'] === 'WooCommerceProduct';
			}
		);

		if ( empty( $schemas ) && Helper::get_default_schema_type( $product_id ) !== 'WooCommerceProduct' ) {
			return $entity;
		}

		$variations = $product->get_available_variations( 'object' );
		if ( empty( $variations ) ) {
			return $entity;
		}

		$entity['@type']          = 'ProductGroup';
		$entity['url']            = $product->get_permalink();
		$entity['productGroupID'] = ! empty( $entity['sku'] ) ? $entity['sku'] : $product_id;

		$this->add_variable_gtin( $product_id, $entity['offers'] );

		$variants = [];
		foreach ( $variations as $variation ) {
			$variants[] = $this->get_variant_data( $variation, $product );
		}

		$this->add_varies_by( $entity );
		$entity['hasVariant'] = $variants;

		unset( $entity['offers'] );

		return $entity;
	}

	/**
	 * Add product retailer ID to the OpenGraph output.
	 *
	 * @param OpenGraph $opengraph The current opengraph network object.
	 */
	public function og_retailer_id( $opengraph ) {
		$product = wc_get_product( get_the_ID() );
		if ( empty( $product ) || ! $product->get_sku() ) {
			return;
		}

		$opengraph->tag( 'product:retailer_item_id', $product->get_sku() );
	}

	/**
	 * Add GTIN migration tool.
	 *
	 * @param array $tools Array of tools.
	 *
	 * @return array
	 */
	public function add_gtin_migration_tool( $tools ) {
		if ( self::add_gtin_field() ) {
			return $tools;
		}

		$products = Migrate_GTIN::get()->find_posts();
		if ( empty( $products ) || get_option( 'rank_math_gtin_migrated' ) ) {
			return $tools;
		}

		$tools['migrate_gtin_values'] = [
			'title'       => esc_html__( 'GTIN Migration Tool for WooCommerce', 'rank-math-pro' ),
			'description' => esc_html__( 'Migrate GTIN values from the plugin into the native WooCommerce GTIN field.', 'rank-math-pro' ),
			'button_text' => esc_html__( 'Migrate', 'rank-math-pro' ),
		];

		return $tools;
	}

	/**
	 * Migrate GTIN values from the plugin into the native WooCommerce GTIN field.
	 */
	public function migrate_gtin_values() {
		$products = Migrate_GTIN::get()->find_posts();
		if ( empty( $products ) ) {
			return [
				'status'  => 'error',
				'message' => __( 'No products found to migrate.', 'rank-math-pro' ),
			];
		}

		Migrate_GTIN::get()->start( $products );

		return __( 'The GTIN values from the plugin are being transferred to the built-in WooCommerce GTIN field. This process runs in the background, and you\'ll receive a confirmation message once all product data has been successfully migrated. You can close this page.', 'rank-math-pro' );
	}

	/**
	 * Whether to add and use the GTIN value from the Rank Math plugin.
	 *
	 * @since 3.0.73
	 */
	public static function add_gtin_field() {
		return apply_filters( 'rank_math/woocommerce/add_gtin_field', false );
	}

	/**
	 * Get Variant data.
	 *
	 * @param Object     $variation Variation Object.
	 * @param WC_Product $product   Product Object.
	 *
	 * @since 3.0.57
	 */
	private function get_variant_data( $variation, $product ) {
		$description = $this->get_variant_description( $variation, $product );
		$description = $this->do_filter( 'product_description/apply_shortcode', false ) ? do_shortcode( $description ) : Helper::strip_shortcodes( $description );
		$variant     = [
			'@type'       => 'Product',
			'sku'         => $variation->get_sku(),
			'name'        => $variation->get_name(),
			'description' => wp_strip_all_tags( $description, true ),
			'image'       => wp_get_attachment_image_url( $variation->get_image_id() ),
		];

		$this->add_variable_attributes( $variation, $variant );
		$this->add_variable_offer( $variation, $variant );
		$this->add_variable_gtin( $variation->get_id(), $variant );

		return $variant;
	}

	/**
	 * Add gtin value in variable offer data.
	 *
	 * @param int   $variation_id Variation ID.
	 * @param array $entity       Offer entity.
	 */
	private function add_variable_gtin( $variation_id, &$entity ) {
		$meta_key = self::add_gtin_field() ? '_rank_math_gtin_code' : '_global_unique_id';
		$gtin_key = Helper::get_settings( 'general.gtin', 'gtin8' );
		$gtin     = get_post_meta( $variation_id, $meta_key, true );
		if ( ! $gtin || 'isbn' === $gtin_key ) {
			return;
		}

		$entity[ $gtin_key ] = $gtin;
	}

	/**
	 * Get GTIN value from Product object.
	 *
	 * @param WC_Product $product Product Object.
	 *
	 * @since 3.0.73
	 */
	private function get_gtin_value( $product ) {
		if ( self::add_gtin_field() ) {
			return $product->get_meta( '_rank_math_gtin_code' );
		}

		return method_exists( $product, 'get_global_unique_id' ) ? $product->get_global_unique_id() : '';
	}

	/**
	 * Get Variant description.
	 *
	 * @param Object     $variation Variation Object.
	 * @param WC_Product $product   Product Object.
	 *
	 * @since 3.0.61
	 */
	private function get_variant_description( $variation, $product ) {
		if ( $variation->get_description() ) {
			return $variation->get_description();
		}

		return $product->get_short_description() ? $product->get_short_description() : $product->get_description();
	}

	/**
	 * Add variesBy property to product data.
	 *
	 * @param Object $entity Product data.
	 *
	 * @since 3.0.57
	 */
	private function add_varies_by( &$entity ) {
		if ( empty( $this->varies_by ) ) {
			return;
		}

		$valid_values = [
			'color'    => 'https://schema.org/color',
			'size'     => 'https://schema.org/size',
			'age'      => 'https://schema.org/suggestedAge',
			'gender'   => 'https://schema.org/suggestedGender',
			'material' => 'https://schema.org/material',
			'pattern'  => 'https://schema.org/pattern',
		];

		$varies_by = [];
		foreach ( array_unique( $this->varies_by ) as $attribute ) {
			if ( isset( $valid_values[ $attribute ] ) ) {
				$varies_by[] = $valid_values[ $attribute ];
			}
		}

		if ( ! empty( $varies_by ) ) {
			$entity['variesBy'] = array_unique( $varies_by );
		}
	}

	/**
	 * Add gtin value in variable offer datta.
	 *
	 * @param Object $variation Variation Object.
	 * @param array  $entity    Variant entity.
	 *
	 * @since 3.0.57
	 */
	private function add_variable_offer( $variation, &$entity ) {
		$price_valid_until = get_post_meta( $variation->get_id(), '_sale_price_dates_to', true );
		if ( ! $price_valid_until ) {
			$price_valid_until = strtotime( ( date( 'Y' ) + 1 ) . '-12-31' );
		}

		$entity['offers'] = [
			'@type'           => 'Offer',
			'description'     => ! empty( $entity['description'] ) ? $entity['description'] : '',
			'price'           => wc_get_price_to_display( $variation ),
			'priceCurrency'   => get_woocommerce_currency(),
			'availability'    => 'outofstock' === $variation->get_stock_status() ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
			'itemCondition'   => 'NewCondition',
			'priceValidUntil' => date_i18n( 'Y-m-d', $price_valid_until ),
			'url'             => $variation->get_permalink(),
		];
	}

	/**
	 * Add attributes value in variable offer datta.
	 *
	 * @param Object $variation Variation Object.
	 * @param array  $variant   Variant entity.
	 *
	 * @since 3.0.57
	 */
	private function add_variable_attributes( $variation, &$variant ) {
		if ( empty( $variation->get_attributes() ) ) {
			return;
		}

		foreach ( $variation->get_attributes() as $key => $value ) {
			if ( ! $value ) {
				continue;
			}

			$key = str_replace( 'pa_', '', $key );
			if ( ! in_array( $key, [ 'color', 'size', 'material', 'pattern', 'weight' ], true ) ) {
				continue;
			}

			$variant[ $key ]   = $value;
			$this->varies_by[] = $key;
		}
	}

	/**
	 * Get formatted GTIN value with label.
	 *
	 * @param string $gtin GTIN code.
	 *
	 * @return string Formatted GTIN value with label.
	 */
	private function get_formatted_value( $gtin ) {
		return esc_html( $this->get_gtin_label() . $gtin );
	}

	/**
	 * Checks if any of the variations have a gtin value.
	 *
	 * @param Object $product The WC Product object.
	 *
	 * @return bool
	 */
	private function variations_have_gtin( $product ) {
		if ( ! $product->has_child() ) {
			return false;
		}

		$args = [
			'parent'     => $product->get_id(),
			'type'       => 'variation',
			'visibility' => 'visible',
		];

		$has_gtin = array_filter(
			wc_get_products( $args ),
			function ( \WC_Product_Variation $variation ) {
				return ! empty( self::get_gtin_value( $variation ) );
			}
		);

		return ! empty( $has_gtin );
	}

	/**
	 * Get GTIN label.
	 *
	 * @return string The GTIN label.
	 */
	private function get_gtin_label() {
		$label = Helper::get_settings( 'general.gtin_label' );
		$label = $label ? $label . ' ' : '';
		return $this->do_filter( 'woocommerce/gtin_label', $label );
	}
}
