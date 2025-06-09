<?php
/**
 * Bulletproof AI Plugin Handler - Uses safety systems before endpoint creation
 *
 * This handler implements bulletproof safety by:
 * 1. Testing route accessibility BEFORE creating anything
 * 2. Using file safety manager for conflict resolution
 * 3. Comprehensive error handling and rollback
 * 4. Diagnostic logging for troubleshooting
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '../../shared/class-route-tester.php');
require_once(plugin_dir_path(__FILE__) . '../../shared/class-file-safety-manager.php');

class Kismet_AI_Plugin_Handler {
    
    private $route_tester;
    private $file_safety_manager;
    private $endpoint_created = false;
    private $creation_method = 'none';
    
    public function __construct() {
        $this->route_tester = new Kismet_Route_Tester();
        $this->file_safety_manager = new Kismet_File_Safety_Manager();
        
        // Only create endpoint after safety validation
        add_action('init', array($this, 'safe_endpoint_creation'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    /**
     * Bulletproof endpoint creation with comprehensive safety checks
     */
    public function safe_endpoint_creation() {
        try {
            // Step 1: Test route accessibility before making any changes
            $test_results = $this->test_ai_plugin_route_safety();
            
            if (!$test_results['can_proceed']) {
                $this->log_safety_issue('Cannot create AI plugin endpoint - safety checks failed', $test_results);
                return;
            }
            
            // Step 2: Create endpoint using the recommended approach
            $creation_result = $this->create_endpoint_safe($test_results['recommended_approach']);
            
            if ($creation_result['success']) {
                $this->endpoint_created = true;
                $this->creation_method = $creation_result['method'];
                $this->log_success('AI plugin endpoint created successfully', $creation_result);
            } else {
                $this->log_safety_issue('Failed to create AI plugin endpoint', $creation_result);
            }
            
        } catch (Exception $e) {
            $this->log_safety_issue('Exception during AI plugin endpoint creation', array(
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Test AI plugin route safety before creating anything
     */
    private function test_ai_plugin_route_safety() {
        $ai_plugin_content = $this->get_ai_plugin_content();
        
        // Test .well-known route approach
        $well_known_test = $this->route_tester->test_well_known_route(
            'ai-plugin.json',
            $ai_plugin_content
        );
        
        // Log the test results for diagnostics
        $this->route_tester->log_test_results($well_known_test, 'ai_plugin_endpoint_safety_check');
        
        // Determine if we can proceed and which approach to use
        $can_proceed = $well_known_test['overall_success'] || $well_known_test['fallback_available'];
        
        return array(
            'can_proceed' => $can_proceed,
            'test_results' => $well_known_test,
            'recommended_approach' => $well_known_test['recommended_approach'] ?? 'wordpress_rewrite',
            'fallback_available' => $well_known_test['fallback_available'] ?? false,
            'reasons' => $this->get_safety_reasons($well_known_test)
        );
    }
    
    /**
     * Create the endpoint using the safest available method
     */
    private function create_endpoint_safe($approach) {
        switch ($approach) {
            case 'physical_file':
                return $this->create_physical_file_endpoint();
                
            case 'wordpress_rewrite':
                return $this->create_wordpress_rewrite_endpoint();
                
            default:
                return array(
                    'success' => false,
                    'method' => 'unknown',
                    'error' => "Unknown approach: $approach"
                );
        }
    }
    
    /**
     * Create physical file endpoint with safety checks
     */
    private function create_physical_file_endpoint() {
        $file_path = ABSPATH . '.well-known/ai-plugin.json';
        $content = $this->get_ai_plugin_content();
        
        try {
            // Use file safety manager for bulletproof file creation
            $result = $this->file_safety_manager->safe_file_create(
                $file_path, 
                $content, 
                Kismet_File_Safety_Manager::POLICY_CONTENT_ANALYSIS
            );
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'method' => 'physical_file',
                    'file_path' => $file_path,
                    'action_taken' => $result['action_taken'],
                    'messages' => $result['messages']
                );
            } else {
                return array(
                    'success' => false,
                    'method' => 'physical_file',
                    'errors' => $result['errors'],
                    'warnings' => $result['warnings']
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'method' => 'physical_file',
                'error' => 'Exception during file creation: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create WordPress rewrite endpoint as fallback
     */
    private function create_wordpress_rewrite_endpoint() {
        try {
            // Add rewrite rule
            add_rewrite_rule('\.well-known/ai-plugin\.json/?$', 'index.php?kismet_ai_plugin=1', 'top');
            add_filter('query_vars', array($this, 'add_query_vars'));
            
            // Add handlers for serving content
            add_action('parse_request', array($this, 'intercept_ai_plugin_request'));
            add_action('template_redirect', array($this, 'handle_ai_plugin_request'));
            
            // Flush rewrite rules to activate
            flush_rewrite_rules();
            
            return array(
                'success' => true,
                'method' => 'wordpress_rewrite',
                'message' => 'WordPress rewrite rules created for AI plugin endpoint'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'method' => 'wordpress_rewrite',
                'error' => 'Exception during rewrite rule creation: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate AI plugin JSON content
     */
    private function get_ai_plugin_content() {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        // Generate auto-detected defaults
        $domain = parse_url($site_url, PHP_URL_HOST);
        $auto_hotel_name = !empty($site_name) ? $site_name : ucwords(str_replace(['.com', '.net', '.org', 'www.'], '', $domain));
        
        // Use custom settings with auto-generated fallbacks
        $hotel_name = get_option('kismet_hotel_name', '') ?: $auto_hotel_name;
        $hotel_description = get_option('kismet_hotel_description', '') ?: ('Get information about ' . $auto_hotel_name . ' including amenities, pricing, availability, and booking assistance.');
        $logo_url = get_option('kismet_logo_url', '') ?: ($site_url . '/wp-content/uploads/2024/kismet-logo.png');
        $contact_email = get_option('kismet_contact_email', '') ?: $admin_email;
        $legal_info_url = get_option('kismet_legal_info_url', '') ?: ($site_url . '/privacy-policy');
        
        $ai_plugin = [
            'schema_version' => 'v1',
            'name_for_human' => $hotel_name . ' AI Assistant',
            'name_for_model' => strtolower(str_replace([' ', '-', '.'], '_', $hotel_name)) . '_assistant',
            'description_for_human' => $hotel_description,
            'description_for_model' => 'Provides hotel information for ' . $hotel_name . ' including room availability, pricing, amenities, policies, and booking assistance.',
            'auth' => [
                'type' => 'none'
            ],
            'api' => [
                'type' => 'openapi',
                'url' => $site_url . '/ask'
            ],
            'logo_url' => $logo_url,
            'contact_email' => $contact_email,
            'legal_info_url' => $legal_info_url,
            // Add metadata for safety tracking
            '_generated_by' => 'kismet-ask-proxy',
            '_generated_at' => current_time('mysql'),
            '_content_hash' => md5(json_encode(array($hotel_name, $hotel_description, $logo_url)))
        ];
        
        return json_encode($ai_plugin, JSON_PRETTY_PRINT);
    }
    
    /**
     * WordPress rewrite handlers (used when physical file approach fails)
     */
    public function add_query_vars($vars) {
        $vars[] = 'kismet_ai_plugin';
        return $vars;
    }
    
    public function intercept_ai_plugin_request($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (preg_match('#^/\.well-known/ai-plugin\.json/?(\?.*)?$#', $request_uri)) {
            $this->serve_ai_plugin_content();
            exit;
        }
    }
    
    public function handle_ai_plugin_request() {
        $query_var = get_query_var('kismet_ai_plugin');
        
        if ($query_var) {
            // Track this request using the reusable helper
            Kismet_Endpoint_Tracking_Helper::track_standard_endpoint('/.well-known/ai-plugin.json');
            
            $this->serve_ai_plugin_content();
            exit;
        }
    }
    
    /**
     * Serve AI plugin content (for WordPress rewrite approach)
     */
    private function serve_ai_plugin_content() {
        $custom_url = get_option('kismet_custom_ai_plugin_url', '');
        
        if (!empty($custom_url)) {
            $this->proxy_custom_ai_plugin($custom_url);
        } else {
            status_header(200);
            header('Content-Type: application/json');
            echo $this->get_ai_plugin_content();
        }
    }
    
    /**
     * Proxy to custom AI plugin URL
     */
    private function proxy_custom_ai_plugin($url) {
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            status_header(500);
            echo json_encode(['error' => 'Failed to fetch custom AI plugin JSON']);
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        status_header(200);
        header('Content-Type: ' . ($content_type ?: 'application/json'));
        echo $body;
    }
    
    /**
     * Get human-readable reasons for safety decisions
     */
    private function get_safety_reasons($test_results) {
        $reasons = array();
        
        if (isset($test_results['physical_file_works']) && $test_results['physical_file_works']) {
            $reasons[] = 'Physical file approach is working - safest option';
        }
        
        if (isset($test_results['wordpress_rewrite_works']) && $test_results['wordpress_rewrite_works']) {
            $reasons[] = 'WordPress rewrite approach is working - good fallback';
        }
        
        if (isset($test_results['server_blocks_well_known']) && $test_results['server_blocks_well_known']) {
            $reasons[] = 'Server is blocking .well-known requests - will use WordPress fallback';
        }
        
        return $reasons;
    }
    
    /**
     * Log safety issues for diagnostic purposes
     */
    private function log_safety_issue($message, $details) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'component' => 'ai_plugin_handler_safe',
            'level' => 'warning',
            'message' => $message,
            'details' => $details
        );
        
        // Store in wp_options for admin review
        $existing_logs = get_option('kismet_safety_logs', array());
        $existing_logs[] = $log_entry;
        
        // Keep only last 50 entries
        if (count($existing_logs) > 50) {
            $existing_logs = array_slice($existing_logs, -50);
        }
        
        update_option('kismet_safety_logs', $existing_logs);
        
        // Also log to PHP error log for immediate visibility
        error_log("KISMET SAFETY: $message - " . json_encode($details));
    }
    
    /**
     * Log successful operations
     */
    private function log_success($message, $details) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'component' => 'ai_plugin_handler_safe',
            'level' => 'success',
            'message' => $message,
            'details' => $details
        );
        
        $existing_logs = get_option('kismet_safety_logs', array());
        $existing_logs[] = $log_entry;
        
        if (count($existing_logs) > 50) {
            $existing_logs = array_slice($existing_logs, -50);
        }
        
        update_option('kismet_safety_logs', $existing_logs);
        
        error_log("KISMET SUCCESS: $message - " . json_encode($details));
    }
    
    /**
     * Get status for environment detector
     */
    public function get_endpoint_status() {
        return array(
            'endpoint_created' => $this->endpoint_created,
            'creation_method' => $this->creation_method,
            'last_test_time' => get_option('kismet_ai_plugin_last_test', 'never'),
            'endpoint_url' => get_site_url() . '/.well-known/ai-plugin.json'
        );
    }
    
    // === ADMIN SETTINGS (inherit from original handler) ===
    
    public function add_admin_menu() {
        add_submenu_page(
            'kismet-ask-proxy',
            'AI Plugin Settings',
            'AI Plugin',
            'manage_options',
            'kismet-ai-plugin',
            array($this, 'settings_page')
        );
    }
    
    public function settings_init() {
        register_setting('kismet_ai_plugin', 'kismet_custom_ai_plugin_url');
        register_setting('kismet_ai_plugin', 'kismet_hotel_name');
        register_setting('kismet_ai_plugin', 'kismet_hotel_description');
        register_setting('kismet_ai_plugin', 'kismet_logo_url');
        register_setting('kismet_ai_plugin', 'kismet_contact_email');
        register_setting('kismet_ai_plugin', 'kismet_legal_info_url');
        
        add_settings_section(
            'kismet_ai_plugin_custom_json_section',
            'Custom JSON Configuration',
            array($this, 'custom_json_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_section(
            'kismet_ai_plugin_json_fields_section',
            'AI Plugin JSON Fields',
            array($this, 'json_fields_section_callback'),
            'kismet_ai_plugin'
        );
        
        add_settings_field(
            'kismet_custom_ai_plugin_url',
            'Custom AI Plugin JSON URL',
            array($this, 'custom_url_render'),
            'kismet_ai_plugin',
            'kismet_ai_plugin_custom_json_section'
        );
        
        // JSON field settings
        $fields = array(
            'hotel_name' => 'Hotel/Business Name',
            'hotel_description' => 'Hotel Description',
            'logo_url' => 'Logo URL',
            'contact_email' => 'Contact Email',
            'legal_info_url' => 'Legal/Privacy Policy URL'
        );
        
        foreach ($fields as $field_key => $field_label) {
            add_settings_field(
                "kismet_$field_key",
                $field_label,
                array($this, $field_key . '_render'),
                'kismet_ai_plugin',
                'kismet_ai_plugin_json_fields_section'
            );
        }
    }
    
    public function custom_json_section_callback() {
        echo '<p>Configure a custom AI plugin JSON source or use auto-generated values below.</p>';
        
        // Show current endpoint status
        $status = $this->get_endpoint_status();
        if ($status['endpoint_created']) {
            echo '<div class="notice notice-success"><p>';
            echo "✅ AI Plugin endpoint is active via <strong>{$status['creation_method']}</strong><br>";
            echo "🔗 <a href=\"{$status['endpoint_url']}\" target=\"_blank\">{$status['endpoint_url']}</a>";
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>';
            echo "⚠️ AI Plugin endpoint is not yet active. Check the Kismet ENV page for diagnostics.";
            echo '</p></div>';
        }
    }
    
    public function json_fields_section_callback() {
        echo '<p>These fields are used to generate the AI plugin JSON automatically when no custom URL is provided.</p>';
    }
    
    public function custom_url_render() {
        $value = get_option('kismet_custom_ai_plugin_url', '');
        echo "<input type='url' name='kismet_custom_ai_plugin_url' value='$value' class='regular-text' placeholder='https://example.com/custom-ai-plugin.json'>";
        echo '<p class="description">Leave empty to use auto-generated AI plugin JSON.</p>';
    }
    
    public function hotel_name_render() {
        $value = get_option('kismet_hotel_name', '');
        $placeholder = get_bloginfo('name') ?: 'Your Hotel Name';
        echo "<input type='text' name='kismet_hotel_name' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Auto-detected from site name if empty.</p>';
    }
    
    public function hotel_description_render() {
        $value = get_option('kismet_hotel_description', '');
        $site_name = get_bloginfo('name') ?: 'Your Hotel';
        $placeholder = "Get information about $site_name including amenities, pricing, availability, and booking assistance.";
        echo "<textarea name='kismet_hotel_description' rows='3' class='large-text' placeholder='$placeholder'>$value</textarea>";
        echo '<p class="description">Auto-generated description if empty.</p>';
    }
    
    public function logo_url_render() {
        $value = get_option('kismet_logo_url', '');
        $placeholder = get_site_url() . '/wp-content/uploads/2024/kismet-logo.png';
        echo "<input type='url' name='kismet_logo_url' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Logo image for AI plugin display.</p>';
    }
    
    public function contact_email_render() {
        $value = get_option('kismet_contact_email', '');
        $placeholder = get_option('admin_email', 'admin@example.com');
        echo "<input type='email' name='kismet_contact_email' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Auto-detected from admin email if empty.</p>';
    }
    
    public function legal_info_url_render() {
        $value = get_option('kismet_legal_info_url', '');
        $placeholder = get_site_url() . '/privacy-policy';
        echo "<input type='url' name='kismet_legal_info_url' value='$value' class='regular-text' placeholder='$placeholder'>";
        echo '<p class="description">Link to privacy policy or legal information.</p>';
    }
    
    public function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>Kismet AI Plugin Settings</h1>';
        echo '<form action="options.php" method="post">';
        
        settings_fields('kismet_ai_plugin');
        do_settings_sections('kismet_ai_plugin');
        submit_button();
        
        echo '</form>';
        echo '</div>';
    }
} 