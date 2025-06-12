<?php
/**
 * Ask Content Logic
 *
 * This class defines the content and behavior for the /ask endpoint.
 * It handles ONE-TIME setup during plugin activation/deactivation.
 * It NEVER runs on page loads. NO init hooks.
 * 
 * RESPONSIBILITY: Define database schema and endpoint behavior for /ask
 * RUNS: Only during plugin activation/deactivation
 * 
 * @package Kismet_Ask_Proxy
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kismet_Ask_Content_Logic {
    
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
     * Generate content for /ask endpoint
     * 
     * This method generates the HTML page content for GET requests to /ask
     * POST requests are handled by the API proxy functionality
     */
    public static function generate_ask_content() {
        $site_name = get_bloginfo('name');
        
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - ' . esc_html($site_name) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }
        
        /* Header styling */
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
            z-index: 20;
            position: relative;
        }
        .logo { 
            font-size: 32px; 
            font-weight: bold; 
            margin-bottom: 8px; 
        }
        .tagline {
            font-size: 18px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .brand-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .brand-link:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        /* Modal overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Modal container */
        .modal-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 384px;
            animation: float 6s ease-in-out infinite;
        }
        
        /* Modal card */
        .modal-card {
            position: relative;
            background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
            border: 1px solid #d1d5db;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: shimmer 8s infinite;
        }
        
        /* Modal header */
        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid rgba(209, 213, 219, 0.5);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            text-align: center;
        }
        
        /* Modal content */
        .modal-content {
            padding: 24px;
        }
        
        /* Checklist items */
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            border: 1px solid;
            transition: all 0.2s ease;
            cursor: default;
        }
        .checklist-item:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .item-completed {
            background: rgba(240, 253, 244, 0.8);
            border-color: rgba(34, 197, 94, 0.3);
            animation: pulseGreen 4s ease-in-out infinite;
        }
        .item-pending {
            background: rgba(254, 242, 242, 0.8);
            border-color: rgba(239, 68, 68, 0.3);
            animation: pulseRed 4s ease-in-out infinite;
        }
        
        /* Status icons */
        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
            font-weight: bold;
        }
        .icon-completed {
            background: #22c55e;
            border-color: #16a34a;
            color: white;
            animation: glowGreen 2s ease-in-out infinite;
        }
        .icon-pending {
            background: #ef4444;
            border-color: #dc2626;
            color: white;
            animation: glowRed 2s ease-in-out infinite;
        }
        
        /* Item text */
        .item-text {
            font-weight: 500;
            font-size: 14px;
            color: #374151;
        }
        
        /* Background gradients */
        .bg-gradient-1 {
            position: absolute;
            top: -150px;
            right: -150px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
            border-radius: 50%;
            filter: blur(60px);
            animation: pulseSlow 10s ease-in-out infinite;
        }
        .bg-gradient-2 {
            position: absolute;
            bottom: -150px;
            left: -150px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.2), rgba(6, 182, 212, 0.2));
            border-radius: 50%;
            filter: blur(60px);
            animation: pulseSlow 10s ease-in-out infinite;
            animation-delay: 5s;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        @keyframes pulseGreen {
            0%, 100% { background-color: rgba(240, 253, 244, 0.8); }
            50% { background-color: rgba(220, 252, 231, 0.9); }
        }
        
        @keyframes pulseRed {
            0%, 100% { background-color: rgba(254, 242, 242, 0.8); }
            50% { background-color: rgba(254, 226, 226, 0.9); }
        }
        
        @keyframes glowGreen {
            0%, 100% { box-shadow: 0 0 0 rgba(34, 197, 94, 0); }
            50% { box-shadow: 0 0 8px rgba(34, 197, 94, 0.6); }
        }
        
        @keyframes glowRed {
            0%, 100% { box-shadow: 0 0 0 rgba(239, 68, 68, 0); }
            50% { box-shadow: 0 0 8px rgba(239, 68, 68, 0.6); }
        }
        
        @keyframes pulseSlow {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }
        
        /* Responsive design */
        @media (max-width: 480px) {
            .modal-container { max-width: 340px; }
            .modal-content, .modal-header { padding: 16px; }
            .logo { font-size: 28px; }
            .tagline { font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🤖 Kismet d2g AI</div>
        <div class="tagline">Turning your website AI ready</div>
        <a href="https://makekismet.com" target="_blank" class="brand-link">
            Learn more at makekismet.com →
        </a>
    </div>
    
    <div class="modal-overlay"></div>
    
    <div class="modal-container">
        <div class="modal-card">
            <div class="modal-header">
                <h2 class="modal-title">AI Ready Checklist</h2>
            </div>
            
            <div class="modal-content">
                <div class="checklist-item item-completed" style="animation-delay: 0s;">
                    <div class="status-icon icon-completed">✓</div>
                    <span class="item-text">Answering Bots</span>
                </div>
                
                <div class="checklist-item item-completed" style="animation-delay: 0.7s;">
                    <div class="status-icon icon-completed">✓</div>
                    <span class="item-text">Tracking Visits</span>
                </div>
                
                <div class="checklist-item item-completed" style="animation-delay: 1.4s;">
                    <div class="status-icon icon-completed">✓</div>
                    <span class="item-text">Optimizing AEO</span>
                </div>
                
                <div class="checklist-item item-pending" style="animation-delay: 2.1s;">
                    <div class="status-icon icon-pending">✕</div>
                    <span class="item-text">Serving Social Media</span>
                </div>
                
                <div class="checklist-item item-pending" style="animation-delay: 2.8s;">
                    <div class="status-icon icon-pending">✕</div>
                    <span class="item-text">Linking Identity Graph</span>
                </div>
                
                <div class="checklist-item item-pending" style="animation-delay: 3.5s;">
                    <div class="status-icon icon-pending">✕</div>
                    <span class="item-text">Booking Agentically</span>
                </div>
            </div>
            
            <div class="bg-gradient-1"></div>
            <div class="bg-gradient-2"></div>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Create database tables during activation
     */
    public static function create_database_tables() {
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