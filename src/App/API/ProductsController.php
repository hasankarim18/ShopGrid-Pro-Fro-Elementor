<?php

namespace PW\App\API;

use PW\App\Query\ProductQuery;
use PW\App\Wishlist\WishlistRepository;

defined( 'ABSPATH' ) || exit;

class ProductsController {

    public static function get_products( \WP_REST_Request $request ): \WP_REST_Response {
        $params = [
            'page'     => (int) $request->get_param( 'page' ),
            'per_page' => (int) $request->get_param( 'per_page' ),
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ),
            'category' => sanitize_text_field( $request->get_param( 'category' ) ),
            'sort'     => sanitize_key( $request->get_param( 'sort' ) ),
        ];

        $query_result = ProductQuery::run( $params );

        $user_id      = get_current_user_id();
        $wishlist_ids = $user_id ? WishlistRepository::get_product_ids( $user_id ) : [];

        $products = array_map( function ( $post ) use ( $wishlist_ids ) {
            return self::format_product( $post->ID, $wishlist_ids );
        }, $query_result['posts'] );

        // Remove any empty entries (format_product returns [] on failure)
        $products = array_values( array_filter( $products ) );

        return rest_ensure_response( [
            'success' => true,
            'data'    => [
                'products'   => $products,
                'pagination' => $query_result['pagination'],
            ],
        ] );
    }

    private static function format_product( int $product_id, array $wishlist_ids ): array {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return [];
        }

        // ── Detect all four WooCommerce product types ──────────────────────────
        //
        // simple   → Add to Cart via AJAX
        // variable → View Details  (needs variant selection on product page)
        // grouped  → View Details  (e.g. "Logo Collection" — bundle of sub-products)
        // external → Buy Now link  (opens the external URL, e.g. WordPress swag store)
        //
        // Previously only simple vs variable was checked, so grouped and external
        // both fell through as "simple" — causing wrong buttons and failed cart adds.
        if ( $product->is_type( 'variable' ) ) {
            $type = 'variable';
        } elseif ( $product->is_type( 'grouped' ) ) {
            $type = 'grouped';
        } elseif ( $product->is_type( 'external' ) ) {
            $type = 'external';
        } else {
            $type = 'simple';
        }

        // For external products, retrieve the custom buy URL and button label
        // that the store admin set in WP Admin → Product → Product Data → External.
        $buy_url     = '';
        $button_text = '';
        if ( $type === 'external' ) {
            /** @var \WC_Product_External $product */
            $buy_url     = esc_url( $product->get_product_url() );
            $button_text = $product->get_button_text() ?: 'Buy Now';
        }

        // Main image
        $image_id  = $product->get_image_id();
        $image_url = $image_id
            ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
            : wc_placeholder_img_src( 'woocommerce_thumbnail' );

        // Price HTML (WooCommerce renders sale prices, ranges etc.)
        $price = $product->get_price_html();

        // Rating
        $rating       = $product->get_average_rating();
        $rating_count = $product->get_rating_count();

        return [
            'id'          => $product_id,
            'title'       => $product->get_name(),
            'price'       => $price,
            'price_raw'   => (float) $product->get_price(),
            'image'       => esc_url( $image_url ),
            'permalink'   => get_permalink( $product_id ),
            'type'        => $type,        // simple | variable | grouped | external
            'buy_url'     => $buy_url,     // external products only
            'button_text' => $button_text, // external products only
            'wishlist'    => in_array( $product_id, $wishlist_ids, true ),
            'in_stock'    => $product->is_in_stock(),
            'rating'      => $rating,
            'rating_count' => $rating_count,
            'categories'  => wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] ),
        ];
    }
}
