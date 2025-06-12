<?php
/**
 * Static File with .htaccess Strategy Implementation
 * 
 * Creates a static file and adds .htaccess rules for proper serving.
 * Best for Apache/LiteSpeed servers with .htaccess support.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Static_File_With_Htaccess {
    
    /**
     * Execute static file with .htaccess strategy
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
                'strategy' => 'static_file_with_htaccess',
                'files_created' => array(),
                'details' => array()
            );
            
            // Verify server compatibility
            if (!$plugin_instance->is_apache && !$plugin_instance->is_litespeed) {
                return array(
                    'success' => false,
                    'error' => 'Server does not support .htaccess files',
                    'server_type' => $plugin_instance->get_server_type_name()
                );
            }
            
            $server_detector = $plugin_instance->get_server_detector();
            if (!$server_detector->supports_htaccess) {
                return array(
                    'success' => false,
                    'error' => '.htaccess support not available or not writable'
                );
            }
            
            // Step 1: Create the static file
            $static_result = self::create_static_file($endpoint_path, $endpoint_data);
            if (!$static_result['success']) {
                return $static_result;
            }
            
            $result['files_created'][] = $static_result['file_path'];
            $result['details']['static_file'] = $static_result;
            
            // Step 2: Create/update .htaccess rules
            $htaccess_result = self::create_htaccess_rules($endpoint_path, $endpoint_data, $plugin_instance);
            if (!$htaccess_result['success']) {
                // Clean up static file if .htaccess fails
                self::cleanup_static_file($static_result['file_path']);
                return $htaccess_result;
            }
            
            $result['files_created'][] = $htaccess_result['file_path'];
            $result['details']['htaccess'] = $htaccess_result;
            
            // Step 3: Test the endpoint
            $test_result = self::test_endpoint($endpoint_path, $plugin_instance);
            $result['details']['test'] = $test_result;
            
            $result['success'] = true;
            $result['message'] = "Static file created with .htaccess rules";
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Static file with .htaccess strategy failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create the static file
     */
    private static function create_static_file($endpoint_path, $endpoint_data) {
        $file_path = ABSPATH . ltrim($endpoint_path, '/');
        
        // Create directory if needed
        $dir_path = dirname($file_path);
        if (!file_exists($dir_path)) {
            if (!wp_mkdir_p($dir_path)) {
                return array(
                    'success' => false,
                    'error' => "Cannot create directory: {$dir_path}"
                );
            }
        }
        
        // Get content from endpoint data
        $content = isset($endpoint_data['content']) ? $endpoint_data['content'] : '';
        if (is_callable($endpoint_data['content_generator'])) {
            $content = call_user_func($endpoint_data['content_generator']);
        }
        
        // Write file
        $result = file_put_contents($file_path, $content);
        if ($result === false) {
            return array(
                'success' => false,
                'error' => "Cannot write file: {$file_path}"
            );
        }
        
        return array(
            'success' => true,
            'file_path' => $file_path,
            'bytes_written' => $result
        );
    }
    
    /**
     * Create .htaccess rules for the endpoint
     */
    private static function create_htaccess_rules($endpoint_path, $endpoint_data, $plugin_instance) {
        $htaccess_path = ABSPATH . '.htaccess';
        
        // Determine content type
        $content_type = 'text/plain';
        if (strpos($endpoint_path, '.json') !== false) {
            $content_type = 'application/json';
        } elseif (strpos($endpoint_path, '.txt') !== false) {
            $content_type = 'text/plain';
        }
        
        // Build .htaccess rules specific to this endpoint
        $endpoint_pattern = ltrim($endpoint_path, '/');
        $endpoint_pattern = str_replace('.', '\.', $endpoint_pattern); // Escape dots
        
        $rules = array();
        $rules[] = "# Kismet Plugin: {$endpoint_path} - Static File with .htaccess";
        $rules[] = "<FilesMatch \"^" . basename($endpoint_pattern) . "$\">";
        
        // Add CORS headers if needed (especially for .well-known endpoints)
        if (strpos($endpoint_path, '.well-known') !== false) {
            $rules[] = "    Header always set Access-Control-Allow-Origin \"*\"";
            $rules[] = "    Header always set Access-Control-Allow-Methods \"GET, OPTIONS\"";
            $rules[] = "    Header always set Access-Control-Allow-Headers \"Content-Type\"";
        }
        
        // Set content type
        $rules[] = "    Header always set Content-Type \"{$content_type}\"";
        
        // Add caching headers for static files
        $rules[] = "    Header always set Cache-Control \"public, max-age=3600\"";
        
        $rules[] = "</FilesMatch>";
        $rules[] = "";
        
        $htaccess_content = implode("\n", $rules);
        
        // Read existing .htaccess content
        $existing_content = '';
        if (file_exists($htaccess_path)) {
            $existing_content = file_get_contents($htaccess_path);
        }
        
        // Check if our rules already exist
        $marker_start = "# Kismet Plugin: {$endpoint_path}";
        if (strpos($existing_content, $marker_start) !== false) {
            // Rules already exist, update them
            $pattern = '/# Kismet Plugin: ' . preg_quote($endpoint_path, '/') . '.*?(?=\n# Kismet Plugin:|$)/s';
            $new_content = preg_replace($pattern, rtrim($htaccess_content), $existing_content);
        } else {
            // Add new rules
            $new_content = $existing_content . "\n" . $htaccess_content;
        }
        
        // Write .htaccess file
        $result = file_put_contents($htaccess_path, $new_content);
        if ($result === false) {
            return array(
                'success' => false,
                'error' => "Cannot write .htaccess file: {$htaccess_path}"
            );
        }
        
        return array(
            'success' => true,
            'file_path' => $htaccess_path,
            'rules_added' => $rules,
            'bytes_written' => $result
        );
    }
    
    /**
     * Test the endpoint to verify it's working
     */
    private static function test_endpoint($endpoint_path, $plugin_instance) {
        $test_url = get_site_url() . $endpoint_path;
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'Kismet Plugin Test'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        
        return array(
            'success' => $status_code === 200,
            'status_code' => $status_code,
            'headers' => $headers,
            'test_url' => $test_url
        );
    }
    
    /**
     * Clean up static file if needed
     */
    private static function cleanup_static_file($file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    /**
     * Remove .htaccess rules for this endpoint
     */
    public static function cleanup($endpoint_path) {
        $htaccess_path = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_path)) {
            return true;
        }
        
        $content = file_get_contents($htaccess_path);
        $marker_start = "# Kismet Plugin: {$endpoint_path}";
        
        if (strpos($content, $marker_start) !== false) {
            // Remove our rules
            $pattern = '/# Kismet Plugin: ' . preg_quote($endpoint_path, '/') . '.*?(?=\n# Kismet Plugin:|\n$|$)/s';
            $new_content = preg_replace($pattern, '', $content);
            $new_content = preg_replace('/\n\n+/', "\n\n", $new_content); // Clean up extra newlines
            
            file_put_contents($htaccess_path, $new_content);
        }
        
        // Clean up static file
        $file_path = ABSPATH . ltrim($endpoint_path, '/');
        self::cleanup_static_file($file_path);
        
        return true;
    }
} 