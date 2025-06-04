<?php
/**
 * Plugin Name: Kismet Ask Proxy
 * Description: Creates an AI-ready /ask page that serves both API requests and human visitors with Kismet branding.
 * Version: 1.0
 * Author: Kismet
 * License: GPL2+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KISMET_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KISMET_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include our modular handler classes
require_once KISMET_PLUGIN_PATH . 'includes/class-robots-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/class-ai-plugin-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/class-ask-handler.php';

/**
 * Main plugin class - coordinates all handlers
 */
class Kismet_Ask_Proxy_Plugin {
    
    private $robots_handler;
    private $ai_plugin_handler;
    private $ask_handler;
    
    public function __construct() {
        // Initialize all our handler classes
        $this->robots_handler = new Kismet_Robots_Handler();
        $this->ai_plugin_handler = new Kismet_AI_Plugin_Handler();
        $this->ask_handler = new Kismet_Ask_Handler();
    
        // Add a "Settings" link to the plugin row
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Add "Settings" link to plugin actions in the plugins list
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=kismet-ai-plugin-settings">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
new Kismet_Ask_Proxy_Plugin();

// === ACTIVATION & DEACTIVATION HOOKS ===

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Activated');
        
    // Flush rewrite rules for ai-plugin.json
    $ai_plugin_handler = new Kismet_AI_Plugin_Handler();
    $ai_plugin_handler->flush_rewrite_rules();
    
    // Send registration notification to Kismet backend
    kismet_register_plugin_activation();
});

/**
 * Plugin deactivation hook  
 */
register_deactivation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Deactivated');
    flush_rewrite_rules();
});

// === BACKEND REGISTRATION FUNCTIONALITY ===

/**
 * Send plugin activation notification to Kismet backend
 */
function kismet_register_plugin_activation() {
    $site_url = get_site_url();
    
    // Determine the correct API endpoint based on environment
    // Check if we're in a local development environment
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || 
                 strpos($host, '127.0.0.1') !== false || 
                 strpos($host, '.local') !== false);
    
    // Determine backend API endpoint based on WordPress site environment
    // If WordPress site is running locally (localhost/127.0.0.1/.local), 
    // send plugin activation notifications to local backend on port 4000
    // Otherwise, send to production API at api.makekismet.com
    $api_base = $is_local ? 'https://localhost:4000' : 'https://api.makekismet.com';
    $endpoint = $api_base . '/PluginInstallation/AddPluginInstallation';
    
    $data = array(
        'siteUrl' => $site_url
    );
    
    // Send the notification
    $args = array(
        'body' => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 15,
        'sslverify' => !$is_local // Disable SSL verification for local development
    );
    
    $response = wp_remote_post($endpoint, $args);
    
    if (is_wp_error($response)) {
        error_log('Kismet Plugin Installation Tracking Error: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 201 || $response_code === 200) {
            error_log('Kismet Plugin Installation: Successfully tracked');
        } else {
            error_log('Kismet Plugin Installation Tracking Error: HTTP ' . $response_code . ' - ' . $response_body);
        }
    }
}