<?php
/**
 * MCP Servers Endpoint Strategy Manager
 * 
 * Manages serving strategies for /.well-known/mcp/servers.json based on server configuration
 * This endpoint is static JSON but may need occasional updates, so balance between static and dynamic.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_MCP_Servers_Strategies {
    
    private $plugin_instance;
    
    public function __construct($plugin_instance) {
        $this->plugin_instance = $plugin_instance;
    }
    
    /**
     * Get ordered strategies for /.well-known/mcp/servers.json endpoint
     * 
     * MCP servers endpoint is mostly static but may need updates when server config changes.
     * We slightly favor dynamic approaches compared to pure static ai-plugin.json.
     * 
     * @return array Ordered array of strategies to try
     */
    public function get_ordered_strategies() {
        // **Apache or LiteSpeed with .htaccess support**
        if (($this->plugin_instance->is_apache || $this->plugin_instance->is_litespeed) 
            && $this->plugin_instance->supports_htaccess) {
            
            return [
                'static_file_with_htaccess',  // BEST: Static file + .htaccess headers
                'wordpress_rewrite',          // GOOD: Dynamic updates possible
                'manual_static_file'          // FALLBACK: Basic static file
            ];
        }
        
        // **Apache or LiteSpeed without .htaccess support** 
        elseif ($this->plugin_instance->is_apache || $this->plugin_instance->is_litespeed) {
            return [
                'wordpress_rewrite',          // BEST: Allows dynamic updates
                'manual_static_file'          // FALLBACK: Static file
            ];
        }
        
        // **Nginx (great for static, but MCP might need dynamic updates)**
        elseif ($this->plugin_instance->is_nginx) {
            return [
                'wordpress_rewrite',                  // BEST: Allows server config updates
                'static_file_with_nginx_suggestion',  // GOOD: Static + nginx config
                'manual_static_file'                  // FALLBACK: Basic static
            ];
        }
        
        // **Microsoft IIS (WordPress rewrite more reliable)**
        elseif ($this->plugin_instance->is_iis) {
            return [
                'wordpress_rewrite',              // BEST: Most reliable on IIS
                'static_file_with_web_config',    // GOOD: Try static with IIS config
                'manual_static_file'              // FALLBACK: Basic static
            ];
        }
        
        // **Hybrid setups: Apache backend + Nginx frontend**
        elseif ($this->detect_hybrid_setup()) {
            return [
                'static_file_with_htaccess',      // BEST: Apache backend handles .htaccess
                'wordpress_rewrite',              // GOOD: Dynamic updates possible
                'static_file_with_nginx_suggestion', // BACKUP: Nginx frontend optimization
                'manual_static_file'              // FALLBACK: Basic static
            ];
        }
        
        // **Unknown server type**
        else {
            return [
                'wordpress_rewrite',    // SAFEST: Works everywhere
                'manual_static_file'    // FALLBACK: Basic approach
            ];
        }
    }
    
    /**
     * Get strategy-specific configuration for this endpoint
     * 
     * @param string $strategy The strategy being implemented
     * @return array Configuration options for the strategy
     */
    public function get_strategy_config($strategy) {
        switch ($strategy) {
            case 'static_file_with_htaccess':
                return [
                    'file_path' => ABSPATH . '.well-known/mcp/servers.json',
                    'directory_path' => ABSPATH . '.well-known/mcp/',
                    'htaccess_rules' => [
                        'Header always set Access-Control-Allow-Origin "*"',
                        'Header always set Access-Control-Allow-Methods "GET, OPTIONS"',
                        'Header always set Content-Type "application/json"',
                        'Header always set Cache-Control "public, max-age=3600"' // 1 hour cache
                    ],
                    'performance_priority' => 'high'
                ];
                
            case 'static_file_with_nginx_suggestion':
                return [
                    'file_path' => ABSPATH . '.well-known/mcp/servers.json',
                    'directory_path' => ABSPATH . '.well-known/mcp/',
                    'nginx_config_suggestion' => 'location /.well-known/mcp/servers.json { add_header Access-Control-Allow-Origin "*"; add_header Content-Type "application/json"; add_header Cache-Control "public, max-age=3600"; }',
                    'performance_priority' => 'high'
                ];
                
            case 'static_file_with_web_config':
                return [
                    'file_path' => ABSPATH . '.well-known/mcp/servers.json',
                    'directory_path' => ABSPATH . '.well-known/mcp/',
                    'web_config_rules' => [
                        '<httpHeaders>',
                        '  <add name="Access-Control-Allow-Origin" value="*" />',
                        '  <add name="Content-Type" value="application/json" />',
                        '  <add name="Cache-Control" value="public, max-age=3600" />',
                        '</httpHeaders>'
                    ],
                    'performance_priority' => 'medium'
                ];
                
            case 'wordpress_rewrite':
                return [
                    'rewrite_rule' => '^\.well-known/mcp/servers\.json$',
                    'query_vars' => ['kismet_mcp_servers' => '1'],
                    'performance_priority' => 'high', // Higher than typical WP rewrite because MCP is important
                    'allow_dynamic_updates' => true
                ];
                
            case 'manual_static_file':
                return [
                    'file_path' => ABSPATH . '.well-known/mcp/servers.json',
                    'directory_path' => ABSPATH . '.well-known/mcp/',
                    'requires_manual_cors' => true,
                    'performance_priority' => 'medium',
                    'note' => 'May need manual updates when server configuration changes'
                ];
                
            default:
                return [];
        }
    }
    
    /**
     * Get specific recommendations when strategies fail for this endpoint
     * 
     * @return array Array of specific recommendations
     */
    public function get_failure_recommendations() {
        $recommendations = [];
        
        if ($this->plugin_instance->is_nginx) {
            $recommendations[] = 'Add nginx configuration for MCP servers endpoint:';
            $recommendations[] = 'location /.well-known/mcp/ { add_header Access-Control-Allow-Origin "*"; add_header Content-Type "application/json"; }';
        } elseif ($this->plugin_instance->is_apache || $this->plugin_instance->is_litespeed) {
            $recommendations[] = 'Ensure .well-known/mcp/ directory has proper .htaccess support';
            $recommendations[] = 'Check mod_headers and mod_rewrite are enabled';
        } elseif ($this->plugin_instance->is_iis) {
            $recommendations[] = 'Configure IIS to handle .well-known/mcp/ directory';
            $recommendations[] = 'Ensure URL Rewrite module is installed';
        }
        
        $recommendations[] = 'MCP server discovery requires proper CORS and Content-Type headers';
        $recommendations[] = 'Test with: curl -v ' . site_url('/.well-known/mcp/servers.json');
        $recommendations[] = 'MCP endpoints may need periodic updates - consider WordPress rewrite for flexibility';
        
        return $recommendations;
    }
    
    /**
     * Check if this endpoint requires special handling
     * 
     * @return array Special requirements for this endpoint
     */
    public function get_special_requirements() {
        return [
            'cors_required' => true,
            'directory_structure' => true,  // Needs /.well-known/mcp/ directory
            'may_need_updates' => true,     // Server config might change
            'content_type_critical' => true // Must be application/json
        ];
    }
    
    /**
     * Get endpoint-specific performance requirements
     * 
     * @return array Performance requirements
     */
    public function get_performance_requirements() {
        return [
            'cache_friendly' => true,       // Can be cached but shorter than ai-plugin
            'cache_duration' => 3600,       // 1 hour (shorter than ai-plugin due to potential updates)
            'low_latency' => true,          // Important for MCP client discovery
            'high_availability' => true,    // Critical for MCP functionality
            'compression_friendly' => true  // JSON compresses well
        ];
    }
    
    /**
     * Check if directory structure needs to be created
     * 
     * @return bool True if directory creation is needed
     */
    public function needs_directory_creation() {
        return !is_dir(ABSPATH . '.well-known/mcp/');
    }
    
    /**
     * Detect hybrid Apache + Nginx setup
     * 
     * @return bool True if hybrid setup is detected
     */
    private function detect_hybrid_setup() {
        // Check if we have Apache indicators AND Nginx cache headers
        $has_apache = ($this->plugin_instance->is_apache || 
                      $this->plugin_instance->supports_htaccess ||
                      function_exists('apache_get_version'));
        
        $has_nginx_frontend = (isset($_SERVER['HTTP_X_NGINX_CACHE']) ||
                              isset($_SERVER['HTTP_X_CACHE_STATUS']) ||
                              isset($_SERVER['HTTP_X_FASTCGI_CACHE']));
        
        return $has_apache && $has_nginx_frontend;
    }
} 