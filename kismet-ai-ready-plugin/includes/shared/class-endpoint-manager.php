<?php
/**
 * Kismet Endpoint Manager
 * 
 * Manages endpoint registration and strategy selection for AI plugin endpoints.
 * Uses the new Kismet_Strategy_Registry system for proper strategy implementation.
 * 
 * **STRATEGY SWITCHING ARCHITECTURE:**
 * 
 * Uses secure WordPress admin action approach (class-strategy-switcher.php):
 * 1. User clicks "Try [Strategy]" link → Triggers WordPress admin action
 * 2. Admin action stores user preference in database (kismet_strategy_preference_*)
 * 3. Admin action deactivates plugin → Cleans up old implementation properly
 * 4. Admin action reactivates plugin → determine_best_approach() reads preferences and implements
 * 5. User sees success/error message and updated endpoint status
 * 
 * This approach is:
 * - ✅ Secure (no AJAX filesystem manipulation)
 * - ✅ Reliable (uses WordPress plugin lifecycle)
 * - ✅ Clean (leverages existing activation/deactivation hooks)
 * - ✅ Simple (preference storage + plugin lifecycle = strategy switching)
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../shared/class-route-tester.php');
require_once(plugin_dir_path(__FILE__) . '../strategies/strategies.php');

class Kismet_Endpoint_Manager {
    
    private $route_tester;
    private $endpoints = array();
    private static $instance = null;
    
    public function __construct() {
        error_log('KISMET ENDPOINT MANAGER: Constructor called');
        $this->route_tester = new Kismet_Route_Tester();
        error_log('KISMET ENDPOINT MANAGER: Route tester initialized');
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        error_log('KISMET ENDPOINT MANAGER: get_instance() called');
        if (self::$instance === null) {
            error_log('KISMET ENDPOINT MANAGER: Creating new instance');
            self::$instance = new self();
            error_log('KISMET ENDPOINT MANAGER: New instance created');
        }
        return self::$instance;
    }
    
    /**
     * Register an endpoint with strategy-based implementation
     * 
     * @param array $config Configuration array with:
     *   - path: URL path (e.g., '/.well-known/ai-plugin.json')
     *   - content_generator: Callable that generates content
     *   - content_type: MIME type (optional, defaults to text/plain)
     *   - method: HTTP method (optional, defaults to GET)
     */
    public function register_endpoint($config) {
        $path = $config['path'];
        $content_generator = $config['content_generator'];
        
        error_log("KISMET ENDPOINT: Registering endpoint: " . $path);
        
        try {
            // Get strategy from the main plugin's server detection system
            global $kismet_ask_proxy_plugin;
            
            if (!$kismet_ask_proxy_plugin) {
                throw new Exception("Main plugin instance not available for strategy selection");
            }
            
            // Get ordered strategies for this endpoint from the main plugin
            $strategies = $kismet_ask_proxy_plugin->get_endpoint_strategies($path);
            
            error_log("KISMET ENDPOINT: Got " . count($strategies) . " strategies for " . $path);
            
            // Try each strategy until one succeeds
            $success = false;
            $last_error = '';
            
            foreach ($strategies as $strategy) {
                error_log("KISMET ENDPOINT: Trying strategy: " . $strategy . " for " . $path);
                
                $result = Kismet_Strategy_Registry::execute_strategy(
                    $strategy,
                    $path,
                    array('content_generator' => $content_generator),
                    $kismet_ask_proxy_plugin
                );
                
                if ($result['success']) {
                    error_log("KISMET ENDPOINT: Strategy " . $strategy . " succeeded for " . $path);
                    
                    // Store successful strategy
                    $this->save_endpoint_strategy($path, array(
                        'current_strategy' => $strategy,
                        'timestamp' => current_time('mysql'),
                        'success' => true
                    ));
                    
                    $success = true;
                    $successful_strategy = $strategy;
                    break;
                } else {
                    error_log("KISMET ENDPOINT: Strategy " . $strategy . " failed for " . $path . ": " . $result['error']);
                    $last_error = $result['error'];
                }
            }
            
            if (!$success) {
                error_log("KISMET ENDPOINT: All strategies failed for " . $path . ". Last error: " . $last_error);
                
                // Store failure information
                $this->save_endpoint_strategy($path, array(
                    'current_strategy' => Kismet_Strategy_Registry::FAILED,
                    'timestamp' => current_time('mysql'),
                    'success' => false,
                    'error' => $last_error
                ));
                
                return array(
                    'path' => $path,
                    'overall_success' => false,
                    'error' => $last_error
                );
            }
            
            // Special handling for /ask endpoint
            if ($path === '/ask') {
                $config['query_var'] = 'kismet_ask';
            } else {
                // Create unique query var based on path for other endpoints
                $config['query_var'] = 'kismet_endpoint_' . md5($path);
            }
            
            // Store endpoint config for runtime handling
            $this->endpoints[$path] = $config;
            
            return array(
                'path' => $path,
                'overall_success' => true,
                'strategy_used' => $successful_strategy ?? 'unknown'
            );
            
        } catch (Exception $e) {
            error_log("KISMET ENDPOINT ERROR: Failed to register endpoint $path: " . $e->getMessage());
            
            // Store error information
            $this->save_endpoint_strategy($path, array(
                'current_strategy' => Kismet_Strategy_Registry::FAILED,
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
                'success' => false
            ));
            
            return array(
                'path' => $path,
                'overall_success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Add query vars for all registered endpoints
     */
    public function add_query_vars($vars) {
        error_log('KISMET DEBUG: Adding query vars in Endpoint Manager');
        
        foreach ($this->endpoints as $path => $config) {
            if (isset($config['query_var'])) {
                $vars[] = $config['query_var'];
                error_log("KISMET DEBUG: Added query var {$config['query_var']} for {$path}");
            }
        }
        
        return $vars;
    }
    
    /**
     * Handle template redirect for all registered endpoints
     */
    public function handle_template_redirect() {
        global $wp_query;
        
        error_log('KISMET DEBUG: Template redirect check in Endpoint Manager');
        error_log('KISMET DEBUG: All query vars: ' . print_r($wp_query->query_vars, true));
        error_log('KISMET DEBUG: Request URI: ' . $_SERVER['REQUEST_URI']);
        
        //TODO: REMOVE THIS LOG WHEN /ASK ENDPOINT WORKS.
        // **CONFIRMATION LOG: Check if /ask endpoint is registered and working**
        if ($_SERVER['REQUEST_URI'] === '/ask' || strpos($_SERVER['REQUEST_URI'], '/ask') === 0) {
            $ask_registered = isset($this->endpoints['/ask']);
            error_log('KISMET CONFIRMATION: /ask endpoint registered in manager: ' . ($ask_registered ? 'YES' : 'NO'));
            error_log('KISMET CONFIRMATION: Registered endpoints: ' . print_r(array_keys($this->endpoints), true));
            
            if (!$ask_registered) {
                error_log('KISMET CONFIRMATION: /ask endpoint NOT registered - WordPress will treat "ask" as page name');
                error_log('KISMET CONFIRMATION: This explains why [name] => ask appears in query vars');
            }
        }
        
        // Check each registered endpoint
        foreach ($this->endpoints as $path => $config) {
            $query_var = $config['query_var'];
            error_log("KISMET DEBUG: Checking endpoint {$path} with query var {$query_var}");
            
            if (isset($wp_query->query_vars[$query_var])) {
                error_log("KISMET DEBUG: Found match for {$path}");
                $this->serve_endpoint_content($config);
                exit;
            }
        }
    }
    
    /**
     * Serve content for an endpoint
     */
    private function serve_endpoint_content($config) {
        $content = call_user_func($config['content_generator']);
        $content_type = $config['content_type'] ?? 'text/plain';
        
        // Set headers
        header('Content-Type: ' . $content_type);
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        
        // Output content
        echo $content;
        
        error_log("KISMET ENDPOINT: Served content via WordPress rewrite for " . $config['path']);
    }
    
    /**
     * Clean up endpoint files during deactivation
     */
    public function cleanup_endpoint($path) {
        $file_path = ABSPATH . ltrim($path, '/');
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("KISMET ENDPOINT: Removed static file: " . $file_path);
            } else {
                error_log("KISMET ENDPOINT WARNING: Failed to remove file: " . $file_path);
            }
        }
    }
    
    /**
     * Get registered endpoints for debugging
     */
    public function get_registered_endpoints() {
        return $this->endpoints;
    }
    
    /**
     * Save endpoint strategy information for dashboard access
     */
    private function save_endpoint_strategy($path, $strategy_data) {
        $endpoint_key = $this->path_to_option_key($path);
        $option_name = 'kismet_endpoint_strategy_' . $endpoint_key;
        
        error_log("KISMET ENDPOINT: Saving strategy for $path as option $option_name");
        return update_option($option_name, $strategy_data);
    }
    
    /**
     * Get strategy information for a specific endpoint
     */
    public function get_endpoint_strategy($path) {
        $endpoint_key = $this->path_to_option_key($path);
        $option_name = 'kismet_endpoint_strategy_' . $endpoint_key;
        
        return get_option($option_name, array(
            'current_strategy' => Kismet_Strategy_Registry::UNKNOWN,
            'timestamp' => null,
            'success' => false
        ));
    }
    
    /**
     * Get all endpoint strategies for dashboard display
     */
    public function get_all_endpoint_strategies() {
        $strategies = array();
        $common_endpoints = array(
            '/.well-known/ai-plugin.json',
            '/.well-known/mcp/servers.json',
            '/llms.txt',
            '/ask',
            '/robots.txt'
        );
        
        foreach ($common_endpoints as $path) {
            $strategies[$path] = $this->get_endpoint_strategy($path);
        }
        
        return $strategies;
    }
    
    /**
     * Convert URL path to safe option key
     */
    private function path_to_option_key($path) {
        // Convert path to safe option key by removing slashes and dots
        $key = str_replace(array('/', '.', '-'), '_', $path);
        $key = trim($key, '_');
        return $key;
    }
    
    /**
     * Deactivate a specific endpoint
     * 
     * @param string $path Endpoint path to deactivate
     */
    public function deactivate_endpoint($path) {
        error_log("KISMET ENDPOINT: Deactivating endpoint: " . $path);
        
        // Remove from active endpoints
        if (isset($this->endpoints[$path])) {
            unset($this->endpoints[$path]);
        }
        
        // Clean up strategy data for this endpoint
        $endpoint_key = $this->path_to_option_key($path);
        $option_name = 'kismet_endpoint_strategy_' . $endpoint_key;
        delete_option($option_name);
        
        error_log("KISMET ENDPOINT: Endpoint deactivated: " . $path);
    }
    
    /**
     * Clean up strategy data during deactivation
     */
    public function cleanup_strategy_data() {
        $common_endpoints = array(
            '/.well-known/ai-plugin.json',
            '/.well-known/mcp/servers.json',
            '/llms.txt',
            '/ask',
            '/robots.txt'
        );
        
        foreach ($common_endpoints as $path) {
            $endpoint_key = $this->path_to_option_key($path);
            $option_name = 'kismet_endpoint_strategy_' . $endpoint_key;
            delete_option($option_name);
        }
        
        error_log("KISMET ENDPOINT: Cleaned up strategy data for all endpoints");
    }
} 