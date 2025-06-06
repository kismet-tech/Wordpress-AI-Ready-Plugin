<?php
/**
 * Kismet System Checker - Basic environment and compatibility validation
 *
 * Handles PHP version, server software, file permissions, and disk space checks
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Kismet_System_Checker {
    
    /**
     * Check PHP version and required extensions
     * 
     * @return array PHP compatibility results
     */
    public function check_php_compatibility() {
        $php_version = PHP_VERSION;
        $min_version = '7.4.0';
        $version_compatible = version_compare($php_version, $min_version, '>=');
        
        $required_extensions = array('json', 'curl', 'reflection');
        $missing_extensions = array();
        
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }
        
        return array(
            'status' => $version_compatible && empty($missing_extensions) ? 'compatible' : 'incompatible',
            'php_version' => $php_version,
            'min_required' => $min_version,
            'version_compatible' => $version_compatible,
            'required_extensions' => $required_extensions,
            'missing_extensions' => $missing_extensions
        );
    }
    
    /**
     * Detect web server software (Apache, Nginx, IIS, etc.)
     * 
     * @return array Server software detection results
     */
    public function detect_server_software() {
        $server_software = 'unknown';
        $supports_htaccess = false;
        $needs_manual_config = false;
        
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $server_info = $_SERVER['SERVER_SOFTWARE'];
            
            if (stripos($server_info, 'apache') !== false) {
                $server_software = 'apache';
                $supports_htaccess = true;
            } elseif (stripos($server_info, 'nginx') !== false) {
                $server_software = 'nginx';
                $needs_manual_config = true;
            }
        }
        
        return array(
            'status' => 'detected',
            'server_type' => $server_software,
            'supports_htaccess' => $supports_htaccess,
            'needs_manual_config' => $needs_manual_config
        );
    }
    
    /**
     * Check file system permissions for .well-known directory operations
     * 
     * @return array File permissions check results
     */
    public function check_file_permissions() {
        $wordpress_root = ABSPATH;
        $well_known_dir = $wordpress_root . '.well-known';
        
        $permissions = array(
            'wordpress_root_writable' => is_writable($wordpress_root),
            'well_known_exists' => file_exists($well_known_dir),
            'well_known_writable' => file_exists($well_known_dir) ? is_writable($well_known_dir) : null,
            'can_create_directory' => false,
            'can_create_files' => false
        );
        
        // Test if we can create the directory if it doesn't exist
        if (!$permissions['well_known_exists'] && $permissions['wordpress_root_writable']) {
            $test_result = @mkdir($well_known_dir, 0755, true);
            if ($test_result) {
                $permissions['can_create_directory'] = true;
                $permissions['well_known_writable'] = is_writable($well_known_dir);
                
                // Test file creation
                $test_file = $well_known_dir . '/test-permissions.txt';
                if (@file_put_contents($test_file, 'test') !== false) {
                    $permissions['can_create_files'] = true;
                    @unlink($test_file); // Clean up
                }
                
                @rmdir($well_known_dir); // Clean up test directory
            }
        } elseif ($permissions['well_known_exists'] && $permissions['well_known_writable']) {
            // Test file creation in existing directory
            $test_file = $well_known_dir . '/test-permissions.txt';
            if (@file_put_contents($test_file, 'test') !== false) {
                $permissions['can_create_files'] = true;
                @unlink($test_file); // Clean up
            }
        }
        
        $overall_writable = $permissions['can_create_directory'] || 
                           ($permissions['well_known_exists'] && $permissions['can_create_files']);
        
        return array(
            'status' => $overall_writable ? 'writable' : 'restricted',
            'details' => $permissions,
            'fallback_available' => true // We can always use WordPress rewrite rules as fallback
        );
    }
    
    /**
     * Validate available disk space
     * 
     * @return array Disk space validation results
     */
    public function validate_disk_space() {
        $wordpress_root = ABSPATH;
        $free_bytes = disk_free_space($wordpress_root);
        $total_bytes = disk_total_space($wordpress_root);
        
        $required_bytes = 50 * 1024; // 50KB minimum for our files
        $has_sufficient_space = $free_bytes !== false && $free_bytes > $required_bytes;
        
        return array(
            'status' => $has_sufficient_space ? 'sufficient' : 'limited',
            'free_bytes' => $free_bytes,
            'total_bytes' => $total_bytes,
            'required_bytes' => $required_bytes,
            'free_mb' => $free_bytes !== false ? round($free_bytes / (1024 * 1024), 2) : 'unknown'
        );
    }
    
    /**
     * Detect existing .well-known directory conflicts
     * 
     * @return array Conflict detection results
     */
    public function detect_existing_well_known_conflicts() {
        $wordpress_root = ABSPATH;
        $well_known_dir = $wordpress_root . '.well-known';
        
        $conflicts = array();
        $common_files = array(
            'acme-challenge' => 'SSL Certificate (Let\'s Encrypt)',
            'apple-app-site-association' => 'Apple App Site Association',
            'assetlinks.json' => 'Google Play Asset Links',
            'security.txt' => 'Security Contact Information',
            'webfinger' => 'WebFinger Protocol',
            'host-meta' => 'Host Metadata',
            'nodeinfo' => 'NodeInfo Protocol'
        );
        
        if (file_exists($well_known_dir)) {
            foreach ($common_files as $file => $description) {
                $file_path = $well_known_dir . '/' . $file;
                if (file_exists($file_path)) {
                    $conflicts[] = array(
                        'file' => $file,
                        'description' => $description,
                        'path' => $file_path
                    );
                }
            }
        }
        
        return array(
            'status' => empty($conflicts) ? 'clear' : 'conflicts_detected',
            'existing_files' => $conflicts,
            'safe_to_proceed' => true // Our ai-plugin.json won't conflict with these
        );
    }
} 