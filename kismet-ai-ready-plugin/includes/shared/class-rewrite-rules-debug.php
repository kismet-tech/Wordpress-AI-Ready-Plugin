<?php
/**
 * Rewrite Rules Debug Utility
 * 
 * Provides debugging and inspection tools for WordPress rewrite rules.
 * This helps identify rule conflicts, duplicates, and verify proper cleanup.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Rewrite_Rules_Debug {
    
    /**
     * Get all current WordPress rewrite rules
     * 
     * @return array Current rewrite rules from WordPress
     */
    public static function get_current_rewrite_rules() {
        global $wp_rewrite;
        
        // Get the compiled rewrite rules from WordPress
        $rewrite_rules = get_option('rewrite_rules');
        
        if (empty($rewrite_rules)) {
            // If no cached rules, generate them
            $wp_rewrite->flush_rules(false);
            $rewrite_rules = get_option('rewrite_rules');
        }
        
        return $rewrite_rules ?: array();
    }
    
    /**
     * Get Kismet-specific rewrite rules
     * 
     * @return array Kismet rewrite rules found in the system
     */
    public static function get_kismet_rewrite_rules() {
        $all_rules = self::get_current_rewrite_rules();
        $kismet_rules = array();
        
        // Patterns that indicate Kismet rules
        $kismet_patterns = array(
            'kismet_ai_plugin',
            'kismet_llms_txt', 
            'kismet_mcp_servers',
            'kismet_test_route',
            'kismet_endpoint',
            '\.well-known/ai-plugin\.json',
            '\.well-known/mcp/servers\.json',
            'llms\.txt'
        );
        
        foreach ($all_rules as $pattern => $rewrite) {
            foreach ($kismet_patterns as $kismet_pattern) {
                if (strpos($pattern, $kismet_pattern) !== false || strpos($rewrite, $kismet_pattern) !== false) {
                    $kismet_rules[$pattern] = $rewrite;
                    break;
                }
            }
        }
        
        return $kismet_rules;
    }
    
    /**
     * Check for duplicate rewrite rules
     * 
     * @return array Information about duplicate rules
     */
    public static function check_for_duplicates() {
        $all_rules = self::get_current_rewrite_rules();
        $duplicates = array();
        $seen_patterns = array();
        
        foreach ($all_rules as $pattern => $rewrite) {
            // Check for exact pattern duplicates
            if (isset($seen_patterns[$pattern])) {
                if (!isset($duplicates['exact_patterns'])) {
                    $duplicates['exact_patterns'] = array();
                }
                $duplicates['exact_patterns'][] = array(
                    'pattern' => $pattern,
                    'rewrite' => $rewrite,
                    'previous_rewrite' => $seen_patterns[$pattern]
                );
            }
            $seen_patterns[$pattern] = $rewrite;
            
            // Check for similar Kismet endpoints
            if (strpos($pattern, 'well-known') !== false || strpos($rewrite, 'kismet') !== false) {
                if (!isset($duplicates['kismet_endpoints'])) {
                    $duplicates['kismet_endpoints'] = array();
                }
                $duplicates['kismet_endpoints'][] = array(
                    'pattern' => $pattern,
                    'rewrite' => $rewrite
                );
            }
        }
        
        return $duplicates;
    }
    
    /**
     * Get rewrite rules statistics
     * 
     * @return array Statistics about current rewrite rules
     */
    public static function get_rewrite_statistics() {
        $all_rules = self::get_current_rewrite_rules();
        $kismet_rules = self::get_kismet_rewrite_rules();
        $duplicates = self::check_for_duplicates();
        
        return array(
            'total_rules' => count($all_rules),
            'kismet_rules' => count($kismet_rules),
            'has_duplicates' => !empty($duplicates['exact_patterns']),
            'duplicate_count' => count($duplicates['exact_patterns'] ?? array()),
            'kismet_endpoint_count' => count($duplicates['kismet_endpoints'] ?? array()),
            'last_flushed' => get_option('rewrite_rules_last_flushed', 'Unknown'),
            'rewrite_rules_cached' => !empty($all_rules)
        );
    }
    
    /**
     * Check if a specific rewrite rule already exists
     * 
     * @param string $pattern The regex pattern to check
     * @param string $rewrite The rewrite rule to check
     * @return bool True if rule already exists
     */
    public static function rule_exists($pattern, $rewrite) {
        $all_rules = self::get_current_rewrite_rules();
        
        // Check for exact match
        if (isset($all_rules[$pattern]) && $all_rules[$pattern] === $rewrite) {
            return true;
        }
        
        // Check for similar patterns that might conflict
        foreach ($all_rules as $existing_pattern => $existing_rewrite) {
            if ($existing_pattern === $pattern || $existing_rewrite === $rewrite) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Safely add a rewrite rule with duplicate checking
     * 
     * @param string $pattern The regex pattern
     * @param string $rewrite The rewrite rule
     * @param string $after Priority position
     * @return array Result of the operation
     */
    public static function safe_add_rewrite_rule($pattern, $rewrite, $after = 'top') {
        $result = array(
            'added' => false,
            'already_exists' => false,
            'message' => '',
            'existing_rule' => null
        );
        
        if (self::rule_exists($pattern, $rewrite)) {
            $result['already_exists'] = true;
            $result['message'] = 'Rule already exists - skipping duplicate';
            
            // Find the existing rule for details
            $all_rules = self::get_current_rewrite_rules();
            if (isset($all_rules[$pattern])) {
                $result['existing_rule'] = $all_rules[$pattern];
            }
            
            return $result;
        }
        
        // Add the rule
        add_rewrite_rule($pattern, $rewrite, $after);
        $result['added'] = true;
        $result['message'] = 'Rewrite rule added successfully';
        
        return $result;
    }
    
    /**
     * Generate HTML output for admin display
     * 
     * @return string HTML content for admin page
     */
    public static function generate_admin_display() {
        $stats = self::get_rewrite_statistics();
        $kismet_rules = self::get_kismet_rewrite_rules();
        $duplicates = self::check_for_duplicates();
        
        ob_start();
        ?>
        <div class="rewrite-rules-debug">
            <h4>📊 Rewrite Rules Statistics</h4>
            <ul>
                <li><strong>Total Rules:</strong> <?php echo $stats['total_rules']; ?></li>
                <li><strong>Kismet Rules:</strong> <?php echo $stats['kismet_rules']; ?></li>
                <li><strong>Has Duplicates:</strong> <?php echo $stats['has_duplicates'] ? '⚠️ Yes (' . $stats['duplicate_count'] . ')' : '✅ No'; ?></li>
                <li><strong>Rules Cached:</strong> <?php echo $stats['rewrite_rules_cached'] ? '✅ Yes' : '❌ No'; ?></li>
            </ul>
            
            <?php if (!empty($kismet_rules)): ?>
            <h4>🔧 Kismet Rewrite Rules</h4>
            <div style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #e1e1e1;">
                            <th style="padding: 8px; text-align: left; border: 1px solid #ccc;">Pattern</th>
                            <th style="padding: 8px; text-align: left; border: 1px solid #ccc;">Rewrite Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kismet_rules as $pattern => $rewrite): ?>
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ccc; font-family: monospace; font-size: 12px;">
                                <code><?php echo esc_html($pattern); ?></code>
                            </td>
                            <td style="padding: 8px; border: 1px solid #ccc; font-family: monospace; font-size: 12px;">
                                <code><?php echo esc_html($rewrite); ?></code>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <h4>✅ No Kismet Rewrite Rules Found</h4>
            <p>This is expected if you're using the static file optimization approach.</p>
            <?php endif; ?>
            
            <?php if ($stats['has_duplicates']): ?>
            <h4>⚠️ Duplicate Rules Detected</h4>
            <div style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0;">
                <p><strong>Found <?php echo $stats['duplicate_count']; ?> duplicate rule(s).</strong></p>
                <?php if (!empty($duplicates['exact_patterns'])): ?>
                <p>Exact duplicates:</p>
                <ul>
                    <?php foreach ($duplicates['exact_patterns'] as $dup): ?>
                    <li><code><?php echo esc_html($dup['pattern']); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <p><strong>Recommendation:</strong> Run "Flush Rewrite Rules" to clean up duplicates.</p>
            </div>
            <?php endif; ?>
            
            <h4>🛠️ Debug Actions</h4>
            <p>
                <button type="button" id="flush-rewrite-rules" class="button button-secondary">
                    🔄 Flush Rewrite Rules
                </button>
                <span style="margin-left: 10px; color: #666;">
                    (Rebuilds rewrite cache from currently active plugins)
                </span>
            </p>
            
            <script>
            document.getElementById('flush-rewrite-rules').addEventListener('click', function() {
                var button = this;
                button.disabled = true;
                button.textContent = '🔄 Flushing...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=kismet_flush_rewrite_rules&nonce=<?php echo wp_create_nonce('kismet_flush_nonce'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.textContent = '✅ Flushed Successfully';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        button.textContent = '❌ Flush Failed';
                        console.error('Flush failed:', data);
                    }
                })
                .catch(error => {
                    button.textContent = '❌ Error';
                    console.error('Error:', error);
                });
            });
            </script>
        </div>
        
        <style>
        .rewrite-rules-debug code {
            background: #f1f1f1;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .rewrite-rules-debug table {
            font-size: 11px;
        }
        .rewrite-rules-debug td code {
            display: block;
            word-break: break-all;
            white-space: pre-wrap;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Register AJAX handlers for admin actions
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_kismet_flush_rewrite_rules', array(__CLASS__, 'ajax_flush_rewrite_rules'));
    }
    
    /**
     * AJAX handler for flushing rewrite rules
     */
    public static function ajax_flush_rewrite_rules() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'kismet_flush_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules(true); // true = hard flush, regenerates .htaccess
        
        // Update timestamp
        update_option('rewrite_rules_last_flushed', current_time('mysql'));
        
        // Log the action
        error_log('KISMET DEBUG: Manual rewrite rules flush performed by admin');
        
        wp_send_json_success(array(
            'message' => 'Rewrite rules flushed successfully',
            'timestamp' => current_time('mysql')
        ));
    }
} 