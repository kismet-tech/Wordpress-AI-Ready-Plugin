<?php
/**
 * Plugin Name: Kismet Ask Proxy
 * Description: Creates an AI-ready /ask page that serves both API requests and human visitors with Kismet branding.
 * Version: 1.0
 * Author: Kismet
 * License: GPL2+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    $request_uri = $_SERVER['REQUEST_URI'];

    // Check if this is an /ask request
    if (strpos($request_uri, '/ask') === 0) {
        
        // Determine if this is an API request or human visitor
        $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
        $is_api_request = strpos($accept_header, 'application/json') !== false;
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($is_api_request || $method === 'POST') {
            // Handle API requests - proxy to Kismet backend
            handle_api_request($request_uri);
        } else {
            // Handle human visitors - show branded page
            show_branded_page();
        }
        
        exit;
    }
});

function handle_api_request($request_uri) {
    $target_url = 'https://api.makekismet.com/ask';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Parse request data according to NLWebAskRequest DTO
    $request_data = null;
    
    if ($method === 'GET') {
        // Handle GET requests: /ask?query=what are your check-in times
        $query = $_GET['query'] ?? '';
        if (empty($query)) {
            send_error_response(400, 'Missing required parameter: query');
            return;
        }
        
        $request_data = [
            'query' => sanitize_text_field($query),
            'site' => get_site_identifier(),
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
            send_error_response(400, 'Invalid JSON in request body');
            return;
        }
        
        if (empty($json_data['query'])) {
            send_error_response(400, 'Missing required field: query');
            return;
        }
        
        $request_data = [
            'query' => sanitize_text_field($json_data['query']),
            'site' => get_site_identifier(),
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
        send_error_response(405, 'Method not allowed');
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
        send_error_response(502, 'Service unavailable');
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        status_header($response_code);
        header('Content-Type: application/json');
        echo $response_body;
    }
}

function get_site_identifier() {
    // Use the WordPress site domain as the site identifier
    return parse_url(get_site_url(), PHP_URL_HOST);
}

function send_error_response($code, $message) {
    status_header($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
}

function show_branded_page() {
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
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🤖 Kismet AI</div>
        <div class="tagline">Turning your website AI ready</div>
        <a href="https://makekismet.com" target="_blank" class="brand-link">
            Learn more at makekismet.com →
        </a>
    </div>
    
    <!-- TODO: Integrate Preact chatbot here -->
    <div style="text-align: center; color: white; margin-top: 40px; opacity: 0.7;">
        <p>Chatbot interface coming soon...</p>
    </div>
</body>
</html>';
}

// Add activation hook for logging and registration
register_activation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Activated');
    
    // Send registration notification to Kismet backend
    kismet_register_plugin_activation();
});

// Add deactivation hook for logging
register_deactivation_hook(__FILE__, function() {
    error_log('Kismet Ask Proxy Plugin Deactivated');
});

/**
 * Send plugin activation notification to Kismet backend
 */
function kismet_register_plugin_activation() {
    $site_url = get_site_url();
    
    // Determine the correct API endpoint based on environment
    // Check if we're in a local development environment
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || 
                 strpos($host, '127.0.0.1') !== false || 
                 strpos($host, '.local') !== false);
    
    // Determine backend API endpoint based on WordPress site environment
    // If WordPress site is running locally (localhost/127.0.0.1/.local), 
    // send plugin activation notifications to local backend on port 4000
    // Otherwise, send to production API at api.makekismet.com
    $api_base = $is_local ? 'https://localhost:4000' : 'https://api.makekismet.com';
    $endpoint = $api_base . '/PluginInstallation/AddPluginInstallation';
    
    $data = array(
        'siteUrl' => $site_url
    );
    
    // Send the notification
    $args = array(
        'body' => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 15,
        'sslverify' => !$is_local // Disable SSL verification for local development
    );
    
    $response = wp_remote_post($endpoint, $args);
    
    if (is_wp_error($response)) {
        error_log('Kismet Plugin Installation Tracking Error: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 201 || $response_code === 200) {
            error_log('Kismet Plugin Installation: Successfully tracked');
        } else {
            error_log('Kismet Plugin Installation Tracking Error: HTTP ' . $response_code . ' - ' . $response_body);
        }
    }
}

?> 