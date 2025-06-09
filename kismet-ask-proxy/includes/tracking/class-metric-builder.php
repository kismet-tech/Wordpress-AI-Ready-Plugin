<?php
/**
 * Kismet Metric Builder
 * 
 * Builds structured metric data packages for backend submission.
 * Handles data formatting and payload construction.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Metric_Builder {
    
    /**
     * Build metric data package for backend submission
     */
    public static function build($event_type, $endpoint = '', $extra_data = array()) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = Kismet_Bot_Detector::is_bot($user_agent);
        $bot_type = $is_bot ? Kismet_Bot_Classifier::classify($user_agent) : null;
        
        // Build full URL instead of just path for backend validation
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $full_url = $protocol . '://' . $host . $endpoint;
        
        return array(
            'eventType' => $event_type,
            'source' => 'wordpress_plugin',
            'userAgent' => $user_agent,
            'url' => $full_url,  // Full URL instead of path
            'ipAddress' => self::get_client_ip(),
            'botDetected' => $is_bot,
            'botType' => $bot_type,
            'payload' => array_merge(array(
                'endpoint' => $endpoint,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'timestamp' => current_time('mysql'),
                'local_filtering_enabled' => get_option('kismet_enable_local_bot_filtering', false) ? 'true' : 'false',
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
                'request_time' => time(),
            ), $extra_data)
        );
    }
    
    /**
     * Validate metric data before sending
     */
    public static function validate($metric_data) {
        $required_fields = ['eventType', 'source', 'userAgent', 'payload'];
        
        foreach ($required_fields as $field) {
            if (!isset($metric_data[$field])) {
                error_log("Kismet: Missing required field '$field' in metric data");
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get client IP address with proxy support
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
} 