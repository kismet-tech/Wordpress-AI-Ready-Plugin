<?php
/**
 * Kismet Robots Handler - Safe Version
 * 
 * This is the bulletproof implementation that performs comprehensive testing
 * before enhancing the /robots.txt file.
 * 
 * Safety Features:
 * - Tests route accessibility before implementation
 * - Uses File Safety Manager for conflict-free file operations
 * - Preserves existing robots.txt content
 * - Chooses optimal approach (file vs filter) based on environment
 * - Comprehensive error handling and logging
 */

// Load safety utilities
require_once(plugin_dir_path(__FILE__) . '../../shared/class-route-tester.php');
require_once(plugin_dir_path(__FILE__) . '../../shared/class-file-safety-manager.php');

class Kismet_Robots_Handler {

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
        
        // Hook into WordPress init to safely enhance robots.txt
        add_action('init', array($this, 'safe_robots_enhancement'));
    }

    /**
     * Safely enhance robots.txt using comprehensive testing
     * 
     * DECISION LOGIC:
     * 1. CHECK: Does /robots.txt already work? (is_route_active)
     * 2. CHECK: Does physical robots.txt file exist? (file_exists)
     * 3. CHECK: How does hosting serve robots.txt? (determine_serving_method)
     * 4. DECIDE: Enhance physical file OR use WordPress filter
     * 
     * IMPLEMENTATION APPROACHES:
     * - Physical File: nginx/apache serves robots.txt directly, we append/create file
     * - WordPress Filter: WordPress generates robots.txt, we hook into generation
     */
    public function safe_robots_enhancement() {
        error_log("KISMET SAFE: Starting robots.txt enhancement");
        
        $robots_file = ABSPATH . 'robots.txt';
        
        // Step 1: Quick check - is robots.txt already working?
        $route_accessible = $this->route_tester->is_route_active('/robots.txt');
        error_log("KISMET SAFE: robots.txt accessible: " . ($route_accessible ? 'YES' : 'NO'));
        
        // Step 2: Check if physical file exists
        $file_exists = file_exists($robots_file);
        error_log("KISMET SAFE: Physical robots.txt exists: " . ($file_exists ? 'YES' : 'NO'));
        
        // Step 3: If no route and no file, determine best approach via environment testing
        if (!$route_accessible && !$file_exists) {
            error_log("KISMET SAFE: No robots.txt found, testing environment capabilities...");
            $test_results = $this->route_tester->determine_serving_method('/robots.txt');
            $recommended_approach = $test_results['recommended_approach'] ?? 'wordpress_rewrite';
            error_log("KISMET SAFE: Environment recommends: " . $recommended_approach);
            
            if ($recommended_approach === 'physical_file') {
                // Environment supports direct file serving
                if ($this->try_physical_file_enhancement()) {
                    error_log("KISMET SAFE: robots.txt physical file creation successful");
                    return;
                }
            }
            // Use WordPress filter approach (recommended or fallback)
            $this->use_wordpress_filter_approach();
            error_log("KISMET SAFE: Using WordPress filter approach for robots.txt");
            return;
        }
        
        // Step 4: Route works but need to enhance - try physical file approach first
        if ($route_accessible) {
            if ($this->try_physical_file_enhancement()) {
                error_log("KISMET SAFE: robots.txt physical file enhancement successful");
                return;
            }
        }
        
        // Step 5: Fallback to WordPress filter approach
        $this->use_wordpress_filter_approach();
        error_log("KISMET SAFE: Using WordPress filter approach for robots.txt");
    }
    
    /**
     * Try enhancing existing robots.txt file
     */
    private function try_physical_file_enhancement() {
        try {
            $robots_file = ABSPATH . 'robots.txt';
            
            // Check if robots.txt already exists
            if (file_exists($robots_file)) {
                $existing_content = file_get_contents($robots_file);
                
                // Check if our content is already there
                if (strpos($existing_content, '# AI/LLM Discovery Section') !== false) {
                    error_log("KISMET SAFE: robots.txt already contains our AI section");
                    return true;
                }
                
                // Safely append our content
                $enhanced_content = $existing_content . "\n" . $this->get_ai_robots_section();
                
                if ($this->file_safety_manager->safe_file_create($robots_file, $enhanced_content)) {
                    // Test if the enhanced file is accessible
                    if ($this->route_tester->is_route_active('/robots.txt')) {
                        return true;
                    }
                    error_log("KISMET SAFE: robots.txt enhanced but not accessible via HTTP");
                }
            } else {
                // Create new robots.txt with our content + WordPress defaults
                $new_content = $this->get_default_wordpress_robots() . "\n" . $this->get_ai_robots_section();
                
                if ($this->file_safety_manager->safe_file_create($robots_file, $new_content)) {
                    if ($this->route_tester->is_route_active('/robots.txt')) {
                        return true;
                    }
                    error_log("KISMET SAFE: robots.txt created but not accessible via HTTP");
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("KISMET SAFE ERROR: robots.txt physical enhancement failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Use WordPress filter approach for robots.txt
     */
    private function use_wordpress_filter_approach() {
        // Hook into WordPress robots.txt generation
        add_filter('robots_txt', array($this, 'enhance_wordpress_robots'), 10, 2);
    }
    
    /**
     * Enhance WordPress-generated robots.txt
     */
    public function enhance_wordpress_robots($output, $public) {
        // Only enhance if site is public
        if ($public) {
            $output .= "\n" . $this->get_ai_robots_section();
        }
        return $output;
    }
    
    /**
     * Get AI/LLM discovery section for robots.txt
     */
    private function get_ai_robots_section() {
        $site_url = get_site_url();
        $current_date = current_time('Y-m-d');
        
        return "
# AI/LLM Discovery Section (Added by Kismet Plugin)
# Last updated: {$current_date}

# AI Endpoints
# AI Plugin Discovery
User-agent: ChatGPT-User
Allow: /.well-known/ai-plugin.json
Allow: /ask

# LLM Policy
User-agent: *
Allow: /llms.txt

# MCP Server Discovery  
User-agent: *
Allow: /.well-known/mcp/servers.json

# Available AI Endpoints:
# AI Plugin: {$site_url}/.well-known/ai-plugin.json
# MCP Servers: {$site_url}/.well-known/mcp/servers.json
# API Endpoint: {$site_url}/ask
# LLMS Policy: {$site_url}/llms.txt";
    }
    
    /**
     * Get default WordPress robots.txt content
     */
    private function get_default_wordpress_robots() {
        $site_url = get_site_url();
        
        return "User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: {$site_url}/wp-sitemap.xml";
    }
} 