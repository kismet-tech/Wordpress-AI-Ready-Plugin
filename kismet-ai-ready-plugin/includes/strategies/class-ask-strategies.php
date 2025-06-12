<?php
/**
 * Ask Endpoint Strategy Manager
 * 
 * Manages serving strategies for /ask endpoint based on server configuration
 * SPECIAL: This endpoint MUST use WordPress processing for API proxying functionality.
 * It cannot be served as a static file since it needs to handle POST requests and proxy to external APIs.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Ask_Strategies {
    
    private $plugin_instance;
    
    public function __construct($plugin_instance) {
        $this->plugin_instance = $plugin_instance;
    }
    
    /**
     * Get ordered strategies for /ask endpoint
     * 
     * The /ask endpoint is unique because it:
     * 1. MUST handle both GET (HTML page) and POST (API proxy) requests
     * 2. Needs WordPress processing for API proxy functionality
     * 3. Serves dynamic content based on request type
     * 4. Cannot be a static file
     * 
     * **UPDATED: Now uses Strategy Executor with composable building blocks**
     * Uses Ask Handler building block that integrates with existing Ask Handler class.
     * 
     * @return array Ordered array of strategies to try
     */
    public function get_ordered_strategies() {
        // Load Strategy Executor for recommendations
        require_once plugin_dir_path(__FILE__) . '../installers/implementations/class-strategy-executor.php';
        
        // Get recommended strategy from Strategy Executor
        $recommended_strategy = Kismet_Strategy_Executor::get_recommended_strategy('/ask', $this->plugin_instance);
        
        // Get server detector for fallback logic
        $server_detector = $this->plugin_instance->get_server_detector();
        
        // Build strategy list based on server capabilities
        $strategies = array();
        
        // Start with the recommended strategy
        $strategies[] = $recommended_strategy;
        
        // Add fallback strategies based on server type
        if ($server_detector->supports_htaccess && $recommended_strategy !== 'ask_handler_with_htaccess_backup') {
            $strategies[] = 'ask_handler_with_htaccess_backup';
        }
        
        if (($server_detector->is_nginx || $server_detector->supports_nginx_config) 
            && $recommended_strategy !== 'ask_handler_with_nginx_optimization') {
            $strategies[] = 'ask_handler_with_nginx_optimization';
        }
        
        // Always include basic handler as final fallback
        if ($recommended_strategy !== 'ask_handler_basic') {
            $strategies[] = 'ask_handler_basic';
        }
        
        // Remove duplicates while preserving order
        $strategies = array_unique($strategies);
        
        error_log("KISMET ASK STRATEGIES: Using Strategy Executor recommendations: " . implode(', ', $strategies));
        
        return $strategies;
    }
    
    /**
     * Get strategy-specific configuration for this endpoint
     * 
     * @param string $strategy The strategy being implemented
     * @return array Configuration options for the strategy
     */
    public function get_strategy_config($strategy) {
        switch ($strategy) {
            // New Ask Handler strategies using composable building blocks
            case 'ask_handler_with_htaccess_backup':
                return [
                    'endpoint_path' => '/ask',
                    'building_blocks' => ['ask_handler', 'add_htaccess_rules'],
                    'handle_methods' => ['GET', 'POST', 'OPTIONS'],
                    'api_proxy_enabled' => true,
                    'cors_required' => true,
                    'performance_priority' => 'high',
                    'htaccess_rules' => [
                        '# Kismet Ask Endpoint CORS Headers',
                        'Header always set Access-Control-Allow-Origin "*"',
                        'Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"',
                        'Header always set Access-Control-Allow-Headers "Content-Type, Authorization"'
                    ]
                ];
                
            case 'ask_handler_with_nginx_optimization':
                return [
                    'endpoint_path' => '/ask',
                    'building_blocks' => ['ask_handler', 'suggest_nginx_config'],
                    'handle_methods' => ['GET', 'POST', 'OPTIONS'],
                    'api_proxy_enabled' => true,
                    'cors_required' => true,
                    'performance_priority' => 'high',
                    'nginx_config_suggestion' => [
                        '# Kismet Ask Endpoint Configuration',
                        'location /ask {',
                        '    try_files $uri $uri/ /index.php?kismet_ask_endpoint=1&$args;',
                        '    add_header Access-Control-Allow-Origin "*" always;',
                        '    add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;',
                        '    add_header Access-Control-Allow-Headers "Content-Type, Authorization" always;',
                        '}'
                    ]
                ];
                
            case 'ask_handler_basic':
                return [
                    'endpoint_path' => '/ask',
                    'building_blocks' => ['ask_handler'],
                    'handle_methods' => ['GET', 'POST', 'OPTIONS'],
                    'api_proxy_enabled' => true,
                    'cors_required' => true,
                    'performance_priority' => 'medium'
                ];
                
            // Legacy strategies (for backward compatibility)
            case 'wordpress_rewrite_with_htaccess_backup':
                return [
                    'rewrite_rule' => '^ask/?$',
                    'query_vars' => ['kismet_ask' => '1'],
                    'htaccess_backup_rules' => [
                        '# Kismet Ask Endpoint Backup Rules',
                        'RewriteEngine On',
                        'RewriteRule ^ask/?$ index.php?kismet_ask=1 [L,QSA]'
                    ],
                    'handle_methods' => ['GET', 'POST', 'OPTIONS'],
                    'api_proxy_enabled' => true,
                    'cors_required' => true,
                    'performance_priority' => 'high'
                ];
                
            case 'wordpress_rewrite_with_nginx_optimization':
                return [
                    'rewrite_rule' => '^ask/?$',
                    'query_vars' => ['kismet_ask' => '1'],
                    'nginx_optimization_suggestion' => 'location /ask { try_files $uri $uri/ /index.php?kismet_ask=1&$args; }',
                    'handle_methods' => ['GET', 'POST', 'OPTIONS'],
                    'api_proxy_enabled' => true,
                    'cors_required' => true,
                    'performance_priority' => 'high'
                ];
                
            case 'wordpress_rewrite_only':
                return [
                    'rewrite_rule' => '^ask/?$',
                    'query_vars' => ['kismet_ask' => '1'],
                    'handle_methods' => ['GET', 'POST', 'OPTIONS'],
                    'api_proxy_enabled' => true,
                    'cors_required' => true,
                    'performance_priority' => 'medium'
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
        
        $recommendations[] = 'CRITICAL: /ask endpoint requires WordPress processing - cannot be static file';
        $recommendations[] = 'Ensure WordPress permalinks are enabled (Settings > Permalinks)';
        $recommendations[] = 'Check if rewrite rules are working: test with other WordPress pages';
        
        $server_detector = $this->plugin_instance->get_server_detector();
        
        if ($server_detector->is_nginx) {
            $recommendations[] = 'Nginx configuration for WordPress rewrite rules:';
            $recommendations[] = 'try_files $uri $uri/ /index.php?$args;';
        } elseif ($server_detector->is_apache || $server_detector->is_litespeed) {
            $recommendations[] = 'Ensure mod_rewrite is enabled for Apache/LiteSpeed';
            $recommendations[] = 'Check .htaccess file permissions and AllowOverride directive';
        } elseif ($server_detector->is_iis) {
            $recommendations[] = 'Install IIS URL Rewrite module for Windows hosting';
            $recommendations[] = 'Ensure web.config file supports URL rewriting';
        }
        
        $recommendations[] = 'Test WordPress rewrite rules: visit ' . site_url('/ask');
        $recommendations[] = 'Check for conflicting plugins that modify rewrite rules';
        $recommendations[] = 'Verify PHP can handle POST requests (API proxy functionality)';
        $recommendations[] = 'Ensure WordPress can make outbound HTTP requests (wp_remote_post)';
        
        return $recommendations;
    }
    
    /**
     * Get API proxy requirements for this endpoint
     * 
     * @return array API proxy configuration requirements
     */
    public function get_api_proxy_requirements() {
        return [
            'outbound_requests_allowed' => true,    // Must be able to make HTTP requests
            'post_requests_supported' => true,      // Must handle POST requests
            'json_processing' => true,              // Must handle JSON request/response
            'cors_headers_required' => true,        // Browser requests need CORS
            'timeout_handling' => true,             // Must handle API timeouts gracefully
            'error_handling' => true,               // Must return proper error responses
            'rate_limiting_aware' => true          // Should respect external API rate limits
        ];
    }
    
    /**
     * Get CORS configuration for browser access
     * 
     * @return array CORS headers configuration
     */
    public function get_cors_config() {
        return [
            'Access-Control-Allow-Origin' => '*',  // Allow from any origin for public API
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age' => '86400' // 24 hours
        ];
    }
    
    /**
     * Get special requirements for this endpoint
     * 
     * @return array Special requirements that cannot be handled by static files
     */
    public function get_special_requirements() {
        return [
            'requires_wordpress_processing' => true,  // CRITICAL: Cannot be static
            'handles_multiple_http_methods' => true,  // GET, POST, OPTIONS
            'api_proxy_functionality' => true,        // Proxies to external APIs
            'dynamic_content_generation' => true,     // Content varies by request
            'server_side_processing' => true,         // Needs PHP execution
            'external_api_communication' => true,     // Makes outbound HTTP requests
            'session_handling' => false,              // Stateless API endpoint
            'database_access' => false                // Pure proxy, no DB needed
        ];
    }
    
    /**
     * Get endpoint-specific performance requirements
     * 
     * @return array Performance requirements for API proxy
     */
    public function get_performance_requirements() {
        return [
            'cache_friendly' => false,              // Cannot cache - dynamic proxy responses
            'low_latency' => true,                  // API responses should be fast
            'high_availability' => true,           // Critical for AI tool functionality
            'compression_friendly' => true,        // JSON responses compress well
            'timeout_tolerance' => 30,             // Up to 30 seconds for AI API calls
            'concurrent_request_handling' => true, // Multiple users may call simultaneously
            'error_recovery' => true               // Must handle external API failures gracefully
        ];
    }
    
    /**
     * Validate that WordPress environment supports API proxy functionality
     * 
     * @return array Validation results
     */
    public function validate_api_proxy_support() {
        $validation = [
            'wordpress_rewrite_enabled' => false,
            'outbound_requests_allowed' => false,
            'json_functions_available' => false,
            'post_method_supported' => false
        ];
        
        // Check WordPress rewrite rules
        global $wp_rewrite;
        $validation['wordpress_rewrite_enabled'] = ($wp_rewrite && $wp_rewrite->using_permalinks());
        
        // Check outbound HTTP requests
        $test_response = wp_remote_get('https://httpbin.org/status/200', ['timeout' => 5]);
        $validation['outbound_requests_allowed'] = !is_wp_error($test_response);
        
        // Check JSON functions
        $validation['json_functions_available'] = (function_exists('json_encode') && function_exists('json_decode'));
        
        // Check POST method support (assume supported if we're running WordPress)
        $validation['post_method_supported'] = isset($_SERVER['REQUEST_METHOD']);
        
        return $validation;
    }
    
    /**
     * Detect hybrid Apache + Nginx setup
     * 
     * @return bool True if hybrid setup is detected
     */
    private function detect_hybrid_setup() {
        // Check if we have Apache indicators AND Nginx cache headers
        $server_detector = $this->plugin_instance->get_server_detector();
        $has_apache = ($server_detector->is_apache || 
                      $server_detector->supports_htaccess ||
                      function_exists('apache_get_version'));
        
        $has_nginx_frontend = (isset($_SERVER['HTTP_X_NGINX_CACHE']) ||
                              isset($_SERVER['HTTP_X_CACHE_STATUS']) ||
                              isset($_SERVER['HTTP_X_FASTCGI_CACHE']));
        
        return $has_apache && $has_nginx_frontend;
    }
} 