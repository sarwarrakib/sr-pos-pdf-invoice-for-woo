<?php
/**
 * Plugin Name: SR POS - PDF Invoice & Packing Slip for WooCommerce
 * Description: PDF Invoice & Packing Slip for WooCommerce orders with watermark, print view, and direct PDF download.
 * Version: 1.1.7.24
 * Author: SarwarRakib
 * Author URI: https://sarwarrakib.com
 * Plugin URI: https://sarwarrakib.com/wpos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: sr-pos-pdf-invoice-for-woo
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WCPOSM_VERSION', '1.1.7.23' );
define( 'WCPOSM_FILE', __FILE__ );
define( 'WCPOSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPOSM_URL', plugin_dir_url( __FILE__ ) );

// Register plugin autoloader (loads WCPOSM\* classes from /includes).
require_once WCPOSM_DIR . 'includes/class-autoloader.php';

/**
 * Declare compatibility with WooCommerce features (prevents "incompatible with enabled features" warnings),
 * especially HPOS (custom order tables) and Cart/Checkout blocks.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        // HPOS / Custom Order Tables
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        // Blocks checkout
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

function wcposm_boot() : void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            printf( '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>', esc_html__( 'SR POS', 'sr-pos-pdf-invoice-for-woo' ), esc_html__( 'requires WooCommerce to be installed and active.', 'sr-pos-pdf-invoice-for-woo' ) );
        } );
        return;
    }

    \WCPOSM\Plugin::instance();
}
add_action( 'init', 'wcposm_boot', 20 );

register_activation_hook( __FILE__, function() {
    if ( ! class_exists( 'WooCommerce' ) ) { return; }
    \WCPOSM\Settings::maybe_set_defaults();
});
