<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap wcposm-wrap">
    <h1 class="wcposm-title"><?php echo esc_html__( 'POS Manager', 'sr-pos-pdf-invoice-for-woo' ); ?></h1>

    <div class="wcposm-grid">
        <div class="wcposm-panel wcposm-products">
            <div class="wcposm-panel-header">
                <input type="text" id="wcposm-search" placeholder="<?php echo esc_attr__( 'Search products (name, SKU, custom meta)…', 'sr-pos-pdf-invoice-for-woo' ); ?>" />
            </div>
            <div id="wcposm-product-list" class="wcposm-product-list wcposm-product-list--list">
                <div class="wcposm-muted"><?php echo esc_html__( 'Type to search, or wait for products to load…', 'sr-pos-pdf-invoice-for-woo' ); ?></div>
            </div>
        </div>

        <div class="wcposm-panel wcposm-cart">
            <div class="wcposm-cart-head">
                <div class="wcposm-customer-bar">
                    <div class="wcposm-customer-left">
                        <div class="wcposm-customer-label">
                            <strong><?php echo esc_html__( 'Customer', 'sr-pos-pdf-invoice-for-woo' ); ?>:</strong>
                            <span id="wcposm-customer-selected" class="wcposm-pill"><?php echo esc_html__( 'Guest', 'sr-pos-pdf-invoice-for-woo' ); ?></span>
                        </div>
                        <input type="text" id="wcposm-customer-search" placeholder="<?php echo esc_attr__( 'Search customer (name/email/phone)…', 'sr-pos-pdf-invoice-for-woo' ); ?>" />
                        <div id="wcposm-customer-results" class="wcposm-dropdown"></div>
                    </div>

                    <div class="wcposm-customer-actions">
                        <button class="button button-primary" id="wcposm-new-customer"><?php echo esc_html__( 'New', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                        <button class="button" id="wcposm-edit-customer"><?php echo esc_html__( 'Edit', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                    </div>
                </div>
                <div class="wcposm-customer-summary" id="wcposm-customer-summary"></div>
            </div>

            <div class="wcposm-cart-body">
                <div class="wcposm-cart-section">
                    <h2><?php echo esc_html__( 'Cart', 'sr-pos-pdf-invoice-for-woo' ); ?></h2>
                    <div id="wcposm-cart-items" class="wcposm-cart-items"></div>
                </div>

                <div class="wcposm-cart-section">
                    <h2><?php echo esc_html__( 'Totals', 'sr-pos-pdf-invoice-for-woo' ); ?></h2>
                    <div class="wcposm-totals">
                        <div><span><?php echo esc_html__( 'Subtotal', 'sr-pos-pdf-invoice-for-woo' ); ?></span><strong id="wcposm-subtotal">0</strong></div>
                        <div><span><?php echo esc_html__( 'Shipping', 'sr-pos-pdf-invoice-for-woo' ); ?></span><strong id="wcposm-shipping-total">0</strong></div>
                        <div><span><?php echo esc_html__( 'Discount', 'sr-pos-pdf-invoice-for-woo' ); ?></span><strong id="wcposm-discount-total">0</strong></div>
                        <div class="wcposm-grand"><span><?php echo esc_html__( 'Grand Total', 'sr-pos-pdf-invoice-for-woo' ); ?></span><strong id="wcposm-grand">0</strong></div>
                    </div>
                </div>

                <div id="wcposm-after-create" class="wcposm-after" style="display:none;">
                    <a class="button wcposm-pdfbtn wcposm-invoice" target="_blank" id="wcposm-download-invoice" href="#" title="<?php echo esc_attr__( 'Invoice PDF', 'sr-pos-pdf-invoice-for-woo' ); ?>"><span class="wcposm-pdficon" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html__( 'Invoice PDF', 'sr-pos-pdf-invoice-for-woo' ); ?></span></a>
                    <a class="button wcposm-pdfbtn wcposm-packing" target="_blank" id="wcposm-download-packing" href="#" title="<?php echo esc_attr__( 'Packing Slip PDF', 'sr-pos-pdf-invoice-for-woo' ); ?>"><span class="wcposm-pdficon" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html__( 'Packing Slip PDF', 'sr-pos-pdf-invoice-for-woo' ); ?></span></a>
                </div>

                <div id="wcposm-msg" class="wcposm-msg"></div>
            </div>

            <div class="wcposm-cart-foot">
                <div class="wcposm-foot-grid">
                    <div class="wcposm-foot-item">
                        <label><?php echo esc_html__( 'Shipping', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                        <input type="number" step="0.01" id="wcposm-shipping" value="0" />
                    </div>

                    <div class="wcposm-foot-item">
                        <label><?php echo esc_html__( 'Discount', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                        <div class="wcposm-foot-row">
                            <select id="wcposm-discount-type">
                                <option value="none"><?php echo esc_html__( 'None', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="percent"><?php echo esc_html__( '%', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                                <option value="fixed"><?php echo esc_html__( 'Fixed', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            </select>
                            <input type="number" step="0.01" id="wcposm-discount-value" value="0" />
                        </div>
                    </div>

                    <div class="wcposm-foot-item">
                        <label><?php echo esc_html__( 'Order Status', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                        <select id="wcposm-status">
                            <?php foreach ( wc_get_order_statuses() as $wcposm_key => $wcposm_label ) :
                                $wcposm_k = str_replace( 'wc-', '', $wcposm_key );
                            ?>
                                <option value="<?php echo esc_attr( $wcposm_k ); ?>"><?php echo esc_html( $wcposm_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wcposm-foot-item">
                        <label><?php echo esc_html__( 'Payment', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                        <select id="wcposm-payment">
                            <option value="pos_cash"><?php echo esc_html__( 'Cash', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            <option value="cod"><?php echo esc_html__( 'Cash on Delivery', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            <option value="pos_card"><?php echo esc_html__( 'Card', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            <option value="pos_bank"><?php echo esc_html__( 'Bank', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                            <option value="pos_custom"><?php echo esc_html__( 'Custom', 'sr-pos-pdf-invoice-for-woo' ); ?></option>
                        </select>
                    </div>

                    <div class="wcposm-foot-item wcposm-foot-cta">
                        <button class="button button-primary button-hero" id="wcposm-create-order"><?php echo esc_html__( 'Create Order', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="wcposm-modal" class="wcposm-modal" style="display:none;">
    <div class="wcposm-modal-backdrop"></div>
    <div class="wcposm-modal-card" role="dialog" aria-modal="true">
        <div class="wcposm-modal-header">
            <h3 id="wcposm-modal-title"><?php echo esc_html__( 'Customer', 'sr-pos-pdf-invoice-for-woo' ); ?></h3>
            <button class="button" id="wcposm-modal-close">×</button>
        </div>
        <div class="wcposm-modal-body">
            <input type="hidden" id="wcposm-modal-id" value="0" />
            <input type="hidden" id="wcposm-modal-mode" value="new" />
            <div class="wcposm-row wcposm-2col">
                <div>
                    <label><?php echo esc_html__( 'Name', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                    <input type="text" id="wcposm-modal-name" />
                </div>
                <div>
                    <label><?php echo esc_html__( 'Phone', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                    <input type="text" id="wcposm-modal-phone" />
                </div>
            </div>
            <div class="wcposm-row">
                <label><?php echo esc_html__( 'Email', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                <input type="email" id="wcposm-modal-email" />
                <p class="description wcposm-muted"><?php echo esc_html__( 'New customer তৈরি করতে Email required.', 'sr-pos-pdf-invoice-for-woo' ); ?></p>
            </div>

            <div class="wcposm-row">
                <label><?php echo esc_html__( 'Billing Address', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                <input type="text" id="wcposm-modal-billing-address" />
            </div>
            <div class="wcposm-row wcposm-3col">
                <div><label><?php echo esc_html__( 'City', 'sr-pos-pdf-invoice-for-woo' ); ?></label><input type="text" id="wcposm-modal-billing-city" /></div>
                <div><label><?php echo esc_html__( 'Postcode', 'sr-pos-pdf-invoice-for-woo' ); ?></label><input type="text" id="wcposm-modal-billing-postcode" /></div>
                <div><label><?php echo esc_html__( 'Country', 'sr-pos-pdf-invoice-for-woo' ); ?></label><input type="text" id="wcposm-modal-billing-country" /></div>
            </div>

            <div class="wcposm-row">
                <label><?php echo esc_html__( 'Shipping Address', 'sr-pos-pdf-invoice-for-woo' ); ?></label>
                <input type="text" id="wcposm-modal-shipping-address" />
            </div>
            <div class="wcposm-row wcposm-3col">
                <div><label><?php echo esc_html__( 'City', 'sr-pos-pdf-invoice-for-woo' ); ?></label><input type="text" id="wcposm-modal-shipping-city" /></div>
                <div><label><?php echo esc_html__( 'Postcode', 'sr-pos-pdf-invoice-for-woo' ); ?></label><input type="text" id="wcposm-modal-shipping-postcode" /></div>
                <div><label><?php echo esc_html__( 'Country', 'sr-pos-pdf-invoice-for-woo' ); ?></label><input type="text" id="wcposm-modal-shipping-country" /></div>
            </div>
        </div>
        <div class="wcposm-modal-footer">
            <button class="button button-primary" id="wcposm-modal-save"><?php echo esc_html__( 'Save', 'sr-pos-pdf-invoice-for-woo' ); ?></button>
        </div>
    </div>
</div>