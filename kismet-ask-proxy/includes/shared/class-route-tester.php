<?php
/**
 * Kismet Route Tester - Bulletproof route testing before any deployment
 *
 * This utility class tests if routes will work BEFORE creating any rewrite rules
 * or files. Should be used by all handlers as a prerequisite check.
 *
 * Route Tester for Kismet Ask Proxy Plugin
 * 
 * This class provides multiple approaches for testing whether WordPress can serve
 * content at specific URL paths. It's crucial for determining the best strategy
 * for serving files like /.well-known/ai-plugin.json or /robots.txt.
 * 
 * TESTING APPROACHES EXPLAINED:
 * 
 * 1. ENVIRONMENT CAPABILITY TESTING (test_route method):
 *    - Creates TEMPORARY test paths with unique suffixes (e.g., "servers-test-123456.json")
 *    - Tests both WordPress rewrite rules AND physical file creation
 *    - Determines if nginx/apache can serve files directly or if WordPress rewrite is needed
 *    - Use this when: Setting up a new endpoint and need to know HOW to implement it
 *    - Benefits: Tells you which approach works in your hosting environment
 * 
 * 2. EXISTING ROUTE CHECK (is_route_active method):
 *    - Tests the ACTUAL path you want to check (e.g., "servers.json")
 *    - Simple HTTP GET request to see if path returns 200 status
 *    - No temporary files or artifacts created
 *    - Use this when: Checking if an existing endpoint is already working
 *    - Benefits: Fast, accurate check - "should I set up this route or skip it?"
 * 
 * WHY THE FALSE POSITIVE PROBLEM EXISTED:
 * - Safe handlers called is_route_active() before setting up endpoints
 * - Old version used comprehensive testing with temporary paths
 * - Test would pass (temporary path worked) but real path still returned 404
 * - Handler thought endpoint was working, skipped setup, real route stayed broken
 * 
 * WHAT EACH METHOD CHECKS:
 * 
 * determine_serving_method() - ENVIRONMENT CAPABILITY TESTING:
 * ✓ Does hosting serve .json/.txt files directly? (nginx/apache)
 * ✓ Do WordPress rewrite rules work?
 * ✓ Which approach is faster/more reliable?
 * ✓ Returns: recommendation for implementation approach
 * 
 * is_route_active() - EXISTING ROUTE VERIFICATION:
 * ✓ Does the specific URL return HTTP 200?
 * ✓ Is content already being served?
 * ✓ Should we skip setup (route already works)?
 * ✓ Returns: boolean true/false
 * 
 * WHEN TO USE EACH METHOD:
 * - determine_serving_method(): Determine HOW to implement a route (file vs rewrite)
 * - is_route_active(): Check if route already works (skip setup)
 * 
 * @package Kismet_Ask_Proxy
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Route Testing Utility for bulletproof WordPress plugin deployment
 * 
 * Call this BEFORE making any rewrite rules or files to ensure they'll work
 */
class Kismet_Route_Tester {
    
    /**
     * DETERMINE SERVING METHOD: Test if hosting supports nginx/apache vs WordPress rewrite
     * 
     * This method creates TEMPORARY test paths (with unique suffixes) to avoid conflicts
     * with existing routes. It tests both WordPress rewrite rules and physical file
     * creation to determine which approach works in your hosting environment.
     * 
     * EXAMPLE: If you want to test /.well-known/mcp/servers.json
     * - Creates temporary path: /.well-known/mcp/servers-kismet-test-1734718234-4567.json
     * - Tests WordPress rewrite approach with temporary path
     * - Tests physical file creation with temporary path (nginx/apache serves directly)
     * - Returns recommendation: "use physical files" or "use WordPress rewrite"
     * 
     * Use this when: Setting up a new endpoint and need to know HOW to implement it
     * Don't use this when: Just checking if an existing endpoint works (use is_route_active)
     * 
     * @param string $path The path to test (e.g., '/llms.txt', '/.well-known/ai-plugin.json')
     * @param string $test_content Content to use for testing (optional)
     * @param array $options Additional testing options
     * @return array Comprehensive test results with recommendations
     */
    public function determine_serving_method($path, $test_content = 'test', $options = array()) {
        $results = array(
            'path' => $path,
            'timestamp' => current_time('mysql'),
            'can_proceed' => false,
            'recommended_approach' => null,
            'wordpress_rewrite_test' => array(),
            'physical_file_test' => array(),
            'overall_success' => false,
            'warnings' => array(),
            'errors' => array()
        );
        
        // Generate unique test path to avoid conflicts
        $test_path = $this->generate_test_path($path);
        
        try {
            // Test WordPress rewrite approach
            $results['wordpress_rewrite_test'] = $this->test_wordpress_rewrite($test_path, $test_content);
            
            // Test physical file approach
            $results['physical_file_test'] = $this->test_physical_file($test_path, $test_content);
            
            // Determine best approach and overall success
            $this->analyze_test_results($results);
            
        } catch (Exception $e) {
            $results['errors'][] = 'Exception during testing: ' . $e->getMessage();
        }
        
        // Clean up any test artifacts
        $this->cleanup_test_artifacts($test_path);
        
        return $results;
    }
    
    /**
     * Test specific well-known route (convenience method)
     * 
     * @param string $filename Filename in .well-known directory
     * @param string $test_content Content for testing
     * @return array Test results
     */
    public function test_well_known_route($filename, $test_content = 'test') {
        return $this->determine_serving_method('/.well-known/' . $filename, $test_content);
    }
    
    /**
     * Test specific root-level route (convenience method)
     * 
     * @param string $filename Filename at document root
     * @param string $test_content Content for testing
     * @return array Test results
     */
    public function test_root_route($filename, $test_content = 'test') {
        return $this->determine_serving_method('/' . $filename, $test_content);
    }
    
    /**
     * IS ROUTE ACTIVE: Check if the route already works (tests the ACTUAL path)
     * 
     * This method tests if the real path is accessible by making an HTTP request
     * to the actual URL. Unlike determine_serving_method(), this doesn't create temporary paths
     * or test artifacts - it just checks if the route you want actually works.
     * 
     * @param string $path The actual path to test (e.g., '/.well-known/mcp/servers.json')
     * @return bool True if route is accessible and returns 200, false otherwise
     */
    public function is_route_active($path) {
        $site_url = get_site_url();
        $test_url = $site_url . $path;
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'Kismet Route Tester/1.0'
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return ($response_code === 200);
    }
    
    /**
     * Generate unique test path that won't conflict with real routes
     * 
     * @param string $original_path Original path to test
     * @return string Unique test path
     */
    private function generate_test_path($original_path) {
        $timestamp = time();
        $random = wp_rand(1000, 9999);
        $path_info = pathinfo($original_path);
        
        // Insert test marker before extension
        if (isset($path_info['extension'])) {
            $test_path = $path_info['dirname'] . '/' . 
                        $path_info['filename'] . 
                        '-kismet-test-' . $timestamp . '-' . $random . '.' . 
                        $path_info['extension'];
        } else {
            $test_path = $original_path . '-kismet-test-' . $timestamp . '-' . $random;
        }
        
        return $test_path;
    }
    
    /**
     * Test WordPress rewrite rule approach
     * 
     * @param string $test_path Test path to use
     * @param string $test_content Content for testing
     * @return array Test results
     */
    private function test_wordpress_rewrite($test_path, $test_content) {
        $results = array(
            'attempted' => true,
            'rewrite_added' => false,
            'http_accessible' => false,
            'response_code' => null,
            'response_content' => null,
            'success' => false,
            'error' => null
        );
        
        try {
            // Add temporary rewrite rule
            $rule_added = $this->add_temporary_rewrite_rule($test_path, $test_content);
            $results['rewrite_added'] = $rule_added;
            
            if ($rule_added) {
                // Flush rewrite rules to make it active
                flush_rewrite_rules();
                
                // Test HTTP accessibility
                $http_test = $this->test_http_access($test_path);
                $results = array_merge($results, $http_test);
                $results['success'] = $http_test['http_accessible'] && 
                                    $http_test['response_code'] === 200;
            }
            
        } catch (Exception $e) {
            $results['error'] = 'WordPress rewrite test failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Test physical file approach
     * 
     * @param string $test_path Test path to use
     * @param string $test_content Content for testing
     * @return array Test results
     */
    private function test_physical_file($test_path, $test_content) {
        $results = array(
            'attempted' => true,
            'file_created' => false,
            'file_writable' => false,
            'http_accessible' => false,
            'response_code' => null,
            'response_content' => null,
            'success' => false,
            'error' => null,
            'file_path' => null
        );
        
        try {
            // Determine physical file path
            $file_path = $this->get_physical_file_path($test_path);
            $results['file_path'] = $file_path;
            
            // Check if we can create the directory structure
            $dir_path = dirname($file_path);
            if (!file_exists($dir_path)) {
                $dir_created = wp_mkdir_p($dir_path);
                if (!$dir_created) {
                    $results['error'] = 'Cannot create directory: ' . $dir_path;
                    return $results;
                }
            }
            
            // Check if target file already exists (safety check)
            if (file_exists($file_path)) {
                $results['error'] = 'Test file already exists: ' . $file_path;
                return $results;
            }
            
            // Create test file
            $file_written = file_put_contents($file_path, $test_content);
            $results['file_created'] = ($file_written !== false);
            $results['file_writable'] = is_writable($file_path);
            
            if ($results['file_created']) {
                // Test HTTP accessibility
                $http_test = $this->test_http_access($test_path);
                $results = array_merge($results, $http_test);
                $results['success'] = $http_test['http_accessible'] && 
                                    $http_test['response_code'] === 200;
            }
            
        } catch (Exception $e) {
            $results['error'] = 'Physical file test failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Test HTTP accessibility of a path
     * 
     * @param string $test_path Path to test
     * @return array HTTP test results
     */
    private function test_http_access($test_path) {
        $results = array(
            'http_accessible' => false,
            'response_code' => null,
            'response_content' => null,
            'response_time' => null,
            'error' => null
        );
        
        $site_url = get_site_url();
        $test_url = $site_url . $test_path;
        
        $start_time = microtime(true);
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'Kismet Route Tester/1.0'
        ));
        
        $results['response_time'] = round((microtime(true) - $start_time) * 1000, 2); // ms
        
        if (is_wp_error($response)) {
            $results['error'] = $response->get_error_message();
        } else {
            $results['response_code'] = wp_remote_retrieve_response_code($response);
            $results['response_content'] = wp_remote_retrieve_body($response);
            $results['http_accessible'] = ($results['response_code'] === 200);
        }
        
        return $results;
    }
    
    /**
     * Add temporary WordPress rewrite rule for testing
     * 
     * @param string $test_path Test path
     * @param string $test_content Content to serve
     * @return bool Success
     */
    private function add_temporary_rewrite_rule($test_path, $test_content) {
        // Remove leading slash for rewrite rule
        $path_pattern = ltrim($test_path, '/');
        
        // Store test content in transient for the handler to use
        $transient_key = 'kismet_test_content_' . md5($test_path);
        set_transient($transient_key, $test_content, 300); // 5 minutes
        
        // Add rewrite rule
        add_rewrite_rule(
            '^' . preg_quote($path_pattern, '/') . '$',
            'index.php?kismet_test_route=' . urlencode($test_path),
            'top'
        );
        
        // Add query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'kismet_test_route';
            return $vars;
        });
        
        // Add template redirect handler
        add_action('template_redirect', array($this, 'handle_test_route_request'));
        
        return true;
    }
    
    /**
     * Handle test route requests
     */
    public function handle_test_route_request() {
        $test_route = get_query_var('kismet_test_route');
        
        if ($test_route) {
            $transient_key = 'kismet_test_content_' . md5($test_route);
            $content = get_transient($transient_key);
            
            if ($content !== false) {
                // Determine content type based on file extension
                $content_type = $this->get_content_type($test_route);
                
                header('Content-Type: ' . $content_type);
                echo $content;
                exit;
            }
        }
    }
    
    /**
     * Get physical file path for a URL path
     * 
     * @param string $url_path URL path
     * @return string Physical file path
     */
    private function get_physical_file_path($url_path) {
        // Remove leading slash and convert to file path
        $relative_path = ltrim($url_path, '/');
        return ABSPATH . $relative_path;
    }
    
    /**
     * Determine content type based on file extension
     * 
     * @param string $path File path
     * @return string Content type
     */
    private function get_content_type($path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $content_types = array(
            'json' => 'application/json',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'xml' => 'application/xml',
            'css' => 'text/css',
            'js' => 'application/javascript'
        );
        
        return isset($content_types[$extension]) ? $content_types[$extension] : 'text/plain';
    }
    
    /**
     * Analyze test results and determine recommendations
     * 
     * @param array &$results Results array to update
     */
    private function analyze_test_results(&$results) {
        $wp_success = $results['wordpress_rewrite_test']['success'] ?? false;
        $file_success = $results['physical_file_test']['success'] ?? false;
        
        // Determine overall success and recommended approach
        if ($wp_success && $file_success) {
            $results['overall_success'] = true;
            $results['can_proceed'] = true;
            // Prefer WordPress rewrite for better integration
            $results['recommended_approach'] = 'wordpress_rewrite';
            $results['warnings'][] = 'Both approaches work - using WordPress rewrite for better integration';
            
        } elseif ($wp_success) {
            $results['overall_success'] = true;
            $results['can_proceed'] = true;
            $results['recommended_approach'] = 'wordpress_rewrite';
            
        } elseif ($file_success) {
            $results['overall_success'] = true;
            $results['can_proceed'] = true;
            $results['recommended_approach'] = 'physical_file';
            $results['warnings'][] = 'WordPress rewrite failed - using physical file fallback';
            
        } else {
            $results['overall_success'] = false;
            $results['can_proceed'] = false;
            $results['recommended_approach'] = null;
            $results['errors'][] = 'Both WordPress rewrite and physical file approaches failed';
            
            // Add specific error details
            if (isset($results['wordpress_rewrite_test']['error'])) {
                $results['errors'][] = 'WordPress rewrite: ' . $results['wordpress_rewrite_test']['error'];
            }
            if (isset($results['physical_file_test']['error'])) {
                $results['errors'][] = 'Physical file: ' . $results['physical_file_test']['error'];
            }
        }
    }
    
    /**
     * Clean up test artifacts
     * 
     * @param string $test_path Test path used
     */
    private function cleanup_test_artifacts($test_path) {
        // Clean up transients
        $transient_key = 'kismet_test_content_' . md5($test_path);
        delete_transient($transient_key);
        
        // Clean up physical test file if it exists
        $file_path = $this->get_physical_file_path($test_path);
        if (file_exists($file_path)) {
            @unlink($file_path);
            
            // Try to remove empty directories
            $dir_path = dirname($file_path);
            if ($dir_path !== ABSPATH && is_dir($dir_path)) {
                @rmdir($dir_path);
            }
        }
        
        // Flush rewrite rules to remove test rules
        flush_rewrite_rules();
    }
    
    /**
     * Log test results for diagnostic purposes
     * 
     * @param array $results Test results
     * @param string $context Context for the test
     */
    public function log_test_results($results, $context = 'route_test') {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'context' => $context,
            'path' => $results['path'] ?? 'unknown',
            'success' => $results['overall_success'] ?? false,
            'recommended_approach' => $results['recommended_approach'] ?? null,
            'errors' => $results['errors'] ?? array(),
            'warnings' => $results['warnings'] ?? array()
        );
        
        // Store in wp_options for diagnostic access
        $existing_logs = get_option('kismet_route_test_logs', array());
        $existing_logs[] = $log_entry;
        
        // Keep only last 50 log entries
        $existing_logs = array_slice($existing_logs, -50);
        
        update_option('kismet_route_test_logs', $existing_logs);
    }
} 