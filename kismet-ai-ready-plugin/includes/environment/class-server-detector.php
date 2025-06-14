<?php
/**
 * Server Detection and Analysis
 * 
 * Handles comprehensive server environment detection for optimal strategy selection.
 * This class analyzes server capabilities, hosting environment, and filesystem permissions
 * to determine the best approach for serving static files and WordPress rewrites.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Server_Detector {
    
    /**
     * Server detection results
     */
    public $server_software = null;
    public $is_apache = false;
    public $is_nginx = false;
    public $is_iis = false;
    public $is_litespeed = false;
    public $server_version = null;
    public $supports_htaccess = false;
    public $supports_nginx_config = false;
    public $supports_web_config = false;
    
    /**
     * Enhanced detection results
     */
    public $apache_capabilities = array();
    public $nginx_capabilities = array();
    public $litespeed_capabilities = array();
    public $iis_capabilities = array();
    public $hosting_environment = array();
    public $filesystem_permissions = array();
    public $wordpress_config = array();
    // NOTE: Removed preferred_file_strategy - misleading generic strategy
    // Each endpoint determines its own optimal strategy based on specific needs
    
    /**
     * Run complete server detection
     */
    public function detect_server_environment() {
        // Get server software from $_SERVER['SERVER_SOFTWARE'] or getenv()
        $this->server_software = $this->get_server_software_string();
        
        if (empty($this->server_software)) {
            // Fallback: Try to detect from other server variables
            $this->server_software = $this->detect_server_from_headers();
        }
        
        $server_lower = strtolower($this->server_software);
        
        // **Apache Detection with Enhanced Capability Testing**
        if (strpos($server_lower, 'apache') !== false || 
            strpos($server_lower, 'detected via apache') !== false) {
            $this->is_apache = true;
            $this->supports_htaccess = $this->verify_htaccess_support();
            
            // Extract Apache version
            if (preg_match('/apache\/([0-9\.]+)/', $server_lower, $matches)) {
                $this->server_version = $matches[1];
            }
            
            // Check for mod_rewrite and mod_headers
            $this->apache_capabilities = $this->detect_apache_modules();
        }
        
        // **Nginx Detection with Performance Optimization** 
        elseif (strpos($server_lower, 'nginx') !== false) {
            $this->is_nginx = true;
            $this->supports_nginx_config = $this->can_suggest_nginx_config();
            
            // Extract Nginx version
            if (preg_match('/nginx\/([0-9\.]+)/', $server_lower, $matches)) {
                $this->server_version = $matches[1];
            }
            
            // Nginx-specific optimizations
            $this->nginx_capabilities = $this->detect_nginx_capabilities();
        }
        
        // **LiteSpeed Detection with Advanced Features**
        elseif (strpos($server_lower, 'litespeed') !== false) {
            $this->is_litespeed = true;
            $this->supports_htaccess = $this->verify_htaccess_support(); // LiteSpeed supports .htaccess
            
            // Extract LiteSpeed version
            if (preg_match('/litespeed\/([0-9\.]+)/', $server_lower, $matches)) {
                $this->server_version = $matches[1];
            }
            
            // LiteSpeed-specific performance features
            $this->litespeed_capabilities = $this->detect_litespeed_capabilities();
        }
        
        // **Microsoft IIS Detection with Web.config Support**
        elseif (strpos($server_lower, 'microsoft-iis') !== false || strpos($server_lower, 'iis') !== false) {
            $this->is_iis = true;
            $this->supports_web_config = $this->verify_web_config_support();
            
            // Extract IIS version
            if (preg_match('/iis\/([0-9\.]+)/', $server_lower, $matches)) {
                $this->server_version = $matches[1];
            }
        
            // IIS-specific module detection
            $this->iis_capabilities = $this->detect_iis_capabilities();
        }
        
        // **Enhanced Hosting Environment Detection**
        $this->hosting_environment = $this->detect_hosting_environment();
        $this->filesystem_permissions = $this->analyze_filesystem_permissions();
        $this->wordpress_config = $this->analyze_wordpress_configuration();
        
        // **Determine Optimal Strategy**
        // NOTE: No longer setting preferred_file_strategy - each endpoint chooses its own
        
        error_log("KISMET SERVER DETECTION: {$this->get_server_type_name()} v{$this->server_version}, .htaccess: " . ($this->supports_htaccess ? 'YES' : 'NO') . ", Environment: {$this->hosting_environment['type']}, Source: " . $this->server_software);
    }
    
    /**
     * Get server software string from various sources
     */
    private function get_server_software_string() {
        // Try $_SERVER first
        if (isset($_SERVER['SERVER_SOFTWARE']) && !empty($_SERVER['SERVER_SOFTWARE'])) {
            return $_SERVER['SERVER_SOFTWARE'];
        }
        
        // Try getenv() as fallback
        $server_software = getenv('SERVER_SOFTWARE');
        if ($server_software !== false && !empty($server_software)) {
            return $server_software;
        }
        
        return '';
    }
    
    /**
     * Try to detect server from HTTP headers when SERVER_SOFTWARE is not available
     */
    private function detect_server_from_headers() {
        // Some servers set custom headers we can check
        $headers_to_check = array(
            'HTTP_SERVER',
            'HTTP_X_POWERED_BY',
            'HTTP_X_SERVER'
        );
        
        foreach ($headers_to_check as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }
        
        // Enhanced detection: Check for Apache-specific functions and features
        if (function_exists('apache_get_version')) {
            return 'Apache (detected via apache_get_version)';
        }
        
        // Check for Apache-specific modules function
        if (function_exists('apache_get_modules')) {
            return 'Apache (detected via apache_get_modules)';
        }
        
        // Check for .htaccess file existence (strong Apache indicator)
        if (file_exists(ABSPATH . '.htaccess')) {
            // Try to read .htaccess for Apache-specific directives
            $htaccess_content = file_get_contents(ABSPATH . '.htaccess');
            if ($htaccess_content && (strpos($htaccess_content, 'RewriteEngine') !== false || 
                                     strpos($htaccess_content, 'RewriteRule') !== false)) {
                return 'Apache (detected via .htaccess analysis)';
            }
        }
        
        // Check for common Apache environment variables
        if (isset($_SERVER['REDIRECT_STATUS']) || isset($_SERVER['REDIRECT_URL'])) {
            return 'Apache (detected via redirect variables)';
        }
        
        // Check for mod_rewrite functionality (WordPress permalink test)
        if (get_option('permalink_structure')) {
            // If WordPress permalinks work, likely Apache or compatible
            return 'Apache-compatible (detected via WordPress permalinks)';
        }
        
        return 'Unknown Server';
    }
    
    /**
     * Check if we can write to the document root directory
     */
    private function can_write_to_document_root() {
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? ABSPATH;
        return is_writable($document_root);
    }
    
    /**
     * Verify actual .htaccess support by testing
     */
    private function verify_htaccess_support() {
        if (!$this->can_write_to_document_root()) {
            return false;
        }
        
        // Try to create a test .htaccess file
        $test_htaccess = ABSPATH . '.htaccess_test_' . time();
        $test_content = "# Kismet plugin test\nRewriteEngine On\n";
        
        if (file_put_contents($test_htaccess, $test_content)) {
            // Clean up test file
            unlink($test_htaccess);
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect Apache modules for better strategy selection
     */
    private function detect_apache_modules() {
        $capabilities = array(
            'mod_rewrite' => false,
            'mod_headers' => false,
            'mod_expires' => false,
            'mod_deflate' => false
        );
        
        // Check if function exists (may not be available in all environments)
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $capabilities['mod_rewrite'] = in_array('mod_rewrite', $modules);
            $capabilities['mod_headers'] = in_array('mod_headers', $modules);
            $capabilities['mod_expires'] = in_array('mod_expires', $modules);
            $capabilities['mod_deflate'] = in_array('mod_deflate', $modules);
        } else {
            // Fallback: Assume common modules are available
            $capabilities['mod_rewrite'] = true; // Required for WordPress
            $capabilities['mod_headers'] = true; // Common on most Apache installs
        }
        
        return $capabilities;
    }
    
    /**
     * Detect Nginx capabilities
     */
    private function detect_nginx_capabilities() {
        return array(
            'static_file_performance' => 'excellent',
            'gzip_compression' => true,
            'browser_caching' => true,
            'config_flexibility' => 'high'
        );
    }
    
    /**
     * Detect LiteSpeed capabilities
     */
    private function detect_litespeed_capabilities() {
        return array(
            'htaccess_compatibility' => 'excellent',
            'performance' => 'high',
            'caching' => 'built_in',
            'security' => 'enhanced'
        );
    }
    
    /**
     * Detect IIS capabilities
     */
    private function detect_iis_capabilities() {
        $capabilities = array(
            'url_rewrite' => false,
            'static_compression' => false,
            'web_config_support' => $this->supports_web_config ?? false
        );
        
        // Check for URL Rewrite module (common indicator)
        if (isset($_SERVER['IIS_UrlRewriteModule'])) {
            $capabilities['url_rewrite'] = true;
        }
        
        return $capabilities;
    }
    
    /**
     * Verify web.config support for IIS
     */
    private function verify_web_config_support() {
        if (!$this->can_write_to_document_root()) {
            return false;
        }
        
        // Check if we can create/modify web.config
        $web_config_path = ABSPATH . 'web.config';
        
        // If web.config already exists, check if we can read it
        if (file_exists($web_config_path)) {
            return is_readable($web_config_path) && is_writable($web_config_path);
        }
        
        // Try to create a test web.config
        $test_content = '<?xml version="1.0" encoding="UTF-8"?><configuration></configuration>';
        if (file_put_contents($web_config_path . '_test', $test_content)) {
            unlink($web_config_path . '_test');
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect hosting environment characteristics
     */
    private function detect_hosting_environment() {
        $environment = array(
            'type' => 'unknown',
            'shared_hosting' => false,
            'managed_wordpress' => false,
            'cloud_platform' => false,
            'performance_tier' => 'standard'
        );
        
        // Check for common shared hosting indicators
        if ($this->is_shared_hosting()) {
            $environment['type'] = 'shared';
            $environment['shared_hosting'] = true;
            $environment['performance_tier'] = 'basic';
        }
        
        // Check for managed WordPress hosting
        elseif ($this->is_managed_wordpress_hosting()) {
            $environment['type'] = 'managed_wordpress';
            $environment['managed_wordpress'] = true;
            $environment['performance_tier'] = 'optimized';
        }
        
        // Check for cloud platforms
        elseif ($this->is_cloud_platform()) {
            $environment['type'] = 'cloud';
            $environment['cloud_platform'] = true;
            $environment['performance_tier'] = 'scalable';
        }
        
        // VPS or dedicated
        else {
            $environment['type'] = 'vps_dedicated';
            $environment['performance_tier'] = 'high';
        }
        
        return $environment;
    }
    
    /**
     * Check if running on shared hosting
     */
    private function is_shared_hosting() {
        // Common shared hosting indicators
        $shared_indicators = array(
            'cpanel', 'plesk', 'directadmin', 'ispconfig',
            'shared', 'hostgator', 'godaddy', 'bluehost'
        );
        
        $server_string = strtolower($this->server_software);
        foreach ($shared_indicators as $indicator) {
            if (strpos($server_string, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for limited filesystem access (common on shared hosting)
        return !is_writable(dirname(ABSPATH));
    }
    
    /**
     * Check if running on managed WordPress hosting
     */
    private function is_managed_wordpress_hosting() {
        $managed_indicators = array(
            'wpengine', 'kinsta', 'siteground', 'wp.com',
            'pressable', 'pagely', 'pantheon'
        );
        
        $server_string = strtolower($this->server_software);
        foreach ($managed_indicators as $indicator) {
            if (strpos($server_string, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if running on cloud platform
     */
    private function is_cloud_platform() {
        $cloud_indicators = array(
            'aws', 'google', 'azure', 'digitalocean',
            'linode', 'vultr', 'cloudflare'
        );
        
        $server_string = strtolower($this->server_software);
        foreach ($cloud_indicators as $indicator) {
            if (strpos($server_string, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Analyze filesystem permissions
     */
    private function analyze_filesystem_permissions() {
        return array(
            'can_write_root' => $this->can_write_to_document_root(),
            'can_create_directories' => $this->can_create_directories(),
            'can_modify_htaccess' => $this->can_modify_htaccess(),
            'wp_uploads_writable' => wp_is_writable(wp_upload_dir()['basedir'])
        );
    }
    
    /**
     * Check if we can create directories
     */
    private function can_create_directories() {
        $test_dir = ABSPATH . 'kismet_test_' . time();
        if (wp_mkdir_p($test_dir)) {
            rmdir($test_dir);
            return true;
        }
        return false;
    }
    
    /**
     * Check if we can modify .htaccess
     */
    private function can_modify_htaccess() {
        $htaccess_path = ABSPATH . '.htaccess';
        
        if (file_exists($htaccess_path)) {
            return is_writable($htaccess_path);
        }
        
        // If .htaccess doesn't exist, check if we can create it
        return $this->can_write_to_document_root();
    }
    
    /**
     * Analyze WordPress configuration
     */
    private function analyze_wordpress_configuration() {
        return array(
            'permalink_structure' => get_option('permalink_structure'),
            'multisite' => is_multisite(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'cache_plugins' => $this->detect_cache_plugins()
        );
    }
    
    /**
     * Detect active cache plugins
     */
    private function detect_cache_plugins() {
        $cache_plugins = array();
        
        if (function_exists('wp_cache_get')) {
            $cache_plugins[] = 'object_cache';
        }
        
        if (defined('WP_CACHE') && WP_CACHE) {
            $cache_plugins[] = 'page_cache';
        }
        
        // Common cache plugin detection
        $known_cache_plugins = array(
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'wp-rocket/wp-rocket.php' => 'WP Rocket'
        );
        
        foreach ($known_cache_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $cache_plugins[] = $plugin_name;
            }
        }
        
        return $cache_plugins;
    }
    
    /**
     * Determine optimal strategy based on comprehensive analysis
     */
    private function determine_optimal_strategy() {
        // **Managed WordPress Hosting: Usually prefers WordPress rewrites**
        if ($this->hosting_environment['managed_wordpress']) {
            return 'wordpress_rewrite';
        }
        
        // **Shared Hosting: Often has restrictions, prefer WordPress rewrites**
        if ($this->hosting_environment['shared_hosting']) {
            return 'wordpress_rewrite';
        }
        
        // **Apache/LiteSpeed with good permissions: Static files are best**
        if (($this->is_apache || $this->is_litespeed) && $this->supports_htaccess && $this->filesystem_permissions['can_write_root']) {
            return 'static_file';
        }
        
        // **Nginx: Excellent for static files**
        if ($this->is_nginx && $this->filesystem_permissions['can_write_root']) {
            return 'static_file';
        }
        
        // **IIS: WordPress rewrites are more reliable**
        if ($this->is_iis) {
            return 'wordpress_rewrite';
        }
        
        // **Default: WordPress rewrite (most compatible)**
        return 'wordpress_rewrite';
    }
    
    /**
     * Check if nginx config suggestions can be provided
     */
    private function can_suggest_nginx_config() {
        // We can always suggest nginx config, but whether user can apply it depends on their access
        return true;
    }
    
    /**
     * Get human-readable server information
     */
    public function get_server_info() {
        $server_type = 'Unknown';
        $capabilities = array();
        
        if ($this->is_apache) {
            $server_type = 'Apache';
            $capabilities[] = '.htaccess support';
        } elseif ($this->is_nginx) {
            $server_type = 'Nginx';
            $capabilities[] = 'High-performance static files';
        } elseif ($this->is_litespeed) {
            $server_type = 'LiteSpeed';
            $capabilities[] = '.htaccess support';
            $capabilities[] = 'High performance';
        } elseif ($this->is_iis) {
            $server_type = 'Microsoft IIS';
            $capabilities[] = 'Windows hosting';
        }
        
        return array(
            'type' => $server_type,
            'version' => $this->server_version,
            'raw_string' => $this->server_software,
            'capabilities' => $capabilities,
            // NOTE: Removed preferred_strategy - misleading for endpoint-specific needs
            'supports_htaccess' => $this->supports_htaccess,
            'supports_nginx_config' => $this->supports_nginx_config,
            'supports_web_config' => $this->supports_web_config,
            'filesystem_permissions' => $this->filesystem_permissions,
            'hosting_environment' => $this->hosting_environment
        );
    }
    
    /**
     * Get simple server type name for logging
     */
    public function get_server_type_name() {
        if ($this->is_apache) return 'Apache';
        if ($this->is_nginx) return 'Nginx';
        if ($this->is_litespeed) return 'LiteSpeed';
        if ($this->is_iis) return 'IIS';
        return 'Unknown';
    }
} 