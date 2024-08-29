<?php
/**
 * WooCommerce module.
 *
 * @since      1.0
 * @package    RankMathPro
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMathPro;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use RankMath\Schema\DB;

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
	 * Constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			new Admin();
			return;
		}
		$this->filter( 'rank_math/sitemap/entry', 'remove_hidden_products', 10, 3 );
		$this->action( 'wp', 'init' );
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
	 * Remove hidden products from the sitemap.
	 *
	 * @param array  $url    Array of URL parts.
	 * @param string $type   URL type. Can be user, post or term.
	 * @param object $object Data object for the URL.
	 */
	public function remove_hidden_products( $url, $type, $object ) {
		if (
			'post' !== $type ||
			! isset( $object->post_type ) ||
			'product' !== $object->post_type ||
			! Helper::get_settings( 'general.noindex_hidden_products' ) ||
			'hidden' !== \wc_get_product( $object->ID )->get_catalog_visibility()
		) {
			return $url;
		}

		return false;
	}

	/**
	 * Change robots for WooCommerce pages according to settings
	 *
	 * @param array $robots Array of robots to sanitize.
	 *
	 * @return array Modified robots.
	 */
	public function robots( $robots ) {
		if ( ! Helper::get_settings( 'general.noindex_hidden_products' ) ) {
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
			$brands                 = get_the_terms( $product_id, $taxonomy );
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

		$gtin = $product->get_meta( '_rank_math_gtin_code' );
		if ( $gtin ) {
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
		$gtin_code = $product->get_meta( '_rank_math_gtin_code' );
		if ( ! $gtin_code ) {
			return;
		}

		echo '<span class="rank-math-gtin-wrapper">';
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
		$args['rank_math_gtin'] = $this->get_formatted_value( $variation->get_meta( '_rank_math_gtin_code' ) );

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
		?>
		<script>
			const $form = jQuery( '.variations_form' );
			const wrapper = jQuery( '.rank-math-gtin-wrapper' );
			const gtin_code = wrapper.text();
			if ( $form.length ) {
				$form.on( 'found_variation', function( event, variation ) {
					if ( variation.rank_math_gtin ) {
						wrapper.text( variation.rank_math_gtin );
					}
				} );

				$form.on( 'reset_data', function() {
					wrapper.text( gtin_code );
				} );
			}
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
			function( $schema ) {
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
	 * @param int   $variation_id Variation ID.
	 * @param array $entity       Offer entity.
	 */
	private function add_variable_gtin( $variation_id, &$entity ) {
		$gtin_key = Helper::get_settings( 'general.gtin', 'gtin8' );
		$gtin     = get_post_meta( $variation_id, '_rank_math_gtin_code', true );
		if ( ! $gtin || 'isbn' === $gtin_key ) {
			return;
		}

		$entity[ $gtin_key ] = $gtin;
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
		$label = Helper::get_settings( 'general.gtin_label' );
		$label = $label ? $label . ' ' : '';

		return esc_html( $this->do_filter( 'woocommerce/gtin_label', $label ) . $gtin );
	}
}
