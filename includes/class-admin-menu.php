<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Admin_Menu {

    private string $capability = 'manage_woocommerce';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    public function register_menu() : void {
        add_menu_page(
            __( 'POS', 'sr-pos-pdf-invoice-for-woo' ),
            __( 'POS', 'sr-pos-pdf-invoice-for-woo' ),
            $this->capability,
            'wcposm-pos',
            [ $this, 'render_pos' ],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'wcposm-pos',
            __( 'POS Manager', 'sr-pos-pdf-invoice-for-woo' ),
            __( 'POS Manager', 'sr-pos-pdf-invoice-for-woo' ),
            $this->capability,
            'wcposm-pos',
            [ $this, 'render_pos' ]
        );

        add_submenu_page(
            'wcposm-pos',
            __( 'Orders', 'sr-pos-pdf-invoice-for-woo' ),
            __( 'Orders', 'sr-pos-pdf-invoice-for-woo' ),
            $this->capability,
            'wcposm-orders',
            [ $this, 'render_orders_redirect' ]
        );

        add_submenu_page(
            'wcposm-pos',
            __( 'Settings', 'sr-pos-pdf-invoice-for-woo' ),
            __( 'Settings', 'sr-pos-pdf-invoice-for-woo' ),
            $this->capability,
            'wcposm-settings',
            [ $this, 'render_settings' ]
        );

        // WooCommerce -> PDF Settings
        add_submenu_page(
            'woocommerce',
            __( 'PDF Settings', 'sr-pos-pdf-invoice-for-woo' ),
            __( 'PDF Settings', 'sr-pos-pdf-invoice-for-woo' ),
            $this->capability,
            'wcposm-pdf-settings',
            [ $this, 'render_pdf_settings' ]
        );
    }

    public function render_pos() : void {
        if ( ! current_user_can( $this->capability ) ) { wp_die( esc_html__( 'Access denied.', 'sr-pos-pdf-invoice-for-woo' ) ); }
        ( new POS_Page() )->render();
    }

    public function render_orders_redirect() : void {
        if ( ! current_user_can( $this->capability ) ) { wp_die( esc_html__( 'Access denied.', 'sr-pos-pdf-invoice-for-woo' ) ); }
        $url = admin_url( 'edit.php?post_type=shop_order' );
        wp_safe_redirect( $url );
        exit;
    }

    public function render_settings() : void {
        if ( ! current_user_can( $this->capability ) ) { wp_die( esc_html__( 'Access denied.', 'sr-pos-pdf-invoice-for-woo' ) ); }
        ( new Settings() )->render_pos_settings_page();
    }

    public function render_pdf_settings() : void {
        if ( ! current_user_can( $this->capability ) ) { wp_die( esc_html__( 'Access denied.', 'sr-pos-pdf-invoice-for-woo' ) ); }
        ( new Settings() )->render_pdf_settings_page();
    }
}
