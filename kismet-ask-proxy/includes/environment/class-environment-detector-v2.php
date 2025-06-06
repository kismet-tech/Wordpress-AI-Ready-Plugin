<?php
/**
 * Kismet Environment Detector v2 - Orchestrates specialized testing classes
 *
 * This is a much smaller, focused class that delegates to specialized classes
 * instead of trying to do everything itself.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include specialized classes
require_once(plugin_dir_path(__FILE__) . 'class-system-checker.php');
require_once(plugin_dir_path(__FILE__) . 'class-plugin-detector.php'); 
require_once(plugin_dir_path(__FILE__) . 'class-endpoint-tester.php');
require_once(plugin_dir_path(__FILE__) . 'class-report-generator.php');
require_once(plugin_dir_path(__FILE__) . '../shared/class-route-tester.php');

class Kismet_Environment_Detector_V2 {
    
    private $system_checker;
    private $plugin_detector;
    private $endpoint_tester;
    private $report_generator;
    private $route_tester;
    
    public function __construct() {
        $this->system_checker = new Kismet_System_Checker();
        $this->plugin_detector = new Kismet_Plugin_Detector();
        $this->endpoint_tester = new Kismet_Endpoint_Tester();
        $this->report_generator = new Kismet_Report_Generator();
        $this->route_tester = new Kismet_Route_Tester();
    }
    
    /**
     * Run complete environment check using specialized classes
     * 
     * @return array Complete environment report
     */
    public function run_full_environment_check() {
        $report = array(
            'timestamp' => current_time('mysql'),
            'errors' => array(),
            'warnings' => array()
        );
        
        // System compatibility checks
        $report['checks'] = array(
            'php' => $this->system_checker->check_php_compatibility(),
            'server' => $this->system_checker->detect_server_software(),
            'file_permissions' => $this->system_checker->check_file_permissions(),
            'disk_space' => $this->system_checker->validate_disk_space(),
            'database' => $this->endpoint_tester->test_database_connectivity(),
            'network' => $this->endpoint_tester->check_network_connectivity()
        );
        
        // Plugin environment detection
        $report['plugins'] = array(
            'security' => $this->plugin_detector->scan_security_plugins(),
            'caching' => $this->plugin_detector->identify_caching_plugins(),
            'multisite' => $this->plugin_detector->check_multisite_configuration()
        );
        
        // Real endpoint testing (what the user actually wants to see)
        $report['endpoint_tests'] = $this->endpoint_tester->get_endpoint_status_summary();
        
        // Safety systems check
        $report['safety_systems'] = $this->check_safety_systems();
        
        // Determine overall status
        $report['overall_status'] = $this->determine_overall_status($report);
        
        // Collect errors and warnings from all checks
        $this->collect_errors_and_warnings($report);
        
        return $report;
    }
    
    /**
     * Check safety systems status
     * 
     * @return array Safety systems status
     */
    private function check_safety_systems() {
        $safety_status = array();
        
        // Test if route tester is available
        $safety_status['route_tester_available'] = class_exists('Kismet_Route_Tester');
        
        // Test if file safety manager is available
        $safety_status['file_safety_manager_available'] = class_exists('Kismet_File_Safety_Manager');
        
        // Test .well-known route testing
        if ($safety_status['route_tester_available']) {
            $test_content = json_encode(array('test' => 'env-check', 'timestamp' => current_time('mysql')));
            $safety_status['well_known_route_test'] = $this->route_tester->test_well_known_route('kismet-env-test.json', $test_content);
        }
        
        // Test root route testing  
        if ($safety_status['route_tester_available']) {
            $test_content = "# Test file\nTimestamp: " . current_time('mysql');
            $safety_status['root_route_test'] = $this->route_tester->test_root_route('kismet-env-test.txt', $test_content);
        }
        
        // Test database access for diagnostic logging
        $safety_status['diagnostic_logging_enabled'] = $this->endpoint_tester->can_access_wp_options();
        
        // Test file fingerprinting (basic file operations)
        $safety_status['file_fingerprinting_available'] = function_exists('file_get_contents') && 
                                                         function_exists('file_put_contents') &&
                                                         function_exists('file_exists');
        
        // Test safe file creation capabilities
        $safety_status['safe_file_creation_available'] = is_writable(ABSPATH) || 
                                                        is_writable(ABSPATH . '.well-known');
        
        // Determine overall safety system status
        $well_known_works = isset($safety_status['well_known_route_test']['overall_success']) && 
                           $safety_status['well_known_route_test']['overall_success'];
        
        $root_works = isset($safety_status['root_route_test']['overall_success']) && 
                     $safety_status['root_route_test']['overall_success'];
        
        $core_systems_available = $safety_status['diagnostic_logging_enabled'] && 
                                 $safety_status['file_fingerprinting_available'] && 
                                 $safety_status['safe_file_creation_available'];
        
        if ($well_known_works && $root_works && $core_systems_available) {
            $safety_status['status'] = 'fully_functional';
        } elseif (($well_known_works || $root_works) && $core_systems_available) {
            $safety_status['status'] = 'mostly_functional';
        } elseif ($well_known_works || $root_works) {
            $safety_status['status'] = 'basic_functional';
        } else {
            $safety_status['status'] = 'limited';
        }
        
        return $safety_status;
    }
    
    /**
     * Determine overall environment status using original simple logic
     * 
     * @param array $report Complete environment report
     * @return string Overall status
     */
    private function determine_overall_status($report) {
        // Use original simple logic from working system
        $error_count = count($report['errors']);
        $warning_count = count($report['warnings']);
        
        if ($error_count > 0) {
            return 'incompatible';
        } elseif ($warning_count > 0) {
            return 'compatible_with_warnings';
        } else {
            return 'fully_compatible';
        }
    }
    
    /**
     * Collect errors and warnings from all checks
     * 
     * @param array &$report Environment report (passed by reference)
     */
    private function collect_errors_and_warnings(&$report) {
        // PHP version errors
        if (!($report['checks']['php']['version_compatible'] ?? true)) {
            $report['errors'][] = 'PHP version ' . ($report['checks']['php']['current_version'] ?? 'unknown') . ' is below minimum required version';
        }
        
        // Missing PHP extensions
        if (!empty($report['checks']['php']['missing_extensions'])) {
            $report['warnings'][] = 'Missing PHP extensions: ' . implode(', ', $report['checks']['php']['missing_extensions']);
        }
        
        // Security plugin warnings
        if (!empty($report['plugins']['security']['detected_plugins'])) {
            $plugin_names = array_column($report['plugins']['security']['detected_plugins'], 'name');
            $report['warnings'][] = 'Security plugins detected that may require configuration: ' . implode(', ', $plugin_names);
        }
        
        // Caching plugin warnings
        if (!empty($report['plugins']['caching']['detected_plugins'])) {
            $plugin_names = array_column($report['plugins']['caching']['detected_plugins'], 'name');
            $report['warnings'][] = 'Caching plugins detected that may need exclusion rules: ' . implode(', ', $plugin_names);
        }
        
        // Endpoint-specific warnings
        foreach ($report['endpoint_tests'] as $key => $endpoint) {
            if (!$endpoint['is_working']) {
                $report['warnings'][] = $endpoint['name'] . ' endpoint not working: ' . $endpoint['result'];
            }
        }
    }
    
    /**
     * Generate HTML admin report
     * 
     * @return string HTML report
     */
    public function get_admin_report() {
        $report = $this->run_full_environment_check();
        return $this->report_generator->generate_admin_report($report);
    }
    
    /**
     * Check if environment is compatible for plugin activation using original logic
     * 
     * @return bool True if environment is compatible, false otherwise
     */
    public function is_environment_compatible() {
        $report = $this->run_full_environment_check();
        
        // Use original working logic: only fail if status is 'incompatible'
        return $report['overall_status'] !== 'incompatible';
    }
    
    /**
     * Generate HTML admin report (alias for get_admin_report for compatibility)
     * 
     * @return string HTML report
     */
    public function get_admin_report_html() {
        return $this->get_admin_report();
    }
} 