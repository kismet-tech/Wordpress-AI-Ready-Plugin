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
        add_action('init', array($this, 'add_ai_plugin_rewrite'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_ai_plugin_request'));
        
        // Admin settings functionality
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    public function add_ai_plugin_rewrite() {
        add_rewrite_rule('^\.well-known/ai-plugin\.json$', 'index.php?kismet_ai_plugin=1', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'kismet_ai_plugin';
        return $vars;
    }
    
    public function handle_ai_plugin_request() {
        if (get_query_var('kismet_ai_plugin')) {
            $custom_url = get_option('kismet_custom_ai_plugin_url', '');
            
            if (!empty($custom_url)) {
                // Proxy to custom URL
                $this->proxy_custom_ai_plugin($custom_url);
            } else {
                // Serve auto-generated JSON
                $this->serve_generated_ai_plugin();
            }
            exit;
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