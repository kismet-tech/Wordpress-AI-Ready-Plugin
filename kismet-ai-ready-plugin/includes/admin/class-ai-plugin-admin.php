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
    
    public function __construct() {
        // Only load in admin context
        if (!is_admin()) {
            return;
        }
        
        // Register admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        
        // Register AJAX handler for manual regeneration
        add_action('wp_ajax_kismet_regenerate_ai_plugin', array($this, 'ajax_regenerate_static_file'));
    }
    
    /**
     * Add admin menu for AI Plugin settings
     */
    public function add_admin_menu() {
        add_submenu_page(
            'kismet-ai-ready-plugin',
            'AI Plugin Settings',
            'AI Plugin',
            'manage_options',
            'kismet-ai-plugin',
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
            'kismet_ai_plugin_static_info_section',
            'Static File Optimization',
            array($this, 'static_info_section_callback'),
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
     * Static file optimization info section
     */
    public function static_info_section_callback() {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>🚀 Performance Optimization:</strong> This plugin uses static file generation instead of dynamic PHP + database calls.<br>';
        echo '<strong>Before:</strong> Every AI discovery request triggered 15+ database operations<br>';
        echo '<strong>After:</strong> Static file served directly by web server = zero PHP execution + zero database operations<br>';
        echo '<strong>File regenerates automatically</strong> when you save changes below.';
        echo '</p></div>';
        
        $status = $this->get_ai_plugin_status();
        echo '<p><strong>Current Status:</strong></p>';
        echo '<ul>';
        echo '<li>Static file exists: ' . ($status['static_file_exists'] ? '✅ Yes' : '❌ No') . '</li>';
        echo '<li>File is current: ' . ($status['static_file_current'] ? '✅ Yes' : '⚠️ Needs regeneration') . '</li>';
        echo '<li>Creation method: <code>' . $status['creation_method'] . '</code></li>';
        echo '<li>Performance: ' . $status['performance_note'] . '</li>';
        echo '</ul>';
        
        // Manual regeneration button
        echo '<p>';
        echo '<button type="button" id="regenerate-static-file" class="button button-secondary">🔄 Regenerate Static File Now</button>';
        echo '</p>';
        
        // Add JavaScript for manual regeneration
        ?>
        <script>
        document.getElementById('regenerate-static-file').addEventListener('click', function() {
            var button = this;
            button.disabled = true;
            button.textContent = '🔄 Regenerating...';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=kismet_regenerate_ai_plugin&nonce=<?php echo wp_create_nonce('kismet_regenerate_nonce'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = '✅ Regenerated Successfully';
                    setTimeout(() => {
                        button.disabled = false;
                        button.textContent = '🔄 Regenerate Static File Now';
                    }, 3000);
                } else {
                    button.textContent = '❌ Regeneration Failed';
                    console.error('Regeneration failed:', data);
                }
            })
            .catch(error => {
                button.textContent = '❌ Error';
                console.error('Error:', error);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Custom JSON configuration section
     */
    public function custom_json_section_callback() {
        echo '<p>Configure a custom AI plugin JSON source or use auto-generated values below.</p>';
        
        // Show current endpoint status
        $status = $this->get_ai_plugin_status();
        if ($status['endpoint_created']) {
            echo '<div class="notice notice-success"><p>';
            echo "✅ AI Plugin endpoint is active via <strong>{$status['creation_method']}</strong><br>";
            echo "🔗 <a href=\"{$status['endpoint_url']}\" target=\"_blank\">{$status['endpoint_url']}</a>";
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>';
            echo "⚠️ AI Plugin endpoint is not yet active. Check the Kismet ENV page for diagnostics.";
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