<?php
/**
 * Htaccess Manager for Kismet Tracking
 * 
 * Manages .htaccess rules to force tracked endpoints through WordPress
 * even when physical files exist.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Htaccess_Manager {
    
    const MARKER_BEGIN = '# BEGIN Kismet Tracking';
    const MARKER_END = '# END Kismet Tracking';
    
    /**
     * Add tracking rewrite rules to .htaccess
     */
    public static function add_tracking_rules() {
        $htaccess_file = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_file) || !is_writable($htaccess_file)) {
            error_log('Kismet Tracking: .htaccess file not writable');
            return false;
        }
        
        // Read current content
        $content = file_get_contents($htaccess_file);
        
        // Remove existing rules if present
        $content = self::remove_tracking_rules_from_content($content);
        
        // Generate new rules
        $tracking_rules = self::generate_tracking_rules();
        
        // Find WordPress section and insert before it
        $wordpress_begin = '# BEGIN WordPress';
        $insert_position = strpos($content, $wordpress_begin);
        
        if ($insert_position === false) {
            // WordPress section not found, append to end
            $new_content = $content . "\n" . $tracking_rules;
        } else {
            // Insert before WordPress section
            $new_content = substr($content, 0, $insert_position) . 
                          $tracking_rules . "\n" . 
                          substr($content, $insert_position);
        }
        
        // Write back to file
        $result = file_put_contents($htaccess_file, $new_content);
        
        if ($result === false) {
            error_log('Kismet Tracking: Failed to write .htaccess rules');
            return false;
        }
        
        error_log('Kismet Tracking: Successfully added .htaccess rules');
        return true;
    }
    
    /**
     * Remove tracking rewrite rules from .htaccess
     */
    public static function remove_tracking_rules() {
        $htaccess_file = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_file) || !is_writable($htaccess_file)) {
            return false;
        }
        
        $content = file_get_contents($htaccess_file);
        $new_content = self::remove_tracking_rules_from_content($content);
        
        if ($content !== $new_content) {
            $result = file_put_contents($htaccess_file, $new_content);
            error_log('Kismet Tracking: Removed .htaccess rules');
            return $result !== false;
        }
        
        return true;
    }
    
    /**
     * Remove tracking rules from content string
     */
    private static function remove_tracking_rules_from_content($content) {
        $begin_pos = strpos($content, self::MARKER_BEGIN);
        $end_pos = strpos($content, self::MARKER_END);
        
        if ($begin_pos !== false && $end_pos !== false) {
            // Include the end marker in removal
            $end_pos += strlen(self::MARKER_END);
            
            // Remove the entire section including any trailing newlines
            $before = substr($content, 0, $begin_pos);
            $after = substr($content, $end_pos);
            
            // Clean up extra newlines
            $before = rtrim($before);
            $after = ltrim($after, "\n");
            
            return $before . ($before && $after ? "\n" : "") . $after;
        }
        
        return $content;
    }
    
    /**
     * Generate the tracking rewrite rules
     * 
     * HTACCESS REWRITE STRATEGY: Forces physical files through WordPress for tracking
     * 
     * PROBLEM: WordPress default .htaccess serves physical files directly via Apache,
     * bypassing WordPress entirely and preventing analytics tracking.
     * 
     * SOLUTION: These rules intercept requests for specific physical files and rewrite
     * them to WordPress with special query parameters (kismet_endpoint=robots).
     * The Universal Tracker catches these and tracks access before serving content.
     * 
     * NOTE: Virtual endpoints like /ask don't need this - they naturally route through
     * WordPress and use the Individual Endpoint Strategy for tracking.
     */
    private static function generate_tracking_rules() {
        $rules = self::MARKER_BEGIN . "\n";
        $rules .= "<IfModule mod_rewrite.c>\n";
        $rules .= "RewriteEngine On\n";
        $rules .= "# HTACCESS REWRITE STRATEGY: Force physical files through WordPress for tracking\n";
        $rules .= "# These files would normally be served directly by Apache, bypassing WordPress\n";
        $rules .= "RewriteRule ^robots\\.txt$ /index.php?kismet_endpoint=robots [L,QSA]\n";
        $rules .= "RewriteRule ^llms\\.txt$ /index.php?kismet_endpoint=llms [L,QSA]\n";
        $rules .= "RewriteRule ^\\.well-known/ai-plugin\\.json$ /index.php?kismet_endpoint=ai_plugin [L,QSA]\n";
        $rules .= "RewriteRule ^\\.well-known/mcp/servers\\.json$ /index.php?kismet_endpoint=mcp_servers [L,QSA]\n";
        $rules .= "</IfModule>\n";
        $rules .= self::MARKER_END;
        
        return $rules;
    }
    
    /**
     * Check if tracking rules are present in .htaccess
     */
    public static function has_tracking_rules() {
        $htaccess_file = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_file)) {
            return false;
        }
        
        $content = file_get_contents($htaccess_file);
        return strpos($content, self::MARKER_BEGIN) !== false;
    }
    
    /**
     * Get the status of .htaccess tracking setup
     */
    public static function get_status() {
        $htaccess_file = ABSPATH . '.htaccess';
        
        return [
            'file_exists' => file_exists($htaccess_file),
            'file_writable' => file_exists($htaccess_file) && is_writable($htaccess_file),
            'rules_present' => self::has_tracking_rules(),
            'file_path' => $htaccess_file
        ];
    }
} 