<?php
/**
 * Plugin Name: Kismet Ask Proxy
 * Description: Creates an AI-ready /ask page that serves both API requests and human visitors with Kismet branding.
 * Version: 1.0
 * Author: Kismet
 * License: GPL2+
 */

/**
 * LICENSE INFORMATION
 * Copyright (C) 2025 Kismet
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**
 * PLUGIN CONTEXT & USAGE NOTES
 * This plugin now includes a dedicated admin page ('Kismet Env') in the WordPress dashboard sidebar.
 * Use this page to diagnose and report environment or plugin issues.
 * When adding new features, ensure any relevant status, errors, or diagnostics are surfaced on this page for visibility.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KISMET_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KISMET_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include our modular handler classes (comprehensive testing and safety built-in)
require_once KISMET_PLUGIN_PATH . 'includes/endpoints/robots-txt/class-robots-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/endpoints/ai-plugin-json/class-ai-plugin-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/endpoints/ask/class-ask-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/endpoints/mcp-servers-json/class-mcp-servers-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/endpoints/llms-txt/class-llms-txt-handler.php';

// Include modular environment detection system
require_once KISMET_PLUGIN_PATH . 'includes/environment/class-system-checker.php';
require_once KISMET_PLUGIN_PATH . 'includes/environment/class-plugin-detector.php';
require_once KISMET_PLUGIN_PATH . 'includes/environment/class-endpoint-tester.php';
require_once KISMET_PLUGIN_PATH . 'includes/environment/class-report-generator.php';
require_once KISMET_PLUGIN_PATH . 'includes/environment/class-environment-detector-v2.php';

// Include tracking system
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-event-types.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-bot-detector.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-bot-classifier.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-metric-builder.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-event-tracker.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-endpoint-tracking-helper.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-htaccess-manager.php';
require_once KISMET_PLUGIN_PATH . 'includes/tracking/class-universal-tracker.php';

// Include admin interface
require_once KISMET_PLUGIN_PATH . 'includes/admin/class-tracking-settings.php';

// Initialize admin interface with settings link
new Kismet_Tracking_Settings();

/**
 * Main plugin class - coordinates all handlers
 */
class Kismet_Ask_Proxy_Plugin {
    
    private $robots_handler;
    private $ai_plugin_handler;
    private $ask_handler;
    private $mcp_servers_handler;
    private $llms_txt_handler;
    
    public function __construct() {
        // Initialize all our handler classes (comprehensive testing and safety built-in)
        $this->robots_handler = new Kismet_Robots_Handler();
        $this->ai_plugin_handler = new Kismet_AI_Plugin_Handler();
        $this->ask_handler = new Kismet_Ask_Handler();
        $this->mcp_servers_handler = new Kismet_MCP_Servers_Handler();
        $this->llms_txt_handler = new Kismet_LLMS_Txt_Handler();

        // Initialize tracking system
        $this->init_tracking();
    }
    
    /**
     * Initialize automatic tracking for AI endpoints
     */
    private function init_tracking() {
        $universal_tracker = new Kismet_Universal_Tracker();
    }
}

// Initialize the plugin
new Kismet_Ask_Proxy_Plugin();

// Add admin notices for environment compatibility
add_action('admin_notices', 'kismet_display_environment_notices');

/**
 * Display environment compatibility notices in admin
 */
function kismet_display_environment_notices() {
    // Check for activation warning
    $activation_warning = get_option('kismet_activation_warning');
    if ($activation_warning) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Kismet Plugin:</strong> ' . esc_html($activation_warning) . '</p>';
        echo '<p><a href="' . esc_url(admin_url('options-general.php?page=kismet-ai-plugin-settings')) . '">View compatibility report</a></p>';
        echo '</div>';
        // Clear the warning after showing it once
        delete_option('kismet_activation_warning');
    }
    
    // Check for .htaccess activation notice
    $htaccess_notice = get_option('kismet_htaccess_activation_notice');
    if ($htaccess_notice) {
        if ($htaccess_notice === 'added') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Kismet Plugin:</strong> Successfully added .htaccess tracking rules for comprehensive AI bot tracking!</p>';
            echo '<p>All endpoints (including robots.txt, llms.txt) will now be tracked even when physical files exist.</p>';
            echo '</div>';
        } else if ($htaccess_notice === 'failed') {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Kismet Plugin:</strong> Failed to add .htaccess tracking rules. Please check file permissions.</p>';
            echo '<p>You may need to manually add tracking rules or use the test script.</p>';
            echo '</div>';
        }
        // Clear the notice after showing it once
        delete_option('kismet_htaccess_activation_notice');
    }
    
    // Show environment report on plugin settings page
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'kismet-ai-plugin-settings') !== false) {
        $environment_report = get_option('kismet_environment_report');
        if ($environment_report) {
            $environment_detector = new Kismet_Environment_Detector_V2();
            echo $environment_detector->get_admin_report_html();
        }
    }
}

add_action('admin_menu', function() {
    add_menu_page(
        'Kismet Environment Report',         // Page title
        'Kismet Env',                        // Menu title
        'manage_options',                    // Capability
        'kismet-env-report',                 // Menu slug
        function() {                         // Callback to display content
            if (class_exists('Kismet_Environment_Detector_V2')) {
                $detector = new Kismet_Environment_Detector_V2();
                echo $detector->get_admin_report_html();
            } else {
                echo '<div class="notice notice-error"><p>Kismet_Environment_Detector_V2 class not found.</p></div>';
            }
        },
        'dashicons-shield-alt',              // Icon (optional)
        80                                   // Position (optional)
    );
});

// === ACTIVATION & DEACTIVATION HOOKS ===

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Activated');
    
    // Add .htaccess tracking rules for comprehensive endpoint coverage
    if (class_exists('Kismet_Htaccess_Manager')) {
        $htaccess_result = Kismet_Htaccess_Manager::add_tracking_rules();
        if ($htaccess_result) {
            error_log('Kismet: Successfully added .htaccess tracking rules during activation');
            update_option('kismet_htaccess_activation_notice', 'added');
        } else {
            error_log('Kismet: Failed to add .htaccess tracking rules - check file permissions');
            update_option('kismet_htaccess_activation_notice', 'failed');
        }
    }
    
    // Run comprehensive environment check before proceeding
    $environment_detector = new Kismet_Environment_Detector_V2();
    $compatibility_report = $environment_detector->run_full_environment_check();
    
    // Log the compatibility report
    error_log('Kismet Environment Check: ' . $compatibility_report['overall_status']);
    if (!empty($compatibility_report['errors'])) {
        error_log('Kismet Environment Errors: ' . implode(', ', $compatibility_report['errors']));
    }
    if (!empty($compatibility_report['warnings'])) {
        error_log('Kismet Environment Warnings: ' . implode(', ', $compatibility_report['warnings']));
    }
    
    // Store the compatibility report for admin display
    update_option('kismet_environment_report', $compatibility_report);
    
    // Set default tracking options
    if (get_option('kismet_enable_local_bot_filtering') === false) {
        add_option('kismet_enable_local_bot_filtering', false);
    }
    if (get_option('kismet_backend_endpoint') === false) {
        add_option('kismet_backend_endpoint', '');
    }
    
    // Proceed with activation only if environment is compatible
    if ($environment_detector->is_environment_compatible()) {
        // All handlers handle their setup automatically via 'init' action
        // They test accessibility, choose optimal approaches (file vs rewrite), and handle conflicts
        // No manual intervention needed!
        
        // Force a general flush to ensure any rewrite rules added by handlers are registered
        flush_rewrite_rules();
        
        // Force a hard flush after a delay to ensure rules are properly registered
        wp_schedule_single_event(time() + 5, 'kismet_delayed_flush');
        
        // Send registration notification to Kismet backend
        kismet_register_plugin_activation();
        
        // Track plugin activation
        Kismet_Event_Tracker::track_endpoint_access(
            Kismet_Event_Types::PLUGIN_ACTIVATION, 
            'plugin_activation'
        );
    } else {
        error_log('Kismet Plugin Activation: Environment incompatible - some features may not work properly');
        // Don't fail activation completely, but warn that some features may not work
        add_option('kismet_activation_warning', 'Environment compatibility issues detected during activation');
    }
});

/**
 * Delayed rewrite rules flush to ensure proper registration
 */
add_action('kismet_delayed_flush', function() {
    flush_rewrite_rules(true); // true = hard flush, clears .htaccess
    error_log('Kismet: Performed delayed rewrite rules flush');
});

/**
 * Plugin deactivation hook  
 */
register_deactivation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Deactivated');
    
    // Remove new .htaccess tracking rules
    if (class_exists('Kismet_Htaccess_Manager')) {
        $htaccess_result = Kismet_Htaccess_Manager::remove_tracking_rules();
        if ($htaccess_result) {
            error_log('Kismet: Successfully removed .htaccess tracking rules during deactivation');
        } else {
            error_log('Kismet: Failed to remove .htaccess tracking rules');
        }
    }
    
    // Remove physical .well-known files
    kismet_remove_physical_well_known_files();
    
    flush_rewrite_rules();
});

// === PHYSICAL FILE CREATION FUNCTIONALITY ===

/**
 * Create physical .well-known files (ai-plugin.json and mcp/servers.json) to bypass web server blocking
 */
function kismet_create_physical_well_known_files() {
    $wordpress_root = ABSPATH;
    $well_known_dir = $wordpress_root . '.well-known';
    $mcp_dir = $well_known_dir . '/mcp';
    $ai_plugin_php_file = $well_known_dir . '/ai-plugin-handler.php';
    $mcp_servers_php_file = $mcp_dir . '/servers-handler.php';
    $llms_txt_php_file = $wordpress_root . 'llms-handler.php';
    $htaccess_file = $well_known_dir . '/.htaccess';
    $mcp_htaccess_file = $mcp_dir . '/.htaccess';
    $root_htaccess_file = $wordpress_root . '.htaccess';
    
    error_log("KISMET DEBUG: Creating physical files in: $well_known_dir");
    
    // Create .well-known directory if it doesn't exist
    if (!file_exists($well_known_dir)) {
        if (wp_mkdir_p($well_known_dir)) {
            error_log("KISMET DEBUG: Created .well-known directory");
        } else {
            error_log("KISMET ERROR: Failed to create .well-known directory");
            return false;
        }
    }
    
    // Create .well-known/mcp directory if it doesn't exist
    if (!file_exists($mcp_dir)) {
        if (wp_mkdir_p($mcp_dir)) {
            error_log("KISMET DEBUG: Created .well-known/mcp directory");
        } else {
            error_log("KISMET ERROR: Failed to create .well-known/mcp directory");
            return false;
        }
    }
    
    // Create the PHP handler file
    $php_content = '<?php
/**
 * Kismet AI Plugin JSON Handler - Generated by Kismet Ask Proxy Plugin
 * This file provides the ai-plugin.json content by calling WordPress functions
 */

// Load WordPress
require_once(__DIR__ . "/../wp-load.php");

// Get the AI plugin handler and generate JSON
if (class_exists("Kismet_AI_Plugin_Handler")) {
    $handler = new Kismet_AI_Plugin_Handler();
    
    // Use reflection to call private method
    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod("serve_generated_ai_plugin");
    $method->setAccessible(true);
    $method->invoke($handler);
} else {
    // Fallback basic JSON if plugin not loaded
    header("Content-Type: application/json");
    http_response_code(200);
    echo json_encode([
        "schema_version" => "v1",
        "name_for_human" => "WordPress AI Assistant",
        "name_for_model" => "wordpress_assistant", 
        "description_for_human" => "AI assistant for this WordPress site",
        "description_for_model" => "Provides information about this WordPress website",
        "auth" => ["type" => "none"],
        "api" => ["type" => "openapi", "url" => "' . get_site_url() . '/ask"],
        "logo_url" => "' . get_site_url() . '/wp-content/uploads/2024/kismet-logo.png",
        "contact_email" => "' . get_option('admin_email') . '",
        "legal_info_url" => "' . get_site_url() . '/privacy-policy"
    ], JSON_PRETTY_PRINT);
}
?>';

    // Create the MCP servers PHP handler file
    $mcp_php_content = '<?php
/**
 * Kismet MCP Servers JSON Handler - Generated by Kismet Ask Proxy Plugin
 * This file provides the mcp/servers.json content by calling WordPress functions
 */

// Load WordPress
require_once(__DIR__ . "/../../wp-load.php");

// Get the MCP servers handler and generate JSON
if (class_exists("Kismet_MCP_Servers_Handler")) {
        $handler = new Kismet_MCP_Servers_Handler();
    
    // Use reflection to call private method
    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod("serve_mcp_servers_json");
    $method->setAccessible(true);
    $method->invoke($handler);
} else {
    // Fallback basic JSON if plugin not loaded
    header("Content-Type: application/json");
    http_response_code(200);
    echo json_encode([
        "schema_version" => "1.0",
        "last_updated" => date("c"),
        "publisher" => [
            "name" => "' . get_bloginfo('name') . '",
            "url" => "' . get_site_url() . '",
            "contact_email" => "' . get_option('admin_email') . '"
        ],
        "servers" => [
            [
                "name" => "Kismet Hotel Assistant",
                "description" => "Hotel information and booking assistance",
                "url" => "' . get_site_url() . '/ask",
                "type" => "hotel_assistant",
                "version" => "1.0",
                "capabilities" => ["room_availability", "pricing_information", "amenities_information", "booking_assistance", "general_inquiries"],
                "authentication" => ["type" => "none"],
                "trusted" => true
            ]
        ],
        "metadata" => [
            "total_servers" => 1,
            "generated_by" => "Kismet WordPress Plugin"
        ]
    ], JSON_PRETTY_PRINT);
}
?>';

    // Create the LLMS.txt PHP handler file
    $llms_txt_php_content = '<?php
/**
 * Kismet LLMS.txt Handler - Generated by Kismet Ask Proxy Plugin
 * This file provides the llms.txt content by calling WordPress functions
 */

// Load WordPress
require_once(__DIR__ . "/wp-load.php");

// Get the LLMS.txt handler and generate content
if (class_exists("Kismet_LLMS_Txt_Handler")) {
    $handler = new Kismet_LLMS_Txt_Handler();
    
    // Use reflection to call private method
    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod("serve_llms_txt");
    $method->setAccessible(true);
    $method->invoke($handler);
} else {
    // Fallback basic llms.txt if plugin not loaded
    header("Content-Type: text/plain; charset=utf-8");
    http_response_code(200);
    $current_date = date("Y-m-d");
    echo "# llms.txt - AI/LLM Policy and MCP Server Discovery\n";
    echo "# Generated by Kismet WordPress Plugin on {$current_date}\n";
    echo "# Site: ' . get_bloginfo('name') . ' (' . get_site_url() . ')\n\n";
    echo "# MCP (Model Context Protocol) Server Discovery\n";
    echo "MCP-SERVER: https://mcp.ksmt.app/sse\n\n";
    echo "# Contact Information\n";
    echo "Contact: ' . get_option('admin_email') . '\n";
    echo "Website: ' . get_site_url() . '\n\n";
    echo "# Available AI Endpoints\n";
    echo "AI-Plugin: ' . get_site_url() . '/.well-known/ai-plugin.json\n";
    echo "MCP-Servers: ' . get_site_url() . '/.well-known/mcp/servers.json\n";
    echo "API-Endpoint: ' . get_site_url() . '/ask\n\n";
    echo "# Last updated: {$current_date}\n";
}
?>';
    
    // Create .htaccess rule to rewrite ai-plugin.json to our PHP handler
    $our_htaccess_rules = '
# BEGIN Kismet AI Plugin
RewriteEngine On
RewriteRule ^ai-plugin\.json$ ai-plugin-handler.php [L]
# END Kismet AI Plugin';

    // Create .htaccess rule for MCP directory to rewrite servers.json
    $mcp_htaccess_rules = '
# BEGIN Kismet MCP Servers
RewriteEngine On
RewriteRule ^servers\.json$ servers-handler.php [L]
# END Kismet MCP Servers';

    // Create root .htaccess rule for llms.txt
    $root_htaccess_rules = '
# BEGIN Kismet LLMS.txt
RewriteEngine On
RewriteRule ^llms\.txt$ llms-handler.php [L]
# END Kismet LLMS.txt';
    
    // Write the PHP files
    $ai_php_success = file_put_contents($ai_plugin_php_file, $php_content);
    $mcp_php_success = file_put_contents($mcp_servers_php_file, $mcp_php_content);
    $llms_txt_php_success = file_put_contents($llms_txt_php_file, $llms_txt_php_content);
    
    // Handle .htaccess files more carefully - preserve existing content
    $htaccess_success = kismet_add_htaccess_rules($htaccess_file, $our_htaccess_rules);
    $mcp_htaccess_success = kismet_add_htaccess_rules($mcp_htaccess_file, $mcp_htaccess_rules);
    $root_htaccess_success = kismet_add_htaccess_rules($root_htaccess_file, $root_htaccess_rules);
    
    if ($ai_php_success && $mcp_php_success && $llms_txt_php_success && $htaccess_success && $mcp_htaccess_success && $root_htaccess_success) {
        error_log("KISMET DEBUG: Successfully created all PHP handlers and .htaccess files");
        return true;
    } else {
        error_log("KISMET ERROR: Failed to write files - AI PHP: " . ($ai_php_success ? 'OK' : 'FAIL') . 
                  ", MCP PHP: " . ($mcp_php_success ? 'OK' : 'FAIL') . 
                  ", LLMS.txt PHP: " . ($llms_txt_php_success ? 'OK' : 'FAIL') . 
                  ", .htaccess: " . ($htaccess_success ? 'OK' : 'FAIL') . 
                  ", MCP .htaccess: " . ($mcp_htaccess_success ? 'OK' : 'FAIL') . 
                  ", Root .htaccess: " . ($root_htaccess_success ? 'OK' : 'FAIL'));
        return false;
    }
}

/**
 * Remove physical .well-known files on deactivation
 */
function kismet_remove_physical_well_known_files() {
    $wordpress_root = ABSPATH;
    $well_known_dir = $wordpress_root . '.well-known';
    $mcp_dir = $well_known_dir . '/mcp';
    $ai_plugin_php_file = $well_known_dir . '/ai-plugin-handler.php';
    $mcp_servers_php_file = $mcp_dir . '/servers-handler.php';
    $llms_txt_php_file = $wordpress_root . 'llms-handler.php';
    $htaccess_file = $well_known_dir . '/.htaccess';
    $mcp_htaccess_file = $mcp_dir . '/.htaccess';
    $root_htaccess_file = $wordpress_root . '.htaccess';
    
    // Remove PHP handler files
    if (file_exists($ai_plugin_php_file)) {
        if (unlink($ai_plugin_php_file)) {
            error_log("KISMET DEBUG: Removed ai-plugin-handler.php file");
        } else {
            error_log("KISMET ERROR: Failed to remove ai-plugin-handler.php file");
        }
    }
    
    if (file_exists($mcp_servers_php_file)) {
        if (unlink($mcp_servers_php_file)) {
            error_log("KISMET DEBUG: Removed mcp/servers-handler.php file");
        } else {
            error_log("KISMET ERROR: Failed to remove mcp/servers-handler.php file");
        }
    }
    
    if (file_exists($llms_txt_php_file)) {
        if (unlink($llms_txt_php_file)) {
            error_log("KISMET DEBUG: Removed llms-handler.php file");
        } else {
            error_log("KISMET ERROR: Failed to remove llms-handler.php file");
        }
    }
    
    // Remove only our .htaccess rules, preserve other content
    if (file_exists($htaccess_file)) {
        if (kismet_remove_htaccess_rules($htaccess_file)) {
            error_log("KISMET DEBUG: Removed Kismet rules from .well-known/.htaccess");
        } else {
            error_log("KISMET ERROR: Failed to remove Kismet rules from .htaccess file");
        }
    }
    
    if (file_exists($mcp_htaccess_file)) {
        if (kismet_remove_htaccess_rules($mcp_htaccess_file)) {
            error_log("KISMET DEBUG: Removed Kismet rules from .well-known/mcp/.htaccess");
        } else {
            error_log("KISMET ERROR: Failed to remove Kismet rules from mcp/.htaccess file");
        }
    }
    
    if (file_exists($root_htaccess_file)) {
        if (kismet_remove_htaccess_rules($root_htaccess_file)) {
            error_log("KISMET DEBUG: Removed Kismet rules from root .htaccess");
        } else {
            error_log("KISMET ERROR: Failed to remove Kismet rules from root .htaccess file");
        }
    }
    
    // Try to remove mcp directory if empty
    if (file_exists($mcp_dir) && count(scandir($mcp_dir)) == 2) { // only . and ..
        rmdir($mcp_dir);
        error_log("KISMET DEBUG: Removed empty .well-known/mcp directory");
    }
    
    // Try to remove .well-known directory if empty
    if (file_exists($well_known_dir) && count(scandir($well_known_dir)) == 2) { // only . and ..
        rmdir($well_known_dir);
        error_log("KISMET DEBUG: Removed empty .well-known directory");
    }
}

// === .HTACCESS HELPER FUNCTIONS ===

/**
 * Safely add our rules to .htaccess, preserving existing content
 */
function kismet_add_htaccess_rules($htaccess_file, $our_rules) {
    // Read existing content if file exists
    $existing_content = '';
    if (file_exists($htaccess_file)) {
        $existing_content = file_get_contents($htaccess_file);
        
        // Check if our rules are already there to avoid duplicates
        if (strpos($existing_content, '# BEGIN Kismet AI Plugin') !== false) {
            error_log("KISMET DEBUG: Kismet rules already exist in .htaccess");
            return true; // Already exists, that's fine
        }
    }
    
    // Add our rules to the existing content
    $new_content = $existing_content . $our_rules;
    
    // Write the combined content back
    $success = file_put_contents($htaccess_file, $new_content);
    
    if ($success) {
        error_log("KISMET DEBUG: Successfully added Kismet rules to .htaccess");
        return true;
    } else {
        error_log("KISMET ERROR: Failed to write Kismet rules to .htaccess");
        return false;
    }
}

/**
 * Safely remove only our rules from .htaccess, preserving other content
 */
function kismet_remove_htaccess_rules($htaccess_file) {
    if (!file_exists($htaccess_file)) {
        return true; // File doesn't exist, nothing to remove
    }
    
    $content = file_get_contents($htaccess_file);
    
    // Remove our sections using regex (AI Plugin, MCP Servers, and LLMS.txt)
    $ai_pattern = '/\n?# BEGIN Kismet AI Plugin.*?# END Kismet AI Plugin\n?/s';
    $mcp_pattern = '/\n?# BEGIN Kismet MCP Servers.*?# END Kismet MCP Servers\n?/s';
    $llms_pattern = '/\n?# BEGIN Kismet LLMS\.txt.*?# END Kismet LLMS\.txt\n?/s';
    $new_content = preg_replace($ai_pattern, '', $content);
    $new_content = preg_replace($mcp_pattern, '', $new_content);
    $new_content = preg_replace($llms_pattern, '', $new_content);
    
    // If the content is now empty or just whitespace, delete the file
    if (trim($new_content) === '') {
        if (unlink($htaccess_file)) {
            error_log("KISMET DEBUG: Removed empty .htaccess file");
            return true;
        } else {
            error_log("KISMET ERROR: Failed to remove empty .htaccess file");
            return false;
        }
    } else {
        // Write back the content without our rules
        $success = file_put_contents($htaccess_file, $new_content);
        if ($success) {
            error_log("KISMET DEBUG: Successfully removed Kismet rules, preserved other content");
            return true;
        } else {
            error_log("KISMET ERROR: Failed to write cleaned .htaccess content");
            return false;
        }
    }
}

// === BACKEND REGISTRATION FUNCTIONALITY ===

/**
 * Send plugin activation notification to Kismet backend
 */
function kismet_register_plugin_activation() {
    $site_url = get_site_url();
    
    // Determine the correct API endpoint based on environment
    // Check if we're in a local development environment
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || 
                 strpos($host, '127.0.0.1') !== false || 
                 strpos($host, '.local') !== false);
    
    // Determine backend API endpoint based on WordPress site environment
    // If WordPress site is running locally (localhost/127.0.0.1/.local), 
    // send plugin activation notifications to local backend on port 4000
    // Otherwise, send to production API at api.makekismet.com
    $api_base = $is_local ? 'https://localhost:4000' : 'https://api.makekismet.com';
    $endpoint = $api_base . '/PluginInstallation/AddPluginInstallation';
    
    $data = array(
        'siteUrl' => $site_url
    );
    
    // Send the notification
    $args = array(
        'body' => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 15,
        'sslverify' => !$is_local // Disable SSL verification for local development
    );
    
    $response = wp_remote_post($endpoint, $args);
    
    if (is_wp_error($response)) {
        error_log('Kismet Plugin Installation Tracking Error: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 201 || $response_code === 200) {
            error_log('Kismet Plugin Installation: Successfully tracked');
        } else {
            error_log('Kismet Plugin Installation Tracking Error: HTTP ' . $response_code . ' - ' . $response_body);
        }
    }
}