<?php
/**
 * LOCAL PREVIEW for WordPress Plugin Development
 * This file allows you to see the branded /ask page locally
 * 
 * Run: npm run preview-plugin
 * Visit: http://localhost:8000
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions that the plugin expects
function get_bloginfo($field) {
    return "The Knollcroft"; // Test hotel name
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function sanitize_text_field($str) {
    // Simple sanitization for preview - strip tags and trim
    return trim(strip_tags($str));
}

function get_site_url() {
    // For preview, return localhost
    return 'http://localhost:8000';
}

function wp_remote_request($url, $args) {
    // Make real HTTP request using cURL
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Set method
    if (isset($args['method'])) {
        if ($args['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($args['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
            }
        }
    }
    
    // Set headers
    if (isset($args['headers'])) {
        $headers = [];
        foreach ($args['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response_body = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return [
        'response_code' => $response_code,
        'body' => $response_body
    ];
}

function status_header($code) {
    http_response_code($code);
}

function wp_remote_retrieve_response_code($response) {
    return $response['response_code'] ?? 200;
}

function wp_remote_retrieve_body($response) {
    return $response['body'] ?? '';
}

function is_wp_error($response) {
    return isset($response['error']);
}

function register_activation_hook($file, $function) {
    // Mock WordPress function - do nothing in preview
}

function register_deactivation_hook($file, $function) {
    // Mock WordPress function - do nothing in preview
}

function add_action($hook, $function, $priority = 10, $accepted_args = 1) {
    // Mock WordPress function - for preview, we'll execute init actions immediately
    if ($hook === 'init' && is_callable($function)) {
        call_user_func($function);
    }
}

try {
    // Import the actual plugin function
    require_once 'kismet-ai-ready-plugin/kismet-ai-ready-plugin.php';
    
    if (function_exists('show_branded_page')) {
        // Call the branded page function directly
        show_branded_page();
    } else {
        echo "Error: Plugin function not found";
    }
    
} catch (Exception $e) {
    echo "Error loading preview: " . $e->getMessage();
}
?> 