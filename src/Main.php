<?php

namespace PW;

defined('ABSPATH') || exit;

class Main
{

    private static ?Main $instance = null;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        // Register Elementor widgets
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // Boot subsystems
        App\API\RestAPI::boot();
        App\Assets\AssetsManager::boot();
        App\Wishlist\WishlistSync::boot();
    }

    public function register_widgets(\Elementor\Widgets_Manager $manager): void
    {
        $manager->register(new App\ProductGridType\ProductGridWidget());
        $manager->register(new App\ProductListType\ProductListWidget());
    }
}
