<?php
/**
 * AI Plugin Installer - Installation Logic ONLY
 *
 * This class handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * RESPONSIBILITY: Create static files, set up directories, configure settings
 * RUNS: Only during plugin activation/deactivation
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../shared/class-file-safety-manager.php');

class Kismet_AI_Plugin_Installer {
    
    /**
     * Plugin activation - runs ONCE when plugin is activated
     */
    public static function activate() {
        error_log("KISMET INSTALLER: AI Plugin activation starting");
        
        try {
            // Create .well-known directory if needed
            self::create_well_known_directory();
            
            // Generate static AI plugin file ONE TIME
            self::create_static_ai_plugin_file();
            
            // Set activation timestamp
            update_option('kismet_ai_plugin_activated', current_time('timestamp'));
            
            error_log("KISMET INSTALLER: AI Plugin activation completed successfully");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: AI Plugin activation failed: " . $e->getMessage());
            // Don't throw - let activation continue
        }
    }
    
    /**
     * Plugin deactivation - runs ONCE when plugin is deactivated
     */
    public static function deactivate() {
        error_log("KISMET INSTALLER: AI Plugin deactivation starting");
        
        try {
            // Remove static file
            self::cleanup_static_file();
            
            // Clean up options
            delete_option('kismet_ai_plugin_activated');
            delete_option('kismet_ai_plugin_static_generated');
            delete_option('kismet_ai_plugin_settings_updated');
            
            error_log("KISMET INSTALLER: AI Plugin deactivation completed");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: AI Plugin deactivation failed: " . $e->getMessage());
            // Don't throw - let deactivation continue
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
            error_log("KISMET INSTALLER: Created .well-known directory");
        } else {
            error_log("KISMET INSTALLER: .well-known directory already exists");
        }
    }
    
    /**
     * Create static AI plugin file during activation
     * 
     * This performs ALL the expensive database operations ONE TIME during activation.
     * The file is then served directly by the web server with zero PHP execution.
     */
    private static function create_static_ai_plugin_file() {
        $file_path = ABSPATH . '.well-known/ai-plugin.json';
        
        // Generate content with all database operations happening NOW
        $content = self::generate_ai_plugin_content();
        
        // Use file safety manager for secure file creation
        $file_safety_manager = new Kismet_File_Safety_Manager();
        $result = $file_safety_manager->safe_file_create(
            $file_path, 
            $content, 
            Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
        );
        
        if ($result['success']) {
            update_option('kismet_ai_plugin_static_generated', current_time('timestamp'));
            update_option('kismet_ai_plugin_settings_updated', current_time('timestamp'));
            error_log("KISMET INSTALLER: AI plugin static file created successfully");
        } else {
            throw new Exception('Failed to create AI plugin static file: ' . implode(', ', $result['errors'] ?? []));
        }
    }
    
    /**
     * Generate AI plugin JSON content
     * 
     * ALL database operations happen here during activation.
     * This content is static until plugin settings change.
     */
    private static function generate_ai_plugin_content() {
        // Perform ALL expensive database operations NOW (activation time)
        $site_url = get_site_url();           // DB operation
        $site_name = get_bloginfo('name');    // DB operation  
        $admin_email = get_option('admin_email'); // DB operation
        
        // Generate defaults
        $domain = parse_url($site_url, PHP_URL_HOST);
        $auto_hotel_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
        
        // Get all plugin settings (all database operations happen NOW)
        $hotel_name = get_option('kismet_hotel_name', '') ?: $auto_hotel_name;
        $hotel_description = get_option('kismet_hotel_description', '') ?: ('Get information about ' . $auto_hotel_name . ' including amenities, pricing, availability, and booking assistance.');
        $logo_url = get_option('kismet_logo_url', '') ?: ($site_url . '/wp-content/uploads/2024/kismet-logo.png');
        $contact_email = get_option('kismet_contact_email', '') ?: $admin_email;
        $legal_info_url = get_option('kismet_legal_info_url', '') ?: ($site_url . '/privacy-policy');
        
        // Build complete AI plugin JSON
        $ai_plugin = [
            'schema_version' => 'v1',
            'name_for_human' => $hotel_name . ' AI Assistant',
            'name_for_model' => strtolower(str_replace([' ', '-', '.'], '_', $hotel_name)) . '_assistant',
            'description_for_human' => $hotel_description,
            'description_for_model' => 'Provides hotel information for ' . $hotel_name . ' including room availability, pricing, amenities, policies, and booking assistance.',
            'auth' => [
                'type' => 'none'
            ],
            'api' => [
                'type' => 'openapi',
                'url' => $site_url . '/ask'
            ],
            'logo_url' => $logo_url,
            'contact_email' => $contact_email,
            'legal_info_url' => $legal_info_url,
            '_generated_by' => 'kismet-ai-ready-plugin-installer',
            '_generation_method' => 'activation_static_file',
            '_generated_at' => current_time('c'),
            '_site_url' => $site_url
        ];
        
        return json_encode($ai_plugin, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Cleanup static file during deactivation
     */
    private static function cleanup_static_file() {
        $file_path = ABSPATH . '.well-known/ai-plugin.json';
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                error_log("KISMET INSTALLER: AI plugin static file removed");
            } else {
                error_log("KISMET INSTALLER WARNING: Failed to remove AI plugin static file");
            }
        }
    }
    
    /**
     * Regenerate static file when settings change
     * 
     * This is called by admin settings when user updates configuration.
     * It's the ONLY time we regenerate the static file after activation.
     */
    public static function regenerate_static_file() {
        try {
            self::create_static_ai_plugin_file();
            return true;
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Failed to regenerate AI plugin file: " . $e->getMessage());
            return false;
        }
    }
} 