<?php
/**
 * AI Plugin Endpoint Strategy Manager
 * 
 * Manages serving strategies for /.well-known/ai-plugin.json based on server configuration
 * This endpoint is pure static JSON and benefits most from static file serving.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_AI_Plugin_Strategies {
    
    private $plugin_instance;
    
    public function __construct($plugin_instance) {
        $this->plugin_instance = $plugin_instance;
    }
    
    /**
     * Get ordered strategies for /.well-known/ai-plugin.json endpoint
     * 
     * This endpoint is pure static JSON, so it heavily favors static file strategies.
     * Performance is critical for AI discovery, so we prioritize the fastest options.
     * 
     * **ENHANCED: Uses comprehensive server analysis for optimal first-time strategy selection**
     * **ENHANCED: Checks admin toggle for event tracking vs static files preference**
     * 
     * @return array Ordered array of strategies to try
     */
    public function get_ordered_strategies() {
        // **CHECK ADMIN TOGGLE FOR EVENT TRACKING PREFERENCE**
        require_once(plugin_dir_path(__FILE__) . '../admin/class-ai-plugin-admin.php');
        $should_send_events = Kismet_AI_Plugin_Admin::should_send_events();
        
        // **LOG TOGGLE STATUS FOR DEBUGGING**
        error_log("KISMET AI PLUGIN STRATEGIES: Admin toggle status - should_send_events: " . ($should_send_events ? 'TRUE' : 'FALSE'));
        
        // **IF CHECKBOX IS UNCHECKED (should send events), prioritize metrics-enabled strategy**
        if ($should_send_events) {
            error_log("KISMET AI PLUGIN STRATEGIES: Prioritizing metrics-enabled strategy (checkbox unchecked)");
            
            // Put the metrics-enabled WordPress rewrite strategy FIRST
            $base_strategies = $this->get_base_strategies_for_server();
            
            // Prepend the metrics strategy to the beginning
            array_unshift($base_strategies, 'wordpress_rewrite_with_metrics_and_caching');
            
            error_log("KISMET AI PLUGIN STRATEGIES: Final strategy order (events enabled): " . implode(', ', $base_strategies));
            return $base_strategies;
        }
        
        // **IF CHECKBOX IS CHECKED (static files only), keep original strategy order**
        error_log("KISMET AI PLUGIN STRATEGIES: Using original strategy order (checkbox checked - static files only)");
        $original_strategies = $this->get_base_strategies_for_server();
        error_log("KISMET AI PLUGIN STRATEGIES: Final strategy order (static files only): " . implode(', ', $original_strategies));
        return $original_strategies;
    }
    
    /**
     * Get base strategies for the current server environment
     * This is the original strategy selection logic, extracted for reuse
     * 
     * @return array Base strategies in server-optimized order
     */
    private function get_base_strategies_for_server() {
        // **MANAGED WORDPRESS HOSTING: Typically optimized for WordPress, prefer rewrites**
        if (isset($this->plugin_instance->hosting_environment['managed_wordpress']) 
            && $this->plugin_instance->hosting_environment['managed_wordpress']) {
            
            return [
                'wordpress_rewrite',           // BEST: Optimized hosting environments
                'static_file_with_htaccess',   // GOOD: If static files are allowed
                'manual_static_file'           // FALLBACK: Basic approach
            ];
        }
        
        // **SHARED HOSTING: Often has restrictions, be conservative**
        if (isset($this->plugin_instance->hosting_environment['shared_hosting']) 
            && $this->plugin_instance->hosting_environment['shared_hosting']) {
            
            return [
                'wordpress_rewrite',          // SAFEST: Most compatible on shared hosting
                'manual_static_file',         // FALLBACK: If WordPress fails
                'static_file_with_htaccess'   // LAST RESORT: May be blocked
            ];
        }
        
        // **APACHE with VERIFIED .htaccess support and permissions**
        $server_detector = $this->plugin_instance->get_server_detector();
        if ($server_detector->is_apache 
            && $server_detector->supports_htaccess 
            && $server_detector->filesystem_permissions['can_write_root']) {
            
            // Check for mod_headers support (needed for CORS)
            $has_mod_headers = isset($server_detector->apache_capabilities['mod_headers']) 
                             && $server_detector->apache_capabilities['mod_headers'];
            
            if ($has_mod_headers) {
                return [
                    'static_file_with_htaccess',  // BEST: Perfect setup for static files + CORS
                    'manual_static_file',         // GOOD: Static file without automatic CORS
                    'wordpress_rewrite'           // FALLBACK: WordPress processing
                ];
            } else {
                return [
                    'manual_static_file',         // BEST: Static file (manual CORS needed)
                    'static_file_with_htaccess',  // EXPERIMENTAL: Try .htaccess anyway
                    'wordpress_rewrite'           // FALLBACK: WordPress processing
                ];
            }
        }
        
        // **APACHE without .htaccess support or permissions** 
        elseif ($server_detector->is_apache) {
            return [
                'wordpress_rewrite',          // BEST: Apache without .htaccess = use WordPress
                'manual_static_file'          // FALLBACK: Basic static file attempt
            ];
        }
        
        // **LITESPEED with VERIFIED capabilities**
        elseif ($server_detector->is_litespeed 
                && $server_detector->supports_htaccess
                && $server_detector->filesystem_permissions['can_write_root']) {
            
            return [
                'static_file_with_htaccess',  // BEST: LiteSpeed + .htaccess = excellent performance
                'manual_static_file',         // GOOD: Static file fallback
                'wordpress_rewrite'           // FALLBACK: WordPress processing
            ];
        }
        
        // **NGINX with VERIFIED file permissions**
        elseif ($server_detector->is_nginx) {
            $can_write = $server_detector->filesystem_permissions['can_write_root'];
            
            if ($can_write) {
                return [
                    'static_file_with_nginx_suggestion', // BEST: Static file + nginx config help
                    'manual_static_file',                 // GOOD: Static file without config
                    'wordpress_rewrite'                   // FALLBACK: WordPress processing
                ];
            } else {
                return [
                    'wordpress_rewrite',                  // BEST: Can't write files = use WordPress
                    'static_file_with_nginx_suggestion'   // FALLBACK: Worth a try
                ];
            }
        }
        
        // **MICROSOFT IIS with capability detection**
        elseif ($server_detector->is_iis) {
            $has_url_rewrite = isset($server_detector->iis_capabilities['url_rewrite'])
                             && $server_detector->iis_capabilities['url_rewrite'];
            $supports_web_config = $server_detector->supports_web_config;
            
            if ($has_url_rewrite) {
                return [
                    'wordpress_rewrite',              // BEST: IIS with URL Rewrite is reliable
                    'static_file_with_web_config',    // GOOD: Try web.config approach
                    'manual_static_file'              // FALLBACK: Basic static file
                ];
            } elseif ($supports_web_config) {
                return [
                    'static_file_with_web_config',    // BEST: web.config without URL Rewrite
                    'wordpress_rewrite',              // GOOD: WordPress fallback
                    'manual_static_file'              // FALLBACK: Basic approach
                ];
            } else {
                return [
                    'wordpress_rewrite',              // SAFEST: Limited IIS capabilities
                    'manual_static_file'              // FALLBACK: Basic attempt
                ];
            }
        }
        
        // **CLOUD PLATFORMS: Usually well-configured**
        elseif (isset($server_detector->hosting_environment['cloud_platform']) 
                && $server_detector->hosting_environment['cloud_platform']) {
            
            return [
                'static_file_with_htaccess',  // BEST: Cloud platforms usually support .htaccess
                'wordpress_rewrite',          // GOOD: Reliable fallback
                'manual_static_file'          // FALLBACK: Basic approach
            ];
        }
        
        // **HYBRID SETUPS: Apache backend + Nginx frontend (common hosting)**
        elseif (($server_detector->is_apache || $server_detector->supports_htaccess) && 
                $this->detect_nginx_frontend()) {
            
            error_log("KISMET AI PLUGIN STRATEGIES: Detected hybrid Apache+Nginx setup");
            return [
                'static_file_with_htaccess',      // BEST: Apache backend handles .htaccess
                'static_file_with_nginx_suggestion', // GOOD: Nginx frontend optimization
                'manual_static_file',             // BACKUP: Static file without config
                'wordpress_rewrite'               // FALLBACK: WordPress processing
            ];
        }
        
        // **UNKNOWN SERVER TYPE: Check for basic Apache support**
        else {
            error_log("KISMET AI PLUGIN STRATEGIES: Unknown server configuration, checking for basic Apache support");
            
            // Enhanced detection for Apache-like behavior
            if ($server_detector->supports_htaccess || $this->detect_apache_indicators()) {
                error_log("KISMET AI PLUGIN STRATEGIES: Detected Apache indicators, using static file strategy");
                return [
                    'static_file_with_htaccess',  // TRY: Basic Apache + .htaccess
                    'manual_static_file',         // BACKUP: Static file without .htaccess
                    'wordpress_rewrite'           // FALLBACK: WordPress processing
                ];
            } else {
                error_log("KISMET AI PLUGIN STRATEGIES: No Apache indicators detected, using conservative approach");
                return [
                    'wordpress_rewrite',    // SAFEST: Works on most configurations
                    'manual_static_file'    // FALLBACK: Basic approach
                ];
            }
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
                    'file_path' => ABSPATH . '.well-known/ai-plugin.json',
                    'htaccess_rules' => [
                        'Header always set Access-Control-Allow-Origin "*"',
                        'Header always set Access-Control-Allow-Methods "GET, OPTIONS"',
                        'Header always set Access-Control-Allow-Headers "Content-Type"',
                        'Header always set Content-Type "application/json"'
                    ],
                    'performance_priority' => 'highest'
                ];
                
            case 'static_file_with_nginx_suggestion':
                return [
                    'file_path' => ABSPATH . '.well-known/ai-plugin.json',
                    'nginx_config_suggestion' => 'location /.well-known/ai-plugin.json { add_header Access-Control-Allow-Origin "*"; add_header Content-Type "application/json"; }',
                    'performance_priority' => 'highest'
                ];
                
            case 'static_file_with_web_config':
                return [
                    'file_path' => ABSPATH . '.well-known/ai-plugin.json',
                    'web_config_rules' => [
                        '<httpHeaders>',
                        '  <add name="Access-Control-Allow-Origin" value="*" />',
                        '  <add name="Content-Type" value="application/json" />',
                        '</httpHeaders>'
                    ],
                    'performance_priority' => 'high'
                ];
                
            case 'wordpress_rewrite':
                return [
                    'rewrite_rule' => '^\.well-known/ai-plugin\.json$',
                    'query_vars' => ['kismet_ai_plugin' => '1'],
                    'performance_priority' => 'medium'
                ];
                
            case 'manual_static_file':
                return [
                    'file_path' => ABSPATH . '.well-known/ai-plugin.json',
                    'requires_manual_cors' => true,
                    'performance_priority' => 'high'
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
        $server_detector = $this->plugin_instance->get_server_detector();
        
        if ($server_detector->is_nginx) {
            $recommendations[] = 'Add this nginx configuration to your server block:';
            $recommendations[] = 'location /.well-known/ai-plugin.json { add_header Access-Control-Allow-Origin "*"; add_header Content-Type "application/json"; }';
        } elseif ($server_detector->is_apache || $server_detector->is_litespeed) {
            $recommendations[] = 'Ensure AllowOverride directive is enabled for .htaccess files';
            $recommendations[] = 'Check if mod_headers is enabled for CORS support';
        } elseif ($server_detector->is_iis) {
            $recommendations[] = 'Install IIS URL Rewrite module';
            $recommendations[] = 'Ensure web.config file permissions allow modifications';
        }
        
        $recommendations[] = 'AI plugin discovery requires CORS headers for browser access';
        $recommendations[] = 'Test endpoint with: curl -H "Origin: https://chatgpt.com" ' . site_url('/.well-known/ai-plugin.json');
        
        return $recommendations;
    }
    
    /**
     * Check if this endpoint requires special CORS handling
     * 
     * @return bool True if CORS is critical for this endpoint
     */
    public function requires_cors() {
        return true; // AI plugin discovery requires CORS for browser access
    }
    
    /**
     * Get endpoint-specific performance requirements
     * 
     * @return array Performance requirements
     */
    public function get_performance_requirements() {
        return [
            'cache_friendly' => true,      // Should be highly cacheable
            'low_latency' => true,         // AI discovery needs fast response
            'high_availability' => true,   // Critical for AI tool discovery
            'compression_friendly' => true // JSON compresses well
        ];
    }
    
    /**
     * Detect if there's an Nginx frontend (common in hybrid setups)
     * 
     * @return bool True if Nginx frontend is detected
     */
    private function detect_nginx_frontend() {
        // Check for Nginx-specific headers that indicate frontend caching
        $nginx_indicators = [
            'HTTP_X_NGINX_CACHE',
            'HTTP_X_CACHE_STATUS', 
            'HTTP_X_FASTCGI_CACHE',
            'HTTP_X_PROXY_CACHE'
        ];
        
        foreach ($nginx_indicators as $header) {
            if (isset($_SERVER[$header])) {
                return true;
            }
        }
        
        // Check for common Nginx cache headers in response
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                $name_lower = strtolower($name);
                if (strpos($name_lower, 'nginx') !== false || 
                    strpos($name_lower, 'cache') !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Detect Apache indicators beyond basic server detection
     * 
     * @return bool True if Apache indicators are found
     */
    private function detect_apache_indicators() {
        // Check for Apache-specific functions
        if (function_exists('apache_get_version') || function_exists('apache_get_modules')) {
            return true;
        }
        
        // Check for .htaccess file with Apache directives
        if (file_exists(ABSPATH . '.htaccess')) {
            $htaccess_content = file_get_contents(ABSPATH . '.htaccess');
            if ($htaccess_content && (
                strpos($htaccess_content, 'RewriteEngine') !== false ||
                strpos($htaccess_content, 'RewriteRule') !== false ||
                strpos($htaccess_content, 'Header ') !== false ||
                strpos($htaccess_content, 'DirectoryIndex') !== false
            )) {
                return true;
            }
        }
        
        // Check for Apache environment variables
        if (isset($_SERVER['REDIRECT_STATUS']) || 
            isset($_SERVER['REDIRECT_URL']) ||
            isset($_SERVER['APACHE_RUN_USER'])) {
            return true;
        }
        
        // Check if WordPress permalinks work (indicates mod_rewrite)
        if (get_option('permalink_structure')) {
            return true;
        }
        
        return false;
    }
} 