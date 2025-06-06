<?php
/**
 * Handles robots.txt modifications
 * - Adds AI agent permissions
 * - Non-destructive approach using WordPress filters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Robots_Handler {
    
    public function __construct() {
        // COMMENT: This hooks into WordPress's robots_txt filter with priority 10
        // If another plugin uses higher priority, it might override our changes
        add_filter('robots_txt', array($this, 'modify_robots_txt'), 10, 2);
        
        // COMMENT: HEADER FIX ATTEMPT - DOES NOT WORK!
        // Tried using do_robotstxt action with priority 1 to force correct Content-Type header
        // Expected: content-type: text/plain; charset=UTF-8
        // Result: Headers completely unchanged, still text/html
        // Conclusion: do_robotstxt hook either doesn't work or isn't called properly
        // add_action('do_robotstxt', array($this, 'force_robots_content_type'), 1);
    }
    
    public function modify_robots_txt($output, $public) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("KISMET DEBUG [$timestamp]: robots_txt filter called, public=" . ($public ? 'true' : 'false'));
        
        // COMMENT: Debug what WordPress is giving us (but don't modify it!)
        $input_debug = str_replace(array("\r", "\n"), array('[CR]', '[LF]'), $output);
        error_log("KISMET DEBUG: WordPress input: '$input_debug'");
        error_log("KISMET DEBUG: Input length: " . strlen($output));
        
        // COMMENT: Only add our content if site is public
        if ($public) {
            // COMMENT: Using simple \n line endings (Content-Type header fix failed)
            $our_addition = "\n\n# Kismet AI integration\n";
            $our_addition .= "User-agent: *\n";
            $our_addition .= "Allow: /ask\n";
            $our_addition .= "Allow: /.well-known/ai-plugin.json\n";
            
            // COMMENT: Debug our addition
            $addition_debug = str_replace(array("\r", "\n"), array('[CR]', '[LF]'), $our_addition);
            error_log("KISMET DEBUG: Our addition: '$addition_debug'");
            
            // COMMENT: Simply append - don't modify WordPress's content at all!
            $final_output = $output . $our_addition;
            
            // COMMENT: Debug final result
            $final_debug = str_replace(array("\r", "\n"), array('[CR]', '[LF]'), $final_output);
            error_log("KISMET DEBUG: Final result: '$final_debug'");
            
            return $final_output;
        }
        
        // COMMENT: Site not public - return unchanged
        error_log("KISMET DEBUG: Site not public, returning original");
        return $output;
    }
    
    /*
    // COMMENT: HEADER FIX FUNCTION - DOES NOT WORK!
    // This function was supposed to force text/plain Content-Type but completely failed
    // Headers remained text/html regardless of this implementation
    public function force_robots_content_type() {
        // COMMENT: This runs specifically when WordPress serves robots.txt
        // Priority 1 ensures it runs before other plugins can interfere
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
            error_log("KISMET DEBUG: Forced Content-Type to text/plain via do_robotstxt action");
        } else {
            error_log("KISMET DEBUG: WARNING - Headers already sent in do_robotstxt action");
        }
    }
    */
} 