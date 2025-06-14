<?php
/**
 * LLMS.txt Endpoint Strategy Manager
 *
 * Manages strategy selection and execution for the /llms.txt endpoint
 * which declares AI/LLM usage policy and available endpoints.
 *
 * STRATEGY PRIORITY for /llms.txt:
 * 1. Static file strategies (best performance for AI crawlers)
 * 2. WordPress rewrite fallback (compatibility)
 *
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_LLMS_Strategies {
    
    private $main_plugin;
    private $server_detector;
    
    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->server_detector = $main_plugin->get_server_detector();
    }
    
    /**
     * Get ordered list of strategies for /llms.txt endpoint
     * 
     * Prioritizes static file strategies for performance, with WordPress rewrite as fallback.
     * 
     * **ENHANCED: Checks admin toggle for event tracking vs static files preference**
     * 
     * @return array Ordered strategies from most preferred to least
     */
    public function get_ordered_strategies() {
        // **CHECK ADMIN TOGGLE FOR EVENT TRACKING PREFERENCE**
        require_once(plugin_dir_path(__FILE__) . '../admin/class-ai-plugin-admin.php');
        $should_send_events = Kismet_AI_Plugin_Admin::should_send_events();
        
        // **LOG TOGGLE STATUS FOR DEBUGGING**
        error_log("KISMET LLMS STRATEGIES: Admin toggle status - should_send_events: " . ($should_send_events ? 'TRUE' : 'FALSE'));
        
        // **IF CHECKBOX IS UNCHECKED (should send events), prioritize metrics-enabled strategy**
        if ($should_send_events) {
            error_log("KISMET LLMS STRATEGIES: Prioritizing metrics-enabled strategy (checkbox unchecked)");
            
            // Put the metrics-enabled WordPress rewrite strategy FIRST
            $base_strategies = $this->get_base_strategies_for_server();
            
            // Prepend the metrics strategy to the beginning
            array_unshift($base_strategies, 'wordpress_rewrite_with_metrics_and_caching');
            
            error_log("KISMET LLMS STRATEGIES: Final strategy order (events enabled): " . implode(', ', $base_strategies));
            return $base_strategies;
        }
        
        // **IF CHECKBOX IS CHECKED (static files only), keep original strategy order**
        error_log("KISMET LLMS STRATEGIES: Using original strategy order (checkbox checked - static files only)");
        $original_strategies = $this->get_base_strategies_for_server();
        error_log("KISMET LLMS STRATEGIES: Final strategy order (static files only): " . implode(', ', $original_strategies));
        return $original_strategies;
    }
    
    /**
     * Get base strategies for the current server environment
     * This is the original strategy selection logic, extracted for reuse
     * 
     * @return array Base strategies in server-optimized order
     */
    private function get_base_strategies_for_server() {
        $server_info = $this->server_detector->get_server_info();
        $strategies = array();
        
        error_log("KISMET LLMS STRATEGIES: Determining strategies for server: " . $server_info['primary_server']);
        
        // Strategy selection based on server capabilities
        switch ($server_info['primary_server']) {
            case 'apache':
            case 'litespeed':
                if ($server_info['has_mod_rewrite'] && $server_info['can_use_htaccess']) {
                    $strategies[] = 'static_file_with_htaccess';
                    error_log("KISMET LLMS STRATEGIES: Apache/LiteSpeed with mod_rewrite - using static file + .htaccess");
                }
                $strategies[] = 'wordpress_rewrite';
                break;
                
            case 'nginx':
                if ($server_info['can_create_files']) {
                    $strategies[] = 'static_file_with_nginx_suggestion';
                    error_log("KISMET LLMS STRATEGIES: Nginx - using static file + config suggestion");
                }
                $strategies[] = 'wordpress_rewrite';
                break;
                
            case 'iis':
                if ($server_info['has_url_rewrite'] && $server_info['can_use_web_config']) {
                    $strategies[] = 'static_file_with_web_config';
                    error_log("KISMET LLMS STRATEGIES: IIS with URL Rewrite - using static file + web.config");
                }
                $strategies[] = 'wordpress_rewrite';
                break;
                
            default:
                error_log("KISMET LLMS STRATEGIES: Unknown/other server - using WordPress rewrite");
                $strategies[] = 'wordpress_rewrite';
                break;
        }
        
        // Always add manual static file as final fallback
        $strategies[] = 'manual_static_file';
        
        error_log("KISMET LLMS STRATEGIES: Final strategy order: " . implode(', ', $strategies));
        return $strategies;
    }
    
    /**
     * Get the recommended strategy for /llms.txt based on server environment
     * 
     * @return string The most recommended strategy for this server
     */
    public function get_recommended_strategy() {
        $strategies = $this->get_ordered_strategies();
        return !empty($strategies) ? $strategies[0] : 'wordpress_rewrite';
    }
    
    /**
     * Check if a specific strategy is suitable for /llms.txt on this server
     * 
     * @param string $strategy Strategy name to check
     * @return bool True if strategy is suitable for this endpoint and server
     */
    public function is_strategy_suitable($strategy) {
        $server_info = $this->server_detector->get_server_info();
        
        switch ($strategy) {
            case 'static_file_with_htaccess':
                return ($server_info['primary_server'] === 'apache' || $server_info['primary_server'] === 'litespeed') 
                    && $server_info['has_mod_rewrite'] 
                    && $server_info['can_use_htaccess'];
                    
            case 'static_file_with_nginx_suggestion':
                return $server_info['primary_server'] === 'nginx' 
                    && $server_info['can_create_files'];
                    
            case 'static_file_with_web_config':
                return $server_info['primary_server'] === 'iis' 
                    && $server_info['has_url_rewrite'] 
                    && $server_info['can_use_web_config'];
                    
            case 'wordpress_rewrite':
                return true; // Always available as fallback
                
            case 'manual_static_file':
                return $server_info['can_create_files'];
                
            default:
                return false;
        }
    }
    
    /**
     * Get strategy-specific requirements and recommendations for /llms.txt
     * 
     * @param string $strategy Strategy name
     * @return array Requirements and recommendations for the strategy
     */
    public function get_strategy_requirements($strategy) {
        switch ($strategy) {
            case 'static_file_with_htaccess':
                return array(
                    'requires' => array('Apache/LiteSpeed', 'mod_rewrite', '.htaccess write permissions'),
                    'benefits' => array('Zero PHP execution', 'Maximum performance', 'AI crawler optimized'),
                    'suitable_for' => array('Production sites', 'High-traffic environments', 'AI discovery optimization')
                );
                
            case 'static_file_with_nginx_suggestion':
                return array(
                    'requires' => array('Nginx server', 'File creation permissions', 'Server config access'),
                    'benefits' => array('Native web server handling', 'Excellent performance', 'Scalable'),
                    'suitable_for' => array('VPS/dedicated servers', 'Custom Nginx configurations')
                );
                
            case 'static_file_with_web_config':
                return array(
                    'requires' => array('IIS server', 'URL Rewrite module', 'web.config permissions'),
                    'benefits' => array('IIS-native handling', 'Good performance', 'Windows hosting optimized'),
                    'suitable_for' => array('Windows hosting', 'Azure web apps', 'IIS environments')
                );
                
            case 'wordpress_rewrite':
                return array(
                    'requires' => array('WordPress permalink structure'),
                    'benefits' => array('Universal compatibility', 'No server config needed', 'Metrics tracking enabled'),
                    'suitable_for' => array('Shared hosting', 'Managed WordPress', 'Testing environments')
                );
                
            case 'manual_static_file':
                return array(
                    'requires' => array('Manual file upload', 'FTP/SFTP access'),
                    'benefits' => array('Works on any server', 'Direct file serving'),
                    'suitable_for' => array('Restrictive hosting', 'Emergency fallback', 'Custom setups')
                );
                
            default:
                return array('requires' => array(), 'benefits' => array(), 'suitable_for' => array());
        }
    }
    
    /**
     * Get performance characteristics for /llms.txt strategies
     * 
     * @return array Performance data for each strategy
     */
    public function get_performance_characteristics() {
        return array(
            'static_file_with_htaccess' => array(
                'latency' => 'Minimal (web server direct)',
                'php_execution' => 'None',
                'database_queries' => 'None',
                'scalability' => 'Excellent',
                'ai_crawler_optimized' => true
            ),
            'static_file_with_nginx_suggestion' => array(
                'latency' => 'Minimal (nginx direct)',
                'php_execution' => 'None', 
                'database_queries' => 'None',
                'scalability' => 'Excellent',
                'ai_crawler_optimized' => true
            ),
            'static_file_with_web_config' => array(
                'latency' => 'Low (IIS direct)',
                'php_execution' => 'None',
                'database_queries' => 'None', 
                'scalability' => 'Good',
                'ai_crawler_optimized' => true
            ),
            'wordpress_rewrite' => array(
                'latency' => 'Moderate (PHP processing)',
                'php_execution' => 'Yes',
                'database_queries' => 'Minimal',
                'scalability' => 'Good',
                'ai_crawler_optimized' => false
            ),
            'manual_static_file' => array(
                'latency' => 'Minimal (direct serving)',
                'php_execution' => 'None',
                'database_queries' => 'None',
                'scalability' => 'Server dependent',
                'ai_crawler_optimized' => true
            )
        );
    }
} 