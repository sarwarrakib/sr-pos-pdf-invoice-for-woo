<?php
namespace WCPOSM;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PDF_Generator {

    /**
     * Remove characters that often render as "□□" in PDF/print when icon fonts or
     * complex unicode marks are present.
     */
    private function strip_pua_and_controls( string $s ) : string {
        if ( $s === '' ) { return ''; }

        // BOM
        $s = preg_replace( '/\x{FEFF}/u', '', $s );

        // Invisible format characters (ZWSP/ZWJ/ZWNJ/LRM/RLM, etc.)
        // NOTE: Do NOT strip ZWJ/ZWNJ (U+200D/U+200C) as it can break Bengali conjunct shaping.
        // Remove only the most problematic format chars (ZWSP, soft hyphen, word joiner).
        $s = preg_replace( '/[\x{00AD}\x{200B}\x{2060}]+/u', '', $s );

        // Combining Grapheme Joiner
        $s = preg_replace( '/\x{034F}/u', '', $s );

        // Directional marks
        $s = preg_replace( '/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $s );

        // Replacement char + common square/tofu glyphs
        $s = preg_replace( '/[\x{FFFD}\x{25A1}\x{25A0}\x{25FB}\x{25FC}\x{25FD}\x{25FE}]/u', '', $s );

        // Private Use Areas (BMP + supplementary planes)
        $s = preg_replace( '/[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}]/u', '', $s );

        // Control chars except \t \n \r
        $s = preg_replace( '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $s );

        // Collapse weird whitespace
        $s = preg_replace( '/\s+/u', ' ', $s );

        return trim( $s );
    }

    /**
     * Wrap Bengali-script runs in an explicit span so mPDF can reliably render
     * mixed Bengali/English strings even when font substitution is imperfect.
     * Input must be plain text (no HTML).
     */
    private function wrap_bn_runs_html( string $s ) : string {
        if ( $s === '' ) { return ''; }

        // Split into Bengali and non-Bengali runs.
        $parts = preg_split( '/(\p{Bengali}+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE );
        if ( ! is_array( $parts ) ) { return esc_html( $s ); }

        $out = '';
        foreach ( $parts as $p ) {
            if ( $p === '' ) { continue; }
            if ( preg_match( '/^\p{Bengali}+$/u', $p ) ) {
                $out .= '<span class="wcposm-bnrun">' . esc_html( $p ) . '</span>';
            } else {
                $out .= esc_html( $p );
            }
        }
        return $out;
    }

    /**
     * Format billing/shipping address as safe HTML with explicit Bengali spans.
     */
    private function format_address_html( \WC_Order $order, string $which ) : string {
        $a = $order->get_address( $which );
        if ( ! is_array( $a ) ) { return ''; }

        $line1    = trim( (string) ( $a['address_1'] ?? '' ) );
        $line2    = trim( (string) ( $a['address_2'] ?? '' ) );
        $city     = trim( (string) ( $a['city'] ?? '' ) );
        $state    = trim( (string) ( $a['state'] ?? '' ) );
        $postcode = trim( (string) ( $a['postcode'] ?? '' ) );
        $country  = trim( (string) ( $a['country'] ?? '' ) );

        // Resolve country/state to readable names when possible.
        $country_code = (string) ( $a['country'] ?? '' );
        if ( $country_code && function_exists( 'WC' ) && WC() && isset( WC()->countries ) ) {
            $countries = WC()->countries->get_countries();
            if ( is_array( $countries ) && isset( $countries[ $country_code ] ) && $countries[ $country_code ] ) {
                $country = (string) $countries[ $country_code ];
            }
            if ( $state ) {
                $states = WC()->countries->get_states( $country_code );
                if ( is_array( $states ) && isset( $states[ $state ] ) && $states[ $state ] ) {
                    $state = (string) $states[ $state ];
                }
            }
        }

        $parts = [];
        if ( $line1 ) { $parts[] = $line1; }
        if ( $line2 ) { $parts[] = $line2; }

        $city_parts = [];
        if ( $city ) { $city_parts[] = $city; }
        if ( $state ) { $city_parts[] = $state; }
        if ( $postcode ) { $city_parts[] = $postcode; }
        if ( $city_parts ) { $parts[] = implode( ', ', $city_parts ); }

        if ( $country ) { $parts[] = $country; }

        $parts = array_map( fn( $p ) => $this->strip_pua_and_controls( (string) $p ), $parts );
        $parts = array_values( array_filter( $parts, fn( $p ) => $p !== '' ) );
        if ( ! $parts ) { return ''; }

        $lines = [];
        foreach ( $parts as $p ) {
            $lines[] = $this->wrap_bn_runs_html( $p );
        }
        return implode( '<br>', $lines );
    }

    /**
     * Return true when the current site/admin locale is Bengali.
     */
    private function is_bn_locale() : bool {
        $loc = function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale();
        $loc = strtolower( $loc );
        return str_starts_with( $loc, 'bn' );
    }

    /**
     * Stable bilingual labels (avoid translation strings that may contain mixed/duplicated text).
     */
    private function ui_label( string $key ) : string {
        $bn = $this->is_bn_locale();
        $map = [
            'bill_to'      => [ 'Bill To', 'বিল টু' ],
            'ship_to'      => [ 'Ship To', 'শিপ টু' ],
            // Trailing space is intentional so labels don't visually "stick" to values in PDF (mPDF) output.
            'name'         => [ 'Name: ', 'নামঃ ' ],
            'phone'        => [ 'Phone: ', 'ফোনঃ ' ],
            'email'        => [ 'Email: ', 'ইমেইলঃ ' ],
            'address'      => [ 'Address: ', 'ঠিকানাঃ ' ],
            'invoice'      => [ 'INVOICE', 'ইনভয়েস' ],
            'packing'      => [ 'PACKING SLIP', 'প্যাকিং স্লিপ' ],
            'order_id'     => [ 'Order ID:', 'অর্ডার আইডিঃ' ],
            'order_status' => [ 'Order Status:', 'অর্ডার স্ট্যাটাসঃ' ],
            'order_date'   => [ 'Order Date:', 'অর্ডার ডেটঃ' ],
            'subtotal'     => [ 'Subtotal', 'সাবটোটাল' ],
            'shipping'     => [ 'Shipping', 'শিপিং' ],
            'discount'     => [ 'Discount', 'ডিসকাউন্ট' ],
            'grand_total'  => [ 'Grand Total', 'গ্র্যান্ড টোটাল' ],
            'auth_sign'    => [ 'Authorized Signature', 'অনুমোদিত স্বাক্ষর' ],
            'thank_you'    => [ 'Thank you for your purchase!', 'আপনার ক্রয়ের জন্য ধন্যবাদ!' ],
        ];

        $pair = $map[ $key ] ?? [ $key, $key ];
        $out  = $bn ? $pair[1] : $pair[0];
        $out = $this->strip_pua_and_controls( $out );
        // Ensure a visible gap after inline labels in mPDF (PDF View/Direct Download).
        if ( in_array( $key, [ 'name', 'phone', 'email', 'address' ], true ) ) {
            $out .= "\u{00A0}"; // NBSP
        }
        return $out;
}

    private function clean_output_buffers() : void {
        // Prevent "headers already sent" by clearing any buffered output before sending headers.
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }
    }

    public function output_order_pdf( WC_Order $order, string $type = 'invoice', string $mode = 'print' ) : void {
        $settings = Settings::get();

        // Normalize mode
        $allowed = [ 'print', 'download', 'view' ];
        if ( ! in_array( $mode, $allowed, true ) ) {
            $mode = $settings['pdf_click_action'] ?? 'print';
        }

        // Try to load mPDF if bundled/installed.
        $this->load_mpdf_autoload();
        $has_mpdf = class_exists( '\\Mpdf\\Mpdf' );

        // mPDF modes (build HTML optimized for mPDF output)
        if ( $has_mpdf && $mode === 'download' ) {
            $html = $this->build_html( $order, $type, $settings, true );
            $this->render_with_mpdf( $html, $order, $type, $settings, 'D' );
            return;
        }
        if ( $has_mpdf && $mode === 'view' ) {
            $html = $this->build_html( $order, $type, $settings, true );
            $this->render_with_mpdf( $html, $order, $type, $settings, 'I' );
            return;
        }

        // Print/preview HTML (browser print “Save as PDF”)
        $html = $this->build_html( $order, $type, $settings, false );

        // Default: Print page (works without any PDF library; users can Save as PDF from the print dialog)
        $this->render_print_page( $html, $order, $type, $has_mpdf );
        exit;
    }

    private function load_mpdf_autoload() : void {
        $autoload_candidates = [
            WCPOSM_DIR . 'vendor/autoload.php',
            WCPOSM_DIR . 'lib/mpdf/vendor/autoload.php',
        ];
        foreach ( $autoload_candidates as $autoload ) {
            if ( file_exists( $autoload ) ) {
                require_once $autoload;
                break;
            }
        }
    }

    private function make_mpdf( array $settings ) : \Mpdf\Mpdf {
        $upload    = wp_upload_dir();
        $tempDir   = trailingslashit( $upload['basedir'] ) . 'wcposm-mpdf-tmp';
        if ( ! is_dir( $tempDir ) ) {
            wp_mkdir_p( $tempDir );
        }

        $defaultsFontDir  = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $defaultsFontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

        $plugin_font_dir = trailingslashit( WCPOSM_DIR ) . 'assets/fonts';

        $fontDir  = $defaultsFontDir;

        // Reduce bundled mPDF font list to the fonts we actually ship.
        // This keeps the plugin ZIP small for WordPress.org uploads and prevents
        // autoLangToFont from selecting fonts whose TTF files are not present.
        $fontData = [];
        foreach ( [ 'dejavusans' ] as $k ) {
            if ( isset( $defaultsFontData[ $k ] ) ) {
                $fontData[ $k ] = $defaultsFontData[ $k ];
            }
        }

        // Bundled Bengali-capable font (OFL).
        $bn_r = $plugin_font_dir . '/NotoSansBengali-Regular.ttf';
        $bn_b = $plugin_font_dir . '/NotoSansBengali-Bold.ttf';
        $has_bn = file_exists( $bn_r );
        if ( $has_bn ) {
            $fontDir = array_merge( $fontDir, [ $plugin_font_dir ] );
            $fontData['notosansbengali'] = [
                'R'  => basename( $bn_r ),
                'B'  => file_exists( $bn_b ) ? basename( $bn_b ) : basename( $bn_r ),
                'I'  => basename( $bn_r ),
                'BI' => file_exists( $bn_b ) ? basename( $bn_b ) : basename( $bn_r ),
                            'useOTL' => 0xFF,
            ];

            // IMPORTANT:
            // mPDF routes Bengali (bn/ben) to the "freeserif" font via its built-in LanguageToFont map.
            // We intentionally remove mPDF's huge FreeSerif.ttf to keep this plugin under the
            // WordPress.org upload limit. To keep Bengali (and the Taka symbol ৳) rendering correctly,
            // alias "freeserif" to our bundled Bengali-capable font.
            $fontData['freeserif'] = $fontData['notosansbengali'];
        }

        // Custom uploaded font (in uploads directory), map all styles to avoid Bengali breaking on <strong>/<h*>.
        $custom_font_file = $settings['pdf_font_file'] ?? '';
        $custom_key = '';
        if ( $custom_font_file ) {
            $full = trailingslashit( $upload['basedir'] ) . ltrim( $custom_font_file, '/' );
            if ( file_exists( $full ) ) {
                $custom_key = 'wcposm_custom';
                $fontDir = array_merge( $fontDir, [ dirname( $full ) ] );
                $fontData[ $custom_key ] = [
                    'R'  => basename( $full ),
                    'B'  => basename( $full ),
                    'I'  => basename( $full ),
                    'BI' => basename( $full ),
                ];
            }
        }

        // Choose default font.
        // IMPORTANT: Always prefer the bundled Bengali-capable font when available.
        // Many users upload a Latin-only custom font; using it as default breaks Bengali and
        // results in "□□" tofu squares. We still register the custom font so it can be used
        // in CSS if needed, but we don't make it the global default.
        $requested    = $settings['pdf_font_family'] ?? '';
        $default_font = 'dejavusans';
        if ( ! $has_bn && $custom_key ) {
            $default_font = $custom_key;
        } elseif ( ! $has_bn && $requested && isset( $fontData[ $requested ] ) ) {
            $default_font = $requested;
        }

        $config = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'tempDir' => $tempDir,
            'default_font' => $default_font,
            'fontDir' => $fontDir,
            'fontdata' => $fontData,
        ];

        $mpdf = new \Mpdf\Mpdf( $config );

        
        // Improve mixed Bengali/English rendering: let mPDF pick suitable fonts per script.
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->useSubstitutions = true;
        // Ensure Bengali + Latin both render: keep Latin default, use Bengali font as backup substitution.
        $mpdf->backupSubsFont = [ 'notosansbengali', 'dejavusans' ];

// Better Unicode / Bengali handling
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->useSubstitutions = true;
        if ( property_exists( $mpdf, 'useOTL' ) ) { $mpdf->useOTL = 0xFF; }

        return $mpdf;
    }

    /**
     * Generate a PDF file on disk (for WooCommerce email attachments).
     * Requires mPDF to be available (vendor bundled).
     *
     * @return string|\WP_Error Absolute file path.
     */
    public function generate_pdf_file( WC_Order $order, string $type = 'invoice' ) {
        $settings = Settings::get();

        $this->load_mpdf_autoload();
        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            return new \WP_Error( 'wcposm_no_mpdf', 'mPDF not installed' );
        }

        $html = $this->build_html( $order, $type, $settings, true );

        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . 'wcposm-pdf-tmp';
        if ( ! wp_mkdir_p( $dir ) ) {
            return new \WP_Error( 'wcposm_tmp_dir', 'Cannot create temp directory' );
        }

        $filename = sprintf(
            '%s-%s-%s.pdf',
            $type === 'packing' ? 'packing-slip' : 'invoice',
            $order->get_order_number(),
            wp_generate_password( 6, false, false )
        );
        $path = trailingslashit( $dir ) . $filename;

        $mpdf = $this->make_mpdf( $settings );
        $this->apply_mpdf_watermark( $mpdf, $settings );

        $mpdf->WriteHTML( $html );
        $mpdf->Output( $path, 'F' );

        return $path;
    }

    private function apply_mpdf_watermark( \Mpdf\Mpdf $mpdf, array $settings ) : void {
        $wm_id = isset( $settings['company_watermark_logo_id'] ) ? absint( $settings['company_watermark_logo_id'] ) : 0;
        if ( ! $wm_id ) {
            return;
        }

        $wm_path = get_attached_file( $wm_id );
        if ( ! $wm_path || ! file_exists( $wm_path ) ) {
            return;
        }

        // Our setting is "opacity". Accept either 0-1 (e.g. 0.08) or 0-100 (e.g. 8).
        // mPDF watermark "alpha" parameter behaves like opacity: 0 = invisible, 1 = fully visible.
        $opacity_raw = isset( $settings['pdf_watermark_opacity'] ) ? floatval( $settings['pdf_watermark_opacity'] ) : 0.08;
        if ( $opacity_raw > 1.0 ) { $opacity_raw = $opacity_raw / 100.0; }
        $alpha = max( 0.0, min( 1.0, $opacity_raw ) );

        try {
            if ( class_exists( '\\Mpdf\\WatermarkImage' ) ) {
                $wm = new \Mpdf\WatermarkImage(
                    $wm_path,
                    60,
                    \Mpdf\WatermarkImage::POSITION_CENTER_FRAME,
                    $alpha,
                    true
                );
                $mpdf->SetWatermarkImage( $wm );
                if ( property_exists( $mpdf, 'watermarkImgBehind' ) ) {
                    $mpdf->watermarkImgBehind = true;
                }
            } else {
                // Fallback for older versions of mPDF.
                $mpdf->SetWatermarkImage( $wm_path, $alpha, 60, 'P' );
                if ( property_exists( $mpdf, 'watermarkImgBehind' ) ) {
                    $mpdf->watermarkImgBehind = true;
                }
                if ( property_exists( $mpdf, 'watermarkImageAlpha' ) ) {
                    $mpdf->watermarkImageAlpha = $alpha;
                }
            }

            $mpdf->showWatermarkImage = true;
        } catch ( \Throwable $e ) {
            // Ignore watermark errors.
        }
    }


private function render_print_page( string $html, WC_Order $order, string $type, bool $has_mpdf ) : void {
        $this->clean_output_buffers();
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        $title = ( $type === 'packing' ) ? 'PACKING SLIP' : 'INVOICE';
        $is_packing = ( $type === 'packing' );

        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title>';
        echo '<style>
            @media print{.noprint{display:none !important;}}
            .noprint{margin:14px 0;font-family:system-ui, -apple-system, Segoe UI, Roboto, Arial; font-size:13px;}
            .wcposm-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;}
            .wcposm-toolbar .left{display:flex;flex-direction:column;gap:2px;}
            .wcposm-toolbar .title{font-weight:700;color:#111827;}
            .wcposm-toolbar .hint{font-size:12px;color:#4b5563;}
            .wcposm-toolbar .actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;}
            .wcposm-toolbar a, .wcposm-toolbar button{appearance:none;border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:8px 10px;cursor:pointer;font-size:13px;text-decoration:none;color:#111827;line-height:1;}
            .wcposm-toolbar a:hover, .wcposm-toolbar button:hover{background:#f3f4f6;}
        </style>';
        echo '</head><body>';

        echo '<div class="noprint">';
        echo '<div class="wcposm-toolbar">';
        echo '<div class="left"><div class="title">' . esc_html( $title ) . '</div><div class="hint">' . esc_html__( 'Print preview controls (won\'t appear on print).', 'sr-pos-pdf-invoice-for-woo' ) . '</div></div>';
        echo '<div class="actions">';
        echo '<button type="button" onclick="try{window.print();}catch(e){}">' . esc_html__( 'Print', 'sr-pos-pdf-invoice-for-woo' ) . '</button>';
        if ( $has_mpdf ) {
            $nonce = wp_create_nonce( 'wcposm_nonce' );
            $download_url = add_query_arg( [
                'action' => 'wcposm_print_pdf',
                'nonce' => $nonce,
                'order_id' => $order->get_id(),
                'type' => $type,
                'mode' => 'download',
            ], admin_url( 'admin-ajax.php' ) );
            $view_url = add_query_arg( [
                'action' => 'wcposm_print_pdf',
                'nonce' => $nonce,
                'order_id' => $order->get_id(),
                'type' => $type,
                'mode' => 'view',
            ], admin_url( 'admin-ajax.php' ) );
            echo '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'PDF View', 'sr-pos-pdf-invoice-for-woo' ) . '</a>';
            echo '<a href="' . esc_url( $download_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Direct PDF Download', 'sr-pos-pdf-invoice-for-woo' ) . '</a>';
        }
        echo '</div></div>';
        echo '<div class="hint" style="margin-top:8px; color:#4b5563; font-size:12px;">' . esc_html__( 'Tip: Use your browser print dialog and choose “Save as PDF”. For clean output, turn off “Headers and footers”.', 'sr-pos-pdf-invoice-for-woo' ) . '</div>';
        echo '</div>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Full HTML document output for print preview.
        echo $html;
        echo '<script>window.addEventListener("load",function(){setTimeout(function(){try{window.print();}catch(e){}},250);});</script>';
        echo '</body></html>';
        exit;
    }

    private function render_with_mpdf( string $html, WC_Order $order, string $type, array $settings, string $output_mode = 'D' ) : void {
        $this->clean_output_buffers();

        if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
            // Fall back to print page instead of breaking the site.
            $this->render_print_page( $html, $order, $type, false );
            return;
        }

        $mpdf = $this->make_mpdf( $settings );
        $this->apply_mpdf_watermark( $mpdf, $settings );

        $mpdf->WriteHTML( $html );

        $filename = sprintf(
            '%s-%s.pdf',
            $type === 'packing' ? 'packing-slip' : 'invoice',
            $order->get_order_number()
        );

        $mpdf->Output( $filename, $output_mode );
        exit;
    }

    private function get_watermark_url( array $settings ) : string {
        $id = isset( $settings['company_watermark_logo_id'] ) ? absint( $settings['company_watermark_logo_id'] ) : 0;
        if ( ! $id ) { return ''; }
        $url = wp_get_attachment_url( $id );
        return $url ?: '';
    }

    private function get_watermark_data_uri( array $settings ) : string {
        $id = isset( $settings['company_watermark_logo_id'] ) ? absint( $settings['company_watermark_logo_id'] ) : 0;
        if ( ! $id ) { return ''; }

        $path = get_attached_file( $id );
        if ( ! $path || ! file_exists( $path ) ) { return ''; }

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $mime = '';
        switch ( $ext ) {
            case 'png':  $mime = 'image/png';  break;
            case 'jpg':
            case 'jpeg': $mime = 'image/jpeg'; break;
            case 'gif':  $mime = 'image/gif';  break;
            case 'webp': $mime = 'image/webp'; break;
            default: return '';
        }

        $data = @file_get_contents( $path );
        if ( ! $data ) { return ''; }

        return 'data:' . $mime . ';base64,' . base64_encode( $data );
    }


    private function build_html( WC_Order $order, string $type, array $settings, bool $for_mpdf = false ) : string {
        $primary    = $settings['pdf_primary_color'] ?? '#111827';
        $show_sku   = ! empty( $settings['pdf_show_sku'] );
        $show_img   = ! empty( $settings['pdf_show_image'] );
        $show_cust  = ! empty( $settings['pdf_show_customer_details'] );
        $show_ship  = ! empty( $settings['pdf_show_shipping_address'] );
        $wm_opacity_raw = isset( $settings['pdf_watermark_opacity'] ) ? floatval( $settings['pdf_watermark_opacity'] ) : 0.08;
        if ( $wm_opacity_raw > 1.0 ) { $wm_opacity_raw = $wm_opacity_raw / 100.0; }
        $wm_opacity = max( 0.0, min( 1.0, $wm_opacity_raw ) );

        $company_name  = $settings['company_name'] ?? get_bloginfo( 'name' );
        $company_addr  = nl2br( esc_html( $settings['company_address'] ?? '' ) );
        $company_phone = esc_html( $settings['company_phone'] ?? '' );
        $company_email = esc_html( $settings['company_email'] ?? '' );

        $logo_id = isset( $settings['company_logo_id'] ) ? absint( $settings['company_logo_id'] ) : 0;
        $logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

        $title = ( $type === 'packing' ) ? 'PACKING SLIP' : 'INVOICE';

        $status       = $order->get_status();
        $status_label = wc_get_order_status_name( $status );
        $status_color = $this->status_color( $status );

        $date = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '';

        // Items
        $rows = '';
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $img = $product ? ( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ) ) : '';
            $sku = $product ? $product->get_sku() : '';
            $qty = (int) $item->get_quantity();
            $price = $order->get_item_total( $item, false, true );
            $total = $order->get_line_total( $item, false, true );

            $rows .= '<tr>';
            if ( $show_img ) {
                $rows .= '<td class="imgcell">' . ( $img ? '<img src="' . esc_url( $img ) . '" width="42" height="42" />' : '' ) . '</td>';
            }
            $rows .= '<td class="namecell">' . $this->wrap_bn_runs_html( $item->get_name() ) . '</td>';
            if ( $show_sku ) {
                $rows .= '<td class="skucell">' . esc_html( $sku ) . '</td>';
            }
            $rows .= '<td class="qtycell">' . esc_html( (string) $qty ) . '</td>';
            if ( ! $is_packing ) {
                $rows .= '<td class="pricecell">' . wc_price( $price, [ 'currency' => $order->get_currency() ] ) . '</td>';
                $rows .= '<td class="totalcell">' . wc_price( $total, [ 'currency' => $order->get_currency() ] ) . '</td>';
            }
            $rows .= '</tr>';
        }

        $subtotal       = $order->get_subtotal();
        $shipping_total = $order->get_shipping_total();
        $discount_total = $order->get_discount_total();

        $fee_discount = 0.0;
        foreach ( $order->get_items( 'fee' ) as $fee ) {
            $fee_total = (float) $fee->get_total();
            if ( $fee_total < 0 ) { $fee_discount += abs( $fee_total ); }
        }
        $discount_display = $discount_total > 0 ? $discount_total : $fee_discount;
        $grand = $order->get_total();

        $thankyou = esc_html( $settings['pdf_thank_you'] ?? 'Thank you for your purchase!' );
        $footer   = esc_html( $settings['pdf_footer_text'] ?? '' );

        // Watermark:
        // - Print/HTML: use data-uri when possible (reliable in all browsers), fallback to attachment URL.
        // - mPDF: use native mPDF watermark (see apply_mpdf_watermark), so don't include HTML watermark in PDF output.
        $wm_src = '';
        if ( ! $for_mpdf ) {
            $wm_src = $this->get_watermark_data_uri( $settings );
            if ( ! $wm_src ) { $wm_src = $this->get_watermark_url( $settings ); }
        }

        $wm_html = '';
        if ( $wm_src ) {
            $wm_attr = ( strpos( $wm_src, 'data:' ) === 0 ) ? esc_attr( $wm_src ) : esc_url( $wm_src );
            $wm_html = '<div class="wm" style="opacity:' . esc_attr( (string) $wm_opacity ) . ';" aria-hidden="true"><img src="' . $wm_attr . '" alt="" /></div>';
        }

        // Customer details
        $addr_html = '';
        if ( $show_cust ) {
            $bill_name  = trim( (string) $order->get_formatted_billing_full_name() );
            $bill_phone = trim( (string) $order->get_billing_phone() );
            // Some sites store phone values with a leading label like "Phone:" or even icon/emoji prefixes; strip them to avoid duplication/boxes.
            $bill_phone = preg_replace( '/^[\p{C}\p{So}\p{Sk}\p{Co}]+/u', '', $bill_phone );
            $bill_phone = preg_replace( '/^\s*((phone|mobile|ফোন|মোবাইল)\s*[:：\-]?\s*)+/iu', '', $bill_phone );
            $bill_phone = preg_replace( '/^\s*[:：\-–—]+\s*/u', '', $bill_phone );
            $bill_email = trim( (string) $order->get_billing_email() );

            // Strip private-use/control glyphs that render as □□□□ in PDF viewers.
            $bill_name  = $this->strip_pua_and_controls( $bill_name );
            $bill_phone = $this->strip_pua_and_controls( $bill_phone );
            $bill_email = $this->strip_pua_and_controls( $bill_email );
            // Format addresses with explicit Bengali spans so mixed EN/BN always renders correctly.
            $bill_addr = $this->format_address_html( $order, 'billing' );

            $ship_name = trim( $order->get_formatted_shipping_full_name() );
            $ship_addr = $this->format_address_html( $order, 'shipping' );

            $bill_box  = '<div class="wcposm-addrbox">';
            // Use stable bilingual labels (no mixed translation strings).
            $bill_box .= '<div class="wcposm-boxtitle"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'bill_to' ) ) . '</span></div>';
            if ( $bill_name ) { $bill_box .= '<div class="wcposm-line wcposm-name"><span class="wcposm-label"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'name' ) ) . '</span></span><span class="wcposm-value wcposm-nameval">' . $this->wrap_bn_runs_html( $bill_name ) . '</span></div>'; }
            if ( $bill_phone ) { $bill_box .= '<div class="wcposm-line"><span class="wcposm-label"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'phone' ) ) . '</span></span><span class="wcposm-value">' . $this->wrap_bn_runs_html( $bill_phone ) . '</span></div>'; }
            if ( $bill_email ) { $bill_box .= '<div class="wcposm-line"><span class="wcposm-label"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'email' ) ) . '</span></span><span class="wcposm-value">' . $this->wrap_bn_runs_html( $bill_email ) . '</span></div>'; }
            if ( $bill_addr ) { $bill_box .= '<div class="wcposm-line wcposm-addr"><span class="wcposm-label"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'address' ) ) . '</span></span><span class="wcposm-value wcposm-addrval">' . $bill_addr . '</span></div>'; }
            $bill_box .= '</div>';

            $ship_box = '';
            if ( $show_ship ) {
                $ship_box  = '<div class="wcposm-addrbox">';
                $ship_box .= '<div class="wcposm-boxtitle"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'ship_to' ) ) . '</span></div>';
                if ( $ship_name ) {
                    $ship_box .= '<div class="wcposm-line wcposm-name"><span class="wcposm-label"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'name' ) ) . '</span></span><span class="wcposm-value wcposm-nameval">' . $this->wrap_bn_runs_html( $ship_name ) . '</span></div>';
                }
                if ( $ship_addr ) {
                    $ship_box .= '<div class="wcposm-line wcposm-addr"><span class="wcposm-label"><span class="t">' . $this->wrap_bn_runs_html( $this->ui_label( 'address' ) ) . '</span></span><span class="wcposm-value wcposm-addrval">' . $ship_addr . '</span></div>';
                } else {
                    $ship_box .= '<div class="line muted bn">' . esc_html__( 'Same as billing address', 'sr-pos-pdf-invoice-for-woo' ) . '</div>';
                }
                $ship_box .= '</div>';
            }

            if ( $show_ship ) {
                // Two-column layout.
                $addr_html .= '<table class="addrtable"><tr>';
                $addr_html .= '<td class="addrcol addrpad-right">' . $bill_box . '</td>';
                $addr_html .= '<td class="addrcol addrpad-left">' . $ship_box . '</td>';
                $addr_html .= '</tr></table>';
            } else {
                // Single address box (full width).
                $addr_html .= '<div class="addrfull">' . $bill_box . '</div>';
            }
        }

        $primary_esc = esc_html( $primary );
        $status_color_esc = esc_html( $status_color );
        $wm_opacity_esc = esc_html( (string) $wm_opacity );

        // Browser-print font face (for Save as PDF). mPDF uses embedded fonts via fontdata.
        $bn_font_url_r = esc_url( WCPOSM_URL . 'assets/fonts/NotoSansBengali-Regular.ttf' );
        $bn_font_url_b = esc_url( WCPOSM_URL . 'assets/fonts/NotoSansBengali-Bold.ttf' );

        // Build a safe font stack for CSS (avoid referring to missing bundled fonts).
        // Put Bengali-capable font first to avoid tofu squares for bn labels/addresses.
        $font_stack = 'dejavusans, notosansbengali, sans-serif';
        $custom_font_file_css = $settings['pdf_font_file'] ?? '';
if ( $custom_font_file_css ) {
    $upload_css = wp_upload_dir();
    $full_css = trailingslashit( $upload_css['basedir'] ) . ltrim( $custom_font_file_css, '/' );
    if ( file_exists( $full_css ) ) {
        // Custom font is allowed but keep Bengali-capable font early in the stack.
        $font_stack = 'wcposm_custom, dejavusans, notosansbengali, sans-serif';
    }
}

        // Build @font-face for optional custom upload font (for browser print only).
        $custom_font_face_css = '';
        if ( $custom_font_file_css ) {
            $upload_css2 = wp_upload_dir();
            $custom_url  = trailingslashit( $upload_css2['baseurl'] ) . ltrim( $custom_font_file_css, '/' );
            $custom_font_face_css =
                "@font-face{font-family:'wcposm_custom';src:url('{$custom_url}') format('truetype');font-weight:400;font-style:normal;}" .
                "@font-face{font-family:'wcposm_custom';src:url('{$custom_url}') format('truetype');font-weight:700;font-style:normal;}";
        }

$css_path = WCPOSM_DIR . 'assets/css/pdf-inline.css';
        $css_raw  = file_exists( $css_path ) ? file_get_contents( $css_path ) : '';

        // Replace CSS placeholders with runtime values (ensures Bengali fonts + watermark opacity work in HTML Print View).
        $css_replacements = [
            '{$bn_font_url_r}'      => $bn_font_url_r,
            '{$bn_font_url_b}'      => $bn_font_url_b,
            '{$custom_font_face_css}' => $custom_font_face_css,
            '{$font_stack}'         => $font_stack,
            '{$wm_opacity_esc}'     => (string) $wm_opacity,
            '{$primary_esc}'        => esc_attr( $primary ),
            '{$status_color_esc}'   => esc_attr( $status_color ),
        ];
        $css_raw = strtr( $css_raw, $css_replacements );

        $css      = '<style>' . "\n" . $css_raw . "\n</style>";

        $order_id = $order->get_order_number();
        $status_badge = '<span class="badge">' . esc_html( $status_label ) . '</span>';

        $logo_html = '';
        if ( $logo ) {
            $logo_html = '<img src="' . esc_url( $logo ) . '" alt="" />';
        }

        $html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>';
        $html .= '<div class="wrap">' . $wm_html . '<div class="content">';

        $html .= '<div class="topline">';
        $html .= '<table class="headertable"><tr>';

        $html .= '<td style="width:60%">';
$html .= '<div class="logo">' . $logo_html . '</div>';
$html .= '<div class="company"><h1>' . esc_html( $company_name ) . '</h1>';
if ( $company_addr ) { $html .= '<div class="meta">' . $company_addr . '</div>'; }
if ( $company_phone ) { $html .= '<div class="meta">' . $this->wrap_bn_runs_html( $this->ui_label( 'phone' ) ) . esc_html( $company_phone ) . '</div>'; }
if ( $company_email ) { $html .= '<div class="meta">' . $this->wrap_bn_runs_html( $this->ui_label( 'email' ) ) . esc_html( $company_email ) . '</div>'; }
$html .= '</div>';

        $html .= '</td>';

        $html .= '<td style="width:40%; text-align:right;">';
        // Use stable bilingual doc title.
        $html .= '<p class="doc-title">' . esc_html( $is_packing ? $this->ui_label( 'packing' ) : $this->ui_label( 'invoice' ) ) . '</p>';
        $html .= '<div class="orderbox">';
        $html .= '<table class="ordertable">';
        $html .= '<tr><td class="k">' . $this->wrap_bn_runs_html( $this->ui_label( 'order_id' ) ) . '</td><td class="v">#' . esc_html( $order_id ) . '</td></tr>';
        if ( ! $is_packing ) {
            $html .= '<tr><td class="k">' . $this->wrap_bn_runs_html( $this->ui_label( 'order_status' ) ) . '</td><td class="v">' . $status_badge . '</td></tr>';
        }
        if ( $date ) {
            $html .= '<tr><td class="k">' . $this->wrap_bn_runs_html( $this->ui_label( 'order_date' ) ) . '</td><td class="v">' . esc_html( $date ) . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</td>';

        $html .= '</tr></table>';
        $html .= '</div>';

        $html .= $addr_html;

        $html .= '<table><thead><tr>';
        if ( $show_img ) { $html .= '<th>' . esc_html__( 'Image', 'sr-pos-pdf-invoice-for-woo' ) . '</th>'; }
        $html .= '<th>' . esc_html__( 'Product', 'sr-pos-pdf-invoice-for-woo' ) . '</th>';
        if ( $show_sku ) { $html .= '<th>' . esc_html__( 'SKU', 'sr-pos-pdf-invoice-for-woo' ) . '</th>'; }
        $html .= '<th>' . esc_html__( 'Qty', 'sr-pos-pdf-invoice-for-woo' ) . '</th>';
        if ( ! $is_packing ) { $html .= '<th>' . esc_html__( 'Price', 'sr-pos-pdf-invoice-for-woo' ) . '</th><th>' . esc_html__( 'Total', 'sr-pos-pdf-invoice-for-woo' ) . '</th>'; }
        $html .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

                if ( ! $is_packing ) {
            $html .= '<table class="summarywrap"><tr><td class="sumspacer"></td><td class="sumcol">';
        $html .= '<table class="summary">';
        $html .= '<tr><td>' . $this->wrap_bn_runs_html( $this->ui_label( 'subtotal' ) ) . '</td><td class="sumval">' . wc_price( $subtotal, [ 'currency' => $order->get_currency() ] ) . '</td></tr>';
        $html .= '<tr><td>' . $this->wrap_bn_runs_html( $this->ui_label( 'shipping' ) ) . '</td><td class="sumval">' . wc_price( $shipping_total, [ 'currency' => $order->get_currency() ] ) . '</td></tr>';
        $html .= '<tr><td>' . $this->wrap_bn_runs_html( $this->ui_label( 'discount' ) ) . '</td><td class="sumval">-' . wc_price( $discount_display, [ 'currency' => $order->get_currency() ] ) . '</td></tr>';
        $html .= '<tr class="grand"><td>' . $this->wrap_bn_runs_html( $this->ui_label( 'grand_total' ) ) . '</td><td class="sumval">' . wc_price( $grand, [ 'currency' => $order->get_currency() ] ) . '</td></tr>';
        $html .= '</table>';
        $html .= '</td></tr></table>';
        }

        $html .= '<div class="footer">';
        $html .= '<table class="footertable"><tr>';
        $html .= '<td class="footleft">' . ( $is_packing ? '' : '<div class="thanks bn">' . esc_html( $thankyou ) . '</div>' ) . '</td>';
        $html .= '<td class="footright"><div class="sign"><div class="sigline"></div><div class="sigtext">' . $this->wrap_bn_runs_html( $this->ui_label( 'auth_sign' ) ) . '</div></div></td>';
        $html .= '</tr></table>';
        if ( $footer ) { $html .= '<div class="small bn">' . esc_html( $footer ) . '</div>'; }
        $html .= '</div>';

        $html .= '</div></div></body></html>';
        return $html;
    }

    private function status_color( string $status ) : string {
        $status = strtolower( $status );
        if ( in_array( $status, [ 'completed' ], true ) ) { return '#16a34a'; }
        if ( in_array( $status, [ 'processing', 'on-hold' ], true ) ) { return '#f59e0b'; }
        if ( in_array( $status, [ 'cancelled', 'failed', 'refunded' ], true ) ) { return '#ef4444'; }
        return '#3b82f6';
    }
}