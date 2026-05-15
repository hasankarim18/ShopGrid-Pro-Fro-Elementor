<?php

namespace PW\App\API;

defined('ABSPATH') || exit;

class CartController
{

    public static function add_to_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        $product_id = (int) $request->get_param('product_id');
        $quantity = (int) $request->get_param('quantity');

        if ($quantity < 1) {
            $quantity = 1;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Product not found.', 'woo-elementor-addon'),
            ]);
        }

        if ($product->is_type('variable')) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Variable products must be configured on the product page.', 'woo-elementor-addon'),
            ]);
        }

        if (!$product->is_in_stock()) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Product is out of stock.', 'woo-elementor-addon'),
            ]);
        }

        // ── Bug fix: properly initialise WC session + cart for REST context ──
        //
        // During a REST API request WordPress does NOT boot the WC session
        // handler automatically. Without a live session, WC creates a brand
        // new empty cart on every single request — so the second "Add to Cart"
        // click always sees an empty cart and only ever stores one item.
        //
        // Fix: initialise the session handler first, then load the cart from
        // that session. This mirrors what WooCommerce does on normal page loads.

        if (!WC()->session) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
        }

        if (!WC()->customer) {
            WC()->customer = new \WC_Customer(get_current_user_id(), true);
        }

        if (!WC()->cart) {
            WC()->cart = new \WC_Cart();
            WC()->cart->get_cart_from_session();
        }

        // ── Add the product ────────────────────────────────────────────────────
        $added = WC()->cart->add_to_cart($product_id, $quantity);

        if (false === $added) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Could not add product to cart.', 'woo-elementor-addon'),
            ]);
        }

        WC()->cart->calculate_totals();

        // ── Bug fix: save the session so the next request sees the updated cart ─
        //
        // WooCommerce normally saves the session at the end of a page request
        // via the 'shutdown' hook. That hook does NOT fire reliably during REST
        // requests, so the cart update is lost when the PHP process ends.
        //
        // Fix: force-save the session immediately after mutating the cart.
        WC()->session->save_data();

        return rest_ensure_response([
            'success' => true,
            'message' => __('Product added to cart.', 'woo-elementor-addon'),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_url' => wc_get_cart_url(),
        ]);
    }
}
