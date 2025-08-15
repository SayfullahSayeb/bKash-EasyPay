<?php
/**
 * bKash Transactions Class
 */
class BKash_Transactions {
    
    public function __construct() {
        add_action('admin_init', array($this, 'handle_csv_export'));
    }
    
    /**
     * Display transactions page
     */
    public function display_page() {
        // Handle search and pagination
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get data
        $data = $this->get_transactions_data($search, $per_page, $offset);
        
        $this->render_page($data, $search, $current_page);
    }
    
    /**
     * Get transactions data
     */
    private function get_transactions_data($search = '', $per_page = 20, $offset = 0) {
        // Get total count for all bKash transactions
        $total_all_orders = count(wc_get_orders(array(
            'limit' => -1,
            'payment_method' => 'pay_bKash',
            'return' => 'ids'
        )));
        
        // Handle search
        $search_order_ids = array();
        $search_results_count = 0;
        
        if (!empty($search)) {
            $search_order_ids = $this->search_transactions($search);
            $search_results_count = count($search_order_ids);
        }
        
        // Get orders
        $args = array(
            'payment_method' => 'pay_bKash',
            'return' => 'objects',
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        if (!empty($search)) {
            if (!empty($search_order_ids)) {
                $args['post__in'] = $search_order_ids;
                $args['limit'] = $per_page;
                $args['offset'] = $offset;
                $total_orders = count($search_order_ids);
            } else {
                $orders = array();
                $total_orders = 0;
            }
        } else {
            $args['limit'] = $per_page;
            $args['offset'] = $offset;
            $total_orders = $total_all_orders;
        }
        
        if (!isset($orders)) {
            $orders = wc_get_orders($args);
        }
        
        return array(
            'orders' => $orders,
            'total_orders' => $total_orders,
            'total_all_orders' => $total_all_orders,
            'search_results_count' => $search_results_count,
            'total_pages' => ceil($total_orders / $per_page)
        );
    }
    
    /**
     * Search transactions
     */
    private function search_transactions($search) {
        $search_order_ids = array();
        $all_bkash_orders = wc_get_orders(array(
            'limit' => -1,
            'payment_method' => 'pay_bKash',
            'return' => 'ids'
        ));
        
        foreach ($all_bkash_orders as $order_id) {
            // Check order ID match
            if (is_numeric($search) && $order_id == intval($search)) {
                $search_order_ids[] = $order_id;
                continue;
            }
            
            // Check bKash number match
            $bkash_number = $this->get_bkash_number($order_id);
            if (!empty($bkash_number) && stripos($bkash_number, $search) !== false) {
                $search_order_ids[] = $order_id;
            }
        }
        
        return array_unique($search_order_ids);
    }
    
    /**
     * Get bKash number for an order
     */
    private function get_bkash_number($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return '';
        
        // Try multiple methods
        $bkash_number = $order->get_meta('_bKash_number', true);
        if (empty($bkash_number)) {
            $bkash_number = $order->get_meta('bKash_number', true);
        }
        if (empty($bkash_number)) {
            $bkash_number = get_post_meta($order_id, '_bKash_number', true);
        }
        if (empty($bkash_number)) {
            $bkash_number = get_post_meta($order_id, 'bKash_number', true);
        }
        
        return $bkash_number;
    }
    
    /**
     * Render transactions page
     */
    private function render_page($data, $search, $current_page) {
        ?>
        <div class="wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin: 0;"><?php esc_html_e('bKash Transaction Information', 'stb'); ?></h1>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pay_bKash_transactions&action=export_csv'), 'export_bkash_csv'); ?>" 
                   class="button button-secondary">
                    <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e('Export to CSV', 'stb'); ?>
                </a>
            </div>
            
            <?php $this->render_search_form($data, $search); ?>
            <?php $this->render_transactions_table($data['orders']); ?>
            <?php $this->render_pagination($data['total_pages'], $current_page, $search); ?>
        </div>
        <?php
    }
    
    /**
     * Render search form
     */
    private function render_search_form($data, $search) {
        ?>
        <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong><?php esc_html_e('Total Transactions:', 'stb'); ?></strong> <?php echo $data['total_all_orders']; ?>
                    <?php if (!empty($search)): ?>
                        | <strong><?php esc_html_e('Search Results:', 'stb'); ?></strong> <?php echo $data['search_results_count']; ?>
                    <?php endif; ?>
                </div>
                
                <form method="get" action="" style="display: flex; align-items: center; gap: 10px;">
                    <input type="hidden" name="page" value="pay_bKash_transactions">
                    <label for="search"><strong><?php esc_html_e('Search:', 'stb'); ?></strong></label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="<?php esc_attr_e('Order ID or bKash Number', 'stb'); ?>"
                           style="width: 200px;">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Search', 'stb'); ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?php echo admin_url('admin.php?page=pay_bKash_transactions'); ?>" class="button">
                            <?php esc_html_e('Clear', 'stb'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render transactions table
     */
    private function render_transactions_table($orders) {
        ?>
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
                            <?php if (!$order) continue; ?>
                            <?php $this->render_order_row($order); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <p style="font-size: 16px; color: #666;">
                                    <?php esc_html_e('No bKash transactions found.', 'stb'); ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .order-status {
                display: inline-block;
                min-width: 80px;
                text-align: center;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: bold;
                color: white;
            }
        </style>
        <?php
    }
    
    /**
     * Render single order row
     */
    private function render_order_row($order) {
        $order_id = $order->get_id();
        $bkash_number = $this->get_bkash_number($order_id);
        $order_total = $order->get_total();
        $order_date = $order->get_date_created();
        $order_status = $order->get_status();
        $status_name = wc_get_order_status_name($order_status);
        ?>
        <tr>
            <td><strong>#<?php echo $order_id; ?></strong></td>
            <td>
                <img src="<?php echo plugins_url('images/bkash.png', BKASH_EASYPAY_PLUGIN_FILE); ?>" 
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
                <span class="order-status" style="background-color: <?php echo $this->get_status_color($order_status); ?>;">
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
        <?php
    }
    
    /**
     * Render pagination
     */
    private function render_pagination($total_pages, $current_page, $search) {
        if ($total_pages <= 1) return;
        
        $base_url = admin_url('admin.php?page=pay_bKash_transactions');
        if (!empty($search)) {
            $base_url .= '&search=' . urlencode($search);
        }
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => $base_url . '%_%',
                    'format' => '&paged=%#%',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo; ' . esc_html__('Previous', 'stb'),
                    'next_text' => esc_html__('Next', 'stb') . ' &raquo;'
                ));
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get status color
     */
    private function get_status_color($status) {
        $colors = array(
            'pending' => '#f56e28',
            'processing' => '#c8d7e1',
            'on-hold' => '#f8dda7',
            'completed' => '#c8d7e1',
            'cancelled' => '#e5e5e5',
            'refunded' => '#e5e5e5',
            'failed' => '#d63638'
        );
        
        return isset($colors[$status]) ? $colors[$status] : '#666';
    }
    
    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'pay_bKash_transactions' || 
            !isset($_GET['action']) || $_GET['action'] !== 'export_csv') {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_bkash_csv') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed.', 'stb'));
        }
        
        $this->export_to_csv();
    }
    
    /**
     * Export transactions to CSV
     */
    private function export_to_csv() {
        $order_ids = wc_get_orders(array(
            'limit' => -1,
            'payment_method' => 'pay_bKash',
            'return' => 'ids'
        ));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bkash-transactions-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, array(
            'Order ID',
            'Payment Method',
            'bKash Number',
            'Amount',
            'Date',
            'Order Status'
        ));
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $bkash_number = $this->get_bkash_number($order_id);
            
            fputcsv($output, array(
                $order_id,
                'bKash',
                $bkash_number,
                $order->get_total(),
                $order->get_date_created()->date('Y-m-d H:i:s'),
                wc_get_order_status_name($order->get_status())
            ));
        }
        
        fclose($output);
        exit;
    }
}