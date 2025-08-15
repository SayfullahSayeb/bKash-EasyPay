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
GitHub Plugin URI: https://github.com/SayfullahSayeb/bKash-EasyPay-WordPress-Plugin
*/
defined('ABSPATH') or die('Only a foolish person try to access directly to see this white page. :-) ');
define( 'pay_bKash__VERSION', '2.0.0' );
define( 'pay_bKash__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin core start
 * Checked Woocommerce activation
 */
if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
	
	/**
	 * bKash gateway register
	 */
	add_filter('woocommerce_payment_gateways', 'pay_bKash_payment_gateways');
	function pay_bKash_payment_gateways( $gateways ){
		$gateways[] = 'pay_bKash';
		return $gateways;
	}

	/**
	 * bKash gateway init
	 */
	add_action('plugins_loaded', 'pay_bKash_plugin_activation');
	function pay_bKash_plugin_activation(){
		
		class pay_bKash extends WC_Payment_Gateway {

			public $bKash_number;
			public $number_type;
			public $order_status;
			public $instructions;
			public $bKash_charge;
			public $bKash_qr_code;

			public function __construct(){
				$this->id 					= 'pay_bKash';
				$this->title 				= $this->get_option('title', 'bKash P2P Gateway');
				$this->description 			= $this->get_option('description', 'bKash Manual Payment');
				$this->method_title 		= esc_html__("bKash", "stb");
				$this->method_description 	= esc_html__("bKash Manual Payment Options", "stb" );
				$this->icon 				= plugins_url('images/bkash.png', __FILE__);
				$this->has_fields 			= true;
				$this->pay_bKash_options_fields();
				$this->init_settings();
				$this->bKash_number = $this->get_option('bKash_number');
				$this->number_type 	= $this->get_option('number_type');
				$this->order_status = $this->get_option('order_status');
				$this->instructions = $this->get_option('instructions');
				$this->bKash_charge = $this->get_option('bKash_charge');
				$this->bKash_qr_code = $this->get_option('bKash_qr_code');

				add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );
	            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'pay_bKash_thankyou_page' ) );
	            add_action( 'woocommerce_email_before_order_table', array( $this, 'pay_bKash_number_instructions' ), 10, 3 );
			}


			public function pay_bKash_options_fields(){
				$this->form_fields = array(
					'enabled' 	=>	array(
						'title'		=> esc_html__( 'Enable/Disable', "stb" ),
						'type' 		=> 'checkbox',
						'label'		=> esc_html__( 'bKash Payment', "stb" ),
						'default'	=> 'yes'
					),
					'title' 	=> array(
						'title' 	=> esc_html__( 'Title', "stb" ),
						'type' 		=> 'text',
						'default'	=> esc_html__( 'bKash', "stb" )
					),
					'description' => array(
						'title'		=> esc_html__( 'Description', "stb" ),
						'type' 		=> 'textarea',
						'default'	=> esc_html__( 'Please fill out the checkout form to confirm the payment.', "stb" ),
						'desc_tip'    => true
					),
	                'order_status' => array(
	                    'title'       => esc_html__( 'Order Status', "stb" ),
	                    'type'        => 'select',
	                    'class'       => 'wc-enhanced-select',
	                    'description' => esc_html__( 'Choose whether status you wish after checkout.', "stb" ),
	                    'default'     => 'wc-on-hold',
	                    'desc_tip'    => true,
	                    'options'     => wc_get_order_statuses()
	                ),				
					'bKash_number'	=> array(
						'title'			=> esc_html__( 'bKash Number', "stb" ),
						'description' 	=> esc_html__( 'Add a bKash Number which will be shown in checkout page', "stb" ),
						'type'			=> 'number',
						'desc_tip'      => true
					),
					'number_type'	=> array(
						'title'			=> esc_html__( 'bKash Account Type', "stb" ),
						'type'			=> 'select',
						'class'       	=> 'wc-enhanced-select',
						'description' 	=> esc_html__( 'Select bKash account type', "stb" ),
						'options'	=> array(
							'Agent'		=> esc_html__( 'Agent', "stb" ),
							'Personal'	=> esc_html__( 'Personal', "stb" )
						),
						'desc_tip'      => true
					),
					'bKash_charge' 	=>	array(
						'title'			=> esc_html__( 'Enable bKash Charge', "stb" ),
						'type' 			=> 'checkbox',
						'label'			=> esc_html__( 'Add 2% bKash "Payment" charge to net price', "stb" ),
						'description' 	=> esc_html__( 'If a product price is 1000 then customer have to pay ( 1000 + 20 ) = 1020. Here 20 is bKash charge', "stb" ),
						'default'		=> 'no',
						'desc_tip'    	=> true
					),						
	                'instructions' => array(
	                    'title'       	=> esc_html__( 'Thank You Page Message', "stb" ),
	                    'type'        	=> 'title',
	                    'type'        	=> 'textarea',
	                    'description' 	=> esc_html__( 'Instructions that will be added to the thank you page and emails.', "stb" ),
	                    'default'     	=> esc_html__( 'Thank you for your purchase! We will review it and update you as soon as possible.', "stb" ),
	                    'desc_tip'    	=> true
	                ),
					'bKash_qr_code' => array(
						'title'       => esc_html__('bKash QR Code', "stb"),
						'type'        => 'text',
						'description' => esc_html__('Upload QR code image for bKash payment', "stb"),
						'desc_tip'    => true,
						'custom_attributes' => array(
							'readonly' => 'readonly',
						),
						'default'     => '',
					),
				);
			}

public function payment_fields(){
    global $woocommerce;
    $bKash_charge = ($this->bKash_charge == 'yes') ? esc_html__(' Note: 2% bKash "Send Money" cost will be added with the net price.', "stb" ) . '<br><br><strong>' . esc_html__('Total Amount:', "stb") . '</strong> ' . get_woocommerce_currency_symbol() . $woocommerce->cart->total : '';
    echo wpautop( wptexturize( esc_html__( $this->description, "stb" ) ) . $bKash_charge  );
    ?>
    <div style="margin-bottom: 10px;">
        <span><strong>bKash Number <?php echo ($this->number_type == 'Agent') ? '(Cash Out)' : '(Send Money)'; ?>:</strong> <span id="bkash-number-display" style="font-size: 16px; font-weight: normal;"><?php echo $this->bKash_number; ?></span></span>
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
            setTimeout(function() {
                button.innerText = 'Copy';
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
    
    <div style="display: flex; margin-top: 15px; flex-direction: column;">
        <label for="bKash_number" style="margin: 0; font-weight: bold;"><?php esc_html_e( 'Your bKash Number (used for payment):', "stb" );?></label>
        <input type="text" name="bKash_number" id="bKash_number" placeholder="017XXXXXXXX" style="margin-top: 5px;">
    </div>
    <?php 
}
			

			public function process_payment( $order_id ) {
				global $woocommerce;
				$order = new WC_Order( $order_id );
				
				$status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
				// Mark as on-hold (we're awaiting the bKash)
				$order->update_status( $status, esc_html__( 'Checkout with bKash payment. ', "stb" ) );

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				$woocommerce->cart->empty_cart();

				// Return thankyou redirect
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}	


	        public function pay_bKash_thankyou_page() {
			    $order_id = get_query_var('order-received');
			    $order = new WC_Order( $order_id );
			    if( $order->payment_method == $this->id ){
		            $thankyou = $this->instructions;
		            return $thankyou;		        
			    } else {
			    	return esc_html__( 'Thank you. Your order has been received.', "stb" );
			    }

	        }

	        public function pay_bKash_number_instructions( $order, $sent_to_admin, $plain_text = false ) {
			    if( $order->payment_method != $this->id )
			        return;        	
	            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method ) {
	                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
	            }
	        }

		}

	}

	/**
	 * Add settings page link in plugins
	 */
	add_filter( "plugin_action_links_". plugin_basename(__FILE__), 'pay_bKash_settings_link' );
	function pay_bKash_settings_link( $links ) {
		
		$settings_links = array();
		$settings_links[] ='<a href="https://www.facebook.com/Labartise" target="_blank">' . esc_html__( 'Follow us', 'stb' ) . '</a>';
		$settings_links[] ='<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pay_bKash' ) . '">' . esc_html__( 'Settings', 'stb' ) . '</a>';
        
        // add the links to the list of links already there
		foreach($settings_links as $link) {
			array_unshift($links, $link);
		}

		return $links;
	}	

	/**
	 * If bKash charge is activated
	 */
	$bKash_charge = get_option( 'woocommerce_pay_bKash_settings' );
	if( $bKash_charge['bKash_charge'] == 'yes' ){ 

		add_action( 'wp_enqueue_scripts', 'pay_bKash_script' );
		function pay_bKash_script(){
			wp_enqueue_script( 'stb-script', plugins_url( 'js/scripts.js', __FILE__ ), array('jquery'), '1.0', true );
		}

		add_action( 'woocommerce_cart_calculate_fees', 'pay_bKash_charge' );
		function pay_bKash_charge(){

		    global $woocommerce;
		    $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		    $current_gateway = '';

		    if ( !empty( $available_gateways ) ) {
		        if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
		            $current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
		        } 
		    }
		    
		    if( $current_gateway!='' ){

		        $current_gateway_id = $current_gateway->id;

				if ( is_admin() && ! defined( 'DOING_AJAX' ) )
					return;

				if ( $current_gateway_id =='pay_bKash' ) {
					$percentage = 0.02;
					$surcharge = ( $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total ) * $percentage;	
					$woocommerce->cart->add_fee( esc_html__('bKash Charge', 'stb'), $surcharge, true, '' ); 
				}
		       
		    }    	
		    
		}
		
	}
	
	/**
	 * Empty field validation
	 */
	add_action( 'woocommerce_checkout_process', 'pay_bKash_payment_process' );
	function pay_bKash_payment_process(){

	    if($_POST['payment_method'] != 'pay_bKash')
	        return;

	    $bKash_number = sanitize_text_field( $_POST['bKash_number'] );

	    $match_number = isset($bKash_number) ? $bKash_number : '';

		$validate_number = preg_match( '/^01[5-9]\d{8}$/', $match_number );

	    if( !isset($bKash_number) || empty($bKash_number) )
	        wc_add_notice( esc_html__( 'Please add bKash Number', 'stb'), 'error' );

		if( !empty($bKash_number) && $validate_number == false )
	        wc_add_notice( esc_html__( 'Incorrect mobile number.', 'stb'), 'error' );
	}

	/**
	 * Update bKash field to database
	 */
	add_action( 'woocommerce_checkout_update_order_meta', 'pay_bKash_additional_fields_update' );
	function pay_bKash_additional_fields_update( $order_id ){

	    if($_POST['payment_method'] != 'pay_bKash' )
	        return;

	    $bKash_number = sanitize_text_field( $_POST['bKash_number'] );

		$number = isset($bKash_number) ? $bKash_number : '';

		update_post_meta($order_id, '_bKash_number', $number);

	}

	/**
	 * Admin order page bKash data output
	 */
	add_action('woocommerce_admin_order_data_after_billing_address', 'pay_bKash_admin_order_data' );
	function pay_bKash_admin_order_data( $order ){
	    
	    if( $order->payment_method != 'pay_bKash' )
	        return;

		$number = (get_post_meta($order->id, '_bKash_number', true)) ? get_post_meta($order->id, '_bKash_number', true) : '';

		?>
		<div class="form-field form-field-wide">
			<img src='<?php echo plugins_url("images/bkash.png", __FILE__); ?>' alt="bKash" style="max-width: 100px; height: auto;">	
			<table class="wp-list-table widefat fixed striped posts">
				<tbody>
					<tr>
						<th><strong><?php esc_html_e('Customer bKash Account Number', 'stb') ;?></strong></th>
						<td style="font-size: 16px; font-weight: bold;">: <?php echo esc_attr( $number );?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php 
		
	}

	/**
	 * Order review page bKash data output
	 */
	add_action('woocommerce_order_details_after_customer_details', 'pay_bKash_additional_info_order_review_fields' );
	function pay_bKash_additional_info_order_review_fields( $order ){
	    
	    if( $order->payment_method != 'pay_bKash' )
	        return;

		$number = (get_post_meta($order->id, '_bKash_number', true)) ? get_post_meta($order->id, '_bKash_number', true) : '';

		?>
			<tr>
				<th><?php esc_html_e('Your bKash Account Number:', 'stb');?></th>
				<td><?php echo esc_attr( $number );?></td>
			</tr>
		<?php 
		
	}	

	/**
	 * Register new admin column
	 */
	add_filter( 'manage_edit-shop_order_columns', 'pay_bKash_admin_new_column' );
	function pay_bKash_admin_new_column($columns){

	    $new_columns = (is_array($columns)) ? $columns : array();
	    unset( $new_columns['order_actions'] );
	    $new_columns['mobile_no'] 	= esc_html__('Send From', 'stb');

	    $new_columns['order_actions'] = $columns['order_actions'];
	    return $new_columns;

	}

	/**
	 * Load data in new column
	 */
	add_action( 'manage_shop_order_posts_custom_column', 'pay_bKash_admin_column_value', 2 );
	function pay_bKash_admin_column_value($column){

	    global $post;

	    $mobile_no = (get_post_meta($post->ID, '_bKash_number', true)) ? get_post_meta($post->ID, '_bKash_number', true) : '';

	    if ( $column == 'mobile_no' ) {    
	        echo esc_attr( $mobile_no );
	    }
	}

	/**
	 * Add custom CSS for checkout page
	 */
	add_action( 'wp_head', 'pay_bKash_custom_checkout_css' );
	function pay_bKash_custom_checkout_css() {
		if ( is_checkout() ) {
			?>
			<style>
				li.wc_payment_method.payment_method_pay_bKash{
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
	}

} else {
	/**
	 * Admin Notice
	 */
	add_action( 'admin_notices', 'pay_bKash_admin_notice__error' );
	function pay_bKash_admin_notice__error() {
	    ?>
	    <div class="notice notice-error">
	        <p><a href="http://wordpress.org/extend/plugins/woocommerce/"><?php esc_html_e( 'Woocommerce', 'stb' ); ?></a> <?php esc_html_e( 'plugin need to active if you wanna use bKash plugin.', 'stb' ); ?></p>
	    </div>
	    <?php
	}
	
	/**
	 * Deactivate Plugin
	 */
	add_action( 'admin_init', 'pay_bKash_deactivate' );
	function pay_bKash_deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		unset( $_GET['activate'] );
	}
}

// Enqueue media scripts early
add_action('admin_enqueue_scripts', 'pay_bKash_enqueue_media_scripts');
function pay_bKash_enqueue_media_scripts($hook) {
    // Only load on WooCommerce settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings') {
        return;
    }
    
    // Check if we're on the checkout tab
    if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
        return;
    }
    
    // Enqueue media scripts
    wp_enqueue_media();
}

// Add the upload script
add_action('admin_footer', 'pay_bKash_qr_upload_script');
function pay_bKash_qr_upload_script() {
    // Only load on WooCommerce settings page for bKash
    if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings' || 
        !isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('bKash QR script loaded');
        
        // Wait for the page to fully load
        setTimeout(function() {
            // Add buttons after the QR code field
            var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
            
            if (qrField.length && !$('#upload_qr_code_button').length) {
                var buttonHtml = '<div style="margin-top: 10px;">';
                buttonHtml += '<button type="button" class="button button-secondary" id="upload_qr_code_button">Upload QR Code</button> ';
                buttonHtml += '<button type="button" class="button button-secondary" id="remove_qr_code_button">Remove</button>';
                buttonHtml += '</div>';
                
                qrField.closest('td').append(buttonHtml);
                console.log('Buttons added');
            }
            
            // Upload QR code functionality
            $(document).on('click', '#upload_qr_code_button', function(e) {
                e.preventDefault();
                console.log('Upload button clicked');
                
                // Check if wp.media is available
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    alert('WordPress media library is not loaded properly. Please refresh the page and try again.');
                    return;
                }
                
                var frame = wp.media({
                    title: 'Select QR Code Image',
                    button: {
                        text: 'Use This Image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    console.log('Image selected:', attachment.url);
                    var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
                    qrField.val(attachment.url);
                    // Trigger change event to enable save button
                    qrField.trigger('change');
                });
                
                frame.open();
            });
            
            // Remove QR code functionality
            $(document).on('click', '#remove_qr_code_button', function(e) {
                e.preventDefault();
                var qrField = $('#woocommerce_pay_bKash_bKash_qr_code');
                qrField.val('');
                // Trigger change event to enable save button
                qrField.trigger('change');
                console.log('QR code removed');
            });
            
        }, 1000); // Wait 1 second for everything to load
    });
    </script>
	<?php
}
// Add a custom admin menu item for bKash settings
add_action('admin_menu', 'pay_bKash_admin_menu');
function pay_bKash_admin_menu() {
    $icon_url = plugins_url('images/bkash.png', __FILE__);

    add_menu_page(
        'bKash Settings',  
        'bKash EasyPay',                                     
        'manage_options',   
        'pay_bKash_settings_menu',   
        function() { 
            // Redirect to WooCommerce bKash settings
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=pay_bKash'));
            exit;
        },
        $icon_url,  
        55          
    );
}

// CSS to resize menu icon
add_action('admin_head', function() {
    ?>
    <style>
        #toplevel_page_pay_bKash_settings_menu .wp-menu-image img {
            width: 20px !important;
            height: 20px !important;
        }
    </style>
    <?php
});
