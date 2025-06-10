<?php
/**
 * Kismet Event Tracker
 * 
 * Core tracking logic for sending events to backend.
 * Coordinates with detection, classification, and metric building components.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once(plugin_dir_path(__FILE__) . 'class-event-types.php');
require_once(plugin_dir_path(__FILE__) . 'class-bot-detector.php');
require_once(plugin_dir_path(__FILE__) . 'class-bot-classifier.php');
require_once(plugin_dir_path(__FILE__) . 'class-metric-builder.php');

class Kismet_Event_Tracker {
    
    private $api_endpoint;
    
    public function __construct() {
        // Base URL configuration - easily switch between production and ngrok for testing
        // $base_url = 'https://api.makekismet.com';  // Production
        $base_url = 'https://f944-208-80-39-148.ngrok-free.app';  // Local testing via ngrok
        
        $this->api_endpoint = $base_url . '/Metrics/AddMetric';
    }
    
    /**
     * Track endpoint access events
     */
    public static function track_endpoint_access($event_type, $endpoint, $extra_data = array()) {
        // Check local filtering preference
        if (get_option('kismet_enable_local_bot_filtering', false)) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (Kismet_Bot_Detector::is_bot($user_agent)) {
                error_log("KISMET DEBUG: Bot detected by local filter, skipping: {$user_agent}");
                return; // Skip if local filtering enabled and bot detected
            }
        }
        
        $metric_data = Kismet_Metric_Builder::build($event_type, $endpoint, $extra_data);
        
        // DEBUG: Log the event being tracked
        error_log("KISMET DEBUG: Tracking event - Type: {$event_type}, Endpoint: {$endpoint}, URL: {$metric_data['url']}");
        
        self::send_to_backend($metric_data);
    }
    
    /**
     * Core tracking method - processes and sends events
     */
    public function track_event($event_type, $endpoint = '', $extra_data = array()) {
        
        if (!Kismet_Event_Types::is_valid_event($event_type)) {
            error_log("Kismet: Invalid event type: $event_type");
            return;
        }
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check if we should send this request to backend
        if (!Kismet_Bot_Detector::should_send_to_backend($user_agent)) {
            return; // Local filtering enabled and this isn't a bot
        }
        
        // Build metric data package
        $metric_data = Kismet_Metric_Builder::build($event_type, $endpoint, $extra_data);
        
        // Validate before sending
        if (!Kismet_Metric_Builder::validate($metric_data)) {
            error_log("Kismet: Invalid metric data, skipping send");
            return;
        }
        
        // Send to backend
        $this->send_to_backend($metric_data);
    }
    
    /**
     * Send metric data to backend API
     */
    private static function send_to_backend($metric_data) {
        // For local testing - switch between production and ngrok
        $base_url = 'https://f944-208-80-39-148.ngrok-free.app'; // ngrok URL
        // $base_url = 'https://api.makekismet.com'; // production URL
        
        $endpoint_url = $base_url . '/Metrics/AddMetric';
        
        // DEBUG: Log the request being sent
        error_log("KISMET DEBUG: Sending to backend - URL: {$endpoint_url}");
        error_log("KISMET DEBUG: Request data: " . json_encode($metric_data, JSON_PRETTY_PRINT));
        
        $response = wp_remote_post($endpoint_url, array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Kismet-WordPress-Plugin/1.0',
            ),
            'body' => json_encode($metric_data),
        ));
        
        // DEBUG: Log the response
        if (is_wp_error($response)) {
            error_log("KISMET DEBUG: HTTP Error: " . $response->get_error_message());
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log("KISMET DEBUG: Response - Status: {$status_code}, Body: " . substr($response_body, 0, 500));
        }
    }
} 