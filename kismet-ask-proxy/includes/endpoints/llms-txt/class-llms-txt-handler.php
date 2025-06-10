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
class Kismet_LLMS_Txt_Handler {
    
    /**
     * Route tester instance
     * @var Kismet_Route_Tester
     */
    private $route_tester;
    
    /**
     * File safety manager instance
     * @var Kismet_File_Safety_Manager
     */
    private $file_safety_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load the route tester
        require_once(plugin_dir_path(__FILE__) . '../../shared/class-route-tester.php');
        require_once(plugin_dir_path(__FILE__) . '../../shared/class-file-safety-manager.php');
        $this->route_tester = new Kismet_Route_Tester();
        $this->file_safety_manager = new Kismet_File_Safety_Manager();
        
        // Hook into WordPress init to safely create LLMS.txt
        add_action('init', array($this, 'safe_llms_creation'));
    }

    /**
     * Safely create LLMS.txt using comprehensive testing
     * 
     * DECISION LOGIC:
     * 1. CHECK: Does /llms.txt already work? (is_route_active)
     * 2. CHECK: Does physical llms.txt file exist? (file_exists)
     * 3. CHECK: How does hosting serve llms.txt? (determine_serving_method)
     * 4. DECIDE: Create physical file OR use WordPress rewrite
     * 
     * IMPLEMENTATION APPROACHES:
     * - Physical File: nginx/apache serves llms.txt directly, we create file
     * - WordPress Rewrite: WordPress generates llms.txt, we use rewrite rules
     */
    public function safe_llms_creation() {
        error_log("KISMET: Starting LLMS.txt creation");
        
        $llms_file = ABSPATH . 'llms.txt';
        
        // Step 1: Quick check - is LLMS.txt already working?
        $route_accessible = $this->route_tester->is_route_active('/llms.txt');
        error_log("KISMET: LLMS.txt accessible: " . ($route_accessible ? 'YES' : 'NO'));
        
        // Step 2: Check if physical file exists
        $file_exists = file_exists($llms_file);
        error_log("KISMET: Physical llms.txt exists: " . ($file_exists ? 'YES' : 'NO'));
        
        // Step 3: If no route and no file, determine best approach via environment testing
        if (!$route_accessible && !$file_exists) {
            error_log("KISMET: No llms.txt found, testing environment capabilities...");
            $test_results = $this->route_tester->determine_serving_method('/llms.txt');
            $recommended_approach = $test_results['recommended_approach'] ?? 'wordpress_rewrite';
            error_log("KISMET: Environment recommends: " . $recommended_approach);
            
            if ($recommended_approach === 'physical_file') {
                // Environment supports direct file serving
                if ($this->try_physical_file_creation()) {
                    error_log("KISMET: LLMS.txt physical file creation successful");
                    return;
                }
            }
            // Use WordPress rewrite approach (recommended or fallback)
            $this->use_wordpress_rewrite_approach();
            error_log("KISMET: Using WordPress rewrite approach for LLMS.txt");
            return;
        }
        
        // Step 4: Route works but file might need creation - try physical file approach first
        if ($route_accessible && !$file_exists) {
            if ($this->try_physical_file_creation()) {
                error_log("KISMET: LLMS.txt physical file creation successful");
                return;
            }
        }
        
        // Step 5: If route works and file exists, we're done
        if ($route_accessible && $file_exists) {
            error_log("KISMET: LLMS.txt already working, no action needed");
            return;
        }
        
        // Step 6: Fallback to WordPress rewrite approach
        $this->use_wordpress_rewrite_approach();
        error_log("KISMET: Using WordPress rewrite approach for LLMS.txt");
    }
    
    /**
     * Try creating physical LLMS.txt file
     */
    private function try_physical_file_creation() {
        try {
            $llms_file = ABSPATH . 'llms.txt';
            
            // Check if our content is already there
            if (file_exists($llms_file)) {
                $existing_content = file_get_contents($llms_file);
                if (strpos($existing_content, '# LLMS.txt - AI Policy') !== false) {
                    error_log("KISMET: LLMS.txt already contains our content");
                    return true;
                }
            }
            
            // Create new LLMS.txt with our content
            $content = $this->get_llms_txt_content();
            
            if ($this->file_safety_manager->safe_file_create($llms_file, $content)) {
                // Test if the file is accessible
                if ($this->route_tester->is_route_active('/llms.txt')) {
                    return true;
                }
                error_log("KISMET: LLMS.txt file created but not accessible via HTTP");
            }
            
            return false;
        } catch (Exception $e) {
            error_log("KISMET ERROR: LLMS.txt physical file creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Use WordPress rewrite approach for LLMS.txt
     */
    private function use_wordpress_rewrite_approach() {
        // Add rewrite rule for /llms.txt
        add_rewrite_rule('^llms\.txt$', 'index.php?kismet_llms_txt=1', 'top');
        
        // Add query var
        add_filter('query_vars', array($this, 'add_llms_query_var'));
        
        // Add template redirect handler
        add_action('template_redirect', array($this, 'handle_llms_request'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
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
            // Track this request using the reusable helper
            Kismet_Endpoint_Tracking_Helper::track_standard_endpoint('/llms.txt', array('source' => 'single_route_llms_individual_endpoint_strategy'));
            
            header('Content-Type: text/plain');
            echo $this->get_llms_txt_content();
            exit;
        }
    }
    



} 