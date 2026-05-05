<?php

namespace PW\App\ProductListType;

use PW\App\Query\ProductQuery;

defined( 'ABSPATH' ) || exit;

class ProductListWidget extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'pw_product_list';
    }

    public function get_title(): string {
        return __( 'Product List', 'woo-elementor-addon' );
    }

    public function get_icon(): string {
        return 'eicon-post-list';
    }

    public function get_categories(): array {
        return [ 'woocommerce-elements' ];
    }

    public function get_keywords(): array {
        return [ 'woocommerce', 'products', 'list', 'shop' ];
    }

    protected function register_controls(): void {

        $this->start_controls_section( 'section_products', [
            'label' => __( 'Products', 'woo-elementor-addon' ),
        ] );

        $this->add_control( 'posts_per_page', [
            'label'   => __( 'Products Per Page', 'woo-elementor-addon' ),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min'     => 1,
            'max'     => 100,
        ] );

        $this->add_control( 'category', [
            'label'   => __( 'Default Category', 'woo-elementor-addon' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'orderby', [
            'label'   => __( 'Default Sort', 'woo-elementor-addon' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'default',
            'options' => [
                'default'    => __( 'Default', 'woo-elementor-addon' ),
                'price_low'  => __( 'Price: Low → High', 'woo-elementor-addon' ),
                'price_high' => __( 'Price: High → Low', 'woo-elementor-addon' ),
                'alphabet'   => __( 'A → Z', 'woo-elementor-addon' ),
            ],
        ] );

        $this->end_controls_section();

        $this->start_controls_section( 'section_features', [
            'label' => __( 'Features', 'woo-elementor-addon' ),
        ] );

        $this->add_control( 'show_pagination', [
            'label'        => __( 'Enable Pagination', 'woo-elementor-addon' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'show_filters', [
            'label'        => __( 'Enable Filters', 'woo-elementor-addon' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'show_search', [
            'label'        => __( 'Enable Search', 'woo-elementor-addon' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'show_wishlist', [
            'label'        => __( 'Enable Wishlist', 'woo-elementor-addon' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings  = $this->get_settings_for_display();
        $widget_id = $this->get_id();

        // ── Editor: render a static PHP preview so the designer sees real products ──
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $this->render_editor_preview( $settings );
            return;
        }

        // ── Frontend: output the empty shell the JavaScript picks up ───────────────
        $config = [
            'id'         => $widget_id,
            'layout'     => 'list',
            'per_page'   => (int) $settings['posts_per_page'],
            'columns'    => 1,
            'category'   => sanitize_text_field( $settings['category'] ),
            'sort'       => sanitize_key( $settings['orderby'] ),
            'pagination' => $settings['show_pagination'] === 'yes',
            'filters'    => $settings['show_filters'] === 'yes',
            'search'     => $settings['show_search'] === 'yes',
            'wishlist'   => $settings['show_wishlist'] === 'yes',
        ];

        echo '<div class="pw-widget pw-widget--list" data-id="' . esc_attr( $widget_id ) . '" data-settings="' . esc_attr( wp_json_encode( $config ) ) . '"></div>';
    }

    /**
     * Render a static product list for the Elementor editor.
     * Uses PHP + WP_Query directly — no AJAX, no JavaScript dependency.
     * Completely isolated from the frontend render path.
     */
    private function render_editor_preview( array $settings ): void {
        $per_page = min( (int) ( $settings['posts_per_page'] ?? 4 ), 4 ); // cap at 4 for editor speed
        $category = sanitize_text_field( $settings['category'] ?? '' );

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
        ];

        if ( ! empty( $category ) ) {
            $args['tax_query'] = [ [
                'taxonomy' => 'product_cat',
                'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
                'terms'    => $category,
            ] ];
        }

        $query    = new \WP_Query( $args );
        $products = $query->posts;

        echo '<div class="pw-editor-preview">';
        echo '<div class="pw-editor-preview__badge">';
        echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        echo ' ' . esc_html__( 'Editor Preview — live page uses AJAX', 'woo-elementor-addon' );
        echo '</div>';

        if ( empty( $products ) ) {
            echo '<div class="pw-editor-preview__empty">';
            echo '<span>🛍️</span><p>' . esc_html__( 'No products found. Add products in WooCommerce.', 'woo-elementor-addon' ) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="pw-products pw-products--list">';

            foreach ( $products as $post ) {
                $product = wc_get_product( $post->ID );
                if ( ! $product ) continue;

                $image_id  = $product->get_image_id();
                $image_url = $image_id
                    ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
                    : wc_placeholder_img_src( 'woocommerce_thumbnail' );

                if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
                    $btn_html = '<span class="pw-view-details pw-btn-secondary">' . esc_html__( 'View Details', 'woo-elementor-addon' ) . '</span>';
                } elseif ( $product->is_type( 'external' ) ) {
                    $btn_html = '<span class="pw-buy-now pw-btn-primary">' . esc_html( $product->get_button_text() ?: 'Buy Now' ) . '</span>';
                } else {
                    $btn_html = '<span class="pw-add-to-cart pw-btn-primary">' . esc_html__( 'Add to Cart', 'woo-elementor-addon' ) . '</span>';
                }

                echo '<div class="pw-product-card pw-product-card--list">';
                echo '<div class="pw-product-image-link"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $product->get_name() ) . '" loading="lazy" /></div>';
                echo '<div class="pw-product-info">';
                echo '<h3 class="pw-product-title"><a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( $product->get_name() ) . '</a></h3>';
                echo '<div class="pw-product-price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
                echo '<div class="pw-product-actions">';
                echo $btn_html;
                if ( $settings['show_wishlist'] === 'yes' ) {
                    echo '<button class="pw-wishlist-btn" aria-label="' . esc_attr__( 'Add to wishlist', 'woo-elementor-addon' ) . '">';
                    echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
                    echo '</button>';
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>'; // .pw-editor-preview
    }
}
