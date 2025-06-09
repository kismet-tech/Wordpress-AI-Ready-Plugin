<?php
/**
 * Kismet Bot Detector
 * 
 * Handles detection of programmatic requests and basic bot identification.
 * Focuses on obvious patterns to reduce backend load when local filtering is enabled.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Bot_Detector {
    
    /**
     * Detect if a user agent indicates a programmatic request
     */
    public static function is_bot($user_agent) {
        if (empty($user_agent)) {
            return true; // No user agent = likely programmatic
        }
        
        $user_agent_lower = strtolower($user_agent);
        
        // Only catch obvious programmatic requests
        // Leave sophisticated detection to backend
        $obvious_patterns = [
            'curl/',
            'wget',
            'python-requests',
            'go-http-client',
            'java/',
            'node-fetch',
            'http.rb',
            'apache-httpclient',
        ];
        
        foreach ($obvious_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if local bot filtering should be applied
     */
    public static function should_filter_locally() {
        return get_option('kismet_enable_local_bot_filtering', false);
    }
    
    /**
     * Determine if request should be sent to backend
     */
    public static function should_send_to_backend($user_agent) {
        $local_filtering_enabled = self::should_filter_locally();
        
        if (!$local_filtering_enabled) {
            return true; // Send everything when local filtering is disabled
        }
        
        return self::is_bot($user_agent); // Only send bots when local filtering is enabled
    }
} 