<?php
/**
 * Kismet Endpoint Status Dashboard
 * 
 * Self-contained class for real-time endpoint testing and status display.
 * Provides immediate feedback on all Kismet AI endpoints with interactive testing.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Endpoint_Status_Dashboard {
    
    /**
     * Initialize the dashboard
     */
    public function __construct() {
        // Register AJAX handlers for endpoint testing
        add_action('wp_ajax_kismet_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_kismet_test_all_endpoints', array($this, 'ajax_test_all_endpoints'));
    }
    
    /**
     * Render the complete dashboard HTML
     */
    public function render_dashboard() {
        echo '<div class="kismet-status-dashboard">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
        echo '<h3 style="margin: 0;">Endpoint Status</h3>';
        echo '<button type="button" id="kismet-test-all" class="button button-secondary">Test All Endpoints</button>';
        echo '</div>';
        
        $endpoints = $this->get_endpoint_definitions();
        
        foreach ($endpoints as $endpoint_key => $endpoint_data) {
            $full_url = get_site_url() . $endpoint_data['url'];
            echo '<div class="kismet-endpoint-row">';
            echo '<div class="kismet-endpoint-name">' . esc_html($endpoint_data['name']) . '</div>';
            echo '<div class="kismet-endpoint-url"><a href="' . esc_url($full_url) . '" target="_blank">' . esc_html($endpoint_data['url']) . '</a></div>';
            echo '<div class="kismet-endpoint-status" id="kismet-status-' . esc_attr($endpoint_key) . '"><span class="endpoint-unknown">⏳ Not tested yet</span></div>';
            echo '<div class="kismet-endpoint-actions"><button type="button" class="button button-small kismet-test-endpoint" data-endpoint="' . esc_attr($endpoint_key) . '">Test Now</button></div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get JavaScript for dashboard functionality
     */
    public function get_dashboard_script() {
        return '
            jQuery(document).ready(function($) {
                // Test individual endpoint
                $(".kismet-test-endpoint").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var endpoint = button.data("endpoint");
                    var statusContainer = $("#kismet-status-" + endpoint);
                    
                    button.prop("disabled", true).text("Testing...");
                    statusContainer.html("<span class=\\"spinner is-active\\"></span> Testing endpoint...");
                    
                    $.post(ajaxurl, {
                        action: "kismet_test_endpoint",
                        endpoint: endpoint,
                        nonce: "' . wp_create_nonce('kismet_test_endpoint') . '"
                    }, function(response) {
                        if (response.success) {
                            statusContainer.html(response.data.html);
                        } else {
                            statusContainer.html("<span class=\\"error\\">❌ Test failed: " + response.data + "</span>");
                        }
                        button.prop("disabled", false).text("Test Now");
                    });
                });
                
                // Test all endpoints
                $("#kismet-test-all").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    
                    button.prop("disabled", true).text("Testing All...");
                    $(".kismet-endpoint-status").html("<span class=\\"spinner is-active\\"></span> Testing...");
                    
                    $.post(ajaxurl, {
                        action: "kismet_test_all_endpoints",
                        nonce: "' . wp_create_nonce('kismet_test_all_endpoints') . '"
                    }, function(response) {
                        if (response.success) {
                            $.each(response.data, function(endpoint, status) {
                                $("#kismet-status-" + endpoint).html(status.html);
                            });
                        } else {
                            $(".kismet-endpoint-status").html("<span class=\\"error\\">❌ Test failed</span>");
                        }
                        button.prop("disabled", false).text("Test All Endpoints");
                    });
                });
                
                // Auto-test on page load
                setTimeout(function() {
                    $("#kismet-test-all").click();
                }, 1000);
            });
        ';
    }
    
    /**
     * Get CSS styles for dashboard
     */
    public function get_dashboard_styles() {
        return '
            .kismet-status-dashboard {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }
            .kismet-endpoint-row {
                display: flex;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            .kismet-endpoint-row:last-child {
                border-bottom: none;
            }
            .kismet-endpoint-name {
                flex: 1;
                font-weight: 600;
            }
            .kismet-endpoint-url {
                flex: 2;
                font-family: monospace;
                font-size: 12px;
                color: #666;
            }
            .kismet-endpoint-status {
                flex: 1;
                text-align: center;
            }
            .kismet-endpoint-actions {
                flex: 0 0 100px;
                text-align: right;
            }
            .kismet-test-endpoint {
                padding: 5px 10px;
                font-size: 11px;
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
     * AJAX handler for testing individual endpoints
     */
    public function ajax_test_endpoint() {
        // Security check
        if (!current_user_can('manage_options') || !check_ajax_referer('kismet_test_endpoint', 'nonce', false)) {
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
        $html = $this->format_endpoint_status_html($status);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for testing all endpoints
     */
    public function ajax_test_all_endpoints() {
        // Security check
        if (!current_user_can('manage_options') || !check_ajax_referer('kismet_test_all_endpoints', 'nonce', false)) {
            wp_send_json_error('Unauthorized');
        }
        
        // Initialize endpoint tester
        $endpoint_tester = new Kismet_Endpoint_Tester();
        $all_statuses = $endpoint_tester->get_endpoint_status_summary();
        
        $response_data = array();
        foreach ($all_statuses as $endpoint_key => $status) {
            $response_data[$endpoint_key] = array(
                'html' => $this->format_endpoint_status_html($status)
            );
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Format endpoint status as HTML for display
     */
    private function format_endpoint_status_html($status) {
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
                'url' => '/.well-known/ai-plugin.json',
                'description' => 'Allows AI tools to discover your hotel assistant'
            ),
            'mcp_servers' => array(
                'name' => 'MCP Servers',
                'url' => '/.well-known/mcp/servers.json',
                'description' => 'Model Context Protocol server discovery'
            ),
            'llms_txt' => array(
                'name' => 'LLMS.txt Policy',
                'url' => '/llms.txt',
                'description' => 'AI/LLM usage policy and guidelines'
            ),
            'ask_endpoint' => array(
                'name' => 'Ask Endpoint',
                'url' => '/ask',
                'description' => 'Interactive chat endpoint for AI and humans'
            ),
            'robots_txt' => array(
                'name' => 'Robots.txt Enhancement',
                'url' => '/robots.txt',
                'description' => 'Enhanced robots.txt with AI directives'
            )
        );
    }
} 