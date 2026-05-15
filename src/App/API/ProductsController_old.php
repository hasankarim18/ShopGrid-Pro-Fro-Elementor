<?php

namespace PW\App\API;

use PW\App\Query\ProductQuery;
use PW\App\Wishlist\WishlistRepository;

defined('ABSPATH') || exit;

class ProductsController
{

    public static function get_products(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = [
            'page' => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
            'search' => sanitize_text_field($request->get_param('search')),
            'category' => sanitize_text_field($request->get_param('category')),
            'sort' => sanitize_key($request->get_param('sort')),
        ];

        $query_result = ProductQuery::run($params);

        $user_id = get_current_user_id();
        $wishlist_ids = $user_id ? WishlistRepository::get_product_ids($user_id) : [];

        $products = array_map(function ($post) use ($wishlist_ids) {
            return self::format_product($post->ID, $wishlist_ids);
        }, $query_result['posts']);

        return rest_ensure_response([
            'success' => true,
            'data' => [
                'user_id' => 'admin',
                'products' => $products,
                'pagination' => $query_result['pagination'],
            ],
        ]);
    }

    private static function format_product(int $product_id, array $wishlist_ids): array
    {
        $product = wc_get_product($product_id);

        if (!$product) {
            return [];
        }

        $type = $product->is_type('variable') ? 'variable' : 'simple';

        // Get main image
        $image_id = $product->get_image_id();
        $image_url = $image_id
            ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail')
            : wc_placeholder_img_src('woocommerce_thumbnail');

        // Price
        $price = $product->get_price_html();

        // Rating
        $rating = $product->get_average_rating();
        $rating_count = $product->get_rating_count();

        return [
            'id' => $product_id,
            'title' => $product->get_name(),
            'price' => $price,
            'price_raw' => (float) $product->get_price(),
            'image' => esc_url($image_url),
            'permalink' => get_permalink($product_id),
            'type' => $type,
            'wishlist' => in_array($product_id, $wishlist_ids, true),
            'in_stock' => $product->is_in_stock(),
            'rating' => $rating,
            'rating_count' => $rating_count,
            'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
        ];
    }
}
