<?php
/**
 * bKash Admin Class
 */
class BKash_Admin {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
        add_action('admin_footer', array($this, 'qr_upload_script'));
        add_action('admin_head', array($this, 'admin_styles'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $icon_url = plugins_url('images/bkash.png', BKASH_EASYPAY_PLUGIN_FILE);
        
        // Main menu
        add_menu_page(
            'bKash Settings',
            'bKash EasyPay',
            'manage_options',
            'pay_bKash_settings_menu',
            array($this, 'redirect_to_settings'),
            $icon_url,
            55
        );
        
        // Transaction Information submenu
        add_submenu_page(
            'pay_bKash_settings_menu',
            'Transaction Information',
            'Transaction Information',
            'manage_options',
            'pay_bKash_transactions',
            array($this, 'transactions_page')
        );
    }
    
    /**
     * Redirect to WooCommerce settings
     */
    public function redirect_to_settings() {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=pay_bKash'));
        exit;
    }
    
    /**
     * Transactions page callback
     */
    public function transactions_page() {
        $transactions = new BKash_Transactions();
        $transactions->display_page();
    }
    
    /**
     * Enqueue media scripts
     */
    public function enqueue_media_scripts($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings') {
            return;
        }
        
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
            return;
        }
        
        wp_enqueue_media();
    }
    
    /**
     * QR upload script
     */
    public function qr_upload_script() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings' || 
            !isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            setTimeout(function() {
                var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
                
                if (qrField.length && !$('#upload_qr_code_button').length) {
                    var buttonHtml = '<div style="margin-top: 10px;">';
                    buttonHtml += '<button type="button" class="button button-secondary" id="upload_qr_code_button">Upload QR Code</button> ';
                    buttonHtml += '<button type="button" class="button button-secondary" id="remove_qr_code_button">Remove</button>';
                    buttonHtml += '</div>';
                    
                    qrField.closest('td').append(buttonHtml);
                }
                
                $(document).on('click', '#upload_qr_code_button', function(e) {
                    e.preventDefault();
                    
                    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                        alert('WordPress media library is not loaded properly. Please refresh the page and try again.');
                        return;
                    }
                    
                    var frame = wp.media({
                        title: 'Select QR Code Image',
                        button: { text: 'Use This Image' },
                        multiple: false,
                        library: { type: 'image' }
                    });
                    
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
                        qrField.val(attachment.url).trigger('change');
                    });
                    
                    frame.open();
                });
                
                $(document).on('click', '#remove_qr_code_button', function(e) {
                    e.preventDefault();
                    var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
                    qrField.val('').trigger('change');
                });
                
            }, 1000);
        });
        </script>
        <?php
    }
    
    /**
     * Admin styles
     */
    public function admin_styles() {
        ?>
        <style>
            #toplevel_page_pay_bKash_settings_menu .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
            }
        </style>
        <?php
    }
}