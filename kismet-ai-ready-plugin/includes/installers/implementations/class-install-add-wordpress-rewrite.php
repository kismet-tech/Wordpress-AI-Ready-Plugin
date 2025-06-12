<?php
/**
 * Add WordPress Rewrite Building Block
 * 
 * Handles WordPress rewrite rule registration, query var addition, and template handling.
 * Used by wordpress_rewrite, wordpress_rewrite_with_htaccess_backup, and wordpress_rewrite_with_nginx_optimization strategies.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Install_Add_WordPress_Rewrite {
    
    /**
     * Execute WordPress rewrite setup
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
                'building_block' => 'add_wordpress_rewrite',
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
            
            $result['success'] = true;
            $result['message'] = "WordPress rewrite rule created successfully";
            $result['query_var'] = $rewrite_result['query_var'];
            $result['pattern'] = $rewrite_result['pattern'];
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Add WordPress rewrite building block failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create WordPress rewrite rule
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Result with rewrite rule details
     */
    private static function create_rewrite_rule($endpoint_path, $endpoint_data) {
        // Use custom rewrite rule if provided in endpoint data
        if (isset($endpoint_data['rewrite_rule'])) {
            $pattern = $endpoint_data['rewrite_rule'];
        } else {
            // Convert path to rewrite pattern
            $pattern = '^' . ltrim($endpoint_path, '/') . '$';
            $pattern = str_replace('.', '\.', $pattern); // Escape dots
            $pattern = str_replace('/', '\/', $pattern); // Escape slashes
        }
        
        // Use custom query vars if provided
        if (isset($endpoint_data['query_vars']) && is_array($endpoint_data['query_vars'])) {
            $query_params = array();
            foreach ($endpoint_data['query_vars'] as $key => $value) {
                $query_params[] = $key . '=' . $value;
            }
            $rewrite_target = 'index.php?' . implode('&', $query_params);
            $query_var = key($endpoint_data['query_vars']); // Use first query var as primary
        } else {
            // Create unique query var based on path
            $query_var = 'kismet_endpoint_' . md5($endpoint_path);
            $rewrite_target = 'index.php?' . $query_var . '=1';
        }
        
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
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Result with query var details
     */
    private static function register_query_vars($endpoint_path, $endpoint_data) {
        // Collect all query vars to register
        $query_vars_to_register = array();
        
        if (isset($endpoint_data['query_vars']) && is_array($endpoint_data['query_vars'])) {
            $query_vars_to_register = array_keys($endpoint_data['query_vars']);
        } else {
            $query_vars_to_register[] = 'kismet_endpoint_' . md5($endpoint_path);
        }
        
        // Add filter to register query vars
        add_filter('query_vars', function($vars) use ($query_vars_to_register) {
            foreach ($query_vars_to_register as $query_var) {
                if (!in_array($query_var, $vars)) {
                    $vars[] = $query_var;
                }
            }
            return $vars;
        });
        
        return array(
            'success' => true,
            'query_vars' => $query_vars_to_register
        );
    }
    
    /**
     * Set up template handling
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Result with template handling details
     */
    private static function setup_template_handling($endpoint_path, $endpoint_data) {
        // Determine primary query var for detection
        if (isset($endpoint_data['query_vars']) && is_array($endpoint_data['query_vars'])) {
            $primary_query_var = key($endpoint_data['query_vars']);
        } else {
            $primary_query_var = 'kismet_endpoint_' . md5($endpoint_path);
        }
        
        // Add template redirect handler
        add_action('template_redirect', function() use ($primary_query_var, $endpoint_path, $endpoint_data) {
            if (get_query_var($primary_query_var)) {
                self::serve_endpoint_content($endpoint_path, $endpoint_data);
                exit;
            }
        });
        
        return array(
            'success' => true,
            'handler_registered' => true,
            'primary_query_var' => $primary_query_var
        );
    }
    
    /**
     * Serve endpoint content
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     */
    private static function serve_endpoint_content($endpoint_path, $endpoint_data) {
        // Set appropriate headers
        $content_type = 'text/plain';
        if (strpos($endpoint_path, '.json') !== false) {
            $content_type = 'application/json';
        } elseif (strpos($endpoint_path, '.txt') !== false) {
            $content_type = 'text/plain';
        }
        
        // Allow custom content type from endpoint data
        if (isset($endpoint_data['content_type'])) {
            $content_type = $endpoint_data['content_type'];
        }
        
        header('Content-Type: ' . $content_type);
        
        // Add CORS headers if needed (especially for .well-known endpoints)
        if (strpos($endpoint_path, '.well-known') !== false || 
            (isset($endpoint_data['cors_required']) && $endpoint_data['cors_required'])) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }
        
        // Add caching headers
        $cache_control = 'public, max-age=3600';
        if (isset($endpoint_data['cache_control'])) {
            $cache_control = $endpoint_data['cache_control'];
        }
        header('Cache-Control: ' . $cache_control);
        
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
        
        // Handle special endpoint types (like API proxy)
        if (isset($endpoint_data['handler_class']) && class_exists($endpoint_data['handler_class'])) {
            $handler = new $endpoint_data['handler_class']();
            if (method_exists($handler, 'handle_request')) {
                $handler->handle_request();
                exit;
            }
        }
        
        // Output content
        echo $content;
    }
    
    /**
     * Test the endpoint to verify it's working
     * 
     * @param string $endpoint_path Endpoint path
     * @param object $plugin_instance Main plugin instance
     * @return array Test result
     */
    public static function test_endpoint($endpoint_path, $plugin_instance) {
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
                'error' => $response->get_error_message(),
                'test_url' => $test_url
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        return array(
            'success' => ($response_code === 200),
            'response_code' => $response_code,
            'response_body' => substr($response_body, 0, 200), // First 200 chars
            'test_url' => $test_url
        );
    }
    
    /**
     * Cleanup WordPress rewrite rules (for rollback scenarios)
     * 
     * @param string $endpoint_path Endpoint path to cleanup
     * @return array Result with success status
     */
    public static function cleanup($endpoint_path) {
        // WordPress doesn't provide a direct way to remove specific rewrite rules
        // The best we can do is flush all rules and let WordPress rebuild them
        flush_rewrite_rules();
        
        return array(
            'success' => true,
            'message' => 'Rewrite rules flushed - specific rule removal not supported by WordPress',
            'endpoint_path' => $endpoint_path
        );
    }
    
    /**
     * Get rewrite rule pattern for an endpoint
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return string Rewrite pattern
     */
    public static function get_rewrite_pattern($endpoint_path, $endpoint_data) {
        if (isset($endpoint_data['rewrite_rule'])) {
            return $endpoint_data['rewrite_rule'];
        }
        
        $pattern = '^' . ltrim($endpoint_path, '/') . '$';
        $pattern = str_replace('.', '\.', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        
        return $pattern;
    }
    
    /**
     * Get query var for an endpoint
     * 
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return string Primary query var
     */
    public static function get_query_var($endpoint_path, $endpoint_data) {
        if (isset($endpoint_data['query_vars']) && is_array($endpoint_data['query_vars'])) {
            return key($endpoint_data['query_vars']);
        }
        
        return 'kismet_endpoint_' . md5($endpoint_path);
    }
} 