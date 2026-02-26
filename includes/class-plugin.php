<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {

    private static ?Plugin $instance = null;

    public static function instance() : Plugin {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        new Admin_Menu();
        new Assets();
        new Ajax();
        new Order_Actions();
        new Email_Attachments();
    }
}
