<?php
/**
 * LLMS Content Logic
 *
 * This class defines the content and behavior for the llms.txt file.
 * It handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * RESPONSIBILITY: Define LLM policy content for llms.txt file
 * RUNS: Only during plugin activation/deactivation
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../shared/class-file-safety-manager.php');
require_once(plugin_dir_path(__FILE__) . '../shared/class-endpoint-manager.php');

class Kismet_LLMS_Content_Logic {
    
    /**
     * Plugin activation - runs ONCE when plugin is activated
     */
    public static function activate() {
        error_log("KISMET INSTALLER: LLMS activation starting");
        
        try {
            // IMMEDIATE FIX: Create static file (like robots.txt does)
            // This ensures the endpoint works immediately
            self::create_static_llms_file();
            
            // FUTURE: Also register with Endpoint Manager for metrics capability
            // This prepares for the metrics system but doesn't break current functionality
            $endpoint_manager = Kismet_Endpoint_Manager::get_instance();
            
            $test_results = $endpoint_manager->register_endpoint(array(
                'path' => '/llms.txt',
                'content_generator' => array(self::class, 'generate_llms_content'),
                'content_type' => 'text/plain'
            ));
            
            // Log the strategy used
            $strategy = $test_results['strategy_used'] ?? 'unknown';
            error_log("KISMET INSTALLER: LLMS endpoint using strategy: " . $strategy);
            
            // Set activation timestamp
            update_option('kismet_llms_activated', current_time('timestamp'));
            update_option('kismet_llms_strategy', $strategy);
            
            error_log("KISMET INSTALLER: LLMS activation completed successfully");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: LLMS activation failed: " . $e->getMessage());
            // Don't throw - let activation continue
        }
    }
    
    /**
     * Plugin deactivation - runs ONCE when plugin is deactivated
     */
    public static function deactivate() {
        error_log("KISMET INSTALLER: LLMS deactivation starting");
        
        try {
            // Clean up static file (like robots.txt does)
            self::cleanup_static_file();
            
            // Also use endpoint manager for cleanup
            $endpoint_manager = Kismet_Endpoint_Manager::get_instance();
            $endpoint_manager->cleanup_endpoint('/llms.txt');
            
            // Clean up options
            delete_option('kismet_llms_activated');
            delete_option('kismet_llms_strategy');
            
            error_log("KISMET INSTALLER: LLMS deactivation completed");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: LLMS deactivation failed: " . $e->getMessage());
            // Don't throw - let deactivation continue
        }
    }
    
    /**
     * Create static LLMS.txt file during activation
     */
    private static function create_static_llms_file() {
        $file_path = ABSPATH . 'llms.txt';
        
        // Generate content with all database operations happening NOW
        $content = self::generate_llms_content();
        
        // Use file safety manager for secure file creation
        $file_safety_manager = new Kismet_File_Safety_Manager();
        $result = $file_safety_manager->safe_file_create(
            $file_path, 
            $content, 
            Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
        );
        
        if ($result['success']) {
            error_log("KISMET INSTALLER: LLMS.txt static file created successfully");
        } else {
            throw new Exception('Failed to create LLMS.txt static file: ' . implode(', ', $result['errors'] ?? []));
        }
    }
    
    /**
     * Generate LLMS.txt content
     * 
     * PUBLIC method so it can be called by Endpoint Manager for WordPress rewrite strategy
     */
    public static function generate_llms_content() {
        // ALL database operations happen here during activation
        $site_url = get_site_url();           // DB operation
        $site_name = get_bloginfo('name');    // DB operation  
        $admin_email = get_option('admin_email'); // DB operation
        $current_date = current_time('Y-m-d'); // DB operation
        
        // Generate defaults
        $domain = parse_url($site_url, PHP_URL_HOST);
        $hotel_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
        
        return "# LLMS.txt - Large Language Model Policy
# Site: {$site_name}
# URL: {$site_url}
# Contact: {$admin_email}
# Last Updated: {$current_date}

## About This Site
This is {$hotel_name}, a hotel website providing information about accommodations, amenities, and booking services.

## AI/LLM Usage Policy
- AI models are welcome to access public content for informational purposes
- Please respect our robots.txt directives
- Commercial scraping requires permission

## Available AI Endpoints
- AI Plugin Discovery: {$site_url}/.well-known/ai-plugin.json
- MCP Server Discovery: {$site_url}/.well-known/mcp/servers.json  
- API Endpoint: {$site_url}/ask
- This Policy: {$site_url}/llms.txt

## Content Guidelines
- Hotel information is updated regularly
- Booking inquiries should use our official API
- Respect user privacy and data protection laws

## Contact Information
For AI/LLM integration questions: {$admin_email}
Website: {$site_url}

---
Generated by Kismet Ask Proxy Plugin
Generation Date: {$current_date}";
    }
    
    /**
     * Cleanup static file during deactivation
     */
    private static function cleanup_static_file() {
        $file_path = ABSPATH . 'llms.txt';
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("KISMET INSTALLER: LLMS.txt static file removed");
            }
        }
    }
    
    /**
     * Regenerate static file when needed
     */
    public static function regenerate_static_file() {
        try {
            self::create_static_llms_file();
            return true;
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Failed to regenerate LLMS.txt file: " . $e->getMessage());
            return false;
        }
    }
} 