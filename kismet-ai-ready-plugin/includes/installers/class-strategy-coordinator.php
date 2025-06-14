<?php
/**
 * Strategy Coordinator
 * 
 * This coordinator uses the strategy registry system to implement any strategy
 * for any endpoint. It coordinates between endpoint content logic and installation strategies.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../strategies/strategies.php';

class Kismet_Strategy_Coordinator {
    
    private $plugin_instance;
    
    public function __construct($plugin_instance) {
        $this->plugin_instance = $plugin_instance;
    }
    
    /**
     * Install an endpoint using its strategy order
     * 
     * @param string $endpoint_path Endpoint path (e.g., '/.well-known/ai-plugin.json')
     * @param array $endpoint_data Data needed for the endpoint
     * @param string $endpoint_type Type of endpoint (for getting strategy order)
     * @return array Installation result
     */
    public function install_endpoint($endpoint_path, $endpoint_data, $endpoint_type) {
        try {
            // Get strategy order for this endpoint type
            $strategies = $this->get_endpoint_strategy_order($endpoint_type);
            
            if (empty($strategies)) {
                return array(
                    'success' => false,
                    'error' => "No strategies available for endpoint type: {$endpoint_type}",
                    'endpoint_path' => $endpoint_path
                );
            }
            
            // Try each strategy in order until one succeeds
            $last_error = null;
            $strategy_results = array();
            
            foreach ($strategies as $strategy) {
                $result = $this->try_strategy($strategy, $endpoint_path, $endpoint_data);
                $strategy_results[$strategy] = $result;
                
                if ($result['success']) {
                    // Strategy succeeded!
                    return array(
                        'success' => true,
                        'strategy_used' => $strategy,
                        'endpoint_path' => $endpoint_path,
                        'endpoint_type' => $endpoint_type,
                        'result' => $result,
                        'attempted_strategies' => $strategy_results
                    );
                }
                
                $last_error = $result['error'] ?? 'Unknown error';
            }
            
            // All strategies failed
            return array(
                'success' => false,
                'error' => "All strategies failed. Last error: {$last_error}",
                'endpoint_path' => $endpoint_path,
                'endpoint_type' => $endpoint_type,
                'attempted_strategies' => $strategy_results
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Unified installer failed: ' . $e->getMessage(),
                'endpoint_path' => $endpoint_path
            );
        }
    }
    
    /**
     * Try a specific strategy
     * 
     * @param string $strategy Strategy name
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint data
     * @return array Strategy result
     */
    private function try_strategy($strategy, $endpoint_path, $endpoint_data) {
        // Check if strategy is implementable
        if (!Kismet_Strategy_Registry::is_implementable_strategy($strategy)) {
            return array(
                'success' => false,
                'error' => "Strategy not implementable: {$strategy}"
            );
        }
        
        // Execute the strategy using the registry
        return Kismet_Strategy_Registry::execute_strategy(
            $strategy, 
            $endpoint_path, 
            $endpoint_data, 
            $this->plugin_instance
        );
    }
    
    /**
     * Get strategy order for an endpoint type
     * 
     * This connects to the existing strategy classes to get the ordered list
     * 
     * @param string $endpoint_type Type of endpoint
     * @return array Ordered array of strategies to try
     */
    private function get_endpoint_strategy_order($endpoint_type) {
        switch ($endpoint_type) {
            case 'ai_plugin':
                require_once plugin_dir_path(__FILE__) . '../strategies/class-ai-plugin-strategies.php';
                $strategy_class = new Kismet_AI_Plugin_Strategies($this->plugin_instance);
                return $strategy_class->get_ordered_strategies();
                
            case 'mcp_servers':
                require_once plugin_dir_path(__FILE__) . '../strategies/class-mcp-servers-strategies.php';
                $strategy_class = new Kismet_MCP_Servers_Strategies($this->plugin_instance);
                return $strategy_class->get_ordered_strategies();
                
            case 'ask':
                require_once plugin_dir_path(__FILE__) . '../strategies/class-ask-strategies.php';
                $strategy_class = new Kismet_Ask_Strategies($this->plugin_instance);
                return $strategy_class->get_ordered_strategies();
                
            case 'robots':
                require_once plugin_dir_path(__FILE__) . '../strategies/class-robots-strategies.php';
                $strategy_class = new Kismet_Robots_Strategies($this->plugin_instance);
                return $strategy_class->get_ordered_strategies();
                
            case 'llms':
                require_once plugin_dir_path(__FILE__) . '../strategies/class-llms-strategies.php';
                $strategy_class = new Kismet_LLMS_Strategies($this->plugin_instance);
                return $strategy_class->get_ordered_strategies();
                
            default:
                return array();
        }
    }
    
    /**
     * Test if an endpoint is working
     * 
     * @param string $endpoint_path Endpoint path to test
     * @return array Test result
     */
    public function test_endpoint($endpoint_path) {
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
        
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        
        return array(
            'success' => $status_code === 200,
            'status_code' => $status_code,
            'headers' => $headers,
            'body_length' => strlen($body),
            'test_url' => $test_url,
            'response_sample' => substr($body, 0, 200) // First 200 chars for debugging
        );
    }
    
    /**
     * Clean up an endpoint installation
     * 
     * @param string $endpoint_path Endpoint path
     * @param string $strategy Strategy that was used
     * @return bool Success status
     */
    public function cleanup_endpoint($endpoint_path, $strategy) {
        if (!Kismet_Strategy_Registry::is_implementable_strategy($strategy)) {
            return false;
        }
        
        $implementation_class = Kismet_Strategy_Registry::get_implementation_class($strategy);
        
        if (!$implementation_class) {
            return false;
        }
        
        // Load the implementation file
        $file_path = plugin_dir_path(__FILE__) . 'implementations/class-install-' . str_replace('_', '-', $strategy) . '.php';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        require_once($file_path);
        
        if (!class_exists($implementation_class) || !method_exists($implementation_class, 'cleanup')) {
            return false;
        }
        
        return $implementation_class::cleanup($endpoint_path);
    }
    
    /**
     * Clean up an endpoint by trying all possible strategies for its type
     * 
     * @param string $endpoint_path Endpoint path
     * @param string $endpoint_type Type of endpoint (for getting strategy order)
     * @return array Cleanup results
     */
    public function cleanup_endpoint_by_type($endpoint_path, $endpoint_type) {
        $strategies = $this->get_endpoint_strategy_order($endpoint_type);
        $cleanup_results = array();
        $any_success = false;
        
        foreach ($strategies as $strategy) {
            $result = $this->cleanup_endpoint($endpoint_path, $strategy);
            $cleanup_results[$strategy] = $result;
            if ($result) {
                $any_success = true;
            }
        }
        
        return array(
            'success' => $any_success,
            'endpoint_path' => $endpoint_path,
            'endpoint_type' => $endpoint_type,
            'cleanup_results' => $cleanup_results
        );
    }
    
    /**
     * Get available strategies for display
     * 
     * @return array Available strategies with display names
     */
    public function get_available_strategies() {
        return Kismet_Strategy_Registry::get_strategy_display_names();
    }
    
    /**
     * Check if a strategy is available
     * 
     * @param string $strategy Strategy name
     * @return bool True if strategy is available
     */
    public function is_strategy_available($strategy) {
        return Kismet_Strategy_Registry::is_implementable_strategy($strategy);
    }
} 