<?php
/**
 * Handles /ask page functionality
 * - API proxy to Kismet backend
 * - Branded page for human visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Ask_Handler {
    
    public function __construct() {
        add_action('init', array($this, 'handle_ask_requests'));
    }
    
    public function handle_ask_requests() {
        $request_uri = $_SERVER['REQUEST_URI'];

        // Check if this is an /ask request
        if (strpos($request_uri, '/ask') === 0) {
            
            // Determine if this is an API request or human visitor
            $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
            $is_api_request = strpos($accept_header, 'application/json') !== false;
            $method = $_SERVER['REQUEST_METHOD'];
            
            if ($is_api_request || $method === 'POST') {
                // Handle API requests - proxy to Kismet backend
                $this->handle_api_request($request_uri);
            } else {
                // Handle human visitors - show branded page
                $this->show_branded_page();
            }
            
            exit;
        }
    }
    
    private function handle_api_request($request_uri) {
        $target_url = 'https://api.makekismet.com/ask';
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Parse request data according to NLWebAskRequest DTO
        $request_data = null;
        
        if ($method === 'GET') {
            // Handle GET requests: /ask?query=what are your check-in times
            $query = $_GET['query'] ?? '';
            if (empty($query)) {
                $this->send_error_response(400, 'Missing required parameter: query');
                return;
            }
            
            $request_data = [
                'query' => sanitize_text_field($query),
                'site' => $this->get_site_identifier(),
                'streaming' => isset($_GET['streaming']) ? (bool)$_GET['streaming'] : true,
            ];
            
            // Add optional parameters if present
            if (!empty($_GET['prev'])) {
                $request_data['prev'] = sanitize_text_field($_GET['prev']);
            }
            if (!empty($_GET['decontextualized_query'])) {
                $request_data['decontextualized_query'] = sanitize_text_field($_GET['decontextualized_query']);
            }
            if (!empty($_GET['query_id'])) {
                $request_data['query_id'] = sanitize_text_field($_GET['query_id']);
            }
            if (!empty($_GET['mode']) && in_array($_GET['mode'], ['list', 'summarize', 'generate'])) {
                $request_data['mode'] = $_GET['mode'];
            }
            
        } else if ($method === 'POST') {
            // Handle POST requests with JSON body
            $body = file_get_contents('php://input');
            $json_data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->send_error_response(400, 'Invalid JSON in request body');
                return;
            }
            
            if (empty($json_data['query'])) {
                $this->send_error_response(400, 'Missing required field: query');
                return;
            }
            
            $request_data = [
                'query' => sanitize_text_field($json_data['query']),
                'site' => $this->get_site_identifier(),
                'streaming' => isset($json_data['streaming']) ? (bool)$json_data['streaming'] : true,
            ];
            
            // Add optional fields if present
            if (!empty($json_data['prev'])) {
                $request_data['prev'] = sanitize_text_field($json_data['prev']);
            }
            if (!empty($json_data['decontextualized_query'])) {
                $request_data['decontextualized_query'] = sanitize_text_field($json_data['decontextualized_query']);
            }
            if (!empty($json_data['query_id'])) {
                $request_data['query_id'] = sanitize_text_field($json_data['query_id']);
            }
            if (!empty($json_data['mode']) && in_array($json_data['mode'], ['list', 'summarize', 'generate'])) {
                $request_data['mode'] = $json_data['mode'];
            }
        } else {
            $this->send_error_response(405, 'Method not allowed');
            return;
        }
        
        // Make the API request with properly formatted DTO
        $args = array(
            'method' => 'POST', // Always POST to the API
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Kismet-WordPress-Plugin/1.0'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        );
        
        $response = wp_remote_request($target_url, $args);
        
        if (is_wp_error($response)) {
            $this->send_error_response(502, 'Service unavailable');
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            status_header($response_code);
            header('Content-Type: application/json');
            echo $response_body;
        }
    }
    
    private function get_site_identifier() {
        // Use the WordPress site domain as the site identifier
        return parse_url(get_site_url(), PHP_URL_HOST);
    }
    
    private function send_error_response($code, $message) {
        status_header($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    }
    
    private function show_branded_page() {
        $site_name = get_bloginfo('name');
        
        header('Content-Type: text/html; charset=utf-8');
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - ' . esc_html($site_name) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }
        
        /* Header styling */
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
            z-index: 20;
            position: relative;
        }
        .logo { 
            font-size: 32px; 
            font-weight: bold; 
            margin-bottom: 8px; 
        }
        .tagline {
            font-size: 18px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .brand-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .brand-link:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        /* Modal overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Modal container */
        .modal-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 384px;
            animation: float 6s ease-in-out infinite;
        }
        
        /* Modal card */
        .modal-card {
            position: relative;
            background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
            border: 1px solid #d1d5db;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: shimmer 8s infinite;
        }
        
        /* Modal header */
        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid rgba(209, 213, 219, 0.5);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            text-align: center;
        }
        
        /* Modal content */
        .modal-content {
            padding: 24px;
        }
        
        /* Checklist items */
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            border: 1px solid;
            transition: all 0.2s ease;
            cursor: default;
        }
        .checklist-item:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .item-completed {
            background: rgba(240, 253, 244, 0.8);
            border-color: rgba(34, 197, 94, 0.3);
            animation: pulseGreen 4s ease-in-out infinite;
        }
        .item-pending {
            background: rgba(254, 242, 242, 0.8);
            border-color: rgba(239, 68, 68, 0.3);
            animation: pulseRed 4s ease-in-out infinite;
        }
        
        /* Status icons */
        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
            font-weight: bold;
        }
        .icon-completed {
            background: #22c55e;
            border-color: #16a34a;
            color: white;
            animation: glowGreen 2s ease-in-out infinite;
        }
        .icon-pending {
            background: #ef4444;
            border-color: #dc2626;
            color: white;
            animation: glowRed 2s ease-in-out infinite;
        }
        
        /* Item text */
        .item-text {
            font-weight: 500;
            font-size: 14px;
            color: #374151;
        }
        
        /* Background gradients */
        .bg-gradient-1 {
            position: absolute;
            top: -150px;
            right: -150px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
            border-radius: 50%;
            filter: blur(60px);
            animation: pulseSlow 10s ease-in-out infinite;
        }
        .bg-gradient-2 {
            position: absolute;
            bottom: -150px;
            left: -150px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.2), rgba(6, 182, 212, 0.2));
            border-radius: 50%;
            filter: blur(60px);
            animation: pulseSlow 10s ease-in-out infinite;
            animation-delay: 5s;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        @keyframes pulseGreen {
            0%, 100% { background-color: rgba(240, 253, 244, 0.8); }
            50% { background-color: rgba(220, 252, 231, 0.9); }
        }
        
        @keyframes pulseRed {
            0%, 100% { background-color: rgba(254, 242, 242, 0.8); }
            50% { background-color: rgba(254, 226, 226, 0.9); }
        }
        
        @keyframes glowGreen {
            0%, 100% { box-shadow: 0 0 0 rgba(34, 197, 94, 0); }
            50% { box-shadow: 0 0 8px rgba(34, 197, 94, 0.6); }
        }
        
        @keyframes glowRed {
            0%, 100% { box-shadow: 0 0 0 rgba(239, 68, 68, 0); }
            50% { box-shadow: 0 0 8px rgba(239, 68, 68, 0.6); }
        }
        
        @keyframes pulseSlow {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }
        
        /* Responsive design */
        @media (max-width: 480px) {
            .modal-container { max-width: 340px; }
            .modal-content, .modal-header { padding: 16px; }
            .logo { font-size: 28px; }
            .tagline { font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🤖 Kismet d2g AI</div>
        <div class="tagline">Turning your website AI ready</div>
        <a href="https://makekismet.com" target="_blank" class="brand-link">
            Learn more at makekismet.com →
        </a>
    </div>
    
    <div class="modal-overlay"></div>
    
    <div class="modal-container">
        <div class="modal-card">
            <div class="modal-header">
                <h2 class="modal-title">AI Ready Checklist</h2>
            </div>
            
            <div class="modal-content">
                <div class="checklist-item item-completed" style="animation-delay: 0s;">
                    <div class="status-icon icon-completed">✓</div>
                    <span class="item-text">Answering Bots</span>
                </div>
                
                <div class="checklist-item item-completed" style="animation-delay: 0.7s;">
                    <div class="status-icon icon-completed">✓</div>
                    <span class="item-text">Tracking Visits</span>
                </div>
                
                <div class="checklist-item item-completed" style="animation-delay: 1.4s;">
                    <div class="status-icon icon-completed">✓</div>
                    <span class="item-text">Optimizing AEO</span>
                </div>
                
                <div class="checklist-item item-pending" style="animation-delay: 2.1s;">
                    <div class="status-icon icon-pending">✕</div>
                    <span class="item-text">Serving Social Media</span>
                </div>
                
                <div class="checklist-item item-pending" style="animation-delay: 2.8s;">
                    <div class="status-icon icon-pending">✕</div>
                    <span class="item-text">Linking Identity Graph</span>
                </div>
                
                <div class="checklist-item item-pending" style="animation-delay: 3.5s;">
                    <div class="status-icon icon-pending">✕</div>
                    <span class="item-text">Booking Agentically</span>
                </div>
            </div>
            
            <div class="bg-gradient-1"></div>
            <div class="bg-gradient-2"></div>
        </div>
    </div>
</body>
</html>';
    }
} 