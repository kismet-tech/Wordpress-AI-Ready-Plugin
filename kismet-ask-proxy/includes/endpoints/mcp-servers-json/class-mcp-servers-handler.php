<?php
/**
 * Handles .well-known/mcp/servers.json functionality for MCP (Model Context Protocol) discovery
 * - URL rewrite rules for /.well-known/mcp/servers.json
 * - Lists trusted MCP servers for the hotel
 * - Follows RFC 8615 well-known URI specification
 * - Settings integration for server configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_MCP_Servers_Handler {
    
    public function __construct() {
        // Set up URL routing and request handling
        add_action('init', array($this, 'add_mcp_servers_rewrite'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Direct request interception - catches requests before WordPress routing
        add_action('parse_request', array($this, 'intercept_mcp_servers_request'));
        
        // Fallback handler for rewrite rule approach
        add_action('template_redirect', array($this, 'handle_mcp_servers_request'));
        
        // Admin settings integration
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    /**
     * Add rewrite rule for .well-known/mcp/servers.json
     */
    public function add_mcp_servers_rewrite() {
        error_log('KISMET DEBUG: add_mcp_servers_rewrite() called');
        
        // Add rewrite rule with optional trailing slash
        add_rewrite_rule('\.well-known/mcp/servers\.json/?$', 'index.php?kismet_mcp_servers=1', 'top');
        
        error_log('KISMET DEBUG: Rewrite rule added for \.well-known/mcp/servers\.json/?$');
    }
    
    /**
     * Add query variable for MCP servers endpoint
     */
    public function add_query_vars($vars) {
        $vars[] = 'kismet_mcp_servers';
        return $vars;
    }
    
    /**
     * Direct request interception - catches /.well-known/mcp/servers.json before WordPress routing
     */
    public function intercept_mcp_servers_request($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        error_log("KISMET DEBUG: intercept_mcp_servers_request called for URI: $request_uri");
        
        // Check if this is a request for our mcp/servers.json
        if (preg_match('#^/\.well-known/mcp/servers\.json/?(\?.*)?$#', $request_uri)) {
            error_log("KISMET DEBUG: Intercepted .well-known/mcp/servers.json request directly");
            
            $this->serve_mcp_servers_json();
            exit;
        }
    }
    
    /**
     * Handle MCP servers request via template redirect (fallback method)
     */
    public function handle_mcp_servers_request() {
        $query_var = get_query_var('kismet_mcp_servers');
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        error_log("KISMET DEBUG: handle_mcp_servers_request called for URI: $request_uri");
        error_log("KISMET DEBUG: kismet_mcp_servers query var = " . ($query_var ? 'TRUE' : 'FALSE'));
        
        if ($query_var) {
            error_log("KISMET DEBUG: Serving mcp/servers.json");
            
            $this->serve_mcp_servers_json();
            exit;
        }
    }
    
    /**
     * Serve the MCP servers.json content
     */
    private function serve_mcp_servers_json() {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        
        // Get configured MCP servers from WordPress options
        $custom_servers = get_option('kismet_mcp_custom_servers', array());
        
        // Build default Kismet MCP server configuration
        $default_servers = array(
            array(
                'name' => 'Kismet Hotel Assistant',
                'description' => 'Hotel information and booking assistance for ' . $site_name,
                'url' => $site_url . '/ask',
                'type' => 'hotel_assistant',
                'version' => '1.0',
                'capabilities' => array(
                    'room_availability',
                    'pricing_information', 
                    'amenities_information',
                    'booking_assistance',
                    'general_inquiries'
                ),
                'authentication' => array(
                    'type' => 'none'
                ),
                'contact' => array(
                    'email' => get_option('admin_email'),
                    'website' => $site_url
                ),
                'trusted' => true,
                'last_verified' => current_time('c') // ISO 8601 format
            )
        );
        
        // Merge custom servers with defaults
        $all_servers = array_merge($default_servers, $custom_servers);
        
        // Build the MCP servers.json structure following RFC 8615
        $mcp_servers = array(
            'schema_version' => '1.0',
            'last_updated' => current_time('c'),
            'publisher' => array(
                'name' => $site_name,
                'url' => $site_url,
                'contact_email' => get_option('admin_email')
            ),
            'servers' => $all_servers,
            'metadata' => array(
                'total_servers' => count($all_servers),
                'generated_by' => 'Kismet WordPress Plugin',
                'specification' => 'RFC 8615 Well-Known URIs',
                'purpose' => 'MCP server discovery for hotel services'
            )
        );
        
        // Set proper headers and serve JSON
        status_header(200);
        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests for discovery
        
        echo json_encode($mcp_servers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        error_log('KISMET DEBUG: Served MCP servers.json with ' . count($all_servers) . ' servers');
    }
    
    /**
     * Flush rewrite rules for MCP servers endpoint
     */
    public function flush_rewrite_rules() {
        $this->add_mcp_servers_rewrite();
        flush_rewrite_rules();
    }
    
    /**
     * Initialize admin settings for MCP servers configuration
     */
    public function settings_init() {
        // Add settings section to existing Kismet settings page
        add_settings_section(
            'kismet_mcp_servers_section',
            'MCP Servers Configuration',
            array($this, 'mcp_servers_section_callback'),
            'kismet_ai_plugin_settings'
        );
        
        // Add custom servers field
        add_settings_field(
            'kismet_mcp_custom_servers',
            'Additional MCP Servers (JSON)',
            array($this, 'custom_servers_render'),
            'kismet_ai_plugin_settings',
            'kismet_mcp_servers_section'
        );
        
        // Register the setting
        register_setting(
            'kismet_ai_plugin_settings_group',
            'kismet_mcp_custom_servers',
            array(
                'sanitize_callback' => array($this, 'sanitize_custom_servers')
            )
        );
    }
    
    /**
     * Section callback for MCP servers settings
     */
    public function mcp_servers_section_callback() {
        echo '<p>Configure additional MCP (Model Context Protocol) servers to be published in your .well-known/mcp/servers.json file. The default Kismet hotel assistant server is always included.</p>';
        echo '<p><strong>Current MCP servers endpoint:</strong> <a href="' . esc_url(get_site_url() . '/.well-known/mcp/servers.json') . '" target="_blank">' . esc_html(get_site_url() . '/.well-known/mcp/servers.json') . '</a></p>';
    }
    
    /**
     * Render custom servers input field
     */
    public function custom_servers_render() {
        $custom_servers = get_option('kismet_mcp_custom_servers', array());
        $json_value = !empty($custom_servers) ? json_encode($custom_servers, JSON_PRETTY_PRINT) : '';
        
        echo '<textarea name="kismet_mcp_custom_servers" rows="10" cols="80" class="large-text code">' . esc_textarea($json_value) . '</textarea>';
        echo '<p class="description">Enter additional MCP servers as a JSON array. Example:</p>';
        echo '<pre><code>[
  {
    "name": "Custom Hotel Service",
    "description": "Additional hotel services",
    "url": "https://api.example.com/mcp",
    "type": "custom_service",
    "version": "1.0",
    "capabilities": ["custom_feature"],
    "authentication": {"type": "none"},
    "trusted": true
  }
]</code></pre>';
    }
    
    /**
     * Sanitize and validate custom servers JSON input
     */
    public function sanitize_custom_servers($input) {
        if (empty($input)) {
            return array();
        }
        
        // Attempt to decode JSON
        $decoded = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error(
                'kismet_mcp_custom_servers',
                'invalid_json',
                'Invalid JSON format for custom MCP servers: ' . json_last_error_msg()
            );
            return get_option('kismet_mcp_custom_servers', array());
        }
        
        // Validate that it's an array
        if (!is_array($decoded)) {
            add_settings_error(
                'kismet_mcp_custom_servers',
                'not_array',
                'Custom MCP servers must be a JSON array'
            );
            return get_option('kismet_mcp_custom_servers', array());
        }
        
        // Validate each server entry
        $validated_servers = array();
        foreach ($decoded as $index => $server) {
            if (!is_array($server)) {
                add_settings_error(
                    'kismet_mcp_custom_servers',
                    'invalid_server_' . $index,
                    'Server entry ' . ($index + 1) . ' must be an object'
                );
                continue;
            }
            
            // Required fields validation
            if (empty($server['name']) || empty($server['url'])) {
                add_settings_error(
                    'kismet_mcp_custom_servers',
                    'missing_required_' . $index,
                    'Server entry ' . ($index + 1) . ' is missing required "name" or "url" field'
                );
                continue;
            }
            
            // Sanitize URL
            $server['url'] = esc_url_raw($server['url']);
            if (empty($server['url'])) {
                add_settings_error(
                    'kismet_mcp_custom_servers',
                    'invalid_url_' . $index,
                    'Server entry ' . ($index + 1) . ' has an invalid URL'
                );
                continue;
            }
            
            // Sanitize other fields
            $server['name'] = sanitize_text_field($server['name']);
            $server['description'] = isset($server['description']) ? sanitize_textarea_field($server['description']) : '';
            $server['type'] = isset($server['type']) ? sanitize_text_field($server['type']) : 'custom';
            $server['version'] = isset($server['version']) ? sanitize_text_field($server['version']) : '1.0';
            
            $validated_servers[] = $server;
        }
        
        return $validated_servers;
    }
    
    /**
     * Get the current MCP servers configuration for display
     */
    public function get_servers_summary() {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        $custom_servers = get_option('kismet_mcp_custom_servers', array());
        
        $summary = array(
            'endpoint_url' => $site_url . '/.well-known/mcp/servers.json',
            'default_server_count' => 1, // Kismet hotel assistant
            'custom_server_count' => count($custom_servers),
            'total_servers' => 1 + count($custom_servers),
            'last_updated' => current_time('c')
        );
        
        return $summary;
    }
} 