<?php
/**
 * Metrics Sender
 * 
 * Handles sending event data to the metrics endpoint
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Metrics_Sender {
    
    /**
     * Get the configured client ID from WordPress settings
     * 
     * @return string|false Client ID if configured, false otherwise
     */
    public static function get_client_id() {
        $client_id = get_option('kismet_hotel_id', '');
        return !empty($client_id) ? $client_id : false;
    }
    
    /**
     * Get the configured hotel ID from WordPress settings (deprecated - use get_client_id)
     * 
     * @return string|false Hotel ID if configured, false otherwise
     * @deprecated Use get_client_id() instead
     */
    public static function get_hotel_id() {
        return self::get_client_id();
    }
    
    /**
     * Send endpoint request data to the metrics endpoint
     * 
     * @param string $eventType The type of event (e.g., 'page_view', 'api_request', etc.)
     * @return bool True if request was sent successfully, false otherwise
     */
    public static function send_endpoint_request_data($eventType) {
        // Check if metrics constants are defined
        if (!defined('KISMET_METRICS_BASE_URL') || !defined('KISMET_METRICS_ROUTE')) {
            error_log('KISMET METRICS: Base URL or route not defined');
            return false;
        }
        
        $metrics_url = KISMET_METRICS_BASE_URL . KISMET_METRICS_ROUTE;
        
        // Get client ID from WordPress settings
        $client_id = self::get_client_id();
        if (!$client_id) {
            error_log('KISMET METRICS: Client ID not configured in plugin settings - sending metrics without client identification');
        }
        
        // Construct event data object with all required properties
        $event_data = array(
            'eventType' => $eventType,
            'eventName' => $eventType, // TODO: Fill with appropriate event name
            'timestamp' => current_time('c'), // ISO 8601 format
            'source' => 'web', // Default to 'web' for WordPress plugin
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        );
        
        // Only include client ID if it's configured
        if ($client_id) {
            $event_data['hotelId'] = $client_id; // Note: keeping 'hotelId' key for API compatibility
        }
        
        // Send as non-blocking POST request
        $response = wp_remote_post($metrics_url, array(
            'timeout' => 2,
            'blocking' => false,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($event_data)
        ));
        
        // Log for debugging (only log success/failure, not the full payload)
        if (is_wp_error($response)) {
            error_log('KISMET METRICS: Failed to send event data - ' . $response->get_error_message());
            return false;
        } else {
            error_log('KISMET METRICS: Event data sent successfully for eventType: ' . $eventType);
            return true;
        }
    }
} 