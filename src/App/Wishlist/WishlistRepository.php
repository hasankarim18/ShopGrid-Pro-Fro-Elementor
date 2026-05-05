<?php

namespace PW\App\Wishlist;

defined( 'ABSPATH' ) || exit;

class WishlistRepository {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pw_wishlist';
    }

    public static function add( int $user_id, int $product_id ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            self::table(),
            [
                'user_id'    => $user_id,
                'product_id' => $product_id,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s' ]
        );

        return $result !== false;
    }

    public static function remove( int $user_id, int $product_id ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            self::table(),
            [ 'user_id' => $user_id, 'product_id' => $product_id ],
            [ '%d', '%d' ]
        );

        return $result !== false;
    }

    public static function is_wishlisted( int $user_id, int $product_id ): bool {
        global $wpdb;

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE user_id = %d AND product_id = %d",
            self::table(),
            $user_id,
            $product_id
        ) );

        return (int) $count > 0;
    }

    public static function get_product_ids( int $user_id ): array {
        global $wpdb;

        $results = $wpdb->get_col( $wpdb->prepare(
            "SELECT product_id FROM %i WHERE user_id = %d",
            self::table(),
            $user_id
        ) );

        return array_map( 'intval', $results );
    }
}
