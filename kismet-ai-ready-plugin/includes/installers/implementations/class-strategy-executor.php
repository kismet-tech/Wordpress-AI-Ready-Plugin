<?php
/**
 * Strategy Executor
 * 
 * Maps strategy names to building block combinations and executes them in correct order.
 * Handles error recovery and cleanup. Replaces individual strategy implementation classes.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Strategy_Executor {
    
    /**
     * Execute a strategy by combining building blocks
     * 
     * @param string $strategy_name Strategy name (e.g., 'static_file_with_htaccess')
     * @param string $endpoint_path Endpoint path (e.g., '/.well-known/ai-plugin.json')
     * @param array $endpoint_data Data needed for the endpoint
     * @param object $plugin_instance Main plugin instance with server info
     * @return array Result with success status and details
     */
    public static function execute($strategy_name, $endpoint_path, $endpoint_data, $plugin_instance) {
        try {
            $result = array(
                'success' => false,
                'strategy' => $strategy_name,
                'endpoint_path' => $endpoint_path,
                'building_blocks_executed' => array(),
                'building_blocks_failed' => array(),
                'details' => array(),
                'cleanup_info' => array()
            );
            
            // Step 1: Get building blocks for this strategy
            $building_blocks = self::get_building_blocks_for_strategy($strategy_name);
            if (empty($building_blocks)) {
                return array(
                    'success' => false,
                    'error' => "Unknown strategy: {$strategy_name}",
                    'strategy' => $strategy_name
                );
            }
            
            $result['details']['building_blocks_planned'] = $building_blocks;
            
            // Step 2: Execute building blocks in order
            foreach ($building_blocks as $block_name) {
                $block_result = self::execute_building_block($block_name, $endpoint_path, $endpoint_data, $plugin_instance);
                
                if ($block_result['success']) {
                    $result['building_blocks_executed'][] = $block_name;
                    $result['details'][$block_name] = $block_result;
                } else {
                    $result['building_blocks_failed'][] = $block_name;
                    $result['details'][$block_name] = $block_result;
                    
                    // Strategy failed - perform cleanup
                    $cleanup_result = self::cleanup_executed_blocks($result['building_blocks_executed'], $endpoint_path, $endpoint_data);
                    $result['cleanup_info'] = $cleanup_result;
                    
                    return array(
                        'success' => false,
                        'error' => "Building block '{$block_name}' failed: " . $block_result['error'],
                        'strategy' => $strategy_name,
                        'failed_block' => $block_name,
                        'building_blocks_executed' => $result['building_blocks_executed'],
                        'cleanup_performed' => $cleanup_result
                    );
                }
            }
            
            $result['success'] = true;
            $result['message'] = "Strategy '{$strategy_name}' executed successfully";
            $result['strategy_used'] = $strategy_name;
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Strategy executor failed: ' . $e->getMessage(),
                'strategy' => $strategy_name
            );
        }
    }
    
    /**
     * Get building blocks for a strategy
     * 
     * @param string $strategy_name Strategy name
     * @return array Array of building block names
     */
    private static function get_building_blocks_for_strategy($strategy_name) {
        $strategy_map = array(
            // Static file strategies
            'static_file_with_htaccess' => array(
                'create_static_file',
                'add_htaccess_rules'
            ),
            
            'static_file_with_nginx_suggestion' => array(
                'create_static_file',
                'suggest_nginx_config'
            ),
            
            'static_file_with_web_config' => array(
                'create_static_file',
                'add_htaccess_rules'  // IIS web.config uses similar rules
            ),
            
            'manual_static_file' => array(
                'create_static_file'
            ),
            
            // WordPress rewrite strategies
            'wordpress_rewrite' => array(
                'add_wordpress_rewrite'
            ),
            
            'wordpress_rewrite_with_htaccess_backup' => array(
                'add_wordpress_rewrite',
                'add_htaccess_rules'
            ),
            
            'wordpress_rewrite_with_nginx_optimization' => array(
                'add_wordpress_rewrite',
                'suggest_nginx_config'
            ),
            
            'wordpress_rewrite_with_iis_optimization' => array(
                'add_wordpress_rewrite',
                'add_htaccess_rules'  // IIS optimization uses similar rules
            ),
            
            'wordpress_rewrite_with_litespeed_optimization' => array(
                'add_wordpress_rewrite',
                'add_htaccess_rules'  // LiteSpeed uses .htaccess
            ),
            
            'wordpress_rewrite_optimized_hosting' => array(
                'add_wordpress_rewrite'  // Managed hosting typically just needs rewrite
            ),
            
            'wordpress_rewrite_shared_hosting_safe' => array(
                'add_wordpress_rewrite'  // Conservative approach for shared hosting
            ),
            
            'wordpress_rewrite_apache_optimized' => array(
                'add_wordpress_rewrite',
                'add_htaccess_rules'
            ),
            
            'wordpress_rewrite_nginx_basic' => array(
                'add_wordpress_rewrite'  // Basic nginx doesn't need config suggestions
            ),
            
            'wordpress_rewrite_cloud_optimized' => array(
                'add_wordpress_rewrite'  // Cloud platforms typically handle optimization
            ),
            
            'wordpress_rewrite_iis_url_rewrite_only' => array(
                'add_wordpress_rewrite'  // IIS with URL Rewrite module
            ),
            
            'wordpress_rewrite_iis_basic' => array(
                'add_wordpress_rewrite'  // Basic IIS without URL Rewrite
            ),
            
            'wordpress_rewrite_with_metrics_and_caching' => array(
                'wordpress_rewrite_with_metrics'
            ),
            
            // File modification strategies
            'file_modification' => array(
                'modify_existing_file'
            ),
            
            'file_append' => array(
                'modify_existing_file'  // File append uses same building block
            ),
            
            // Ask endpoint strategies (dynamic content with API proxy)
            'ask_handler_basic' => array(
                'ask_handler'
            ),
            
            'ask_handler_with_htaccess_backup' => array(
                'ask_handler',
                'add_htaccess_rules'  // Add CORS headers as backup
            ),
            
            'ask_handler_with_nginx_optimization' => array(
                'ask_handler',
                'suggest_nginx_config'  // Suggest nginx optimization
            )
        );
        
        return isset($strategy_map[$strategy_name]) ? $strategy_map[$strategy_name] : array();
    }
    
    /**
     * Execute a single building block
     * 
     * @param string $block_name Building block name
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @param object $plugin_instance Main plugin instance
     * @return array Building block execution result
     */
    private static function execute_building_block($block_name, $endpoint_path, $endpoint_data, $plugin_instance) {
        try {
            // Convert block name to class name format (e.g., ask_handler -> Kismet_Install_Ask_Handler)
            $class_name = 'Kismet_Install_' . implode('_', array_map('ucfirst', explode('_', $block_name)));
            
            // The file is already in the current directory
            $block_file = __FILE__;
            $block_file = str_replace('class-strategy-executor.php', 'class-install-' . str_replace('_', '-', $block_name) . '.php', $block_file);
            
            error_log("KISMET DEBUG: Looking for building block file: " . $block_file);
            error_log("KISMET DEBUG: Looking for class name: " . $class_name);
            
            if (!file_exists($block_file)) {
                return array(
                    'success' => false,
                    'error' => "Building block file not found: {$block_file}",
                    'block' => $block_name
                );
            }
            
            require_once $block_file;
            
            if (!class_exists($class_name)) {
                return array(
                    'success' => false,
                    'error' => "Building block class not found: {$class_name}",
                    'block' => $block_name
                );
            }
            
            // Execute the building block
            $result = $class_name::execute($endpoint_path, $endpoint_data, $plugin_instance);
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => "Building block execution failed: " . $e->getMessage(),
                'block' => $block_name
            );
        }
    }
    
    /**
     * Cleanup executed building blocks in case of failure
     * 
     * @param array $executed_blocks Array of executed building block names
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Cleanup result
     */
    private static function cleanup_executed_blocks($executed_blocks, $endpoint_path, $endpoint_data) {
        $cleanup_results = array();
        
        // Cleanup in reverse order
        $blocks_to_cleanup = array_reverse($executed_blocks);
        
        foreach ($blocks_to_cleanup as $block_name) {
            $cleanup_result = self::cleanup_building_block($block_name, $endpoint_path, $endpoint_data);
            $cleanup_results[$block_name] = $cleanup_result;
        }
        
        return array(
            'blocks_cleaned' => $blocks_to_cleanup,
            'cleanup_results' => $cleanup_results,
            'all_successful' => !in_array(false, array_column($cleanup_results, 'success'))
        );
    }
    
    /**
     * Cleanup a single building block
     * 
     * @param string $block_name Building block name
     * @param string $endpoint_path Endpoint path
     * @param array $endpoint_data Endpoint configuration data
     * @return array Cleanup result
     */
    private static function cleanup_building_block($block_name, $endpoint_path, $endpoint_data) {
        $class_map = array(
            'create_static_file' => 'Kismet_Install_Create_Static_File',
            'add_wordpress_rewrite' => 'Kismet_Install_Add_WordPress_Rewrite',
            'add_htaccess_rules' => 'Kismet_Install_Add_Htaccess_Rules',
            'suggest_nginx_config' => 'Kismet_Install_Suggest_Nginx_Config',
            'modify_existing_file' => 'Kismet_Install_Modify_Existing_File',
            'ask_handler' => 'Kismet_Install_Ask_Handler'
        );
        
        if (!isset($class_map[$block_name])) {
            return array(
                'success' => false,
                'error' => "Unknown building block for cleanup: {$block_name}"
            );
        }
        
        $class_name = $class_map[$block_name];
        
        if (!class_exists($class_name) || !method_exists($class_name, 'cleanup')) {
            return array(
                'success' => true,
                'message' => "No cleanup method available for {$block_name}"
            );
        }
        
        try {
            return $class_name::cleanup($endpoint_path, $endpoint_data);
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => "Cleanup failed for {$block_name}: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Get recommended strategy for an endpoint and server environment
     * 
     * @param string $endpoint_path Endpoint path
     * @param object $plugin_instance Main plugin instance with server info
     * @return string Recommended strategy name
     */
    public static function get_recommended_strategy($endpoint_path, $plugin_instance) {
        // For file modification endpoints (like robots.txt)
        if (strpos($endpoint_path, 'robots.txt') !== false) {
            return 'file_modification';
        }
        
        // Get server detector for proper property access
        $server_detector = $plugin_instance->get_server_detector();
        
        // For virtual endpoints (like /ask) - use Ask Handler strategies
        if (strpos($endpoint_path, '/ask') !== false) {
            if ($server_detector->supports_htaccess) {
                return 'ask_handler_with_htaccess_backup';
            } elseif ($server_detector->is_nginx || $server_detector->supports_nginx_config) {
                return 'ask_handler_with_nginx_optimization';
            } else {
                return 'ask_handler_basic';
            }
        }
        
        // For static file endpoints (.well-known, etc.)
        if ($server_detector->supports_htaccess) {
            return 'static_file_with_htaccess';
        } elseif ($server_detector->is_nginx || $server_detector->supports_nginx_config) {
            return 'static_file_with_nginx_suggestion';
        } else {
            return 'manual_static_file';
        }
    }
} 