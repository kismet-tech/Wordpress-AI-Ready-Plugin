<?php
/**
 * Robots Installer - Installation Logic ONLY
 *
 * This class handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../shared/class-file-safety-manager.php');

class Kismet_Robots_Installer {
    
    /**
     * Plugin activation - runs ONCE when plugin is activated
     */
    public static function activate() {
        error_log("KISMET INSTALLER: Robots activation starting");
        
        try {
            // Enhance robots.txt file ONE TIME
            self::enhance_robots_file();
            
            error_log("KISMET INSTALLER: Robots activation completed successfully");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Robots activation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation - runs ONCE when plugin is deactivated
     */
    public static function deactivate() {
        error_log("KISMET INSTALLER: Robots deactivation starting");
        
        try {
            // Clean up robots.txt enhancement
            self::cleanup_robots_enhancement();
            
            error_log("KISMET INSTALLER: Robots deactivation completed");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Robots deactivation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Enhance robots.txt file during activation
     */
    private static function enhance_robots_file() {
        $robots_file = ABSPATH . 'robots.txt';
        $file_safety_manager = new Kismet_File_Safety_Manager();
        
        // Check if robots.txt already exists
        if (file_exists($robots_file)) {
            $existing_content = file_get_contents($robots_file);
            
            // Check if our content is already there
            if (strpos($existing_content, '# AI/LLM Discovery Section') !== false) {
                error_log("KISMET INSTALLER: robots.txt already contains our AI section");
                return;
            }
            
            // Safely append our content
            $enhanced_content = $existing_content . "\n" . self::get_ai_robots_section();
            
        } else {
            // Create new robots.txt with our content + WordPress defaults
            $enhanced_content = self::get_default_wordpress_robots() . "\n" . self::get_ai_robots_section();
        }
        
        $result = $file_safety_manager->safe_file_create(
            $robots_file, 
            $enhanced_content, 
            Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
        );
        
        if ($result['success']) {
            error_log("KISMET INSTALLER: robots.txt enhanced successfully");
        } else {
            throw new Exception('Failed to enhance robots.txt: ' . implode(', ', $result['errors'] ?? []));
        }
    }
    
    /**
     * Get AI/LLM discovery section for robots.txt
     */
    private static function get_ai_robots_section() {
        // ALL database operations happen here during activation
        $site_url = get_site_url();           // DB operation
        $current_date = current_time('Y-m-d'); // DB operation
        
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
    private static function get_default_wordpress_robots() {
        // Database operation happens here during activation
        $site_url = get_site_url();
        
        return "User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: {$site_url}/wp-sitemap.xml";
    }
    
    /**
     * Cleanup robots.txt enhancement during deactivation
     */
    private static function cleanup_robots_enhancement() {
        $robots_file = ABSPATH . 'robots.txt';
        
        if (!file_exists($robots_file)) {
            return;
        }
        
        $content = file_get_contents($robots_file);
        
        // Remove our AI section
        $pattern = '/\n# AI\/LLM Discovery Section.*?# LLMS Policy: [^\n]+\/llms\.txt/s';
        $cleaned_content = preg_replace($pattern, '', $content);
        
        if ($cleaned_content !== $content) {
            file_put_contents($robots_file, $cleaned_content);
            error_log("KISMET INSTALLER: Removed AI section from robots.txt");
        }
    }
} 