<?php
/**
 * Kismet Ask Proxy Plugin
 *
 * NOTE: This plugin now includes a dedicated admin page ('Kismet Env') in the WordPress dashboard sidebar.
 * Use this page to diagnose and report environment or plugin issues.
 * When adding new features, ensure any relevant status, errors, or diagnostics are surfaced on this page for visibility.
 */

/**
 * Plugin Name: Kismet Ask Proxy
 * Description: Creates an AI-ready /ask page that serves both API requests and human visitors with Kismet branding.
 * Version: 1.0
 * Author: Kismet
 * License: GPL2+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KISMET_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KISMET_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include our modular handler classes
require_once KISMET_PLUGIN_PATH . 'includes/class-robots-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/class-ai-plugin-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/class-ask-handler.php';
require_once KISMET_PLUGIN_PATH . 'includes/class-environment-detector.php';
require_once KISMET_PLUGIN_PATH . 'includes/class-mcp-servers-handler.php';

/**
 * Main plugin class - coordinates all handlers
 */
class Kismet_Ask_Proxy_Plugin {
    
    private $robots_handler;
    private $ai_plugin_handler;
    private $ask_handler;
    private $mcp_servers_handler;
    
    public function __construct() {
        // Initialize all our handler classes
        $this->robots_handler = new Kismet_Robots_Handler();
        $this->ai_plugin_handler = new Kismet_AI_Plugin_Handler();
        $this->ask_handler = new Kismet_Ask_Handler();
        $this->mcp_servers_handler = new Kismet_MCP_Servers_Handler();
    
        // Add a "Settings" link to the plugin row
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Add "Settings" link to plugin actions in the plugins list
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=kismet-ai-plugin-settings">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
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
    
    // Show environment report on plugin settings page
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'kismet-ai-plugin-settings') !== false) {
        $environment_report = get_option('kismet_environment_report');
        if ($environment_report) {
            $environment_detector = new Kismet_Environment_Detector();
            echo $environment_detector->get_admin_report_html();
        }
    }
}

// === ACTIVATION & DEACTIVATION HOOKS ===

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Activated');
    
    // Run comprehensive environment check before proceeding
    $environment_detector = new Kismet_Environment_Detector();
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
    
    // Proceed with activation only if environment is compatible
    if ($environment_detector->is_environment_compatible()) {
        // Create physical .well-known directory and files to bypass web server blocking
        kismet_create_physical_well_known_files();
            
        // Flush rewrite rules for ai-plugin.json and mcp/servers.json
        $ai_plugin_handler = new Kismet_AI_Plugin_Handler();
        $ai_plugin_handler->flush_rewrite_rules();
        
        $mcp_servers_handler = new Kismet_MCP_Servers_Handler();
        $mcp_servers_handler->flush_rewrite_rules();
        
        // Force a hard flush after a delay to ensure rules are properly registered
        wp_schedule_single_event(time() + 5, 'kismet_delayed_flush');
        
        // Send registration notification to Kismet backend
        kismet_register_plugin_activation();
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
    $htaccess_file = $well_known_dir . '/.htaccess';
    $mcp_htaccess_file = $mcp_dir . '/.htaccess';
    
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
    
    // Write the PHP files
    $ai_php_success = file_put_contents($ai_plugin_php_file, $php_content);
    $mcp_php_success = file_put_contents($mcp_servers_php_file, $mcp_php_content);
    
    // Handle .htaccess files more carefully - preserve existing content
    $htaccess_success = kismet_add_htaccess_rules($htaccess_file, $our_htaccess_rules);
    $mcp_htaccess_success = kismet_add_htaccess_rules($mcp_htaccess_file, $mcp_htaccess_rules);
    
    if ($ai_php_success && $mcp_php_success && $htaccess_success && $mcp_htaccess_success) {
        error_log("KISMET DEBUG: Successfully created all PHP handlers and .htaccess files");
        return true;
    } else {
        error_log("KISMET ERROR: Failed to write files - AI PHP: " . ($ai_php_success ? 'OK' : 'FAIL') . 
                  ", MCP PHP: " . ($mcp_php_success ? 'OK' : 'FAIL') . 
                  ", .htaccess: " . ($htaccess_success ? 'OK' : 'FAIL') . 
                  ", MCP .htaccess: " . ($mcp_htaccess_success ? 'OK' : 'FAIL'));
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
    $htaccess_file = $well_known_dir . '/.htaccess';
    $mcp_htaccess_file = $mcp_dir . '/.htaccess';
    
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
    
    // Remove our sections using regex (both AI Plugin and MCP Servers)
    $ai_pattern = '/\n?# BEGIN Kismet AI Plugin.*?# END Kismet AI Plugin\n?/s';
    $mcp_pattern = '/\n?# BEGIN Kismet MCP Servers.*?# END Kismet MCP Servers\n?/s';
    $new_content = preg_replace($ai_pattern, '', $content);
    $new_content = preg_replace($mcp_pattern, '', $new_content);
    
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

add_action('admin_menu', function() {
    add_menu_page(
        'Kismet Environment Report',         // Page title
        'Kismet Env',                        // Menu title
        'manage_options',                    // Capability
        'kismet-env-report',                 // Menu slug
        function() {                         // Callback to display content
            if (class_exists('Kismet_Environment_Detector')) {
                $detector = new Kismet_Environment_Detector();
                echo $detector->get_admin_report_html();
            } else {
                echo '<div class="notice notice-error"><p>Kismet_Environment_Detector class not found.</p></div>';
            }
        },
        'dashicons-shield-alt',              // Icon (optional)
        80                                   // Position (optional)
    );
});