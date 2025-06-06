<?php
/**
 * Kismet Plugin Detector - WordPress plugin and configuration detection
 *
 * Handles detection of security plugins, caching plugins, and multisite configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Plugin_Detector {
    
    /**
     * Scan for installed security plugins that might interfere
     * 
     * @return array Security plugin detection results
     */
    public function scan_security_plugins() {
        $security_plugins = array(
            'wordfence/wordfence.php' => 'Wordfence Security',
            'sucuri-scanner/sucuri.php' => 'Sucuri Security',
            'better-wp-security/better-wp-security.php' => 'iThemes Security',
            'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security'
        );
        
        $detected_plugins = array();
        
        foreach ($security_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_plugins[] = array(
                    'name' => $plugin_name,
                    'file' => $plugin_file
                );
            }
        }
        
        return array(
            'status' => empty($detected_plugins) ? 'none_detected' : 'plugins_detected',
            'detected_plugins' => $detected_plugins,
            'requires_configuration' => !empty($detected_plugins)
        );
    }
    
    /**
     * Identify caching plugins that need configuration
     * 
     * @return array Caching plugin detection results
     */
    public function identify_caching_plugins() {
        $caching_plugins = array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache'
        );
        
        $detected_plugins = array();
        
        foreach ($caching_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_plugins[] = array(
                    'name' => $plugin_name,
                    'file' => $plugin_file,
                    'needs_exclusion' => true
                );
            }
        }
        
        return array(
            'status' => empty($detected_plugins) ? 'none_detected' : 'plugins_detected',
            'detected_plugins' => $detected_plugins,
            'requires_exclusion_rules' => !empty($detected_plugins)
        );
    }
    
    /**
     * Check WordPress multisite configuration
     * 
     * @return array Multisite configuration results
     */
    public function check_multisite_configuration() {
        $is_multisite = is_multisite();
        $multisite_config = array();
        
        if ($is_multisite) {
            $multisite_config = array(
                'is_subdomain' => defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL,
                'is_subdirectory' => defined('SUBDOMAIN_INSTALL') && !SUBDOMAIN_INSTALL,
                'network_admin' => current_user_can('manage_network'),
                'current_site_id' => get_current_blog_id(),
                'main_site_id' => get_main_site_id()
            );
        }
        
        return array(
            'status' => $is_multisite ? 'multisite_detected' : 'single_site',
            'is_multisite' => $is_multisite,
            'configuration' => $multisite_config,
            'special_handling_required' => $is_multisite
        );
    }
} 