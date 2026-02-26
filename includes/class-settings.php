<?php
namespace WCPOSM;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Settings {

    private const OPTION = 'wcposm_settings';

    public static function maybe_set_defaults() : void {
        if ( get_option( self::OPTION, null ) !== null ) {
            return;
        }
        $defaults = [
            'company_name' => get_bloginfo( 'name' ),
            'company_logo_id' => 0,
            'company_watermark_logo_id' => 0,
            'company_address' => '',
            'company_phone' => '',
            'company_email' => get_bloginfo( 'admin_email' ),

            'pdf_primary_color' => '#111827',
            'pdf_font_family'   => 'wcposm_bn',
            'pdf_font_file'     => '',
            'pdf_watermark_opacity' => '0.08',
            'pdf_show_sku' => 1,
            'pdf_show_image' => 1,
            'pdf_footer_text' => '',
            'pdf_thank_you' => 'Thank you for your purchase!',

            'pdf_click_action' => 'print',
            'pdf_show_customer_details' => 1,
            'pdf_show_shipping_address' => 1,

            'email_attach_enabled' => 0,
            'email_attach_targets' => [],
            'email_attach_packing_admin_only' => 1,

            'pos_default_customer' => 0,
            'pos_default_status' => 'processing',
            'pos_default_payment' => 'pos_cash',
            'pos_enable_shipping' => 1,
            'pos_enable_discount' => 1,
        ];
        add_option( self::OPTION, $defaults );
    }

    public static function get() : array {
        $opt = get_option( self::OPTION, [] );
        return is_array( $opt ) ? $opt : [];
    }

    public static function update( array $data ) : void {
        update_option( self::OPTION, $data );
    }

    public function render_pos_settings_page() : void {
        $settings = self::get();

        if ( isset( $_POST['wcposm_save_pos'] ) && current_user_can( 'manage_woocommerce' ) ) {
            check_admin_referer( 'wcposm_save_pos_settings' );

            $settings['company_name'] = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) );
            $settings['company_address'] = wp_kses_post( wp_unslash( $_POST['company_address'] ?? '' ) );
            $settings['company_phone'] = sanitize_text_field( wp_unslash( $_POST['company_phone'] ?? '' ) );
            $settings['company_email'] = sanitize_email( wp_unslash( $_POST['company_email'] ?? '' ) );

            $settings['pos_default_customer'] = absint( $_POST['pos_default_customer'] ?? 0 );
            $settings['pos_default_status'] = sanitize_key( wp_unslash( $_POST['pos_default_status'] ?? 'processing' ) );
            $settings['pos_default_payment'] = sanitize_key( wp_unslash( $_POST['pos_default_payment'] ?? 'pos_cash' ) );
            $settings['pos_enable_shipping'] = ! empty( $_POST['pos_enable_shipping'] ) ? 1 : 0;
            $settings['pos_enable_discount'] = ! empty( $_POST['pos_enable_discount'] ) ? 1 : 0;

            $settings['company_logo_id'] = absint( $_POST['company_logo_id'] ?? 0 );
            $settings['company_watermark_logo_id'] = absint( $_POST['company_watermark_logo_id'] ?? 0 );

            self::update( $settings );

            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'sr-pos-pdf-invoice-for-woo' ) . '</p></div>';
        }

        $logo_url = ! empty( $settings['company_logo_id'] ) ? wp_get_attachment_image_url( (int) $settings['company_logo_id'], 'medium' ) : '';
        $wm_url = ! empty( $settings['company_watermark_logo_id'] ) ? wp_get_attachment_image_url( (int) $settings['company_watermark_logo_id'], 'medium' ) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'POS Settings', 'sr-pos-pdf-invoice-for-woo' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'wcposm_save_pos_settings' ); ?>

                <h2 class="title"><?php echo esc_html__( 'Company Settings', 'sr-pos-pdf-invoice-for-woo' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Company Name', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="text" name="company_name" class="regular-text" value="<?php echo esc_attr( $settings['company_name'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Company Address', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><textarea name="company_address" class="large-text" rows="3"><?php echo esc_textarea( $settings['company_address'] ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Phone', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="text" name="company_phone" class="regular-text" value="<?php echo esc_attr( $settings['company_phone'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="email" name="company_email" class="regular-text" value="<?php echo esc_attr( $settings['company_email'] ?? '' ); ?>" /></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Company Logo', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <input type="hidden" id="company_logo_id" name="company_logo_id" value="<?php echo esc_attr( (string) ( $settings['company_logo_id'] ?? 0 ) ); ?>" />
                            <div class="wcposm-media-row">
                                <button type="button" class="button wcposm-media-pick" data-target="company_logo_id" data-preview="company_logo_preview"><?php esc_html_e( 'Choose Logo', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                                <button type="button" class="button wcposm-media-remove" data-target="company_logo_id" data-preview="company_logo_preview"><?php esc_html_e( 'Remove', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                                <img id="company_logo_preview" class="wcposm-media-preview" src="<?php echo esc_url( $logo_url ); ?>" style="<?php echo $logo_url ? '' : 'display:none;'; ?>" />
                            </div>
                            
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Watermark Logo', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <input type="hidden" id="company_watermark_logo_id" name="company_watermark_logo_id" value="<?php echo esc_attr( (string) ( $settings['company_watermark_logo_id'] ?? 0 ) ); ?>" />
                            <div class="wcposm-media-row">
                                <button type="button" class="button wcposm-media-pick" data-target="company_watermark_logo_id" data-preview="company_watermark_logo_preview"><?php esc_html_e( 'Choose Watermark', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                                <button type="button" class="button wcposm-media-remove" data-target="company_watermark_logo_id" data-preview="company_watermark_logo_preview"><?php esc_html_e( 'Remove', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                                <img id="company_watermark_logo_preview" class="wcposm-media-preview" src="<?php echo esc_url( $wm_url ); ?>" style="<?php echo $wm_url ? '' : 'display:none;'; ?>" />
                            </div>
                            
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php echo esc_html__( 'POS Defaults', 'sr-pos-pdf-invoice-for-woo' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default Customer (User ID, 0 = Guest)', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="number" name="pos_default_customer" class="small-text" value="<?php echo esc_attr( (string) ( $settings['pos_default_customer'] ?? 0 ) ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default Order Status', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <select name="pos_default_status">
                                <?php foreach ( wc_get_order_statuses() as $key => $label ) :
                                    $k = str_replace( 'wc-', '', $key );
                                ?>
                                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $settings['pos_default_status'] ?? 'processing', $k ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default Payment Method', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <select name="pos_default_payment">
                                <option value="pos_cash" <?php selected( $settings['pos_default_payment'] ?? 'pos_cash', 'pos_cash' ); ?>><?php esc_html_e( 'Cash', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="cod" <?php selected( $settings['pos_default_payment'] ?? 'pos_cash', 'cod' ); ?>><?php esc_html_e( 'Cash on Delivery', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="pos_card" <?php selected( $settings['pos_default_payment'] ?? 'pos_cash', 'pos_card' ); ?>><?php esc_html_e( 'Card', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="pos_bank" <?php selected( $settings['pos_default_payment'] ?? 'pos_cash', 'pos_bank' ); ?>><?php esc_html_e( 'Bank', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="pos_custom" <?php selected( $settings['pos_default_payment'] ?? 'pos_cash', 'pos_custom' ); ?>><?php esc_html_e( 'Custom POS', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Shipping', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><label><input type="checkbox" name="pos_enable_shipping" value="1" <?php checked( ! empty( $settings['pos_enable_shipping'] ) ); ?>/> <?php esc_html_e( 'Enable', 'sr-pos-pdf-invoice-for-woo' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Discount', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><label><input type="checkbox" name="pos_enable_discount" value="1" <?php checked( ! empty( $settings['pos_enable_discount'] ) ); ?>/> <?php esc_html_e( 'Enable', 'sr-pos-pdf-invoice-for-woo' ); ?></label></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="wcposm_save_pos" class="button button-primary"><?php esc_html_e( 'Save Settings', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                </p>
            </form>

            <hr/>
            <p><strong><?php echo esc_html__( 'PDF Settings moved to:', 'sr-pos-pdf-invoice-for-woo' ); ?></strong> <?php echo esc_html__( 'WooCommerce → PDF Settings', 'sr-pos-pdf-invoice-for-woo' ); ?></p>
        </div>
        <?php
    }

    public function render_pdf_settings_page() : void {
        $settings = self::get();

        if ( isset( $_POST['wcposm_save_pdf'] ) && current_user_can( 'manage_woocommerce' ) ) {
            check_admin_referer( 'wcposm_save_pdf_settings' );

            $settings['pdf_primary_color'] = sanitize_text_field( wp_unslash( $_POST['pdf_primary_color'] ?? '#111827' ) );
            $settings['pdf_font_family'] = sanitize_text_field( wp_unslash( $_POST['pdf_font_family'] ?? 'wcposm_bn' ) );
            $settings['pdf_watermark_opacity'] = sanitize_text_field( wp_unslash( $_POST['pdf_watermark_opacity'] ?? '0.08' ) );
            $settings['pdf_show_sku'] = ! empty( $_POST['pdf_show_sku'] ) ? 1 : 0;
            $settings['pdf_show_image'] = ! empty( $_POST['pdf_show_image'] ) ? 1 : 0;
            $settings['pdf_show_customer_details'] = ! empty( $_POST['pdf_show_customer_details'] ) ? 1 : 0;
            $settings['pdf_show_shipping_address'] = ! empty( $_POST['pdf_show_shipping_address'] ) ? 1 : 0;

            $settings['email_attach_enabled'] = ! empty( $_POST['email_attach_enabled'] ) ? 1 : 0;
            $targets = isset( $_POST['email_attach_targets'] ) && is_array( $_POST['email_attach_targets'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['email_attach_targets'] ) ) : [];
            $settings['email_attach_targets'] = $targets;
            $settings['email_attach_packing_admin_only'] = ! empty( $_POST['email_attach_packing_admin_only'] ) ? 1 : 0;
            $settings['pdf_footer_text'] = sanitize_text_field( wp_unslash( $_POST['pdf_footer_text'] ?? '' ) );
            $settings['pdf_thank_you'] = sanitize_text_field( wp_unslash( $_POST['pdf_thank_you'] ?? '' ) );
            $settings['pdf_font_file'] = sanitize_text_field( wp_unslash( $_POST['pdf_font_file'] ?? '' ) );
            $settings['pdf_click_action'] = sanitize_key( wp_unslash( $_POST['pdf_click_action'] ?? ( $settings['pdf_click_action'] ?? 'print' ) ) );

            self::update( $settings );

            echo '<div class="notice notice-success"><p>' . esc_html__( 'PDF settings saved.', 'sr-pos-pdf-invoice-for-woo' ) . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'WooCommerce PDF Settings', 'sr-pos-pdf-invoice-for-woo' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'wcposm_save_pdf_settings' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Primary Color', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="text" name="pdf_primary_color" class="regular-text" value="<?php echo esc_attr( $settings['pdf_primary_color'] ?? '#111827' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Font Family (mPDF)', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <input type="text" name="pdf_font_family" class="regular-text" value="<?php echo esc_attr( $settings['pdf_font_family'] ?? 'dejavusans' ); ?>" />
                            <p class="description"><?php esc_html_e( 'Built‑in Bengali font is included (wcposm_bn). You can still use a custom TTF via "Custom Font File" below if needed.', 'sr-pos-pdf-invoice-for-woo' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Custom Font File (relative to wp-uploads)', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <input type="text" name="pdf_font_file" class="regular-text" value="<?php echo esc_attr( $settings['pdf_font_file'] ?? '' ); ?>" />
                            <p class="description"><?php esc_html_e( 'Example: wcposm-fonts/NotoSansBengali-Regular.ttf (place file in wp-content/uploads/wcposm-fonts/)', 'sr-pos-pdf-invoice-for-woo' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Watermark Opacity', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="text" name="pdf_watermark_opacity" class="small-text" value="<?php echo esc_attr( $settings['pdf_watermark_opacity'] ?? '0.08' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'PDF Button Action', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <select name="pdf_click_action">
                                <option value="print" <?php selected( $settings['pdf_click_action'] ?? 'print', 'print' ); ?>><?php esc_html_e( 'Open print page (recommended)', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="download" <?php selected( $settings['pdf_click_action'] ?? 'print', 'download' ); ?>><?php esc_html_e( 'Direct download (requires mPDF)', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="view" <?php selected( $settings['pdf_click_action'] ?? 'print', 'view' ); ?>><?php esc_html_e( 'Open PDF in new tab (requires mPDF)', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Invoice/Packing icon click action from Orders list and POS.', 'sr-pos-pdf-invoice-for-woo' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show SKU', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><label><input type="checkbox" name="pdf_show_sku" value="1" <?php checked( ! empty( $settings['pdf_show_sku'] ) ); ?>/> <?php esc_html_e( 'Enable', 'sr-pos-pdf-invoice-for-woo' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Product Image', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><label><input type="checkbox" name="pdf_show_image" value="1" <?php checked( ! empty( $settings['pdf_show_image'] ) ); ?>/> <?php esc_html_e( 'Enable', 'sr-pos-pdf-invoice-for-woo' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Customer Details', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><label><input type="checkbox" name="pdf_show_customer_details" value="1" <?php checked( ! empty( $settings['pdf_show_customer_details'] ) ); ?>/> <?php esc_html_e( 'Enable', 'sr-pos-pdf-invoice-for-woo' ); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Show Shipping Address', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><label><input type="checkbox" name="pdf_show_shipping_address" value="1" <?php checked( ! empty( $settings['pdf_show_shipping_address'] ) ); ?>/> <?php esc_html_e( 'Enable', 'sr-pos-pdf-invoice-for-woo' ); ?></label></td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email PDF Attachments', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="email_attach_enabled" value="1" <?php checked( ! empty( $settings['email_attach_enabled'] ) ); ?>/> <?php esc_html_e( 'Attach invoice/packing slip PDF in WooCommerce emails (requires mPDF).', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                            <p class="description"><?php esc_html_e( 'If mPDF is not installed, attachments will be skipped automatically.', 'sr-pos-pdf-invoice-for-woo' ); ?></p>
                            <p><strong><?php esc_html_e( 'Attach to emails:', 'sr-pos-pdf-invoice-for-woo' ); ?></strong></p>
                            <?php
                                $options = [
                                    'customer_processing_order' => __( 'Customer: Processing order', 'sr-pos-pdf-invoice-for-woo' ),
                                    'customer_completed_order'  => __( 'Customer: Completed order', 'sr-pos-pdf-invoice-for-woo' ),
                                    'customer_on_hold_order'    => __( 'Customer: On-hold order', 'sr-pos-pdf-invoice-for-woo' ),
                                    'new_order'                 => __( 'Admin: New order', 'sr-pos-pdf-invoice-for-woo' ),
                                ];
                                $selected_targets = is_array( $settings['email_attach_targets'] ?? [] ) ? ( $settings['email_attach_targets'] ?? [] ) : [];
                            ?>
                            <?php foreach ( $options as $key => $label ) : ?>
                                <label style="display:block;margin:4px 0;">
                                    <input type="checkbox" name="email_attach_targets[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected_targets, true ) ); ?>/>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                            <label style="display:block;margin-top:6px;">
                                <input type="checkbox" name="email_attach_packing_admin_only" value="1" <?php checked( ! empty( $settings['email_attach_packing_admin_only'] ) ); ?>/>
                                <?php esc_html_e( 'For "Admin: New order" email attach Packing Slip (otherwise attach Invoice).', 'sr-pos-pdf-invoice-for-woo' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Custom Footer Text', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="text" name="pdf_footer_text" class="regular-text" value="<?php echo esc_attr( $settings['pdf_footer_text'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Thank You Message', 'sr-pos-pdf-invoice-for-woo' ); ?></th>
                        <td><input type="text" name="pdf_thank_you" class="regular-text" value="<?php echo esc_attr( $settings['pdf_thank_you'] ?? '' ); ?>" /></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="wcposm_save_pdf" class="button button-primary"><?php esc_html_e( 'Save PDF Settings', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                </p>
            </form>

            
            <hr/>
            <h2><?php echo esc_html__( 'PDF Engine (mPDF)', 'sr-pos-pdf-invoice-for-woo' ); ?></h2>
            <?php
                $autoload = WCPOSM_DIR . 'vendor/autoload.php';
                $installed = false;
                if ( file_exists( $autoload ) ) {
                    require_once $autoload;
                    $installed = class_exists( '\Mpdf\Mpdf' );
                }
            ?>
            <p>
                <?php if ( $installed ) : ?>
                    <span class="dashicons dashicons-yes" style="color:#16a34a;"></span>
                    <strong><?php echo esc_html__( 'mPDF is installed.', 'sr-pos-pdf-invoice-for-woo' ); ?></strong>
                <?php else : ?>
                    <span class="dashicons dashicons-warning" style="color:#d97706;"></span>
                    <strong><?php echo esc_html__( 'mPDF is NOT installed yet.', 'sr-pos-pdf-invoice-for-woo' ); ?></strong>
                <?php endif; ?>
            </p>

        </div>
        <?php
    }
}
