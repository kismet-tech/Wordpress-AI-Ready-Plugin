<?php
/**
 * Reusable Endpoint Tracking Helper
 * 
 * Provides a composable method for individual endpoint handlers
 * to track access before calling exit() and terminating execution.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Endpoint_Tracking_Helper {
    
    /**
     * Track endpoint access from any handler
     * 
     * Call this method before exit() in individual endpoint handlers
     * to ensure tracking happens before execution terminates.
     * 
     * @param string $event_type Event type constant (e.g., 'PLUGIN_LLMS_TXT_ACCESS')
     * @param string $endpoint_path The endpoint path (e.g., '/llms.txt')
     * @param array $extra_data Optional additional data to include
     */
    public static function track_before_exit($event_type, $endpoint_path, $extra_data = array()) {
        // Safety check - ensure Event Tracker exists
        if (!class_exists('Kismet_Event_Tracker')) {
            error_log("KISMET WARNING: Event Tracker not available for endpoint: {$endpoint_path}");
            return;
        }
        
        // Add debug log to confirm this helper is being called
        error_log("KISMET DEBUG: Endpoint Tracking Helper called - Event: {$event_type}, Path: {$endpoint_path}");
        
        // Track the endpoint access
        Kismet_Event_Tracker::track_endpoint_access($event_type, $endpoint_path, $extra_data);
    }
    
    /**
     * Convenience method that maps common endpoint paths to their event types
     * 
     * @param string $endpoint_path The endpoint path (e.g., '/llms.txt')
     * @param array $extra_data Optional additional data to include
     */
    public static function track_standard_endpoint($endpoint_path, $extra_data = array()) {
        // Map endpoints to their event types
        $endpoint_mapping = array(
            '/llms.txt' => Kismet_Event_Types::PLUGIN_LLMS_TXT_ACCESS,
            '/robots.txt' => Kismet_Event_Types::PLUGIN_ROBOTS_TXT_ACCESS,
            '/ask' => Kismet_Event_Types::PLUGIN_ASK_REQUEST,
            '/.well-known/ai-plugin.json' => Kismet_Event_Types::PLUGIN_AI_PLUGIN_MANIFEST_ACCESS,
            '/.well-known/mcp/servers.json' => Kismet_Event_Types::PLUGIN_MCP_SERVERS_ACCESS,
        );
        
        if (isset($endpoint_mapping[$endpoint_path])) {
            self::track_before_exit($endpoint_mapping[$endpoint_path], $endpoint_path, $extra_data);
        } else {
            error_log("KISMET WARNING: Unknown endpoint path for tracking: {$endpoint_path}");
        }
    }
} 