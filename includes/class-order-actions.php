<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Order_Actions {

    public function __construct() {
        add_filter( 'woocommerce_admin_order_actions', [ $this, 'add_actions' ], 100, 2 );
    }

    public function add_actions( array $actions, $order ) : array {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return $actions; }

        $order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
        if ( ! $order_id ) { return $actions; }

        $nonce = wp_create_nonce( 'wcposm_nonce' );

        $settings = Settings::get();
        $mode = $settings['pdf_click_action'] ?? 'print';


        $invoice_url = add_query_arg( [
            'action' => 'wcposm_print_pdf',
            'nonce' => $nonce,
            'order_id' => $order_id,
            'type' => 'invoice',
            'mode' => $mode,
        ], admin_url( 'admin-ajax.php' ) );

        $packing_url = add_query_arg( [
            'action' => 'wcposm_print_pdf',
            'nonce' => $nonce,
            'order_id' => $order_id,
            'type' => 'packing',
            'mode' => $mode,
        ], admin_url( 'admin-ajax.php' ) );

        $actions['wcposm_invoice_pdf'] = [
            'url'    => $invoice_url,
            'name'   => __( 'Invoice PDF', 'sr-pos-pdf-invoice-for-woo' ),
            'action' => 'wcposm_invoice_pdf',
        ];

        $actions['wcposm_packing_pdf'] = [
            'url'    => $packing_url,
            'name'   => __( 'Packing Slip PDF', 'sr-pos-pdf-invoice-for-woo' ),
            'action' => 'wcposm_packing_pdf',
        ];

        return $actions;
    }
}
