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
     * Essential configuration section - critical settings that should be configured first
     */
    public function essential_section_callback() {
        echo '<div class="postbox" style="margin-top: 20px;">';
        echo '<div class="inside">';
        echo '<p>Configure these essential settings for your business to enable proper metrics tracking and identification.</p>';
        echo '<div style="background: #e7f3ff; border: 1px solid #72aee6; border-radius: 4px; padding: 12px; margin: 10px 0;">';
        echo '<strong>ℹ️ Important:</strong> The Client ID is used to identify your business in analytics and metrics data. While optional, it\'s highly recommended for proper tracking.';
        echo '</div>';
        
        // Render Client ID field directly inside the box
        $value = get_option('kismet_hotel_id', '');
        $is_configured = !empty($value);
        
        echo '<table class="form-table" style="margin-top: 15px;">';
        echo '<tr>';
        echo '<th scope="row"><label for="kismet_hotel_id">Client ID</label></th>';
        echo '<td>';
        echo "<input type='text' id='kismet_hotel_id' name='kismet_hotel_id' value='$value' class='regular-text' placeholder='my-business-id'>";
        
        if ($is_configured) {
            echo '<span style="color: #46b450; margin-left: 10px; font-weight: 500;">✓ Configured</span>';
        } else {
            echo '<span style="color: #dc3232; margin-left: 10px; font-weight: 500;">⚠ Not configured</span>';
        }
        
        echo '<p class="description" style="margin-top: 8px;">Unique identifier for your business (e.g., "my-business", "company-name"). This will be included in all metrics data sent to your analytics endpoint for proper tracking and identification.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '</div>';
        echo '</div>';
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
     * Initialize settings
     */
    public function settings_init() {
        // Register settings for all fields
        register_setting('kismet_ai_plugin', 'kismet_client_id');
        register_setting('kismet_ai_plugin', 'kismet_custom_ai_plugin_url');
        register_setting('kismet_ai_plugin', 'kismet_hotel_name');
        register_setting('kismet_ai_plugin', 'kismet_hotel_description');
        register_setting('kismet_ai_plugin', 'kismet_logo_url');
        register_setting('kismet_ai_plugin', 'kismet_contact_email');
        register_setting('kismet_ai_plugin', 'kismet_legal_info_url');
        
        // Essential Configuration Section
        add_settings_section(
            'kismet_ai_plugin_essential_section',
            'Essential Configuration',
            array($this, 'essential_section_callback'),
            'kismet_ai_plugin'
        );
        
        // Endpoint Status Dashboard Section
        add_settings_section(
            'kismet_ai_plugin_status_section',
            'Endpoint Status Dashboard',
            array($this, 'status_section_callback'),
            'kismet_ai_plugin'
        );
        
        // Server Environment Information Section
        add_settings_section(
            'kismet_ai_plugin_server_info_section',
            'Server Environment Information',
            array($this, 'server_info_section_callback'),
            'kismet_ai_plugin'
        );
        
        // Note: JSON Configuration section is now rendered manually in settings_page()
        // to ensure all fields appear in the same styled box
    }
    
    /**
     * Status dashboard section - now using the dedicated dashboard class
     */
    public function status_section_callback() {
        echo '<p>Real-time status of all Kismet AI endpoints. Click "Test Now" to check individual endpoints or "Test All" for a complete status check.</p>';
        $this->endpoint_dashboard->render_dashboard();
    }
    
    /**
     * Server information section callback
     */
    public function server_info_section_callback() {
        global $kismet_ask_proxy_plugin;
        
        if ($kismet_ask_proxy_plugin) {
            $server_info = $kismet_ask_proxy_plugin->get_server_info();
        } else {
            // Fallback if plugin instance not available
            $server_info = array(
                'type' => 'Unknown',
                'version' => null,
                'raw_string' => '',
                'capabilities' => array(),
                'preferred_strategy' => 'wordpress_rewrite',
                'supports_htaccess' => false,
                'supports_nginx_config' => false
            );
        }
        
        $this->render_server_info_display($server_info);
    }
    
    /**
     * Render server information display using the proper server info data structure
     */
    private function render_server_info_display($server_info) {
        echo '<div class="kismet-server-variables">';
        echo '<table class="widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">Variable</th>';
        echo '<th scope="col">Value</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        // Display server information using the proper data structure
        echo '<tr>';
        echo '<td><strong>Server Type</strong></td>';
        echo '<td><code>' . esc_html($server_info['type'] ?? 'Unknown') . '</code></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Server Version</strong></td>';
        echo '<td><code>' . esc_html($server_info['version'] ?? 'Unknown') . '</code></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Raw Server String</strong></td>';
        echo '<td><code>' . esc_html($server_info['raw_string'] ?? 'Unknown') . '</code></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Supports .htaccess</strong></td>';
        echo '<td>' . (($server_info['supports_htaccess'] ?? false) ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Supports Nginx Config</strong></td>';
        echo '<td>' . (($server_info['supports_nginx_config'] ?? false) ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Server Capabilities</strong></td>';
        echo '<td>';
        if (!empty($server_info['capabilities']) && is_array($server_info['capabilities'])) {
            foreach ($server_info['capabilities'] as $capability) {
                echo '<span style="color: green;">' . esc_html($capability) . ' ✓</span> ';
            }
        } else {
            echo '<span style="color: #666;">None detected</span>';
        }
        echo '</td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
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
                
                // Render Essential Configuration and other sections using WordPress API
                do_settings_sections('kismet_ai_plugin');
                ?>
                
                <!-- Custom JSON Configuration Box -->
                <h2>AI Plugin JSON Configuration</h2>
                <div class="kismet-json-config-box">
                    <p>Configure a custom AI plugin JSON source or use the auto-generated fields below.</p>
                    
                    <?php
                    // Show current endpoint status
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
                    ?>
                    
                    <hr style="margin: 20px 0;">
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="kismet_custom_ai_plugin_url">Custom AI Plugin JSON URL</label>
                                </th>
                                <td>
                                    <?php
                                    $value = get_option('kismet_custom_ai_plugin_url', '');
                                    echo "<input type='url' id='kismet_custom_ai_plugin_url' name='kismet_custom_ai_plugin_url' value='$value' class='regular-text' placeholder='https://example.com/custom-ai-plugin.json'>";
                                    echo '<p class="description">Leave empty to use auto-generated AI plugin JSON served as static file.</p>';
                                    ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="kismet_hotel_name">Hotel/Business Name</label>
                                </th>
                                <td>
                                    <?php
                                    $value = get_option('kismet_hotel_name', '');
                                    $placeholder = get_bloginfo('name') ?: 'Your Hotel Name';
                                    echo "<input type='text' id='kismet_hotel_name' name='kismet_hotel_name' value='$value' class='regular-text' placeholder='$placeholder'>";
                                    echo '<p class="description">Auto-detected from site name if empty.</p>';
                                    ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="kismet_hotel_description">Hotel Description</label>
                                </th>
                                <td>
                                    <?php
                                    $value = get_option('kismet_hotel_description', '');
                                    $site_name = get_bloginfo('name') ?: 'Your Hotel';
                                    $placeholder = "Get information about $site_name including amenities, pricing, availability, and booking assistance.";
                                    echo "<textarea id='kismet_hotel_description' name='kismet_hotel_description' rows='3' class='large-text' placeholder='$placeholder'>$value</textarea>";
                                    echo '<p class="description">Auto-generated description if empty.</p>';
                                    ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="kismet_logo_url">Logo URL</label>
                                </th>
                                <td>
                                    <?php
                                    $value = get_option('kismet_logo_url', '');
                                    $placeholder = get_site_url() . '/wp-content/uploads/2024/kismet-logo.png';
                                    echo "<input type='url' id='kismet_logo_url' name='kismet_logo_url' value='$value' class='regular-text' placeholder='$placeholder'>";
                                    echo '<p class="description">Logo image for AI plugin display.</p>';
                                    ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="kismet_contact_email">Contact Email</label>
                                </th>
                                <td>
                                    <?php
                                    $value = get_option('kismet_contact_email', '');
                                    $placeholder = get_option('admin_email', 'admin@example.com');
                                    echo "<input type='email' id='kismet_contact_email' name='kismet_contact_email' value='$value' class='regular-text' placeholder='$placeholder'>";
                                    echo '<p class="description">Auto-detected from admin email if empty.</p>';
                                    ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="kismet_legal_info_url">Legal/Privacy Policy URL</label>
                                </th>
                                <td>
                                    <?php
                                    $value = get_option('kismet_legal_info_url', '');
                                    $placeholder = get_site_url() . '/privacy-policy';
                                    echo "<input type='url' id='kismet_legal_info_url' name='kismet_legal_info_url' value='$value' class='regular-text' placeholder='$placeholder'>";
                                    echo '<p class="description">Link to privacy policy or legal information.</p>';
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p><strong>Note:</strong> The static file will automatically regenerate when you save changes to any field above.</p>
                </div>
                
                <style>
                .kismet-json-config-box {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .kismet-json-config-box h2 {
                    margin-top: 0;
                    color: #1d2327;
                    font-size: 1.3em;
                }
                .kismet-json-config-box .notice {
                    margin: 15px 0;
                }
                .kismet-json-config-box .form-table {
                    margin-top: 0;
                }
                </style>
                
                <?php submit_button(); ?>
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