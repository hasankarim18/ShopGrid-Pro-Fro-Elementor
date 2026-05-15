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

// Add more template functions as needed