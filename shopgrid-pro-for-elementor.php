<?php
/**
 * Plugin Name: ShopGrid Pro Fro Elementor
 * Plugin URI:  https://example.com
 * Description: A WooCommerce-focused Elementor addon with AJAX-powered product widgets, wishlist, quick view, and cart system.
 * Version:     1.0.0
 * Author:      Hasan
 * Text Domain: shop-grid-pro-for-elementor
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

define('PW_VERSION', '1.0.0');
define('PW_FILE', __FILE__);
define('PW_PATH', plugin_dir_path(__FILE__));
define('PW_URL', plugin_dir_url(__FILE__));
define('PW_TEXT_DOMAIN', 'shop-grid-pro-for-elementor');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'PW\\';
    $base = PW_PATH . 'src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Boot
add_action('plugins_loaded', function () {
    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('WooCommerce Elementor Addon requires Elementor to be installed and active.', PW_TEXT_DOMAIN) .
                '</p></div>';
        });
        return;
    }

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('WooCommerce Elementor Addon requires WooCommerce to be installed and active.', PW_TEXT_DOMAIN) .
                '</p></div>';
        });
        return;
    }

    \PW\Main::instance();
});

// Activation
register_activation_hook(__FILE__, function () {
    \PW\App\Wishlist\WishlistTable::create();
});
