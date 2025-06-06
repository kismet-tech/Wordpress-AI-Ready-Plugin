<?php
/**
 * Kismet Environment Detector - Comprehensive environment validation
 *
 * NOTE: The plugin now includes a dedicated admin page ('Kismet Env') in the WordPress dashboard sidebar.
 * Use this page to diagnose and report environment or plugin issues.
 * When adding new features, ensure any relevant status, errors, or diagnostics are surfaced on this page for visibility.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Comprehensive environment detection and compatibility checking
 */
class Kismet_Environment_Detector {
    
    /**
     * Comprehensive environment check results
     * @var array
     */
    private $environment_report = array();
    
    /**
     * Run all environment checks and return comprehensive report
     * 
     * @return array Detailed environment compatibility report
     */
    public function run_full_environment_check() {
        $this->environment_report = array(
            'timestamp' => current_time('mysql'),
            'overall_status' => 'unknown',
            'checks' => array(),
            'warnings' => array(),
            'errors' => array(),
            'recommendations' => array()
        );
        
        // Run all detection methods
        $this->detect_server_software();
        $this->check_php_compatibility();
        $this->check_file_permissions();
        $this->detect_existing_well_known_conflicts();
        $this->check_mcp_servers_functionality();
        $this->scan_security_plugins();
        $this->identify_caching_plugins();
        $this->check_multisite_configuration();
        $this->validate_disk_space();
        $this->test_database_connectivity();
        $this->check_network_connectivity();
        
        // Determine overall status
        $this->calculate_overall_status();
        
        return $this->environment_report;
    }
    
    /**
     * Detect web server software (Apache, Nginx, IIS, etc.)
     */
    private function detect_server_software() {
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
        
        $this->environment_report['checks']['server_software'] = array(
            'status' => 'detected',
            'server_type' => $server_software,
            'supports_htaccess' => $supports_htaccess,
            'needs_manual_config' => $needs_manual_config
        );
        
        if ($needs_manual_config) {
            $this->environment_report['warnings'][] = "Server requires manual configuration";
        }
    }
    
    /**
     * Check PHP version and required extensions
     */
    private function check_php_compatibility() {
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
        
        $this->environment_report['checks']['php_compatibility'] = array(
            'status' => $version_compatible && empty($missing_extensions) ? 'compatible' : 'incompatible',
            'php_version' => $php_version,
            'min_required' => $min_version,
            'version_compatible' => $version_compatible,
            'required_extensions' => $required_extensions,
            'missing_extensions' => $missing_extensions
        );
        
        if (!$version_compatible) {
            $this->environment_report['errors'][] = "PHP version $php_version is below minimum required $min_version";
        }
        
        if (!empty($missing_extensions)) {
            $this->environment_report['errors'][] = "Missing required PHP extensions: " . implode(', ', $missing_extensions);
        }
    }
    
    /**
     * Check file system permissions for .well-known directory operations
     */
    private function check_file_permissions() {
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
        
        $this->environment_report['checks']['file_permissions'] = array(
            'status' => $overall_writable ? 'writable' : 'restricted',
            'details' => $permissions,
            'fallback_available' => true // We can always use WordPress rewrite rules as fallback
        );
        
        if (!$overall_writable) {
            $this->environment_report['warnings'][] = "Limited file system permissions detected - will use WordPress rewrite fallback";
            $this->environment_report['recommendations'][] = "Consider enabling write permissions for enhanced compatibility";
        }
    }
    
    /**
     * Detect existing .well-known directory conflicts
     */
    private function detect_existing_well_known_conflicts() {
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
        
        $this->environment_report['checks']['well_known_conflicts'] = array(
            'status' => empty($conflicts) ? 'clear' : 'conflicts_detected',
            'existing_files' => $conflicts,
            'safe_to_proceed' => true // Our ai-plugin.json won't conflict with these
        );
        
        if (!empty($conflicts)) {
            $this->environment_report['warnings'][] = "Existing .well-known files detected - plugin will coexist safely";
        }
    }
    
    /**
     * Check MCP servers functionality and endpoint accessibility
     */
    private function check_mcp_servers_functionality() {
        $wordpress_root = ABSPATH;
        $well_known_dir = $wordpress_root . '.well-known';
        $mcp_dir = $well_known_dir . '/mcp';
        
        $mcp_status = array(
            'mcp_directory_writable' => false,
            'can_create_mcp_directory' => false,
            'mcp_servers_handler_available' => class_exists('Kismet_MCP_Servers_Handler'),
            'endpoint_accessible' => false
        );
        
        // Check if we can create/write to MCP directory
        if (file_exists($mcp_dir)) {
            $mcp_status['mcp_directory_writable'] = is_writable($mcp_dir);
        } elseif (file_exists($well_known_dir) && is_writable($well_known_dir)) {
            // Test if we can create the MCP directory
            $test_result = @mkdir($mcp_dir, 0755, true);
            if ($test_result) {
                $mcp_status['can_create_mcp_directory'] = true;
                $mcp_status['mcp_directory_writable'] = is_writable($mcp_dir);
                @rmdir($mcp_dir); // Clean up test directory
            }
        }
        
        // Test MCP servers endpoint accessibility (if handler is available)
        if ($mcp_status['mcp_servers_handler_available']) {
            $site_url = get_site_url();
            $mcp_endpoint = $site_url . '/.well-known/mcp/servers.json';
            
            // Simple connectivity test
            $response = wp_remote_get($mcp_endpoint, array(
                'timeout' => 5,
                'sslverify' => true
            ));
            
            $mcp_status['endpoint_accessible'] = !is_wp_error($response) && 
                                               wp_remote_retrieve_response_code($response) === 200;
        }
        
        $overall_mcp_status = ($mcp_status['mcp_directory_writable'] || $mcp_status['can_create_mcp_directory']) && 
                             $mcp_status['mcp_servers_handler_available'];
        
        $this->environment_report['checks']['mcp_servers_functionality'] = array(
            'status' => $overall_mcp_status ? 'functional' : 'limited',
            'details' => $mcp_status,
            'endpoint_url' => get_site_url() . '/.well-known/mcp/servers.json'
        );
        
        if (!$overall_mcp_status) {
            $this->environment_report['warnings'][] = "MCP servers functionality may be limited due to file permissions or missing handler";
        }
        
        if ($mcp_status['mcp_servers_handler_available'] && !$mcp_status['endpoint_accessible']) {
            $this->environment_report['warnings'][] = "MCP servers endpoint may not be accessible - check server configuration";
        }
    }
    
    /**
     * Scan for installed security plugins that might interfere
     */
    private function scan_security_plugins() {
        $security_plugins = array(
            'wordfence/wordfence.php' => 'Wordfence Security',
            'sucuri-scanner/sucuri.php' => 'Sucuri Security',
            'better-wp-security/better-wp-security.php' => 'iThemes Security',
            'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security'
        );
        
        $detected_plugins = array();
        
        foreach ($security_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_plugins[] = array(
                    'name' => $plugin_name,
                    'file' => $plugin_file
                );
            }
        }
        
        $this->environment_report['checks']['security_plugins'] = array(
            'status' => empty($detected_plugins) ? 'none_detected' : 'plugins_detected',
            'detected_plugins' => $detected_plugins,
            'requires_configuration' => !empty($detected_plugins)
        );
        
        if (!empty($detected_plugins)) {
            $this->environment_report['warnings'][] = "Security plugins detected - may require whitelist configuration";
            $this->environment_report['recommendations'][] = "Review security plugin documentation for API endpoint whitelisting";
        }
    }
    
    /**
     * Identify caching plugins that need configuration
     */
    private function identify_caching_plugins() {
        $caching_plugins = array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache'
        );
        
        $detected_plugins = array();
        
        foreach ($caching_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_plugins[] = array(
                    'name' => $plugin_name,
                    'file' => $plugin_file,
                    'needs_exclusion' => true
                );
            }
        }
        
        $this->environment_report['checks']['caching_plugins'] = array(
            'status' => empty($detected_plugins) ? 'none_detected' : 'plugins_detected',
            'detected_plugins' => $detected_plugins,
            'requires_exclusion_rules' => !empty($detected_plugins)
        );
        
        if (!empty($detected_plugins)) {
            $this->environment_report['warnings'][] = "Caching plugins detected - /ask endpoint should be excluded from caching";
            $this->environment_report['recommendations'][] = "Configure caching plugins to exclude /ask and /.well-known/ai-plugin.json";
        }
    }
    
    /**
     * Check WordPress multisite configuration
     */
    private function check_multisite_configuration() {
        $is_multisite = is_multisite();
        $multisite_config = array();
        
        if ($is_multisite) {
            $multisite_config = array(
                'is_subdomain' => defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL,
                'is_subdirectory' => defined('SUBDOMAIN_INSTALL') && !SUBDOMAIN_INSTALL,
                'network_admin' => current_user_can('manage_network'),
                'current_site_id' => get_current_blog_id(),
                'main_site_id' => get_main_site_id()
            );
        }
        
        $this->environment_report['checks']['multisite'] = array(
            'status' => $is_multisite ? 'multisite_detected' : 'single_site',
            'is_multisite' => $is_multisite,
            'configuration' => $multisite_config,
            'special_handling_required' => $is_multisite
        );
        
        if ($is_multisite) {
            $this->environment_report['warnings'][] = "WordPress Multisite detected - plugin behavior will vary by site";
            $this->environment_report['recommendations'][] = "Consider network activation vs individual site activation";
        }
    }
    
    /**
     * Validate available disk space
     */
    private function validate_disk_space() {
        $wordpress_root = ABSPATH;
        $free_bytes = disk_free_space($wordpress_root);
        $total_bytes = disk_total_space($wordpress_root);
        
        $required_bytes = 50 * 1024; // 50KB minimum for our files
        $has_sufficient_space = $free_bytes !== false && $free_bytes > $required_bytes;
        
        $this->environment_report['checks']['disk_space'] = array(
            'status' => $has_sufficient_space ? 'sufficient' : 'limited',
            'free_bytes' => $free_bytes,
            'total_bytes' => $total_bytes,
            'required_bytes' => $required_bytes,
            'free_mb' => $free_bytes !== false ? round($free_bytes / (1024 * 1024), 2) : 'unknown'
        );
        
        if (!$has_sufficient_space) {
            $this->environment_report['errors'][] = "Insufficient disk space for file operations";
        }
    }
    
    /**
     * Test database connectivity and permissions
     */
    private function test_database_connectivity() {
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
        
        $this->environment_report['checks']['database'] = array(
            'status' => ($can_read && $can_write) ? 'accessible' : 'limited',
            'can_read' => $can_read,
            'can_write' => $can_write,
            'has_options_access' => $has_options_access,
            'wpdb_available' => isset($wpdb) && is_object($wpdb)
        );
        
        if (!$can_read || !$can_write) {
            $this->environment_report['errors'][] = "Database access limitations detected";
        }
    }
    
    /**
     * Check network connectivity for Kismet backend communication
     */
    private function check_network_connectivity() {
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
        
        $this->environment_report['checks']['network_connectivity'] = array(
            'status' => $has_connectivity ? 'connected' : 'limited',
            'test_results' => $connectivity_results,
            'can_reach_backend' => $has_connectivity
        );
        
        if (!$has_connectivity) {
            $this->environment_report['warnings'][] = "Limited network connectivity - backend communication may be affected";
        }
    }
    
    /**
     * Calculate overall compatibility status based on all checks
     */
    private function calculate_overall_status() {
        $error_count = count($this->environment_report['errors']);
        $warning_count = count($this->environment_report['warnings']);
        
        if ($error_count > 0) {
            $this->environment_report['overall_status'] = 'incompatible';
        } elseif ($warning_count > 0) {
            $this->environment_report['overall_status'] = 'compatible_with_warnings';
        } else {
            $this->environment_report['overall_status'] = 'fully_compatible';
        }
        
        // Add summary
        $this->environment_report['summary'] = array(
            'total_checks' => count($this->environment_report['checks']),
            'errors' => $error_count,
            'warnings' => $warning_count,
            'recommendations' => count($this->environment_report['recommendations'])
        );
    }
    
    /**
     * Get a simplified compatibility check for quick validation
     * 
     * @return bool True if environment is compatible enough to proceed
     */
    public function is_environment_compatible() {
        $report = $this->run_full_environment_check();
        return $report['overall_status'] !== 'incompatible';
    }
    
    /**
     * Get formatted report for admin display
     * 
     * @return string HTML formatted compatibility report
     */
    public function get_admin_report_html() {
        $report = $this->run_full_environment_check();
        $html = '<div class="kismet-environment-report">';
        $html .= '<h3>Kismet Plugin Compatibility Report</h3>';

        // Overall status
        $status_class = '';
        $status_text = '';
        switch ($report['overall_status']) {
            case 'fully_compatible':
                $status_class = 'notice-success';
                $status_text = 'Fully Compatible';
                break;
            case 'compatible_with_warnings':
                $status_class = 'notice-warning';
                $status_text = 'Compatible (with warnings)';
                break;
            case 'incompatible':
                $status_class = 'notice-error';
                $status_text = 'Incompatible';
                break;
        }
        $html .= "<div class='notice $status_class'><p><strong>Status: $status_text</strong></p></div>";

        // Errors
        if (!empty($report['errors'])) {
            $html .= '<h4>❌ Errors (must be resolved):</h4><ul>';
            foreach ($report['errors'] as $error) {
                $html .= "<li style='color: red;'>$error</li>";
            }
            $html .= '</ul>';
        }
        // Warnings
        if (!empty($report['warnings'])) {
            $html .= '<h4>⚠️ Warnings:</h4><ul>';
            foreach ($report['warnings'] as $warning) {
                $html .= "<li style='color: orange;'>$warning</li>";
            }
            $html .= '</ul>';
        }
        // Recommendations
        if (!empty($report['recommendations'])) {
            $html .= '<h4>💡 Recommendations:</h4><ul>';
            foreach ($report['recommendations'] as $recommendation) {
                $html .= "<li>$recommendation</li>";
            }
            $html .= '</ul>';
        }

        // --- BEGIN FRIENDLY TABLE OF CHECKS ---
        $html .= '<h4>🔍 Detailed Environment Checks</h4>';
        $html .= '<style>
            .kismet-check-table { width: 100%; border-collapse: collapse; margin-bottom: 2em; }
            .kismet-check-table th, .kismet-check-table td { padding: 1em 0.7em; border-bottom: 1px solid #e0e0e0; text-align: left; }
            .kismet-check-table th { background: #f5f5f5; font-size: 1.08em; }
            .kismet-status-pass { color: #1a7f37; font-weight: bold; }
            .kismet-status-fail { color: #d63638; font-weight: bold; }
            .kismet-status-warn { color: #e67c00; font-weight: bold; }
            .kismet-details-toggle { cursor: pointer; color: #0073aa; text-decoration: underline; background: none; border: none; font: inherit; padding: 0; }
            .kismet-details-content { display: none; background: #f9f9f9; border-radius: 4px; padding: 0.7em; font-size: 0.98em; margin-top: 0.5em; }
            .kismet-check-table tr.expanded { background: #f6fbff; }
        </style>';
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".kismet-details-toggle").forEach(function(btn) {
                    btn.addEventListener("click", function(e) {
                        e.stopPropagation();
                        var row = btn.closest("tr");
                        var content = row.querySelector(".kismet-details-content");
                        if (content.style.display === "block") {
                            content.style.display = "none";
                            row.classList.remove("expanded");
                        } else {
                            content.style.display = "block";
                            row.classList.add("expanded");
                        }
                    });
                });
            });
        </script>';
        $html .= '<table class="kismet-check-table">';
        $html .= '<thead><tr><th>Check</th><th>Status</th><th>Summary</th><th>Details</th></tr></thead><tbody>';
        foreach ($report['checks'] as $check => $result) {
            $status = isset($result['status']) ? strtolower($result['status']) : 'unknown';
            $icon = '❔';
            $status_class = '';
            if ($status === 'compatible' || $status === 'writable' || $status === 'none_detected' || $status === 'sufficient' || $status === 'single_site' || $status === 'detected' || $status === 'clear' || $status === 'accessible' || $status === 'connected' || $status === 'fully_compatible' || $status === 'functional') {
                $icon = '✅';
                $status_class = 'kismet-status-pass';
            } elseif ($status === 'incompatible' || $status === 'restricted' || $status === 'conflicts_detected' || $status === 'limited' || $status === 'plugins_detected') {
                $icon = '❌';
                $status_class = 'kismet-status-fail';
            } elseif ($status === 'compatible_with_warnings' || $status === 'multisite_detected' || $status === 'plugins_detected' || $status === 'compatible_with_warnings') {
                $icon = '⚠️';
                $status_class = 'kismet-status-warn';
            }
            // Human-friendly summary for each check
            $summary = '';
            switch ($check) {
                case 'server_software':
                    $summary = ($result['server_type'] === 'apache') ? 'Apache server detected.' : (($result['server_type'] === 'nginx') ? 'Nginx server detected. Manual config may be needed.' : 'Unknown server type.');
                    break;
                case 'php_compatibility':
                    $summary = ($result['status'] === 'compatible') ? 'PHP version is compatible (' . $result['php_version'] . ', required: ' . $result['min_required'] . ').' : 'PHP version is incompatible! (' . $result['php_version'] . ', required: ' . $result['min_required'] . ')';
                    if (!empty($result['missing_extensions'])) {
                        $summary .= ' Missing extensions: ' . implode(', ', $result['missing_extensions']) . '.';
                    }
                    break;
                case 'file_permissions':
                    $summary = ($result['status'] === 'writable') ? 'File permissions OK.' : 'Cannot write to .well-known directory!';
                    break;
                case 'well_known_conflicts':
                    $summary = ($result['status'] === 'clear') ? 'No .well-known conflicts.' : 'Conflicts detected with existing .well-known files.';
                    break;
                case 'mcp_servers_functionality':
                    $summary = ($result['status'] === 'functional') ? 'MCP servers endpoint is functional.' : 'MCP servers functionality is limited.';
                    if (isset($result['endpoint_url'])) {
                        $summary .= ' Endpoint: ' . $result['endpoint_url'];
                    }
                    break;
                case 'security_plugins':
                    $summary = ($result['status'] === 'none_detected') ? 'No security plugins detected.' : 'Security plugins detected. May require configuration.';
                    break;
                case 'caching_plugins':
                    $summary = ($result['status'] === 'none_detected') ? 'No caching plugins detected.' : 'Caching plugins detected. Exclusion rules may be needed.';
                    break;
                case 'multisite':
                    $summary = ($result['status'] === 'single_site') ? 'Single site installation.' : 'Multisite detected. Special handling may be required.';
                    break;
                case 'disk_space':
                    $summary = ($result['status'] === 'sufficient') ? 'Sufficient disk space available.' : 'Insufficient disk space!';
                    break;
                case 'database':
                    $summary = ($result['status'] === 'accessible') ? 'Database access OK.' : 'Database access is limited!';
                    break;
                case 'network_connectivity':
                    $summary = ($result['status'] === 'connected') ? 'Network connectivity to backend OK.' : 'Cannot reach Kismet backend!';
                    break;
                default:
                    $summary = ucfirst(str_replace('_', ' ', $status));
            }
            $html .= '<tr>';
            $html .= '<td><strong>' . ucfirst(str_replace('_', ' ', $check)) . '</strong></td>';
            $html .= '<td class="' . $status_class . '">' . $icon . ' ' . ucfirst($status) . '</td>';
            $html .= '<td>' . esc_html($summary) . '</td>';
            $html .= '<td><button class="kismet-details-toggle">Show details</button><div class="kismet-details-content"><pre style="white-space:pre-wrap;">' . esc_html(print_r($result, true)) . '</pre></div></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        // --- END FRIENDLY TABLE OF CHECKS ---

        $html .= '</div>';
        return $html;
    }
}