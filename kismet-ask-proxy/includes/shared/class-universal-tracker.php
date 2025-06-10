<?php
/**
 * Kismet Universal Request Tracker
 * 
 * Automatically intercepts WordPress requests and tracks access to AI endpoints.
 * Maps routes to event types and delegates to Event Tracker for backend submission.
 */

// WordPress security check - prevents direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Load the files we depend on
require_once(plugin_dir_path(__FILE__) . '../tracking/class-event-tracker.php');
require_once(plugin_dir_path(__FILE__) . 'class-event-types.php');   // Event type constants

/**
 * Universal Request Tracker Class
 * 
 * This class sets up automatic tracking for all AI-related endpoints
 */
class Kismet_Universal_Tracker {
    
    private $route_mapping;
    private static $hooks_registered = false;
    private static $instance_count = 0;
    private static $execution_count = 0;
    
    public function __construct() {
        self::$instance_count++;
        error_log("KISMET DEBUG: Universal Tracker constructor called - Instance #" . self::$instance_count);
        $this->setup_route_mapping();
        $this->register_hooks();
        error_log("KISMET DEBUG: Universal Tracker initialization complete - Total instances: " . self::$instance_count);
    }
    
    /**
     * Map request paths to event types
     */
    private function setup_route_mapping() {
        $this->route_mapping = array(
            '/llms.txt' => Kismet_Event_Types::PLUGIN_LLMS_TXT_ACCESS,
            '/robots.txt' => Kismet_Event_Types::PLUGIN_ROBOTS_TXT_ACCESS,
            '/ask' => Kismet_Event_Types::PLUGIN_ASK_REQUEST,
            '/.well-known/ai-plugin.json' => Kismet_Event_Types::PLUGIN_AI_PLUGIN_MANIFEST_ACCESS,
            '/.well-known/mcp/servers' => Kismet_Event_Types::PLUGIN_MCP_SERVERS_ACCESS,
        );
        error_log("KISMET DEBUG: Route mapping configured with " . count($this->route_mapping) . " routes");
    }
    
    /**
     * Register WordPress hooks to intercept requests
     */
    private function register_hooks() {
        // Prevent duplicate hook registration
        if (self::$hooks_registered) {
            error_log("KISMET DEBUG: Universal Tracker hooks already registered, skipping");
            return;
        }
        
        // Hook into request parsing to catch all requests that reach WordPress
        add_action('parse_request', array($this, 'intercept_request'), 1);
        self::$hooks_registered = true;
        error_log("KISMET DEBUG: Universal Tracker parse_request hook ENABLED for testing");
    }
    
    /**
     * Check incoming request against tracked endpoints
     */
    public function intercept_request() {
        self::$execution_count++;
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');
        
        error_log("KISMET DEBUG: intercept_request called - Execution #" . self::$execution_count . " for URI: {$request_uri}");
        
        // CRITICAL BUG FIX: Prevent duplicate tracking per request
        // Create unique identifier for this specific request
        $request_id = md5($request_uri . ($_GET['kismet_endpoint'] ?? '') . $_SERVER['REQUEST_TIME']);
        static $processed_requests = array();
        
        if (isset($processed_requests[$request_id])) {
            error_log("KISMET DEBUG: DUPLICATE REQUEST BLOCKED - Already processed request ID: {$request_id}");
            return; // Early exit to prevent duplicate tracking
        }
        $processed_requests[$request_id] = true;
        error_log("KISMET DEBUG: NEW REQUEST - Processing request ID: {$request_id}");
        
        // ROBOTS.TXT DEBUGGING: Track all robots.txt related requests
        if (strpos($request_uri, 'robots.txt') !== false) {
            error_log("ROBOTS DEBUG [{$timestamp}]: Request intercepted - URI: {$request_uri}, User-Agent: {$user_agent}");
        }
        
        // Check for .htaccess rewrite query parameter first
        $kismet_endpoint = $_GET['kismet_endpoint'] ?? null;
        
        if ($kismet_endpoint) {
            if ($kismet_endpoint === 'robots') {
                error_log("ROBOTS DEBUG [{$timestamp}]: .htaccess rewrite detected for robots.txt");
            }
            error_log("KISMET DEBUG: Handling .htaccess rewrite for endpoint: {$kismet_endpoint}");
            $this->handle_htaccess_rewrite($kismet_endpoint, $request_uri);
            return; // IMPORTANT: Return early to prevent duplicate tracking
        }
        
        // WORDPRESS MIDDLEWARE STRATEGY: This section processes direct path matches as middleware
        // Intercepts requests and handles them before normal WordPress routing
        // COMMENTED OUT: Testing htaccess rewrite strategy vs individual endpoints only
        /*
        // Parse path without query parameters for regular requests
        $path = parse_url($request_uri, PHP_URL_PATH);
        
        // ROBOTS.TXT DEBUGGING: Track direct path matches
        if ($path === '/robots.txt') {
            error_log("ROBOTS DEBUG [{$timestamp}]: Direct path match for /robots.txt - URI: {$request_uri}");
        }
        
        // ONLY process non-rewrite requests to prevent duplicates
        // DEBUG: Log all requests to see what's being intercepted
        error_log("KISMET DEBUG: Universal Tracker intercepted request - Path: {$path}, Full URI: {$request_uri}");
        
        foreach ($this->route_mapping as $tracked_path => $event_type) {
            if ($this->path_matches($path, $tracked_path)) {
                if ($tracked_path === '/robots.txt') {
                    error_log("ROBOTS DEBUG [{$timestamp}]: Triggering tracking event for robots.txt");
                }
                error_log("KISMET DEBUG: Path matched! Tracked: {$tracked_path}, Event: {$event_type}");
                Kismet_Event_Tracker::track_endpoint_access(
                    $event_type,
                    $tracked_path,
                    array('full_request_uri' => $request_uri, 'source' => 'all_routes_wordpress_middleware_strategy')
                );
                break;
            }
        }
        */
    }
    
    /**
     * Handle requests that came through .htaccess rewrite rules
     */
    private function handle_htaccess_rewrite($endpoint, $request_uri) {
        $endpoint_mapping = array(
            'robots' => array(
                'event_type' => Kismet_Event_Types::PLUGIN_ROBOTS_TXT_ACCESS,
                'path' => '/robots.txt'
            ),
            'llms' => array(
                'event_type' => Kismet_Event_Types::PLUGIN_LLMS_TXT_ACCESS,
                'path' => '/llms.txt'
            ),
            'ai_plugin' => array(
                'event_type' => Kismet_Event_Types::PLUGIN_AI_PLUGIN_MANIFEST_ACCESS,
                'path' => '/.well-known/ai-plugin.json'
            ),
            'mcp_servers' => array(
                'event_type' => Kismet_Event_Types::PLUGIN_MCP_SERVERS_ACCESS,
                'path' => '/.well-known/mcp/servers'
            )
        );
        
        if (isset($endpoint_mapping[$endpoint])) {
            $mapping = $endpoint_mapping[$endpoint];
            error_log("KISMET DEBUG: Tracking .htaccess rewrite - Endpoint: {$endpoint}, Event: {$mapping['event_type']}");
            
            // Track the access
            Kismet_Event_Tracker::track_endpoint_access(
                $mapping['event_type'],
                $mapping['path'],
                array(
                    'full_request_uri' => $request_uri,
                    'source' => 'all_routes_htaccess_rewrite_strategy'
                )
            );
            
            // Now serve the actual content by delegating to the appropriate handler
            $this->serve_content_for_endpoint($endpoint);
        } else {
            error_log("KISMET DEBUG: Unknown .htaccess endpoint: {$endpoint}");
        }
    }
    
    /**
     * Serve the actual content for rewritten endpoints
     */
    private function serve_content_for_endpoint($endpoint) {
        switch ($endpoint) {
            case 'robots':
                $this->serve_robots_content();
                break;
                
            case 'llms':
                $this->serve_llms_content();
                break;
                
            case 'ai_plugin':
                $this->serve_ai_plugin_content();
                break;
                
            case 'mcp_servers':
                $this->serve_mcp_servers_content();
                break;
                
            default:
                // Unknown endpoint - send 404
                status_header(404);
                exit;
        }
    }

    /**
     * Serve robots.txt content
     */
    private function serve_robots_content() {
        header('Content-Type: text/plain');
        
        // Check if physical robots.txt exists first
        $robots_file = ABSPATH . 'robots.txt';
        if (file_exists($robots_file)) {
            $content = file_get_contents($robots_file);
            echo $content;
        } else {
            // Generate default WordPress robots.txt with AI enhancements
            $site_url = get_site_url();
            $content = "User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: {$site_url}/wp-sitemap.xml

# AI/LLM Discovery Section (Added by Kismet Plugin)
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
            echo $content;
        }
        exit;
    }

    /**
     * Serve llms.txt content
     */
    private function serve_llms_content() {
        header('Content-Type: text/plain');
        
        // Check if physical llms.txt exists first
        $llms_file = ABSPATH . 'llms.txt';
        if (file_exists($llms_file)) {
            $content = file_get_contents($llms_file);
            echo $content;
        } else {
            // Generate default llms.txt content
            $site_url = get_site_url();
            $content = "# LLM Usage Policy for " . get_bloginfo('name') . "

# This site provides AI-accessible content and APIs
# For AI/LLM training or interaction purposes

## Available Endpoints:
# Main API: {$site_url}/ask
# AI Plugin: {$site_url}/.well-known/ai-plugin.json
# MCP Servers: {$site_url}/.well-known/mcp/servers.json

## Usage Guidelines:
# - Respectful crawling please
# - Rate limiting may apply
# - Contact site owner for bulk access";
            echo $content;
        }
        exit;
    }

    /**
     * Serve AI Plugin manifest content
     */
    private function serve_ai_plugin_content() {
        // Check if physical file exists first
        $ai_plugin_file = ABSPATH . '.well-known/ai-plugin.json';
        if (file_exists($ai_plugin_file)) {
            header('Content-Type: application/json');
            $content = file_get_contents($ai_plugin_file);
            echo $content;
        } else {
            // Generate default AI plugin manifest
            $site_url = get_site_url();
            $manifest = array(
                "schema_version" => "v1",
                "name_for_human" => get_bloginfo('name'),
                "name_for_model" => sanitize_title(get_bloginfo('name')),
                "description_for_human" => get_bloginfo('description'),
                "description_for_model" => "AI-accessible content and services",
                "auth" => array("type" => "none"),
                "api" => array(
                    "type" => "openapi",
                    "url" => $site_url . "/ask"
                ),
                "logo_url" => $site_url . "/wp-content/uploads/favicon.ico",
                "contact_email" => get_option('admin_email'),
                "legal_info_url" => $site_url . "/privacy-policy"
            );
            header('Content-Type: application/json');
            echo json_encode($manifest, JSON_PRETTY_PRINT);
        }
        exit;
    }

    /**
     * Serve MCP servers manifest content
     */
    private function serve_mcp_servers_content() {
        // Check if physical file exists first
        $mcp_file = ABSPATH . '.well-known/mcp/servers.json';
        if (file_exists($mcp_file)) {
            header('Content-Type: application/json');
            $content = file_get_contents($mcp_file);
            echo $content;
        } else {
            // Generate default MCP servers manifest
            $site_url = get_site_url();
            $manifest = array(
                "mcpServers" => array(
                    array(
                        "name" => sanitize_title(get_bloginfo('name')),
                        "description" => "AI-accessible content services",
                        "endpoint" => $site_url . "/ask",
                        "capabilities" => array("query", "search")
                    )
                )
            );
            header('Content-Type: application/json');
            echo json_encode($manifest, JSON_PRETTY_PRINT);
        }
        exit;
    }
    
    /**
     * Check if request path matches tracked endpoint
     * Supports exact matches and pattern matching
     */
    private function path_matches($request_path, $tracked_path) {
        // Exact match
        if ($request_path === $tracked_path) {
            return true;
        }
        
        // Pattern matching for dynamic routes
        if (strpos($tracked_path, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($tracked_path, '/'));
            return preg_match('/^' . $pattern . '$/', $request_path);
        }
        
        return false;
    }
    
    /**
     * CONFIGURATION: Add new routes to track
     * 
     * If you want to track additional endpoints in the future, you can use this function
     * 
     * @param string $route      The URL pattern to start tracking (like "/new-endpoint")
     * @param string $event_type The event type name (like "PLUGIN_NEW_ENDPOINT_ACCESS") 
     */
    public static function add_route_mapping($route, $event_type) {
        self::$route_event_map[$route] = $event_type;
    }
    
    /**
     * CONFIGURATION: Get all current tracked routes
     * 
     * Returns the complete mapping of which URLs trigger which events
     * Useful for debugging or displaying configuration
     * 
     * @return array Current route to event mappings
     */
    public static function get_route_mappings() {
        return self::$route_event_map;
    }
    
    /**
     * CONFIGURATION: Stop tracking a route
     * 
     * Remove a URL from the tracking list
     * 
     * @param string $route The route to stop tracking
     */
    public static function remove_route_mapping($route) {
        unset(self::$route_event_map[$route]);
    }
} 