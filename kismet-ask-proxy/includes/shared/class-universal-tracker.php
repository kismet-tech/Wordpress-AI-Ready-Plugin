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
    
    public function __construct() {
        error_log("KISMET DEBUG: Universal Tracker constructor called");
        $this->setup_route_mapping();
        $this->register_hooks();
        error_log("KISMET DEBUG: Universal Tracker initialization complete");
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
        // Hook into request parsing to catch all requests that reach WordPress
        add_action('parse_request', array($this, 'intercept_request'), 1);
        error_log("KISMET DEBUG: Registered parse_request hook for Universal Tracker");
    }
    
    /**
     * Check incoming request against tracked endpoints
     */
    public function intercept_request() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check for .htaccess rewrite query parameter first
        $kismet_endpoint = $_GET['kismet_endpoint'] ?? null;
        
        if ($kismet_endpoint) {
            error_log("KISMET DEBUG: Handling .htaccess rewrite for endpoint: {$kismet_endpoint}");
            $this->handle_htaccess_rewrite($kismet_endpoint, $request_uri);
            return;
        }
        
        // Parse path without query parameters for regular requests
        $path = parse_url($request_uri, PHP_URL_PATH);
        
        // DEBUG: Log all requests to see what's being intercepted
        error_log("KISMET DEBUG: Universal Tracker intercepted request - Path: {$path}, Full URI: {$request_uri}");
        
        foreach ($this->route_mapping as $tracked_path => $event_type) {
            if ($this->path_matches($path, $tracked_path)) {
                error_log("KISMET DEBUG: Path matched! Tracked: {$tracked_path}, Event: {$event_type}");
                Kismet_Event_Tracker::track_endpoint_access(
                    $event_type,
                    $tracked_path,
                    array('full_request_uri' => $request_uri)
                );
                break;
            }
        }
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
                    'source' => 'htaccess_rewrite'
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
                // Delegate to robots handler
                if (class_exists('Kismet_Robots_Handler')) {
                    $handler = new Kismet_Robots_Handler();
                    $handler->handle_robots_request();
                }
                break;
                
            case 'llms':
                // Delegate to LLMS handler
                if (class_exists('Kismet_LLMS_Handler')) {
                    $handler = new Kismet_LLMS_Handler();
                    $handler->handle_llms_request();
                }
                break;
                
            case 'ai_plugin':
                // Delegate to AI Plugin handler
                if (class_exists('Kismet_AI_Plugin_Handler')) {
                    $handler = new Kismet_AI_Plugin_Handler();
                    $handler->handle_ai_plugin_request();
                }
                break;
                
            case 'mcp_servers':
                // Delegate to MCP handler
                if (class_exists('Kismet_MCP_Handler')) {
                    $handler = new Kismet_MCP_Handler();
                    $handler->handle_mcp_request();
                }
                break;
                
            default:
                // Unknown endpoint - send 404
                status_header(404);
                exit;
        }
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