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
        
        // Wrap route testing in try-catch block
        try {
            // Test which approach works in this environment
            $test_results = $this->route_tester->determine_serving_method($path, $test_content);
            
            // **SIMPLIFIED: Use simple strategy selection**
            $approach_result = $this->determine_best_approach($path, $test_results);
            $approach = $approach_result['strategy'];
            $strategy_index = $approach_result['index'];
            
            if (!$approach) {
                throw new Exception("No working strategy found for endpoint: $path");
            }
            
            error_log("KISMET ENDPOINT: Using strategy: " . $approach . " (index $strategy_index) for " . $path);
            
            if ($approach === 'physical_file') {
                $this->create_static_file($path, $test_content);
            } else {
                $this->register_rewrite_endpoint($path, $config);
            }
            
            // Store endpoint config for runtime handling
            $this->endpoints[$path] = $config;
            
            // Ensure the recommended_approach is preserved in the returned results
            $test_results['recommended_approach'] = $approach;
            
            // **SIMPLIFIED: Persist simple strategy information for dashboard access**
            $this->save_endpoint_strategy($path, array(
                'current_strategy' => $approach,
                'current_strategy_index' => $strategy_index,
                'timestamp' => current_time('mysql'),
                'both_strategies_work' => $test_results['wordpress_rewrite_test']['success'] && $test_results['physical_file_test']['success']
            ));
            
            error_log("KISMET ENDPOINT: Returning test results with approach: " . ($test_results['recommended_approach'] ?? 'NULL'));
            
            return $test_results;
            
        } catch (Exception $e) {
            error_log("KISMET ENDPOINT ERROR: Failed to register endpoint $path: " . $e->getMessage());
            
            // **SIMPLIFIED: Store error information for dashboard**
            $this->save_endpoint_strategy($path, array(
                'current_strategy' => 'failed',
                'current_strategy_index' => 0,
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql')
            ));
            
            // Return error results
            return array(
                'path' => $path,
                'overall_success' => false,
                'can_proceed' => false,
                'recommended_approach' => null,
                'error' => $e->getMessage()
            );
        }
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
    
    /**
     * **SIMPLIFIED: Save endpoint strategy information for dashboard access**
     */
    private function save_endpoint_strategy($path, $strategy_data) {
        $endpoint_key = $this->path_to_option_key($path);
        $option_name = 'kismet_endpoint_strategy_' . $endpoint_key;
        
        error_log("KISMET ENDPOINT: Saving strategy for $path as option $option_name");
        return update_option($option_name, $strategy_data);
    }
    
    /**
     * **SIMPLIFIED: Get strategy information for a specific endpoint**
     */
    public function get_endpoint_strategy($path) {
        $endpoint_key = $this->path_to_option_key($path);
        $option_name = 'kismet_endpoint_strategy_' . $endpoint_key;
        
        return get_option($option_name, array(
            'current_strategy' => 'unknown',
            'current_strategy_index' => 0,
            'timestamp' => null
        ));
    }
    
    /**
     * **SIMPLIFIED: Get all endpoint strategies for dashboard display**
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
     * **SIMPLIFIED: Get available strategies array**
     */
    private function get_available_strategies() {
        return array('physical_file', 'wordpress_rewrite');
    }
    
    /**
     * **SIMPLIFIED: Get next strategy to try**
     */
    private function get_next_strategy_index($current_index) {
        $strategies = $this->get_available_strategies();
        return ($current_index + 1) % count($strategies);
    }
    
    /**
     * **SIMPLIFIED: Determine best approach and save simple strategy data**
     */
    private function determine_best_approach($path, $test_results) {
        $strategies = $this->get_available_strategies();
        $wp_success = $test_results['wordpress_rewrite_test']['success'] ?? false;
        $file_success = $test_results['physical_file_test']['success'] ?? false;
        
        // Try strategies in order: physical_file (index 0), then wordpress_rewrite (index 1)
        if ($file_success) {
            error_log("KISMET ENDPOINT: Choosing physical_file (index 0)");
            return array('strategy' => 'physical_file', 'index' => 0);
        }
        
        if ($wp_success) {
            error_log("KISMET ENDPOINT: Choosing wordpress_rewrite (index 1)");
            return array('strategy' => 'wordpress_rewrite', 'index' => 1);
        }
        
        error_log("KISMET ENDPOINT: No working strategy found");
        return array('strategy' => null, 'index' => 0);
    }
    
    /**
     * **NEW: Determine what the fallback strategy should be**
     */
    private function determine_fallback_strategy($test_results) {
        $wp_success = $test_results['wordpress_rewrite_test']['success'] ?? false;
        $file_success = $test_results['physical_file_test']['success'] ?? false;
        $current_approach = $test_results['recommended_approach'] ?? null;
        
        // If both work, the fallback is the one not currently used
        if ($wp_success && $file_success) {
            return ($current_approach === 'wordpress_rewrite') ? 'physical_file' : 'wordpress_rewrite';
        }
        
        // If only one works, no fallback available
        if ($wp_success && !$file_success) {
            return 'none_available';
        }
        
        if ($file_success && !$wp_success) {
            return 'none_available';
        }
        
        // If neither works
        return 'manual_intervention_required';
    }
    
    /**
     * **NEW: Convert URL path to safe option key**
     */
    private function path_to_option_key($path) {
        // Convert path to safe option key by removing slashes and dots
        $key = str_replace(array('/', '.', '-'), '_', $path);
        $key = trim($key, '_');
        return $key;
    }
    
    /**
     * **NEW: Clean up strategy data during deactivation**
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
    
    /**
     * **NEW: Get ordered strategy preferences for each endpoint type**
     */
    private function get_endpoint_strategy_preferences() {
        return array(
            '/.well-known/ai-plugin.json' => array('physical_file', 'wordpress_rewrite'),
            '/.well-known/mcp/servers.json' => array('physical_file', 'wordpress_rewrite'),
            '/robots.txt' => array('physical_file', 'wordpress_rewrite'),
            '/llms.txt' => array('physical_file', 'wordpress_rewrite'),
            '/ask' => array('wordpress_rewrite', 'physical_file'),
            // Add more endpoint-specific preferences as needed
        );
    }
    
    /**
     * **NEW: Get preferred strategy order for a specific path**
     */
    private function get_preferred_strategies($path) {
        $preferences = $this->get_endpoint_strategy_preferences();
        
        // Return specific preferences if defined, otherwise use defaults
        return $preferences[$path] ?? array('wordpress_rewrite', 'physical_file');
    }
} 