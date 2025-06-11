<?php
/**
 * Rewrite Rules Admin Interface
 * 
 * Provides admin interface for debugging and managing WordPress rewrite rules.
 * Only loads in admin context when settings pages are being viewed.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Rewrite_Rules_Admin {
    
    public function __construct() {
        // Only load in admin context
        if (!is_admin()) {
            return;
        }
        
        // Load the debug utility class
        require_once KISMET_ASK_PROXY_PATH . 'includes/shared/class-rewrite-rules-debug.php';
        
        // Register admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register AJAX handlers for admin actions
        Kismet_Rewrite_Rules_Debug::register_ajax_handlers();
    }
    
    /**
     * Register settings sections for rewrite rules debugging
     */
    public function register_settings() {
        // Add debug section to existing AI Plugin settings page
        add_settings_section(
            'kismet_rewrite_debug_section',
            'Rewrite Rules Debug',
            array($this, 'debug_section_callback'),
            'kismet_ai_plugin'  // Add to existing AI plugin settings page
        );
    }
    
    /**
     * Add admin menu for standalone debug page (optional)
     */
    public function add_admin_menu() {
        // Add as submenu under Kismet Ask Proxy
        add_submenu_page(
            'kismet-ai-ready-plugin',
            'Rewrite Rules Debug',
            'Debug Rules',
            'manage_options',
            'kismet-rewrite-debug',
            array($this, 'debug_page')
        );
    }
    
    /**
     * Debug section callback for settings page
     */
    public function debug_section_callback() {
        echo '<p>Monitor and debug WordPress rewrite rules to prevent conflicts and duplicate rule accumulation.</p>';
        echo $this->render_debug_interface();
    }
    
    /**
     * Standalone debug page
     */
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1>WordPress Rewrite Rules Debug</h1>
            <p>This tool helps identify and resolve rewrite rule conflicts that can cause performance issues.</p>
            <?php echo $this->render_debug_interface(); ?>
        </div>
        <?php
    }
    
    /**
     * Render the debug interface
     */
    private function render_debug_interface() {
        // Only generate the interface when actually needed
        return Kismet_Rewrite_Rules_Debug::generate_admin_display();
    }
} 