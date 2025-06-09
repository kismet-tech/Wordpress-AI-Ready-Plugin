<?php
/**
 * Kismet WordPress Plugin Event Types
 * 
 * Enumerated constants for all WordPress plugin events
 * Following ALL CAPS convention with PLUGIN_ prefix
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Plugin Event Types - ALL CAPS with PLUGIN_ prefix
 */
class Kismet_Event_Types {
    
    // AI Endpoint Access Events
    const PLUGIN_LLMS_TXT_ACCESS = 'PLUGIN_LLMS_TXT_ACCESS';
    const PLUGIN_ROBOTS_TXT_ACCESS = 'PLUGIN_ROBOTS_TXT_ACCESS';
    const PLUGIN_ASK_REQUEST = 'PLUGIN_ASK_REQUEST';
    const PLUGIN_AI_PLUGIN_MANIFEST_ACCESS = 'PLUGIN_AI_PLUGIN_MANIFEST_ACCESS';
    const PLUGIN_MCP_SERVERS_ACCESS = 'PLUGIN_MCP_SERVERS_ACCESS';
    
    // WordPress Plugin Lifecycle Events
    const PLUGIN_ACTIVATION = 'PLUGIN_ACTIVATION';
    const PLUGIN_DEACTIVATION = 'PLUGIN_DEACTIVATION';
    const PLUGIN_ENDPOINT_REGISTRATION = 'PLUGIN_ENDPOINT_REGISTRATION';
    
    /**
     * Get all available event types
     * @return array All plugin event type constants
     */
    public static function get_all_events() {
        return [
            // AI Endpoint Access
            self::PLUGIN_LLMS_TXT_ACCESS,
            self::PLUGIN_ROBOTS_TXT_ACCESS,
            self::PLUGIN_ASK_REQUEST,
            self::PLUGIN_AI_PLUGIN_MANIFEST_ACCESS,
            self::PLUGIN_MCP_SERVERS_ACCESS,
            
            // Plugin Lifecycle
            self::PLUGIN_ACTIVATION,
            self::PLUGIN_DEACTIVATION,
            self::PLUGIN_ENDPOINT_REGISTRATION,
        ];
    }
    
    /**
     * Check if an event type is valid
     * @param string $event_type The event type to validate
     * @return bool True if valid, false otherwise
     */
    public static function is_valid_event($event_type) {
        return in_array($event_type, self::get_all_events());
    }
    
    /**
     * Get events by category
     * @param string $category Category: 'endpoint' or 'lifecycle'
     * @return array Events in that category
     */
    public static function get_events_by_category($category) {
        switch ($category) {
            case 'endpoint':
                return [
                    self::PLUGIN_LLMS_TXT_ACCESS,
                    self::PLUGIN_ROBOTS_TXT_ACCESS,
                    self::PLUGIN_ASK_REQUEST,
                    self::PLUGIN_AI_PLUGIN_MANIFEST_ACCESS,
                    self::PLUGIN_MCP_SERVERS_ACCESS,
                ];
            case 'lifecycle':
                return [
                    self::PLUGIN_ACTIVATION,
                    self::PLUGIN_DEACTIVATION,
                    self::PLUGIN_ENDPOINT_REGISTRATION,
                ];
            default:
                return [];
        }
    }
} 