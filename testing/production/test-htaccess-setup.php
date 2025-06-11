<?php
/**
 * Kismet Tracking - .htaccess Setup Test
 * 
 * This script helps enable and test the .htaccess rewrite rules that force
 * tracked endpoints through WordPress even when physical files exist.
 * 
 * Place this file in your WordPress root directory and access it via browser.
 */

// Include WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Include our htaccess manager
require_once(plugin_dir_path(__FILE__) . 'includes/tracking/class-htaccess-manager.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Kismet .htaccess Setup Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
        .action-buttons { margin: 20px 0; }
        .action-buttons button { margin-right: 10px; padding: 8px 16px; }
    </style>
</head>
<body>
    <h1>Kismet .htaccess Setup Test</h1>
    
    <?php
    // Handle actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_rules':
                $result = Kismet_Htaccess_Manager::add_tracking_rules();
                if ($result) {
                    echo '<p class="success">✅ Successfully added .htaccess tracking rules!</p>';
                } else {
                    echo '<p class="error">❌ Failed to add .htaccess tracking rules. Check file permissions.</p>';
                }
                break;
                
            case 'remove_rules':
                $result = Kismet_Htaccess_Manager::remove_tracking_rules();
                if ($result) {
                    echo '<p class="success">✅ Successfully removed .htaccess tracking rules!</p>';
                } else {
                    echo '<p class="error">❌ Failed to remove .htaccess tracking rules.</p>';
                }
                break;
        }
    }
    
    // Get current status
    $status = Kismet_Htaccess_Manager::get_status();
    ?>
    
    <h2>Current Status</h2>
    <ul>
        <li><strong>.htaccess file exists:</strong> 
            <span class="<?= $status['file_exists'] ? 'success' : 'error' ?>">
                <?= $status['file_exists'] ? 'YES' : 'NO' ?>
            </span>
        </li>
        <li><strong>.htaccess file writable:</strong> 
            <span class="<?= $status['file_writable'] ? 'success' : 'error' ?>">
                <?= $status['file_writable'] ? 'YES' : 'NO' ?>
            </span>
        </li>
        <li><strong>Tracking rules present:</strong> 
            <span class="<?= $status['rules_present'] ? 'success' : 'warning' ?>">
                <?= $status['rules_present'] ? 'YES' : 'NO' ?>
            </span>
        </li>
        <li><strong>File path:</strong> <code><?= esc_html($status['file_path']) ?></code></li>
    </ul>
    
    <div class="action-buttons">
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="add_rules">
            <button type="submit" <?= !$status['file_writable'] ? 'disabled' : '' ?>>
                Add Tracking Rules
            </button>
        </form>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="remove_rules">
            <button type="submit" <?= !$status['rules_present'] ? 'disabled' : '' ?>>
                Remove Tracking Rules
            </button>
        </form>
    </div>
    
    <?php if ($status['rules_present']): ?>
    <h2>What This Does</h2>
    <p class="info">The tracking rules intercept requests to specific endpoints and force them through WordPress for tracking, even when physical files exist. Here's what gets added to your .htaccess:</p>
    
    <pre># BEGIN Kismet Tracking
&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
# Force tracked endpoints through WordPress even if physical files exist
RewriteRule ^robots\.txt$ /index.php?kismet_endpoint=robots [L,QSA]
RewriteRule ^llms\.txt$ /index.php?kismet_endpoint=llms [L,QSA]
RewriteRule ^\.well-known/ai-plugin\.json$ /index.php?kismet_endpoint=ai_plugin [L,QSA]
RewriteRule ^\.well-known/mcp/servers$ /index.php?kismet_endpoint=mcp_servers [L,QSA]
&lt;/IfModule&gt;
# END Kismet Tracking</pre>
    <?php endif; ?>
    
    <h2>Test Endpoints</h2>
    <p>After adding the rules, test these endpoints to verify tracking is working:</p>
    <ul>
        <li><a href="/robots.txt" target="_blank">robots.txt</a></li>
        <li><a href="/llms.txt" target="_blank">llms.txt</a></li>
        <li><a href="/.well-known/ai-plugin.json" target="_blank">.well-known/ai-plugin.json</a></li>
        <li><a href="/.well-known/mcp/servers" target="_blank">.well-known/mcp/servers</a></li>
    </ul>
    
    <h2>How to Verify</h2>
    <ol>
        <li>Add the tracking rules using the button above</li>
        <li>Check your WordPress debug log (usually in wp-content/debug.log)</li>
        <li>Access one of the test endpoints above</li>
        <li>Look for log entries like: <code>KISMET DEBUG: Handling .htaccess rewrite for endpoint: robots</code></li>
        <li>Verify the endpoint still serves content correctly</li>
    </ol>
    
    <?php if (!$status['file_writable']): ?>
    <div class="error">
        <h3>❌ Permission Issue</h3>
        <p>The .htaccess file is not writable. You need to:</p>
        <ol>
            <li>Make the file writable: <code>chmod 644 .htaccess</code></li>
            <li>Or add the rules manually to your .htaccess file</li>
        </ol>
    </div>
    <?php endif; ?>
    
</body>
</html> 