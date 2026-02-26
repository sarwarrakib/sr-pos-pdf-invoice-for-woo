<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Installer utilities.
 *
 * Note: WordPress.org plugins must not execute shell commands or run Composer at runtime.
 * This class therefore only performs lightweight environment checks.
 */
final class Installer {

    /**
     * Check whether bundled mPDF is available.
     *
     * @return array{ok:bool,message:string,log:string}
     */
    public static function mpdf_status() : array {
        $autoload = WCPOSM_DIR . 'vendor/autoload.php';
        if ( file_exists( $autoload ) ) {
            return [
                'ok'      => true,
                'message' => __( 'mPDF is available.', 'sr-pos-pdf-invoice-for-woo' ),
                'log'     => '',
            ];
        }

        return [
            'ok'      => false,
            'message' => __( 'mPDF is missing. Please reinstall the plugin package that includes vendor libraries.', 'sr-pos-pdf-invoice-for-woo' ),
            'log'     => '',
        ];
    }

    /**
     * Back-compat shim for older code paths.
     * @deprecated 1.1.7.12 Use mpdf_status() instead.
     */
    public static function maybe_install_mpdf( bool $force = false ) : array {
        unset( $force );
        return self::mpdf_status();
    }
}
