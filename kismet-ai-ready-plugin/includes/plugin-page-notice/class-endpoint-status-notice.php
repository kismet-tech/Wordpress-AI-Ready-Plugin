<?php
/**
 * Kismet Endpoint Status Notice
 * 
 * Displays the endpoint testing dashboard as a WordPress admin notice
 * providing immediate visibility of plugin status across admin pages.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Endpoint_Status_Notice {
    
    /**
     * Initialize the notice system
     */
    public function __construct() {
        // Register admin notice hook
        add_action('admin_notices', array($this, 'display_endpoint_status_notice'));
        
        // Register AJAX handlers for the notice dashboard (testing only)
        add_action('wp_ajax_kismet_notice_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_kismet_notice_test_all_endpoints', array($this, 'ajax_test_all_endpoints'));
        add_action('wp_ajax_kismet_dismiss_status_notice', array($this, 'ajax_dismiss_notice'));
        
        // **NEW: Initialize strategy switcher for secure admin actions**
        require_once(plugin_dir_path(__FILE__) . '../admin/class-strategy-switcher.php');
        $this->strategy_switcher = new Kismet_Strategy_Switcher();
        
        // Add admin scripts for notice functionality
        add_action('admin_enqueue_scripts', array($this, 'enqueue_notice_scripts'));
    }
    
    /**
     * Display the endpoint status notice
     */
    public function display_endpoint_status_notice() {
        // Only show on main admin pages, not on every page
        $screen = get_current_screen();
        $allowed_screens = array('dashboard', 'plugins', 'options-general');
        
        if (!in_array($screen->id, $allowed_screens)) {
            return;
        }
        
        // Check if user dismissed the notice
        if (get_user_meta(get_current_user_id(), 'kismet_status_notice_dismissed', true)) {
            return;
        }
        
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div id="kismet-status-notice" class="notice notice-info is-dismissible kismet-endpoint-notice">';
        echo '<div class="kismet-notice-header">';
        echo '<h3><span class="dashicons dashicons-networking"></span> Kismet AI Plugin - Endpoint Status</h3>';
        echo '<p>Real-time status of your AI endpoints. <a href="' . admin_url('options-general.php?page=kismet-ai-plugin-settings') . '">Visit settings</a> for full configuration.</p>';
        echo '</div>';
        
        $this->render_compact_dashboard();
        
        echo '</div>';
    }
    
    /**
     * Render a compact version of the endpoint dashboard for notice display
     */
    private function render_compact_dashboard() {
        echo '<div class="kismet-notice-dashboard">';
        echo '<div class="kismet-notice-controls">';
        echo '<button type="button" id="kismet-notice-test-all" class="button button-small">Test All Endpoints</button>';
        echo '<button type="button" id="kismet-notice-toggle-details" class="button button-small">Show Details</button>';
        echo '</div>';
        
        // **NEW: Add server information summary**
        $this->render_server_info_summary();
        
        // Status summary row (always visible)
        echo '<div class="kismet-notice-summary" id="kismet-notice-summary">';
        echo '<div class="endpoint-status-item" id="kismet-notice-status-ai_plugin">';
        echo '<span class="endpoint-name">AI Plugin</span>';
        echo '<span class="endpoint-status">⏳ Not tested</span>';
        echo '</div>';
        echo '<div class="endpoint-status-item" id="kismet-notice-status-mcp_servers">';
        echo '<span class="endpoint-name">MCP Servers</span>';
        echo '<span class="endpoint-status">⏳ Not tested</span>';
        echo '</div>';
        echo '<div class="endpoint-status-item" id="kismet-notice-status-llms_txt">';
        echo '<span class="endpoint-name">LLMS.txt</span>';
        echo '<span class="endpoint-status">⏳ Not tested</span>';
        echo '</div>';
        echo '<div class="endpoint-status-item" id="kismet-notice-status-ask_endpoint">';
        echo '<span class="endpoint-name">Ask Endpoint</span>';
        echo '<span class="endpoint-status">⏳ Not tested</span>';
        echo '</div>';
        echo '<div class="endpoint-status-item" id="kismet-notice-status-robots_txt">';
        echo '<span class="endpoint-name">Robots.txt</span>';
        echo '<span class="endpoint-status">⏳ Not tested</span>';
        echo '</div>';
        echo '</div>';
        
        // Detailed view (hidden by default)
        echo '<div class="kismet-notice-details" id="kismet-notice-details" style="display: none;">';
        
        $endpoints = $this->get_endpoint_definitions();
        
        foreach ($endpoints as $endpoint_key => $endpoint_data) {
            $full_url = get_site_url() . $endpoint_data['url'];
            echo '<div class="kismet-notice-endpoint-row">';
            echo '<div class="endpoint-info">';
            echo '<strong>' . esc_html($endpoint_data['name']) . '</strong>';
            echo '<br><a href="' . esc_url($full_url) . '" target="_blank">' . esc_html($endpoint_data['url']) . '</a>';
            echo '</div>';
            echo '<div class="endpoint-result" id="kismet-notice-detail-' . esc_attr($endpoint_key) . '">⏳ Not tested yet</div>';
            echo '<div class="endpoint-action">';
            echo '<button type="button" class="button button-small kismet-notice-test-endpoint" data-endpoint="' . esc_attr($endpoint_key) . '">Test</button>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Enqueue scripts and styles for the notice
     */
    public function enqueue_notice_scripts() {
        // Only enqueue on pages where we show the notice
        $screen = get_current_screen();
        $allowed_screens = array('dashboard', 'plugins', 'options-general');
        
        if (!in_array($screen->id, $allowed_screens)) {
            return;
        }
        
        // Add the JavaScript for notice functionality
        wp_add_inline_script('jquery', $this->get_notice_script());
        
        // Add the CSS styles for notice
        wp_add_inline_style('wp-admin', $this->get_notice_styles());
    }
    
    /**
     * Get JavaScript for notice functionality
     */
    private function get_notice_script() {
        return '
            jQuery(document).ready(function($) {
                // Test individual endpoint in notice
                $(".kismet-notice-test-endpoint").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var endpoint = button.data("endpoint");
                    var statusSummary = $("#kismet-notice-status-" + endpoint + " .endpoint-status");
                    var statusDetail = $("#kismet-notice-detail-" + endpoint);
                    
                    button.prop("disabled", true).text("Testing...");
                    statusSummary.html("🔄 Testing...");
                    statusDetail.html("🔄 Testing endpoint...");
                    
                    $.post(ajaxurl, {
                        action: "kismet_notice_test_endpoint",
                        endpoint: endpoint,
                        nonce: "' . wp_create_nonce('kismet_notice_test_endpoint') . '"
                    }, function(response) {
                        if (response.success) {
                            statusSummary.html(response.data.summary);
                            statusDetail.html(response.data.detail);
                        } else {
                            statusSummary.html("❌ Failed");
                            statusDetail.html("❌ Test failed: " + response.data);
                        }
                        button.prop("disabled", false).text("Test");
                    });
                });
                
                // Test all endpoints in notice
                $("#kismet-notice-test-all").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    
                    button.prop("disabled", true).text("Testing All...");
                    $(".endpoint-status").html("🔄 Testing...");
                    
                    $.post(ajaxurl, {
                        action: "kismet_notice_test_all_endpoints",
                        nonce: "' . wp_create_nonce('kismet_notice_test_all_endpoints') . '"
                    }, function(response) {
                        if (response.success) {
                            $.each(response.data, function(endpoint, status) {
                                $("#kismet-notice-status-" + endpoint + " .endpoint-status").html(status.summary);
                                $("#kismet-notice-detail-" + endpoint).html(status.detail);
                            });
                        } else {
                            $(".endpoint-status").html("❌ Test failed");
                        }
                        button.prop("disabled", false).text("Test All Endpoints");
                    });
                });
                
                // Toggle detailed view
                $("#kismet-notice-toggle-details").click(function(e) {
                    e.preventDefault();
                    var details = $("#kismet-notice-details");
                    var button = $(this);
                    
                    if (details.is(":visible")) {
                        details.slideUp();
                        button.text("Show Details");
                    } else {
                        details.slideDown();
                        button.text("Hide Details");
                    }
                });
                
                // Handle notice dismissal
                $("#kismet-status-notice").on("click", ".notice-dismiss", function() {
                    $.post(ajaxurl, {
                        action: "kismet_dismiss_status_notice",
                        nonce: "' . wp_create_nonce('kismet_dismiss_status_notice') . '"
                    });
                });
                
                // **NEW: Handle fallback button clicks in notice**
                $(document).on("click", ".try-fallback-btn", function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var endpoint = button.data("endpoint");
                    var strategy = button.data("strategy");
                    var strategyName = strategy.replace("_", " ");
                    
                    if (confirm("This will switch to " + strategyName + " strategy and may take a moment. Continue?")) {
                        button.prop("disabled", true).text("Switching...");
                        
                        // Get the endpoint path from the endpoint key
                        var endpointPaths = {
                            "ai_plugin": "/.well-known/ai-plugin.json",
                            "mcp_servers": "/.well-known/mcp/servers.json", 
                            "llms_txt": "/llms.txt",
                            "ask_endpoint": "/ask",
                            "robots_txt": "/robots.txt"
                        };
                        
                        var endpointPath = endpointPaths[endpoint];
                        if (!endpointPath) {
                            alert("Unknown endpoint: " + endpoint);
                            button.prop("disabled", false).text("Try " + strategyName);
                            return;
                        }
                        
                        $.post(ajaxurl, {
                            action: "kismet_notice_switch_strategy",
                            endpoint_path: endpointPath,
                            target_strategy: strategy,
                            nonce: "' . wp_create_nonce('kismet_notice_switch_strategy') . '"
                        }, function(response) {
                            if (response.success) {
                                var endpointName = $("#kismet-notice-status-" + endpoint + " .endpoint-name").text() || endpoint;
                                alert("✅ Success! " + endpointName + " switched to " + strategyName + " strategy.\\n\\nDetails: " + response.data.message);
                                
                                // Re-test all endpoints to show updated status
                                $("#kismet-notice-test-all").click();
                            } else {
                                var endpointName = $("#kismet-notice-status-" + endpoint + " .endpoint-name").text() || endpoint;
                                alert("❌ Strategy switch failed for " + endpointName + ": " + response.data);
                            }
                            button.prop("disabled", false).text("Try " + strategyName);
                        }).fail(function() {
                            var endpointName = $("#kismet-notice-status-" + endpoint + " .endpoint-name").text() || endpoint;
                            alert("🌐 Network error occurred during strategy switch for " + endpointName + ".");
                            button.prop("disabled", false).text("Try " + strategyName);
                        });
                    }
                });
                
                // Auto-test on load (after a short delay)
                setTimeout(function() {
                    $("#kismet-notice-test-all").click();
                }, 2000);
            });
        ';
    }
    
    /**
     * Get CSS styles for notice
     */
    private function get_notice_styles() {
        return '
            .kismet-endpoint-notice {
                padding: 15px !important;
                border-left: 4px solid #00a32a !important;
            }
            .kismet-notice-header h3 {
                margin: 0 0 10px 0;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .kismet-notice-header .dashicons {
                color: #00a32a;
            }
            .kismet-notice-dashboard {
                margin-top: 10px;
            }
            .kismet-notice-controls {
                margin-bottom: 15px;
                display: flex;
                gap: 10px;
            }
            .kismet-notice-summary {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-bottom: 10px;
            }
            .endpoint-status-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                min-width: 120px;
                padding: 8px;
                background: #f9f9f9;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .endpoint-status-item .endpoint-name {
                font-weight: 600;
                font-size: 12px;
                margin-bottom: 4px;
            }
            .endpoint-status-item .endpoint-status {
                font-size: 11px;
                text-align: center;
            }
            .kismet-notice-details {
                border-top: 1px solid #ddd;
                padding-top: 15px;
                margin-top: 15px;
            }
            .kismet-notice-endpoint-row {
                display: flex;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .kismet-notice-endpoint-row:last-child {
                border-bottom: none;
            }
            .kismet-notice-endpoint-row .endpoint-info {
                flex: 1;
                font-size: 12px;
            }
            .kismet-notice-endpoint-row .endpoint-result {
                flex: 1;
                text-align: center;
                font-size: 11px;
            }
            .kismet-notice-endpoint-row .endpoint-action {
                flex: 0 0 60px;
                text-align: right;
            }
            .endpoint-working {
                color: #00a32a;
                font-weight: 600;
            }
            .endpoint-failed {
                color: #d63638;
                font-weight: 600;
            }
            .endpoint-unknown {
                color: #dba617;
                font-weight: 600;
            }
            /* **NEW: Strategy information styles for notice** */
            .notice-strategy-info {
                margin-top: 6px;
                padding: 4px 6px;
                background: #f0f0f1;
                border-radius: 2px;
                font-size: 10px;
                line-height: 1.3;
            }
            .notice-strategy-fallback {
                color: #0073aa;
                font-weight: 500;
            }
            .notice-strategy-warning {
                color: #dba617;
                font-weight: 600;
            }
            /* **NEW: Server information styles for notice** */
            .kismet-notice-server-info {
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #e1e4e8;
                border-radius: 4px;
            }
            .kismet-notice-server-info h4 {
                margin: 0 0 10px 0;
                font-size: 13px;
                color: #374151;
            }
            .server-variables-list {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 5px;
                font-size: 11px;
            }
            .server-var {
                display: flex;
                justify-content: space-between;
                padding: 2px 0;
            }
        ';
    }
    
    /**
     * **NEW: Render server information summary for the notice**
     */
    private function render_server_info_summary() {
        global $kismet_ask_proxy_plugin;
        
        echo '<div class="kismet-notice-server-info">';
        echo '<h4>Server Detection Variables</h4>';
        
        if ($kismet_ask_proxy_plugin) {
            $server_detector = $kismet_ask_proxy_plugin->get_server_detector();
            echo '<div class="server-variables-list">';
            echo '<div class="server-var"><strong>Server Software:</strong> <code>' . esc_html($server_detector->server_software ?: 'null') . '</code></div>';
            echo '<div class="server-var"><strong>Apache:</strong> ' . ($server_detector->is_apache ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</div>';
            echo '<div class="server-var"><strong>Nginx:</strong> ' . ($server_detector->is_nginx ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</div>';
            echo '<div class="server-var"><strong>IIS:</strong> ' . ($server_detector->is_iis ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</div>';
            echo '<div class="server-var"><strong>LiteSpeed:</strong> ' . ($server_detector->is_litespeed ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</div>';
            echo '<div class="server-var"><strong>Server Version:</strong> <code>' . esc_html($server_detector->server_version ?: 'null') . '</code></div>';
            echo '<div class="server-var"><strong>Supports .htaccess:</strong> ' . ($server_detector->supports_htaccess ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</div>';
            echo '<div class="server-var"><strong>Supports Nginx Config:</strong> ' . ($server_detector->supports_nginx_config ? '<span style="color: green;">True</span>' : '<span style="color: red;">False</span>') . '</div>';
            echo '<div class="server-var"><strong>Server Capabilities:</strong> ';
            if ($server_detector->supports_htaccess) echo '<span style="color: green;">.htaccess ✓</span> ';
            if ($server_detector->supports_nginx_config) echo '<span style="color: green;">nginx ✓</span> ';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="server-variables-list">';
            echo '<div class="server-var"><strong>Server Detection:</strong> <span style="color: red;">Unavailable</span></div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * AJAX handler for testing individual endpoints in notice
     */
    public function ajax_test_endpoint() {
        // Security check
        if (!current_user_can('manage_options') || !check_ajax_referer('kismet_notice_test_endpoint', 'nonce', false)) {
            wp_send_json_error('Unauthorized');
        }
        
        $endpoint_key = sanitize_text_field($_POST['endpoint']);
        
        // Initialize endpoint tester
        $endpoint_tester = new Kismet_Endpoint_Tester();
        $all_statuses = $endpoint_tester->get_endpoint_status_summary();
        
        if (!isset($all_statuses[$endpoint_key])) {
            wp_send_json_error('Unknown endpoint');
        }
        
        $status = $all_statuses[$endpoint_key];
        $response_data = array(
            'summary' => $this->format_status_summary($status),
            'detail' => $this->format_status_detail($status)
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX handler for testing all endpoints in notice
     */
    public function ajax_test_all_endpoints() {
        // Security check
        if (!current_user_can('manage_options') || !check_ajax_referer('kismet_notice_test_all_endpoints', 'nonce', false)) {
            wp_send_json_error('Unauthorized');
        }
        
        // Initialize endpoint tester
        $endpoint_tester = new Kismet_Endpoint_Tester();
        $all_statuses = $endpoint_tester->get_endpoint_status_summary();
        
        $response_data = array();
        foreach ($all_statuses as $endpoint_key => $status) {
            $response_data[$endpoint_key] = array(
                'summary' => $this->format_status_summary($status),
                'detail' => $this->format_status_detail($status)
            );
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX handler for dismissing the notice
     */
    public function ajax_dismiss_notice() {
        // Security check
        if (!current_user_can('manage_options') || !check_ajax_referer('kismet_dismiss_status_notice', 'nonce', false)) {
            wp_send_json_error('Unauthorized');
        }
        
        // Remember that user dismissed the notice
        update_user_meta(get_current_user_id(), 'kismet_status_notice_dismissed', true);
        
        wp_send_json_success();
    }
    
    /**
     * Format endpoint status for summary display
     */
    private function format_status_summary($status) {
        if ($status['is_working']) {
            $summary = '<span class="endpoint-working">✅ Working</span>';
            // Show what method is working
            if (isset($status['current_strategy']) && $status['current_strategy'] !== 'unknown') {
                $strategy_name = $this->format_strategy_name($status['current_strategy']);
                $summary .= '<br><small style="color: #00a32a;">via ' . esc_html($strategy_name) . '</small>';
            }
        } else {
            $summary = '<span class="endpoint-failed">❌ Failed</span>';
            // Show what method failed and next strategy to try
            if (isset($status['current_strategy']) && $status['current_strategy'] !== 'unknown') {
                $strategy_name = $this->format_strategy_name($status['current_strategy']);
                $summary .= '<br><small style="color: #d63638;">' . esc_html($strategy_name) . ' not working</small>';
                
                // **SECURE: Show next strategy link using WordPress admin actions**
                if (isset($status['current_strategy_index'])) {
                    $next_strategy = $this->get_next_strategy($status['current_strategy_index']);
                    if ($next_strategy) {
                        $next_strategy_name = $this->format_strategy_name($next_strategy);
                        $endpoint_path = $this->get_endpoint_path_from_status($status);
                        if ($endpoint_path) {
                            $switch_url = $this->strategy_switcher->get_strategy_switch_url($endpoint_path, $next_strategy);
                            $summary .= '<br><a href="' . esc_url($switch_url) . '" class="button button-small" style="margin-top: 4px; font-size: 10px; padding: 2px 6px;" onclick="return confirm(\'Switch to ' . esc_js($next_strategy_name) . ' strategy? This will deactivate and reactivate the plugin.\')">Try ' . esc_html($next_strategy_name) . '</a>';
                        }
                    }
                }
            }
        }
        
        return $summary;
    }
    
    /**
     * **SIMPLIFIED: Get the next strategy to try based on simple array index**
     */
    private function get_next_strategy($current_index) {
        $strategies = array('physical_file', 'wordpress_rewrite');
        $next_index = ($current_index + 1) % count($strategies);
        return $strategies[$next_index];
    }
    
    /**
     * **NEW: Extract endpoint path from status data for admin actions**
     */
    private function get_endpoint_path_from_status($status) {
        // Map URL patterns to endpoint paths
        $url_to_path_map = array(
            '/.well-known/ai-plugin.json' => '/.well-known/ai-plugin.json',
            '/.well-known/mcp/servers.json' => '/.well-known/mcp/servers.json',
            '/llms.txt' => '/llms.txt',
            '/ask' => '/ask',
            '/robots.txt' => '/robots.txt'
        );
        
        foreach ($url_to_path_map as $url_pattern => $path) {
            if (isset($status['url']) && strpos($status['url'], $url_pattern) !== false) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Format endpoint status for detailed display
     */
    private function format_status_detail($status) {
        $css_class = $status['is_working'] ? 'endpoint-working' : 'endpoint-failed';
        $html = '<div class="' . $css_class . '">';
        $html .= esc_html($status['status']) . '<br>';
        $html .= '<small>' . esc_html($status['result']) . '</small>';
        
        // **NEW: Add strategy information to detailed view**
        if (isset($status['current_strategy']) && $status['current_strategy'] !== 'unknown') {
            $html .= '<br><div class="notice-strategy-info">';
            $html .= '<strong>Method:</strong> ' . esc_html($this->format_strategy_name($status['current_strategy']));
            
            // Show fallback if available
            if (isset($status['fallback_strategy']) && $status['fallback_strategy'] !== 'unknown' && $status['fallback_strategy'] !== 'none_available') {
                if ($status['fallback_strategy'] === 'manual_intervention_required') {
                    $html .= '<br><span class="notice-strategy-warning">⚠️ No fallback</span>';
                } else {
                    $html .= '<br><span class="notice-strategy-fallback">Fallback: ' . esc_html($this->format_strategy_name($status['fallback_strategy'])) . '</span>';
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * **NEW: Format strategy names for display**
     */
    private function format_strategy_name($strategy) {
        switch ($strategy) {
            case 'wordpress_rewrite':
                return 'WP Rewrite';
            case 'physical_file':
                return 'Static File';
            case 'failed':
                return 'Failed';
            case 'none_available':
                return 'No Fallback';
            case 'manual_intervention_required':
                return 'Manual Fix';
            default:
                return ucfirst(str_replace('_', ' ', $strategy));
        }
    }
    
    /**
     * Get endpoint definitions for testing
     */
    private function get_endpoint_definitions() {
        return array(
            'ai_plugin' => array(
                'name' => 'AI Plugin Discovery',
                'url' => '/.well-known/ai-plugin.json'
            ),
            'mcp_servers' => array(
                'name' => 'MCP Servers',
                'url' => '/.well-known/mcp/servers.json'
            ),
            'llms_txt' => array(
                'name' => 'LLMS.txt Policy',
                'url' => '/llms.txt'
            ),
            'ask_endpoint' => array(
                'name' => 'Ask Endpoint',
                'url' => '/ask'
            ),
            'robots_txt' => array(
                'name' => 'Robots.txt Enhancement',
                'url' => '/robots.txt'
            )
        );
    }
    
    // **REMOVED: Old AJAX strategy switching handler - now using secure WordPress admin actions**
} 