<?php
/**
 * Ask Installer - Installation Logic ONLY
 *
 * This class handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Ask_Installer {
    
    /**
     * Plugin activation - runs ONCE when plugin is activated
     */
    public static function activate() {
        error_log("KISMET INSTALLER: Ask endpoint activation starting");
        
        try {
            // Install database tables for chat logs ONE TIME
            self::create_database_tables();
            
            // Add rewrite rules for /ask endpoint
            self::add_rewrite_rules();
            
            // Flush rewrite rules to activate them
            flush_rewrite_rules();
            
            error_log("KISMET INSTALLER: Ask endpoint activation completed successfully");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Ask endpoint activation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation - runs ONCE when plugin is deactivated
     */
    public static function deactivate() {
        error_log("KISMET INSTALLER: Ask endpoint deactivation starting");
        
        try {
            // Clean up rewrite rules
            self::remove_rewrite_rules();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            error_log("KISMET INSTALLER: Ask endpoint deactivation completed");
            
        } catch (Exception $e) {
            error_log("KISMET INSTALLER ERROR: Ask endpoint deactivation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create database tables during activation
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kismet_chat_logs';
        
        // Check if table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            error_log("KISMET INSTALLER: Chat logs table already exists");
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_message text NOT NULL,
            assistant_response text NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_ip varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            status varchar(20) DEFAULT 'completed',
            PRIMARY KEY (id),
            INDEX idx_session_id (session_id),
            INDEX idx_timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log("KISMET INSTALLER: Chat logs database table created");
    }
    
    /**
     * Add rewrite rules for /ask endpoint
     */
    private static function add_rewrite_rules() {
        // Add rewrite rule for /ask endpoint
        add_rewrite_rule('^ask/?$', 'index.php?kismet_ask_endpoint=1', 'top');
        
        // Add query var
        add_filter('query_vars', array(self::class, 'add_query_vars'));
        
        error_log("KISMET INSTALLER: Added rewrite rules for /ask endpoint");
    }
    
    /**
     * Remove rewrite rules
     */
    private static function remove_rewrite_rules() {
        // WordPress will clean up rewrite rules on flush
        error_log("KISMET INSTALLER: Rewrite rules will be cleaned up on flush");
    }
    
    /**
     * Add query vars
     */
    public static function add_query_vars($vars) {
        $vars[] = 'kismet_ask_endpoint';
        return $vars;
    }
    
    /**
     * Database cleanup during plugin uninstall (not deactivation)
     */
    public static function uninstall() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kismet_chat_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        error_log("KISMET INSTALLER: Chat logs database table removed during uninstall");
    }
} 