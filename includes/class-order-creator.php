<?php
namespace WCPOSM;

use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Shipping;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Order_Creator {

    public function create_from_payload( array $payload ) {
        $items = $payload['items'] ?? [];
        if ( empty( $items ) || ! is_array( $items ) ) {
            return new WP_Error( 'wcposm_empty', __( 'Cart is empty.', 'sr-pos-pdf-invoice-for-woo' ) );
        }

        $customer_id = isset( $payload['customer_id'] ) ? absint( $payload['customer_id'] ) : 0;
        $status      = isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : 'processing';
        $payment     = isset( $payload['payment'] ) ? sanitize_key( $payload['payment'] ) : 'pos_cash';

        $shipping_amount = isset( $payload['shipping'] ) ? floatval( $payload['shipping'] ) : 0.0;

        $discount_type = isset( $payload['discount_type'] ) ? sanitize_key( $payload['discount_type'] ) : 'none';
        $discount_val  = isset( $payload['discount_value'] ) ? floatval( $payload['discount_value'] ) : 0.0;

        $billing  = is_array( $payload['billing'] ?? null ) ? $payload['billing'] : [];
        $shipping = is_array( $payload['shipping_addr'] ?? null ) ? $payload['shipping_addr'] : [];

        $order = wc_create_order( [ 'customer_id' => $customer_id ] );
        if ( is_wp_error( $order ) ) { return $order; }

        foreach ( $items as $line ) {
            $pid = absint( $line['product_id'] ?? 0 );
            $qty = max( 1, absint( $line['qty'] ?? 1 ) );
            if ( ! $pid ) { continue; }
            $product = wc_get_product( $pid );
            if ( ! $product ) { continue; }
            $order->add_product( $product, $qty );
        }

        // Addresses (editable)
        $order->set_address( [
            'first_name' => sanitize_text_field( $billing['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $billing['last_name'] ?? '' ),
            'company'    => sanitize_text_field( $billing['company'] ?? '' ),
            'address_1'  => sanitize_text_field( $billing['address_1'] ?? '' ),
            'address_2'  => sanitize_text_field( $billing['address_2'] ?? '' ),
            'city'       => sanitize_text_field( $billing['city'] ?? '' ),
            'state'      => sanitize_text_field( $billing['state'] ?? '' ),
            'postcode'   => sanitize_text_field( $billing['postcode'] ?? '' ),
            'country'    => sanitize_text_field( $billing['country'] ?? '' ),
            'email'      => sanitize_email( $billing['email'] ?? '' ),
            'phone'      => sanitize_text_field( $billing['phone'] ?? '' ),
        ], 'billing' );

        $order->set_address( [
            'first_name' => sanitize_text_field( $shipping['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $shipping['last_name'] ?? '' ),
            'company'    => sanitize_text_field( $shipping['company'] ?? '' ),
            'address_1'  => sanitize_text_field( $shipping['address_1'] ?? '' ),
            'address_2'  => sanitize_text_field( $shipping['address_2'] ?? '' ),
            'city'       => sanitize_text_field( $shipping['city'] ?? '' ),
            'state'      => sanitize_text_field( $shipping['state'] ?? '' ),
            'postcode'   => sanitize_text_field( $shipping['postcode'] ?? '' ),
            'country'    => sanitize_text_field( $shipping['country'] ?? '' ),
            'phone'      => sanitize_text_field( $shipping['phone'] ?? '' ),
        ], 'shipping' );

        // Shipping line (manual)
        if ( $shipping_amount > 0 ) {
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title( __( 'POS Shipping', 'sr-pos-pdf-invoice-for-woo' ) );
            $shipping_item->set_method_id( 'wcposm_shipping' );
            $shipping_item->set_total( wc_format_decimal( $shipping_amount ) );
            $order->add_item( $shipping_item );
        }

        // Discount as negative fee (percentage or fixed)
        $discount_total = 0.0;
        if ( $discount_val > 0 && in_array( $discount_type, [ 'percent', 'fixed' ], true ) ) {
            $subtotal = (float) $order->get_subtotal();
            if ( $discount_type === 'percent' ) {
                $discount_total = round( ( $subtotal * $discount_val ) / 100, wc_get_price_decimals() );
            } else {
                $discount_total = $discount_val;
            }
            if ( $discount_total > 0 ) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name( __( 'POS Discount', 'sr-pos-pdf-invoice-for-woo' ) );
                $fee->set_amount( -1 * $discount_total );
                $fee->set_total( -1 * $discount_total );
                $order->add_item( $fee );
            }
        }

        // Payment method store
        $payment_key = $payment;
        $gateway_title = $this->payment_title( $payment_key );
        // If a real WooCommerce gateway exists (e.g. 'cod'), use it so WooCommerce reports are correct.
        if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
            $gateways = WC()->payment_gateways()->payment_gateways();
            if ( isset( $gateways[ $payment_key ] ) ) {
                $order->set_payment_method( $gateways[ $payment_key ] );
                $gateway_title = $gateways[ $payment_key ]->get_title();
            }
        }
        $order->set_payment_method_title( $gateway_title );
        $order->calculate_totals();

        // Status
        $order->update_status( $status, __( 'POS order created.', 'sr-pos-pdf-invoice-for-woo' ), true );

        // Meta for tracking
        $order->update_meta_data( '_wcposm_source', 'pos' );
        $order->save();

        return $order->get_id();
    }

    private function payment_title( string $key ) : string {
        $map = [
            'pos_cash' => __( 'Cash', 'sr-pos-pdf-invoice-for-woo' ),
            'cod'      => __( 'Cash on Delivery', 'sr-pos-pdf-invoice-for-woo' ),
            'pos_card' => __( 'Card', 'sr-pos-pdf-invoice-for-woo' ),
            'pos_bank' => __( 'Bank', 'sr-pos-pdf-invoice-for-woo' ),
            'pos_custom' => __( 'Custom POS', 'sr-pos-pdf-invoice-for-woo' ),
        ];
        return $map[ $key ] ?? $key;
    }
}
