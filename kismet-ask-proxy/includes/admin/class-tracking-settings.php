<?php
/**
 * Kismet Tracking Settings - Admin Interface
 * 
 * Handles the WordPress admin interface for configuring tracking settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Tracking_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add tracking settings to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Kismet Tracking Settings',
            'Kismet Tracking',
            'manage_options',
            'kismet-tracking-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings with WordPress
     */
    public function register_settings() {
        register_setting('kismet_tracking_settings', 'kismet_backend_endpoint', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('kismet_tracking_settings', 'kismet_enable_local_bot_filtering', array(
            'type' => 'boolean',
            'default' => false
        ));
    }
    
    /**
     * Admin settings page content
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('kismet_tracking_settings');
            
            update_option('kismet_backend_endpoint', esc_url_raw($_POST['kismet_backend_endpoint']));
            update_option('kismet_enable_local_bot_filtering', isset($_POST['kismet_enable_local_bot_filtering']));
            
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        
        $backend_endpoint = get_option('kismet_backend_endpoint', '');
        $local_filtering = get_option('kismet_enable_local_bot_filtering', false);
        ?>
        <div class="wrap">
            <h1>Kismet Tracking Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('kismet_tracking_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Backend Endpoint</th>
                        <td>
                            <input type="url" name="kismet_backend_endpoint" value="<?php echo esc_attr($backend_endpoint); ?>" class="regular-text" />
                            <p class="description">Your server endpoint to receive tracking data (e.g., https://yourapi.com/api/metrics)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Local Bot Filtering</th>
                        <td>
                            <label>
                                <input type="checkbox" name="kismet_enable_local_bot_filtering" value="1" <?php checked(1, $local_filtering); ?> />
                                Enable basic local filtering (optional)
                            </label>
                            <p class="description">
                                <strong>Disabled (default):</strong> All traffic sent to backend for sophisticated AI bot detection<br>
                                <strong>Enabled:</strong> Only obvious programmatic requests (curl, wget, etc.) sent to reduce server load<br>
                                <em>Note: Backend has comprehensive bot detection with 100+ patterns</em>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the admin settings
new Kismet_Tracking_Settings(); 