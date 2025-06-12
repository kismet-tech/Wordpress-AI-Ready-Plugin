<?php
/**
 * Kismet Strategy Switcher
 * 
 * Handles endpoint strategy switching through WordPress admin actions
 * Uses proper plugin lifecycle instead of AJAX manipulation
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Strategy_Switcher {
    
    /**
     * Initialize the strategy switcher
     */
    public function __construct() {
        // Register admin action handlers (secure, nonce-protected)
        add_action('admin_post_kismet_switch_strategy', array($this, 'handle_strategy_switch'));
        add_action('admin_post_kismet_reset_strategies', array($this, 'handle_reset_strategies'));
    }
    
    /**
     * Handle strategy switch request through WordPress admin action
     */
    public function handle_strategy_switch() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        if (!check_admin_referer('kismet_switch_strategy', 'nonce')) {
            wp_die('Security check failed');
        }
        
        $endpoint_path = sanitize_text_field($_POST['endpoint_path']);
        $target_strategy = sanitize_text_field($_POST['target_strategy']);
        $redirect_url = sanitize_text_field($_POST['redirect_url']);
        
        // Validate inputs
        $valid_strategies = array('physical_file', 'wordpress_rewrite');
        if (!in_array($target_strategy, $valid_strategies)) {
            wp_die('Invalid strategy specified');
        }
        
        $valid_endpoints = array(
            '/.well-known/ai-plugin.json',
            '/.well-known/mcp/servers.json',
            '/llms.txt',
            '/ask',
            '/robots.txt'
        );
        if (!in_array($endpoint_path, $valid_endpoints)) {
            wp_die('Invalid endpoint specified');
        }
        
        try {
            // Store the strategy preference in database
            $this->set_strategy_preference($endpoint_path, $target_strategy);
            
            // Get current strategy to show in success message
            $current_strategy = $this->get_current_strategy($endpoint_path);
            $strategy_name = $this->format_strategy_name($target_strategy);
            $endpoint_name = $this->get_endpoint_display_name($endpoint_path);
            
            // Deactivate and reactivate plugin to implement the change
            $plugin_file = plugin_basename(dirname(__DIR__, 2) . '/kismet-ai-ready-plugin.php');
            
            if (!function_exists('deactivate_plugins')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            
            // Deactivate plugin (this cleans up old implementation)
            deactivate_plugins($plugin_file, false, false);
            
            // Reactivate plugin (this implements new strategy based on preferences)
            $activation_result = activate_plugin($plugin_file, '', false, false);
            
            if (is_wp_error($activation_result)) {
                throw new Exception('Plugin reactivation failed: ' . $activation_result->get_error_message());
            }
            
            // Add success message
            $message = "✅ Successfully switched $endpoint_name to $strategy_name strategy and reactivated plugin.";
            set_transient('kismet_strategy_switch_message', array(
                'type' => 'success',
                'message' => $message
            ), 30);
            
        } catch (Exception $e) {
            // Add error message
            set_transient('kismet_strategy_switch_message', array(
                'type' => 'error', 
                'message' => '❌ Strategy switch failed: ' . $e->getMessage()
            ), 30);
        }
        
        // Redirect back to settings page
        $redirect_url = $redirect_url ?: admin_url('options-general.php?page=kismet-ai-plugin-settings');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle reset all strategies to auto-detect
     */
    public function handle_reset_strategies() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        if (!check_admin_referer('kismet_reset_strategies', 'nonce')) {
            wp_die('Security check failed');
        }
        
        try {
            // Clear all strategy preferences (will trigger auto-detection on reactivation)
            $this->clear_all_strategy_preferences();
            
            // Deactivate and reactivate plugin to re-detect strategies
            $plugin_file = plugin_basename(dirname(__DIR__, 2) . '/kismet-ai-ready-plugin.php');
            
            if (!function_exists('deactivate_plugins')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            
            deactivate_plugins($plugin_file, false, false);
            $activation_result = activate_plugin($plugin_file, '', false, false);
            
            if (is_wp_error($activation_result)) {
                throw new Exception('Plugin reactivation failed: ' . $activation_result->get_error_message());
            }
            
            set_transient('kismet_strategy_switch_message', array(
                'type' => 'success',
                'message' => '🔄 Successfully reset all endpoint strategies and reactivated plugin.'
            ), 30);
            
        } catch (Exception $e) {
            set_transient('kismet_strategy_switch_message', array(
                'type' => 'error',
                'message' => '❌ Strategy reset failed: ' . $e->getMessage()
            ), 30);
        }
        
        // Redirect back to settings page
        $redirect_url = admin_url('options-general.php?page=kismet-ai-plugin-settings');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Set strategy preference for an endpoint
     */
    public function set_strategy_preference($endpoint_path, $strategy) {
        $option_key = $this->get_preference_option_key($endpoint_path);
        return update_option($option_key, $strategy);
    }
    
    /**
     * Get strategy preference for an endpoint
     */
    public function get_strategy_preference($endpoint_path) {
        $option_key = $this->get_preference_option_key($endpoint_path);
        return get_option($option_key, 'auto'); // Default to auto-detect
    }
    
    /**
     * Get current active strategy for an endpoint
     */
    public function get_current_strategy($endpoint_path) {
        require_once(plugin_dir_path(__FILE__) . '../shared/class-endpoint-manager.php');
        $endpoint_manager = Kismet_Endpoint_Manager::get_instance();
        $strategy_data = $endpoint_manager->get_endpoint_strategy($endpoint_path);
        return $strategy_data['current_strategy'] ?? 'unknown';
    }
    
    /**
     * Clear all strategy preferences
     */
    public function clear_all_strategy_preferences() {
        $endpoints = array(
            '/.well-known/ai-plugin.json',
            '/.well-known/mcp/servers.json',
            '/llms.txt',
            '/ask',
            '/robots.txt'
        );
        
        foreach ($endpoints as $endpoint) {
            $option_key = $this->get_preference_option_key($endpoint);
            delete_option($option_key);
        }
    }
    
    /**
     * Generate option key for storing strategy preferences
     */
    private function get_preference_option_key($endpoint_path) {
        $clean_path = str_replace(array('/', '.', '-'), '_', $endpoint_path);
        $clean_path = trim($clean_path, '_');
        return 'kismet_strategy_preference_' . $clean_path;
    }
    
    /**
     * Format strategy names for display
     */
    private function format_strategy_name($strategy) {
        switch ($strategy) {
            case 'wordpress_rewrite':
                return 'WordPress Rewrite';
            case 'physical_file':
                return 'Static File';
            default:
                return ucfirst(str_replace('_', ' ', $strategy));
        }
    }
    
    /**
     * Get display name for endpoints
     */
    private function get_endpoint_display_name($endpoint_path) {
        switch ($endpoint_path) {
            case '/.well-known/ai-plugin.json':
                return 'AI Plugin Discovery';
            case '/.well-known/mcp/servers.json':
                return 'MCP Servers';
            case '/llms.txt':
                return 'LLMS.txt Policy';
            case '/ask':
                return 'Ask Endpoint';
            case '/robots.txt':
                return 'Robots.txt Enhancement';
            default:
                return $endpoint_path;
        }
    }
    
    /**
     * Display admin messages after strategy switches
     */
    public function display_admin_messages() {
        $message_data = get_transient('kismet_strategy_switch_message');
        if ($message_data) {
            $css_class = ($message_data['type'] === 'success') ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $css_class . ' is-dismissible">';
            echo '<p>' . esc_html($message_data['message']) . '</p>';
            echo '</div>';
            
            // Clear the message
            delete_transient('kismet_strategy_switch_message');
        }
    }
    
    /**
     * Generate strategy switch URL for a specific endpoint
     */
    public function get_strategy_switch_url($endpoint_path, $target_strategy) {
        return wp_nonce_url(
            admin_url('admin-post.php'),
            'kismet_switch_strategy',
            'nonce'
        ) . '&action=kismet_switch_strategy&endpoint_path=' . urlencode($endpoint_path) . '&target_strategy=' . urlencode($target_strategy) . '&redirect_url=' . urlencode($_SERVER['REQUEST_URI']);
    }
    
    /**
     * Generate reset all strategies URL
     */
    public function get_reset_strategies_url() {
        return wp_nonce_url(
            admin_url('admin-post.php'),
            'kismet_reset_strategies',
            'nonce'
        ) . '&action=kismet_reset_strategies';
    }
} 