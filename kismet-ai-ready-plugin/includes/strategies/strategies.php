<?php
/**
 * Kismet Strategy Implementation System
 * 
 * Defines all specific endpoint serving strategies and maps them to implementation methods.
 * This is the central registry that connects strategy selection to actual implementation.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Strategy Implementation Registry
 * 
 * Maps specific strategy names to their implementation methods and properties
 */
class Kismet_Strategy_Registry {
    
    // ===========================================
    // STATIC FILE STRATEGIES
    // ===========================================
    
    /** Static file with Apache/LiteSpeed .htaccess rules */
    const STATIC_FILE_WITH_HTACCESS = 'static_file_with_htaccess';
    
    /** Static file with Nginx configuration suggestions */
    const STATIC_FILE_WITH_NGINX_SUGGESTION = 'static_file_with_nginx_suggestion';
    
    /** Static file with IIS web.config rules */
    const STATIC_FILE_WITH_WEB_CONFIG = 'static_file_with_web_config';
    
    /** Basic static file without server-specific configuration */
    const MANUAL_STATIC_FILE = 'manual_static_file';
    
    // ===========================================
    // WORDPRESS REWRITE STRATEGIES
    // ===========================================
    
    /** Basic WordPress rewrite rule */
    const WORDPRESS_REWRITE = 'wordpress_rewrite';
    
    /** WordPress rewrite with Apache .htaccess backup rules */
    const WORDPRESS_REWRITE_WITH_HTACCESS_BACKUP = 'wordpress_rewrite_with_htaccess_backup';
    
    /** WordPress rewrite with Nginx optimization suggestions */
    const WORDPRESS_REWRITE_WITH_NGINX_OPTIMIZATION = 'wordpress_rewrite_with_nginx_optimization';
    
    /** WordPress rewrite with IIS optimization */
    const WORDPRESS_REWRITE_WITH_IIS_OPTIMIZATION = 'wordpress_rewrite_with_iis_optimization';
    
    /** WordPress rewrite with LiteSpeed-specific optimizations */
    const WORDPRESS_REWRITE_WITH_LITESPEED_OPTIMIZATION = 'wordpress_rewrite_with_litespeed_optimization';
    
    /** WordPress rewrite optimized for managed hosting environments */
    const WORDPRESS_REWRITE_OPTIMIZED_HOSTING = 'wordpress_rewrite_optimized_hosting';
    
    /** WordPress rewrite safe for shared hosting restrictions */
    const WORDPRESS_REWRITE_SHARED_HOSTING_SAFE = 'wordpress_rewrite_shared_hosting_safe';
    
    /** WordPress rewrite with Apache-specific optimizations */
    const WORDPRESS_REWRITE_APACHE_OPTIMIZED = 'wordpress_rewrite_apache_optimized';
    
    /** WordPress rewrite for Nginx without config suggestions */
    const WORDPRESS_REWRITE_NGINX_BASIC = 'wordpress_rewrite_nginx_basic';
    
    /** WordPress rewrite for cloud platform optimizations */
    const WORDPRESS_REWRITE_CLOUD_OPTIMIZED = 'wordpress_rewrite_cloud_optimized';
    
    /** WordPress rewrite for IIS with URL Rewrite module only */
    const WORDPRESS_REWRITE_IIS_URL_REWRITE_ONLY = 'wordpress_rewrite_iis_url_rewrite_only';
    
    /** WordPress rewrite for basic IIS without URL Rewrite */
    const WORDPRESS_REWRITE_IIS_BASIC = 'wordpress_rewrite_iis_basic';
    
    /** WordPress rewrite with metrics tracking and caching */
    const WORDPRESS_REWRITE_WITH_METRICS_AND_CACHING = 'wordpress_rewrite_with_metrics_and_caching';
    
    // ===========================================
    // SPECIAL FILE MODIFICATION STRATEGIES
    // ===========================================
    
    /** Modify existing file instead of creating new (for robots.txt) */
    const FILE_MODIFICATION = 'file_modification';
    
    /** Append to existing file without overwriting */
    const FILE_APPEND = 'file_append';
    
    // ===========================================
    // STATUS/ERROR STRATEGIES
    // ===========================================
    
    /** Strategy failed during implementation */
    const FAILED = 'failed';
    
    /** No strategies available for this endpoint */
    const NONE_AVAILABLE = 'none_available';
    
    /** Manual intervention required */
    const MANUAL_INTERVENTION_REQUIRED = 'manual_intervention_required';
    
    /** Strategy not yet determined */
    const UNKNOWN = 'unknown';
    
    // ===========================================
    // ASK ENDPOINT STRATEGIES
    // ===========================================
    
    /** Ask handler basic strategy */
    const ASK_HANDLER_BASIC = 'ask_handler_basic';
    
    /** Ask handler with .htaccess backup strategy */
    const ASK_HANDLER_WITH_HTACCESS_BACKUP = 'ask_handler_with_htaccess_backup';
    
    /** Ask handler with Nginx optimization strategy */
    const ASK_HANDLER_WITH_NGINX_OPTIMIZATION = 'ask_handler_with_nginx_optimization';
    
    /**
     * Get all available implementation strategies (excludes status/error types)
     * 
     * @return array Array of strategy constants that can be implemented
     */
    public static function get_available_strategies() {
        return array(
            // Static file strategies
            self::STATIC_FILE_WITH_HTACCESS,
            self::STATIC_FILE_WITH_NGINX_SUGGESTION,
            self::STATIC_FILE_WITH_WEB_CONFIG,
            self::MANUAL_STATIC_FILE,
            
            // WordPress rewrite strategies
            self::WORDPRESS_REWRITE,
            self::WORDPRESS_REWRITE_WITH_HTACCESS_BACKUP,
            self::WORDPRESS_REWRITE_WITH_NGINX_OPTIMIZATION,
            self::WORDPRESS_REWRITE_WITH_IIS_OPTIMIZATION,
            self::WORDPRESS_REWRITE_WITH_LITESPEED_OPTIMIZATION,
            self::WORDPRESS_REWRITE_OPTIMIZED_HOSTING,
            self::WORDPRESS_REWRITE_SHARED_HOSTING_SAFE,
            self::WORDPRESS_REWRITE_APACHE_OPTIMIZED,
            self::WORDPRESS_REWRITE_NGINX_BASIC,
            self::WORDPRESS_REWRITE_CLOUD_OPTIMIZED,
            self::WORDPRESS_REWRITE_IIS_URL_REWRITE_ONLY,
            self::WORDPRESS_REWRITE_IIS_BASIC,
            self::WORDPRESS_REWRITE_WITH_METRICS_AND_CACHING,
            
            // Special strategies
            self::FILE_MODIFICATION,
            self::FILE_APPEND,
            
            // Ask endpoint strategies
            self::ASK_HANDLER_BASIC,
            self::ASK_HANDLER_WITH_HTACCESS_BACKUP,
            self::ASK_HANDLER_WITH_NGINX_OPTIMIZATION
        );
    }
    
    /**
     * Get strategy implementation class name
     * 
     * @deprecated This method is deprecated. All strategies now use Kismet_Strategy_Executor
     * with composable building blocks instead of individual monolithic classes.
     * 
     * @param string $strategy Strategy constant
     * @return string Always returns 'Kismet_Strategy_Executor'
     */
    public static function get_implementation_class($strategy) {
        // All strategies now use the unified Strategy Executor
        return 'Kismet_Strategy_Executor';
    }
    
    /**
     * Execute a specific strategy implementation
     * 
     * Uses the new composable building block system via Kismet_Strategy_Executor
     * instead of monolithic strategy classes.
     * 
     * @param string $strategy Strategy constant
     * @param string $endpoint_path Endpoint path (e.g., '/.well-known/ai-plugin.json')
     * @param array $endpoint_data Data needed for the endpoint (content, etc.)
     * @param object $plugin_instance Main plugin instance with server info
     * @return array Result with success status and details
     */
    public static function execute_strategy($strategy, $endpoint_path, $endpoint_data, $plugin_instance) {
        // Check if strategy is valid
        if (!self::is_implementable_strategy($strategy)) {
            return array(
                'success' => false,
                'error' => "Strategy not implementable: {$strategy}"
            );
        }
        
        // Load the Strategy Executor
        $executor_path = plugin_dir_path(__FILE__) . '../installers/implementations/class-strategy-executor.php';
        if (!file_exists($executor_path)) {
            return array(
                'success' => false,
                'error' => "Strategy Executor not found: {$executor_path}"
            );
        }
        
        require_once($executor_path);
        
        if (!class_exists('Kismet_Strategy_Executor')) {
            return array(
                'success' => false,
                'error' => "Strategy Executor class not found"
            );
        }
        
        // Execute the strategy using the new composable system
        try {
            return Kismet_Strategy_Executor::execute($strategy, $endpoint_path, $endpoint_data, $plugin_instance);
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => "Strategy execution failed: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Get file path for strategy implementation
     * 
     * @deprecated This method is deprecated. All strategies now use the unified
     * Strategy Executor instead of individual implementation files.
     * 
     * @param string $strategy Strategy constant
     * @return string Path to the Strategy Executor file
     */
    private static function get_implementation_file_path($strategy) {
        // All strategies now use the unified Strategy Executor
        return plugin_dir_path(__FILE__) . '../installers/implementations/class-strategy-executor.php';
    }
    
    /**
     * Get human-readable names for strategies
     * 
     * @return array Associative array of strategy => display name
     */
    public static function get_strategy_display_names() {
        return array(
            // Static file strategies
            self::STATIC_FILE_WITH_HTACCESS => 'Static File with .htaccess',
            self::STATIC_FILE_WITH_NGINX_SUGGESTION => 'Static File with Nginx Config',
            self::STATIC_FILE_WITH_WEB_CONFIG => 'Static File with web.config',
            self::MANUAL_STATIC_FILE => 'Manual Static File',
            
            // WordPress rewrite strategies
            self::WORDPRESS_REWRITE => 'WordPress Rewrite',
            self::WORDPRESS_REWRITE_WITH_HTACCESS_BACKUP => 'WordPress Rewrite + .htaccess Backup',
            self::WORDPRESS_REWRITE_WITH_NGINX_OPTIMIZATION => 'WordPress Rewrite (Nginx Optimized)',
            self::WORDPRESS_REWRITE_WITH_IIS_OPTIMIZATION => 'WordPress Rewrite (IIS Optimized)',
            self::WORDPRESS_REWRITE_WITH_LITESPEED_OPTIMIZATION => 'WordPress Rewrite (LiteSpeed Optimized)',
            self::WORDPRESS_REWRITE_OPTIMIZED_HOSTING => 'WordPress Rewrite (Managed Hosting)',
            self::WORDPRESS_REWRITE_SHARED_HOSTING_SAFE => 'WordPress Rewrite (Shared Hosting Safe)',
            self::WORDPRESS_REWRITE_APACHE_OPTIMIZED => 'WordPress Rewrite (Apache Optimized)',
            self::WORDPRESS_REWRITE_NGINX_BASIC => 'WordPress Rewrite (Nginx Basic)',
            self::WORDPRESS_REWRITE_CLOUD_OPTIMIZED => 'WordPress Rewrite (Cloud Optimized)',
            self::WORDPRESS_REWRITE_IIS_URL_REWRITE_ONLY => 'WordPress Rewrite (IIS URL Rewrite)',
            self::WORDPRESS_REWRITE_IIS_BASIC => 'WordPress Rewrite (IIS Basic)',
            self::WORDPRESS_REWRITE_WITH_METRICS_AND_CACHING => 'WordPress Rewrite (Metrics and Caching)',
            
            // Special strategies
            self::FILE_MODIFICATION => 'File Modification',
            self::FILE_APPEND => 'File Append',
            
            // Status strategies
            self::FAILED => 'Setup Failed',
            self::NONE_AVAILABLE => 'No Fallback',
            self::MANUAL_INTERVENTION_REQUIRED => 'Manual Fix Needed',
            self::UNKNOWN => 'Unknown'
        );
    }
    
    /**
     * Check if a strategy is a valid implementation strategy
     * 
     * @param string $strategy Strategy to check
     * @return bool True if it's an implementable strategy
     */
    public static function is_implementable_strategy($strategy) {
        return in_array($strategy, self::get_available_strategies(), true);
    }
} 