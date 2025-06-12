<?php
/**
 * WordPress Rewrite Strategy Implementation
 * 
 * Uses WordPress rewrite rules to serve dynamic content.
 * Most compatible strategy that works on all server types.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_WordPress_Rewrite {
    
    /**
     * Execute WordPress rewrite strategy
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
                'strategy' => 'wordpress_rewrite',
                'rewrite_rules' => array(),
                'details' => array()
            );
            
            // Step 1: Create rewrite rule
            $rewrite_result = self::create_rewrite_rule($endpoint_path, $endpoint_data);
            if (!$rewrite_result['success']) {
                return $rewrite_result;
            }
            
            $result['rewrite_rules'] = $rewrite_result['rules'];
            $result['details']['rewrite'] = $rewrite_result;
            
            // Step 2: Register query vars
            $query_var_result = self::register_query_vars($endpoint_path, $endpoint_data);
            $result['details']['query_vars'] = $query_var_result;
            
            // Step 3: Set up template handling
            $template_result = self::setup_template_handling($endpoint_path, $endpoint_data);
            $result['details']['template'] = $template_result;
            
            // Step 4: Flush rewrite rules
            flush_rewrite_rules();
            
            // Step 5: Test the endpoint
            $test_result = self::test_endpoint($endpoint_path, $plugin_instance);
            $result['details']['test'] = $test_result;
            
            $result['success'] = true;
            $result['message'] = "WordPress rewrite rule created";
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'WordPress rewrite strategy failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create WordPress rewrite rule
     */
    private static function create_rewrite_rule($endpoint_path, $endpoint_data) {
        // Convert path to rewrite pattern
        $pattern = '^' . ltrim($endpoint_path, '/') . '$';
        $pattern = str_replace('.', '\.', $pattern); // Escape dots
        $pattern = str_replace('/', '\/', $pattern); // Escape slashes
        
        // Create unique query var based on path
        $query_var = 'kismet_endpoint_' . md5($endpoint_path);
        
        // Build rewrite rule
        $rewrite_target = 'index.php?' . $query_var . '=1';
        
        // Add rewrite rule
        add_rewrite_rule($pattern, $rewrite_target, 'top');
        
        return array(
            'success' => true,
            'pattern' => $pattern,
            'target' => $rewrite_target,
            'query_var' => $query_var,
            'rules' => array(
                'pattern' => $pattern,
                'target' => $rewrite_target
            )
        );
    }
    
    /**
     * Register query variables
     */
    private static function register_query_vars($endpoint_path, $endpoint_data) {
        $query_var = 'kismet_endpoint_' . md5($endpoint_path);
        
        // Add filter to register query var
        add_filter('query_vars', function($vars) use ($query_var) {
            $vars[] = $query_var;
            return $vars;
        });
        
        return array(
            'success' => true,
            'query_var' => $query_var
        );
    }
    
    /**
     * Set up template handling
     */
    private static function setup_template_handling($endpoint_path, $endpoint_data) {
        $query_var = 'kismet_endpoint_' . md5($endpoint_path);
        
        // Add template redirect handler
        add_action('template_redirect', function() use ($query_var, $endpoint_path, $endpoint_data) {
            if (get_query_var($query_var)) {
                self::serve_endpoint_content($endpoint_path, $endpoint_data);
                exit;
            }
        });
        
        return array(
            'success' => true,
            'handler_registered' => true
        );
    }
    
    /**
     * Serve endpoint content
     */
    private static function serve_endpoint_content($endpoint_path, $endpoint_data) {
        // Set appropriate headers
        $content_type = 'text/plain';
        if (strpos($endpoint_path, '.json') !== false) {
            $content_type = 'application/json';
        } elseif (strpos($endpoint_path, '.txt') !== false) {
            $content_type = 'text/plain';
        }
        
        header('Content-Type: ' . $content_type);
        
        // Add CORS headers if needed (especially for .well-known endpoints)
        if (strpos($endpoint_path, '.well-known') !== false) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        
        // Add caching headers
        header('Cache-Control: public, max-age=3600');
        
        // Handle OPTIONS requests for CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Generate content
        $content = '';
        if (isset($endpoint_data['content'])) {
            $content = $endpoint_data['content'];
        } elseif (isset($endpoint_data['content_generator']) && is_callable($endpoint_data['content_generator'])) {
            $content = call_user_func($endpoint_data['content_generator']);
        }
        
        // Output content
        echo $content;
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
     * Cleanup WordPress rewrite rules
     */
    public static function cleanup($endpoint_path) {
        // WordPress automatically handles rewrite rule cleanup when plugin is deactivated
        // We just need to flush rewrite rules
        flush_rewrite_rules();
        
        return true;
    }
} 