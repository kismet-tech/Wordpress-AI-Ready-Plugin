<?php
/**
 * Suggest Nginx Config Building Block
 * 
 * Generates nginx location blocks, headers, and optimization suggestions.
 * Used by static_file_with_nginx_suggestion and wordpress_rewrite_with_nginx_optimization strategies.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Suggest_Nginx_Config {
    
    /**
     * Execute nginx configuration suggestion
     * 
     * @param string $endpoint_path Endpoint path (e.g., '/.well-known/ai-plugin.json')
     * @param array $endpoint_data Data needed for the endpoint
     * @param object $plugin_instance Main plugin instance with server info
     * @return array Result with success status and details
     */
    public static function execute($endpoint_path, $endpoint_data, $plugin_instance) {
        try {
            $result = array(
                'success' => false,
                'building_block' => 'suggest_nginx_config',
                'config_suggestions' => array(),
                'details' => array()
            );
            
            // Step 1: Generate nginx config for this endpoint
            $config_result = self::generate_nginx_config($endpoint_path, $endpoint_data);
            if (!$config_result['success']) {
                return $config_result;
            }
            $result['details']['config_generation'] = $config_result;
            
            // Step 2: Create admin notice with suggestions
            $notice_result = self::create_admin_notice($endpoint_path, $config_result['config']);
            $result['details']['admin_notice'] = $notice_result;
            
            // Step 3: Store suggestions for later retrieval
            $storage_result = self::store_suggestions($endpoint_path, $config_result['config']);
            $result['details']['storage'] = $storage_result;
            
            $result['config_suggestions'] = $config_result['config'];
            $result['success'] = true;
            $result['message'] = "Nginx configuration suggestions generated";
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Suggest nginx config building block failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate nginx configuration for an endpoint
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Result with generated config
     */
    private static function generate_nginx_config($endpoint_path, $endpoint_data) {
        $config = array();
        
        // Determine file path and content type
        $file_path = ltrim($endpoint_path, '/');
        $content_type = 'text/plain';
        
        if (strpos($endpoint_path, '.json') !== false) {
            $content_type = 'application/json';
        } elseif (strpos($endpoint_path, '.txt') !== false) {
            $content_type = 'text/plain; charset=utf-8';
        }
        
        // Allow custom content type
        if (isset($endpoint_data['content_type'])) {
            $content_type = $endpoint_data['content_type'];
        }
        
        // Generate location block for static file serving
        $config['static_file_location'] = array(
            'title' => 'Static File Serving Configuration',
            'description' => 'Add this to your nginx server block to serve the file directly',
            'config' => array(
                "# Serve {$endpoint_path} directly",
                "location = /{$file_path} {",
                "    add_header Content-Type \"{$content_type}\";",
                "    add_header Cache-Control \"public, max-age=3600\";",
                "    expires 1h;",
                "}"
            )
        );
        
        // Add CORS headers if needed
        if (strpos($endpoint_path, '.well-known') !== false || 
            (isset($endpoint_data['cors_required']) && $endpoint_data['cors_required'])) {
            
            $config['cors_location'] = array(
                'title' => 'CORS Headers Configuration',
                'description' => 'Add CORS headers for cross-origin requests',
                'config' => array(
                    "# CORS headers for {$endpoint_path}",
                    "location = /{$file_path} {",
                    "    add_header Content-Type \"{$content_type}\";",
                    "    add_header Access-Control-Allow-Origin \"*\";",
                    "    add_header Access-Control-Allow-Methods \"GET, OPTIONS\";",
                    "    add_header Access-Control-Allow-Headers \"Content-Type, Authorization, X-Requested-With\";",
                    "    add_header Cache-Control \"public, max-age=3600\";",
                    "    ",
                    "    # Handle OPTIONS requests",
                    "    if (\$request_method = OPTIONS) {",
                    "        return 200;",
                    "    }",
                    "}"
                )
            );
        }
        
        // Generate WordPress fallback configuration
        $config['wordpress_fallback'] = array(
            'title' => 'WordPress Fallback Configuration',
            'description' => 'Fallback to WordPress if static file is not found',
            'config' => array(
                "# WordPress fallback for {$endpoint_path}",
                "location = /{$file_path} {",
                "    try_files \$uri @wordpress;",
                "    add_header Content-Type \"{$content_type}\";",
                "    add_header Cache-Control \"public, max-age=3600\";",
                "}",
                "",
                "# WordPress handler",
                "location @wordpress {",
                "    rewrite ^.*$ /index.php last;",
                "}"
            )
        );
        
        // Generate caching optimization
        $cache_control = 'public, max-age=3600';
        if (isset($endpoint_data['cache_control'])) {
            $cache_control = $endpoint_data['cache_control'];
        }
        
        $config['caching_optimization'] = array(
            'title' => 'Caching Optimization',
            'description' => 'Optimize caching for better performance',
            'config' => array(
                "# Caching for {$endpoint_path}",
                "location = /{$file_path} {",
                "    add_header Cache-Control \"{$cache_control}\";",
                "    expires 1h;",
                "    ",
                "    # Enable gzip compression",
                "    gzip on;",
                "    gzip_types application/json text/plain;",
                "}"
            )
        );
        
        // Add custom nginx config if provided
        if (isset($endpoint_data['nginx_config']) && is_array($endpoint_data['nginx_config'])) {
            $config['custom_config'] = array(
                'title' => 'Custom Configuration',
                'description' => 'Custom nginx configuration for this endpoint',
                'config' => $endpoint_data['nginx_config']
            );
        }
        
        return array(
            'success' => true,
            'config' => $config,
            'endpoint_path' => $endpoint_path
        );
    }
    
    /**
     * Create admin notice with nginx suggestions
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $config Generated nginx config
     * @return array Result with notice details
     */
    private static function create_admin_notice($endpoint_path, $config) {
        // Create admin notice
        add_action('admin_notices', function() use ($endpoint_path, $config) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<h3>Nginx Configuration Suggestions for ' . esc_html($endpoint_path) . '</h3>';
            echo '<p>Your server appears to be running Nginx. For optimal performance, consider adding these configurations:</p>';
            
            foreach ($config as $section_key => $section) {
                echo '<h4>' . esc_html($section['title']) . '</h4>';
                echo '<p>' . esc_html($section['description']) . '</p>';
                echo '<pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">';
                echo esc_html(implode("\n", $section['config']));
                echo '</pre>';
            }
            
            echo '<p><strong>Note:</strong> These are suggestions only. Please consult your hosting provider or system administrator before making changes to your nginx configuration.</p>';
            echo '</div>';
        });
        
        return array(
            'success' => true,
            'notice_created' => true
        );
    }
    
    /**
     * Store nginx suggestions for later retrieval
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $config Generated nginx config
     * @return array Result with storage details
     */
    private static function store_suggestions($endpoint_path, $config) {
        $option_key = 'kismet_nginx_suggestions_' . md5($endpoint_path);
        
        $suggestion_data = array(
            'endpoint_path' => $endpoint_path,
            'config' => $config,
            'generated_at' => current_time('mysql'),
            'server_info' => array(
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'site_url' => get_site_url()
            )
        );
        
        $stored = update_option($option_key, $suggestion_data);
        
        return array(
            'success' => $stored,
            'option_key' => $option_key,
            'data_size' => strlen(serialize($suggestion_data))
        );
    }
    
    /**
     * Get stored nginx suggestions for an endpoint
     * 
     * @param string $endpoint_path Endpoint path
     * @return array|false Stored suggestions or false if not found
     */
    public static function get_stored_suggestions($endpoint_path) {
        $option_key = 'kismet_nginx_suggestions_' . md5($endpoint_path);
        return get_option($option_key, false);
    }
    
    /**
     * Get all stored nginx suggestions
     * 
     * @return array All stored nginx suggestions
     */
    public static function get_all_suggestions() {
        global $wpdb;
        
        $suggestions = array();
        $option_pattern = 'kismet_nginx_suggestions_%';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $option_pattern
        ));
        
        foreach ($results as $result) {
            $data = maybe_unserialize($result->option_value);
            if (is_array($data) && isset($data['endpoint_path'])) {
                $suggestions[$data['endpoint_path']] = $data;
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Cleanup nginx suggestions (for rollback scenarios)
     * 
     * @param string $endpoint_path Endpoint path to cleanup suggestions for
     * @return array Result with success status
     */
    public static function cleanup($endpoint_path) {
        $option_key = 'kismet_nginx_suggestions_' . md5($endpoint_path);
        $deleted = delete_option($option_key);
        
        return array(
            'success' => $deleted,
            'message' => $deleted ? 'Nginx suggestions removed' : 'No suggestions found to remove',
            'option_key' => $option_key
        );
    }
    
    /**
     * Generate nginx config text for download
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $config_sections Config sections
     * @return string Formatted nginx config text
     */
    public static function generate_config_file($endpoint_path, $config_sections) {
        $output = array();
        $output[] = "# Nginx configuration for {$endpoint_path}";
        $output[] = "# Generated by Kismet AI Ready Plugin on " . date('Y-m-d H:i:s');
        $output[] = "# Please review and adapt to your specific nginx setup";
        $output[] = "";
        
        foreach ($config_sections as $section_key => $section) {
            $output[] = "# " . $section['title'];
            $output[] = "# " . $section['description'];
            $output[] = "";
            
            foreach ($section['config'] as $line) {
                $output[] = $line;
            }
            
            $output[] = "";
        }
        
        $output[] = "# End of configuration for {$endpoint_path}";
        
        return implode("\n", $output);
    }
    
    /**
     * Test if nginx optimizations are working
     * 
     * @param string $endpoint_path Endpoint path to test
     * @return array Test result
     */
    public static function test_nginx_optimizations($endpoint_path) {
        $test_url = get_site_url() . $endpoint_path;
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'Kismet Plugin Nginx Test'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'test_url' => $test_url
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        
        // Check for nginx-specific optimizations
        $optimizations = array(
            'gzip_enabled' => isset($headers['content-encoding']) && strpos($headers['content-encoding'], 'gzip') !== false,
            'cache_headers' => isset($headers['cache-control']),
            'expires_header' => isset($headers['expires']),
            'server_nginx' => isset($headers['server']) && strpos(strtolower($headers['server']), 'nginx') !== false
        );
        
        return array(
            'success' => ($response_code === 200),
            'response_code' => $response_code,
            'optimizations' => $optimizations,
            'headers' => $headers,
            'test_url' => $test_url
        );
    }
} 