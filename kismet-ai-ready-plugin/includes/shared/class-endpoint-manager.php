<?php
/**
 * Kismet Endpoint Manager
 * 
 * Centralized endpoint management with automatic route testing and implementation.
 * Handles both static file creation and WordPress rewrite rules based on environment.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../shared/class-route-tester.php');

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
     * Register an endpoint with automatic route testing and implementation
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
        
        // Generate content for testing
        $test_content = call_user_func($content_generator);
        
        error_log("KISMET ENDPOINT: Registering endpoint: " . $path);
        
        // Test which approach works in this environment
        $test_results = $this->route_tester->determine_serving_method($path, $test_content);
        $approach = $test_results['recommended_approach'] ?? 'wordpress_rewrite';
        
        error_log("KISMET ENDPOINT: Route tester recommends: " . $approach . " for " . $path);
        
        if ($approach === 'physical_file') {
            $this->create_static_file($path, $test_content);
        } else {
            $this->register_rewrite_endpoint($path, $config);
        }
        
        // Store endpoint config for runtime handling
        $this->endpoints[$path] = $config;
        
        // Ensure the recommended_approach is preserved in the returned results
        // This fixes a bug where the approach value was getting lost
        $test_results['recommended_approach'] = $approach;
        
        error_log("KISMET ENDPOINT: Returning test results with approach: " . ($test_results['recommended_approach'] ?? 'NULL'));
        
        return $test_results;
    }
    
    /**
     * Create static file approach
     */
    private function create_static_file($path, $content) {
        $file_path = ABSPATH . ltrim($path, '/');
        
        // Create directory if needed
        $dir_path = dirname($file_path);
        if (!file_exists($dir_path)) {
            if (!wp_mkdir_p($dir_path)) {
                error_log("KISMET ENDPOINT ERROR: Cannot create directory: " . $dir_path);
                return false;
            }
        }
        
        // Write file
        $result = file_put_contents($file_path, $content);
        if ($result === false) {
            error_log("KISMET ENDPOINT ERROR: Cannot write file: " . $file_path);
            return false;
        }
        
        error_log("KISMET ENDPOINT: Created static file: " . $file_path);
        return true;
    }
    
    /**
     * Register WordPress rewrite endpoint
     */
    private function register_rewrite_endpoint($path, $config) {
        // Convert path to rewrite pattern
        $pattern = '^' . ltrim($path, '/') . '$';
        $pattern = str_replace('.', '\.', $pattern); // Escape dots
        $pattern = str_replace('/', '\/', $pattern); // Escape slashes
        
        // Create unique query var based on path
        $query_var = 'kismet_endpoint_' . md5($path);
        
        // Add rewrite rule
        add_rewrite_rule($pattern, 'index.php?' . $query_var . '=1', 'top');
        
        // Store query var for later registration
        $config['query_var'] = $query_var;
        
        error_log("KISMET ENDPOINT: Added rewrite rule: " . $pattern . " => " . $query_var);
    }
    
    /**
     * Add query vars for all registered endpoints
     */
    public function add_query_vars($vars) {
        foreach ($this->endpoints as $config) {
            if (isset($config['query_var'])) {
                $vars[] = $config['query_var'];
            }
        }
        return $vars;
    }
    
    /**
     * Handle template redirect for all registered endpoints
     */
    public function handle_template_redirect() {
        foreach ($this->endpoints as $path => $config) {
            if (isset($config['query_var'])) {
                $query_var = $config['query_var'];
                
                if (get_query_var($query_var)) {
                    $this->serve_endpoint_content($config);
                    exit;
                }
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
     * Get current request path for debugging
     */
    private function get_current_request_path() {
        return $_SERVER['REQUEST_URI'] ?? '/';
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
} 