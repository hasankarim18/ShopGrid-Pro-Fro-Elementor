<?php

namespace PW\App\Assets;

defined('ABSPATH') || exit;

class AssetsManager
{

    public static function boot(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('elementor/editor/before_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('elementor/preview/', [__CLASS__, 'enqueue']);
    }

    public static function enqueue(): void
    {
        wp_enqueue_style(
            'pw-widget-style',
            PW_URL . 'assets/css/widget.css',
            [],
            PW_VERSION
        );

        wp_enqueue_script(
            'pw-widget-script',
            PW_URL . 'assets/js/widget.js',
            ['jquery'],
            PW_VERSION,
            true
        );

        wp_localize_script('pw-widget-script', 'PW_CONFIG', [
            'api_base' => esc_url_raw(rest_url('pw/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'is_logged' => is_user_logged_in() ? 1 : 0,
            'cart_url' => wc_get_cart_url(),
        ]);
    }
}
