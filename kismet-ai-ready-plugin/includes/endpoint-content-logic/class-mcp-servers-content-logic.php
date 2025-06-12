<?php
/**
 * MCP Servers Content Logic
 *
 * This class defines the content and behavior for the MCP Servers endpoint.
 * It handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * RESPONSIBILITY: Define content for /.well-known/mcp/servers.json endpoint
 * RUNS: Only during plugin activation/deactivation
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../shared/class-file-safety-manager.php');

class Kismet_MCP_Servers_Content_Logic {
    
    /**
     * Plugin activation - runs ONCE when plugin is activated
     */
    public static function activate() {
        error_log("KISMET INSTALLER: MCP Servers activation starting");
        
        try {
            // Create .well-known directory if needed
            self::create_well_known_directory();
            
            // Generate static MCP servers file ONE TIME
            self::create_static_mcp_servers_file();
            
            error_log("KISMET INSTALLER: MCP Servers activation completed successfully");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: MCP Servers activation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation - runs ONCE when plugin is deactivated
     */
    public static function deactivate() {
        error_log("KISMET INSTALLER: MCP Servers deactivation starting");
        
        try {
            // Remove static file
            self::cleanup_static_file();
            
            error_log("KISMET INSTALLER: MCP Servers deactivation completed");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: MCP Servers deactivation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create .well-known directory
     */
    private static function create_well_known_directory() {
        $well_known_dir = ABSPATH . '.well-known';
        
        if (!file_exists($well_known_dir)) {
            if (!wp_mkdir_p($well_known_dir)) {
                throw new Exception('Failed to create .well-known directory');
            }
        }
        
        // Also create mcp subdirectory
        $mcp_dir = $well_known_dir . '/mcp';
        if (!file_exists($mcp_dir)) {
            if (!wp_mkdir_p($mcp_dir)) {
                throw new Exception('Failed to create .well-known/mcp directory');
            }
            error_log("KISMET INSTALLER: Created .well-known/mcp directory");
        }
    }
    
    /**
     * Create static MCP servers file during activation
     */
    private static function create_static_mcp_servers_file() {
        $file_path = ABSPATH . '.well-known/mcp/servers.json';
        
        // Generate content with all database operations happening NOW
        $content = self::generate_mcp_servers_content();
        
        // Use file safety manager for secure file creation
        $file_safety_manager = new Kismet_File_Safety_Manager();
        $result = $file_safety_manager->safe_file_create(
            $file_path, 
            $content, 
            Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
        );
        
        if ($result['success']) {
            error_log("KISMET INSTALLER: MCP servers static file created successfully");
        } else {
            throw new Exception('Failed to create MCP servers static file: ' . implode(', ', $result['errors'] ?? []));
        }
    }
    
    /**
     * Generate MCP servers JSON content
     */
    private static function generate_mcp_servers_content() {
        // ALL database operations happen here during activation
        $site_url = get_site_url();           // DB operation
        $site_name = get_bloginfo('name');    // DB operation  
        $admin_email = get_option('admin_email'); // DB operation
        
        $servers_data = array(
            "schema_version" => "1.0",
            "last_updated" => current_time('c'),
            "publisher" => array(
                "name" => $site_name,
                "url" => $site_url,
                "contact_email" => $admin_email
            ),
            "servers" => array(
                array(
                    "name" => "Kismet Hotel Assistant",
                    "description" => "Hotel information and booking assistance",
                    "url" => $site_url . "/ask",
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
                "_generated_by" => "kismet-ai-ready-plugin-installer",
                "_generation_method" => "activation_static_file",
                "_generated_at" => current_time('c')
            )
        );
        
        return json_encode($servers_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Cleanup static file during deactivation
     */
    private static function cleanup_static_file() {
        $file_path = ABSPATH . '.well-known/mcp/servers.json';
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("KISMET INSTALLER: MCP servers static file removed");
            }
        }
    }
    
    /**
     * Regenerate static file when needed
     */
    public static function regenerate_static_file() {
        try {
            self::create_static_mcp_servers_file();
            return true;
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Failed to regenerate MCP servers file: " . $e->getMessage());
            return false;
        }
    }
} 