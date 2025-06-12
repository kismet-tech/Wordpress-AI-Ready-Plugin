<?php
/**
 * Add Htaccess Rules Building Block
 * 
 * Handles .htaccess rule insertion, CORS headers, content-type headers, and caching rules.
 * Used by static_file_with_htaccess and wordpress_rewrite_with_htaccess_backup strategies.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Add_Htaccess_Rules {
    
    /**
     * Execute .htaccess rules addition
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
                'building_block' => 'add_htaccess_rules',
                'htaccess_path' => '',
                'rules_added' => array(),
                'details' => array()
            );
            
            // Check if .htaccess is supported
            $server_detector = $plugin_instance->get_server_detector();
            if (!$server_detector->supports_htaccess) {
                return array(
                    'success' => false,
                    'error' => '.htaccess not supported on this server configuration'
                );
            }
            
            // Get .htaccess file path
            $htaccess_path = ABSPATH . '.htaccess';
            $result['htaccess_path'] = $htaccess_path;
            
            // Step 1: Read existing .htaccess content
            $existing_content = self::read_htaccess_content($htaccess_path);
            $result['details']['existing_content'] = array(
                'exists' => $existing_content !== false,
                'length' => $existing_content !== false ? strlen($existing_content) : 0
            );
            
            // Step 2: Generate rules for this endpoint
            $rules_result = self::generate_htaccess_rules($endpoint_path, $endpoint_data);
            if (!$rules_result['success']) {
                return $rules_result;
            }
            $result['details']['rules_generation'] = $rules_result;
            
            // Step 3: Add rules to .htaccess
            $write_result = self::add_rules_to_htaccess($htaccess_path, $rules_result['rules'], $existing_content);
            if (!$write_result['success']) {
                return $write_result;
            }
            
            $result['rules_added'] = $rules_result['rules'];
            $result['details']['file_write'] = $write_result;
            $result['success'] = true;
            $result['message'] = ".htaccess rules added successfully";
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Add htaccess rules building block failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Read existing .htaccess content
     * 
     * @param string $htaccess_path Path to .htaccess file
     * @return string|false Content or false if file doesn't exist
     */
    private static function read_htaccess_content($htaccess_path) {
        if (!file_exists($htaccess_path)) {
            return '';
        }
        
        return file_get_contents($htaccess_path);
    }
    
    /**
     * Generate .htaccess rules for an endpoint
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Result with generated rules
     */
    private static function generate_htaccess_rules($endpoint_path, $endpoint_data) {
        $rules = array();
        
        // Start with comment block
        $rules[] = '# Kismet AI Ready Plugin - ' . $endpoint_path;
        $rules[] = '# Generated on ' . date('Y-m-d H:i:s');
        
        // Determine file pattern for rules
        $file_pattern = ltrim($endpoint_path, '/');
        $escaped_pattern = preg_quote($file_pattern, '/');
        
        // Add content-type rules
        if (strpos($endpoint_path, '.json') !== false) {
            $rules[] = '<Files "' . basename($file_pattern) . '">';
            $rules[] = '    Header set Content-Type "application/json"';
            $rules[] = '</Files>';
        } elseif (strpos($endpoint_path, '.txt') !== false) {
            $rules[] = '<Files "' . basename($file_pattern) . '">';
            $rules[] = '    Header set Content-Type "text/plain; charset=utf-8"';
            $rules[] = '</Files>';
        }
        
        // Add CORS headers for .well-known endpoints
        if (strpos($endpoint_path, '.well-known') !== false || 
            (isset($endpoint_data['cors_required']) && $endpoint_data['cors_required'])) {
            
            $rules[] = '<Files "' . basename($file_pattern) . '">';
            $rules[] = '    Header set Access-Control-Allow-Origin "*"';
            $rules[] = '    Header set Access-Control-Allow-Methods "GET, OPTIONS"';
            $rules[] = '    Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"';
            $rules[] = '</Files>';
        }
        
        // Add caching rules
        $cache_control = 'public, max-age=3600';
        if (isset($endpoint_data['cache_control'])) {
            $cache_control = $endpoint_data['cache_control'];
        }
        
        $rules[] = '<Files "' . basename($file_pattern) . '">';
        $rules[] = '    Header set Cache-Control "' . $cache_control . '"';
        $rules[] = '</Files>';
        
        // Add custom htaccess rules if provided
        if (isset($endpoint_data['htaccess_rules']) && is_array($endpoint_data['htaccess_rules'])) {
            $rules = array_merge($rules, $endpoint_data['htaccess_rules']);
        }
        
        // Add WordPress rewrite fallback if requested
        if (isset($endpoint_data['wordpress_rewrite_fallback']) && $endpoint_data['wordpress_rewrite_fallback']) {
            $rules[] = '';
            $rules[] = '# WordPress rewrite fallback for ' . $endpoint_path;
            $rules[] = 'RewriteEngine On';
            $rules[] = 'RewriteCond %{REQUEST_URI} ^/' . $escaped_pattern . '$';
            $rules[] = 'RewriteCond %{REQUEST_METHOD} GET';
            $rules[] = 'RewriteRule ^' . $escaped_pattern . '$ index.php?kismet_endpoint=' . urlencode($endpoint_path) . ' [L]';
        }
        
        $rules[] = '# End Kismet AI Ready Plugin - ' . $endpoint_path;
        $rules[] = '';
        
        return array(
            'success' => true,
            'rules' => $rules,
            'rule_count' => count($rules)
        );
    }
    
    /**
     * Add rules to .htaccess file
     * 
     * @param string $htaccess_path Path to .htaccess file
     * @param array $rules Rules to add
     * @param string $existing_content Existing .htaccess content
     * @return array Result with success status
     */
    private static function add_rules_to_htaccess($htaccess_path, $rules, $existing_content) {
        // Check if rules already exist
        $rules_text = implode("\n", $rules);
        if (strpos($existing_content, $rules[0]) !== false) {
            return array(
                'success' => true,
                'message' => 'Rules already exist in .htaccess',
                'action' => 'skipped'
            );
        }
        
        // Prepare new content
        $new_content = $existing_content;
        if (!empty($existing_content) && !preg_match('/\n$/', $existing_content)) {
            $new_content .= "\n";
        }
        $new_content .= $rules_text;
        
        // Write to file
        $bytes_written = file_put_contents($htaccess_path, $new_content);
        if ($bytes_written === false) {
            return array(
                'success' => false,
                'error' => "Cannot write to .htaccess file: {$htaccess_path}"
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Rules added to .htaccess successfully',
            'bytes_written' => $bytes_written,
            'action' => 'added'
        );
    }
    
    /**
     * Cleanup .htaccess rules (for rollback scenarios)
     * 
     * @param string $endpoint_path Endpoint path to cleanup rules for
     * @return array Result with success status
     */
    public static function cleanup($endpoint_path) {
        $htaccess_path = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_path)) {
            return array(
                'success' => true,
                'message' => '.htaccess file does not exist, no cleanup needed'
            );
        }
        
        $content = file_get_contents($htaccess_path);
        if ($content === false) {
            return array(
                'success' => false,
                'error' => 'Cannot read .htaccess file for cleanup'
            );
        }
        
        // Find and remove our rules block
        $start_marker = '# Kismet AI Ready Plugin - ' . $endpoint_path;
        $end_marker = '# End Kismet AI Ready Plugin - ' . $endpoint_path;
        
        $start_pos = strpos($content, $start_marker);
        if ($start_pos === false) {
            return array(
                'success' => true,
                'message' => 'No rules found for this endpoint in .htaccess'
            );
        }
        
        $end_pos = strpos($content, $end_marker, $start_pos);
        if ($end_pos === false) {
            return array(
                'success' => false,
                'error' => 'Found start marker but not end marker in .htaccess'
            );
        }
        
        // Remove the rules block
        $end_pos += strlen($end_marker) + 1; // Include the newline after end marker
        $new_content = substr($content, 0, $start_pos) . substr($content, $end_pos);
        
        // Write back to file
        if (file_put_contents($htaccess_path, $new_content) === false) {
            return array(
                'success' => false,
                'error' => 'Cannot write cleaned content back to .htaccess'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Rules removed from .htaccess successfully'
        );
    }
    
    /**
     * Validate .htaccess write permissions
     * 
     * @return array Validation result
     */
    public static function validate_htaccess_permissions() {
        $htaccess_path = ABSPATH . '.htaccess';
        
        $validation = array(
            'htaccess_path' => $htaccess_path,
            'file_exists' => false,
            'can_write' => false,
            'can_read' => false
        );
        
        $validation['file_exists'] = file_exists($htaccess_path);
        
        if ($validation['file_exists']) {
            $validation['can_read'] = is_readable($htaccess_path);
            $validation['can_write'] = is_writable($htaccess_path);
        } else {
            // Test if we can create the file
            $validation['can_write'] = is_writable(ABSPATH);
        }
        
        $validation['success'] = $validation['can_write'];
        
        return $validation;
    }
    
    /**
     * Test if .htaccess rules are working
     * 
     * @param string $endpoint_path Endpoint path to test
     * @return array Test result
     */
    public static function test_htaccess_rules($endpoint_path) {
        $test_url = get_site_url() . $endpoint_path;
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'Kismet Plugin .htaccess Test'
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
        
        // Check for expected headers
        $expected_headers = array();
        if (strpos($endpoint_path, '.json') !== false) {
            $expected_headers['content-type'] = 'application/json';
        }
        if (strpos($endpoint_path, '.well-known') !== false) {
            $expected_headers['access-control-allow-origin'] = '*';
        }
        
        $headers_match = true;
        foreach ($expected_headers as $header => $expected_value) {
            if (!isset($headers[$header]) || strpos($headers[$header], $expected_value) === false) {
                $headers_match = false;
                break;
            }
        }
        
        return array(
            'success' => ($response_code === 200 && $headers_match),
            'response_code' => $response_code,
            'headers_match' => $headers_match,
            'headers' => $headers,
            'test_url' => $test_url
        );
    }
} 