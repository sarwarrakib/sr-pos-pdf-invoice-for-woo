<?php
namespace WCPOSM;

use WP_User;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Ajax {

    public function __construct() {
        add_action( 'wp_ajax_wcposm_product_search', [ $this, 'product_search' ] );
        add_action( 'wp_ajax_wcposm_customer_search', [ $this, 'customer_search' ] );
        add_action( 'wp_ajax_wcposm_customer_get', [ $this, 'customer_get' ] );
        add_action( 'wp_ajax_wcposm_customer_save', [ $this, 'customer_save' ] );
        add_action( 'wp_ajax_wcposm_create_order', [ $this, 'create_order' ] );
        add_action( 'wp_ajax_wcposm_print_pdf', [ $this, 'print_pdf' ] );
    }

    private function must_have_access() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Access denied' ], 403 );
        }
    }

    public function product_search() : void {
        $this->must_have_access();
        check_ajax_referer( 'wcposm_nonce', 'nonce' );

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        $page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $per  = isset( $_POST['per'] ) ? min( 60, max( 5, absint( $_POST['per'] ) ) ) : 30;

        $args = [
            'status'  => 'publish',
            'limit'   => $per,
            'page'    => $page,
            'return'  => 'ids',
        ];

        if ( $term !== '' ) {
            $args['search'] = '*' . $term . '*';
        }

        $query = new \WC_Product_Query( $args );
        $ids = $query->get_products();

        if ( $term !== '' ) {
            $meta_keys = apply_filters( 'wcposm_search_meta_keys', [ '_sku' ] );
            $ids_extra = $this->meta_like_product_ids( $term, $meta_keys, $per, ($page-1)*$per );
            $ids = array_values( array_unique( array_merge( $ids, $ids_extra ) ) );
        }

        $products = [];
        foreach ( $ids as $pid ) {
            $p = wc_get_product( $pid );
            if ( ! $p ) { continue; }
            $products[] = [
                'id'    => $p->get_id(),
                'name'  => $p->get_name(),
                'price' => wc_get_price_to_display( $p ),
                'sku'   => $p->get_sku(),
                'img'   => wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
            ];
        }

        wp_send_json_success( [ 'products' => $products ] );
    }

	    /**
     * Search products by meta keys using WP_Query (avoids direct SQL).
     */
    private function meta_like_product_ids( string $term, array $meta_keys, int $limit, int $offset ) : array {
        $term = sanitize_text_field( $term );
        $meta_keys = array_filter( array_map( 'sanitize_key', $meta_keys ) );
        if ( $term === '' || empty( $meta_keys ) ) {
            return [];
        }

        $meta_query = [ 'relation' => 'OR' ];
        foreach ( $meta_keys as $mk ) {
            $meta_query[] = [
                'key'     => $mk,
                'value'   => $term,
                'compare' => 'LIKE',
            ];
        }

        $q = new \WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed for admin search/autocomplete; query is limited and uses no_found_rows.
            'meta_query'     => $meta_query,
        ] );

        return array_map( 'intval', $q->posts );
    }


    public function customer_search() : void {
        $this->must_have_access();
        check_ajax_referer( 'wcposm_nonce', 'nonce' );

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        if ( $term === '' ) {
            wp_send_json_success( [ 'customers' => [] ] );
        }

        $args = [
            'number'  => 20,
            'orderby' => 'ID',
            'order'   => 'DESC',
            'role__in' => [ 'customer', 'subscriber' ],
            'search'  => '*' . esc_attr( $term ) . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
        ];

        $q = new \WP_User_Query( $args );
        $users = $q->get_results();

        $users_phone = $this->find_users_by_phone_like( $term, 20 );
        $users = array_values( array_unique( array_merge( $users, $users_phone ), SORT_REGULAR ) );

        $customers = [];
        foreach ( $users as $u ) {
            if ( ! $u instanceof WP_User ) { continue; }
            $customers[] = [
                'id' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
                'phone' => get_user_meta( $u->ID, 'billing_phone', true ),
            ];
        }

        wp_send_json_success( [ 'customers' => $customers ] );
    }

    public function customer_get() : void {
        $this->must_have_access();
        check_ajax_referer( 'wcposm_nonce', 'nonce' );

        $id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
        if ( ! $id ) { wp_send_json_error( [ 'message' => 'Missing customer_id' ], 400 ); }

        $u = get_user_by( 'id', $id );
        if ( ! $u ) { wp_send_json_error( [ 'message' => 'Customer not found' ], 404 ); }

        wp_send_json_success( [ 'customer' => $this->build_customer_payload( $id ) ] );
    }

    private function build_customer_payload( int $id ) : array {
        return [
            'id' => $id,
            'name' => get_the_author_meta( 'display_name', $id ),
            'email' => get_the_author_meta( 'user_email', $id ),
            'phone' => get_user_meta( $id, 'billing_phone', true ),
            'billing' => [
                'address_1' => get_user_meta( $id, 'billing_address_1', true ),
                'city' => get_user_meta( $id, 'billing_city', true ),
                'postcode' => get_user_meta( $id, 'billing_postcode', true ),
                'country' => get_user_meta( $id, 'billing_country', true ),
            ],
            'shipping' => [
                'address_1' => get_user_meta( $id, 'shipping_address_1', true ),
                'city' => get_user_meta( $id, 'shipping_city', true ),
                'postcode' => get_user_meta( $id, 'shipping_postcode', true ),
                'country' => get_user_meta( $id, 'shipping_country', true ),
            ],
        ];
    }

    
    private function find_users_by_phone_like( string $term, int $limit ) : array {
        $term = sanitize_text_field( $term );
        if ( $term === '' ) {
            return [];
        }

        $q = new \WP_User_Query( [
            'number'    => $limit,
            'orderby'   => 'ID',
            'order'     => 'DESC',
            'role__in'  => [ 'customer', 'subscriber' ],
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed for admin customer search by phone; query is limited.
            'meta_query'=> [
                [
                    'key'     => 'billing_phone',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        $users = $q->get_results();
        return is_array( $users ) ? $users : [];
    }

    public function customer_save() : void {
        $this->must_have_access();
        check_ajax_referer( 'wcposm_nonce', 'nonce' );

        $payload = isset( $_POST['customer'] ) ? (array) wc_clean( wp_unslash( $_POST['customer'] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via wc_clean().
        $id = isset( $payload['id'] ) ? absint( $payload['id'] ) : 0;

        $data = [
            'name'    => sanitize_text_field( $payload['name'] ?? '' ),
            'phone'   => sanitize_text_field( $payload['phone'] ?? '' ),
            'email'   => sanitize_email( $payload['email'] ?? '' ),
            'billing' => [
                'address_1' => sanitize_text_field( $payload['billing_address'] ?? '' ),
                'city'      => sanitize_text_field( $payload['billing_city'] ?? '' ),
                'postcode'  => sanitize_text_field( $payload['billing_postcode'] ?? '' ),
                'country'   => sanitize_text_field( $payload['billing_country'] ?? '' ),
            ],
            'shipping' => [
                'address_1' => sanitize_text_field( $payload['shipping_address'] ?? '' ),
                'city'      => sanitize_text_field( $payload['shipping_city'] ?? '' ),
                'postcode'  => sanitize_text_field( $payload['shipping_postcode'] ?? '' ),
                'country'   => sanitize_text_field( $payload['shipping_country'] ?? '' ),
            ],
        ];

        if ( $id > 0 ) {
            $user = get_user_by( 'id', $id );
            if ( ! $user ) {
                wp_send_json_error( [ 'message' => 'Customer not found' ], 404 );
            }
            wp_update_user( [
                'ID' => $id,
                'display_name' => $data['name'] ?: $user->display_name,
                'user_email'   => $data['email'] ?: $user->user_email,
            ] );
        } else {
            if ( empty( $data['email'] ) ) {
                wp_send_json_error( [ 'message' => 'Email is required' ], 400 );
            }
            if ( email_exists( $data['email'] ) ) {
                wp_send_json_error( [ 'message' => 'Email already exists' ], 409 );
            }
            $username = sanitize_user( current( explode( '@', $data['email'] ) ) );
            if ( username_exists( $username ) ) {
                $username .= wp_rand( 100, 999 );
            }
            $password = wp_generate_password( 12, true );
            $id = wp_create_user( $username, $password, $data['email'] );
            if ( is_wp_error( $id ) ) {
                wp_send_json_error( [ 'message' => $id->get_error_message() ], 400 );
            }
            wp_update_user( [
                'ID' => $id,
                'display_name' => $data['name'] ?: $data['email'],
                'role' => 'customer',
            ] );
        }

        update_user_meta( $id, 'billing_phone', $data['phone'] );
        update_user_meta( $id, 'billing_address_1', $data['billing']['address_1'] );
        update_user_meta( $id, 'billing_city', $data['billing']['city'] );
        update_user_meta( $id, 'billing_postcode', $data['billing']['postcode'] );
        update_user_meta( $id, 'billing_country', $data['billing']['country'] );

        update_user_meta( $id, 'shipping_address_1', $data['shipping']['address_1'] );
        update_user_meta( $id, 'shipping_city', $data['shipping']['city'] );
        update_user_meta( $id, 'shipping_postcode', $data['shipping']['postcode'] );
        update_user_meta( $id, 'shipping_country', $data['shipping']['country'] );

        wp_send_json_success( [ 'customer' => $this->build_customer_payload( $id ) ] );
    }

    public function create_order() : void {
        $this->must_have_access();
        check_ajax_referer( 'wcposm_nonce', 'nonce' );

        $payload = isset( $_POST['order'] ) ? (array) wc_clean( wp_unslash( $_POST['order'] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via wc_clean().
        $result = ( new Order_Creator() )->create_from_payload( $payload );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        $order_id = (int) $result;
        $nonce = wp_create_nonce( 'wcposm_nonce' );

        wp_send_json_success( [
            'order_id' => $order_id,
            'invoice_url' => add_query_arg( [
                'action' => 'wcposm_print_pdf',
                'nonce' => $nonce,
                'order_id' => $order_id,
                'type' => 'invoice',
                'mode' => ( Settings::get()['pdf_click_action'] ?? 'print' ),
            ], admin_url( 'admin-ajax.php' ) ),
            'packing_url' => add_query_arg( [
                'action' => 'wcposm_print_pdf',
                'nonce' => $nonce,
                'order_id' => $order_id,
                'type' => 'packing',
                'mode' => ( Settings::get()['pdf_click_action'] ?? 'print' ),
            ], admin_url( 'admin-ajax.php' ) ),
        ] );
    }
    public function print_pdf() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Access denied', 403 ); }

        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wcposm_nonce' ) ) { wp_die( 'Invalid nonce', 403 ); }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'invoice';
        $mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : ( Settings::get()['pdf_click_action'] ?? 'print' );

        if ( ! $order_id ) { wp_die( 'Missing order_id', 400 ); }

        $order = wc_get_order( $order_id );
        if ( ! $order ) { wp_die( 'Order not found', 404 ); }

        $pdf = new PDF_Generator();
        $pdf->output_order_pdf( $order, $type, $mode );
        exit;
    }
}
