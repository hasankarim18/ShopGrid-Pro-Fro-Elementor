<?php


// autoload
if (!function_exists('spl_autoload_register')) {
    function spl_autoload_register()
    {
    }
}

/**
 * WooCommerce procedural/template function stubs for IDE
 * These do nothing at runtime but fix Intelephense autocomplete
 */

if (!function_exists('woocommerce_product_loop_start')) {
    function woocommerce_product_loop_start()
    {
    }
}
if (!function_exists('wc_get_cart_url')) {
    function wc_get_cart_url()
    {
    }
}

if (!function_exists('woocommerce_product_loop_end')) {
    function woocommerce_product_loop_end()
    {
    }
}

if (!function_exists('woocommerce_template_loop_product_title')) {
    function woocommerce_template_loop_product_title()
    {
    }
}

if (!function_exists('woocommerce_output_related_products')) {
    function woocommerce_output_related_products()
    {
    }
}
if (!function_exists('woocommerce_product_loop')) {
    function woocommerce_product_loop()
    {
    }
}
if (!function_exists('wc_get_loop_prop')) {
    function wc_get_loop_prop()
    {
    }
}
if (!function_exists('wc_get_template_part')) {
    function wc_get_template_part()
    {
    }
}

//
if (!function_exists('woocommerce_template_loop_add_to_cart')) {
    function woocommerce_template_loop_add_to_cart()
    {
    }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product()
    {
    }
}
if (!function_exists('wc_placeholder_img_src')) {
    function wc_placeholder_img_src()
    {
    }
}

if (!class_exists('WC_Session_Handler')) {
    class WC_Session_Handler
    {
    }
}
if (!class_exists('WC_Customer')) {
    class WC_Customer
    {
    }
}

// woo commerce<?php
/**
 * Custom Stubs for WooCommerce in VSCode Intelephense
 */

if (!function_exists('WC')) {
    /**
     * Returns the main instance of WooCommerce.
     *
     * @return WooCommerce
     */
    function WC()
    {
        return WooCommerce::instance();
    }
}

if (!class_exists('WooCommerce')) {
    class WooCommerce
    {
        /**
         * @var WC_Customer
         */
        public $customer;

        /**
         * @var WC_Session_Handler
         */
        public $session;
        public $cart;

        public static function instance()
        {
            return new self();
        }
    }
}

if (!class_exists('WC_Customer')) {
    class WC_Customer
    {
        public function __construct($user_id = 0, $is_active = true)
        {
        }
    }
}
if (!class_exists('WC_Cart')) {
    class WC_Cart
    {
        public function __construct($user_id = 0, $is_active = true)
        {
        }
    }
}

if (!class_exists('WC_Session_Handler')) {
    class WC_Session_Handler
    {
        /**
         * Initialize session handler.
         *
         * @return void
         */
        public function init()
        {
        }

        public function save_data()
        {
        }
    }
}
