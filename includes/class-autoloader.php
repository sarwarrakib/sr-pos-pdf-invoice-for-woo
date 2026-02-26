<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Autoloader {
    public static function register() : void {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    public static function autoload( string $class ) : void {
        if ( strpos( $class, __NAMESPACE__ . '\\' ) !== 0 ) {
            return;
        }
        $relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
        $relative = strtolower( str_replace( ['\\', '_'], ['-', '-'], $relative ) );
        $file = WCPOSM_DIR . 'includes/class-' . $relative . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
Autoloader::register();
