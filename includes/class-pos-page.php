<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class POS_Page {

    public function render() : void {
        $settings = Settings::get();
        $store = [
            'address' => $settings['company_address'] ?? '',
            'phone'   => $settings['company_phone'] ?? '',
            'email'   => $settings['company_email'] ?? '',
        ];
        include WCPOSM_DIR . 'templates/pos-manager.php';
    }
}
