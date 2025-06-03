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

// Add AI agent permissions to robots.txt (non-destructive)
add_filter('robots_txt', 'kismet_modify_robots_txt', 10, 2);

function kismet_modify_robots_txt($output, $public) {
    // Only add our rules if the site is public
    if ($public) {
        $output .= "\n# Kismet AI integration\n";
        $output .= "User-agent: *\n";
        $output .= "Allow: /ask\n";
        $output .= "Allow: /.well-known/ai-plugin.json\n";
    }
    return $output;
}

// Add Settings link to plugin row
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kismet_add_settings_link');

function kismet_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=kismet-ai-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Register settings page
add_action('admin_menu', 'kismet_add_admin_menu');
add_action('admin_init', 'kismet_settings_init');

function kismet_add_admin_menu() {
    add_options_page(
        'Kismet AI Settings',
        'Kismet AI Settings', 
        'manage_options',
        'kismet-ai-settings',
        'kismet_settings_page'
    );
}

function kismet_settings_init() {
    register_setting('kismet_ai', 'kismet_custom_ai_plugin_url');
    register_setting('kismet_ai', 'kismet_hotel_name');
    register_setting('kismet_ai', 'kismet_hotel_description');
    register_setting('kismet_ai', 'kismet_logo_url');
    register_setting('kismet_ai', 'kismet_contact_email');
    register_setting('kismet_ai', 'kismet_legal_info_url');
    
    // Section 1: Custom JSON Override
    add_settings_section(
        'kismet_custom_json_section',
        'Option 1: Use Your Own Complete ai-plugin.json File',
        'kismet_custom_json_section_callback',
        'kismet_ai'
    );
    
    add_settings_field(
        'kismet_custom_ai_plugin_url',
        'Custom ai-plugin.json URL',
        'kismet_custom_url_render',
        'kismet_ai',
        'kismet_custom_json_section'
    );
    
    // Section 2: Individual Field Customization
    add_settings_section(
        'kismet_json_fields_section',
        'Option 2: Customize Individual ai-plugin.json Fields',
        'kismet_json_fields_section_callback',
        'kismet_ai'
    );
    
    add_settings_field(
        'kismet_hotel_name',
        'Business Name (for ai-plugin.json)',
        'kismet_hotel_name_render',
        'kismet_ai',
        'kismet_json_fields_section'
    );
    
    add_settings_field(
        'kismet_hotel_description',
        'Business Description (for ai-plugin.json)',
        'kismet_hotel_description_render',
        'kismet_ai',
        'kismet_json_fields_section'
    );
    
    add_settings_field(
        'kismet_logo_url',
        'Logo URL (for ai-plugin.json)',
        'kismet_logo_url_render',
        'kismet_ai',
        'kismet_json_fields_section'
    );
    
    add_settings_field(
        'kismet_contact_email',
        'Contact Email (for ai-plugin.json)',
        'kismet_contact_email_render',
        'kismet_ai',
        'kismet_json_fields_section'
    );
    
    add_settings_field(
        'kismet_legal_info_url',
        'Privacy/Legal Info URL (for ai-plugin.json)',
        'kismet_legal_info_url_render',
        'kismet_ai',
        'kismet_json_fields_section'
    );
}

function kismet_custom_json_section_callback() {
    echo '<p style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;">';
    echo '<strong>Advanced:</strong> If you have your own complete ai-plugin.json file hosted elsewhere, enter the URL below. ';
    echo 'This will override all other settings and proxy directly to your custom file.';
    echo '</p>';
}

function kismet_json_fields_section_callback() {
    echo '<p style="background: #f9f9f9; padding: 15px; border-left: 4px solid #72aee6; margin: 10px 0;">';
    echo '<strong>Simple Customization:</strong> Customize individual fields in the auto-generated ai-plugin.json file. ';
    echo 'Leave fields blank to use smart auto-detection. ';
    echo '<em>(These settings are ignored if you set a custom JSON URL above.)</em>';
    echo '</p>';
}

function kismet_custom_url_render() {
    $url = get_option('kismet_custom_ai_plugin_url', '');
    echo '<input type="url" name="kismet_custom_ai_plugin_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="https://yourdomain.com/custom-ai-plugin.json" />';
    echo '<p class="description">Enter the full URL to your custom ai-plugin.json file. When this is set, your site will proxy all /.well-known/ai-plugin.json requests to this URL instead of generating the JSON automatically.</p>';
}

function kismet_hotel_name_render() {
    $value = get_option('kismet_hotel_name', '');
    $site_name = get_bloginfo('name');
    $domain = parse_url(get_site_url(), PHP_URL_HOST);
    $auto_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
    
    echo '<input type="text" name="kismet_hotel_name" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($auto_name) . '" />';
    echo '<p class="description">The name of your hotel/business. Auto-detected: <strong>' . esc_html($auto_name) . '</strong></p>';
}

function kismet_hotel_description_render() {
    $value = get_option('kismet_hotel_description', '');
    $site_name = get_bloginfo('name');
    $domain = parse_url(get_site_url(), PHP_URL_HOST);
    $auto_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
    
    echo '<textarea name="kismet_hotel_description" rows="3" class="large-text" placeholder="Get information about ' . esc_attr($auto_name) . ' including amenities, pricing, availability, and booking assistance.">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">How your AI assistant should be described to users.</p>';
}

function kismet_logo_url_render() {
    $value = get_option('kismet_logo_url', '');
    echo '<input type="url" name="kismet_logo_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://your-logo-url.com/logo.png" />';
    echo '<p class="description">URL to your business logo (recommended: 512x512px PNG).</p>';
}

function kismet_contact_email_render() {
    $value = get_option('kismet_contact_email', '');
    $admin_email = get_option('admin_email');
    
    echo '<input type="email" name="kismet_contact_email" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($admin_email) . '" />';
    echo '<p class="description">Contact email for AI-related inquiries. Auto-detected: <strong>' . esc_html($admin_email) . '</strong></p>';
}

function kismet_legal_info_url_render() {
    $value = get_option('kismet_legal_info_url', '');
    $auto_url = get_site_url() . '/privacy-policy';
    
    echo '<input type="url" name="kismet_legal_info_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($auto_url) . '" />';
    echo '<p class="description">Link to your privacy policy or terms. Auto-detected: <strong>' . esc_html($auto_url) . '</strong></p>';
}

function kismet_settings_page() {
    ?>
    <div class="wrap">
        <h1>Kismet AI Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('kismet_ai');
            do_settings_sections('kismet_ai');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Add rewrite rule for /.well-known/ai-plugin.json
add_action('init', 'kismet_add_ai_plugin_rewrite');

function kismet_add_ai_plugin_rewrite() {
    add_rewrite_rule('^\.well-known/ai-plugin\.json$', 'index.php?kismet_ai_plugin=1', 'top');
}

// Handle the custom query var
add_filter('query_vars', 'kismet_add_query_vars');

function kismet_add_query_vars($vars) {
    $vars[] = 'kismet_ai_plugin';
    return $vars;
}

// Handle ai-plugin.json requests
add_action('template_redirect', 'kismet_handle_ai_plugin_request');

function kismet_handle_ai_plugin_request() {
    if (get_query_var('kismet_ai_plugin')) {
        $custom_url = get_option('kismet_custom_ai_plugin_url', '');
        
        if (!empty($custom_url)) {
            // Proxy to custom URL
            kismet_proxy_custom_ai_plugin($custom_url);
        } else {
            // Serve auto-generated JSON
            kismet_serve_generated_ai_plugin();
        }
        exit;
    }
}

function kismet_proxy_custom_ai_plugin($url) {
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        status_header(500);
        echo json_encode(['error' => 'Failed to fetch custom AI plugin JSON']);
        return;
    }
    
    $body = wp_remote_retrieve_body($response);
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    
    status_header(200);
    header('Content-Type: ' . ($content_type ?: 'application/json'));
    echo $body;
}

function kismet_serve_generated_ai_plugin() {
    $site_url = get_site_url();
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // Generate auto-detected defaults
    $domain = parse_url($site_url, PHP_URL_HOST);
    $auto_hotel_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
    
    // Use custom settings with auto-generated fallbacks
    $hotel_name = get_option('kismet_hotel_name', '') ?: $auto_hotel_name;
    $hotel_description = get_option('kismet_hotel_description', '') ?: ('Get information about ' . $auto_hotel_name . ' including amenities, pricing, availability, and booking assistance.');
    $logo_url = get_option('kismet_logo_url', '') ?: ($site_url . '/wp-content/uploads/2024/kismet-logo.png');
    $contact_email = get_option('kismet_contact_email', '') ?: $admin_email;
    $legal_info_url = get_option('kismet_legal_info_url', '') ?: ($site_url . '/privacy-policy');
    
    $ai_plugin = [
        'schema_version' => 'v1',
        'name_for_human' => $hotel_name . ' AI Assistant',
        'name_for_model' => strtolower(str_replace([' ', '-', '.'], '_', $hotel_name)) . '_assistant',
        'description_for_human' => $hotel_description,
        'description_for_model' => 'Provides hotel information for ' . $hotel_name . ' including room availability, pricing, amenities, policies, and booking assistance.',
        'auth' => [
            'type' => 'none'
        ],
        'api' => [
            'type' => 'openapi',
            'url' => $site_url . '/ask'
        ],
        'logo_url' => $logo_url,
        'contact_email' => $contact_email,
        'legal_info_url' => $legal_info_url
    ];
    
    status_header(200);
    header('Content-Type: application/json');
    echo json_encode($ai_plugin, JSON_PRETTY_PRINT);
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'kismet_flush_rewrite_rules');

function kismet_flush_rewrite_rules() {
    kismet_add_ai_plugin_rewrite();
    flush_rewrite_rules();
}

// Flush rewrite rules on deactivation  
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

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
        <div class="logo">🤖 Kismet AI</div>
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