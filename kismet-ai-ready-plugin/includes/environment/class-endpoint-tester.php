<?php
/**
 * Kismet Endpoint Tester - Real HTTP endpoint testing
 *
 * Tests actual endpoints with HTTP requests to provide real status information
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Endpoint_Tester {
    
    /**
     * Test an actual endpoint with HTTP request
     * 
     * @param string $url The endpoint URL to test
     * @return array Test results with real HTTP data
     */
    public function test_real_endpoint($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'Kismet Environment Detector'
        ));
        
        if (is_wp_error($response)) {
            return array(
                'is_working' => false,
                'status_icon' => '❌ Connection failed',
                'result_text' => 'Error: ' . $response->get_error_message(),
                'what_exists' => 'Could not connect to endpoint',
                'http_code' => null,
                'response_time' => null
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        
        if ($response_code === 200) {
            // Additional content validation for specific endpoints
            $content_valid = true;
            $content_info = '';
            
            if (strpos($url, 'ai-plugin.json') !== false) {
                $json_data = json_decode($response_body, true);
                $content_valid = is_array($json_data) && isset($json_data['schema_version']);
                $content_info = $content_valid ? 'Valid AI plugin JSON' : 'Invalid JSON format';
            } elseif (strpos($url, 'llms.txt') !== false) {
                $content_valid = strpos($response_body, 'Model instructions') !== false || 
                                strpos($response_body, 'MCP-SERVER') !== false ||
                                strlen(trim($response_body)) > 0;
                $content_info = $content_valid ? 'LLMS.txt content found' : 'Empty or invalid content';
            } elseif (strpos($url, '/ask') !== false) {
                // For /ask endpoint, any 200 response is good (it's an API endpoint)
                $content_info = 'Ask endpoint responding';
            }
            
            return array(
                'is_working' => $content_valid,
                'status_icon' => $content_valid ? '✅ Working' : '⚠️ Responding but invalid content',
                'result_text' => "HTTP 200 OK - {$content_info}",
                'what_exists' => $content_valid ? 'Endpoint active and serving content' : 'Endpoint exists but content issue',
                'http_code' => $response_code,
                'content_length' => strlen($response_body),
                'content_type' => isset($headers['content-type']) ? $headers['content-type'] : 'unknown'
            );
        } elseif ($response_code === 404) {
            return array(
                'is_working' => false,
                'status_icon' => '❌ Not found',
                'result_text' => 'HTTP 404 Not Found',
                'what_exists' => 'Endpoint does not exist',
                'http_code' => $response_code,
                'server_info' => $this->extract_server_info($headers)
            );
        } else {
            return array(
                'is_working' => false,
                'status_icon' => '❌ Error',
                'result_text' => "HTTP {$response_code}",
                'what_exists' => "Server returned error code {$response_code}",
                'http_code' => $response_code,
                'server_info' => $this->extract_server_info($headers)
            );
        }
    }
    
    /**
     * Get simple endpoint status summary with REAL endpoint testing
     * 
     * @return array Simple status for each endpoint with real HTTP results
     */
    public function get_endpoint_status_summary() {
        $endpoints = array();
        $site_url = get_site_url();
        
        // **NEW: Get strategy information from endpoint manager**
        require_once(plugin_dir_path(__FILE__) . '../shared/class-endpoint-manager.php');
        $endpoint_manager = Kismet_Endpoint_Manager::get_instance();
        $all_strategies = $endpoint_manager->get_all_endpoint_strategies();
        
        // Test AI Plugin endpoint (REAL HTTP test)
        $ai_plugin_url = $site_url . '/.well-known/ai-plugin.json';
        $ai_plugin_test = $this->test_real_endpoint($ai_plugin_url);
        $ai_plugin_strategy = $all_strategies['/.well-known/ai-plugin.json'] ?? array();
        $endpoints['ai_plugin'] = array(
            'name' => 'AI Plugin Discovery',
            'url' => $ai_plugin_url,
            'check_done' => 'HTTP request to actual endpoint',
            'result' => $ai_plugin_test['result_text'],
            'what_we_did' => $ai_plugin_test['what_exists'],
            'is_working' => $ai_plugin_test['is_working'],
            'status' => $ai_plugin_test['status_icon'],
            'http_code' => $ai_plugin_test['http_code'],
            // **SIMPLIFIED: Strategy information**
            'current_strategy' => $ai_plugin_strategy['current_strategy'] ?? 'unknown',
            'current_strategy_index' => $ai_plugin_strategy['current_strategy_index'] ?? 0,
            'strategy_timestamp' => $ai_plugin_strategy['timestamp'] ?? null,
            'both_strategies_work' => $ai_plugin_strategy['both_strategies_work'] ?? false
        );
        
        // Test LLMS.txt endpoint (REAL HTTP test)
        $llms_url = $site_url . '/llms.txt';
        $llms_test = $this->test_real_endpoint($llms_url);
        $llms_strategy = $all_strategies['/llms.txt'] ?? array();
        $endpoints['llms_txt'] = array(
            'name' => 'LLMS.txt Policy File',
            'url' => $llms_url,
            'check_done' => 'HTTP request to actual endpoint',
            'result' => $llms_test['result_text'],
            'what_we_did' => $llms_test['what_exists'],
            'is_working' => $llms_test['is_working'],
            'status' => $llms_test['status_icon'],
            'http_code' => $llms_test['http_code'],
            // **SIMPLIFIED: Strategy information**
            'current_strategy' => $llms_strategy['current_strategy'] ?? 'unknown',
            'current_strategy_index' => $llms_strategy['current_strategy_index'] ?? 0,
            'strategy_timestamp' => $llms_strategy['timestamp'] ?? null,
            'both_strategies_work' => $llms_strategy['both_strategies_work'] ?? false
        );
        
        // Test Ask endpoint (REAL HTTP test)
        $ask_url = $site_url . '/ask';
        $ask_test = $this->test_real_endpoint($ask_url);
        $ask_strategy = $all_strategies['/ask'] ?? array();
        $endpoints['ask_endpoint'] = array(
            'name' => 'Ask Endpoint',
            'url' => $ask_url,
            'check_done' => 'HTTP request to actual endpoint',
            'result' => $ask_test['result_text'],
            'what_we_did' => $ask_test['what_exists'],
            'is_working' => $ask_test['is_working'],
            'status' => $ask_test['status_icon'],
            'http_code' => $ask_test['http_code'],
            // **SIMPLIFIED: Strategy information**
            'current_strategy' => $ask_strategy['current_strategy'] ?? 'unknown',
            'current_strategy_index' => $ask_strategy['current_strategy_index'] ?? 0,
            'strategy_timestamp' => $ask_strategy['timestamp'] ?? null,
            'both_strategies_work' => $ask_strategy['both_strategies_work'] ?? false
        );
        
        // Test MCP Servers endpoint (REAL HTTP test)
        $mcp_url = $site_url . '/.well-known/mcp/servers.json';
        $mcp_test = $this->test_real_endpoint($mcp_url);
        $mcp_strategy = $all_strategies['/.well-known/mcp/servers.json'] ?? array();
        $endpoints['mcp_servers'] = array(
            'name' => 'MCP Servers',
            'url' => $mcp_url,
            'check_done' => 'HTTP request to actual endpoint',
            'result' => $mcp_test['result_text'],
            'what_we_did' => $mcp_test['what_exists'],
            'is_working' => $mcp_test['is_working'],
            'status' => $mcp_test['status_icon'],
            'http_code' => $mcp_test['http_code'],
            // **SIMPLIFIED: Strategy information**
            'current_strategy' => $mcp_strategy['current_strategy'] ?? 'unknown',
            'current_strategy_index' => $mcp_strategy['current_strategy_index'] ?? 0,
            'strategy_timestamp' => $mcp_strategy['timestamp'] ?? null,
            'both_strategies_work' => $mcp_strategy['both_strategies_work'] ?? false
        );
        
        // Test Robots.txt endpoint (REAL HTTP test)
        $robots_url = $site_url . '/robots.txt';
        $robots_test = $this->test_real_endpoint($robots_url);
        $robots_strategy = $all_strategies['/robots.txt'] ?? array();
        $endpoints['robots_txt'] = array(
            'name' => 'Robots.txt Enhancement',
            'url' => $robots_url,
            'check_done' => 'HTTP request to actual endpoint',
            'result' => $robots_test['result_text'],
            'what_we_did' => $robots_test['what_exists'],
            'is_working' => $robots_test['is_working'],
            'status' => $robots_test['status_icon'],
            'http_code' => $robots_test['http_code'],
            // **SIMPLIFIED: Strategy information**
            'current_strategy' => $robots_strategy['current_strategy'] ?? 'unknown',
            'current_strategy_index' => $robots_strategy['current_strategy_index'] ?? 0,
            'strategy_timestamp' => $robots_strategy['timestamp'] ?? null,
            'both_strategies_work' => $robots_strategy['both_strategies_work'] ?? false
        );
        
        return $endpoints;
    }
    
    /**
     * Test database connectivity and permissions
     * 
     * @return array Database connectivity results
     */
    public function test_database_connectivity() {
        global $wpdb;
        
        $can_read = false;
        $can_write = false;
        $has_options_access = false;
        
        // Test read access
        $test_read = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl' LIMIT 1");
        $can_read = ($test_read !== null);
        
        // Test write access (using a temporary option)
        $test_option_name = 'kismet_temp_test_' . time();
        $test_write = update_option($test_option_name, 'test_value');
        if ($test_write) {
            $can_write = true;
            $has_options_access = true;
            delete_option($test_option_name); // Clean up
        }
        
        return array(
            'status' => ($can_read && $can_write) ? 'accessible' : 'limited',
            'can_read' => $can_read,
            'can_write' => $can_write,
            'has_options_access' => $has_options_access,
            'wpdb_available' => isset($wpdb) && is_object($wpdb)
        );
    }
    
    /**
     * Check network connectivity for Kismet backend communication
     * 
     * @return array Network connectivity results
     */
    public function check_network_connectivity() {
        $test_endpoints = array(
            'production' => 'https://api.makekismet.com',
            'local' => 'https://localhost:4000'
        );
        
        $connectivity_results = array();
        
        foreach ($test_endpoints as $env => $endpoint) {
            $response = wp_remote_get($endpoint . '/health', array(
                'timeout' => 5,
                'sslverify' => ($env !== 'local')
            ));
            
            $connectivity_results[$env] = array(
                'endpoint' => $endpoint,
                'accessible' => !is_wp_error($response),
                'response_code' => is_wp_error($response) ? null : wp_remote_retrieve_response_code($response),
                'error' => is_wp_error($response) ? $response->get_error_message() : null
            );
        }
        
        $has_connectivity = false;
        foreach ($connectivity_results as $result) {
            if ($result['accessible']) {
                $has_connectivity = true;
                break;
            }
        }
        
        return array(
            'status' => $has_connectivity ? 'connected' : 'limited',
            'test_results' => $connectivity_results,
            'can_reach_backend' => $has_connectivity
        );
    }
    
    /**
     * Test WordPress options access directly
     * 
     * @return bool True if we can access wp_options
     */
    public function can_access_wp_options() {
        try {
            $test_option_name = 'kismet_temp_access_test_' . time();
            $test_result = update_option($test_option_name, 'test');
            if ($test_result) {
                delete_option($test_option_name);
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Extract useful server information from HTTP headers
     * 
     * @param array $headers HTTP response headers
     * @return array Server information
     */
    private function extract_server_info($headers) {
        $server_info = array();
        
        if (isset($headers['server'])) {
            $server_info['server_software'] = $headers['server'];
        }
        
        if (isset($headers['x-powered-by'])) {
            $server_info['powered_by'] = $headers['x-powered-by'];
        }
        
        return $server_info;
    }
} 