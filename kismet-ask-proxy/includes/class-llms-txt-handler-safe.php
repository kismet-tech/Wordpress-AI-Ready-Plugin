<?php
/**
 * Kismet LLMS.txt Handler - Bulletproof Implementation
 *
 * This is an example of how handlers should use the route tester BEFORE
 * making any rewrite rules or files. This follows the bulletproof deployment pattern.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * LLMS.txt handler with bulletproof safety checks
 */
class Kismet_LLMS_Txt_Handler_Safe {
    
    /**
     * Route tester instance
     * @var Kismet_Route_Tester
     */
    private $route_tester;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load the route tester
        require_once(plugin_dir_path(__FILE__) . 'class-route-tester.php');
        $this->route_tester = new Kismet_Route_Tester();
    }
    
    /**
     * Safely deploy LLMS.txt endpoint using bulletproof testing
     * 
     * @return array Deployment results
     */
    public function deploy_safely() {
        $deployment_result = array(
            'success' => false,
            'approach_used' => null,
            'test_results' => array(),
            'errors' => array(),
            'warnings' => array()
        );
        
        // STEP 1: Test route BEFORE making any changes
        $test_content = $this->get_llms_txt_content();
        $test_results = $this->route_tester->test_root_route('llms.txt', $test_content);
        
        $deployment_result['test_results'] = $test_results;
        
        // Log the test results for diagnostics
        $this->route_tester->log_test_results($test_results, 'llms_txt_deployment');
        
        // STEP 2: Only proceed if testing indicates we can succeed
        if (!$test_results['can_proceed']) {
            $deployment_result['errors'][] = 'Route testing failed - cannot safely deploy LLMS.txt';
            $deployment_result['errors'] = array_merge(
                $deployment_result['errors'], 
                $test_results['errors'] ?? array()
            );
            return $deployment_result;
        }
        
        // STEP 3: Use the recommended approach from testing
        $approach = $test_results['recommended_approach'];
        $deployment_result['approach_used'] = $approach;
        
        try {
            if ($approach === 'wordpress_rewrite') {
                $success = $this->deploy_wordpress_rewrite();
            } elseif ($approach === 'physical_file') {
                $success = $this->deploy_physical_file();
            } else {
                throw new Exception('Unknown deployment approach: ' . $approach);
            }
            
            $deployment_result['success'] = $success;
            
            if ($success) {
                // Verify the actual deployment works
                $verification = $this->verify_deployment();
                if (!$verification['success']) {
                    $deployment_result['warnings'][] = 'Deployment completed but verification failed';
                    $deployment_result['warnings'] = array_merge(
                        $deployment_result['warnings'],
                        $verification['errors'] ?? array()
                    );
                }
            }
            
        } catch (Exception $e) {
            $deployment_result['errors'][] = 'Deployment failed: ' . $e->getMessage();
        }
        
        return $deployment_result;
    }
    
    /**
     * Deploy using WordPress rewrite rules
     * 
     * @return bool Success
     */
    private function deploy_wordpress_rewrite() {
        // Add rewrite rule for /llms.txt
        add_rewrite_rule('^llms\.txt$', 'index.php?kismet_llms_txt=1', 'top');
        
        // Add query var
        add_filter('query_vars', array($this, 'add_llms_query_var'));
        
        // Add template redirect handler
        add_action('template_redirect', array($this, 'handle_llms_request'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        return true;
    }
    
    /**
     * Deploy using physical file with bulletproof safety
     * 
     * @return bool Success
     */
    private function deploy_physical_file() {
        $file_path = ABSPATH . 'llms.txt';
        $content = $this->get_llms_txt_content();
        
        try {
            // Use file safety manager for bulletproof file creation
            $result = $this->file_safety_manager->safe_file_create(
                $file_path, 
                $content, 
                Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
            );
            
            if ($result['success']) {
                // Store metadata for cleanup tracking
                $this->store_file_metadata($file_path, $content);
                return true;
            } else {
                throw new Exception('File safety manager failed: ' . implode(', ', $result['errors']));
            }
            
        } catch (Exception $e) {
            throw new Exception('Physical file deployment failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get LLMS.txt content
     * 
     * @return string LLMS.txt content
     */
    private function get_llms_txt_content() {
        $site_url = get_site_url();
        return "# LLMS.txt - AI Policy for " . get_bloginfo('name') . "\n" .
               "# This file contains AI-related policies and endpoints for this site.\n" .
               "\n" .
               "MCP-SERVER: " . $site_url . "/.well-known/mcp/servers.json\n" .
               "\n" .
               "# Generated by Kismet Ask Proxy Plugin\n" .
               "# Last updated: " . current_time('mysql') . "\n";
    }
    
    /**
     * Add query var for LLMS.txt requests
     * 
     * @param array $vars Query vars
     * @return array Updated query vars
     */
    public function add_llms_query_var($vars) {
        $vars[] = 'kismet_llms_txt';
        return $vars;
    }
    
    /**
     * Handle LLMS.txt requests
     */
    public function handle_llms_request() {
        if (get_query_var('kismet_llms_txt')) {
            header('Content-Type: text/plain');
            echo $this->get_llms_txt_content();
            exit;
        }
    }
    
    /**
     * Verify that the deployment actually works
     * 
     * @return array Verification results
     */
    private function verify_deployment() {
        $site_url = get_site_url();
        $llms_url = $site_url . '/llms.txt';
        
        $response = wp_remote_get($llms_url, array(
            'timeout' => 10,
            'sslverify' => true
        ));
        
        $result = array(
            'success' => false,
            'response_code' => null,
            'content_valid' => false,
            'errors' => array()
        );
        
        if (is_wp_error($response)) {
            $result['errors'][] = 'HTTP request failed: ' . $response->get_error_message();
            return $result;
        }
        
        $result['response_code'] = wp_remote_retrieve_response_code($response);
        
        if ($result['response_code'] !== 200) {
            $result['errors'][] = 'HTTP response code: ' . $result['response_code'];
            return $result;
        }
        
        $content = wp_remote_retrieve_body($response);
        $result['content_valid'] = (strpos($content, 'MCP-SERVER:') !== false);
        
        if (!$result['content_valid']) {
            $result['errors'][] = 'Content does not contain expected MCP-SERVER directive';
            return $result;
        }
        
        $result['success'] = true;
        return $result;
    }
    
    /**
     * Store file metadata for cleanup tracking
     * 
     * @param string $file_path File path
     * @param string $content File content
     */
    private function store_file_metadata($file_path, $content) {
        $metadata = array(
            'file_path' => $file_path,
            'content_hash' => md5($content),
            'created_at' => current_time('mysql'),
            'created_by' => 'kismet-ask-proxy'
        );
        
        $existing_files = get_option('kismet_created_files', array());
        $existing_files[] = $metadata;
        
        update_option('kismet_created_files', $existing_files);
    }
    
    /**
     * Clean up deployed resources (for uninstall)
     * 
     * @return array Cleanup results
     */
    public function cleanup() {
        $results = array(
            'files_removed' => 0,
            'errors' => array()
        );
        
        // Get files we created
        $created_files = get_option('kismet_created_files', array());
        
        foreach ($created_files as $file_meta) {
            if (isset($file_meta['file_path']) && 
                basename($file_meta['file_path']) === 'llms.txt') {
                
                $file_path = $file_meta['file_path'];
                
                if (file_exists($file_path)) {
                    // Verify it's still our file
                    $current_content = file_get_contents($file_path);
                    $expected_hash = $file_meta['content_hash'] ?? '';
                    
                    if (md5($current_content) === $expected_hash) {
                        if (@unlink($file_path)) {
                            $results['files_removed']++;
                        } else {
                            $results['errors'][] = 'Failed to remove file: ' . $file_path;
                        }
                    } else {
                        $results['errors'][] = 'File content changed - not removing: ' . $file_path;
                    }
                }
            }
        }
        
        return $results;
    }


} 