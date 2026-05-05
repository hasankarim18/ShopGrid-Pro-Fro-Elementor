<?php

namespace PW\App\API;

defined( 'ABSPATH' ) || exit;

class CartController {

    public static function add_to_cart( \WP_REST_Request $request ): \WP_REST_Response {
        $product_id = (int) $request->get_param( 'product_id' );
        $quantity   = (int) $request->get_param( 'quantity' );

        if ( $quantity < 1 ) {
            $quantity = 1;
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Product not found.', 'woo-elementor-addon' ),
            ] );
        }

        if ( $product->is_type( 'variable' ) ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Variable products must be configured on the product page.', 'woo-elementor-addon' ),
            ] );
        }

        if ( ! $product->is_in_stock() ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Product is out of stock.', 'woo-elementor-addon' ),
            ] );
        }

        // Ensure WC session & cart are loaded
        if ( ! WC()->cart ) {
            wc_load_cart();
        }

        $added = WC()->cart->add_to_cart( $product_id, $quantity );

        if ( false === $added ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Could not add product to cart.', 'woo-elementor-addon' ),
            ] );
        }

        WC()->cart->calculate_totals();

        return rest_ensure_response( [
            'success'    => true,
            'message'    => __( 'Product added to cart.', 'woo-elementor-addon' ),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_url'   => wc_get_cart_url(),
        ] );
    }
}
