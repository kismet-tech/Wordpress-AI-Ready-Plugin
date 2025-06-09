<?php
/**
 * Kismet Bot Classifier
 * 
 * Provides basic classification of detected bot types.
 * Minimal categorization since sophisticated analysis happens on backend.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Bot_Classifier {
    
    /**
     * Classify detected bot into basic category
     */
    public static function classify($user_agent) {
        if (empty($user_agent)) {
            return 'no_user_agent';
        }
        
        $user_agent_lower = strtolower($user_agent);
        
        // Basic classification patterns
        $classifications = [
            'curl' => ['curl/'],
            'wget' => ['wget'],
            'python_client' => ['python-requests', 'python/', 'urllib'],
            'go_client' => ['go-http-client', 'go/'],
            'java_client' => ['java/', 'apache-httpclient', 'okhttp'],
            'node_client' => ['node-fetch', 'node/', 'axios'],
            'ruby_client' => ['http.rb', 'ruby/'],
        ];
        
        foreach ($classifications as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($user_agent_lower, $pattern) !== false) {
                    return $type;
                }
            }
        }
        
        return 'unclassified_bot';
    }
    
    /**
     * Get human-readable description of bot type
     */
    public static function get_description($bot_type) {
        $descriptions = [
            'curl' => 'cURL command line tool',
            'wget' => 'Wget download utility',
            'python_client' => 'Python HTTP client',
            'go_client' => 'Go HTTP client',
            'java_client' => 'Java HTTP client',
            'node_client' => 'Node.js HTTP client',
            'ruby_client' => 'Ruby HTTP client',
            'no_user_agent' => 'Request with no user agent',
            'unclassified_bot' => 'Unclassified programmatic request',
        ];
        
        return $descriptions[$bot_type] ?? 'Unknown bot type';
    }
} 