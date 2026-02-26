<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue( string $hook ) : void {

        // POS screen
        if ( $this->is_pos_screen( $hook ) ) {
            wp_enqueue_style(
                'wcposm-admin-pos',
                WCPOSM_URL . 'assets/css/admin-pos.css',
                [],
                WCPOSM_VERSION
            );

            wp_enqueue_script(
                'wcposm-admin-pos',
                WCPOSM_URL . 'assets/js/admin-pos.js',
                [ 'jquery' ],
                WCPOSM_VERSION,
                true
            );

            $settings = Settings::get();

            wp_localize_script( 'wcposm-admin-pos', 'WCPOSM', [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'wcposm_nonce' ),
                'currency'  => get_woocommerce_currency_symbol(),
                'defaults'  => [
                    'customer' => $settings['pos_default_customer'] ?? 0,
                    'status'   => $settings['pos_default_status'] ?? 'processing',
                    'payment'  => $settings['pos_default_payment'] ?? 'pos_cash',
                    'enableShipping' => ! empty( $settings['pos_enable_shipping'] ),
                    'enableDiscount' => ! empty( $settings['pos_enable_discount'] ),
                ],
                'strings'   => [
                    'creating' => __( 'Creating order...', 'sr-pos-pdf-invoice-for-woo' ),
                    'created'  => __( 'Order created!', 'sr-pos-pdf-invoice-for-woo' ),
                    'error'    => __( 'Something went wrong.', 'sr-pos-pdf-invoice-for-woo' ),
                ],
            ] );
        }

        // Settings screens (POS Settings & WooCommerce PDF Settings)
        if ( $this->is_settings_screen( $hook ) ) {
            wp_enqueue_media();

            wp_enqueue_style(
                'wcposm-admin-settings',
                WCPOSM_URL . 'assets/css/admin-settings.css',
                [],
                WCPOSM_VERSION
            );

            wp_enqueue_script(
                'wcposm-admin-settings',
                WCPOSM_URL . 'assets/js/admin-settings.js',
                [ 'jquery' ],
                WCPOSM_VERSION,
                true
            );

            wp_localize_script( 'wcposm-admin-settings', 'WCPOSM_SETTINGS', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wcposm_nonce' ),
                'strings' => [
                    'installing' => __( 'Installing…', 'sr-pos-pdf-invoice-for-woo' ),
                    'installed'  => __( 'Installed!', 'sr-pos-pdf-invoice-for-woo' ),
                    'failed'     => __( 'Install failed.', 'sr-pos-pdf-invoice-for-woo' ),
                ],
            ] );
        }

        if ( $this->is_orders_list_screen() ) {
            wp_enqueue_style(
                'wcposm-order-actions',
                WCPOSM_URL . 'assets/css/order-actions.css',
                [],
                WCPOSM_VERSION
            );

            wp_enqueue_script(
                'wcposm-order-actions-js',
                WCPOSM_URL . 'assets/js/order-actions.js',
                [ 'jquery' ],
                WCPOSM_VERSION,
                true
            );
        }
    }

    private function is_pos_screen( string $hook ) : bool {
        // Top-level POS page slug is wcposm-pos
        return ( strpos( $hook, 'wcposm-pos' ) !== false ) && ( strpos( $hook, 'wcposm-settings' ) === false );
    }

    private function is_settings_screen( string $hook ) : bool {
        return ( strpos( $hook, 'wcposm-settings' ) !== false ) || ( strpos( $hook, 'wcposm-pdf-settings' ) !== false );
    }

    private function is_orders_list_screen() : bool {
        if ( ! function_exists( 'get_current_screen' ) ) { return false; }
        $screen = get_current_screen();
        if ( ! $screen ) { return false; }
        return in_array( $screen->id, [ 'edit-shop_order', 'woocommerce_page_wc-orders' ], true );
    }
}
