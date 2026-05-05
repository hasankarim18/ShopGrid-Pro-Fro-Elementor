<?php

namespace PW\App\API;

use PW\App\Wishlist\WishlistRepository;

defined( 'ABSPATH' ) || exit;

class WishlistController {

    public static function toggle( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id    = get_current_user_id();
        $product_id = (int) $request->get_param( 'product_id' );

        if ( ! wc_get_product( $product_id ) ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Product not found.', 'woo-elementor-addon' ),
            ] );
        }

        $already = WishlistRepository::is_wishlisted( $user_id, $product_id );

        if ( $already ) {
            WishlistRepository::remove( $user_id, $product_id );
            $wishlisted = false;
            $message    = __( 'Removed from wishlist.', 'woo-elementor-addon' );
        } else {
            WishlistRepository::add( $user_id, $product_id );
            $wishlisted = true;
            $message    = __( 'Added to wishlist.', 'woo-elementor-addon' );
        }

        return rest_ensure_response( [
            'success'    => true,
            'wishlisted' => $wishlisted,
            'product_id' => $product_id,
            'message'    => $message,
        ] );
    }

    public static function get_wishlist( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = get_current_user_id();
        $ids     = WishlistRepository::get_product_ids( $user_id );

        return rest_ensure_response( [
            'success'     => true,
            'product_ids' => $ids,
        ] );
    }
}
