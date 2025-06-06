<?php
/**
 * Kismet Report Generator - HTML admin report generation
 *
 * Handles formatting and display of environment and endpoint test results
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Report_Generator {
    
    /**
     * Generate complete admin report HTML
     * 
     * @param array $report Full environment report data
     * @return string Complete HTML report
     */
    public function generate_admin_report($report) {
        $html = '';
        
        // Overall status at the top
        $html .= $this->generate_status_header($report);
        
        // Simple Endpoint Status Summary (what the user really wants to see)
        $html .= $this->generate_endpoint_summary_table($report);
        
        // Errors and warnings (if any)
        $html .= $this->generate_errors_and_warnings($report);
        
        // Safety systems status
        $html .= $this->generate_safety_systems_section($report);
        
        // Detailed technical information (collapsible)
        $html .= $this->generate_technical_details($report);
        
        return $html;
    }
    
    /**
     * Generate status header section
     * 
     * @param array $report Environment report data
     * @return string HTML for status header
     */
    private function generate_status_header($report) {
        $overall_status = $report['overall_status'] ?? 'unknown';
        
        $status_messages = array(
            'fully_functional' => '🟢 All systems operational',
            'mostly_functional' => '🟡 Minor issues detected',
            'partially_functional' => '🟠 Some limitations present', 
            'limited' => '🔴 Significant limitations',
            'critical_issues' => '🚫 Critical issues require attention'
        );
        
        $status_text = $status_messages[$overall_status] ?? '❓ Status unknown';
        $status_class = $overall_status === 'fully_functional' ? 'notice-success' : 
                       ($overall_status === 'critical_issues' ? 'notice-error' : 'notice-warning');
        
        return "<div class='notice $status_class'><p><strong>Status: $status_text</strong></p></div>";
    }
    
    /**
     * Generate simple endpoint summary table - THIS IS WHAT USERS WANT TO SEE
     * 
     * @param array $report Environment report data
     * @return string HTML for endpoint summary table
     */
    private function generate_endpoint_summary_table($report) {
        $html = '<h4>📍 Endpoint Status - Simple Summary</h4>';
        $html .= '<table class="widefat" style="margin-bottom: 2em;">';
        $html .= '<thead><tr><th>Endpoint</th><th>Status</th><th>What We Checked</th><th>Test Result</th><th>What We Did</th></tr></thead>';
        $html .= '<tbody>';
        
        // Get endpoint data from report
        $endpoints = isset($report['endpoint_tests']) ? $report['endpoint_tests'] : array();
        
        foreach ($endpoints as $key => $endpoint) {
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html($endpoint['name']) . '</strong><br/>';
            $html .= '<small><a href="' . esc_url($endpoint['url']) . '" target="_blank">' . esc_html($endpoint['url']) . '</a></small></td>';
            $html .= '<td>' . esc_html($endpoint['status']) . '</td>';
            $html .= '<td>' . esc_html($endpoint['check_done']) . '</td>';
            $html .= '<td>' . esc_html($endpoint['result']) . '</td>';
            $html .= '<td>' . esc_html($endpoint['what_we_did']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Generate errors and warnings section
     * 
     * @param array $report Environment report data
     * @return string HTML for errors and warnings
     */
    private function generate_errors_and_warnings($report) {
        $html = '';
        
        // Errors
        if (!empty($report['errors'])) {
            $html .= '<h4>🚫 Errors</h4><ul>';
            foreach ($report['errors'] as $error) {
                $html .= '<li style="color: #d63384;">' . esc_html($error) . '</li>';
            }
            $html .= '</ul>';
        }
        
        // Warnings  
        if (!empty($report['warnings'])) {
            $html .= '<h4>⚠️ Warnings</h4><ul>';
            foreach ($report['warnings'] as $warning) {
                $html .= '<li style="color: #fd7e14;">' . esc_html($warning) . '</li>';
            }
            $html .= '</ul>';
        }
        
        return $html;
    }
    
    /**
     * Generate safety systems section
     * 
     * @param array $report Environment report data
     * @return string HTML for safety systems status
     */
    private function generate_safety_systems_section($report) {
        $safety_status = $report['safety_systems'] ?? array();
        
        $html = '<h4>🛡️ Safety Systems Status</h4>';
        $html .= '<p>' . $this->get_safety_systems_summary($safety_status) . '</p>';
        
        return $html;
    }
    
    /**
     * Generate technical details section (collapsible)
     * 
     * @param array $report Environment report data
     * @return string HTML for technical details
     */
    private function generate_technical_details($report) {
        $html = '<details style="margin-top: 2em;"><summary><strong>🔧 Technical Details (Click to expand)</strong></summary>';
        
        // System checks
        if (isset($report['checks'])) {
            $html .= $this->format_system_checks($report['checks']);
        }
        
        // Raw safety systems data (for debugging)
        if (isset($report['safety_systems'])) {
            $html .= '<h5>Safety Systems Details</h5>';
            $html .= '<pre style="background: #f1f1f1; padding: 10px; font-size: 12px;">';
            $html .= esc_html(print_r($report['safety_systems'], true));
            $html .= '</pre>';
        }
        
        $html .= '</details>';
        
        return $html;
    }
    
    /**
     * Format system checks for display
     * 
     * @param array $checks System check results
     * @return string HTML for system checks
     */
    private function format_system_checks($checks) {
        $html = '<h5>System Checks</h5>';
        
        foreach ($checks as $category => $check_data) {
            $html .= '<h6>' . ucwords(str_replace('_', ' ', $category)) . '</h6>';
            $html .= '<ul>';
            
            if (is_array($check_data)) {
                foreach ($check_data as $key => $value) {
                    if (is_bool($value)) {
                        $icon = $value ? '✅' : '❌';
                        $html .= "<li>$icon " . ucwords(str_replace('_', ' ', $key)) . '</li>';
                    } elseif (is_string($value) || is_numeric($value)) {
                        $html .= '<li><strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong> ' . esc_html($value) . '</li>';
                    }
                }
            }
            
            $html .= '</ul>';
        }
        
        return $html;
    }
    
    /**
     * Generate human-friendly summary for safety systems check
     * 
     * @param array $result Safety systems check result
     * @return string Human-friendly summary
     */
    private function get_safety_systems_summary($result) {
        $status = $result['status'] ?? 'unknown';
        
        $summary_messages = array(
            'fully_functional' => '🟢 All endpoint creation methods working properly - plugin ready to deploy endpoints safely',
            'mostly_functional' => '🟡 Core safety features working - minor limitations with advanced features', 
            'basic_functional' => '🟠 Basic safety features available - some advanced protections unavailable',
            'limited' => '🔴 Limited safety features - manual intervention may be required for some endpoints',
            'unavailable' => '🚫 Safety systems unavailable - endpoint creation disabled for protection'
        );
        
        return $summary_messages[$status] ?? '❓ Safety system status unclear';
    }
} 