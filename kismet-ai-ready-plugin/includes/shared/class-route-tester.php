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
    
    public function __construct() {
        error_log('KISMET ROUTE TESTER: Constructor called');
    }
    
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
        error_log("KISMET ROUTE TESTER: ===== STARTING ROUTE TEST =====");
        error_log("KISMET ROUTE TESTER: Original path: " . $path);
        error_log("KISMET ROUTE TESTER: Test content length: " . strlen($test_content) . " bytes");
        
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
        error_log("KISMET ROUTE TESTER: Generated test path: " . $test_path);
        
        try {
            // Test WordPress rewrite approach
            error_log("KISMET ROUTE TESTER: ----- TESTING WORDPRESS REWRITE -----");
            $results['wordpress_rewrite_test'] = $this->test_wordpress_rewrite($test_path, $test_content);
            error_log("KISMET ROUTE TESTER: WordPress rewrite test completed. Success: " . ($results['wordpress_rewrite_test']['success'] ? 'YES' : 'NO'));
            
            // Test physical file approach
            error_log("KISMET ROUTE TESTER: ----- TESTING PHYSICAL FILE -----");
            $results['physical_file_test'] = $this->test_physical_file($test_path, $test_content);
            error_log("KISMET ROUTE TESTER: Physical file test completed. Success: " . ($results['physical_file_test']['success'] ? 'YES' : 'NO'));
            
            // Determine best approach and overall success
            error_log("KISMET ROUTE TESTER: ----- ANALYZING RESULTS -----");
            $this->analyze_test_results($results);
            error_log("KISMET ROUTE TESTER: Analysis complete. Recommended approach: " . ($results['recommended_approach'] ?? 'NONE'));
            
        } catch (Exception $e) {
            $error_msg = 'Exception during testing: ' . $e->getMessage();
            $results['errors'][] = $error_msg;
            error_log("KISMET ROUTE TESTER ERROR: " . $error_msg);
        }
        
        // Clean up any test artifacts
        error_log("KISMET ROUTE TESTER: ----- CLEANING UP TEST ARTIFACTS -----");
        $this->cleanup_test_artifacts($test_path);
        
        error_log("KISMET ROUTE TESTER: ===== ROUTE TEST COMPLETE =====");
        error_log("KISMET ROUTE TESTER: Final result - Can proceed: " . ($results['can_proceed'] ? 'YES' : 'NO') . ", Approach: " . ($results['recommended_approach'] ?? 'NONE'));
        
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
        
        // Determine if this is a local development environment
        $is_local_dev = (
            strpos($test_url, 'localhost') !== false ||
            strpos($test_url, '.local') !== false ||
            strpos($test_url, '127.0.0.1') !== false ||
            strpos($test_url, '::1') !== false
        );
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => !$is_local_dev, // Disable SSL verification for local dev
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
        $random = rand(1000, 9999);
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
        error_log("KISMET ROUTE TESTER: WordPress rewrite test starting for: " . $test_path);
        
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
            error_log("KISMET ROUTE TESTER: Adding temporary rewrite rule...");
            $rule_added = $this->add_temporary_rewrite_rule($test_path, $test_content);
            $results['rewrite_added'] = $rule_added;
            error_log("KISMET ROUTE TESTER: Rewrite rule added: " . ($rule_added ? 'YES' : 'NO'));
            
            if ($rule_added) {
                // Flush rewrite rules to make it active
                error_log("KISMET ROUTE TESTER: Flushing rewrite rules to activate...");
                flush_rewrite_rules();
                error_log("KISMET ROUTE TESTER: Rewrite rules flushed");
                
                // Test HTTP accessibility
                error_log("KISMET ROUTE TESTER: Testing HTTP accessibility via WordPress rewrite...");
                $http_test = $this->test_http_access($test_path);
                $results = array_merge($results, $http_test);
                $results['success'] = $http_test['http_accessible'] && 
                                    $http_test['response_code'] === 200 &&
                                    $http_test['served_by_wordpress'];
                
                error_log("KISMET ROUTE TESTER: WordPress rewrite HTTP test results:");
                error_log("KISMET ROUTE TESTER:   - HTTP accessible: " . ($http_test['http_accessible'] ? 'YES' : 'NO'));
                error_log("KISMET ROUTE TESTER:   - Response code: " . ($http_test['response_code'] ?? 'NULL'));
                error_log("KISMET ROUTE TESTER:   - Served by WordPress: " . ($http_test['served_by_wordpress'] ? 'YES' : 'NO'));
                error_log("KISMET ROUTE TESTER:   - Response time: " . ($http_test['response_time'] ?? 'NULL') . "ms");
                error_log("KISMET ROUTE TESTER:   - Final success: " . ($results['success'] ? 'YES' : 'NO'));
            } else {
                error_log("KISMET ROUTE TESTER: Skipping HTTP test - rewrite rule not added");
            }
            
        } catch (Exception $e) {
            $error_msg = 'WordPress rewrite test failed: ' . $e->getMessage();
            $results['error'] = $error_msg;
            error_log("KISMET ROUTE TESTER ERROR: " . $error_msg);
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
        error_log("KISMET ROUTE TESTER: Physical file test starting for: " . $test_path);
        
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
            error_log("KISMET ROUTE TESTER: Target file path: " . $file_path);
            
            // Check if we can create the directory structure
            $dir_path = dirname($file_path);
            error_log("KISMET ROUTE TESTER: Target directory: " . $dir_path);
            
            if (!file_exists($dir_path)) {
                error_log("KISMET ROUTE TESTER: Directory doesn't exist, creating...");
                $dir_created = wp_mkdir_p($dir_path);
                if (!$dir_created) {
                    $error_msg = 'Cannot create directory: ' . $dir_path;
                    $results['error'] = $error_msg;
                    error_log("KISMET ROUTE TESTER ERROR: " . $error_msg);
                    return $results;
                }
                error_log("KISMET ROUTE TESTER: Directory created successfully");
            } else {
                error_log("KISMET ROUTE TESTER: Directory already exists");
            }
            
            // Check if target file already exists (safety check)
            if (file_exists($file_path)) {
                $error_msg = 'Test file already exists: ' . $file_path;
                $results['error'] = $error_msg;
                error_log("KISMET ROUTE TESTER ERROR: " . $error_msg);
                return $results;
            }
            
            // Create test file
            error_log("KISMET ROUTE TESTER: Creating test file with " . strlen($test_content) . " bytes...");
            $file_written = file_put_contents($file_path, $test_content);
            $results['file_created'] = ($file_written !== false);
            $results['file_writable'] = is_writable($file_path);
            
            error_log("KISMET ROUTE TESTER: File creation results:");
            error_log("KISMET ROUTE TESTER:   - Bytes written: " . ($file_written !== false ? $file_written : 'FAILED'));
            error_log("KISMET ROUTE TESTER:   - File created: " . ($results['file_created'] ? 'YES' : 'NO'));
            error_log("KISMET ROUTE TESTER:   - File writable: " . ($results['file_writable'] ? 'YES' : 'NO'));
            
            if ($results['file_created']) {
                // Test HTTP accessibility
                error_log("KISMET ROUTE TESTER: Testing HTTP accessibility via physical file...");
                $http_test = $this->test_http_access($test_path);
                $results = array_merge($results, $http_test);
                $results['success'] = $http_test['http_accessible'] && 
                                    $http_test['response_code'] === 200 &&
                                    $http_test['served_by_webserver'];
                
                error_log("KISMET ROUTE TESTER: Physical file HTTP test results:");
                error_log("KISMET ROUTE TESTER:   - HTTP accessible: " . ($http_test['http_accessible'] ? 'YES' : 'NO'));
                error_log("KISMET ROUTE TESTER:   - Response code: " . ($http_test['response_code'] ?? 'NULL'));
                error_log("KISMET ROUTE TESTER:   - Served by webserver: " . ($http_test['served_by_webserver'] ? 'YES' : 'NO'));
                error_log("KISMET ROUTE TESTER:   - Served by WordPress: " . ($http_test['served_by_wordpress'] ? 'YES' : 'NO'));
                error_log("KISMET ROUTE TESTER:   - Response time: " . ($http_test['response_time'] ?? 'NULL') . "ms");
                error_log("KISMET ROUTE TESTER:   - Final success: " . ($results['success'] ? 'YES' : 'NO'));
            } else {
                error_log("KISMET ROUTE TESTER: Skipping HTTP test - file not created");
            }
            
        } catch (Exception $e) {
            $error_msg = 'Physical file test failed: ' . $e->getMessage();
            $results['error'] = $error_msg;
            error_log("KISMET ROUTE TESTER ERROR: " . $error_msg);
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
        $site_url = get_site_url();
        $test_url = $site_url . $test_path;
        
        error_log("KISMET ROUTE TESTER: HTTP access test starting");
        error_log("KISMET ROUTE TESTER: Site URL: " . $site_url);
        error_log("KISMET ROUTE TESTER: Test URL: " . $test_url);
        
        $results = array(
            'http_accessible' => false,
            'response_code' => null,
            'response_content' => null,
            'response_time' => null,
            'served_by_webserver' => false,
            'served_by_wordpress' => false,
            'error' => null
        );
        
        $start_time = microtime(true);
        
        error_log("KISMET ROUTE TESTER: Making HTTP request...");
        
        // Determine if this is a local development environment
        $is_local_dev = (
            strpos($test_url, 'localhost') !== false ||
            strpos($test_url, '.local') !== false ||
            strpos($test_url, '127.0.0.1') !== false ||
            strpos($test_url, '::1') !== false
        );
        
        $request_args = array(
            'timeout' => 10,
            'sslverify' => !$is_local_dev, // Disable SSL verification for local dev
            'user-agent' => 'Kismet Route Tester/1.0'
        );
        
        if ($is_local_dev) {
            error_log("KISMET ROUTE TESTER: Local development environment detected - disabling SSL verification");
        }
        
        $response = wp_remote_get($test_url, $request_args);
        
        $results['response_time'] = round((microtime(true) - $start_time) * 1000, 2); // ms
        error_log("KISMET ROUTE TESTER: HTTP request completed in " . $results['response_time'] . "ms");
        
        if (is_wp_error($response)) {
            $results['error'] = $response->get_error_message();
            error_log("KISMET ROUTE TESTER: HTTP request failed: " . $results['error']);
        } else {
            $results['response_code'] = wp_remote_retrieve_response_code($response);
            $results['response_content'] = wp_remote_retrieve_body($response);
            $results['http_accessible'] = ($results['response_code'] === 200);
            
            error_log("KISMET ROUTE TESTER: HTTP response received:");
            error_log("KISMET ROUTE TESTER:   - Response code: " . $results['response_code']);
            error_log("KISMET ROUTE TESTER:   - Content length: " . strlen($results['response_content']) . " bytes");
            error_log("KISMET ROUTE TESTER:   - HTTP accessible: " . ($results['http_accessible'] ? 'YES' : 'NO'));
            
            // Check headers to determine what served the content
            $headers = wp_remote_retrieve_headers($response);
            error_log("KISMET ROUTE TESTER: Analyzing response headers...");
            
            // Log key headers for debugging
            $key_headers = array('server', 'x-powered-by', 'x-pingback', 'content-type');
            foreach ($key_headers as $header) {
                if (isset($headers[$header])) {
                    error_log("KISMET ROUTE TESTER:   - " . ucfirst($header) . ": " . $headers[$header]);
                }
            }
            
            // WordPress indicators
            $wordpress_indicators = array(
                isset($headers['x-powered-by']) && strpos($headers['x-powered-by'], 'PHP') !== false,
                isset($headers['x-pingback']),
                strpos($results['response_content'], 'wp-') !== false,
                strpos($results['response_content'], '<html') !== false && strpos($results['response_content'], 'wordpress') !== false
            );
            
            $results['served_by_wordpress'] = in_array(true, $wordpress_indicators);
            $results['served_by_webserver'] = $results['http_accessible'] && !$results['served_by_wordpress'];
            
            error_log("KISMET ROUTE TESTER: Server detection results:");
            error_log("KISMET ROUTE TESTER:   - WordPress indicators found: " . array_sum($wordpress_indicators) . "/4");
            error_log("KISMET ROUTE TESTER:   - Served by WordPress: " . ($results['served_by_wordpress'] ? 'YES' : 'NO'));
            error_log("KISMET ROUTE TESTER:   - Served by webserver: " . ($results['served_by_webserver'] ? 'YES' : 'NO'));
            
            // Log first 100 chars of content for debugging
            $content_preview = substr($results['response_content'], 0, 100);
            if (strlen($results['response_content']) > 100) {
                $content_preview .= '...';
            }
            error_log("KISMET ROUTE TESTER:   - Content preview: " . $content_preview);
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
        
        error_log("KISMET ROUTE TESTER: Analyzing test results...");
        error_log("KISMET ROUTE TESTER:   - WordPress rewrite success: " . ($wp_success ? 'YES' : 'NO'));
        error_log("KISMET ROUTE TESTER:   - Physical file success: " . ($file_success ? 'YES' : 'NO'));
        
        // Determine overall success and recommended approach
        if ($wp_success && $file_success) {
            $results['overall_success'] = true;
            $results['can_proceed'] = true;
            // Prefer WordPress rewrite for better integration
            $results['recommended_approach'] = 'wordpress_rewrite';
            $warning_msg = 'Both approaches work - using WordPress rewrite for better integration';
            $results['warnings'][] = $warning_msg;
            error_log("KISMET ROUTE TESTER: BOTH APPROACHES WORK - choosing WordPress rewrite");
            error_log("KISMET ROUTE TESTER: " . $warning_msg);
            
        } elseif ($wp_success) {
            $results['overall_success'] = true;
            $results['can_proceed'] = true;
            $results['recommended_approach'] = 'wordpress_rewrite';
            error_log("KISMET ROUTE TESTER: ONLY WordPress rewrite works - using it");
            
        } elseif ($file_success) {
            $results['overall_success'] = true;
            $results['can_proceed'] = true;
            $results['recommended_approach'] = 'physical_file';
            $warning_msg = 'WordPress rewrite failed - using physical file fallback';
            $results['warnings'][] = $warning_msg;
            error_log("KISMET ROUTE TESTER: ONLY physical file works - using it");
            error_log("KISMET ROUTE TESTER: " . $warning_msg);
            
        } else {
            $results['overall_success'] = false;
            $results['can_proceed'] = false;
            $results['recommended_approach'] = null;
            $error_msg = 'Both WordPress rewrite and physical file approaches failed';
            $results['errors'][] = $error_msg;
            error_log("KISMET ROUTE TESTER: BOTH APPROACHES FAILED");
            error_log("KISMET ROUTE TESTER ERROR: " . $error_msg);
            
            // Add specific error details
            if (isset($results['wordpress_rewrite_test']['error'])) {
                $wp_error = 'WordPress rewrite: ' . $results['wordpress_rewrite_test']['error'];
                $results['errors'][] = $wp_error;
                error_log("KISMET ROUTE TESTER ERROR: " . $wp_error);
            }
            if (isset($results['physical_file_test']['error'])) {
                $file_error = 'Physical file: ' . $results['physical_file_test']['error'];
                $results['errors'][] = $file_error;
                error_log("KISMET ROUTE TESTER ERROR: " . $file_error);
            }
        }
        
        error_log("KISMET ROUTE TESTER: Analysis complete:");
        error_log("KISMET ROUTE TESTER:   - Overall success: " . ($results['overall_success'] ? 'YES' : 'NO'));
        error_log("KISMET ROUTE TESTER:   - Can proceed: " . ($results['can_proceed'] ? 'YES' : 'NO'));
        error_log("KISMET ROUTE TESTER:   - Recommended approach: " . ($results['recommended_approach'] ?? 'NONE'));
        error_log("KISMET ROUTE TESTER:   - Warnings: " . count($results['warnings']));
        error_log("KISMET ROUTE TESTER:   - Errors: " . count($results['errors']));
    }
    
    /**
     * Clean up test artifacts
     * 
     * @param string $test_path Test path used
     */
    private function cleanup_test_artifacts($test_path) {
        error_log("KISMET ROUTE TESTER: Cleaning up test artifacts for: " . $test_path);
        
        // Clean up transients
        $transient_key = 'kismet_test_content_' . md5($test_path);
        $transient_deleted = delete_transient($transient_key);
        error_log("KISMET ROUTE TESTER: Transient cleanup (" . $transient_key . "): " . ($transient_deleted ? 'DELETED' : 'NOT_FOUND'));
        
        // Clean up physical test file if it exists
        $file_path = $this->get_physical_file_path($test_path);
        if (file_exists($file_path)) {
            error_log("KISMET ROUTE TESTER: Removing test file: " . $file_path);
            $file_deleted = @unlink($file_path);
            error_log("KISMET ROUTE TESTER: Test file removal: " . ($file_deleted ? 'SUCCESS' : 'FAILED'));
            
            // Try to remove empty directories
            $dir_path = dirname($file_path);
            if ($dir_path !== ABSPATH && is_dir($dir_path)) {
                error_log("KISMET ROUTE TESTER: Attempting to remove empty directory: " . $dir_path);
                $dir_removed = @rmdir($dir_path);
                error_log("KISMET ROUTE TESTER: Directory removal: " . ($dir_removed ? 'SUCCESS' : 'FAILED_OR_NOT_EMPTY'));
            }
        } else {
            error_log("KISMET ROUTE TESTER: No test file to clean up at: " . $file_path);
        }
        
        // Flush rewrite rules to remove test rules
        error_log("KISMET ROUTE TESTER: Flushing rewrite rules to remove test rules...");
        flush_rewrite_rules();
        error_log("KISMET ROUTE TESTER: Rewrite rules flushed");
        
        error_log("KISMET ROUTE TESTER: Cleanup complete");
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