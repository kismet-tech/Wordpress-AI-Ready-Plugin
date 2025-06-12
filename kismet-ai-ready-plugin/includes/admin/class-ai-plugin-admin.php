<?php
/**
 * AI Plugin Admin Interface
 * 
 * Handles all admin functionality for AI Plugin settings.
 * Only loads in admin context when settings pages are being viewed.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_AI_Plugin_Admin {
    
    private $endpoint_dashboard;
    
    public function __construct() {
        // Only load in admin context
        if (!is_admin()) {
            return;
        }
        
        // Initialize the endpoint status dashboard
        require_once(plugin_dir_path(__FILE__) . 'class-endpoint-status-dashboard.php');
        $this->endpoint_dashboard = new Kismet_Endpoint_Status_Dashboard();
        
        // Register admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        
        // Register AJAX handler for manual regeneration (keeping existing functionality)
        add_action('wp_ajax_kismet_regenerate_ai_plugin', array($this, 'ajax_regenerate_static_file'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Enqueue admin scripts and styles - now using the dashboard class
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load on our settings page
        if ($hook_suffix !== 'settings_page_kismet-ai-plugin-settings') {
            return;
        }
        
        // Use the dashboard class for scripts and styles
        wp_add_inline_script('jquery', $this->endpoint_dashboard->get_dashboard_script());
        wp_add_inline_style('wp-admin', $this->endpoint_dashboard->get_dashboard_styles());
    }
    
    /**
     * Add admin menu for AI Plugin settings
     */
    public function add_admin_menu() {
        add_options_page(
            'Kismet AI Plugin Settings',
            'Kismet AI Plugin',
            'manage_options',
            'kismet-ai-plugin-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register all settings and sections
     */
    public function settings_init() {
        // Register settings
        register_setting('kismet_ai_plugin', 'kismet_custom_ai_plugin_url');
        register_setting('kismet_ai_plugin', 'kismet_hotel_name');
        register_setting('kismet_ai_plugin', 'kismet_hotel_description');
        register_setting('kismet_ai_plugin', 'kismet_logo_url');
        register_setting('kismet_ai_plugin', 'kismet_contact_email');
        register_setting('kismet_ai_plugin', 'kismet_legal_info_url');
        
        // Add settings sections
        add_settings_section(
            'kismet_ai_plugin_status_section',
            'Endpoint Status Dashboard',
            array($this, 'status_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_section(
            'kismet_ai_plugin_server_info_section',
            'Server Environment Information',
            array($this, 'server_info_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_section(
            'kismet_ai_plugin_custom_json_section',
            'Custom JSON Configuration',
            array($this, 'custom_json_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_section(
            'kismet_ai_plugin_json_fields_section',
            'AI Plugin JSON Fields',
            array($this, 'json_fields_section_callback'),
            'kismet_ai_plugin'
        );
        
        // Add settings fields
        add_settings_field(
            'kismet_custom_ai_plugin_url',
            'Custom AI Plugin JSON URL',
            array($this, 'custom_url_render'),
            'kismet_ai_plugin',
            'kismet_ai_plugin_custom_json_section'
        );
        
        // JSON field settings
        $fields = array(
            'hotel_name' => 'Hotel/Business Name',
            'hotel_description' => 'Hotel Description',
            'logo_url' => 'Logo URL',
            'contact_email' => 'Contact Email',
            'legal_info_url' => 'Legal/Privacy Policy URL'
        );
        
        foreach ($fields as $field_key => $field_label) {
            add_settings_field(
                "kismet_$field_key",
                $field_label,
                array($this, $field_key . '_render'),
                'kismet_ai_plugin',
                'kismet_ai_plugin_json_fields_section'
            );
        }
    }
    
    /**
     * Status dashboard section - now using the dedicated dashboard class
     */
    public function status_section_callback() {
        echo '<p>Real-time status of all Kismet AI endpoints. Click "Test Now" to check individual endpoints or "Test All" for a complete status check.</p>';
        $this->endpoint_dashboard->render_dashboard();
    }
    
    /**
     * **NEW: Server information section - displays detected server type and recommended strategies**
     */
    public function server_info_section_callback() {
        global $kismet_ask_proxy_plugin;
        
        echo '<p>Information about your web server environment and optimal file serving strategies for AI endpoints.</p>';
        
        if ($kismet_ask_proxy_plugin) {
            $server_info = $kismet_ask_proxy_plugin->get_server_info();
            $this->render_server_info_display($server_info);
        } else {
            echo '<div class="notice notice-error"><p>❌ Server detection not available - plugin instance not found.</p></div>';
        }
    }
    
    /**
     * **NEW: Render server information display**
     */
    private function render_server_info_display($server_info) {
        global $kismet_ask_proxy_plugin;
        
        echo '<div class="kismet-server-variables">';
        echo '<table class="widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">Variable</th>';
        echo '<th scope="col">Value</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // Display all server detection variables
        echo '<tr>';
        echo '<td><strong>Server Software</strong></td>';
        echo '<td><code>' . esc_html($kismet_ask_proxy_plugin->server_software ?: 'null') . '</code></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Apache</strong></td>';
        echo '<td>' . ($kismet_ask_proxy_plugin->is_apache ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Nginx</strong></td>';
        echo '<td>' . ($kismet_ask_proxy_plugin->is_nginx ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>IIS</strong></td>';
        echo '<td>' . ($kismet_ask_proxy_plugin->is_iis ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>LiteSpeed</strong></td>';
        echo '<td>' . ($kismet_ask_proxy_plugin->is_litespeed ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Server Version</strong></td>';
        echo '<td><code>' . esc_html($kismet_ask_proxy_plugin->server_version ?: 'null') . '</code></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Supports .htaccess</strong></td>';
        echo '<td>' . ($kismet_ask_proxy_plugin->supports_htaccess ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Supports Nginx Config</strong></td>';
        echo '<td>' . ($kismet_ask_proxy_plugin->supports_nginx_config ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Server Capabilities</strong></td>';
        echo '<td>';
        if ($kismet_ask_proxy_plugin->supports_htaccess) echo '<span style="color: green;">.htaccess ✓</span> ';
        if ($kismet_ask_proxy_plugin->supports_nginx_config) echo '<span style="color: green;">nginx ✓</span> ';
        echo '</td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Custom JSON configuration section
     */
    public function custom_json_section_callback() {
        echo '<p>Configure a custom AI plugin JSON source or use auto-generated values below.</p>';
        
        // Show current endpoint status (keeping the existing functionality)
        $status = $this->get_ai_plugin_status();
        if ($status['endpoint_created']) {
            echo '<div class="notice notice-success"><p>';
            echo "✅ AI Plugin endpoint is active via <strong>{$status['creation_method']}</strong><br>";
            echo "🔗 <a href=\"{$status['endpoint_url']}\" target=\"_blank\">{$status['endpoint_url']}</a>";
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>';
            echo "⚠️ AI Plugin endpoint is not yet active. Check the status dashboard above for detailed diagnostics.";
            echo '</p></div>';
        }
    }
    
    /**
     * JSON fields section description
     */
    public function json_fields_section_callback() {
        echo '<p>These fields are used to generate the AI plugin JSON automatically when no custom URL is provided.</p>';
        echo '<p><strong>Note:</strong> The static file will automatically regenerate when you save changes to any field below.</p>';
    }
    
    /**
     * Form field renderers
     */
    public function custom_url_render() {
        $value = get_option('kismet_custom_ai_plugin_url', '');
        echo "<input type='url' name='kismet_custom_ai_plugin_url' value='$value' class='regular-text' placeholder='https://example.com/custom-ai-plugin.json'>";
        echo '<p class="description">Leave empty to use auto-generated AI plugin JSON served as static file.</p>';
    }
    
    public function hotel_name_render() {
        $value = get_option('kismet_hotel_name', '');
        $placeholder = get_bloginfo('name') ?: 'Your Hotel Name';
        echo "<input type='text' name='kismet_hotel_name' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Auto-detected from site name if empty.</p>';
    }
    
    public function hotel_description_render() {
        $value = get_option('kismet_hotel_description', '');
        $site_name = get_bloginfo('name') ?: 'Your Hotel';
        $placeholder = "Get information about $site_name including amenities, pricing, availability, and booking assistance.";
        echo "<textarea name='kismet_hotel_description' rows='3' class='large-text' placeholder='$placeholder'>$value</textarea>";
        echo '<p class="description">Auto-generated description if empty.</p>';
    }
    
    public function logo_url_render() {
        $value = get_option('kismet_logo_url', '');
        $placeholder = get_site_url() . '/wp-content/uploads/2024/kismet-logo.png';
        echo "<input type='url' name='kismet_logo_url' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Logo image for AI plugin display.</p>';
    }
    
    public function contact_email_render() {
        $value = get_option('kismet_contact_email', '');
        $placeholder = get_option('admin_email', 'admin@example.com');
        echo "<input type='email' name='kismet_contact_email' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Auto-detected from admin email if empty.</p>';
    }
    
    public function legal_info_url_render() {
        $value = get_option('kismet_legal_info_url', '');
        $placeholder = get_site_url() . '/privacy-policy';
        echo "<input type='url' name='kismet_legal_info_url' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Link to privacy policy or legal information.</p>';
    }
    
    /**
     * Settings page renderer
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Kismet AI Plugin Settings</h1>
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
    
    /**
     * AJAX handler for manual static file regeneration
     */
    public function ajax_regenerate_static_file() {
        // Security check
        if (!current_user_can('manage_options') || !check_ajax_referer('kismet_regenerate_nonce', 'nonce', false)) {
            wp_die('Unauthorized');
        }
        
        // Get the core handler and trigger regeneration
        $core_handler = $this->get_core_handler();
        if ($core_handler) {
            $result = $core_handler->regenerate_static_file();
            wp_send_json($result);
        } else {
            wp_send_json_error('Core AI Plugin Handler not available');
        }
    }
    
    /**
     * Get AI Plugin status from core handler
     */
    private function get_ai_plugin_status() {
        $core_handler = $this->get_core_handler();
        if ($core_handler) {
            return $core_handler->get_endpoint_status();
        }
        
        // Fallback status if core handler not available
        return array(
            'endpoint_created' => false,
            'creation_method' => 'handler_not_available',
            'static_file_exists' => false,
            'static_file_current' => false,
            'static_file_path' => ABSPATH . '.well-known/ai-plugin.json',
            'last_generated' => 'unknown',
            'last_settings_update' => 'unknown',
            'endpoint_url' => get_site_url() . '/.well-known/ai-plugin.json',
            'performance_note' => 'Core handler not available'
        );
    }
    
    /**
     * Get reference to core AI Plugin Handler
     */
    private function get_core_handler() {
        // Access the global plugin instance to get the handler
        global $kismet_ask_proxy_plugin;
        if ($kismet_ask_proxy_plugin && isset($kismet_ask_proxy_plugin->ai_plugin_handler)) {
            return $kismet_ask_proxy_plugin->ai_plugin_handler;
        }
        return null;
    }
} 