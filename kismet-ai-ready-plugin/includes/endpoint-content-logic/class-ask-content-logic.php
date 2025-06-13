<?php
/**
 * Ask Content Logic
 *
 * This class defines the content and behavior for the /ask endpoint.
 * It handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * RESPONSIBILITY: Define endpoint behavior for /ask
 * RUNS: Only during plugin activation/deactivation and endpoint requests
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Ask_Content_Logic {
    
    /**
     * Plugin activation - runs ONCE when plugin is activated
     */
    public static function activate() {
        error_log("KISMET INSTALLER: Ask endpoint activation starting");
        
        try {
            // Register query var
            global $wp_rewrite;
            
            // Add our query var to WordPress
            add_filter('query_vars', function($vars) {
                error_log('KISMET DEBUG: Adding kismet_ask to query vars during activation');
                if (!in_array('kismet_ask', $vars)) {
                    $vars[] = 'kismet_ask';
                }
                return $vars;
            });
            
            // Add rewrite rule
            error_log('KISMET DEBUG: Adding rewrite rule for /ask during activation');
            add_rewrite_rule('^ask/?$', 'index.php?kismet_ask=1', 'top');
            
            // Force update rewrite rules
            $wp_rewrite->flush_rules(true);
            
            error_log("KISMET INSTALLER: Ask endpoint activation completed successfully");
        } catch (Exception $e) {
            error_log("KISMET ERROR: Ask endpoint activation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation - runs ONCE when plugin is deactivated
     */
    public static function deactivate() {
        error_log("KISMET INSTALLER: Ask endpoint deactivation starting");
        
        try {
            // Remove query var filter
            remove_filter('query_vars', function($vars) {
                $vars[] = 'kismet_ask';
                return $vars;
            });

            // Flush rewrite rules to remove our endpoint
            flush_rewrite_rules();
            
            error_log("KISMET INSTALLER: Ask endpoint deactivation completed successfully");
        } catch (Exception $e) {
            error_log("KISMET ERROR: Ask endpoint deactivation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate content for /ask endpoint
     * 
     * This method handles both GET and POST requests:
     * - GET: Shows the AI Ready status page
     * - POST: Proxies requests to the Kismet backend API
     */
    public static function generate_ask_content() {
        error_log('KISMET DEBUG: Generating ask content');
        // Handle CORS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
            status_header(200);
            exit;
        }
        
        // Handle POST request (API Proxy)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $target_url = 'https://api.makekismet.com/ask';
            
            // Parse request data according to NLWebAskRequest DTO
            $body = file_get_contents('php://input');
            $json_data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                self::send_error_response(400, 'Invalid JSON in request body');
                return;
            }
            
            if (empty($json_data['query'])) {
                self::send_error_response(400, 'Missing required field: query');
                return;
            }
            
            $request_data = [
                'query' => sanitize_text_field($json_data['query']),
                'site' => parse_url(get_site_url(), PHP_URL_HOST),
                'streaming' => isset($json_data['streaming']) ? (bool)$json_data['streaming'] : true,
            ];
            
            // Add optional fields if present
            if (!empty($json_data['prev'])) {
                $request_data['prev'] = sanitize_text_field($json_data['prev']);
            }
            if (!empty($json_data['decontextualized_query'])) {
                $request_data['decontextualized_query'] = sanitize_text_field($json_data['decontextualized_query']);
            }
            if (!empty($json_data['query_id'])) {
                $request_data['query_id'] = sanitize_text_field($json_data['query_id']);
            }
            if (!empty($json_data['mode']) && in_array($json_data['mode'], ['list', 'summarize', 'generate'])) {
                $request_data['mode'] = $json_data['mode'];
            }
            
            // Make the API request
            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Kismet-WordPress-Plugin/1.0'
                ),
                'body' => json_encode($request_data),
                'timeout' => 30
            );
            
            $response = wp_remote_request($target_url, $args);
            
            if (is_wp_error($response)) {
                self::send_error_response(502, 'Service unavailable');
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                status_header($response_code);
                header('Content-Type: application/json');
                echo $response_body;
            }
            exit;
        }
        
        // Handle GET request (Show status page)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Load and display the template - FIX: Go up two levels to plugin root
            $template_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'views/ask.php';
            error_log("KISMET DEBUG: Looking for template at: " . $template_path);
            if (file_exists($template_path)) {
                include($template_path);
                exit;
            } else {
                error_log("KISMET ERROR: Template file not found: " . $template_path);
                status_header(500);
                echo "Internal server error";
                exit;
            }
        }
    }
    
    /**
     * Send an error response in JSON format
     */
    private static function send_error_response($code, $message) {
        status_header($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
    
    /**
     * Clean up strategy tracking during plugin uninstall
     */
    public static function uninstall() {
        delete_option('kismet_ask_endpoint_strategy');
        error_log("KISMET INSTALLER: Ask endpoint strategy tracking cleaned up during uninstall");
    }

    /**
     * Add this temporary debugging method
     */
    public static function add_debug_hooks() {
        // Check if our query var is detected
        add_action('wp', function() {
            $kismet_ask = get_query_var('kismet_ask');
            if ($kismet_ask) {
                error_log('KISMET DEBUG: Query var detected - kismet_ask=' . $kismet_ask);
                // This is where we should call our content generator
                self::generate_ask_content();
            }
        });
        
        // Log rewrite rules on admin pages only (to avoid spam)
        if (is_admin()) {
            add_action('admin_init', function() {
                global $wp_rewrite;
                $rules = $wp_rewrite->wp_rewrite_rules();
                $ask_rule_found = false;
                foreach ($rules as $pattern => $replacement) {
                    if (strpos($pattern, 'ask') !== false) {
                        error_log('KISMET DEBUG: Found ask rewrite rule - ' . $pattern . ' => ' . $replacement);
                        $ask_rule_found = true;
                    }
                }
                if (!$ask_rule_found) {
                    error_log('KISMET DEBUG: No ask rewrite rule found in current rules');
                }
            }, 999);
        }
    }
} 