<?php
namespace WCPOSM;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Email_Attachments {

    /** @var string[] */
    private array $created_files = [];

    public function __construct() {
        add_filter( 'woocommerce_email_attachments', [ $this, 'maybe_attach' ], 20, 4 );
        add_action( 'shutdown', [ $this, 'cleanup' ] );
    }

    /**
     * @param string[] $attachments
     * @param string $email_id
     * @param WC_Order|bool $order
     * @param \WC_Email|null $email_obj
     */
    public function maybe_attach( array $attachments, string $email_id, $order, $email_obj = null ) : array {
        if ( ! $order instanceof WC_Order ) {
            return $attachments;
        }

        $settings = Settings::get();

        $enabled = ! empty( $settings['email_attach_enabled'] );
        if ( ! $enabled ) {
            return $attachments;
        }

        $targets = $settings['email_attach_targets'] ?? [];
        if ( ! is_array( $targets ) ) {
            $targets = [];
        }

        if ( ! in_array( $email_id, $targets, true ) ) {
            return $attachments;
        }

        // Decide what to attach for each email type.
        $attach_type = 'invoice';
        if ( ! empty( $settings['email_attach_packing_admin_only'] ) && $email_id === 'new_order' ) {
            $attach_type = 'packing';
        }

        $pdf = new PDF_Generator();
        $file = $pdf->generate_pdf_file( $order, $attach_type );

        if ( is_wp_error( $file ) ) {
            return $attachments;
        }

        $attachments[] = $file;
        $this->created_files[] = $file;

        return $attachments;
    }

    public function cleanup() : void {
        foreach ( $this->created_files as $file ) {
            if ( is_string( $file ) && file_exists( $file ) ) {
                wp_delete_file( $file );
            }
        }
        $this->created_files = [];
    }
}
