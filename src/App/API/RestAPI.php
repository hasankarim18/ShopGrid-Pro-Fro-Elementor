<?php

namespace PW\App\API;

defined( 'ABSPATH' ) || exit;

class RestAPI {

    public static function boot(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        // Products endpoint
        register_rest_route( 'pw/v1', '/products', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ ProductsController::class, 'get_products' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                'per_page' => [ 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 100 ],
                'search'   => [ 'type' => 'string',  'default' => '' ],
                'category' => [ 'type' => 'string',  'default' => '' ],
                'sort'     => [ 'type' => 'string',  'default' => 'default', 'enum' => [ 'default', 'price_low', 'price_high', 'alphabet' ] ],
            ],
        ] );

        // Add to cart endpoint
        register_rest_route( 'pw/v1', '/cart/add', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ CartController::class, 'add_to_cart' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
                'quantity'   => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
            ],
        ] );

        // Wishlist toggle
        register_rest_route( 'pw/v1', '/wishlist/toggle', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ WishlistController::class, 'toggle' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'product_id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
            ],
        ] );

        // Wishlist status
        register_rest_route( 'pw/v1', '/wishlist', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ WishlistController::class, 'get_wishlist' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ] );
    }
}
