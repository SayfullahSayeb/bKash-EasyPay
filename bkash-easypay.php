<?php
/**
 * Main Plugin File: bkash-easypay.php
 * 
 * Plugin Name: bKash EasyPay
 * Plugin URI:  https://labartise.com
 * Description: bKash EasyPay is a manual payment gateway for WooCommerce that allows customers to pay via bKash.
 * Version:     1.0.0
 * Author:      Labartise
 * Author URI:  https://labartise.com
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 5.6
 * GitHub Plugin URI: https://github.com/SayfullahSayeb/bKash-EasyPay-WordPress-Plugin
 */

// Prevent direct access
defined('ABSPATH') or die('Only a foolish person try to access directly to see this white page. :-) ');

// Define plugin constants
define('BKASH_EASYPAY_VERSION', '1.0.0');
define('BKASH_EASYPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BKASH_EASYPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BKASH_EASYPAY_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class BKash_EasyPay_Plugin {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            add_action('admin_init', array($this, 'deactivate_plugin'));
            return;
        }
        
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once BKASH_EASYPAY_PLUGIN_DIR . 'includes/class-bkash-gateway.php';
        require_once BKASH_EASYPAY_PLUGIN_DIR . 'includes/class-bkash-admin.php';
        require_once BKASH_EASYPAY_PLUGIN_DIR . 'includes/class-bkash-transactions.php';
        require_once BKASH_EASYPAY_PLUGIN_DIR . 'includes/class-bkash-hooks.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register payment gateway
        add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));
        
        // Initialize gateway on plugins loaded
        add_action('plugins_loaded', array($this, 'init_gateway'));
        
        // Add settings link
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Initialize other components
        add_action('init', array($this, 'init_components'));
    }
    
    /**
     * Register the bKash payment gateway
     */
    public function register_gateway($gateways) {
        $gateways[] = 'BKash_Payment_Gateway';
        return $gateways;
    }
    
    /**
     * Initialize the payment gateway
     */
    public function init_gateway() {
        if (class_exists('BKash_Payment_Gateway')) {
            new BKash_Payment_Gateway();
        }
    }
    
    /**
     * Initialize other components
     */
    public function init_components() {
        new BKash_Admin();
        new BKash_Transactions();
        new BKash_Hooks();
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_links = array();
        $settings_links[] = '<a href="https://www.facebook.com/Labartise" target="_blank">' . esc_html__('Follow us', 'stb') . '</a>';
        $settings_links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=pay_bKash') . '">' . esc_html__('Settings', 'stb') . '</a>';
        
        foreach($settings_links as $link) {
            array_unshift($links, $link);
        }
        
        return $links;
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <a href="http://wordpress.org/extend/plugins/woocommerce/"><?php esc_html_e('WooCommerce', 'stb'); ?></a> 
                <?php esc_html_e('plugin needs to be active if you want to use bKash plugin.', 'stb'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Deactivate plugin if WooCommerce is not active
     */
    public function deactivate_plugin() {
        if (!$this->is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            unset($_GET['activate']);
        }
    }
}

// Initialize the plugin
BKash_EasyPay_Plugin::get_instance();