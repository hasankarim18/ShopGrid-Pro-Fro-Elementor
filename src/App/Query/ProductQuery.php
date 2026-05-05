<?php

namespace PW\App\Query;

defined( 'ABSPATH' ) || exit;

class ProductQuery {

    public static function run( array $params ): array {
        $page     = max( 1, (int) ( $params['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $params['per_page'] ?? 12 ) ) );
        $search   = sanitize_text_field( $params['search'] ?? '' );
        $category = sanitize_text_field( $params['category'] ?? '' );
        $sort     = sanitize_key( $params['sort'] ?? 'default' );

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ];

        // Search
        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        // Category
        if ( ! empty( $category ) ) {
            $args['tax_query'] = [ [
                'taxonomy'         => 'product_cat',
                'field'            => is_numeric( $category ) ? 'term_id' : 'slug',
                'terms'            => $category,
                'include_children' => true,
            ] ];
        }

        // Sorting
        switch ( $sort ) {
            case 'price_low':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;
            case 'price_high':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'alphabet':
                $args['orderby'] = 'title';
                $args['order']   = 'ASC';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;
        }

        // Cache key
        $cache_key = 'pw_query_' . md5( serialize( $args ) );
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        $query = new \WP_Query( $args );

        $result = [
            'posts'      => $query->posts,
            'pagination' => [
                'current' => $page,
                'total'   => max( 1, (int) $query->max_num_pages ),
                'total_products' => (int) $query->found_posts,
            ],
        ];

        set_transient( $cache_key, $result, 60 ); // 1 minute cache

        return $result;
    }
}
