<?php

namespace PW\App\ProductGridType;

defined('ABSPATH') || exit;

class ProductGridWidget extends \Elementor\Widget_Base
{

    public function get_name(): string
    {
        return 'pw_product_grid';
    }

    public function get_title(): string
    {
        return __('Product Grid', 'woo-elementor-addon');
    }

    public function get_icon(): string
    {
        return 'eicon-products';
    }

    public function get_categories(): array
    {
        return ['woocommerce-elements'];
    }

    public function get_keywords(): array
    {
        return ['woocommerce', 'products', 'grid', 'shop', 'shopgrid'];
    }

    protected function register_controls(): void
    {

        // ─── Content: Products ─────────────────────────────────────────────
        $this->start_controls_section('section_products', [
            'label' => __('Products', 'woo-elementor-addon'),
        ]);

        $this->add_control('posts_per_page', [
            'label' => __('Products Per Page', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 12,
            'min' => 1,
            'max' => 100,
        ]);

        $this->add_control('columns', [
            'label' => __('Columns (Desktop)', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => '3',
            'options' => [
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
            ],
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

        // ─── Content: Features ─────────────────────────────────────────────
        $this->start_controls_section('section_features', [
            'label' => __('Features', 'woo-elementor-addon'),
        ]);

        $this->add_control('show_pagination', [
            'label' => __('Enable Pagination', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'woo-elementor-addon'),
            'label_off' => __('No', 'woo-elementor-addon'),
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_filters', [
            'label' => __('Enable Filters', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'woo-elementor-addon'),
            'label_off' => __('No', 'woo-elementor-addon'),
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_search', [
            'label' => __('Enable Search', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'woo-elementor-addon'),
            'label_off' => __('No', 'woo-elementor-addon'),
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('show_wishlist', [
            'label' => __('Enable Wishlist', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'woo-elementor-addon'),
            'label_off' => __('No', 'woo-elementor-addon'),
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->end_controls_section();

        // ─── Style: Card ───────────────────────────────────────────────────
        $this->start_controls_section('section_style_card', [
            'label' => __('Card Style', 'woo-elementor-addon'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg', [
            'label' => __('Card Background', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .pw-product-card' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('card_border_radius', [
            'label' => __('Border Radius', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => ['px' => ['min' => 0, 'max' => 40]],
            'default' => ['size' => 12, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .pw-product-card' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('btn_color', [
            'label' => __('Button Color', 'woo-elementor-addon'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .pw-add-to-cart' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();


        // ── Editor: render a static PHP preview so the designer sees real products ──
        // This only runs inside the Elementor editor/preview panel.
        // The live frontend always uses the AJAX path below — untouched.
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $this->render_editor_preview($settings);
            return;
        }

        $config = [
            'id' => $widget_id,
            'layout' => 'grid',
            'per_page' => (int) $settings['posts_per_page'],
            'columns' => (int) $settings['columns'],
            'category' => sanitize_text_field($settings['category']),
            'sort' => sanitize_key($settings['orderby']),
            'pagination' => $settings['show_pagination'] === 'yes',
            'filters' => $settings['show_filters'] === 'yes',
            'search' => $settings['show_search'] === 'yes',
            'wishlist' => $settings['show_wishlist'] === 'yes',
        ];

        echo '<div class="pw-widget pw-widget--grid" data-id="' . esc_attr($widget_id) . '" data-settings="' . esc_attr(wp_json_encode($config)) . '"></div>';
    }

    private function render_editor_preview($settings)
    {

        $columns = (int) ($settings['columns'] ?? 3);
        $per_page = min((int) ($settings['posts_per_page'] ?? 6), 6); // cap at 6 for editor speed
        $category = sanitize_text_field($settings['category'] ?? '');

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
        ];

        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => is_numeric($category) ? 'term_id' : 'slug',
                    'terms' => $category,
                ]
            ];
        }

        $query = new \WP_Query($args);
        $products = $query->posts;

        // 
        echo '<div class="pw-editor-preview">';
        echo '<div class="pw-editor-preview__badge">';
        echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        echo ' ' . esc_html__('Editor Preview — live page uses AJAX', 'woo-elementor-addon');
        echo '</div>';

        if (empty($products)) {
            echo '<div class="pw-editor-preview__empty">';
            echo '<span>🛍️</span><p>' . esc_html__('No products found. Add products in WooCommerce.', 'woo-elementor-addon') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="pw-products pw-products--grid" style="--pw-cols:' . esc_attr($columns) . '">';

            foreach ($products as $post) {
                $product = wc_get_product($post->ID);
                if (!$product)
                    continue;

                $image_id = $product->get_image_id();
                $image_url = $image_id
                    ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail')
                    : wc_placeholder_img_src('woocommerce_thumbnail');

                // Determine button label based on type
                if ($product->is_type('variable') || $product->is_type('grouped')) {
                    $btn_html = '<span class="pw-view-details pw-btn-secondary">' . esc_html__('View Details', 'woo-elementor-addon') . '</span>';
                } elseif ($product->is_type('external')) {
                    $btn_html = '<span class="pw-buy-now pw-btn-primary">' . esc_html($product->get_button_text() ?: 'Buy Now') . '</span>';
                } else {
                    $btn_html = '<span class="pw-add-to-cart pw-btn-primary">'
                        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> '
                        . esc_html__('Add to Cart', 'woo-elementor-addon')
                        . '</span>';
                }

                echo '<div class="pw-product-card pw-product-card--grid">';
                echo '<div class="pw-product-thumb">';
                echo '<div class="pw-product-image-link"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($product->get_name()) . '" loading="lazy" /></div>';
                if ($settings['show_wishlist'] === 'yes') {
                    echo '<button class="pw-wishlist-btn" aria-label="' . esc_attr__('Add to wishlist', 'woo-elementor-addon') . '">';
                    echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
                    echo '</button>';
                }
                echo '</div>';
                echo '<div class="pw-product-info">';
                echo '<h3 class="pw-product-title"><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($product->get_name()) . '</a></h3>';
                echo '<div class="pw-product-price">' . wp_kses_post($product->get_price_html()) . '</div>';
                echo $btn_html;
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>'; // .pw-editor-preview
    }
}
