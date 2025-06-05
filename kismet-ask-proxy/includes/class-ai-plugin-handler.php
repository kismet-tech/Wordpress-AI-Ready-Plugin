<?php
/**
 * Handles ai-plugin.json functionality
 * - URL rewrite rules for /.well-known/ai-plugin.json
 * - Custom JSON proxy or auto-generated JSON
 * - Settings integration and admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_AI_Plugin_Handler {
    
    public function __construct() {
        // Try both approaches: traditional rewrite rules AND direct request interception
        add_action('init', array($this, 'add_ai_plugin_rewrite'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // EARLIEST possible hook - test if WordPress even sees the request
        add_action('muplugins_loaded', array($this, 'very_early_intercept'));
        
        // Direct request interception - catches requests before WordPress routing
        add_action('parse_request', array($this, 'intercept_ai_plugin_request'));
        
        // Fallback handler for rewrite rule approach
        add_action('template_redirect', array($this, 'handle_ai_plugin_request'));
        
        // Admin settings functionality
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    public function add_ai_plugin_rewrite() {
        error_log('KISMET DEBUG: add_ai_plugin_rewrite() called');
        
        // More robust rewrite rule with optional trailing slash
        add_rewrite_rule('\.well-known/ai-plugin\.json/?$', 'index.php?kismet_ai_plugin=1', 'top');
        
        error_log('KISMET DEBUG: Rewrite rule added for \.well-known/ai-plugin\.json/?$');
        
        // Debug: Let's see what rewrite rules WordPress has after our addition
        global $wp_rewrite;
        if (isset($wp_rewrite->rules)) {
            $our_rule_found = false;
            foreach ($wp_rewrite->rules as $pattern => $rewrite) {
                if (strpos($pattern, 'well-known') !== false || strpos($pattern, 'ai-plugin') !== false) {
                    error_log("KISMET DEBUG: Found related rule: '$pattern' => '$rewrite'");
                    $our_rule_found = true;
                }
            }
            if (!$our_rule_found) {
                error_log('KISMET DEBUG: No ai-plugin related rules found in wp_rewrite->rules');
            }
        } else {
            error_log('KISMET DEBUG: wp_rewrite->rules is not set');
        }
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'kismet_ai_plugin';
        return $vars;
    }
    
    /**
     * Very early intercept - runs as soon as WordPress starts loading
     */
    public function very_early_intercept() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Log ALL requests to see if .well-known requests reach WordPress
        error_log("KISMET DEBUG: very_early_intercept - WordPress processing URI: $request_uri");
        
        // Check if this is a request for our ai-plugin.json
        if (preg_match('#^/\.well-known/ai-plugin\.json/?(\?.*)?$#', $request_uri)) {
            error_log("KISMET DEBUG: VERY EARLY - Detected .well-known/ai-plugin.json request!");
            
            // Don't serve here - just confirm WordPress sees the request
            // Let other hooks handle the actual serving
        }
    }
    
    /**
     * Direct request interception - catches /.well-known/ai-plugin.json before WordPress routing
     */
    public function intercept_ai_plugin_request($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        error_log("KISMET DEBUG: intercept_ai_plugin_request called for URI: $request_uri");
        
        // Check if this is a request for our ai-plugin.json
        if (preg_match('#^/\.well-known/ai-plugin\.json/?(\?.*)?$#', $request_uri)) {
            error_log("KISMET DEBUG: Intercepted .well-known/ai-plugin.json request directly");
            
            $custom_url = get_option('kismet_custom_ai_plugin_url', '');
            
            if (!empty($custom_url)) {
                error_log("KISMET DEBUG: Using custom URL proxy (intercepted)");
                $this->proxy_custom_ai_plugin($custom_url);
            } else {
                error_log("KISMET DEBUG: Serving generated JSON (intercepted)");
                $this->serve_generated_ai_plugin();
            }
            exit;
        }
    }
    
    public function handle_ai_plugin_request() {
        $query_var = get_query_var('kismet_ai_plugin');
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        error_log("KISMET DEBUG: handle_ai_plugin_request called for URI: $request_uri");
        error_log("KISMET DEBUG: kismet_ai_plugin query var = " . ($query_var ? 'TRUE' : 'FALSE'));
        
        if ($query_var) {
            error_log("KISMET DEBUG: Serving ai-plugin.json");
            
            $custom_url = get_option('kismet_custom_ai_plugin_url', '');
            
            if (!empty($custom_url)) {
                error_log("KISMET DEBUG: Using custom URL proxy");
                // Proxy to custom URL
                $this->proxy_custom_ai_plugin($custom_url);
            } else {
                error_log("KISMET DEBUG: Serving generated JSON");
                // Serve auto-generated JSON
                $this->serve_generated_ai_plugin();
            }
            exit;
        } else {
            // Only log this for .well-known requests to avoid spam
            if (strpos($request_uri, 'well-known') !== false) {
                error_log("KISMET DEBUG: .well-known request but query var not detected");
            }
        }
    }
    
    private function proxy_custom_ai_plugin($url) {
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
    
    private function serve_generated_ai_plugin() {
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
    
    public function flush_rewrite_rules() {
        $this->add_ai_plugin_rewrite();
        flush_rewrite_rules();
    }
    
    // === ADMIN SETTINGS FUNCTIONALITY ===
    
    public function add_admin_menu() {
        add_options_page(
            'Kismet AI Plugin Settings',
            'Kismet AI Plugin', 
            'manage_options',
            'kismet-ai-plugin-settings',
            array($this, 'settings_page')
        );
    }
    
    public function settings_init() {
        register_setting('kismet_ai_plugin', 'kismet_custom_ai_plugin_url');
        register_setting('kismet_ai_plugin', 'kismet_hotel_name');
        register_setting('kismet_ai_plugin', 'kismet_hotel_description');
        register_setting('kismet_ai_plugin', 'kismet_logo_url');
        register_setting('kismet_ai_plugin', 'kismet_contact_email');
        register_setting('kismet_ai_plugin', 'kismet_legal_info_url');
        
        // Section 1: Custom JSON Override
        add_settings_section(
            'kismet_custom_json_section',
            'Option 1: Use Your Own Complete ai-plugin.json File',
            array($this, 'custom_json_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_field(
            'kismet_custom_ai_plugin_url',
            'Custom ai-plugin.json URL',
            array($this, 'custom_url_render'),
            'kismet_ai_plugin',
            'kismet_custom_json_section'
        );
        
        // Section 2: Individual Field Customization
        add_settings_section(
            'kismet_json_fields_section',
            'Option 2: Customize Individual ai-plugin.json Fields',
            array($this, 'json_fields_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_field(
            'kismet_hotel_name',
            'Business Name',
            array($this, 'hotel_name_render'),
            'kismet_ai_plugin',
            'kismet_json_fields_section'
        );
        
        add_settings_field(
            'kismet_hotel_description',
            'Business Description',
            array($this, 'hotel_description_render'),
            'kismet_ai_plugin',
            'kismet_json_fields_section'
        );
        
        add_settings_field(
            'kismet_logo_url',
            'Logo URL',
            array($this, 'logo_url_render'),
            'kismet_ai_plugin',
            'kismet_json_fields_section'
        );
        
        add_settings_field(
            'kismet_contact_email',
            'Contact Email',
            array($this, 'contact_email_render'),
            'kismet_ai_plugin',
            'kismet_json_fields_section'
        );
        
        add_settings_field(
            'kismet_legal_info_url',
            'Privacy/Legal Info URL',
            array($this, 'legal_info_url_render'),
            'kismet_ai_plugin',
            'kismet_json_fields_section'
        );
    }
    
    public function custom_json_section_callback() {
        echo '<p style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;">';
        echo '<strong>Advanced:</strong> If you have your own complete ai-plugin.json file hosted elsewhere, enter the URL below. ';
        echo 'This will override all other settings and proxy directly to your custom file.';
        echo '</p>';
    }
    
    public function json_fields_section_callback() {
        echo '<p style="background: #f9f9f9; padding: 15px; border-left: 4px solid #72aee6; margin: 10px 0;">';
        echo '<strong>Simple Customization:</strong> Customize individual fields in the auto-generated ai-plugin.json file. ';
        echo 'Leave fields blank to use smart auto-detection. ';
        echo '<em>(These settings are ignored if you set a custom JSON URL above.)</em>';
        echo '</p>';
    }
    
    public function custom_url_render() {
        $url = get_option('kismet_custom_ai_plugin_url', '');
        echo '<input type="url" name="kismet_custom_ai_plugin_url" value="' . esc_attr($url) . '" class="regular-text" placeholder="https://yourdomain.com/custom-ai-plugin.json" />';
        echo '<p class="description">Enter the full URL to your custom ai-plugin.json file. When this is set, your site will proxy all /.well-known/ai-plugin.json requests to this URL instead of generating the JSON automatically.</p>';
    }
    
    public function hotel_name_render() {
        $value = get_option('kismet_hotel_name', '');
        $site_name = get_bloginfo('name');
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $auto_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
        
        echo '<input type="text" name="kismet_hotel_name" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($auto_name) . '" />';
        echo '<p class="description">The name of your hotel/business. Auto-detected: <strong>' . esc_html($auto_name) . '</strong></p>';
    }
    
    public function hotel_description_render() {
        $value = get_option('kismet_hotel_description', '');
        $site_name = get_bloginfo('name');
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $auto_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
        
        echo '<textarea name="kismet_hotel_description" rows="3" class="large-text" placeholder="Get information about ' . esc_attr($auto_name) . ' including amenities, pricing, availability, and booking assistance.">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">How your AI assistant should be described to users.</p>';
    }
    
    public function logo_url_render() {
        $value = get_option('kismet_logo_url', '');
        echo '<input type="url" name="kismet_logo_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://your-logo-url.com/logo.png" />';
        echo '<p class="description">URL to your business logo (recommended: 512x512px PNG).</p>';
    }
    
    public function contact_email_render() {
        $value = get_option('kismet_contact_email', '');
        $admin_email = get_option('admin_email');
        
        echo '<input type="email" name="kismet_contact_email" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($admin_email) . '" />';
        echo '<p class="description">Contact email for AI-related inquiries. Auto-detected: <strong>' . esc_html($admin_email) . '</strong></p>';
    }
    
    public function legal_info_url_render() {
        $value = get_option('kismet_legal_info_url', '');
        $auto_url = get_site_url() . '/privacy-policy';
        
        echo '<input type="url" name="kismet_legal_info_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($auto_url) . '" />';
        echo '<p class="description">Link to your privacy policy or terms. Auto-detected: <strong>' . esc_html($auto_url) . '</strong></p>';
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Kismet AI Plugin Settings</h1>
            <p>Configure how your site serves the <code>/.well-known/ai-plugin.json</code> file for AI agent discovery.</p>
            <form action="options.php" method="post">
                <?php
                settings_fields('kismet_ai_plugin');
                do_settings_sections('kismet_ai_plugin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
} 