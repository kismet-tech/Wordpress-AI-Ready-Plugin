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
     * Get the configured hotel ID from WordPress settings
     * 
     * @return string|false Hotel ID if configured, false otherwise
     */
    public static function get_hotel_id() {
        $hotel_id = get_option('kismet_hotel_id', '');
        return !empty($hotel_id) ? $hotel_id : false;
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
        
        // Get hotel ID from WordPress settings (optional)
        $hotel_id = self::get_hotel_id();
        if (!$hotel_id) {
            error_log('KISMET METRICS: Hotel ID not configured in plugin settings - sending metrics without hotel ID');
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
        
        // Add hotel ID only if it's configured
        if ($hotel_id) {
            $event_data['hotelId'] = $hotel_id;
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