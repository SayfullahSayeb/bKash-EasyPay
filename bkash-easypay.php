<?php 
/*
Plugin Name: bKash EasyPay
Plugin URI:  https://labartise.com
Description: bKash EasyPay is a manual payment gateway for WooCommerce that allows customers to pay via bKash.
Version:     2.0.0
Author:      Labartise
Author URI:  https://labartise.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 5.6
GitHub Plugin URI: https://github.com/SayfullahSayeb/bKash-EasyPay
*/

defined('ABSPATH') or die('Only a foolish person try to access directly to see this white page. :-) ');

// Plugin constants
define('pay_bKash__VERSION', '2.0.0');
define('pay_bKash__PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Helper Functions - Centralized common operations
 */

// Get bKash number from order using multiple fallback methods
function pay_bKash_get_order_number($order_id) {
    $order = is_object($order_id) ? $order_id : wc_get_order($order_id);
    if (!$order) return '';
    
    // Try multiple methods to get bKash number
    $methods = ['_bKash_number', 'bKash_number'];
    foreach ($methods as $method) {
        $number = $order->get_meta($method, true);
        if (!empty($number)) return $number;
        
        $number = get_post_meta($order->get_id(), $method, true);
        if (!empty($number)) return $number;
    }
    return '';
}

// Check if current payment method is bKash
function pay_bKash_is_payment_method($order) {
    return $order && $order->get_payment_method() === 'pay_bKash';
}

// Get status color for order status
function pay_bKash_get_status_color($status) {
    $colors = [
        'pending' => '#f56e28', 'processing' => '#8bc34a', 'on-hold' => '#ff9800',
        'completed' => '#4caf50', 'cancelled' => '#000000', 'refunded' => '#9e9e9e', 'failed' => '#d63638'
    ];
    return $colors[$status] ?? '#666';
}

// Validate Bangladeshi mobile number
function pay_bKash_validate_number($number) {
    return preg_match('/^01[5-9]\d{8}$/', $number);
}

/**
 * Plugin core start - Check WooCommerce activation
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    
    // Register bKash gateway
    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'pay_bKash';
        return $gateways;
    });

    // Initialize bKash gateway
    add_action('plugins_loaded', function() {
        
        class pay_bKash extends WC_Payment_Gateway {

            public $bKash_number, $number_type, $order_status, $instructions, $bKash_charge, $bKash_qr_code;

            public function __construct() {
                $this->id = 'pay_bKash';
                $this->title = $this->get_option('title', 'bKash P2P Gateway');
                $this->description = $this->get_option('description', 'bKash Manual Payment');
                $this->method_title = esc_html__("bKash", "stb");
                $this->method_description = esc_html__("bKash Manual Payment Options", "stb");
                $this->icon = plugins_url('images/bkash.png', __FILE__);
                $this->has_fields = true;
                
                $this->pay_bKash_options_fields();
                $this->init_settings();
                
                // Initialize properties
                $options = ['bKash_number', 'number_type', 'order_status', 'instructions', 'bKash_charge', 'bKash_qr_code'];
                foreach ($options as $option) {
                    $this->$option = $this->get_option($option);
                }

                // Hook actions
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
                add_filter('woocommerce_thankyou_order_received_text', [$this, 'pay_bKash_thankyou_page']);
                add_action('woocommerce_email_before_order_table', [$this, 'pay_bKash_number_instructions'], 10, 3);
            }

            public function pay_bKash_options_fields() {
                $this->form_fields = [
                    'enabled' => [
                        'title' => esc_html__('Enable/Disable', "stb"),
                        'type' => 'checkbox',
                        'label' => esc_html__('bKash Payment', "stb"),
                        'default' => 'yes'
                    ],
                    'title' => [
                        'title' => esc_html__('Title', "stb"),
                        'type' => 'text',
                        'default' => esc_html__('bKash', "stb")
                    ],
                    'description' => [
                        'title' => esc_html__('Description', "stb"),
                        'type' => 'textarea',
                        'default' => esc_html__('Please fill out the checkout form to confirm the payment.', "stb"),
                        'desc_tip' => true
                    ],
                    'order_status' => [
                        'title' => esc_html__('Order Status', "stb"),
                        'type' => 'select',
                        'class' => 'wc-enhanced-select',
                        'description' => esc_html__('Choose whether status you wish after checkout.', "stb"),
                        'default' => 'wc-on-hold',
                        'desc_tip' => true,
                        'options' => wc_get_order_statuses()
                    ],
                    'bKash_number' => [
                        'title' => esc_html__('bKash Number', "stb"),
                        'description' => esc_html__('Add a bKash Number which will be shown in checkout page', "stb"),
                        'type' => 'number',
                        'desc_tip' => true
                    ],
                    'number_type' => [
                        'title' => esc_html__('bKash Account Type', "stb"),
                        'type' => 'select',
                        'class' => 'wc-enhanced-select',
                        'description' => esc_html__('Select bKash account type', "stb"),
                        'options' => [
                            'Agent' => esc_html__('Agent', "stb"),
                            'Personal' => esc_html__('Personal', "stb")
                        ],
                        'desc_tip' => true
                    ],
                    'bKash_charge' => [
                        'title' => esc_html__('Enable bKash Charge', "stb"),
                        'type' => 'checkbox',
                        'label' => esc_html__('Add 2% bKash "Payment" charge to net price', "stb"),
                        'description' => esc_html__('If a product price is 1000 then customer have to pay ( 1000 + 20 ) = 1020. Here 20 is bKash charge', "stb"),
                        'default' => 'no',
                        'desc_tip' => true
                    ],
                    'instructions' => [
                        'title' => esc_html__('Thank You Page Message', "stb"),
                        'type' => 'textarea',
                        'description' => esc_html__('Instructions that will be added to the thank you page and emails.', "stb"),
                        'default' => esc_html__('Thank you for your purchase! We will review it and update you as soon as possible.', "stb"),
                        'desc_tip' => true
                    ],
                    'bKash_qr_code' => [
                        'title' => esc_html__('bKash QR Code', "stb"),
                        'type' => 'text',
                        'description' => esc_html__('Upload QR code image for bKash payment', "stb"),
                        'desc_tip' => true,
                        'custom_attributes' => ['readonly' => 'readonly'],
                        'default' => ''
                    ]
                ];
            }

            public function payment_fields() {
                global $woocommerce;
                
                // Display charge information if enabled
                $bKash_charge = ($this->bKash_charge == 'yes') 
                    ? esc_html__(' Note: 2% bKash "Send Money" cost will be added with the net price.', "stb") . '<br><br><strong>' 
                      . esc_html__('Total Amount:', "stb") . '</strong> ' . get_woocommerce_currency_symbol() . $woocommerce->cart->total 
                    : '';
                
                echo wpautop(wptexturize(esc_html__($this->description, "stb")) . $bKash_charge);
                
                $account_type = ($this->number_type == 'Agent') ? '(Cash Out)' : '(Send Money)';
                ?>
                <div style="margin-bottom: 10px;">
                    <span><strong>bKash Number <?php echo $account_type; ?>:</strong> 
                        <span id="bkash-number-display" style="font-size: 16px; font-weight: normal;"><?php echo $this->bKash_number; ?></span>
                    </span>
                    <button type="button" id="copy-bkash-number" style="margin-left: 10px; font-size: 14px; padding: 2px 8px; cursor: pointer; background: #e2136e; border-radius: 25px; color: #fff;">Copy Number</button>
                    <?php if (!empty($this->bKash_qr_code)): ?>
                        <button type="button" id="toggle-qr-code" style="margin-left: 10px; font-size: 14px; padding: 2px 8px; cursor: pointer; background: #e2136e; border-radius: 25px; color: #fff;">Show QR Code</button>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($this->bKash_qr_code)): ?>
                    <div id="qr-code-container" style="display: none; text-align: center; margin: 15px 0;">
                        <img src="<?php echo esc_url($this->bKash_qr_code); ?>" alt="bKash QR Code" style="max-width: 250px !important; height: auto !important; border: 2px solid #ddd; border-radius: 8px;">
                    </div>
                <?php endif; ?>

                <script type="text/javascript">
                    document.getElementById('copy-bkash-number').addEventListener('click', function() {
                        var bkashNumber = document.getElementById('bkash-number-display').innerText;
                        var tempInput = document.createElement('input');
                        tempInput.value = bkashNumber;
                        document.body.appendChild(tempInput);
                        tempInput.select();
                        document.execCommand('copy');
                        document.body.removeChild(tempInput);
                        this.innerText = 'Copied!';
                        var button = this;
                        setTimeout(function() { button.innerText = 'Copy Number'; }, 2000);
                    });

                    <?php if (!empty($this->bKash_qr_code)): ?>
                    document.getElementById('toggle-qr-code').addEventListener('click', function() {
                        var qrContainer = document.getElementById('qr-code-container');
                        if (qrContainer.style.display === 'none') {
                            qrContainer.style.display = 'block';
                            this.innerText = 'Hide QR Code';
                        } else {
                            qrContainer.style.display = 'none';
                            this.innerText = 'Show QR Code';
                        }
                    });
                    <?php endif; ?>
                </script>
                
                <div style="display: flex; margin-top: 15px; flex-direction: column;">
                    <label for="bKash_number" style="margin: 0; font-weight: bold;"><?php esc_html_e('Your bKash Number (used for payment):', "stb"); ?></label>
                    <input type="text" name="bKash_number" id="bKash_number" placeholder="017XXXXXXXX" style="margin-top: 5px;">
                </div>
                <?php 
            }

            public function process_payment($order_id) {
                global $woocommerce;
                $order = new WC_Order($order_id);
                
                $status = 'wc-' === substr($this->order_status, 0, 3) ? substr($this->order_status, 3) : $this->order_status;
                $order->update_status($status, esc_html__('Checkout with bKash payment. ', "stb"));
                $order->reduce_order_stock();
                $woocommerce->cart->empty_cart();

                return [
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                ];
            }

            public function pay_bKash_thankyou_page() {
                $order_id = get_query_var('order-received');
                $order = new WC_Order($order_id);
                
                return pay_bKash_is_payment_method($order) 
                    ? $this->instructions 
                    : esc_html__('Thank you. Your order has been received.', "stb");
            }

            public function pay_bKash_number_instructions($order, $sent_to_admin, $plain_text = false) {
                if (!pay_bKash_is_payment_method($order)) return;
                
                if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
                    echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
                }
            }
        }
    });

    /**
     * Plugin Settings and Links
     */
    add_filter("plugin_action_links_" . plugin_basename(__FILE__), function($links) {
        $settings_links = [
            '<a href="https://www.facebook.com/Labartise" target="_blank">' . esc_html__('Follow us', 'stb') . '</a>',
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=pay_bKash') . '">' . esc_html__('Settings', 'stb') . '</a>'
        ];
        
        foreach ($settings_links as $link) {
            array_unshift($links, $link);
        }
        return $links;
    });

    /**
     * bKash Charge Functionality
     */
    $bKash_charge = get_option('woocommerce_pay_bKash_settings');
    if ($bKash_charge['bKash_charge'] == 'yes') {
        
        add_action('wp_enqueue_scripts', function() {
            wp_enqueue_script('stb-script', plugins_url('js/scripts.js', __FILE__), ['jquery'], '1.0', true);
        });

        add_action('woocommerce_cart_calculate_fees', function() {
            global $woocommerce;
            
            if (is_admin() && !defined('DOING_AJAX')) return;
            
            $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
            $current_gateway = '';

            if (!empty($available_gateways) && isset($woocommerce->session->chosen_payment_method)) {
                $current_gateway = $available_gateways[$woocommerce->session->chosen_payment_method] ?? '';
            }
            
            if ($current_gateway && $current_gateway->id == 'pay_bKash') {
                $percentage = 0.02;
                $surcharge = ($woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total) * $percentage;
                $woocommerce->cart->add_fee(esc_html__('bKash Charge', 'stb'), $surcharge, true, '');
            }
        });
    }

    /**
     * Form Validation and Processing
     */
    add_action('woocommerce_checkout_process', function() {
        if ($_POST['payment_method'] != 'pay_bKash') return;

        $bKash_number = sanitize_text_field($_POST['bKash_number'] ?? '');

        if (empty($bKash_number)) {
            wc_add_notice(esc_html__('Please add bKash Number', 'stb'), 'error');
        } elseif (!pay_bKash_validate_number($bKash_number)) {
            wc_add_notice(esc_html__('Incorrect mobile number.', 'stb'), 'error');
        }
    });

    add_action('woocommerce_checkout_update_order_meta', function($order_id) {
        if ($_POST['payment_method'] != 'pay_bKash') return;
        
        $bKash_number = sanitize_text_field($_POST['bKash_number'] ?? '');
        if ($bKash_number) {
            update_post_meta($order_id, '_bKash_number', $bKash_number);
        }
    });

    /**
     * Admin Order Display Functions
     */
    add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
        if (!pay_bKash_is_payment_method($order)) return;

        $number = pay_bKash_get_order_number($order);
        ?>
        <div class="form-field form-field-wide">
            <img src='<?php echo plugins_url("images/bkash.png", __FILE__); ?>' alt="bKash" style="max-width: 100px; height: auto;">
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
    });

    add_action('woocommerce_order_details_after_customer_details', function($order) {
        if (!pay_bKash_is_payment_method($order)) return;

        $number = pay_bKash_get_order_number($order);
        ?>
        <tr>
            <th><?php esc_html_e('Your bKash Account Number:', 'stb'); ?></th>
            <td><?php echo esc_attr($number); ?></td>
        </tr>
        <?php
    });

    /**
     * Admin Column Management
     */
    add_filter('manage_edit-shop_order_columns', function($columns) {
        $new_columns = is_array($columns) ? $columns : [];
        unset($new_columns['order_actions']);
        $new_columns['mobile_no'] = esc_html__('Send From', 'stb');
        $new_columns['order_actions'] = $columns['order_actions'];
        return $new_columns;
    });

    add_action('manage_shop_order_posts_custom_column', function($column) {
        global $post;
        if ($column == 'mobile_no') {
            echo esc_attr(pay_bKash_get_order_number($post->ID));
        }
    }, 2);

    /**
     * Styling
     */
    add_action('wp_head', function() {
        if (is_checkout()) {
            ?>
            <style>
                li.wc_payment_method.payment_method_pay_bKash {
                    border: solid 2px #e7e7e7; padding: 10px; border-radius: 8px; background-color: #fff;
                }
                .payment_method_pay_bKash img { max-width: 60px !important; height: auto !important; }
            </style>
            <?php
        }
    });

    add_action('admin_head', function() {
        ?>
        <style>
            #toplevel_page_pay_bKash_settings_menu .wp-menu-image img { width: 20px !important; height: 20px !important; }
        </style>
        <?php
    });

    /**
     * Media Upload Scripts for QR Code
     */
    add_action('admin_enqueue_scripts', function($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings' || 
            !isset($_GET['tab']) || $_GET['tab'] !== 'checkout') return;
        wp_enqueue_media();
    });

    add_action('admin_footer', function() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings' || 
            !isset($_GET['tab']) || $_GET['tab'] !== 'checkout') return;
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            setTimeout(function() {
                var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
                
                if (qrField.length && !$('#upload_qr_code_button').length) {
                    var buttonHtml = '<div style="margin-top: 10px;">' +
                        '<button type="button" class="button button-secondary" id="upload_qr_code_button">Upload QR Code</button> ' +
                        '<button type="button" class="button button-secondary" id="remove_qr_code_button">Remove</button>' +
                        '</div>';
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
                    $('#woocommerce_pay_bKash_bKash_qr_code').val('').trigger('change');
                });
                
            }, 1000);
        });
        </script>
        <?php
    });

    /**
     * Admin Menu
     */
    add_action('admin_menu', function() {
        $icon_url = plugins_url('images/bkash.png', __FILE__);
        
        add_menu_page(
            'bKash Settings', 'bKash EasyPay', 'manage_options', 'pay_bKash_settings_menu',
            function() { 
                wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=pay_bKash'));
                exit;
            },
            $icon_url, 55
        );
        
        add_submenu_page(
            'pay_bKash_settings_menu', 'Transaction Information', 'Transaction Information',
            'manage_options', 'pay_bKash_transactions', 'pay_bKash_transactions_page'
        );
    });

    /**
     * Transaction Information Page
     */
    function pay_bKash_transactions_page() {
        $search = sanitize_text_field($_GET['search'] ?? '');
        $per_page = 20;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count for all bKash transactions
        $total_all_args = ['limit' => -1, 'payment_method' => 'pay_bKash', 'return' => 'ids'];
        $total_all_orders = count(wc_get_orders($total_all_args));
        
        // Handle search functionality
        $search_order_ids = [];
        $search_results_count = 0;
        
        if (!empty($search)) {
            $all_bkash_orders = wc_get_orders($total_all_args);
            
            foreach ($all_bkash_orders as $order_id) {
                if (is_numeric($search) && $order_id == intval($search)) {
                    $search_order_ids[] = $order_id;
                    continue;
                }
                
                $bkash_number = pay_bKash_get_order_number($order_id);
                if (!empty($bkash_number) && stripos($bkash_number, $search) !== false) {
                    $search_order_ids[] = $order_id;
                }
            }
            
            $search_order_ids = array_unique($search_order_ids);
            $search_results_count = count($search_order_ids);
        }
        
        // Get orders with proper filtering
        $args = [
            'payment_method' => 'pay_bKash',
            'return' => 'objects',
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if (!empty($search)) {
            if (!empty($search_order_ids)) {
                $args['post__in'] = $search_order_ids;
                $args['limit'] = $per_page;
                $args['offset'] = $offset;
            } else {
                $orders = [];
                $total_orders = 0;
                $total_pages = 0;
            }
        } else {
            $args['limit'] = $per_page;
            $args['offset'] = $offset;
        }
        
        if (!isset($orders)) {
            $orders = wc_get_orders($args);
            $total_orders = !empty($search) && !empty($search_order_ids) 
                ? count($search_order_ids) 
                : count(wc_get_orders(['limit' => -1, 'payment_method' => 'pay_bKash', 'return' => 'ids']));
            $total_pages = ceil($total_orders / $per_page);
        }
        
        ?>
        <div class="wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin: 0;"><?php esc_html_e('bKash Transaction Information', 'stb'); ?></h1>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pay_bKash_transactions&action=export_csv'), 'export_bkash_csv'); ?>" 
                   class="button button-secondary">
                    <?php esc_html_e('Export to CSV', 'stb'); ?>
                </a>
            </div>
            
            <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php esc_html_e('Total Transactions:', 'stb'); ?></strong> <?php echo $total_all_orders; ?>
                        <?php if (!empty($search)): ?>
                            | <strong><?php esc_html_e('Search Results:', 'stb'); ?></strong> <?php echo $search_results_count; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="get" action="" style="display: flex; align-items: center; gap: 10px;">
                        <input type="hidden" name="page" value="pay_bKash_transactions">
                        <label for="search"><strong><?php esc_html_e('Search:', 'stb'); ?></strong></label>
                        <input type="text" id="search" name="search" value="<?php echo esc_attr($search); ?>" 
                               placeholder="<?php esc_attr_e('Order ID or bKash Number', 'stb'); ?>" style="width: 200px;">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Search', 'stb'); ?>">
                        <?php if (!empty($search)): ?>
                            <a href="<?php echo admin_url('admin.php?page=pay_bKash_transactions'); ?>" class="button">
                                <?php esc_html_e('Clear', 'stb'); ?>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div style="background: #fff; margin: 20px 0;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 80px;"><strong><?php esc_html_e('Order ID', 'stb'); ?></strong></th>
                            <th style="width: 120px;"><strong><?php esc_html_e('Payment Method', 'stb'); ?></strong></th>
                            <th style="width: 130px;"><strong><?php esc_html_e('bKash Number', 'stb'); ?></strong></th>
                            <th style="width: 100px;"><strong><?php esc_html_e('Amount', 'stb'); ?></strong></th>
                            <th style="width: 120px;"><strong><?php esc_html_e('Date', 'stb'); ?></strong></th>
                            <th style="width: 120px;"><strong><?php esc_html_e('Order Status', 'stb'); ?></strong></th>
                            <th style="width: 100px;"><strong><?php esc_html_e('Action', 'stb'); ?></strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <?php 
                                if (!$order) continue;
                                $order_id = $order->get_id();
                                $bkash_number = pay_bKash_get_order_number($order);
                                $order_total = $order->get_total();
                                $order_date = $order->get_date_created();
                                $order_status = $order->get_status();
                                $status_name = wc_get_order_status_name($order_status);
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $order_id; ?></strong></td>
                                    <td>
                                        <img src="<?php echo plugins_url('images/bkash.png', __FILE__); ?>" 
                                             alt="bKash" style="width: 20px; height: auto; margin-right: 5px;">
                                        bKash
                                    </td>
                                    <td>
                                        <?php if (!empty($bkash_number)): ?>
                                            <strong><?php echo esc_html($bkash_number); ?></strong>
                                        <?php else: ?>
                                            <em style="color: #999;"><?php esc_html_e('Not provided', 'stb'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo wc_price($order_total); ?></strong></td>
                                    <td><?php echo $order_date->date('Y-m-d H:i:s'); ?></td>
                                    <td>
                                        <span class="order-status status-<?php echo esc_attr($order_status); ?>" 
                                              style="padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; 
                                                     background-color: <?php echo pay_bKash_get_status_color($order_status); ?>; 
                                                     color: white;">
                                            <?php echo esc_html($status_name); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id); ?>" 
                                           class="button button-small button-primary">
                                            <?php esc_html_e('View/Edit', 'stb'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <p style="font-size: 16px; color: #666;">
                                        <?php echo !empty($search) 
                                            ? esc_html__('No transactions found for your search.', 'stb')
                                            : esc_html__('No bKash transactions found.', 'stb'); ?>
                                    </p>
                                    <p style="color: #999; font-size: 14px;">
                                        <?php esc_html_e('Make sure you have orders with bKash payment method.', 'stb'); ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(esc_html__('%d items', 'stb'), $total_orders); ?>
                        </span>
                        
                        <?php
                        $base_url = admin_url('admin.php?page=pay_bKash_transactions');
                        if (!empty($search)) {
                            $base_url .= '&search=' . urlencode($search);
                        }
                        
                        echo paginate_links([
                            'base' => $base_url . '%_%',
                            'format' => '&paged=%#%',
                            'current' => $current_page,
                            'total' => $total_pages,
                            'prev_text' => '&laquo; ' . esc_html__('Previous', 'stb'),
                            'next_text' => esc_html__('Next', 'stb') . ' &raquo;'
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <style>
                .order-status { display: inline-block; min-width: 80px; text-align: center; }
            </style>
        </div>
        <?php
    }

    /**
     * CSV Export Handler
     */
    add_action('admin_init', function() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'pay_bKash_transactions' || 
            !isset($_GET['action']) || $_GET['action'] !== 'export_csv') return;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_bkash_csv') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'stb'));
        }
        
        $order_ids = wc_get_orders(['limit' => -1, 'payment_method' => 'pay_bKash', 'return' => 'ids']);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bkash-transactions-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order ID', 'Payment Method', 'bKash Number', 'Amount', 'Date', 'Order Status']);
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            fputcsv($output, [
                $order_id, 'bKash', pay_bKash_get_order_number($order), $order->get_total(),
                $order->get_date_created()->date('Y-m-d H:i:s'), wc_get_order_status_name($order->get_status())
            ]);
        }
        
        fclose($output);
        exit;
    });

} else {
    // WooCommerce not active - show admin notice and deactivate
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><a href="http://wordpress.org/extend/plugins/woocommerce/"><?php esc_html_e('Woocommerce', 'stb'); ?></a> 
               <?php esc_html_e('plugin need to active if you wanna use bKash plugin.', 'stb'); ?></p>
        </div>
        <?php
    });
    
    add_action('admin_init', function() {
        deactivate_plugins(plugin_basename(__FILE__));
        unset($_GET['activate']);
    });
}
?>
