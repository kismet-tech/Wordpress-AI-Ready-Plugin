<?php
/**
 * Robots.txt Endpoint Strategy Manager
 * 
 * Manages serving strategies for /robots.txt based on server configuration
 * CRITICAL: This endpoint must NEVER overwrite existing robots.txt content!
 * It only appends LLM-specific directives while preserving existing rules.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Robots_Strategies {
    
    private $plugin_instance;
    
    public function __construct($plugin_instance) {
        $this->plugin_instance = $plugin_instance;
    }
    
    /**
     * Get ordered strategies for /robots.txt endpoint
     * 
     * robots.txt requires special handling:
     * 1. NEVER overwrite existing content
     * 2. Only append LLM-specific rules
     * 3. Respect existing SEO configuration
     * 4. Must be served from document root
     * 
     * **ENHANCED: Checks admin toggle for event tracking vs static files preference**
     * 
     * @return array Ordered array of strategies to try
     */
    public function get_ordered_strategies() {
        // **CHECK ADMIN TOGGLE FOR EVENT TRACKING PREFERENCE**
        require_once(plugin_dir_path(__FILE__) . '../admin/class-ai-plugin-admin.php');
        $should_send_events = Kismet_AI_Plugin_Admin::should_send_events();
        
        // **LOG TOGGLE STATUS FOR DEBUGGING**
        error_log("KISMET ROBOTS STRATEGIES: Admin toggle status - should_send_events: " . ($should_send_events ? 'TRUE' : 'FALSE'));
        
        // **IF CHECKBOX IS UNCHECKED (should send events), prioritize metrics-enabled strategy**
        if ($should_send_events) {
            error_log("KISMET ROBOTS STRATEGIES: Prioritizing metrics-enabled strategy (checkbox unchecked)");
            
            // Put the metrics-enabled WordPress rewrite strategy FIRST
            $base_strategies = $this->get_base_strategies_for_server();
            
            // Prepend the metrics strategy to the beginning
            array_unshift($base_strategies, 'wordpress_rewrite_with_metrics_and_caching');
            
            error_log("KISMET ROBOTS STRATEGIES: Final strategy order (events enabled): " . implode(', ', $base_strategies));
            return $base_strategies;
        }
        
        // **IF CHECKBOX IS CHECKED (static files only), keep original strategy order**
        error_log("KISMET ROBOTS STRATEGIES: Using original strategy order (checkbox checked - static files only)");
        $original_strategies = $this->get_base_strategies_for_server();
        error_log("KISMET ROBOTS STRATEGIES: Final strategy order (static files only): " . implode(', ', $original_strategies));
        return $original_strategies;
    }
    
    /**
     * Get base strategies for the current server environment
     * This is the original strategy selection logic, extracted for reuse
     * 
     * @return array Base strategies in server-optimized order
     */
    private function get_base_strategies_for_server() {
        // **All server types follow similar pattern for robots.txt**
        // The key difference is whether we can use .htaccess for fallback rules
        
        // **Apache or LiteSpeed with .htaccess support**
        if (($this->plugin_instance->is_apache || $this->plugin_instance->is_litespeed) 
            && $this->plugin_instance->supports_htaccess) {
            
            return [
                'file_modification_with_backup',     // BEST: Modify existing file safely
                'wordpress_rewrite_with_passthrough', // GOOD: WordPress handles it, passes through existing
                'create_with_htaccess_fallback'      // FALLBACK: Create new file with .htaccess rules
            ];
        }
        
        // **Apache or LiteSpeed without .htaccess support**
        elseif ($this->plugin_instance->is_apache || $this->plugin_instance->is_litespeed) {
            return [
                'file_modification_with_backup',     // BEST: Modify existing file safely
                'wordpress_rewrite_with_passthrough' // FALLBACK: WordPress handles it
            ];
        }
        
        // **Nginx (static file serving preferred)**
        elseif ($this->plugin_instance->is_nginx) {
            return [
                'file_modification_with_backup',     // BEST: Nginx serves static files efficiently
                'wordpress_rewrite_with_passthrough' // FALLBACK: WordPress handling
            ];
        }
        
        // **Microsoft IIS (file system handling can be tricky)**
        elseif ($this->plugin_instance->is_iis) {
            return [
                'wordpress_rewrite_with_passthrough', // BEST: More reliable on IIS
                'file_modification_with_backup'      // FALLBACK: Try direct file modification
            ];
        }
        
        // **Unknown server type**
        else {
            return [
                'wordpress_rewrite_with_passthrough', // SAFEST: WordPress handles existing content
                'file_modification_with_backup'      // FALLBACK: Try file modification
            ];
        }
    }
    
    /**
     * Get strategy-specific configuration for this endpoint
     * 
     * @param string $strategy The strategy being implemented
     * @return array Configuration options for the strategy
     */
    public function get_strategy_config($strategy) {
        switch ($strategy) {
            case 'file_modification_with_backup':
                return [
                    'file_path' => ABSPATH . 'robots.txt',
                    'backup_path' => ABSPATH . 'robots.txt.kismet-backup',
                    'append_only' => true,
                    'preserve_existing' => true,
                    'llm_section_marker' => '# Kismet LLM Training Directives',
                    'safety_checks' => [
                        'backup_before_modify',
                        'validate_existing_content',
                        'check_file_permissions'
                    ],
                    'performance_priority' => 'highest' // Static file serving is fastest
                ];
                
            case 'wordpress_rewrite_with_passthrough':
                return [
                    'rewrite_rule' => '^robots\.txt$',
                    'query_vars' => ['kismet_robots' => '1'],
                    'passthrough_existing' => true,
                    'merge_with_existing' => true,
                    'performance_priority' => 'medium'
                ];
                
            case 'create_with_htaccess_fallback':
                return [
                    'file_path' => ABSPATH . 'robots.txt',
                    'backup_path' => ABSPATH . 'robots.txt.kismet-backup',
                    'htaccess_rules' => [
                        '# Fallback robots.txt rules if main file fails',
                        'RewriteEngine On',
                        'RewriteCond %{REQUEST_URI} ^/robots\.txt$',
                        'RewriteCond %{REQUEST_METHOD} GET',
                        'RewriteRule ^robots\.txt$ index.php?kismet_robots=1 [L]'
                    ],
                    'append_only' => true,
                    'preserve_existing' => true,
                    'performance_priority' => 'high'
                ];
                
            default:
                return [];
        }
    }
    
    /**
     * Get specific recommendations when strategies fail for this endpoint
     * 
     * @return array Array of specific recommendations
     */
    public function get_failure_recommendations() {
        $recommendations = [];
        
        $recommendations[] = 'CRITICAL: Never overwrite existing robots.txt content!';
        $recommendations[] = 'Check file permissions on robots.txt in WordPress root directory';
        $recommendations[] = 'Ensure robots.txt is not write-protected by hosting provider';
        
        if ($this->plugin_instance->is_nginx) {
            $recommendations[] = 'Nginx serves robots.txt as static file - ensure WordPress root is accessible';
        } elseif ($this->plugin_instance->is_apache || $this->plugin_instance->is_litespeed) {
            $recommendations[] = 'Apache/LiteSpeed should serve robots.txt directly from document root';
        } elseif ($this->plugin_instance->is_iis) {
            $recommendations[] = 'IIS robots.txt handling may need special MIME type configuration';
        }
        
        $recommendations[] = 'Test robots.txt with: curl -v ' . site_url('/robots.txt');
        $recommendations[] = 'Verify existing SEO robots.txt rules are preserved';
        $recommendations[] = 'Check for robots.txt plugins that might conflict';
        
        return $recommendations;
    }
    
    /**
     * Get LLM-specific directives to add to robots.txt
     * 
     * @return array LLM training directives
     */
    public function get_llm_directives() {
        return [
            '# Kismet LLM Training Directives',
            '# Added by Kismet AI Ready Plugin',
            '',
            '# Allow LLM training on publicly accessible content',
            'User-agent: GPTBot',
            'Allow: /',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            '',
            'User-agent: CCBot',
            'Allow: /',
            '',
            '# Disallow training on admin and private areas',
            'User-agent: *',
            'Disallow: /wp-admin/',
            'Disallow: /wp-includes/',
            'Disallow: /wp-content/plugins/',
            'Disallow: /wp-content/themes/',
            '',
            '# End Kismet LLM Training Directives'
        ];
    }
    
    /**
     * Check if existing robots.txt file exists
     * 
     * @return bool True if robots.txt already exists
     */
    public function existing_robots_exists() {
        return file_exists(ABSPATH . 'robots.txt');
    }
    
    /**
     * Get current robots.txt content (if it exists)
     * 
     * @return string|false Current content or false if file doesn't exist
     */
    public function get_existing_robots_content() {
        $robots_path = ABSPATH . 'robots.txt';
        return file_exists($robots_path) ? file_get_contents($robots_path) : false;
    }
    
    /**
     * Check if LLM directives already exist in robots.txt
     * 
     * @return bool True if Kismet directives are already present
     */
    public function llm_directives_exist() {
        $existing_content = $this->get_existing_robots_content();
        return $existing_content !== false && strpos($existing_content, '# Kismet LLM Training Directives') !== false;
    }
    
    /**
     * Get special requirements for robots.txt handling
     * 
     * @return array Special requirements
     */
    public function get_special_requirements() {
        return [
            'never_overwrite' => true,          // CRITICAL: Never overwrite existing content
            'append_only' => true,              // Only add new content
            'backup_required' => true,          // Always backup before modifying
            'preserve_seo' => true,             // Preserve existing SEO rules
            'document_root_only' => true,       // Must be served from document root
            'no_cors_needed' => true,           // robots.txt doesn't need CORS
            'plain_text_format' => true        // Must be plain text, not JSON
        ];
    }
    
    /**
     * Get endpoint-specific performance requirements
     * 
     * @return array Performance requirements
     */
    public function get_performance_requirements() {
        return [
            'cache_friendly' => true,           // Should be cached by crawlers
            'cache_duration' => 86400,          // 24 hours (standard for robots.txt)
            'low_latency' => false,             // Not critical for immediate response
            'high_availability' => true,        // Important for SEO crawlers
            'compression_friendly' => false     // Plain text, minimal compression benefit
        ];
    }
} 