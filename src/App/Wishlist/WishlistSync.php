<?php

namespace PW\App\Wishlist;

defined( 'ABSPATH' ) || exit;

class WishlistSync {

    public static function boot(): void {
        // When user logs in, sync guest wishlist from the JS-side via an AJAX call.
        add_action( 'rest_api_init', [ __CLASS__, 'register_sync_route' ] );
    }

    public static function register_sync_route(): void {
        register_rest_route( 'pw/v1', '/wishlist/sync', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'sync' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => [
                'product_ids' => [
                    'type'     => 'array',
                    'required' => true,
                    'items'    => [ 'type' => 'integer' ],
                ],
            ],
        ] );
    }

    public static function sync( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id     = get_current_user_id();
        $product_ids = array_map( 'intval', $request->get_param( 'product_ids' ) );
        $synced      = 0;

        foreach ( $product_ids as $pid ) {
            if ( $pid > 0 && wc_get_product( $pid ) ) {
                if ( ! WishlistRepository::is_wishlisted( $user_id, $pid ) ) {
                    WishlistRepository::add( $user_id, $pid );
                    $synced++;
                }
            }
        }

        return rest_ensure_response( [
            'success' => true,
            'synced'  => $synced,
        ] );
    }
}
