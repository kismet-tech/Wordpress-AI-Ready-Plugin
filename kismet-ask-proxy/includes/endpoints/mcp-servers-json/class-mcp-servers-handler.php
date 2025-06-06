<?php
/**
 * Kismet MCP Servers Handler - Safe Version
 * 
 * This is the bulletproof implementation that performs comprehensive testing
 * before creating the /.well-known/mcp/servers.json endpoint.
 * 
 * Safety Features:
 * - Tests route accessibility before implementation
 * - Uses File Safety Manager for conflict-free file operations
 * - Chooses optimal approach (file vs rewrite) based on environment
 * - Comprehensive error handling and logging
 */

// Load safety utilities
require_once(plugin_dir_path(__FILE__) . '../../shared/class-route-tester.php');
require_once(plugin_dir_path(__FILE__) . '../../shared/class-file-safety-manager.php');

class Kismet_MCP_Servers_Handler {

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
        $this->route_tester = new Kismet_Route_Tester();
        $this->file_safety_manager = new Kismet_File_Safety_Manager();
        
        // Hook into WordPress init to safely create endpoint
        add_action('init', array($this, 'safe_endpoint_creation'));
    }

    /**
     * Safely create the MCP servers endpoint using comprehensive testing
     * 
     * DECISION LOGIC:
     * 1. CHECK: Does the route already work? (is_route_active)
     * 2. CHECK: Does the physical file exist? (file_exists) 
     * 3. CHECK: How does the hosting environment serve files? (determine_serving_method)
     * 4. DECIDE: Use physical file OR WordPress rewrite based on environment capabilities
     * 
     * IMPLEMENTATION APPROACHES:
     * - Physical File: nginx/apache serves .json files directly from filesystem
     * - WordPress Rewrite: PHP intercepts requests and serves dynamic content
     */
    public function safe_endpoint_creation() {
        $target_url = '/.well-known/mcp/servers.json';
        $file_path = ABSPATH . '.well-known/mcp/servers.json';
        
        error_log("KISMET SAFE: Starting MCP servers endpoint creation for: " . $target_url);
        
        // Step 1: Quick check - is route already working?
        if ($this->route_tester->is_route_active($target_url)) {
            error_log("KISMET SAFE: MCP servers route already accessible, skipping creation");
            return;
        }
        
        // Step 2: Check if physical file exists (might exist but not be served correctly)
        $file_exists = file_exists($file_path);
        error_log("KISMET SAFE: Physical file exists: " . ($file_exists ? 'YES' : 'NO'));
        
        // Step 3: Test environment capabilities to determine best approach
        error_log("KISMET SAFE: Testing environment capabilities...");
        $test_results = $this->route_tester->determine_serving_method($target_url);
        $recommended_approach = $test_results['recommended_approach'] ?? 'wordpress_rewrite';
        error_log("KISMET SAFE: Environment recommends: " . $recommended_approach);
        
        // Step 4: Implement using recommended approach first, then fallback
        if ($recommended_approach === 'physical_file') {
            // Environment supports direct file serving
            if ($this->try_physical_file_approach($file_path)) {
                error_log("KISMET SAFE: MCP servers physical file approach successful");
                return;
            }
            // Fallback to WordPress rewrite
            if ($this->try_wordpress_rewrite_approach()) {
                error_log("KISMET SAFE: MCP servers WordPress rewrite fallback successful");
                return;
            }
        } else {
            // Environment prefers WordPress rewrite (or physical files failed in testing)
            if ($this->try_wordpress_rewrite_approach()) {
                error_log("KISMET SAFE: MCP servers WordPress rewrite approach successful");
                return;
            }
            // Fallback to physical file
            if ($this->try_physical_file_approach($file_path)) {
                error_log("KISMET SAFE: MCP servers physical file fallback successful");
                return;
            }
        }
        
        error_log("KISMET SAFE ERROR: All MCP servers endpoint creation methods failed");
        error_log("KISMET SAFE DEBUG: Test results: " . json_encode($test_results, JSON_PRETTY_PRINT));
    }
    
    /**
     * Try creating physical file approach
     */
    private function try_physical_file_approach($file_path) {
        try {
            // Use file safety manager for conflict-free creation
            $content = $this->generate_mcp_servers_json();
            
            if ($this->file_safety_manager->safe_file_create($file_path, $content)) {
                // Test if the file is actually accessible
                if ($this->route_tester->is_route_active('/.well-known/mcp/servers.json')) {
                    return true;
                }
                error_log("KISMET SAFE: MCP servers file created but not accessible via HTTP");
            }
            return false;
        } catch (Exception $e) {
            error_log("KISMET SAFE ERROR: MCP servers physical file creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Try WordPress rewrite rule approach
     */
    private function try_wordpress_rewrite_approach() {
        try {
            // Add rewrite rule for MCP servers
            add_rewrite_rule('\.well-known/mcp/servers\.json/?$', 'index.php?kismet_mcp_servers=1', 'top');
            
            // Add query var
            add_filter('query_vars', function($vars) {
                $vars[] = 'kismet_mcp_servers';
                return $vars;
            });
            
            // Handle the request with multiple interceptors for reliability
            add_action('parse_request', array($this, 'intercept_mcp_servers_request'));
            add_action('template_redirect', array($this, 'handle_mcp_servers_request'));
            
            // Flush rewrite rules to activate
            flush_rewrite_rules();
            
            // Test if rewrite is working
            if ($this->route_tester->is_route_active('/.well-known/mcp/servers.json')) {
                return true;
            }
            
            error_log("KISMET SAFE: MCP servers WordPress rewrite rule added but not accessible");
            return false;
        } catch (Exception $e) {
            error_log("KISMET SAFE ERROR: MCP servers WordPress rewrite approach failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Intercept MCP servers request early (parse_request level)
     */
    public function intercept_mcp_servers_request($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (preg_match('#^/\.well-known/mcp/servers\.json/?(\?.*)?$#', $request_uri)) {
            $this->serve_mcp_servers_content();
            exit;
        }
    }
    
    /**
     * Handle MCP servers request via WordPress (template_redirect level)
     */
    public function handle_mcp_servers_request() {
        if (get_query_var('kismet_mcp_servers')) {
            $this->serve_mcp_servers_content();
            exit;
        }
    }
    
    /**
     * Serve MCP servers content (common method for both interceptors)
     */
    private function serve_mcp_servers_content() {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        echo $this->generate_mcp_servers_json();
    }
    
    /**
     * Generate MCP servers JSON content
     */
    private function generate_mcp_servers_json() {
        $servers_data = array(
            "schema_version" => "1.0",
            "last_updated" => current_time('c'),
            "publisher" => array(
                "name" => get_bloginfo('name'),
                "url" => get_site_url(),
                "contact_email" => get_option('admin_email')
            ),
            "servers" => array(
                array(
                    "name" => "Kismet Hotel Assistant",
                    "description" => "Hotel information and booking assistance",
                    "url" => get_site_url() . "/ask",
                    "type" => "hotel_assistant",
                    "version" => "1.0",
                    "capabilities" => array(
                        "room_availability",
                        "pricing_information", 
                        "amenities_information",
                        "booking_assistance",
                        "general_inquiries"
                    ),
                    "authentication" => array("type" => "none"),
                    "trusted" => true
                )
            ),
            "metadata" => array(
                "total_servers" => 1,
                "generated_by" => "Kismet WordPress Plugin"
            )
        );
        
        return json_encode($servers_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
} 