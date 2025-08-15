<?php
/**
 * bKash Payment Gateway Class
 */
class BKash_Payment_Gateway extends WC_Payment_Gateway {
    
    public $bKash_number;
    public $number_type;
    public $order_status;
    public $instructions;
    public $bKash_charge;
    public $bKash_qr_code;
    
    public function __construct() {
        $this->id = 'pay_bKash';
        $this->title = $this->get_option('title', 'bKash P2P Gateway');
        $this->description = $this->get_option('description', 'bKash Manual Payment');
        $this->method_title = esc_html__("bKash", "stb");
        $this->method_description = esc_html__("bKash Manual Payment Options", "stb");
        $this->icon = plugins_url('images/bkash.png', BKASH_EASYPAY_PLUGIN_FILE);
        $this->has_fields = true;
        
        $this->init_form_fields();
        $this->init_settings();
        $this->init_properties();
        $this->init_hooks();
    }
    
    /**
     * Initialize gateway properties
     */
    private function init_properties() {
        $this->bKash_number = $this->get_option('bKash_number');
        $this->number_type = $this->get_option('number_type');
        $this->order_status = $this->get_option('order_status');
        $this->instructions = $this->get_option('instructions');
        $this->bKash_charge = $this->get_option('bKash_charge');
        $this->bKash_qr_code = $this->get_option('bKash_qr_code');
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'thankyou_page_message'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }
    
    /**
     * Initialize form fields for admin settings
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => esc_html__('Enable/Disable', "stb"),
                'type' => 'checkbox',
                'label' => esc_html__('bKash Payment', "stb"),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => esc_html__('Title', "stb"),
                'type' => 'text',
                'default' => esc_html__('bKash', "stb")
            ),
            'description' => array(
                'title' => esc_html__('Description', "stb"),
                'type' => 'textarea',
                'default' => esc_html__('Please fill out the checkout form to confirm the payment.', "stb"),
                'desc_tip' => true
            ),
            'order_status' => array(
                'title' => esc_html__('Order Status', "stb"),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => esc_html__('Choose whether status you wish after checkout.', "stb"),
                'default' => 'wc-on-hold',
                'desc_tip' => true,
                'options' => wc_get_order_statuses()
            ),
            'bKash_number' => array(
                'title' => esc_html__('bKash Number', "stb"),
                'description' => esc_html__('Add a bKash Number which will be shown in checkout page', "stb"),
                'type' => 'number',
                'desc_tip' => true
            ),
            'number_type' => array(
                'title' => esc_html__('bKash Account Type', "stb"),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => esc_html__('Select bKash account type', "stb"),
                'options' => array(
                    'Agent' => esc_html__('Agent', "stb"),
                    'Personal' => esc_html__('Personal', "stb")
                ),
                'desc_tip' => true
            ),
            'bKash_charge' => array(
                'title' => esc_html__('Enable bKash Charge', "stb"),
                'type' => 'checkbox',
                'label' => esc_html__('Add 2% bKash "Payment" charge to net price', "stb"),
                'description' => esc_html__('If a product price is 1000 then customer have to pay ( 1000 + 20 ) = 1020. Here 20 is bKash charge', "stb"),
                'default' => 'no',
                'desc_tip' => true
            ),
            'instructions' => array(
                'title' => esc_html__('Thank You Page Message', "stb"),
                'type' => 'textarea',
                'description' => esc_html__('Instructions that will be added to the thank you page and emails.', "stb"),
                'default' => esc_html__('Thank you for your purchase! We will review it and update you as soon as possible.', "stb"),
                'desc_tip' => true
            ),
            'bKash_qr_code' => array(
                'title' => esc_html__('bKash QR Code', "stb"),
                'type' => 'text',
                'description' => esc_html__('Upload QR code image for bKash payment', "stb"),
                'desc_tip' => true,
                'custom_attributes' => array(
                    'readonly' => 'readonly',
                ),
                'default' => '',
            ),
        );
    }
    
    /**
     * Display payment fields on checkout page
     */
    public function payment_fields() {
        global $woocommerce;
        
        $bKash_charge = ($this->bKash_charge == 'yes') ? 
            esc_html__(' Note: 2% bKash "Send Money" cost will be added with the net price.', "stb") . 
            '<br><br><strong>' . esc_html__('Total Amount:', "stb") . '</strong> ' . 
            get_woocommerce_currency_symbol() . $woocommerce->cart->total : '';
            
        echo wpautop(wptexturize(esc_html__($this->description, "stb")) . $bKash_charge);
        
        $this->render_payment_form();
    }
    
    /**
     * Render payment form HTML
     */
    private function render_payment_form() {
        ?>
        <div style="margin-bottom: 10px;">
            <span>
                <strong>bKash Number <?php echo ($this->number_type == 'Agent') ? '(Cash Out)' : '(Send Money)'; ?>:</strong> 
                <span id="bkash-number-display" style="font-size: 16px; font-weight: normal;">
                    <?php echo $this->bKash_number; ?>
                </span>
            </span>
            <button type="button" id="copy-bkash-number" style="margin-left: 10px; font-size: 14px; padding: 2px 8px; cursor: pointer; background: #e2136e; border-radius: 25px; color: #fff;">
                Copy Number
            </button>
            <?php if (!empty($this->bKash_qr_code)): ?>
                <button type="button" id="toggle-qr-code" style="margin-left: 10px; font-size: 14px; padding: 2px 8px; cursor: pointer; background: #e2136e; border-radius: 25px; color: #fff;">
                    Show QR Code
                </button>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($this->bKash_qr_code)): ?>
            <div id="qr-code-container" style="display: none; text-align: center; margin: 15px 0;">
                <img src="<?php echo esc_url($this->bKash_qr_code); ?>" alt="bKash QR Code" 
                     style="max-width: 250px !important; height: auto !important; border: 2px solid #ddd; border-radius: 8px;">
            </div>
        <?php endif; ?>

        <?php $this->render_payment_scripts(); ?>
        
        <div style="display: flex; margin-top: 15px; flex-direction: column;">
            <label for="bKash_number" style="margin: 0; font-weight: bold;">
                <?php esc_html_e('Your bKash Number (used for payment):', "stb"); ?>
            </label>
            <input type="text" name="bKash_number" id="bKash_number" placeholder="017XXXXXXXX" style="margin-top: 5px;">
        </div>
        <?php
    }
    
    /**
     * Render JavaScript for payment form
     */
    private function render_payment_scripts() {
        ?>
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
                setTimeout(function() {
                    button.innerText = 'Copy Number';
                }, 2000);
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
        <?php
    }
    
    /**
     * Process payment
     */
    public function process_payment($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);
        
        $status = 'wc-' === substr($this->order_status, 0, 3) ? 
                  substr($this->order_status, 3) : $this->order_status;
                  
        $order->update_status($status, esc_html__('Checkout with bKash payment. ', "stb"));
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();
        
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
    
    /**
     * Thank you page message
     */
    public function thankyou_page_message() {
        $order_id = get_query_var('order-received');
        $order = new WC_Order($order_id);
        
        if ($order->payment_method == $this->id) {
            return $this->instructions;
        }
        
        return esc_html__('Thank you. Your order has been received.', "stb");
    }
    
    /**
     * Email instructions
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($order->payment_method != $this->id) {
            return;
        }
        
        if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }
}