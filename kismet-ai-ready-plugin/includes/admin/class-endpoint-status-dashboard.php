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
        ?>
        <div class="kismet-status-dashboard">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Endpoint Status Dashboard</h3>
                <div>
                    <button type="button" id="kismet-test-all" class="button button-primary">Test All Endpoints</button>
                    <button type="button" id="kismet-show-example" class="button" style="margin-left: 8px;">Show Button Examples</button>
                </div>
            </div>
            
            <?php
            $endpoints = $this->get_endpoint_definitions();
            foreach ($endpoints as $endpoint_key => $endpoint_config): ?>
                <div class="kismet-endpoint-row">
                    <div class="kismet-endpoint-name"><?php echo esc_html($endpoint_config['name']); ?></div>
                    <div class="kismet-endpoint-url"><?php echo esc_html($endpoint_config['url']); ?></div>
                    <div class="kismet-endpoint-status" id="kismet-status-<?php echo esc_attr($endpoint_key); ?>">
                        <span class="endpoint-unknown">⏳ Ready to test</span>
                    </div>
                    <div class="kismet-endpoint-actions">
                        <button type="button" class="button kismet-test-endpoint" data-endpoint="<?php echo esc_attr($endpoint_key); ?>">Test Now</button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="kismet-example-container" style="display: none; margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 4px;">
                <h4>Button Examples (Simulated Failed Endpoints)</h4>
                <div class="kismet-endpoint-row">
                    <div class="kismet-endpoint-name">AI Plugin (Example)</div>
                    <div class="kismet-endpoint-url">/.well-known/ai-plugin.json</div>
                    <div class="kismet-endpoint-status">
                        <div class="endpoint-failed">
                            ❌ Failed<br>
                            <small>Static file method failed</small><br>
                            <strong style="color: #d63638; font-size: 12px;">✗ Static File not working</strong><br>
                            <button type="button" class="button button-small try-fallback-btn" data-endpoint="ai_plugin" data-strategy="wordpress_rewrite" style="margin-top: 6px; font-size: 11px;">Try WordPress Rewrite</button>
                        </div>
                    </div>
                    <div class="kismet-endpoint-actions">Example</div>
                </div>
                <div class="kismet-endpoint-row">
                    <div class="kismet-endpoint-name">Ask Endpoint (Example)</div>
                    <div class="kismet-endpoint-url">/ask</div>
                    <div class="kismet-endpoint-status">
                        <div class="endpoint-failed">
                            ❌ Failed<br>
                            <small>WordPress rewrite failed</small><br>
                            <strong style="color: #d63638; font-size: 12px;">✗ WordPress Rewrite not working</strong><br>
                            <button type="button" class="button button-small try-fallback-btn" data-endpoint="ask_endpoint" data-strategy="physical_file" style="margin-top: 6px; font-size: 11px;">Try Static File</button>
                        </div>
                    </div>
                    <div class="kismet-endpoint-actions">Example</div>
                </div>
            </div>
        </div>
        <?php
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
                
                // **NEW: Handle fallback button clicks**
                $(document).on("click", ".try-fallback-btn", function(e) {
                    e.preventDefault();
                    var button = $(this);
                    
                    if (confirm("This will attempt to switch to the fallback strategy. Continue?")) {
                        button.prop("disabled", true).text("Switching...");
                        
                        // You could add AJAX call here to actually switch strategies
                        // For now, just show a message
                        alert("Fallback strategy switching will be implemented. For now, please contact support.");
                        button.prop("disabled", false).text("Try Fallback");
                    }
                });
                
                // **NEW: Show button examples**
                $("#kismet-show-example").click(function(e) {
                    e.preventDefault();
                    var container = $("#kismet-example-container");
                    var button = $(this);
                    
                    if (container.is(":visible")) {
                        container.slideUp();
                        button.text("Show Button Examples");
                    } else {
                        container.slideDown();
                        button.text("Hide Examples");
                    }
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
            /* **NEW: Strategy information styles** */
            .endpoint-strategy-info {
                margin-top: 8px;
                padding: 6px 8px;
                background: #f8f9fa;
                border-radius: 3px;
                font-size: 11px;
                line-height: 1.4;
            }
            .strategy-fallback {
                color: #0073aa;
                font-weight: 500;
            }
            .strategy-warning {
                color: #dba617;
                font-weight: 600;
            }
            .strategy-robust {
                color: #00a32a;
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
        
        // **SIMPLIFIED: Show strategy information and next strategy button**
        if (isset($status['current_strategy']) && $status['current_strategy'] !== 'unknown') {
            $strategy_name = $this->format_strategy_name($status['current_strategy']);
            
            if ($status['is_working']) {
                $html .= '<br><strong style="color: #00a32a; font-size: 12px;">✓ ' . esc_html($strategy_name) . ' working</strong>';
            } else {
                $html .= '<br><strong style="color: #d63638; font-size: 12px;">✗ ' . esc_html($strategy_name) . ' not working</strong>';
                
                // **SIMPLIFIED: Show next strategy button based on simple array index**
                if (isset($status['current_strategy_index'])) {
                    $next_strategy = $this->get_next_strategy($status['current_strategy_index']);
                    if ($next_strategy) {
                        $next_strategy_name = $this->format_strategy_name($next_strategy);
                        $html .= '<br><button type="button" class="button button-small try-fallback-btn" data-endpoint="' . esc_attr($this->get_endpoint_key_from_status($status)) . '" data-strategy="' . esc_attr($next_strategy) . '" style="margin-top: 6px; font-size: 11px;">Try ' . esc_html($next_strategy_name) . '</button>';
                    }
                }
            }
        }
        
        $html .= '</div>';
        return $html;
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
     * **NEW: Extract endpoint key from status data for button actions**
     */
    private function get_endpoint_key_from_status($status) {
        // Map URL patterns to endpoint keys used in the dashboard
        $url_to_key_map = array(
            '/.well-known/ai-plugin.json' => 'ai_plugin',
            '/.well-known/mcp/servers.json' => 'mcp_servers',
            '/llms.txt' => 'llms_txt',
            '/ask' => 'ask_endpoint',
            '/robots.txt' => 'robots_txt'
        );
        
        foreach ($url_to_key_map as $url_pattern => $key) {
            if (isset($status['url']) && strpos($status['url'], $url_pattern) !== false) {
                return $key;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Format strategy names for display
     */
    private function format_strategy_name($strategy) {
        switch ($strategy) {
            case 'wordpress_rewrite':
                return 'WordPress Rewrite';
            case 'physical_file':
                return 'Static File';
            case 'failed':
                return 'Setup Failed';
            case 'none_available':
                return 'No Fallback';
            case 'manual_intervention_required':
                return 'Manual Fix Needed';
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