<?php
/**
 * bKash Hooks Class - Handles all WordPress/WooCommerce hooks
 */
class BKash_Hooks {
    
    public function __construct() {
        $this->init_hooks();
        $this->maybe_init_charge_hooks();
    }
    
    /**
     * Initialize all hooks
     */
    private function init_hooks() {
        // Checkout validation and processing
        add_action('woocommerce_checkout_process', array($this, 'validate_payment_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_payment_fields'));
        
        // Admin order display
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_data'));
        add_action('woocommerce_order_details_after_customer_details', array($this, 'display_order_review_fields'));
        
        // Admin columns
        add_filter('manage_edit-shop_order_columns', array($this, 'add_admin_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'populate_admin_column'), 2);
        
        // Frontend styles
        add_action('wp_head', array($this, 'checkout_styles'));
    }
    
    /**
     * Initialize bKash charge hooks if enabled
     */
    private function maybe_init_charge_hooks() {
        $bKash_charge = get_option('woocommerce_pay_bKash_settings');
        if (isset($bKash_charge['bKash_charge']) && $bKash_charge['bKash_charge'] == 'yes') {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('woocommerce_cart_calculate_fees', array($this, 'add_bkash_charge'));
        }
    }
    
    /**
     * Validate payment fields on checkout
     */
    public function validate_payment_fields() {
        if ($_POST['payment_method'] != 'pay_bKash') {
            return;
        }
        
        $bKash_number = sanitize_text_field($_POST['bKash_number']);
        
        if (empty($bKash_number)) {
            wc_add_notice(esc_html__('Please add bKash Number', 'stb'), 'error');
            return;
        }
        
        $validate_number = preg_match('/^01[5-9]\d{8}$/', $bKash_number);
        if (!$validate_number) {
            wc_add_notice(esc_html__('Incorrect mobile number.', 'stb'), 'error');
        }
    }
    
    /**
     * Save payment fields to order meta
     */
    public function save_payment_fields($order_id) {
        if ($_POST['payment_method'] != 'pay_bKash') {
            return;
        }
        
        $bKash_number = sanitize_text_field($_POST['bKash_number']);
        if (!empty($bKash_number)) {
            update_post_meta($order_id, '_bKash_number', $bKash_number);
        }
    }
    
    /**
     * Display bKash data in admin order page
     */
    public function display_admin_order_data($order) {
        if ($order->payment_method != 'pay_bKash') {
            return;
        }
        
        $number = get_post_meta($order->id, '_bKash_number', true);
        ?>
        <div class="form-field form-field-wide">
            <img src='<?php echo plugins_url("images/bkash.png", BKASH_EASYPAY_PLUGIN_FILE); ?>' 
                 alt="bKash" style="max-width: 100px; height: auto;">    
            <table class="wp-list-table widefat fixed striped posts">
                <tbody>
                    <tr>
                        <th><strong><?php esc_html_e('Customer bKash Account Number', 'stb'); ?></strong></th>
                        <td style="font-size: 16px; font-weight: bold;">: <?php echo esc_attr($number); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display bKash data on order review page
     */
    public function display_order_review_fields($order) {
        if ($order->payment_method != 'pay_bKash') {
            return;
        }
        
        $number = get_post_meta($order->id, '_bKash_number', true);
        ?>
        <tr>
            <th><?php esc_html_e('Your bKash Account Number:', 'stb'); ?></th>
            <td><?php echo esc_attr($number); ?></td>
        </tr>
        <?php
    }
    
    /**
     * Add custom admin column
     */
    public function add_admin_column($columns) {
        $new_columns = (is_array($columns)) ? $columns : array();
        unset($new_columns['order_actions']);
        $new_columns['mobile_no'] = esc_html__('Send From', 'stb');
        $new_columns['order_actions'] = $columns['order_actions'];
        return $new_columns;
    }
    
    /**
     * Populate custom admin column
     */
    public function populate_admin_column($column) {
        global $post;
        
        if ($column == 'mobile_no') {
            $mobile_no = get_post_meta($post->ID, '_bKash_number', true);
            echo esc_attr($mobile_no);
        }
    }
    
    /**
     * Add checkout page styles
     */
    public function checkout_styles() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <style>
            li.wc_payment_method.payment_method_pay_bKash {
                border: solid 2px #e7e7e7;
                padding: 10px;
                border-radius: 8px;
                background-color: #fff;
            }
            .payment_method_pay_bKash img {
                max-width: 60px !important;
                height: auto !important;
            }
        </style>
        <?php
    }
    
    /**
     * Enqueue scripts for bKash charge functionality
     */
    public function enqueue_scripts() {
        wp_enqueue_script('bkash-script', 
            plugins_url('js/scripts.js', BKASH_EASYPAY_PLUGIN_FILE), 
            array('jquery'), BKASH_EASYPAY_VERSION, true);
    }
    
    /**
     * Add bKash charge to cart
     */
    public function add_bkash_charge() {
        global $woocommerce;
        
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
        $current_gateway = '';
        
        if (!empty($available_gateways)) {
            if (isset($woocommerce->session->chosen_payment_method) && 
                isset($available_gateways[$woocommerce->session->chosen_payment_method])) {
                $current_gateway = $available_gateways[$woocommerce->session->chosen_payment_method];
            }
        }
        
        if ($current_gateway && $current_gateway->id == 'pay_bKash') {
            $percentage = 0.02;
            $surcharge = ($woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total) * $percentage;
            $woocommerce->cart->add_fee(esc_html__('bKash Charge', 'stb'), $surcharge, true, '');
        }
    }
}