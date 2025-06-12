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
        
        // Register AJAX handlers for the notice dashboard
        add_action('wp_ajax_kismet_notice_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_kismet_notice_test_all_endpoints', array($this, 'ajax_test_all_endpoints'));
        add_action('wp_ajax_kismet_dismiss_status_notice', array($this, 'ajax_dismiss_notice'));
        
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
        ';
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
            return '<span class="endpoint-working">✅ Working</span>';
        } else {
            return '<span class="endpoint-failed">❌ Failed</span>';
        }
    }
    
    /**
     * Format endpoint status for detailed display
     */
    private function format_status_detail($status) {
        $css_class = $status['is_working'] ? 'endpoint-working' : 'endpoint-failed';
        $html = '<div class="' . $css_class . '">';
        $html .= esc_html($status['status']) . '<br>';
        $html .= '<small>' . esc_html($status['result']) . '</small>';
        $html .= '</div>';
        return $html;
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
} 