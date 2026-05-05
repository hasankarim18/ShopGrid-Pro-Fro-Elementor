<?php

namespace PW\App\ProductListType;

defined('ABSPATH') || exit;

class ProductListWidget extends \Elementor\Widget_Base
{

    public function get_name(): string
    {
        return 'pw_product_list';
    }

    public function get_title(): string
    {
        return __('Product List', 'woo-elementor-addon');
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    public function get_categories(): array
    {
        return ['woocommerce-elements'];
    }

    public function get_keywords(): array
    {
        return ['woocommerce', 'products', 'list', 'shop', 'shopgrid'];
    }

    protected function register_controls(): void
    {

        $this->start_controls_section('section_products', [
            'label' => __('Products', 'woo-elementor-addon'),
        ]);

        $this->add_control('posts_per_page', [
            'label' => __('Products Per Page', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min' => 1,
            'max' => 100,
        ]);

        $this->add_control('category', [
            'label' => __('Default Category', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('orderby', [
            'label' => __('Default Sort', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'default',
            'options' => [
                'default' => __('Default', 'woo-elementor-addon'),
                'price_low' => __('Price: Low → High', 'woo-elementor-addon'),
                'price_high' => __('Price: High → Low', 'woo-elementor-addon'),
                'alphabet' => __('A → Z', 'woo-elementor-addon'),
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_features', [
            'label' => __('Features', 'woo-elementor-addon'),
        ]);

        $this->add_control('show_pagination', [
            'label' => __('Enable Pagination', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_filters', [
            'label' => __('Enable Filters', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_search', [
            'label' => __('Enable Search', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_wishlist', [
            'label' => __('Enable Wishlist', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();

        $config = [
            'id' => $widget_id,
            'layout' => 'list',
            'per_page' => (int) $settings['posts_per_page'],
            'columns' => 1,
            'category' => sanitize_text_field($settings['category']),
            'sort' => sanitize_key($settings['orderby']),
            'pagination' => $settings['show_pagination'] === 'yes',
            'filters' => $settings['show_filters'] === 'yes',
            'search' => $settings['show_search'] === 'yes',
            'wishlist' => $settings['show_wishlist'] === 'yes',
        ];

        echo '<div class="pw-widget pw-widget--list" data-id="' . esc_attr($widget_id) . '" data-settings="' . esc_attr(wp_json_encode($config)) . '"></div>';
    }
}
